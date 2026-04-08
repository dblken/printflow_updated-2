<?php
/**
 * AJAX: Get Order Data (Staff)
 * Returns full order details as JSON for modal display
 */

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

header('Content-Type: application/json');
ob_clean();

try {

// Allow Staff, Admin and Manager to access order data
if (!is_logged_in() || !in_array(get_user_type(), ['Staff', 'Admin', 'Manager'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$branchFilter = printflow_branch_filter_for_user();

$action = $_GET['action'] ?? 'get_order';

if ($action === 'list_orders') {
    $status = $_GET['status'] ?? '';
    $sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            (SELECT GROUP_CONCAT(COALESCE(p.name, 'Custom Product') SEPARATOR ', ') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as item_names
            FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id WHERE 1=1";
    $params = [];
    $types = '';

    if ($branchFilter !== null) {
        $sql .= " AND o.branch_id = ?";
        $params[] = $branchFilter;
        $types .= 'i';
    }
    
    if (!empty($status)) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $sql .= " ORDER BY o.order_date DESC LIMIT 50";
    $orders = db_query($sql, $types, $params);
    
    // Format for JS consumption
    foreach ($orders as &$o) {
        $o['order_date_fmt'] = format_date($o['order_date']);
        $o['total_amount_fmt'] = format_currency($o['total_amount']);
    }
    
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

// Get order with customer info
if ($branchFilter !== null) {
    $order_result = db_query("
        SELECT o.*,
               c.first_name as cust_first, c.last_name as cust_last,
               c.email as cust_email, c.contact_number as cust_phone,
               c.customer_id as cust_id
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.order_id = ? AND o.branch_id = ?
    ", 'ii', [$order_id, $branchFilter]);
} else {
    $order_result = db_query("
        SELECT o.*,
               c.first_name as cust_first, c.last_name as cust_last,
               c.email as cust_email, c.contact_number as cust_phone,
               c.customer_id as cust_id
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.order_id = ?
    ", 'i', [$order_id]);
}

if (empty($order_result)) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}
$order = $order_result[0];

// Get order items
$items = db_query("
    SELECT oi.*, p.name as product_name, p.sku, p.category
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

    // Removed other orders from same customer as requested
    $customer_orders = [];

// Build items array
$items_out = [];
foreach ($items as $item) {
    $custom_data = json_decode($item['customization_data'] ?? '{}', true) ?? [];
    // Remove design_upload key from display
    unset($custom_data['design_upload']);

    $items_out[] = [
        'order_item_id' => $item['order_item_id'],
        'product_name'  => (function() use ($item, $custom_data) {
            if (!empty($item['product_name'])) return $item['product_name'];
            $name = get_service_name_from_customization($custom_data, 'Custom Order');
            if (!empty($custom_data['product_type']) && $custom_data['product_type'] !== $name) {
                $name .= " (" . $custom_data['product_type'] . ")";
            }
            return $name;
        })(),
        'sku'           => $item['sku'] ?? '',
        'category'      => $item['category'] ?? '',
        'quantity'      => (int)$item['quantity'],
        'unit_price'    => (float)$item['unit_price'],
        'subtotal'      => (float)($item['quantity'] * $item['unit_price']),
        'customization' => $custom_data,
        'has_design'    => !empty($item['design_image']) || !empty($item['design_file']),
        'has_reference' => !empty($item['reference_image_file']),
        'design_name'   => $item['design_image_name'] ?? 'design_file',
        'design_url'    => (!empty($item['design_image']) || !empty($item['design_file']))
                            ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id']
                            : null,
        'reference_url' => !empty($item['reference_image_file'])
                            ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] . '&field=reference'
                            : null,
    ];
}

// Build customer orders array
$cust_orders_out = [];
foreach ($customer_orders as $co) {
    $cust_orders_out[] = [
        'order_id'     => $co['order_id'],
        'order_date'   => format_date($co['order_date']),
        'total_amount' => format_currency($co['total_amount']),
        'status'       => $co['status'],
    ];
}

// Get revision history - table doesn't exist yet, return empty array
$revisions_out = [];

echo json_encode([
    'order_id'            => $order['order_id'],
    'order_date'          => format_datetime($order['order_date']),
    'total_amount'        => format_currency($order['total_amount']),
    'total_raw'           => (float)$order['total_amount'],
    'status'              => $order['status'],
    'payment_status'      => $order['payment_status'],
    'payment_reference'   => $order['payment_reference'] ?? '',
    'payment_type'        => $order['payment_type'] ?? 'full_payment',
    'downpayment_amount'  => (float)($order['downpayment_amount'] ?? 0),
    'notes'               => $order['notes'] ?? '',
    'cancelled_by'        => $order['cancelled_by'] ?? '',
    'cancel_reason'       => $order['cancel_reason'] ?? '',
    'cancelled_at'        => !empty($order['cancelled_at']) ? format_datetime($order['cancelled_at']) : '',
    'design_status'       => $order['design_status'] ?? 'Pending',
    'reviewed_by'         => $order['reviewed_by'] ?? null,
    'reviewed_at'         => !empty($order['reviewed_at']) ? format_datetime($order['reviewed_at']) : '',
    'cust_name'           => trim(($order['cust_first'] ?? '') . ' ' . ($order['cust_last'] ?? '')),
    'cust_initial'        => strtoupper(substr($order['cust_first'] ?? 'C', 0, 1)),
    'cust_email'          => $order['cust_email'] ?? '',
    'cust_phone'          => $order['cust_phone'] ?? '',
    'payment_proof'       => !empty($order['payment_proof']) ? '/printflow' . $order['payment_proof'] : null,
    'payment_submitted_at'=> !empty($order['payment_submitted_at']) ? format_datetime($order['payment_submitted_at']) : '',
    'revision_count'      => (int)($order['revision_count'] ?? 0),
    'revision_reason'     => $order['revision_reason'] ?? '',
    'items'               => $items_out,
    'customer_orders'     => $cust_orders_out,
    'revisions'           => $revisions_out,
    'csrf_token'          => generate_csrf_token(),
]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
