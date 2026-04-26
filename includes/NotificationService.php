<?php
/**
 * NotificationService
 * Sends notifications to customers on job order status changes.
 * PrintFlow v2
 */

require_once __DIR__ . '/functions.php';

class NotificationService {

    /** Values allowed by `notifications.type` ENUM (avoid "Data truncated for column 'type'"). */
    private static function normalizeNotificationType(string $type): string {
        $allowed = ['Order', 'Stock', 'System', 'Message', 'Job Order', 'Payment Issue', 'Design', 'Payment', 'Status', 'Rating', 'Review'];
        return in_array($type, $allowed, true) ? $type : 'System';
    }

    /**
     * Status → human-readable notification messages.
     */
    private static $statusMessages = [
        'APPROVED'     => 'Your inquiry has been approved. We are now preparing the final price for your order. Please get ready for payment.',
        'TO_PAY'       => 'Your order for {product_name} is now ready for payment. The final price is ₱{amount}. Please proceed with your payment.',
        'VERIFY_PAY'   => 'Your payment has been received and is under verification.',
        'IN_PRODUCTION'=> 'Your payment has been verified. Your order is now in production.',
        'TO_RECEIVE'   => 'Good news! Your order is now ready for pickup.',
        'COMPLETED'    => 'Your order has been completed. Thank you for your purchase!',
        'CANCELLED'    => 'Your order has been cancelled. Please contact us for assistance.',
        'FOR REVISION' => 'Revision requested for your order. Reason: {reason}',
    ];

    /**
     * Send a notification to a customer about a job order status change.
     */
    public static function sendJobOrderNotification(int $customerId, int $jobOrderId, string $newStatus, ?string $overrideMessage = null, string $reason = ''): bool {
        if (!$customerId) return false;

        $newStatus = strtoupper($newStatus);
        $message = $overrideMessage ?? (self::$statusMessages[$newStatus] ?? null);
        if (!$message) return false;

        // Fetch order details for dynamic placeholders and linking
        $order = db_query("
            SELECT jo.id, jo.order_id, jo.estimated_total, jo.job_title, jo.service_type,
                   o.total_amount AS parent_total
            FROM job_orders jo
            LEFT JOIN orders o ON jo.order_id = o.order_id
            WHERE jo.id = ?
        ", 'i', [$jobOrderId]);

        if (!empty($order)) {
            $o = $order[0];
            
            // Prioritize staff-set parent total, fallback to job's estimated total
            $raw_amount = ($o['parent_total'] > 0) ? $o['parent_total'] : ($o['estimated_total'] ?? 0);
            $amount = number_format((float)$raw_amount, 2);
            
            // Resolve a readable product name for the notification
            $product_name = !empty($o['job_title']) ? $o['job_title'] : ($o['service_type'] ?? 'your order');

            $orderNo = "#JO-" . str_pad((int)$o['id'], 5, '0', STR_PAD_LEFT);
            
            $rev_reason = 'No reason provided';
            if (!empty($o['order_id'])) {
                $st = db_query("SELECT revision_reason FROM orders WHERE order_id = ?", 'i', [$o['order_id']]);
                if (!empty($st)) $rev_reason = $st[0]['revision_reason'] ?: 'No reason provided';
            }
            if (!empty($reason)) $rev_reason = $reason;

            // Use standard order ID for linking if it exists, otherwise use job order ID
            $linkId = $o['order_id'] ?: $o['id'];
            $type = $o['order_id'] ? 'Order' : 'Job Order';

            if (!empty($o['order_id'])) {
                $orderNo .= " (#ORD-" . str_pad((int)$o['order_id'], 5, '0', STR_PAD_LEFT) . ")";
            }

            $message = str_replace(
                ['{product_name}', '{amount}', '{order_no}', '{reason}'], 
                [$product_name, $amount, $orderNo, $rev_reason], 
                $message
            );
        } else {
            $message = str_replace(['{product_name}', '{amount}', '{order_no}', '{reason}'], ['your order', '0.00', '', 'No reason provided'], $message);
            $linkId = $jobOrderId;
            $type = 'Job Order';
        }

        // Also send enhanced chat message if it's a standard order
        if (!empty($o['order_id'])) {
            send_order_update_message((int)$o['order_id'], $message);
        }

        return (bool) create_notification(
            $customerId,
            'Customer',
            $message,
            $type,
            false,
            false,
            $linkId
        );
    }

    /**
     * Send a generic custom notification to a customer.
     */
    public static function send(int $customerId, string $type, string $message, int $dataId = 0): bool {
        if (!$customerId) return false;

        $type = self::normalizeNotificationType($type);

        $result = db_execute(
            "INSERT INTO notifications (customer_id, type, message, data_id, is_read, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())",
            'isis',
            [$customerId, $type, $message, $dataId]
        );

        return (bool) $result;
    }
}
