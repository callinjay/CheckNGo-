<?php
/**
 * ajax/toggle-item.php
 * POST: session_item_id, checked ("1"/"0"), csrf_token
 * Returns JSON with the recalculated readiness for the session.
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../services/ChecklistService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$sessionItemId = (int) ($_POST['session_item_id'] ?? 0);
$checked = ($_POST['checked'] ?? '0') === '1';

try {
    $readiness = ChecklistService::toggleItem($sessionItemId, current_user_id(), $checked);
    echo json_encode(['success' => true, 'readiness' => $readiness]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
