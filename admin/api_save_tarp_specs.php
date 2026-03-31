<?php
/**
 * Admin: Save Tarpaulin Specifications API
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/api_header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

require_role(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!verify_csrf_token($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$order_item_id = (int)($input['order_item_id'] ?? 0);
$roll_id      = (int)($input['roll_id'] ?? 0);
$width_ft     = (float)($input['width_ft'] ?? 0);
$height_ft    = (float)($input['height_ft'] ?? 0);

if (!$order_item_id || !$roll_id || $width_ft <= 0 || $height_ft <= 0) {
    echo json_encode(['success' => false, 'error' => 'All fields required']);
    exit;
}

// Get item quantity to calculate total required length
$item = db_query("SELECT quantity, order_id FROM order_items WHERE order_item_id = ?", 'i', [$order_item_id]);
if (empty($item)) {
    echo json_encode(['success' => false, 'error' => 'Order item not found']);
    exit;
}
printflow_assert_order_branch_access((int)$item[0]['order_id']);
$qty = (int)$item[0]['quantity'];
$required_length = $height_ft * $qty;

// Check if roll matches width (optional but recommended)
$roll = db_query("SELECT width_ft FROM inv_rolls WHERE id = ?", 'i', [$roll_id]);
if (empty($roll)) {
    echo json_encode(['success' => false, 'error' => 'Roll not found']);
    exit;
}
// if ((int)$roll[0]['width_ft'] !== (int)$width_ft) {
//     echo json_encode(['success' => false, 'error' => 'Roll width mismatch']);
//     exit;
// }

// Upsert into order_tarp_details
$existing = db_query("SELECT order_item_id FROM order_tarp_details WHERE order_item_id = ?", 'i', [$order_item_id]);

if (!empty($existing)) {
    $ok = db_execute(
        "UPDATE order_tarp_details SET roll_id = ?, width_ft = ?, height_ft = ?, qty = ?, required_length_ft = ? WHERE order_item_id = ?",
        'iddidi', [$roll_id, $width_ft, $height_ft, $qty, $required_length, $order_item_id]
    );
} else {
    $ok = db_execute(
        "INSERT INTO order_tarp_details (order_item_id, roll_id, width_ft, height_ft, qty, required_length_ft) VALUES (?, ?, ?, ?, ?, ?)",
        'iiddid', [$order_item_id, $roll_id, $width_ft, $height_ft, $qty, $required_length]
    );
}

if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Tarpaulin specifications saved']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save specifications']);
}
