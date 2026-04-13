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
        $notifications = array_map(static function (array $notification): array {
            return [
                'id' => (int)($notification['notification_id'] ?? 0),
                'notification_id' => (int)($notification['notification_id'] ?? 0),
                'message' => $notification['message'] ?? '',
                'type' => $notification['type'] ?? '',
                'data_id' => $notification['data_id'] ?? null,
                'order_id' => $notification['order_id'] ?? null,
                'product_id' => $notification['product_id'] ?? null,
                'service_id' => $notification['service_id'] ?? null,
                'is_read' => (int)($notification['is_read'] ?? 0),
                'created_at' => $notification['created_at'] ?? null,
                'display_name' => $notification['display_name'] ?? '',
                'display_image' => $notification['display_image'] ?? '',
                'fallback_image' => $notification['fallback_image'] ?? '',
                'target_link' => $notification['target_link'] ?? '',
                'time_ago' => $notification['time_ago'] ?? '',
            ];
        }, get_customer_notifications_for_display($user_id, $limit));

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => get_unread_notification_count($user_id, 'Customer'),
        ]);
        exit;
    }

    // Choose available product image column for compatibility across DB versions.
    $has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
    $has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
    $product_image_column = 'NULL';
    if ($has_photo_path && $has_product_image) {
        $product_image_column = "COALESCE(p.photo_path, p.product_image)";
    } elseif ($has_photo_path) {
        $product_image_column = "p.photo_path";
    } elseif ($has_product_image) {
        $product_image_column = "p.product_image";
    }

    $sql = "SELECT 
                n.*,
                o.order_id,
                o.status as current_order_status,
                CASE WHEN n.type = 'Job Order' THEN jo.job_title ELSE 
                    (SELECT p.name FROM order_items oi 
                     LEFT JOIN products p ON oi.product_id = p.product_id 
                     WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1)
                END as service_name,
                CASE WHEN n.type = 'Job Order' THEN jo.service_type ELSE NULL END as jo_service_category,
                CASE WHEN n.type = 'Job Order' THEN jo.artwork_path ELSE 
                    (SELECT {$product_image_column} FROM products p 
                     INNER JOIN order_items oi ON oi.product_id = p.product_id 
                     WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1)
                END as product_image_path,
                (SELECT oi.customization_data FROM order_items oi 
                 WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization,
                (SELECT oi.order_item_id FROM order_items oi 
                 WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_id,
                (SELECT oi.design_image FROM order_items oi 
                 WHERE oi.order_id = n.data_id AND oi.design_image IS NOT NULL ORDER BY oi.order_item_id ASC LIMIT 1) as design_image
            FROM notifications n
            LEFT JOIN orders o ON n.data_id = o.order_id AND (n.type = 'Order' OR n.type = 'Payment' OR n.type = 'Status')
            LEFT JOIN job_orders jo ON n.data_id = jo.id AND n.type = 'Job Order'
            WHERE " . ($user_type === 'Customer' ? "n.customer_id = ?" : "n.user_id = ?") . "
            ORDER BY n.created_at DESC
            LIMIT " . (int)$limit;

    $rows = db_query($sql, 'i', [$user_id]);
    $notifications = [];

    if ($rows) {
        foreach ($rows as $n) {
            // Determine service name (logic from notifications.php)
            $name_data = !empty($n['first_item_customization']) ? json_decode($n['first_item_customization'], true) : [];
            $raw_service_name = trim((string)($name_data['service_type'] ?? $n['jo_service_category'] ?? $n['service_name'] ?? ''));
            if (empty($raw_service_name) || in_array(strtolower($raw_service_name), ['custom order', 'customer order', 'service order', 'order item', 'order update'])) {
                $raw_service_name = get_service_name_from_customization($name_data, $n['service_name'] ?? 'Order Update');
            }
            $display_name = normalize_service_name($raw_service_name, 'Order Update');

            // Determine image
            $final_image_url = "";
            if (!empty($n['design_image'])) {
                $final_image_url = "/printflow/staff/get_design_image.php?id=" . $n['first_item_id'];
            } elseif (!empty($n['product_image_path'])) {
                $final_image_url = $n['product_image_path'];
                if (strpos($final_image_url, 'uploads/') === 0) {
                    $final_image_url = '/printflow/' . $final_image_url;
                }
            } else {
                $final_image_url = get_service_image_url($raw_service_name ?: $display_name);
            }

            // Determine deep link (logic from notifications.php)
            $link = "";
            if (!empty($n['data_id'])) {
                $msg_text = (string)$n['message'];
                $is_rating_notif = ($n['type'] === 'Rating' || stripos($msg_text, 'rate') !== false);
                $is_payment_notif = ($n['type'] === 'Payment' || stripos($msg_text, 'payment') !== false);
                
                if ($is_rating_notif) {
                    $link = "/printflow/customer/rate_order.php?order_id=" . $n['data_id'];
                } elseif ($is_payment_notif) {
                    $link = "/printflow/customer/payment.php?order_id=" . $n['data_id'];
                } elseif ($n['type'] === 'Order' || $n['type'] === 'Status') {
                    $status = strtolower(trim($n['current_order_status'] ?? ''));
                    if (strpos($status, 'pending') !== false) $tab = 'pending';
                    elseif (strpos($status, 'approved') !== false) $tab = 'approved';
                    elseif (strpos($status, 'verify') !== false) $tab = 'toverify';
                    elseif (strpos($status, 'to pay') !== false) $tab = 'topay';
                    elseif (strpos($status, 'production') !== false || strpos($status, 'processing') !== false) $tab = 'production';
                    elseif (strpos($status, 'ready') !== false) $tab = 'pickup';
                    elseif (strpos($status, 'completed') !== false || strpos($status, 'rated') !== false) $tab = 'completed';
                    else $tab = 'all';
                    $link = "/printflow/customer/orders.php?tab=" . $tab . "&highlight=" . $n['data_id'];
                } elseif ($n['type'] === 'Job Order') {
                    $link = "/printflow/customer/order_details.php?id=" . $n['data_id'];
                } elseif ($n['type'] === 'Message') {
                    $link = "/printflow/customer/chat.php?order_id=" . $n['data_id'];
                }
            }

            $notifications[] = [
                'id'            => $n['notification_id'],
                'message'       => $n['message'],
                'type'          => $n['type'],
                'data_id'       => $n['data_id'],
                'is_read'       => $n['is_read'],
                'created_at'    => $n['created_at'],
                'display_name'  => $display_name,
                'display_image' => $final_image_url,
                'target_link'   => $link
            ];
        }
    }

    $unread = get_unread_notification_count($user_id, $user_type);

    echo json_encode([
        'success'       => true,
        'notifications' => $notifications,
        'unread_count'  => (int) $unread
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'notifications' => [], 'unread_count' => 0]);
}
