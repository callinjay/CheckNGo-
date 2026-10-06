<?php
/**
 * ajax/recommendation-action.php
 * POST: activity_id, belonging_id, decision ("accept"|"dismiss"), csrf_token
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../services/RecommendationService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$userId = current_user_id();
$activityId = (int) ($_POST['activity_id'] ?? 0);
$belongingId = (int) ($_POST['belonging_id'] ?? 0);
$decision = (string) ($_POST['decision'] ?? '');

if (!$activityId || !$belongingId || !in_array($decision, ['accept', 'dismiss'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$ok = $decision === 'accept'
    ? RecommendationService::accept($userId, $activityId, $belongingId)
    : RecommendationService::dismiss($userId, $activityId, $belongingId);

echo json_encode(['success' => $ok]);
