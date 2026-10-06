<?php
/**
 * controllers/SettingsController.php
 * Handles POST actions from settings.php: update preferences, delete account.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Preference.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/database.php';

class SettingsController
{
    /** @return array{success:bool, error:?string} */
    public static function handle(array $post, int $userId): array
    {
        $action = $post['action'] ?? '';

        try {
            switch ($action) {
                case 'update_preferences':
                    return self::updatePreferences($post, $userId);
                case 'delete_account':
                    return self::deleteAccount($post, $userId);
                default:
                    return ['success' => false, 'error' => 'Unknown action.'];
            }
        } catch (Throwable $e) {
            error_log('[CheckNGo] SettingsController error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Something went wrong. Please try again.'];
        }
    }

    private static function updatePreferences(array $post, int $userId): array
    {
        $intervals = [
            'preparation' => max(0, (int) ($post['interval_preparation'] ?? 30)),
            'checklist'   => max(0, (int) ($post['interval_checklist'] ?? 15)),
            'final_check' => max(0, (int) ($post['interval_final_check'] ?? 5)),
        ];

        $notificationPrefs = [
            'in_app'  => isset($post['notify_in_app']),
            'browser' => isset($post['notify_browser']),
        ];

        $theme = ($post['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';

        Preference::update($userId, $intervals, $notificationPrefs, $theme);
        return ['success' => true, 'error' => null];
    }

    private static function deleteAccount(array $post, int $userId): array
    {
        $password = (string) ($post['confirm_password'] ?? '');
        $user = User::findById($userId);

        if (!$user || !User::verifyPassword($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Incorrect password. Account was not deleted.'];
        }

        // All child tables reference users.id with ON DELETE CASCADE,
        // so this single delete removes every belonging, activity,
        // template, session, schedule, reminder, and notification too.
        $stmt = get_db()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        return ['success' => true, 'error' => null];
    }
}
