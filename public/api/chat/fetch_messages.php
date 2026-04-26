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
        m.is_pinned,
        CASE 
            WHEN m.sender = 'Customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE customer_id = m.sender_id)
            WHEN m.sender = 'Staff' OR (m.sender = 'System' AND m.sender_id > 0) THEN (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = m.sender_id)
            ELSE 'System' 
        END as sender_name,
        CASE 
            WHEN m.sender = 'Customer' THEN 'Customer'
            WHEN m.sender = 'Staff' OR (m.sender = 'System' AND m.sender_id > 0) THEN (SELECT role FROM users WHERE user_id = m.sender_id)
            ELSE 'System' 
        END as sender_role,
        CASE 
            WHEN m.sender = 'Customer' THEN (SELECT profile_picture FROM customers WHERE customer_id = m.sender_id)
            WHEN m.sender = 'Staff' OR (m.sender = 'System' AND m.sender_id > 0) THEN (SELECT profile_picture FROM users WHERE user_id = m.sender_id)
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

        $sender_avatar = get_profile_image($msg['sender_avatar'] ?? null);

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
            'sender_avatar' => $sender_avatar,
            'is_pinned' => (bool)($msg['is_pinned'] ?? false)
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
$partner_sql = "SELECT last_activity, is_typing,
                CASE 
                    WHEN ? = 'Staff' THEN (SELECT online_status FROM users WHERE user_id = (SELECT user_id FROM user_status WHERE order_id = ? AND user_type = 'Staff' ORDER BY last_activity DESC LIMIT 1))
                    ELSE (SELECT online_status FROM customers WHERE customer_id = (SELECT customer_id FROM orders WHERE order_id = ?))
                END as online_status
                FROM user_status 
                WHERE order_id = ? AND user_type = ? 
                ORDER BY last_activity DESC LIMIT 1";
$partner_raw = db_query($partner_sql, 'siiis', [$partner_type, $order_id, $order_id, $order_id, $partner_type]);

$partner = [ 'id' => null, 'name' => null, 'is_online' => false, 'is_typing' => false, 'avatar' => null ];
if (!empty($partner_raw)) {
    $last_active = strtotime($partner_raw[0]['last_activity']);
    $partner['is_online'] = (time() - $last_active) < 90; 
    $partner['is_typing'] = (bool)$partner_raw[0]['is_typing'] && $partner['is_online'];
    $partner['online_status'] = $partner_raw[0]['online_status'] ?? ($partner['is_online'] ? 'online' : 'offline');
}

// Get partner avatar and ID for seen indicator and call system
if ($partner_type === 'Staff') {
    $av_res = db_query(
        "SELECT user_id, profile_picture, first_name, last_name FROM users WHERE user_id = COALESCE(
            (SELECT sender_id FROM order_messages
             WHERE order_id = ?
               AND sender_id > 0
               AND (sender = 'Staff' OR sender = 'System')
             ORDER BY message_id DESC
             LIMIT 1),
            (SELECT us.user_id
             FROM user_status us
             WHERE us.order_id = ? AND us.user_type = 'Staff'
             ORDER BY us.last_activity DESC
             LIMIT 1),
            (SELECT al.user_id
             FROM activity_logs al
             WHERE al.details LIKE CONCAT('%Order #', ?, '%')
             ORDER BY al.created_at DESC
             LIMIT 1),
            (SELECT COALESCE(NULLIF(jo.assigned_to, 0), NULLIF(jo.payment_verified_by, 0), NULLIF(jo.created_by, 0))
             FROM job_orders jo
             WHERE jo.order_id = ?
             ORDER BY jo.updated_at DESC, jo.id DESC
             LIMIT 1),
            (SELECT user_id FROM users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1)
        )",
        'iiii',
        [$order_id, $order_id, $order_id, $order_id]
    );
    if ($av_res) {
        $partner['avatar'] = $av_res[0]['profile_picture'];
        $partner['id'] = (int)$av_res[0]['user_id'];
        $partner['name'] = trim(($av_res[0]['first_name'] ?? '') . ' ' . ($av_res[0]['last_name'] ?? '')) ?: 'Customer Support';
    }
} else {
    $av_res = db_query("SELECT c.customer_id, c.profile_picture, c.first_name, c.last_name FROM customers c WHERE c.customer_id = (SELECT customer_id FROM orders WHERE order_id = ?)", 'i', [$order_id]);
    if ($av_res) {
        $partner['avatar'] = $av_res[0]['profile_picture'];
        $partner['id'] = (int)$av_res[0]['customer_id'];
        $partner['name'] = trim(($av_res[0]['first_name'] ?? '') . ' ' . ($av_res[0]['last_name'] ?? ''));
    }
}

$partner['avatar'] = get_profile_image($partner['avatar'] ?? null);

// 4. Fetch order metadata (archive status)
$has_archived_col = !empty(db_query("SHOW COLUMNS FROM orders LIKE 'is_archived'"));
$order_meta = $has_archived_col
    ? db_query("SELECT is_archived FROM orders WHERE order_id = ?", 'i', [$order_id])
    : [];
$is_archived = !empty($order_meta) ? (bool)$order_meta[0]['is_archived'] : false;

// 5. Fetch last seen message ID for the current authenticated user's sent messages
$user_sender_type = ($user_type === 'Customer') ? 'Customer' : 'Staff';
$last_seen_id = -1;
$seen_query = db_query("SELECT MAX(message_id) as last_seen FROM order_messages WHERE order_id = ? AND sender = ? AND read_receipt = 2", 'is', [$order_id, $user_sender_type]);
if (!empty($seen_query) && $seen_query[0]['last_seen']) {
    $last_seen_id = (int)$seen_query[0]['last_seen'];
}

// 6. Fetch all pinned messages for the Pinned Bar
$pinned_sql = "SELECT m.message_id as id, m.message, m.image_path, m.file_type, m.created_at,
                CASE 
                    WHEN m.sender = 'Customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE customer_id = m.sender_id)
                    WHEN m.sender = 'Staff' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = m.sender_id)
                    ELSE 'System' 
                END as sender_name
              FROM order_messages m 
              WHERE m.order_id = ? AND m.is_pinned = 1 
              ORDER BY m.created_at DESC";
$pinned_messages_raw = db_query($pinned_sql, 'i', [$order_id]) ?: [];
$pinned_messages = [];
foreach ($pinned_messages_raw as $pm) {
    if ($pm['image_path'] && !preg_match('#^https?://#i', $pm['image_path'])) {
        if (strpos($pm['image_path'], '/printflow/') !== 0) $pm['image_path'] = '/printflow/' . ltrim($pm['image_path'], '/');
    }
    $pm['created_at'] = date('M j, h:i A', strtotime($pm['created_at']));
    $pinned_messages[] = $pm;
}

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'reactions' => $reactions,
        'partner' => $partner,
        'is_archived' => $is_archived,
        'last_seen_message_id' => $last_seen_id,
        'pinned_messages' => $pinned_messages
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
