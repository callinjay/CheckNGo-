<?php
/**
 * controllers/HistoryController.php
 * Aggregates checklist_sessions into the filtered list + statistics
 * shown on history.php. All statistics are computed directly from
 * database records — nothing here is fabricated.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class HistoryController
{
    /**
     * @param array{period?:string, activity_type?:string, status?:string} $filters
     *   period: '' | 'today' | 'week' | 'month'
     *   status: '' | 'completed' | 'incomplete'
     */
    public static function listSessions(int $userId, array $filters): array
    {
        $sql = 'SELECT cs.*, a.activity_name, a.activity_type
                FROM checklist_sessions cs
                JOIN activities a ON a.id = cs.activity_id
                WHERE cs.user_id = :uid';
        $params = ['uid' => $userId];

        $period = $filters['period'] ?? '';
        if ($period === 'today') {
            $sql .= ' AND cs.session_date = CURDATE()';
        } elseif ($period === 'week') {
            $sql .= ' AND YEARWEEK(cs.session_date, 1) = YEARWEEK(CURDATE(), 1)';
        } elseif ($period === 'month') {
            $sql .= ' AND YEAR(cs.session_date) = YEAR(CURDATE()) AND MONTH(cs.session_date) = MONTH(CURDATE())';
        }

        $activityType = $filters['activity_type'] ?? '';
        if ($activityType !== '') {
            $sql .= ' AND a.activity_type = :atype';
            $params['atype'] = $activityType;
        }

        $status = $filters['status'] ?? '';
        if ($status === 'completed') {
            $sql .= ' AND cs.session_status = "completed"';
        } elseif ($status === 'incomplete') {
            $sql .= ' AND cs.session_status IN ("in_progress","abandoned")';
        }

        $sql .= ' ORDER BY cs.session_date DESC, cs.created_at DESC LIMIT 100';

        $stmt = get_db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array{total:int, completed:int, incomplete:int, average_readiness:float} */
    public static function getSummaryStats(int $userId): array
    {
        $stmt = get_db()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN session_status = "completed" THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN session_status <> "completed" THEN 1 ELSE 0 END) AS incomplete,
                COALESCE(AVG(CASE WHEN session_status = "completed" THEN readiness_score END), 0) AS avg_readiness
             FROM checklist_sessions WHERE user_id = :uid'
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'incomplete' => (int) ($row['incomplete'] ?? 0),
            'average_readiness' => round((float) ($row['avg_readiness'] ?? 0), 1),
        ];
    }

    /** Most frequently completed activity, by count of completed sessions. */
    public static function getMostFrequentActivity(int $userId): ?array
    {
        $stmt = get_db()->prepare(
            'SELECT a.activity_name, COUNT(*) AS c
             FROM checklist_sessions cs JOIN activities a ON a.id = cs.activity_id
             WHERE cs.user_id = :uid AND cs.session_status = "completed"
             GROUP BY a.activity_name ORDER BY c DESC LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Frequently unchecked items across all history (used for the panel on this page too). */
    public static function getFrequentlyUnchecked(int $userId, int $limit = 5): array
    {
        $stmt = get_db()->prepare(
            'SELECT b.item_name, a.activity_name, ics.checked_count, ics.unchecked_count
             FROM item_check_stats ics
             JOIN belongings b ON b.id = ics.belonging_id
             JOIN activities a ON a.id = ics.activity_id
             WHERE ics.user_id = :uid AND ics.unchecked_count > 0
             ORDER BY ics.unchecked_count DESC LIMIT :lim'
        );
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Readiness score of the last N completed sessions, oldest first — for a trend line. */
    public static function getReadinessTrend(int $userId, int $limit = 10): array
    {
        $stmt = get_db()->prepare(
            'SELECT session_date, readiness_score FROM checklist_sessions
             WHERE user_id = :uid AND session_status = "completed"
             ORDER BY session_date DESC, created_at DESC LIMIT :lim'
        );
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll());
    }

    /** Sessions grouped by ISO week, for the last ~8 weeks — weekly activity summary. */
    public static function getWeeklySummary(int $userId): array
    {
        $stmt = get_db()->prepare(
            'SELECT YEARWEEK(session_date, 1) AS yw, MIN(session_date) AS week_start, COUNT(*) AS sessions,
                    SUM(CASE WHEN session_status = "completed" THEN 1 ELSE 0 END) AS completed
             FROM checklist_sessions WHERE user_id = :uid
             AND session_date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
             GROUP BY yw ORDER BY yw ASC'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }
}
