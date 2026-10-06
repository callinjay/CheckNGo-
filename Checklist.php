<?php
/**
 * models/Checklist.php
 * Manages checklist_templates and checklist_template_items — the editable
 * "recipe" of belongings + priorities for a given activity.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Checklist
{
    /** Gets the user's default template for an activity, creating an empty one if needed. */
    public static function getOrCreateDefaultTemplate(int $userId, int $activityId): array
    {
        $stmt = get_db()->prepare(
            'SELECT * FROM checklist_templates WHERE user_id = :uid AND activity_id = :aid AND is_default = 1 LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'aid' => $activityId]);
        $existing = $stmt->fetch();
        if ($existing) {
            return $existing;
        }

        $insert = get_db()->prepare(
            'INSERT INTO checklist_templates (user_id, activity_id, template_name, is_default)
             VALUES (:uid, :aid, "Default", 1)'
        );
        $insert->execute(['uid' => $userId, 'aid' => $activityId]);
        $id = (int) get_db()->lastInsertId();

        $find = get_db()->prepare('SELECT * FROM checklist_templates WHERE id = :id');
        $find->execute(['id' => $id]);
        return $find->fetch();
    }

    public static function findTemplate(int $templateId, int $userId): ?array
    {
        $stmt = get_db()->prepare('SELECT * FROM checklist_templates WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $templateId, 'uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Template items joined with the live belonging record (name/category). */
    public static function getTemplateItems(int $templateId): array
    {
        $stmt = get_db()->prepare(
            'SELECT cti.*, b.item_name, b.category
             FROM checklist_template_items cti
             JOIN belongings b ON b.id = cti.belonging_id
             WHERE cti.template_id = :tid AND b.is_active = 1
             ORDER BY cti.sort_order ASC, b.item_name ASC'
        );
        $stmt->execute(['tid' => $templateId]);
        return $stmt->fetchAll();
    }

    public static function itemCount(int $templateId): int
    {
        $stmt = get_db()->prepare(
            'SELECT COUNT(*) c FROM checklist_template_items cti
             JOIN belongings b ON b.id = cti.belonging_id
             WHERE cti.template_id = :tid AND b.is_active = 1'
        );
        $stmt->execute(['tid' => $templateId]);
        return (int) $stmt->fetch()['c'];
    }

    public static function addItem(int $templateId, int $belongingId, string $priority, bool $isRequired): bool
    {
        // Prevent duplicate checklist items within the same template.
        $check = get_db()->prepare(
            'SELECT id FROM checklist_template_items WHERE template_id = :tid AND belonging_id = :bid'
        );
        $check->execute(['tid' => $templateId, 'bid' => $belongingId]);
        if ($check->fetch()) {
            return false;
        }

        $sortStmt = get_db()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next FROM checklist_template_items WHERE template_id = :tid'
        );
        $sortStmt->execute(['tid' => $templateId]);
        $nextOrder = (int) $sortStmt->fetch()['next'];

        $stmt = get_db()->prepare(
            'INSERT INTO checklist_template_items (template_id, belonging_id, priority, is_required, sort_order)
             VALUES (:tid, :bid, :priority, :req, :sort)'
        );
        return $stmt->execute([
            'tid' => $templateId, 'bid' => $belongingId, 'priority' => $priority,
            'req' => $isRequired ? 1 : 0, 'sort' => $nextOrder,
        ]);
    }

    public static function removeItem(int $templateItemId, int $templateId): bool
    {
        $stmt = get_db()->prepare(
            'DELETE FROM checklist_template_items WHERE id = :id AND template_id = :tid'
        );
        return $stmt->execute(['id' => $templateItemId, 'tid' => $templateId]);
    }

    public static function updatePriority(int $templateItemId, int $templateId, string $priority): bool
    {
        $stmt = get_db()->prepare(
            'UPDATE checklist_template_items SET priority = :p WHERE id = :id AND template_id = :tid'
        );
        return $stmt->execute(['p' => $priority, 'id' => $templateItemId, 'tid' => $templateId]);
    }
}
