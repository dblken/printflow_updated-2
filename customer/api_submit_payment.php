<?php
/**
 * API: Submit Payment Proof
 * PrintFlow - Printing Shop PWA
 */

// Disable HTML error output to prevent breaking JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/JobOrderService.php';

require_role('Customer');

header('Content-Type: application/json');

try {
    // Start output buffering to catch any accidental output from included files
    ob_start();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $order_id = (int)($_POST['order_id'] ?? 0);
    $is_job = (bool)($_POST['is_job'] ?? false);
    $customer_id = get_user_id();
    $payment_choice = $_POST['payment_choice'] ?? 'full';
    if (!in_array($payment_choice, ['full', 'half'], true)) {
        $payment_choice = 'full';
    }
    error_log("[DEBUG] Incoming payment_method POST: " . print_r($_POST['payment_method'] ?? '(not set)', true));

    // 1. Validate order in correct table
    if (!$is_job) {
        $order_result = db_query("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
        if (empty($order_result)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }
        $order = $order_result[0];
        $total_to_pay = (float)$order['total_amount'];
        $target_order_id = $order_id;
    } else {
        $order_result = db_query("SELECT * FROM job_orders WHERE id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
        if (empty($order_result)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Job Order not found']);
            exit;
        }
        $order = $order_result[0];
        $total_to_pay = (float)$order['estimated_total'];
        $target_order_id = $order['order_id'] ?? $order_id;
    }

    // Validate amount and proof
    $amount = (float)($_POST['amount'] ?? 0);
    $min_required = ($payment_choice === 'half') ? $total_to_pay * 0.5 : $total_to_pay;

    if ($amount < $min_required - 0.01) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Amount must be at least ' . ($payment_choice === 'half' ? '50%' : '100%') . ' of the total (' . format_currency($min_required) . ')']);
        exit;
    }

    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        $upload_err = $_FILES['payment_proof']['error'] ?? 'MISSING_FILE';
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Please upload a proof of payment. (Error: ' . $upload_err . ')']);
        exit;
    }

    $upload = upload_file($_FILES['payment_proof'], ['jpg', 'jpeg', 'png', 'webp'], 'payments');
    if (!$upload['success']) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $upload['error']]);
        exit;
    }

    $file_path = $upload['file_path'];
    $payment_type = ($payment_choice === 'half') ? '50_percent' : 'full_payment';

    // ── OCR Extraction & Payment Method Determination (Early) ──────────────────
    // Extract this BEFORE database updates so we can include payment_method in UPDATE statements
    $abs_path = __DIR__ . '/../' . ltrim(str_replace('/printflow/', '', $file_path), '/');
    $ocr_details = extract_payment_details($abs_path);

    // ── Determine Payment Method ──────────────────────────────────────────────
    $manual_method   = $_POST['payment_method'] ?? null;
    $detected_method = $ocr_details['payment_method'] ?? 'Unknown';

    $decision_source = 'Fallback';
    if ($manual_method && !in_array($manual_method, ['', 'Unknown'])) {
        $payment_method  = $manual_method;
        $decision_source = 'Manual';
    } else {
        $payment_method  = ($detected_method !== 'Unknown') ? $detected_method : 'GCash';
        $decision_source = ($detected_method !== 'Unknown') ? 'OCR' : 'Default';
    }

    // Debug Logging
    error_log("[DEBUG] Final payment_method to save: $payment_method (Source: $decision_source)");

    if (!$is_job) {
        // 3. Update regular order (including payment_method)
        $sql = "UPDATE orders SET 
            status = 'To Verify', 
            payment_type = ?,
            payment_method = ?,
            downpayment_amount = ?, 
            payment_proof = ?, 
            payment_submitted_at = NOW() 
            WHERE order_id = ?";
        error_log("[DEBUG] Saving to orders: payment_method = $payment_method for order_id = $order_id");
        $update_success = db_execute($sql, 'ssdsi', [$payment_type, $payment_method, $amount, $file_path, $order_id]);
        $type_label = "Order";
    } else {
        // 4. Update job order (including payment_method)
        $sql = "UPDATE job_orders SET 
            status = 'VERIFY_PAY',
            payment_method = ?,
            payment_proof_status = 'SUBMITTED', 
            payment_proof_path = ?, 
            payment_submitted_amount = ?, 
            payment_proof_uploaded_at = NOW() 
            WHERE id = ?";
        error_log("[DEBUG] Saving to job_orders: payment_method = $payment_method for job_id = $order_id");
        $update_success = db_execute($sql, 'ssdi', [$payment_method, $file_path, $amount, $order_id]);
        $type_label = "Job Order";
    }

    if ($update_success) {
        // Keep linked production jobs in sync so staff Customizations → TO_VERIFY tab shows this row
        if (!$is_job) {
            JobOrderService::ensureJobsForStoreOrder($order_id);
            db_execute(
                "UPDATE job_orders SET
                    payment_method = ?,
                    status = 'VERIFY_PAY',
                    payment_proof_status = 'SUBMITTED',
                    payment_submitted_amount = ?,
                    payment_proof_path = ?,
                    payment_proof_uploaded_at = NOW()
                 WHERE order_id = ?
                   AND status NOT IN ('COMPLETED','CANCELLED')",
                'sdsi',
                [$payment_method, $amount, $file_path, $order_id]
            );
        }

        // ── Insert into Payments table (Strict Version) ──────────────────────────
        
        $sender_name = $ocr_details['sender_name'] ?? null;
        
        // OCR Priority with Manual Fallback
        $ref_id = $ocr_details['reference_id'] ?? (!empty($_POST['manual_ref']) ? trim($_POST['manual_ref']) : null);
        $ocr_amount = $ocr_details['amount'] ?? (!empty($_POST['manual_amount']) ? (float)$_POST['manual_amount'] : null);
        
        // Strict Validation: Incomplete if any core field is missing
        $is_incomplete  = ($sender_name === null || $ocr_amount === null || $ref_id === null);
        $payment_status = $is_incomplete ? 'Incomplete' : 'To Verify';

        // Get customer name row
        $cust_row = db_query("SELECT first_name, last_name FROM customers WHERE customer_id = ?", 'i', [$customer_id]);
        $customer_display_name = !empty($cust_row) ? trim($cust_row[0]['first_name'] . ' ' . $cust_row[0]['last_name']) : "Customer";

        db_execute(
            "INSERT INTO payments (order_id, customer_name, sender_name, payment_method, amount, proof_image, reference_id, source, payment_status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Online', ?)",
            'isssdsss',
            [$target_order_id, $customer_display_name, $sender_name, $payment_method, $ocr_amount, $file_path, $ref_id, $payment_status]
        );
        error_log("[DEBUG] Inserted into payments: payment_method = $payment_method for order_id = $target_order_id");

        // Notify staff
        $service_name = "Order";
        if (!$is_job) {
            $first_item = db_query("SELECT customization_data FROM order_items WHERE order_id = ? LIMIT 1", 'i', [$order_id]);
            $custom_data = (!empty($first_item) && !empty($first_item[0]['customization_data'])) ? json_decode($first_item[0]['customization_data'], true) : [];
            $service_name = get_service_name_from_customization($custom_data, 'Product Order');
        } else {
            $service_name = normalize_service_name($order['service_type'] ?? 'Custom Job');
        }

        // Determine if it was a resubmission
        $is_resubmission = false;
        if (!$is_job) {
            if (in_array($order['status'] ?? '', ['To Pay', 'For Revision']) && !empty($order['payment_submitted_at'])) {
                $is_resubmission = true;
            }
        } else {
            if (($order['payment_proof_status'] ?? '') === 'REJECTED') {
                $is_resubmission = true;
            }
        }

        $action_verb = $is_resubmission ? "resubmitted" : "submitted";
        $staff_msg = "{$customer_display_name} {$action_verb} payment for {$service_name}";
        
        $staff_users = db_query("SELECT user_id FROM users WHERE role IN ('Staff', 'Admin', 'Manager') AND status = 'Activated'");
        foreach ($staff_users as $staff) {
            create_notification($staff['user_id'], 'Staff', $staff_msg, 'Order', false, false, $order_id);
        }
        
        log_activity($customer_id, 'Payment Submitted', "Submitted proof for {$type_label} #{$order_id}");

        // Clear cart
        if (!empty($_SESSION['last_order_item_key'])) {
            $item_keys = explode(',', $_SESSION['last_order_item_key']);
            foreach ($item_keys as $key) {
                $key = trim($key);
                if (isset($_SESSION['cart'][$key])) unset($_SESSION['cart'][$key]);
            }
            unset($_SESSION['last_order_item_key']);
            sync_cart_to_db($customer_id);
        }

        // Return clean JSON
        ob_end_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Payment submitted successfully. Waiting for staff verification.',
            'file_path' => $file_path
        ]);
        exit;
    } else {
        throw new Exception("Database update failed.");
    }

} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}
