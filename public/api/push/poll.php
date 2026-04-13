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
$fields = "n.notification_id AS id, n.message, n.type, n.data_id, n.is_read, o.order_type,
           UNIX_TIMESTAMP(n.created_at) AS ts";

if ($user_type === 'Customer') {
    $rows = db_query(
        "SELECT $fields
         FROM notifications n
         LEFT JOIN orders o ON n.data_id = o.order_id
         WHERE n.customer_id = ? AND UNIX_TIMESTAMP(n.created_at) > ?
         ORDER BY n.created_at ASC
         LIMIT 20",
        'ii',
        [$user_id, $since]
    );
} else {
    $rows = db_query(
        "SELECT $fields
         FROM notifications n
         LEFT JOIN orders o ON n.data_id = o.order_id
         WHERE n.user_id = ? AND UNIX_TIMESTAMP(n.created_at) > ?
         ORDER BY n.created_at ASC
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
