<?php
// Highly stable API response for Chat List
// PrintFlow Enterprise Messaging

// Production-ready error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable raw output to prevent JSON corruption

try {
    require_once __DIR__ . '/../../../includes/auth.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    // Global Output Buffer to trap stray warnings/notices
    ob_start();

    header('Content-Type: application/json');

    if (!is_logged_in()) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Your session has expired. Please login again.']);
        exit;
    }

$user_id = get_user_id();
$user_type = get_user_type();
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$show_archived = isset($_GET['archived']) && $_GET['archived'] == 1;

$search_clause = "";
$params = [];
$types = "";

if ($search !== '') {
    $like = "%$search%";
    if ($user_type === 'Customer') {
        $search_clause = " AND (o.order_id LIKE ? OR (SELECT mi.message FROM order_messages mi WHERE mi.order_id = o.order_id ORDER BY mi.message_id DESC LIMIT 1) LIKE ? OR (SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.customization_data, '$.service_type')), pi.name, 'Order') FROM order_items oi LEFT JOIN products pi ON oi.product_id = pi.product_id WHERE oi.order_id = o.order_id LIMIT 1) LIKE ?)";
        $params = [$like, $like, $like];
        $types = "sss";
    } else {
        $search_clause = " AND (o.order_id LIKE ? OR CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) LIKE ? OR (SELECT mi.message FROM order_messages mi WHERE mi.order_id = o.order_id ORDER BY mi.message_id DESC LIMIT 1) LIKE ? OR (SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.customization_data, '$.service_type')), pi.name, 'Order') FROM order_items oi LEFT JOIN products pi ON oi.product_id = pi.product_id WHERE oi.order_id = o.order_id LIMIT 1) LIKE ?)";
        $params = [$like, $like, $like, $like];
        $types = "ssss";
    }
}

if ($user_type === 'Customer') {
    $archive_col = "o.is_archived";
    $sql = "
        SELECT o.order_id, o.status, o.order_date, $archive_col as is_archived,
               (SELECT m.message FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message,
               (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message_at,
               (SELECT COUNT(*) FROM order_messages m WHERE m.order_id = o.order_id AND m.sender = 'Staff' AND m.read_receipt != 2) AS unread_count,
               (SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.customization_data, '$.service_type')), p.name, 'Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) AS service_name,
               (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) FROM order_messages m JOIN users u ON u.user_id = m.sender_id WHERE m.order_id = o.order_id AND m.sender = 'Staff' ORDER BY m.message_id DESC LIMIT 1) AS staff_name
        FROM orders o
        WHERE o.customer_id = ? AND $archive_col = ? $search_clause
        ORDER BY COALESCE((SELECT MAX(mx.created_at) FROM order_messages mx WHERE mx.order_id = o.order_id), o.order_date) DESC
    ";
    
    $full_params = array_merge([$user_id, ($show_archived ? 1 : 0)], $params);
    $full_types = "ii" . $types;
    $rows = db_query($sql, $full_types, $full_params);
    if ($rows === false) throw new Exception("Database lookup failed on orders.");
} else {
    $archive_col = "o.is_archived";
    $has_activity = !empty(db_query("SHOW COLUMNS FROM customers LIKE 'last_activity'"));
    $activity_sel = $has_activity ? "c.last_activity as partner_last_activity," : "NULL as partner_last_activity,";

    $sql = "
        SELECT o.order_id, o.status, o.order_date, $archive_col as is_archived,
               TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS customer_name,
               c.profile_picture AS customer_avatar,
               $activity_sel
               (SELECT m.message FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message,
               (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message_at,
               (SELECT COUNT(*) FROM order_messages m WHERE m.order_id = o.order_id AND m.sender = 'Customer' AND m.read_receipt != 2) AS unread_count,
               (SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.customization_data, '$.service_type')), p.name, 'Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) AS service_name
        FROM orders o
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        WHERE o.status != 'Cancelled' AND $archive_col = ? $search_clause
        AND (
            EXISTS (SELECT 1 FROM order_messages m WHERE m.order_id = o.order_id)
            OR o.order_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        )
        ORDER BY COALESCE((SELECT MAX(mx.created_at) FROM order_messages mx WHERE mx.order_id = o.order_id), o.order_date) DESC
    ";
    
    $full_params = array_merge([($show_archived ? 1 : 0)], $params);
    $full_types = "i" . $types;
    $rows = db_query($sql, $full_types, $full_params);
    if ($rows === false) throw new Exception("Database lookup failed on staff view.");
}

$conversations = [];
foreach ($rows ?: [] as $r) {
    $last_msg = (string)($r['last_message'] ?? '');
    if (strlen($last_msg) > 60) $last_msg = substr($last_msg, 0, 57) . '...';
    $customer_name = trim((string)($r['customer_name'] ?? ''));
    if ($customer_name === '') $customer_name = 'Customer';
    $staff_name = trim((string)($r['staff_name'] ?? ''));
    if ($staff_name === '') $staff_name = 'PrintFlow Team';
    
    $is_online = false;
    if ($user_type !== 'Customer' && !empty($r['partner_last_activity'])) {
        $last_active = strtotime($r['partner_last_activity']);
        $is_online = (time() - $last_active) < 90; // Online if active in last 90s
    }

    $customer_avatar = $r['customer_avatar'] ?? null;
    if ($customer_avatar && strpos($customer_avatar, '/') === false) {
        $customer_avatar = 'public/assets/uploads/profiles/' . $customer_avatar;
    }

    $conv = [
        'order_id' => (int)$r['order_id'],
        'status' => $r['status'] ?? '',
        'service_name' => $r['service_name'] ?? 'Order',
        'customer_name' => $customer_name,
        'customer_avatar' => $customer_avatar,
        'last_message' => $last_msg,
        'last_message_at' => $r['last_message_at'] ?? $r['order_date'] ?? null,
        'unread_count' => (int)($r['unread_count'] ?? 0),
        'is_archived' => (bool)$r['is_archived'],
        'is_online' => $is_online
    ];
    if ($user_type === 'Customer') {
        $conv['staff_name'] = $staff_name;
    }
        $conversations[] = $conv;
    }

    // Success: Clear buffer and return clean JSON
    $captured_garbage = ob_get_contents();
    ob_end_clean();
    
    // If there was garbage (notices), we might want to log it but still return valid JSON
    if ($captured_garbage !== '' && strpos($captured_garbage, 'success') === false) {
        error_log("Stray output in list_conversations.php: " . $captured_garbage);
    }

    echo json_encode([
        'success' => true, 
        'conversations' => $conversations,
        'debug' => !empty($captured_garbage) ? 'Server notice captured' : null
    ]);

} catch (Exception $e) {
    // Fatal Error Handler: Always return JSON
    if (ob_get_level() > 0) ob_end_clean();
    
    http_response_code(500); 
    echo json_encode([
        'success' => false,
        'error' => 'A server error occurred while loading your chats.',
        'details' => $e->getMessage()
    ]);
} catch (Throwable $t) {
    // PHP 7+ Engine Errors (Syntax, etc.)
    if (ob_get_level() > 0) ob_end_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Critical engine error.',
        'details' => $t->getMessage()
    ]);
}
