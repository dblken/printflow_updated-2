<?php
/**
 * reviews.php
 * Redirects to the specific product/service page where the review is displayed
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$order_id = (int)($_GET['order_id'] ?? 0);
$customer_id = get_user_id();

if ($order_id > 0) {
    $review = db_query("SELECT id, review_type, reference_id, service_type FROM reviews WHERE order_id = ? AND user_id = ?", 'ii', [$order_id, $customer_id]);
    
    if (!empty($review)) {
        $r = $review[0];
        $ref_id = (int)$r['reference_id'];
        
        // If reference_id is missing for a custom review, try to find the service by name
        if ($r['review_type'] === 'custom' && $ref_id <= 0 && !empty($r['service_type'])) {
            $svc = db_query("SELECT service_id FROM services WHERE name = ? LIMIT 1", 's', [$r['service_type']]);
            if (!empty($svc)) {
                $ref_id = (int)$svc[0]['service_id'];
            }
        }
        
        if ($r['review_type'] === 'custom' && $ref_id > 0) {
            header("Location: /printflow/customer/order_service_dynamic.php?service_id=" . $ref_id . "#review-" . $r['id']);
            exit;
        } elseif ($ref_id > 0) {
            header("Location: /printflow/customer/order_create.php?product_id=" . $ref_id . "#review-" . $r['id']);
            exit;
        }
    }
}

// Fallback to orders page if review not found or invalid
header('Location: /printflow/customer/orders.php?tab=completed');
exit;
