<?php
/**
 * models/User.php
 * Data-access for the users table. All queries are scoped and use
 * prepared statements — no user input is ever concatenated into SQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function emailExists(string $email): bool
    {
        return self::findByEmail($email) !== null;
    }

    /**
     * Creates a new user and a default preferences row. Returns the new user id.
     */
    public static function create(string $fullName, string $email, string $plainPassword): int
    {
        $db = get_db();
        $db->beginTransaction();

        try {
            $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

            $stmt = $db->prepare(
                'INSERT INTO users (full_name, email, password_hash) VALUES (:name, :email, :hash)'
            );
            $stmt->execute([
                'name'  => $fullName,
                'email' => $email,
                'hash'  => $hash,
            ]);

            $userId = (int) $db->lastInsertId();

            $prefStmt = $db->prepare(
                'INSERT INTO user_preferences (user_id, default_reminder_intervals, notification_preferences)
                 VALUES (:uid, :intervals, :prefs)'
            );
            $prefStmt->execute([
                'uid'       => $userId,
                'intervals' => json_encode([30, 15, 5, 0]),
                'prefs'     => json_encode(['browser' => false, 'in_app' => true]),
            ]);

            $db->commit();
            return $userId;
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[CheckNGo] User::create failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    public static function updateProfile(int $userId, string $fullName): bool
    {
        $stmt = get_db()->prepare('UPDATE users SET full_name = :name WHERE id = :id');
        return $stmt->execute(['name' => $fullName, 'id' => $userId]);
    }

    public static function changePassword(int $userId, string $newPlainPassword): bool
    {
        $hash = password_hash($newPlainPassword, PASSWORD_DEFAULT);
        $stmt = get_db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        return $stmt->execute(['hash' => $hash, 'id' => $userId]);
    }
}
