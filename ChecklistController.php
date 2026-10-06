<?php
/**
 * controllers/ChecklistController.php
 * Handles POST actions from template.php (template editing) and
 * checklist.php (starting a session). AJAX toggling/confirmation live in
 * ajax/toggle-item.php and ajax/confirm-departure.php, using ChecklistService directly.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Checklist.php';
require_once __DIR__ . '/../models/Belonging.php';

class ChecklistController
{
    private const PRIORITIES = ['critical', 'important', 'optional'];

    public static function handleTemplateAction(array $post, int $userId, int $templateId): array
    {
        $action = $post['action'] ?? '';

        try {
            switch ($action) {
                case 'add_item':
                    $belongingId = (int) ($post['belonging_id'] ?? 0);
                    $priority = in_array($post['priority'] ?? '', self::PRIORITIES, true) ? $post['priority'] : 'important';
                    $required = isset($post['is_required']);

                    if (!$belongingId || !Belonging::findById($belongingId, $userId)) {
                        return ['success' => false, 'error' => 'Please choose a valid item.'];
                    }
                    $added = Checklist::addItem($templateId, $belongingId, $priority, $required);
                    if (!$added) {
                        return ['success' => false, 'error' => 'This item is already in the checklist.'];
                    }
                    return ['success' => true, 'error' => null];

                case 'remove_item':
                    $itemId = (int) ($post['template_item_id'] ?? 0);
                    Checklist::removeItem($itemId, $templateId);
                    return ['success' => true, 'error' => null];

                case 'update_priority':
                    $itemId = (int) ($post['template_item_id'] ?? 0);
                    $priority = in_array($post['priority'] ?? '', self::PRIORITIES, true) ? $post['priority'] : 'important';
                    Checklist::updatePriority($itemId, $templateId, $priority);
                    return ['success' => true, 'error' => null];

                default:
                    return ['success' => false, 'error' => 'Unknown action.'];
            }
        } catch (Throwable $e) {
            error_log('[CheckNGo] ChecklistController error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Something went wrong. Please try again.'];
        }
    }
}
