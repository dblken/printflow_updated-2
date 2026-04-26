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
    // Check if is_archived column exists
    $has_archived = !empty(db_query("SHOW COLUMNS FROM orders LIKE 'is_archived'"));
    $archive_col = $has_archived ? "o.is_archived" : "0";
    
    $sql = "
        SELECT o.order_id, o.status, o.order_date, $archive_col as is_archived,
               (SELECT CASE 
                    WHEN m.message != '' THEN m.message 
                    WHEN m.message_type = 'image' THEN (CASE WHEN m.file_type = 'video' THEN '🎥 Video' ELSE '📸 Photo' END)
                    WHEN m.message_type = 'voice' THEN '🎤 Voice message'
                    ELSE 'Attachment'
                END FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message,
               (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message_at,
               (SELECT COUNT(*) FROM order_messages m WHERE m.order_id = o.order_id AND m.sender = 'Staff' AND m.read_receipt != 2) AS unread_count,
               (SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.customization_data, '$.service_type')), p.name, 'Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) AS product_name,
               COALESCE(
                   (SELECT m.sender_id FROM order_messages m WHERE m.order_id = o.order_id AND m.sender_id > 0 AND m.sender IN ('Staff','System') ORDER BY m.message_id DESC LIMIT 1),
                   (SELECT us.user_id FROM user_status us WHERE us.order_id = o.order_id AND us.user_type = 'Staff' ORDER BY us.last_activity DESC LIMIT 1),
                   (SELECT jo.assigned_to FROM job_orders jo WHERE jo.order_id = o.order_id AND jo.assigned_to IS NOT NULL ORDER BY jo.updated_at DESC LIMIT 1),
                   (SELECT jo.created_by FROM job_orders jo WHERE jo.order_id = o.order_id AND jo.created_by IS NOT NULL ORDER BY jo.updated_at DESC LIMIT 1),
                   (SELECT user_id FROM users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1)
               ) AS staff_id,
               COALESCE(
                   (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
                    FROM order_messages m
                    JOIN users u ON u.user_id = m.sender_id
                    WHERE m.order_id = o.order_id AND m.sender_id > 0 AND m.sender IN ('Staff','System')
                    ORDER BY m.message_id DESC
                    LIMIT 1),
                   (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
                    FROM user_status us
                    JOIN users u ON u.user_id = us.user_id
                    WHERE us.order_id = o.order_id AND us.user_type = 'Staff'
                    ORDER BY us.last_activity DESC
                    LIMIT 1),
                   (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
                    FROM job_orders jo
                    JOIN users u ON u.user_id = jo.assigned_to
                    WHERE jo.order_id = o.order_id AND jo.assigned_to IS NOT NULL
                    ORDER BY jo.updated_at DESC, jo.id DESC
                    LIMIT 1),
                   (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
                    FROM job_orders jo
                    JOIN users u ON u.user_id = jo.payment_verified_by
                    WHERE jo.order_id = o.order_id AND jo.payment_verified_by IS NOT NULL
                    ORDER BY COALESCE(jo.payment_verified_at, jo.updated_at) DESC, jo.id DESC
                    LIMIT 1),
                   (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
                    FROM job_orders jo
                    JOIN users u ON u.user_id = jo.created_by
                    WHERE jo.order_id = o.order_id AND jo.created_by IS NOT NULL
                    ORDER BY jo.updated_at DESC, jo.id DESC
                    LIMIT 1),
                   (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) FROM users u WHERE u.role = 'Admin' ORDER BY u.user_id ASC LIMIT 1),
                   (SELECT TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
                    FROM activity_logs al
                    JOIN users u ON u.user_id = al.user_id
                    WHERE al.details LIKE CONCAT('%Order #', o.order_id, '%')
                    ORDER BY al.created_at DESC
                    LIMIT 1)
               ) AS staff_name,
               COALESCE(
                   (SELECT u.profile_picture
                    FROM order_messages m
                    JOIN users u ON u.user_id = m.sender_id
                    WHERE m.order_id = o.order_id AND m.sender_id > 0 AND m.sender IN ('Staff','System')
                    ORDER BY m.message_id DESC
                    LIMIT 1),
                   (SELECT u.profile_picture
                    FROM user_status us
                    JOIN users u ON u.user_id = us.user_id
                    WHERE us.order_id = o.order_id AND us.user_type = 'Staff'
                    ORDER BY us.last_activity DESC
                    LIMIT 1),
                   (SELECT u.profile_picture
                    FROM job_orders jo
                    JOIN users u ON u.user_id = jo.assigned_to
                    WHERE jo.order_id = o.order_id AND jo.assigned_to IS NOT NULL
                    ORDER BY jo.updated_at DESC, jo.id DESC
                    LIMIT 1),
                   (SELECT u.profile_picture
                    FROM job_orders jo
                    JOIN users u ON u.user_id = jo.payment_verified_by
                    WHERE jo.order_id = o.order_id AND jo.payment_verified_by IS NOT NULL
                    ORDER BY COALESCE(jo.payment_verified_at, jo.updated_at) DESC, jo.id DESC
                    LIMIT 1),
                   (SELECT u.profile_picture
                    FROM job_orders jo
                    JOIN users u ON u.user_id = jo.created_by
                    WHERE jo.order_id = o.order_id AND jo.created_by IS NOT NULL
                    ORDER BY jo.updated_at DESC, jo.id DESC
                    LIMIT 1),
                   (SELECT u.profile_picture FROM users u WHERE u.role = 'Admin' ORDER BY u.user_id ASC LIMIT 1),
                   (SELECT u.profile_picture
                    FROM activity_logs al
                    JOIN users u ON u.user_id = al.user_id
                    WHERE al.details LIKE CONCAT('%Order #', o.order_id, '%')
                    ORDER BY al.created_at DESC
                    LIMIT 1)
               ) AS staff_avatar,
               COALESCE(
                   (SELECT u.online_status FROM users u WHERE u.user_id = (SELECT m.sender_id FROM order_messages m WHERE m.order_id = o.order_id AND m.sender_id > 0 AND m.sender IN ('Staff','System') ORDER BY m.message_id DESC LIMIT 1)),
                   (SELECT u.online_status FROM users u WHERE u.user_id = (SELECT us.user_id FROM user_status us WHERE us.order_id = o.order_id AND us.user_type = 'Staff' ORDER BY us.last_activity DESC LIMIT 1)),
                   (SELECT u.online_status FROM users u WHERE u.user_id = (SELECT jo.assigned_to FROM job_orders jo WHERE jo.order_id = o.order_id AND jo.assigned_to IS NOT NULL ORDER BY jo.updated_at DESC LIMIT 1)),
                   'offline'
               ) AS staff_status
        FROM orders o
        WHERE o.customer_id = ?" . ($has_archived ? " AND $archive_col = ?" : "") . " $search_clause
        ORDER BY COALESCE((SELECT MAX(mx.created_at) FROM order_messages mx WHERE mx.order_id = o.order_id), o.order_date) DESC
    ";
    
    $full_params = $has_archived ? array_merge([$user_id, ($show_archived ? 1 : 0)], $params) : array_merge([$user_id], $params);
    $full_types = $has_archived ? "ii" . $types : "i" . $types;
    $rows = db_query($sql, $full_types, $full_params);
    if ($rows === false) throw new Exception("Database lookup failed on orders.");
} else {
    // Check if is_archived column exists
    $has_archived = !empty(db_query("SHOW COLUMNS FROM orders LIKE 'is_archived'"));
    $archive_col = $has_archived ? "o.is_archived" : "0";
    $has_activity = !empty(db_query("SHOW COLUMNS FROM customers LIKE 'last_activity'"));
    $activity_sel = $has_activity ? "c.last_activity as partner_last_activity," : "NULL as partner_last_activity,";

    $sql = "
        SELECT o.order_id, o.customer_id, o.status, o.order_date, $archive_col as is_archived,
               TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS customer_name,
               c.profile_picture AS customer_avatar,
               c.online_status AS customer_status,
               $activity_sel
               (SELECT CASE 
                    WHEN m.message != '' THEN m.message 
                    WHEN m.message_type = 'image' THEN (CASE WHEN m.file_type = 'video' THEN '🎥 Video' ELSE '📸 Photo' END)
                    WHEN m.message_type = 'voice' THEN '🎤 Voice message'
                    ELSE 'Attachment'
                END FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message,
               (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id ORDER BY m.message_id DESC LIMIT 1) AS last_message_at,
               (SELECT COUNT(*) FROM order_messages m WHERE m.order_id = o.order_id AND m.sender = 'Customer' AND m.read_receipt != 2) AS unread_count,
               (SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.customization_data, '$.service_type')), p.name, 'Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) AS product_name
        FROM orders o
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        WHERE o.status != 'Cancelled'" . ($has_archived ? " AND $archive_col = ?" : "") . " $search_clause
        AND (
            EXISTS (SELECT 1 FROM order_messages m WHERE m.order_id = o.order_id)
            OR o.order_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        )
        ORDER BY COALESCE((SELECT MAX(mx.created_at) FROM order_messages mx WHERE mx.order_id = o.order_id), o.order_date) DESC
    ";
    
    $full_params = $has_archived ? array_merge([($show_archived ? 1 : 0)], $params) : $params;
    $full_types = $has_archived ? "i" . $types : $types;
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
    if ($staff_name === '') $staff_name = 'Customer Support';
    
    $is_online = false;
    $status = 'offline';
    if ($user_type !== 'Customer') {
        $status = $r['customer_status'] ?? 'offline';
        if (!empty($r['partner_last_activity'])) {
            $last_active = strtotime($r['partner_last_activity']);
            $is_online = (time() - $last_active) < 90;
        }
    } else {
        $status = $r['staff_status'] ?? 'offline';
        // For customer view, we might not have staff_last_activity easily, 
        // but online_status 'online' or 'in-call' is enough.
        $is_online = ($status === 'online' || $status === 'in-call');
    }

    $customer_avatar = get_profile_image($r['customer_avatar'] ?? null);

    $conv = [
        'order_id' => (int)$r['order_id'],
        'customer_id' => isset($r['customer_id']) ? (int)$r['customer_id'] : null,
        'status' => $r['status'] ?? '',
        'product_name' => $r['product_name'] ?? 'Order',
        'customer_name' => $customer_name,
        'customer_avatar' => $customer_avatar,
        'last_message' => $last_msg,
        'last_message_at' => $r['last_message_at'] ?? $r['order_date'] ?? null,
        'unread_count' => (int)($r['unread_count'] ?? 0),
        'is_archived' => (bool)$r['is_archived'],
        'is_online' => $is_online,
        'online_status' => $status
    ];
    if ($user_type === 'Customer') {
        $conv['staff_id'] = isset($r['staff_id']) ? (int)$r['staff_id'] : null;
        $conv['staff_name'] = $staff_name;
        $conv['staff_avatar'] = get_profile_image($r['staff_avatar'] ?? null, 'Staff');
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
