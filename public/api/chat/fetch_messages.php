<?php
/**
 * Fetch messages for a specific order.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../../../includes/auth.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    require_once __DIR__ . '/../../../includes/ensure_order_messages.php';

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
$sql = "SELECT m.*, 
        p.message AS reply_message, 
        p.image_path AS reply_image,
        p.sender_id AS reply_sender_id,
        CASE 
            WHEN m.sender = 'Customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE customer_id = m.sender_id)
            WHEN m.sender = 'Staff' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = m.sender_id)
            ELSE 'System' 
        END as sender_name,
        CASE 
            WHEN m.sender = 'Customer' THEN 'Customer'
            WHEN m.sender = 'Staff' THEN (SELECT role FROM users WHERE user_id = m.sender_id)
            ELSE 'System' 
        END as sender_role,
        CASE 
            WHEN m.sender = 'Customer' THEN (SELECT profile_picture FROM customers WHERE customer_id = m.sender_id)
            WHEN m.sender = 'Staff' THEN (SELECT profile_picture FROM users WHERE user_id = m.sender_id)
            ELSE NULL 
        END as sender_avatar
        FROM order_messages m 
        LEFT JOIN order_messages p ON m.reply_id = p.message_id
        WHERE m.order_id = ? AND m.message_id > ? 
        ORDER BY m.message_id ASC";
$messages_raw = db_query($sql, 'ii', [$order_id, $last_id]);
if ($messages_raw === false) {
    global $conn;
    $db_err = $conn ? mysqli_error($conn) : 'Unknown DB error';
    throw new Exception("Could not fetch messages. Database returned an error: " . $db_err);
}

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

        $sender_avatar = $msg['sender_avatar'] ?? null;
        if ($sender_avatar && strpos($sender_avatar, '/') === false) {
            $sender_avatar = 'public/assets/uploads/profiles/' . $sender_avatar;
        }

        $messages[] = [
            'id' => $msg['message_id'],
            'message' => $msg['message'] ?? '',
            'message_type' => $msg['message_type'] ?? 'text',
            'image_path' => $image_path,
            'message_file' => $msg['message_file'] ?? $image_path,
            'file_type' => $msg['file_type'] ?? 'text',
            'file_path' => $msg['file_path'] ?? null,
            'file_name' => $msg['file_name'] ?? null,
            'file_size' => $msg['file_size'] ?? null,
            'created_at' => date('h:i A', strtotime($msg['created_at'])),
            'is_self' => $is_self,
            'status' => (int)$msg['read_receipt'], // 0=Sent, 1=Delivered, 2=Seen
            'is_system' => $is_system,
            'reply_id' => $msg['reply_id'] ?: null,
            'reply_message' => $msg['reply_message'] ?? null,
            'reply_image' => $msg['reply_image'] ?? null,
            'reply_sender_id' => $msg['reply_sender_id'] ?? null,
            'sender_name' => $msg['sender_name'],
            'sender_role' => $msg['sender_role'],
            'sender_avatar' => $sender_avatar
        ];
    }
}

// Fetch reactions for all messages in the order (efficient enough for polling limited chats)
$reactions_sql = "SELECT r.message_id, r.reaction_type, r.sender, r.sender_id,
            CASE 
                WHEN r.sender = 'Customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE customer_id = r.sender_id)
                WHEN r.sender = 'Staff' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = r.sender_id)
                ELSE 'System' 
            END as reactor_name
            FROM message_reactions r
            JOIN order_messages m ON r.message_id = m.message_id
            WHERE m.order_id = ?";
$reactions_raw = db_query($reactions_sql, 'i', [$order_id]);
if ($reactions_raw === false) {
    global $conn;
    $db_err = $conn ? mysqli_error($conn) : 'Unknown DB error';
    throw new Exception("Could not fetch reactions. Database returned an error: " . $db_err);
}
$reactions = $reactions_raw ?: [];

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

$partner = [ 'is_online' => false, 'is_typing' => false, 'avatar' => null ];
if (!empty($partner_raw)) {
    $last_active = strtotime($partner_raw[0]['last_activity']);
    $partner['is_online'] = (time() - $last_active) < 90; 
    $partner['is_typing'] = (bool)$partner_raw[0]['is_typing'] && $partner['is_online'];
}

// Get partner avatar for seen indicator
if ($partner_type === 'Staff') {
    $av_res = db_query("SELECT profile_picture FROM users WHERE user_id = (SELECT sender_id FROM order_messages WHERE order_id = ? AND sender = 'Staff' ORDER BY message_id DESC LIMIT 1)", 'i', [$order_id]);
    if ($av_res) $partner['avatar'] = $av_res[0]['profile_picture'];
} else {
    $av_res = db_query("SELECT profile_picture FROM customers WHERE customer_id = (SELECT customer_id FROM orders WHERE order_id = ?)", 'i', [$order_id]);
    if ($av_res) $partner['avatar'] = $av_res[0]['profile_picture'];
}

if ($partner['avatar'] && strpos($partner['avatar'], '/') === false) {
    $partner['avatar'] = 'public/assets/uploads/profiles/' . $partner['avatar'];
}

// 4. Fetch order metadata (archive status)
$order_meta = db_query("SELECT is_archived FROM orders WHERE order_id = ?", 'i', [$order_id]);
$is_archived = !empty($order_meta) ? (bool)$order_meta[0]['is_archived'] : false;

// 5. Fetch last seen message ID for the current authenticated user's sent messages
$user_sender_type = ($user_type === 'Customer') ? 'Customer' : 'Staff';
$last_seen_id = -1;
$seen_query = db_query("SELECT MAX(message_id) as last_seen FROM order_messages WHERE order_id = ? AND sender = ? AND read_receipt = 2", 'is', [$order_id, $user_sender_type]);
if (!empty($seen_query) && $seen_query[0]['last_seen']) {
    $last_seen_id = (int)$seen_query[0]['last_seen'];
}

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'reactions' => $reactions,
        'partner' => $partner,
        'is_archived' => $is_archived,
        'last_seen_message_id' => $last_seen_id
    ]);

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    // Return 200 OK with success => false per user request to avoid 500s that break clients
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $t) {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Critical error: ' . $t->getMessage()]);
}
?>
