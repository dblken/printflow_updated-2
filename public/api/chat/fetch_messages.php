<?php
/**
 * Fetch messages for a specific order.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../../../includes/auth.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    // Global Output Buffer to trap notices
    ob_start();

    header('Content-Type: application/json');

    if (!is_logged_in()) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Your session has expired. Please login again.']);
        exit;
    }

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$is_active = isset($_GET['is_active']) && $_GET['is_active'] == 1; // Is the chat window currently open by the requester?
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit;
}

// 1. Fetch new messages
$sql = "SELECT m.* FROM order_messages m 
        WHERE m.order_id = ? AND m.message_id > ? 
        ORDER BY m.message_id ASC";
$messages_raw = db_query($sql, 'ii', [$order_id, $last_id]);
if ($messages_raw === false) throw new Exception("Could not fetch messages. Database returned an error.");

$messages = [];
if ($messages_raw) {
    foreach ($messages_raw as $msg) {
        $is_system = ($msg['sender'] === 'System');
        $is_self = false;
        if (!$is_system) {
            $is_self = ($user_type === 'Customer') ? ($msg['sender'] === 'Customer') : ($msg['sender'] === 'Staff');
        }

        $image_path = (string)($msg['image_path'] ?? '');
        if ($image_path !== '' && !preg_match('#^https?://#i', $image_path)) {
            if (strpos($image_path, '/printflow/') !== 0) $image_path = '/printflow/' . ltrim($image_path, '/');
        }

        $messages[] = [
            'id' => $msg['message_id'],
            'message' => $msg['message'] ?? '',
            'message_type' => $msg['message_type'] ?? 'text',
            'image_path' => $image_path,
            'created_at' => date('h:i A', strtotime($msg['created_at'])),
            'is_self' => $is_self,
            'status' => (int)$msg['read_receipt'], // 0=Sent, 1=Delivered, 2=Seen
            'is_system' => $is_system
        ];
    }
}

// 2. Mark messages as seen/delivered
$target_sender = ($user_type === 'Customer') ? 'Staff' : 'Customer';
if ($is_active) {
    // Current user has chat open -> Mark as SEEN
    db_execute("UPDATE order_messages SET read_receipt = 2 WHERE order_id = ? AND sender = ? AND read_receipt < 2", 'is', [$order_id, $target_sender]);
} else {
    // Current user just fetched updates (sidebar/background) -> Mark as DELIVERED
    db_execute("UPDATE order_messages SET read_receipt = 1 WHERE order_id = ? AND sender = ? AND read_receipt = 0", 'is', [$order_id, $target_sender]);
}

// 3. Fetch partner online/typing status
$partner_type = ($user_type === 'Customer') ? 'Staff' : 'Customer';
$partner_sql = "SELECT last_activity, is_typing FROM user_status 
                WHERE order_id = ? AND user_type = ? 
                ORDER BY last_activity DESC LIMIT 1";
$partner_raw = db_query($partner_sql, 'is', [$order_id, $partner_type]);

$partner = [ 'is_online' => false, 'is_typing' => false ];
if (!empty($partner_raw)) {
    $last_active = strtotime($partner_raw[0]['last_activity']);
    $partner['is_online'] = (time() - $last_active) < 90; 
    $partner['is_typing'] = (bool)$partner_raw[0]['is_typing'] && $partner['is_online'];
}

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'partner' => $partner
    ]);

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500); 
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $t) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Critical error: ' . $t->getMessage()]);
}
?>
