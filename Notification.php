<?php
/**
 * models/Notification.php
 * Data access for the in-app notification center.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Notification
{
    public static function listForUser(int $userId, int $limit = 50): array
    {
        $stmt = get_db()->prepare(
            'SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = get_db()->prepare(
            'SELECT COUNT(*) c FROM notifications WHERE user_id = :uid AND read_at IS NULL'
        );
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetch()['c'];
    }

    public static function markRead(int $id, int $userId): bool
    {
        $stmt = get_db()->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE id = :id AND user_id = :uid AND read_at IS NULL'
        );
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public static function markAllRead(int $userId): bool
    {
        $stmt = get_db()->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = :uid AND read_at IS NULL'
        );
        return $stmt->execute(['uid' => $userId]);
    }

    /**
     * Creates a notification row. delivery_status defaults to "delivered"
     * because in-app storage IS the delivery mechanism here; there is no
     * external push service in this build (see README limitations).
     */
    public static function create(int $userId, ?int $reminderId, string $title, string $message, string $type): int
    {
        $stmt = get_db()->prepare(
            'INSERT INTO notifications (user_id, reminder_id, title, message, notification_type, delivery_status)
             VALUES (:uid, :rid, :title, :msg, :type, "delivered")'
        );
        $stmt->execute([
            'uid' => $userId, 'rid' => $reminderId, 'title' => $title, 'msg' => $message, 'type' => $type,
        ]);
        return (int) get_db()->lastInsertId();
    }
}
