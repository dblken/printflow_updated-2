<?php
/**
 * AJAX: Get Order Items (Customer)
 * Returns order items + full order details as JSON for modal display
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

header('Content-Type: application/json');

$order_id = (int)($_GET['id'] ?? 0);
$customer_id = get_user_id();

if (!$order_id) {
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

// Verify order belongs to this customer
$order_result = db_query("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
if (empty($order_result)) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}
$order = $order_result[0];

// Get items with design info
$items = db_query("
    SELECT oi.*, p.name as product_name, p.category
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

$items_out = [];
foreach ($items as $item) {
    $custom_data = json_decode($item['customization_data'] ?? '{}', true) ?: [];
    unset($custom_data['design_upload'], $custom_data['reference_upload']);

    $items_out[] = [
        'order_item_id' => (int)$item['order_item_id'],
        'product_name'  => (function() use ($item, $custom_data) {
            // Priority 1: Sintra Board
            if (!empty($custom_data['sintra_type'])) {
                return 'Sintra Board - ' . $custom_data['sintra_type'];
            }
            
            // Priority 2: Tarpaulin
            if (!empty($custom_data['tarp_size']) || (!empty($custom_data['width']) && !empty($custom_data['height']))) {
                $size = $custom_data['tarp_size'] ?? ($custom_data['width'] . 'x' . $custom_data['height'] . 'ft');
                return 'Tarpaulin Printing - ' . $size;
            }
            
            // Priority 3: Vinyl T-Shirt
            if (!empty($custom_data['vinyl_type'])) {
                return 'T-Shirt Printing (Vinyl)';
            }
            
            // Priority 4: Stickers
            if (!empty($custom_data['sticker_type'])) {
                return 'Decals/Stickers';
            }

            // Priority 5: Pre-defined Product Name (if not generic)
            $genericNames = ['custom order', 'customer order', 'service order', 'order item', 'sticker pack', 'merchandise'];
            if (!empty($item['product_name']) && !in_array(strtolower(trim($item['product_name'])), $genericNames)) {
                return normalize_service_name($item['product_name'], 'Order Item');
            }

            // Priority 6: Service Type from Customization
            if (!empty($custom_data['service_type'])) {
                $name = normalize_service_name($custom_data['service_type'], 'Order Item');
                if (!empty($custom_data['product_type'])) {
                    $name .= " (" . $custom_data['product_type'] . ")";
                }
                return $name;
            }

            return 'Order Item';
        })(),
        'category'      => (strtolower($item['category'] ?? '') === 'merchandise') ? '' : ($item['category'] ?? ''),
        'quantity'      => (int)$item['quantity'],
        'unit_price'    => format_currency($item['unit_price']),
        'subtotal'      => format_currency($item['quantity'] * $item['unit_price']),
        'customization' => $custom_data,
        'has_design'    => !empty($item['design_image']) || !empty($item['design_file']),
        'has_reference' => !empty($item['reference_image_file']),
        'design_url'    => (!empty($item['design_image']) || !empty($item['design_file']))
                            ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id']
                            : null,
        'reference_url' => !empty($item['reference_image_file'])
                            ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] . '&field=reference'
                            : null,
    ];
}

// Cancellation / revision details
$cancel_info = '';
if ($order['status'] === 'Cancelled') {
    $cancel_info = trim(($order['cancelled_by'] ? 'By: ' . $order['cancelled_by'] : '') . ' | ' . ($order['cancel_reason'] ?? ''), ' |');
}

$can_cancel = can_customer_cancel_order($order);
$restriction_msg = '';
if (!$can_cancel && !in_array($order['status'], ['Cancelled', 'Completed'])) {
    switch ($order['status']) {
        case 'To Pay':
            $restriction_msg = "Order #" . $order['order_id'] . " is already ready for payment.";
            break;
        case 'In Production':
        case 'Printing':
            $restriction_msg = "Order #" . $order['order_id'] . " is already in production.";
            break;
        case 'Ready for Pickup':
            $restriction_msg = "Order #" . $order['order_id'] . " is already ready for pickup.";
            break;
        default:
            $restriction_msg = "Order #" . $order['order_id'] . " is already being processed.";
            break;
    }
}

// Rating details
$rating_data = null;
if (in_array($order['status'], ['Completed', 'To Rate', 'Rated'], true)) {
    $rating_res = db_query("SELECT * FROM ratings WHERE order_id = ?", 'i', [$order_id]);
    if (!empty($rating_res)) {
        $r = $rating_res[0];
        $rating_data = [
            'rating' => (int)$r['rating'],
            'comment' => $r['comment'] ?? '',
            'image_url' => !empty($r['image']) ? $r['image'] : null,
            'created_at' => format_datetime($r['created_at'])
        ];
    }
}

echo json_encode([
    'order_id'         => $order['order_id'],
    'order_date'       => format_datetime($order['order_date']),
    'total_amount'     => format_currency($order['total_amount']),
    'status'           => $order['status'],
    'payment_status'   => $order['payment_status'],
    'estimated_comp'   => ($order['estimated_completion'] ?? null) ? format_date($order['estimated_completion']) : 'Waiting for confirmation from the shop',
    'notes'            => $order['notes'] ?? '',
    'cancelled_by'     => $order['cancelled_by'] ?? '',
    'cancel_reason'    => $order['cancel_reason'] ?? '',
    'cancelled_at'     => !empty($order['cancelled_at']) ? format_datetime($order['cancelled_at']) : '',
    'design_status'    => $order['design_status'] ?? 'Pending',
    'revision_reason'  => $order['revision_reason'] ?? '',
    'items'            => $items_out,
    'can_cancel'       => $can_cancel,
    'cancel_restriction_msg' => $restriction_msg,
    'rating_data'      => $rating_data,
    'csrf_token'       => generate_csrf_token()
]);

