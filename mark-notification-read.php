<?php
/**
 * ajax/mark-notification-read.php
 * POST: notification_id (or all=1), csrf_token
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../models/Notification.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$userId = current_user_id();

if (($_POST['all'] ?? '0') === '1') {
    Notification::markAllRead($userId);
} else {
    $id = (int) ($_POST['notification_id'] ?? 0);
    Notification::markRead($id, $userId);
}

echo json_encode(['success' => true, 'unread_count' => Notification::unreadCount($userId)]);
