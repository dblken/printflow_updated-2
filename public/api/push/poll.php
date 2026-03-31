<?php
/**
 * push/poll.php — Lightweight in-tab notification poll.
 * GET ?since=<unix_timestamp>
 * Returns new notifications created after `since` for the logged-in user.
 * Used as fallback when the tab is open (in-tab toasts); the service worker
 * handles background push when the tab is closed.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'notifications' => []]);
    exit;
}

$user_id   = (int) get_user_id();
$user_type = get_user_type() ?? 'Customer';
$since     = isset($_GET['since']) ? (int) $_GET['since'] : (time() - 30);

// Pull notifications newer than the timestamp
if ($user_type === 'Customer') {
    $rows = db_query(
        "SELECT notification_id AS id, message, type, data_id, is_read, order_type,
                UNIX_TIMESTAMP(created_at) AS ts
         FROM notifications
         WHERE customer_id = ? AND UNIX_TIMESTAMP(created_at) > ?
         ORDER BY created_at ASC
         LIMIT 20",
        'ii',
        [$user_id, $since]
    );
} else {
    $rows = db_query(
        "SELECT notification_id AS id, message, type, data_id, is_read, order_type,
                UNIX_TIMESTAMP(created_at) AS ts
         FROM notifications
         WHERE user_id = ? AND UNIX_TIMESTAMP(created_at) > ?
         ORDER BY created_at ASC
         LIMIT 20",
        'ii',
        [$user_id, $since]
    );
}

// Unread count
$unread = get_unread_notification_count($user_id, $user_type);

echo json_encode([
    'success'       => true,
    'notifications' => $rows ?: [],
    'unread_count'  => (int) $unread,
    'server_time'   => time(),
]);
