<?php
/**
 * API: POS Checkout Process
 * Path: staff/api/pos_checkout.php
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/product_branch_stock.php';

// Require staff or admin role
if (!has_role(['Admin', 'Staff'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data. Cart is empty.']);
    exit;
}

$customer_id = $data['customer_id'] === 'guest' ? null : (int)$data['customer_id'];

if ($customer_id === null) {
    global $conn;
    $res = db_query("SELECT customer_id FROM customers WHERE first_name='Walk-in' AND last_name='Guest' LIMIT 1");
    if (!empty($res)) {
        $customer_id = (int)$res[0]['customer_id'];
    } else {
        db_execute("INSERT INTO customers (first_name, last_name, status, created_at) VALUES ('Walk-in', 'Guest', 'Activated', NOW())");
        $customer_id = $conn->insert_id;
    }
}
$payment_method = sanitize($data['payment_method'] ?? 'Cash');
$reference_number = sanitize($data['reference_number'] ?? '');
$amount_tendered = (float)($data['amount_tendered'] ?? 0);
$items = $data['items'];

if ($payment_method !== 'Cash' && empty($reference_number)) {
    echo json_encode(['success' => false, 'message' => "Reference number is required for $payment_method."]);
    exit;
}
if ($amount_tendered > 1000000) {
    echo json_encode(['success' => false, 'message' => 'Amount tendered exceeds maximum limit of ₱1,000,000.']);
    exit;
}

printflow_ensure_product_branch_stock_table();
$pos_branch_id = (int)($_SESSION['branch_id'] ?? 0);

// Calculate total and verify stock
$total_amount = 0;
$products_cache = [];
foreach ($items as $item) {
    $product_id = (int)$item['id'];
    $qty = (int)$item['qty'];
    
    $product = db_query("SELECT price, name FROM products WHERE product_id = ?", 'i', [$product_id]);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found: ' . $product_id]);
        exit;
    }
    $p = $product[0];
    $products_cache[$product_id] = $p;

    [$effStock] = printflow_product_effective_stock($product_id, $pos_branch_id);
    if ($effStock < $qty) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock for ' . $p['name']]);
        exit;
    }
    
    // For POS walk-ins, we trust the negotiated price sent from the frontend 
    // especially for "Services" or custom jobs.
    $price = (float)($item['price'] ?? $p['price']);
    $total_amount += $price * $qty;
}

try {
    global $conn;
    $conn->begin_transaction();

    // Create Order
    // For POS walk-ins, we use status 'Completed' and payment_status 'Paid'
    $branch_id = (int)($_SESSION['branch_id'] ?? 1);
    if ($branch_id < 1) $branch_id = 1;

    // Determine order_type based on items (if any has customization, map to custom)
    $order_type = 'product';
    $reference_id = $items[0]['id'] ?? null;
    foreach ($items as $item) {
        if (!empty($item['customization'])) {
            $order_type = 'custom';
            break;
        }
    }

    $order_result = db_execute(
        "INSERT INTO orders (customer_id, branch_id, reference_id, total_amount, amount_paid, status, payment_status, payment_method, payment_reference, order_date, updated_at, order_type) 
         VALUES (?, ?, ?, ?, ?, 'Completed', 'Paid', ?, ?, NOW(), NOW(), ?)",
        'iiiddsss',
        [$customer_id, $branch_id, $reference_id, $total_amount, $total_amount, $payment_method, $reference_number, $order_type]
    );

    if (!$order_result) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to create order.']);
        exit;
    }

    $order_id = $conn->insert_id;

    // Insert Order Items and Update Stock
    foreach ($items as $item) {
        $product_id = (int)$item['id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $p = $products_cache[$product_id] ?? null;
        $prod_name = $p['name'] ?? 'Product';

        $name = $item['name'] ?? $prod_name;
        $custom_details = $item['customization'] ?? [];
        if (!is_array($custom_details)) $custom_details = [];
        
        if ($name !== $prod_name) {
            $custom_details['Service/Item Name'] = $name;
        }
        
        $customization_json = !empty($custom_details) ? json_encode($custom_details) : null;

        $item_result = db_execute(
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data) VALUES (?, ?, ?, ?, ?)",
            'iiids',
            [$order_id, $product_id, $qty, $price, $customization_json]
        );

        if (!$item_result) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to add order items.']);
            exit;
        }

        // Deduct stock (branch entry when present, else global products)
        if (!printflow_product_deduct_stock_for_branch($product_id, $branch_id, $qty)) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to update stock.']);
            exit;
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Sale completed successfully.']);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
