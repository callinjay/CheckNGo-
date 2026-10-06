<?php
/**
 * models/Preference.php
 * Data access for user_preferences (notification prefs, reminder
 * intervals, theme).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Preference
{
    public static function get(int $userId): array
    {
        $stmt = get_db()->prepare('SELECT * FROM user_preferences WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            // Should not normally happen (created at registration), but fail safe.
            $insert = get_db()->prepare(
                'INSERT INTO user_preferences (user_id, default_reminder_intervals, notification_preferences)
                 VALUES (:uid, :intervals, :prefs)'
            );
            $insert->execute([
                'uid' => $userId,
                'intervals' => json_encode([30, 15, 5, 0]),
                'prefs' => json_encode(['browser' => false, 'in_app' => true]),
            ]);
            return self::get($userId);
        }

        $row['default_reminder_intervals'] = json_decode($row['default_reminder_intervals'] ?? '[]', true) ?: [];
        $row['notification_preferences'] = json_decode($row['notification_preferences'] ?? '{}', true) ?: [];
        return $row;
    }

    public static function update(int $userId, array $intervals, array $notificationPrefs, string $theme): bool
    {
        $stmt = get_db()->prepare(
            'UPDATE user_preferences
             SET default_reminder_intervals = :intervals, notification_preferences = :prefs, theme = :theme
             WHERE user_id = :uid'
        );
        return $stmt->execute([
            'intervals' => json_encode(array_values($intervals)),
            'prefs' => json_encode($notificationPrefs),
            'theme' => $theme,
            'uid' => $userId,
        ]);
    }
}
