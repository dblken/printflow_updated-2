<?php
/**
 * API: Verify Job Order Payment Proofs
 * Handles the staff/admin verification logic for uploaded payment proofs.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Staff, Manager, Admin (same as customizations page)
if (!in_array($_SESSION['user_type'] ?? '', ['Admin', 'Staff', 'Manager'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$job_id = (int)($_POST['id'] ?? 0);

if (!$action || !$job_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters.']);
    exit;
}

// Fetch the job
$job = db_query("SELECT * FROM job_orders WHERE id = ?", 'i', [$job_id]);
if (empty($job)) {
    echo json_encode(['success' => false, 'error' => 'Job order not found.']);
    exit;
}

$job = $job[0];
$user_id = $_SESSION['user_id'];
$user_first = $_SESSION['user_first_name'] ?? '';
$user_last = $_SESSION['user_last_name'] ?? '';
$user_name = trim($user_first . ' ' . $user_last);
if ($user_name === '') $user_name = 'Staff';

// Normalize workflow status so variants like "To Verify" / "to-verify"
// are treated the same as enum-style keys.
$normalize_workflow_status = static function ($s): string {
    $s = strtoupper((string)$s);
    $s = str_replace(['–', '-'], '_', $s);
    $s = preg_replace('/\s+/', '_', $s);
    return trim((string)$s, '_');
};

// Verify Action
if ($action === 'verify_payment') {
    
    $payment_proof_status = strtoupper((string)($job['payment_proof_status'] ?? ''));
    $job_status = $normalize_workflow_status($job['status'] ?? '');
    $submitted_amount = (float)($job['payment_submitted_amount'] ?? 0);
    $has_proof_path = !empty($job['payment_proof_path']) || !empty($job['payment_proof']);

    // Idempotency / readiness check:
    // Normally we only verify when payment_proof_status is exactly SUBMITTED.
    // But some rows can be inconsistent (e.g., status=VERIFY_PAY with mismatched proof_status),
    // so if we clearly have proof + amount, treat it as SUBMITTED for this verification.
    if ($payment_proof_status !== 'SUBMITTED') {
        $can_treat_as_submitted =
            in_array($job_status, ['TO_PAY', 'VERIFY_PAY', 'TO_VERIFY', 'PENDING_VERIFICATION', 'DOWNPAYMENT_SUBMITTED'], true) &&
            $submitted_amount > 0 &&
            $has_proof_path;

        if (!$can_treat_as_submitted) {
            $msg = "Payment proof is not currently in SUBMITTED state (Current: {$payment_proof_status}, Job: {$job_status}, Amount: {$submitted_amount}, Proof: " . ($has_proof_path ? 'Yes' : 'No') . ").";
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }

        // Normalize proof_status so downstream logic and UI remain consistent.
        db_execute("UPDATE job_orders SET payment_proof_status = 'SUBMITTED' WHERE id = ?", 'i', [$job_id]);
        $payment_proof_status = 'SUBMITTED';
    }
    
    if ($submitted_amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot verify: Valid submitted amount not found.']);
        exit;
    }
    
    $current_paid = (float)$job['amount_paid'];
    $estimated_total = (float)$job['estimated_total'];
    $required_payment = (float)$job['required_payment'];
    
    // Calculate new amounts
    $new_amount_paid = $current_paid + $submitted_amount;
    
    // Determine new payment status based on money
    $new_payment_status = 'UNPAID';
    if ($new_amount_paid > 0 && $new_amount_paid < $estimated_total) {
        $new_payment_status = 'PARTIAL';
    } elseif ($new_amount_paid >= $estimated_total) {
        $new_payment_status = 'PAID';
        // Cap amount_paid to estimated_total just for safety, though it shouldn't exceed
        if ($new_amount_paid > $estimated_total) {
            $new_amount_paid = $estimated_total;
        }
    }
    
    // Move to production when required payment is met (TO_PAY or VERIFY_PAY after customer proof)
    $new_order_status = (string)$job['status'];
    $new_order_status_norm = $normalize_workflow_status($new_order_status);
    if (in_array($new_order_status_norm, ['TO_PAY', 'VERIFY_PAY', 'TO_VERIFY', 'PENDING_VERIFICATION', 'DOWNPAYMENT_SUBMITTED'], true) && $new_amount_paid >= $required_payment) {
        $new_order_status = 'IN_PRODUCTION';
    }
    
    // Execute update transaction
    try {
        db_execute("UPDATE job_orders SET 
                    amount_paid = ?, 
                    payment_status = ?, 
                    payment_proof_status = 'VERIFIED',
                    payment_verified_at = NOW(),
                    payment_verified_by = ?,
                    status = ?
                    WHERE id = ?", 
        'dsisi', [$new_amount_paid, $new_payment_status, $user_id, $new_order_status, $job_id]);

        // If linked to a store order, sync the store order status
        if ($job['order_id']) {
            $store_status = 'Paid – In Process';
            if ($new_order_status === 'IN_PRODUCTION') $store_status = 'Processing';
            if ($new_order_status === 'TO_RECEIVE') $store_status = 'Ready for Pickup';
            if ($new_order_status === 'COMPLETED') $store_status = 'Completed';
            
            db_execute("UPDATE orders SET status = ?, amount_paid = ?, payment_status = ? WHERE order_id = ?", 
                'sdsi', [$store_status, $new_amount_paid, ($new_payment_status === 'PAID' ? 'Paid' : 'Partial'), $job['order_id']]);
        }
        
        // Log activity (user_id must be a valid staff users.user_id)
        if ($user_id > 0) {
            log_activity($user_id, 'Job payment verified', "Job #{$job_id}: verified ₱{$submitted_amount} ({$user_name})");
        }
        if (!empty($job['customer_id'])) {
            create_notification((int)$job['customer_id'], 'Customer', "Your payment proof for Custom Job #{$job_id} was verified. (₱{$submitted_amount})", 'Job Order', true, true);
        }

        if ($new_order_status !== $job['status'] && $user_id > 0) {
            log_activity($user_id, 'Job status update', "Job #{$job_id} moved to {$new_order_status} after payment verification.");
        }
        if ($new_order_status !== $job['status'] && !empty($job['customer_id'])) {
            create_notification((int)$job['customer_id'], 'Customer', "Custom Job #{$job_id} is now in production!", 'Job Order', true, true);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error during verification: ' . $e->getMessage()]);
    }

} 
// Reject Action
elseif ($action === 'reject_payment') {
    
    $reason = sanitize($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        echo json_encode(['success' => false, 'error' => 'Rejection reason is required.']);
        exit;
    }
    
    // Idempotency check
    $payment_proof_status = strtoupper((string)($job['payment_proof_status'] ?? ''));
    if ($payment_proof_status !== 'SUBMITTED' && !in_array($job_status, ['TO_PAY', 'VERIFY_PAY'], true)) {
        echo json_encode(['success' => false, 'error' => 'Payment proof is not currently in SUBMITTED state for rejection.']);
        exit;
    }
    
    try {
        db_execute("UPDATE job_orders SET 
                    payment_proof_status = 'REJECTED',
                    payment_rejection_reason = ?,
                    payment_verified_at = NOW(),
                    payment_verified_by = ?
                    WHERE id = ?", 
        'sii', [$reason, $user_id, $job_id]);

        // If linked to a store order, revert to 'To Pay' so they can submit again
        if ($job['order_id']) {
            db_execute("UPDATE orders SET status = 'To Pay' WHERE order_id = ?", 'i', [$job['order_id']]);
        }
        
        if ($user_id > 0) {
            log_activity($user_id, 'Job payment rejected', "Job #{$job_id}: rejected by {$user_name}. {$reason}");
        }
        if (!empty($job['customer_id'])) {
            $data_id = (int)($job['order_id'] ?: $job_id);
            create_notification((int)$job['customer_id'], 'Customer', "Your payment proof for Custom Job #{$job_id} was rejected. Please review and re-upload.", 'Payment Issue', true, true, $data_id);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error during rejection: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
}
