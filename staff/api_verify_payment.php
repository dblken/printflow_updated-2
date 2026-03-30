<?php
/**
 * API: Verify Payment Proof (Staff)
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

require_role(['Admin', 'Staff', 'Manager']);

$staffBranchId = null;
if (is_staff() || is_manager()) {
    $staffBranchId = printflow_branch_filter_for_user() ?? (int)($_SESSION['branch_id'] ?? 1);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'Approve' or 'Reject'

if (!$order_id || !in_array($action, ['Approve', 'Reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Get order details (staff: same branch only)
if ($staffBranchId !== null) {
    $order_result = db_query("SELECT * FROM orders WHERE order_id = ? AND branch_id = ?", 'ii', [$order_id, $staffBranchId]);
} else {
    $order_result = db_query("SELECT * FROM orders WHERE order_id = ?", 'i', [$order_id]);
}
if (empty($order_result)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
$order = $order_result[0];

$staff_id = get_user_id();
$new_status = '';
$payment_status = $order['payment_status'];
$success = false;
$error_message = '';

try {
    if ($action === 'Approve') {
        $new_status = 'Paid – In Process';
        $payment_status = 'Paid';
        
        // Update order
        if ($staffBranchId !== null) {
            $sql = "UPDATE orders SET status = ?, payment_status = ? WHERE order_id = ? AND branch_id = ?";
            $success = db_execute($sql, 'ssii', [$new_status, $payment_status, $order_id, $staffBranchId]);
        } else {
            $sql = "UPDATE orders SET status = ?, payment_status = ? WHERE order_id = ?";
            $success = db_execute($sql, 'ssi', [$new_status, $payment_status, $order_id]);
        }
        
        if ($success) {
            $msg = "Your payment has been verified. Your order is now in process!";
            if (!empty($order['customer_id'])) {
                create_notification((int)$order['customer_id'], 'Customer', $msg, 'Order', false, false, $order_id);
            }
            add_order_system_message($order_id, $msg);
            log_activity($staff_id, 'Payment Approved', "Approved payment for Order #{$order_id}");
            // Keep linked job_orders in sync (any active job — not only VERIFY_PAY / SUBMITTED)
            db_execute(
                "UPDATE job_orders SET payment_proof_status = 'VERIFIED', payment_status = 'PAID', status = 'IN_PRODUCTION'
                 WHERE order_id = ? AND status NOT IN ('COMPLETED', 'CANCELLED')",
                'i',
                [$order_id]
            );
        }
    } else {
        // Rejected - move back to To Pay or Pending
        $new_status = 'To Pay';
        $reason = $_POST['reason'] ?? 'Payment proof rejected by staff.';
        
        // Clear proof so they can re-upload
        if ($staffBranchId !== null) {
            $sql = "UPDATE orders SET status = ?, payment_proof = NULL WHERE order_id = ? AND branch_id = ?";
            $success = db_execute($sql, 'sii', [$new_status, $order_id, $staffBranchId]);
        } else {
            $sql = "UPDATE orders SET status = ?, payment_proof = NULL WHERE order_id = ?";
            $success = db_execute($sql, 'si', [$new_status, $order_id]);
        }
        
        if ($success) {
            $msg = "Your payment proof was rejected. Reason: " . $reason;
            if (!empty($order['customer_id'])) {
                create_notification((int)$order['customer_id'], 'Customer', $msg, 'Payment Issue', false, false, $order_id);
            }
            add_order_system_message($order_id, "[PAYMENT REJECTION] " . $reason);
            log_activity($staff_id, 'Payment Rejected', "Rejected payment for Order #{$order_id}. Reason: {$reason}");
            db_execute(
                "UPDATE job_orders SET payment_proof_status = 'REJECTED', status = 'TO_PAY',
                 payment_rejection_reason = ?,
                 payment_proof_path = NULL, payment_submitted_amount = 0, payment_proof_uploaded_at = NULL
                 WHERE order_id = ? AND status NOT IN ('COMPLETED','CANCELLED')",
                'si',
                [$reason, $order_id]
            );

            // Delete the file if it exists to save space (optional, but cleaner)
            $proof = $order['payment_proof'] ?? '';
            if ($proof !== '') {
                $rel = ltrim(str_replace('\\', '/', $proof), '/');
                if (strpos($rel, 'printflow/') === 0) {
                    $rel = substr($rel, strlen('printflow/'));
                }
                $abs = __DIR__ . '/../' . $rel;
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
        }
    }
} catch (Exception $e) {
    $success = false;
    $error_message = 'Database error: ' . $e->getMessage();
}

if ($success) {
    echo json_encode([
        'success' => true,
        'new_status' => $new_status,
        'payment_status' => $payment_status
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $error_message ?: 'Database update failed']);
}
