<?php
/**
 * controllers/ProfileController.php
 * Handles POST actions from profile.php: update profile info, change password.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';

class ProfileController
{
    /** @return array{success:bool, error:?string} */
    public static function handle(array $post, int $userId): array
    {
        $action = $post['action'] ?? '';

        try {
            switch ($action) {
                case 'update_profile':
                    return self::updateProfile($post, $userId);
                case 'change_password':
                    return self::changePassword($post, $userId);
                default:
                    return ['success' => false, 'error' => 'Unknown action.'];
            }
        } catch (Throwable $e) {
            error_log('[CheckNGo] ProfileController error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Something went wrong. Please try again.'];
        }
    }

    private static function updateProfile(array $post, int $userId): array
    {
        $fullName = trim((string) ($post['full_name'] ?? ''));
        if ($fullName === '' || mb_strlen($fullName) > 150) {
            return ['success' => false, 'error' => 'Please enter a valid name.'];
        }
        User::updateProfile($userId, $fullName);
        $_SESSION['user_name'] = $fullName;
        return ['success' => true, 'error' => null];
    }

    private static function changePassword(array $post, int $userId): array
    {
        $current = (string) ($post['current_password'] ?? '');
        $new = (string) ($post['new_password'] ?? '');
        $confirm = (string) ($post['confirm_password'] ?? '');

        $user = User::findById($userId);
        if (!$user || !User::verifyPassword($current, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Your current password is incorrect.'];
        }
        if (mb_strlen($new) < 8) {
            return ['success' => false, 'error' => 'New password must be at least 8 characters.'];
        }
        if ($new !== $confirm) {
            return ['success' => false, 'error' => 'New passwords do not match.'];
        }

        User::changePassword($userId, $new);
        return ['success' => true, 'error' => null];
    }
}
