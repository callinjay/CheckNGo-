<?php
/**
 * controllers/DashboardController.php
 * Pulls together the data the dashboard view needs, scoped to the
 * currently authenticated user only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ReadinessService.php';

class DashboardController
{
    /** The user's most recent in-progress checklist session, if any. */
    public static function getActiveSession(int $userId): ?array
    {
        $stmt = get_db()->prepare(
            'SELECT cs.*, a.activity_name, a.destination_label
             FROM checklist_sessions cs
             JOIN activities a ON a.id = cs.activity_id
             WHERE cs.user_id = :uid AND cs.session_status = "in_progress"
             ORDER BY cs.created_at DESC LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Items (with check state) belonging to a session, for readiness calc. */
    public static function getSessionItems(int $sessionId): array
    {
        $stmt = get_db()->prepare(
            'SELECT item_name_snapshot AS item_name, priority_snapshot AS priority, is_checked
             FROM checklist_session_items WHERE session_id = :sid'
        );
        $stmt->execute(['sid' => $sessionId]);
        return $stmt->fetchAll();
    }

    /** The next upcoming departure schedule for this user. */
    public static function getNextDeparture(int $userId): ?array
    {
        $stmt = get_db()->prepare(
            'SELECT ds.*, a.activity_name, a.destination_label
             FROM departure_schedules ds
             JOIN activities a ON a.id = ds.activity_id
             WHERE ds.user_id = :uid AND ds.status = "upcoming"
             ORDER BY ds.departure_date ASC, ds.departure_time ASC
             LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Frequently unchecked items across this user's history (rule-based). */
    public static function getFrequentlyUnchecked(int $userId, int $limit = 3): array
    {
        $stmt = get_db()->prepare(
            'SELECT b.item_name, ics.checked_count, ics.unchecked_count
             FROM item_check_stats ics
             JOIN belongings b ON b.id = ics.belonging_id
             WHERE ics.user_id = :uid AND ics.unchecked_count > 0
             ORDER BY ics.unchecked_count DESC
             LIMIT :lim'
        );
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Recent completed sessions for the "recent activity" widget. */
    public static function getRecentHistory(int $userId, int $limit = 5): array
    {
        $stmt = get_db()->prepare(
            'SELECT cs.id, cs.session_date, cs.readiness_score, cs.session_status,
                    cs.departure_confirmed, a.activity_name
             FROM checklist_sessions cs
             JOIN activities a ON a.id = cs.activity_id
             WHERE cs.user_id = :uid
             ORDER BY cs.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
