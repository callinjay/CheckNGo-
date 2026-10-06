<?php
/**
 * services/RecommendationService.php
 *
 * Rule-based personalization (NOT AI/ML): reads item_check_stats, which
 * is updated every time a checklist session is confirmed
 * (see ChecklistService::confirmDeparture). If a belonging has been left
 * unchecked at least MIN_UNCHECKED times for a given activity, and the
 * user hasn't already accepted or dismissed that suggestion, it surfaces
 * as a recommendation: "You often forget this — prioritize it?"
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Checklist.php';

class RecommendationService
{
    private const MIN_UNCHECKED = 2;

    /**
     * @return array<int, array{belonging_id:int, activity_id:int, item_name:string,
     *                           activity_name:string, checked_count:int, unchecked_count:int}>
     */
    public static function getActiveRecommendations(int $userId, int $limit = 5): array
    {
        $stmt = get_db()->prepare(
            'SELECT ics.belonging_id, ics.activity_id, b.item_name, a.activity_name,
                    ics.checked_count, ics.unchecked_count
             FROM item_check_stats ics
             JOIN belongings b ON b.id = ics.belonging_id AND b.is_active = 1
             JOIN activities a ON a.id = ics.activity_id
             WHERE ics.user_id = :uid AND ics.unchecked_count >= :min
               AND NOT EXISTS (
                   SELECT 1 FROM recommendation_actions ra
                   WHERE ra.user_id = ics.user_id AND ra.activity_id = ics.activity_id
                     AND ra.belonging_id = ics.belonging_id
               )
             ORDER BY ics.unchecked_count DESC
             LIMIT :lim'
        );
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('min', self::MIN_UNCHECKED, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Accepts a recommendation: bumps the item's priority to "critical" in that activity's default template. */
    public static function accept(int $userId, int $activityId, int $belongingId): bool
    {
        $db = get_db();
        $db->beginTransaction();
        try {
            $template = Checklist::getOrCreateDefaultTemplate($userId, $activityId);

            $find = $db->prepare(
                'SELECT id FROM checklist_template_items WHERE template_id = :tid AND belonging_id = :bid'
            );
            $find->execute(['tid' => $template['id'], 'bid' => $belongingId]);
            $item = $find->fetch();

            if ($item) {
                Checklist::updatePriority((int) $item['id'], (int) $template['id'], 'critical');
            } else {
                // Not currently in the template (e.g. was removed) — add it back as critical.
                Checklist::addItem((int) $template['id'], $belongingId, 'critical', true);
            }

            self::recordAction($userId, $activityId, $belongingId, 'accepted');
            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[CheckNGo] RecommendationService::accept failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function dismiss(int $userId, int $activityId, int $belongingId): bool
    {
        return self::recordAction($userId, $activityId, $belongingId, 'dismissed');
    }

    private static function recordAction(int $userId, int $activityId, int $belongingId, string $action): bool
    {
        $stmt = get_db()->prepare(
            'INSERT INTO recommendation_actions (user_id, activity_id, belonging_id, action)
             VALUES (:uid, :aid, :bid, :action)
             ON DUPLICATE KEY UPDATE action = VALUES(action), created_at = NOW()'
        );
        return $stmt->execute(['uid' => $userId, 'aid' => $activityId, 'bid' => $belongingId, 'action' => $action]);
    }
}
