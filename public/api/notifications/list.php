<?php
/**
 * notifications/list.php — Fetch latest notifications as JSON for dropdown.
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = (int) get_user_id();
$user_type = get_user_type() ?? 'Customer';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 15;

try {
    if ($user_type === 'Customer') {
        $rows = db_query(
            "SELECT notification_id AS id, message, type, is_read, created_at
             FROM notifications
             WHERE customer_id = ?
             ORDER BY created_at DESC
             LIMIT " . (int)$limit,
            'i',
            [$user_id]
        );
    } else {
        $rows = db_query(
            "SELECT notification_id AS id, message, type, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT " . (int)$limit,
            'i',
            [$user_id]
        );
    }

    // Return rows with unread count
    $unread = get_unread_notification_count($user_id, $user_type);

    echo json_encode([
        'success'       => true,
        'notifications' => $rows ?: [],
        'unread_count'  => (int) $unread
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'notifications' => [],
        'unread_count' => 0
    ]);
}