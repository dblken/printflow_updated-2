<?php
/**
 * Order Details API for Chat Modal
 * Used by both Customer (own orders) and Staff (any order)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Prevent accidental output (notices, etc.) from breaking JSON
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$order_id = (int)($_GET['order_id'] ?? $_GET['id'] ?? 0);
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

// Access control: Customer = own orders only; Staff/Admin/Manager = any order
if ($user_type === 'Customer') {
    $order_result = db_query("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $user_id]);
} else {
    $order_result = db_query("SELECT * FROM orders WHERE order_id = ?", 'i', [$order_id]);
}

if (empty($order_result)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
$order = $order_result[0];

// Get customer info (join from customers table)
$customer = [];
$customer_id = (int)($order['customer_id'] ?? 0);
if ($customer_id) {
    $addr_cols = array_column(db_query("SHOW COLUMNS FROM customers") ?: [], 'Field');
    $addr_sel = '';
    if (in_array('address', $addr_cols)) {
        $addr_sel = ', c.address';
    } elseif (count(array_intersect($addr_cols, ['street_address', 'barangay', 'city', 'province'])) === 4) {
        $addr_sel = ", CONCAT_WS(', ', NULLIF(TRIM(c.street_address),''), NULLIF(TRIM(c.barangay),''), NULLIF(TRIM(c.city),''), NULLIF(TRIM(c.province),'')) as address";
    }
    $cust_result = db_query("
        SELECT c.first_name, c.middle_name, c.last_name, c.email, c.contact_number, c.profile_picture
        $addr_sel
        FROM customers c WHERE c.customer_id = ?
    ", 'i', [$customer_id]);
    if (!empty($cust_result)) {
        $c = $cust_result[0];
        $customer = [
            'full_name' => trim(($c['first_name'] ?? '') . ' ' . ($c['middle_name'] ?? '') . ' ' . ($c['last_name'] ?? '')),
            'contact_number' => $c['contact_number'] ?? '',
            'email' => $c['email'] ?? '',
            'profile_picture' => get_profile_image($c['profile_picture'] ?? null),
        ];
        if (isset($c['address']) && trim((string)$c['address']) !== '') {
            $customer['address'] = trim($c['address']);
        }
    }
}

// Get items with product info
$has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
$has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
$product_image_select = "'' AS product_image";
if ($has_product_image && $has_photo_path) {
    $product_image_select = "COALESCE(p.photo_path, p.product_image) AS product_image";
} elseif ($has_product_image) {
    $product_image_select = "p.product_image AS product_image";
} elseif ($has_photo_path) {
    $product_image_select = "p.photo_path AS product_image";
}

$items = db_query("
    SELECT oi.*, p.name as product_name, p.category, {$product_image_select}
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

$price_pending_statuses = ['Pending', 'Pending Review', 'Pending Approval', 'For Revision', 'Approved'];
$show_price = !in_array($order['status'], $price_pending_statuses, true);

$items_out = [];
foreach ($items ?: [] as $item) {
    $custom_data = json_decode($item['customization_data'] ?? '{}', true) ?: [];
    unset($custom_data['design_upload'], $custom_data['reference_upload']);

    $product_name = get_service_name_from_customization($custom_data, 'Order Item');
    $product_name = normalize_service_name($product_name, 'Order Item');
    // Prioritize service_type from customization data over the base product name
    $raw_service_name = !empty($custom_data['service_type']) ? $custom_data['service_type'] : '';
    if (empty($raw_service_name) && !empty($item['product_name'])) {
        $raw_service_name = $item['product_name'];
    }
    
    // Check if the resulting name is generic. If it is, and we have a service_type, we might want to ensure we use it,
    // but our logic above already prioritizes service_type. Let's just normalize it now.
    $product_name = normalize_service_name($raw_service_name, 'Order Item');

    $design_url = null;
    $ref_url = null;
    if (!empty($item['design_image']) || !empty($item['design_file'])) {
        $design_url = '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'];
    } elseif (!empty($item['product_image'])) {
        $design_url = (strpos($item['product_image'], '/') === 0) ? $item['product_image'] : '/printflow/' . ltrim($item['product_image'], '/');
    }
    if (!empty($item['reference_image_file'])) {
        $ref_url = '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] . '&field=reference';
    }

    $service_id = !empty($custom_data['service_id']) ? (int)$custom_data['service_id'] : 0;
    $service_image = get_customer_notification_service_image($service_id, $product_name);

    if (!$design_url) {
        $design_url = $service_image;
    }

    $items_out[] = [
        'order_item_id'     => (int)$item['order_item_id'],
        'product_name'      => $product_name,
        'category'          => $item['category'] ?? '',
        'quantity'          => (int)$item['quantity'],
        'unit_price'        => $show_price ? format_currency($item['unit_price']) : null,
        'subtotal'          => $show_price ? format_currency($item['quantity'] * $item['unit_price']) : null,
        'customization'     => $custom_data,
        'design_url'        => $design_url,
        'service_image'     => $service_image,
        'reference_url'     => $ref_url,
    ];
}

// Clear accidental output before sending JSON
ob_end_clean();
echo json_encode([
    'success'  => true,
    'customer' => $customer,
    'order' => [
        'order_id'       => (int)$order['order_id'],
        'order_date'     => format_datetime($order['order_date']),
        'status'         => $order['status'],
        'payment_status' => $order['payment_status'] ?? '',
        'total_amount'   => $show_price ? format_currency($order['total_amount']) : null,
        'notes'          => $order['notes'] ?? '',
        'revision_reason'=> $order['revision_reason'] ?? '',
    ],
    'items' => $items_out,
]);
