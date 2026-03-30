<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $order_id = (int)($_POST['order_id'] ?? 0);
    $customer_id = get_user_id();

    if (!$order_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit;
    }

    // Verify order ownership
    $order = db_query("SELECT status FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
    if (empty($order)) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    if (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['design_file'];
    
    // Validate file
    $validation = validate_file_upload($file);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'error' => $validation['message']]);
        exit;
    }

    // Read file content for BLOB
    $file_data = file_get_contents($file['tmp_name']);
    $mime_type = $file['type'];
    $file_name = $file['name'];

    // Update the FIRST item in the order with the new design (simplification for this project)
    // In a multi-item order, we'd ideally specify which item to update, 
    // but the current UI implies one primary design per order review flow.
    // Update ALL items in the order with the new design for consistency
    // This ensures that the "Pending" section shows the latest design for all line items.
    $sql = "UPDATE order_items 
            SET design_image = ?, design_image_mime = ?, design_image_name = ?, design_file = NULL 
            WHERE order_id = ?";
    
    $success = db_execute($sql, 'bssi', [$file_data, $mime_type, $file_name, $order_id]);

    if ($success) {
        // Set design status to Revision Submitted and order status back to Pending
        db_execute(
            "UPDATE orders SET design_status = 'Revision Submitted', status = 'Pending', revision_reason = '' WHERE order_id = ?",
            'i', [$order_id]
        );
        
        // Also update associated job orders back to PENDING and clear stale files
        db_execute("UPDATE job_orders SET status = 'PENDING', updated_at = NOW() WHERE order_id = ?", 'i', [$order_id]);
        
        // Clear any previous production/artwork files associated with these job orders to avoid confusion
        // as the customer has just provided a new master design.
        $jobs = db_query("SELECT id FROM job_orders WHERE order_id = ?", 'i', [$order_id]);
        foreach ($jobs as $j) {
            db_execute("DELETE FROM job_order_files WHERE job_order_id = ?", 'i', [$j['id']]);
        }

        log_activity($customer_id, 'Design Re-upload', "Customer re-uploaded design for Order #$order_id");

        // Notify Staff/Admin of this branch
        $branch_id = db_query("SELECT branch_id FROM orders WHERE order_id = ?", 'i', [$order_id])[0]['branch_id'] ?? 1;
        $staff_to_notify = db_query(
            "SELECT user_id FROM users WHERE role IN ('Staff', 'Admin', 'Manager') AND (branch_id = ? OR role = 'Admin')", 
            'i', [$branch_id]
        );

        $cust_row = db_query("SELECT first_name, last_name FROM customers WHERE customer_id = ?", 'i', [$customer_id]);
        $cust_name = !empty($cust_row) ? trim($cust_row[0]['first_name'] . ' ' . $cust_row[0]['last_name']) : "Customer";
        
        $first_item = db_query("SELECT customization_data FROM order_items WHERE order_id = ? LIMIT 1", 'i', [$order_id]);
        $service_name = 'Service Order';
        if (!empty($first_item)) {
            $custom_data = json_decode($first_item[0]['customization_data'] ?? '[]', true);
            $service_name = get_service_name_from_customization($custom_data, 'Service Order');
        }

        foreach ($staff_to_notify as $st) {
            create_notification(
                $st['user_id'], 
                'User', 
                "{$cust_name} re-uploaded design for {$service_name}", 
                'Order', 
                false, 
                false, 
                $order_id
            );
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save design to database']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

