<?php
/**
 * ajax/confirm-departure.php
 * POST: session_id, force ("1" to confirm despite unchecked critical items), csrf_token
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

$sessionId = (int) ($_POST['session_id'] ?? 0);
$force = ($_POST['force'] ?? '0') === '1';

try {
    $result = ChecklistService::confirmDeparture($sessionId, current_user_id(), $force);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
