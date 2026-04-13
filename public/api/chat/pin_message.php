<?php
/**
 * Toggle PIN status of a message.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

$user_id = get_user_id();
$user_type = get_user_type();

// Verify access: usually staff can pin anything in orders they manage, 
// but we'll check if the message exists first.
$msg_res = db_query("SELECT is_pinned, order_id FROM order_messages WHERE message_id = ?", 'i', [$message_id]);
if (empty($msg_res)) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit();
}

$msg = $msg_res[0];
$new_status = $msg['is_pinned'] ? 0 : 1;

// Update status
$success = db_execute("UPDATE order_messages SET is_pinned = ? WHERE message_id = ?", 'ii', [$new_status, $message_id]);

if ($success !== false) {
    echo json_encode([
        'success' => true, 
        'pinned' => $new_status === 1,
        'message' => 'Pin status updated successfully.'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed.']);
}
