<?php
/**
 * AJAX: Update Order Status (Staff)
 * Handles status changes and stock deduction when completed
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/product_branch_stock.php';

require_role(['Staff', 'Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$new_status = $_POST['status'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? '';

if (!verify_csrf_token($csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

if (!$order_id || !$new_status) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// 1. Get current status to avoid double-deduction
$order_row = db_query("SELECT status, branch_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
if (empty($order_row)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$old_status = $order_row[0]['status'];

// 2. Access control (Staff must be in same branch)
if (get_user_type() === 'Staff') {
    $staff_branch = $_SESSION['branch_id'] ?? 0;
    if ($order_row[0]['branch_id'] != $staff_branch) {
        echo json_encode(['success' => false, 'error' => 'Permission denied: Order belongs to another branch']);
        exit;
    }
}

// 3. Update Status
$update_sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
$result = db_execute($update_sql, 'si', [$new_status, $order_id]);

if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Failed to update order status']);
    exit;
}


// 4. Stock Deduction Logic
if ($new_status === 'Completed' && $old_status !== 'Completed') {
    $branch_id = (int)$order_row[0]['branch_id'];
    $items = db_query("SELECT product_id, quantity FROM order_items WHERE order_id = ?", 'i', [$order_id]);
    
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        
        if ($pid > 0 && $qty > 0) {
            // Use branch-aware deduction
            if (printflow_product_deduct_stock_for_branch($pid, $branch_id, $qty)) {
                // Log in inventory_ledger
                try {
                    db_execute("INSERT INTO inventory_ledger (product_id, branch_id, transaction_type, quantity, details, created_at) 
                               VALUES (?, ?, 'Deduction', ?, ?, NOW())", 'iiis', 
                               [$pid, $branch_id, $qty, "Automated deduction for Order #$order_id completion"]);
                } catch (Throwable $e) { }
            }
        }
    }
}

// 5. System Message
add_order_system_message($order_id, "Order status updated to '{$new_status}' by " . $_SESSION['user_name']);

echo json_encode(['success' => true, 'message' => "Order #$order_id marked as $new_status"]);
