<?php
/**
 * models/Belonging.php
 * CRUD for the user's personal belongings catalog. Deletion is a soft
 * delete (is_active = 0) so past checklist snapshots are never affected.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Belonging
{
    public static function listForUser(int $userId, string $search = '', string $category = ''): array
    {
        $sql = 'SELECT * FROM belongings WHERE user_id = :uid AND is_active = 1';
        $params = ['uid' => $userId];

        if ($search !== '') {
            $sql .= ' AND item_name LIKE :search';
            $params['search'] = '%' . $search . '%';
        }
        if ($category !== '') {
            $sql .= ' AND category = :category';
            $params['category'] = $category;
        }
        $sql .= ' ORDER BY item_name ASC';

        $stmt = get_db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $stmt = get_db()->prepare('SELECT * FROM belongings WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function categories(int $userId): array
    {
        $stmt = get_db()->prepare(
            'SELECT DISTINCT category FROM belongings WHERE user_id = :uid AND is_active = 1
             AND category IS NOT NULL AND category <> "" ORDER BY category'
        );
        $stmt->execute(['uid' => $userId]);
        return array_column($stmt->fetchAll(), 'category');
    }

    public static function nameExists(int $userId, string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM belongings WHERE user_id = :uid AND item_name = :name AND is_active = 1';
        $params = ['uid' => $userId, 'name' => $name];
        if ($excludeId) {
            $sql .= ' AND id <> :exclude';
            $params['exclude'] = $excludeId;
        }
        $stmt = get_db()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public static function create(int $userId, string $name, string $category, string $description, string $priority): int
    {
        $stmt = get_db()->prepare(
            'INSERT INTO belongings (user_id, item_name, category, description, default_priority)
             VALUES (:uid, :name, :category, :desc, :priority)'
        );
        $stmt->execute([
            'uid' => $userId, 'name' => $name, 'category' => $category ?: null,
            'desc' => $description ?: null, 'priority' => $priority,
        ]);
        return (int) get_db()->lastInsertId();
    }

    public static function update(int $id, int $userId, string $name, string $category, string $description, string $priority): bool
    {
        $stmt = get_db()->prepare(
            'UPDATE belongings SET item_name = :name, category = :category, description = :desc,
             default_priority = :priority WHERE id = :id AND user_id = :uid'
        );
        return $stmt->execute([
            'name' => $name, 'category' => $category ?: null, 'desc' => $description ?: null,
            'priority' => $priority, 'id' => $id, 'uid' => $userId,
        ]);
    }

    public static function softDelete(int $id, int $userId): bool
    {
        $stmt = get_db()->prepare('UPDATE belongings SET is_active = 0 WHERE id = :id AND user_id = :uid');
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }
}
