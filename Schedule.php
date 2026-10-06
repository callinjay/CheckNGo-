<?php
/**
 * models/Schedule.php
 * CRUD for departure_schedules, scoped per user.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Schedule
{
    public static function listForUser(int $userId): array
    {
        $stmt = get_db()->prepare(
            'SELECT ds.*, a.activity_name, a.destination_label
             FROM departure_schedules ds
             JOIN activities a ON a.id = ds.activity_id
             WHERE ds.user_id = :uid
             ORDER BY ds.departure_date DESC, ds.departure_time DESC'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $stmt = get_db()->prepare(
            'SELECT ds.*, a.activity_name, a.destination_label, a.activity_type
             FROM departure_schedules ds
             JOIN activities a ON a.id = ds.activity_id
             WHERE ds.id = :id AND ds.user_id = :uid LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, int $activityId, int $templateId, string $date, string $time): int
    {
        $stmt = get_db()->prepare(
            'INSERT INTO departure_schedules (user_id, activity_id, template_id, departure_date, departure_time, status)
             VALUES (:uid, :aid, :tid, :date, :time, "upcoming")'
        );
        $stmt->execute([
            'uid' => $userId, 'aid' => $activityId, 'tid' => $templateId, 'date' => $date, 'time' => $time,
        ]);
        return (int) get_db()->lastInsertId();
    }

    public static function cancel(int $id, int $userId): bool
    {
        $db = get_db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'UPDATE departure_schedules SET status = "cancelled" WHERE id = :id AND user_id = :uid'
            );
            $stmt->execute(['id' => $id, 'uid' => $userId]);

            $db->prepare(
                'UPDATE reminders SET status = "cancelled"
                 WHERE departure_schedule_id = :id AND user_id = :uid AND status = "pending"'
            )->execute(['id' => $id, 'uid' => $userId]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[CheckNGo] Schedule::cancel failed: ' . $e->getMessage());
            return false;
        }
    }
}
