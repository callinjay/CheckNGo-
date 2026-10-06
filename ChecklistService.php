<?php
/**
 * services/ChecklistService.php
 * Orchestrates checklist_sessions + checklist_session_items — the
 * "generate checklist -> check items -> confirm departure" workflow.
 * Session items are SNAPSHOTS of the template at generation time, so
 * later edits to belongings/templates never rewrite history.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Checklist.php';
require_once __DIR__ . '/ReadinessService.php';

class ChecklistService
{
    /**
     * Resumes today's in-progress session for this activity, or generates
     * a new one from the default template.
     */
    public static function startOrResume(int $userId, int $activityId, ?int $scheduleId = null): int
    {
        $db = get_db();

        $existing = $db->prepare(
            'SELECT id FROM checklist_sessions
             WHERE user_id = :uid AND activity_id = :aid AND session_status = "in_progress"
             AND session_date = CURDATE() LIMIT 1'
        );
        $existing->execute(['uid' => $userId, 'aid' => $activityId]);
        $row = $existing->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        $template = Checklist::getOrCreateDefaultTemplate($userId, $activityId);
        $items = Checklist::getTemplateItems((int) $template['id']);

        if (empty($items)) {
            throw new RuntimeException('This activity has no checklist items yet. Add items in Manage Template first.');
        }

        $db->beginTransaction();
        try {
            $insertSession = $db->prepare(
                'INSERT INTO checklist_sessions
                    (user_id, activity_id, template_id, departure_schedule_id, session_date, start_time, session_status)
                 VALUES (:uid, :aid, :tid, :sid, CURDATE(), NOW(), "in_progress")'
            );
            $insertSession->execute([
                'uid' => $userId, 'aid' => $activityId, 'tid' => $template['id'], 'sid' => $scheduleId,
            ]);
            $sessionId = (int) $db->lastInsertId();

            $insertItem = $db->prepare(
                'INSERT INTO checklist_session_items
                    (session_id, belonging_id, item_name_snapshot, priority_snapshot, is_required_snapshot, is_checked)
                 VALUES (:sid, :bid, :name, :priority, :required, 0)'
            );
            foreach ($items as $item) {
                $insertItem->execute([
                    'sid' => $sessionId,
                    'bid' => $item['belonging_id'],
                    'name' => $item['item_name'],
                    'priority' => $item['priority'],
                    'required' => $item['is_required'],
                ]);
            }

            $db->commit();
            return $sessionId;
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[CheckNGo] startOrResume failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function getSession(int $sessionId, int $userId): ?array
    {
        $stmt = get_db()->prepare(
            'SELECT cs.*, a.activity_name, a.destination_label
             FROM checklist_sessions cs JOIN activities a ON a.id = cs.activity_id
             WHERE cs.id = :id AND cs.user_id = :uid LIMIT 1'
        );
        $stmt->execute(['id' => $sessionId, 'uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function getItems(int $sessionId): array
    {
        $stmt = get_db()->prepare(
            'SELECT * FROM checklist_session_items WHERE session_id = :sid ORDER BY
             FIELD(priority_snapshot, "critical","important","optional"), item_name_snapshot'
        );
        $stmt->execute(['sid' => $sessionId]);
        return $stmt->fetchAll();
    }

    /**
     * Toggles one item's checked state. Verifies the item belongs to a
     * session owned by $userId before writing (ownership check).
     */
    public static function toggleItem(int $sessionItemId, int $userId, bool $checked): array
    {
        $db = get_db();

        $verify = $db->prepare(
            'SELECT csi.session_id FROM checklist_session_items csi
             JOIN checklist_sessions cs ON cs.id = csi.session_id
             WHERE csi.id = :id AND cs.user_id = :uid LIMIT 1'
        );
        $verify->execute(['id' => $sessionItemId, 'uid' => $userId]);
        $row = $verify->fetch();
        if (!$row) {
            throw new RuntimeException('Item not found.');
        }
        $sessionId = (int) $row['session_id'];

        $update = $db->prepare(
            'UPDATE checklist_session_items SET is_checked = :checked, checked_at = :ts WHERE id = :id'
        );
        $update->execute([
            'checked' => $checked ? 1 : 0,
            'ts' => $checked ? date('Y-m-d H:i:s') : null,
            'id' => $sessionItemId,
        ]);

        $items = self::getItems($sessionId);
        $readiness = ReadinessService::calculate(array_map(
            fn ($i) => ['priority' => $i['priority_snapshot'], 'is_checked' => $i['is_checked']],
            $items
        ));

        // Persist the running readiness score so dashboard/history stay in sync.
        $db->prepare('UPDATE checklist_sessions SET readiness_score = :pct WHERE id = :id')
           ->execute(['pct' => $readiness['percent'], 'id' => $sessionId]);

        return $readiness;
    }

    /**
     * Finalizes a session: marks it completed, records departure confirmation,
     * and updates the personalization stats (item_check_stats) used for
     * "frequently unchecked" recommendations.
     *
     * @param bool $force If true, allows confirming even with unchecked critical items.
     * @return array{success:bool, readiness: array, forced: bool}
     */
    public static function confirmDeparture(int $sessionId, int $userId, bool $force = false): array
    {
        $db = get_db();
        $session = self::getSession($sessionId, $userId);
        if (!$session) {
            throw new RuntimeException('Session not found.');
        }

        $items = self::getItems($sessionId);
        $readiness = ReadinessService::calculate(array_map(
            fn ($i) => ['priority' => $i['priority_snapshot'], 'is_checked' => $i['is_checked']],
            $items
        ));

        if ($readiness['has_unchecked_critical'] && !$force) {
            return ['success' => false, 'readiness' => $readiness, 'forced' => false];
        }

        $db->beginTransaction();
        try {
            $db->prepare(
                'UPDATE checklist_sessions
                 SET session_status = "completed", completion_time = NOW(),
                     readiness_score = :pct, departure_confirmed = 1
                 WHERE id = :id'
            )->execute(['pct' => $readiness['percent'], 'id' => $sessionId]);

            if (!empty($session['departure_schedule_id'])) {
                $db->prepare('UPDATE departure_schedules SET status = "completed" WHERE id = :id')
                   ->execute(['id' => $session['departure_schedule_id']]);
            }

            // Update rule-based personalization stats per item.
            $upsert = $db->prepare(
                'INSERT INTO item_check_stats (user_id, activity_id, belonging_id, checked_count, unchecked_count)
                 VALUES (:uid, :aid, :bid, :checked, :unchecked)
                 ON DUPLICATE KEY UPDATE
                    checked_count = checked_count + VALUES(checked_count),
                    unchecked_count = unchecked_count + VALUES(unchecked_count)'
            );
            foreach ($items as $item) {
                if (empty($item['belonging_id'])) {
                    continue; // source item was later deleted; skip stats
                }
                $upsert->execute([
                    'uid' => $userId,
                    'aid' => $session['activity_id'],
                    'bid' => $item['belonging_id'],
                    'checked' => $item['is_checked'] ? 1 : 0,
                    'unchecked' => $item['is_checked'] ? 0 : 1,
                ]);
            }

            $db->commit();
            return ['success' => true, 'readiness' => $readiness, 'forced' => $force];
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[CheckNGo] confirmDeparture failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
