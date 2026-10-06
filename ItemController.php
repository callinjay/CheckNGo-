<?php
/**
 * controllers/ItemController.php
 * Handles POST actions from my-items.php: add, edit, delete belongings.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Belonging.php';

class ItemController
{
    private const PRIORITIES = ['critical', 'important', 'optional'];

    /** @return array{success:bool, error:?string} */
    public static function handle(array $post, int $userId): array
    {
        $action = $post['action'] ?? '';

        try {
            switch ($action) {
                case 'add':
                    return self::add($post, $userId);
                case 'edit':
                    return self::edit($post, $userId);
                case 'delete':
                    return self::delete($post, $userId);
                default:
                    return ['success' => false, 'error' => 'Unknown action.'];
            }
        } catch (Throwable $e) {
            error_log('[CheckNGo] ItemController error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Something went wrong. Please try again.'];
        }
    }

    private static function add(array $post, int $userId): array
    {
        $name = trim((string) ($post['item_name'] ?? ''));
        $category = trim((string) ($post['category'] ?? ''));
        $description = trim((string) ($post['description'] ?? ''));
        $priority = (string) ($post['default_priority'] ?? 'important');

        if ($name === '') {
            return ['success' => false, 'error' => 'Item name is required.'];
        }
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'important';
        }
        if (Belonging::nameExists($userId, $name)) {
            return ['success' => false, 'error' => 'You already have an item with this name.'];
        }

        Belonging::create($userId, $name, $category, $description, $priority);
        return ['success' => true, 'error' => null];
    }

    private static function edit(array $post, int $userId): array
    {
        $id = (int) ($post['item_id'] ?? 0);
        $name = trim((string) ($post['item_name'] ?? ''));
        $category = trim((string) ($post['category'] ?? ''));
        $description = trim((string) ($post['description'] ?? ''));
        $priority = (string) ($post['default_priority'] ?? 'important');

        if (!$id || !Belonging::findById($id, $userId)) {
            return ['success' => false, 'error' => 'Item not found.'];
        }
        if ($name === '') {
            return ['success' => false, 'error' => 'Item name is required.'];
        }
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'important';
        }
        if (Belonging::nameExists($userId, $name, $id)) {
            return ['success' => false, 'error' => 'You already have another item with this name.'];
        }

        Belonging::update($id, $userId, $name, $category, $description, $priority);
        return ['success' => true, 'error' => null];
    }

    private static function delete(array $post, int $userId): array
    {
        $id = (int) ($post['item_id'] ?? 0);
        if (!$id || !Belonging::findById($id, $userId)) {
            return ['success' => false, 'error' => 'Item not found.'];
        }
        Belonging::softDelete($id, $userId);
        return ['success' => true, 'error' => null];
    }
}
