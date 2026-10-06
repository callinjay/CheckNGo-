<?php
/**
 * models/Reminder.php
 * Data access for the reminders table.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Reminder
{
    public const TYPES = ['preparation', 'checklist', 'final_check', 'departure'];

    public static function listForSchedule(int $scheduleId): array
    {
        $stmt = get_db()->prepare(
            'SELECT * FROM reminders WHERE departure_schedule_id = :sid ORDER BY reminder_datetime ASC'
        );
        $stmt->execute(['sid' => $scheduleId]);
        return $stmt->fetchAll();
    }

    public static function listForUser(int $userId, string $statusFilter = ''): array
    {
        $sql = 'SELECT r.*, ds.departure_date, ds.departure_time, a.activity_name
                FROM reminders r
                JOIN departure_schedules ds ON ds.id = r.departure_schedule_id
                JOIN activities a ON a.id = ds.activity_id
                WHERE r.user_id = :uid';
        $params = ['uid' => $userId];
        if ($statusFilter !== '') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $statusFilter;
        }
        $sql .= ' ORDER BY r.reminder_datetime ASC';

        $stmt = get_db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Inserts a reminder row; silently skips if one of this type already
     * exists for the schedule (enforced by the DB's unique key too — this
     * check avoids relying on catching a duplicate-key exception).
     */
    public static function createIfMissing(int $userId, int $scheduleId, string $type, string $datetime): bool
    {
        $check = get_db()->prepare(
            'SELECT id FROM reminders WHERE departure_schedule_id = :sid AND reminder_type = :type LIMIT 1'
        );
        $check->execute(['sid' => $scheduleId, 'type' => $type]);
        if ($check->fetch()) {
            return false;
        }

        $stmt = get_db()->prepare(
            'INSERT INTO reminders (user_id, departure_schedule_id, reminder_type, reminder_datetime, status)
             VALUES (:uid, :sid, :type, :dt, "pending")'
        );
        return $stmt->execute(['uid' => $userId, 'sid' => $scheduleId, 'type' => $type, 'dt' => $datetime]);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = get_db()->prepare('UPDATE reminders SET status = :status WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** All reminders that are due and still pending — used by the cron processor. System-wide, not user-scoped. */
    public static function findDue(): array
    {
        $stmt = get_db()->prepare(
            'SELECT * FROM reminders WHERE status = "pending" AND reminder_datetime <= NOW()
             ORDER BY reminder_datetime ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
