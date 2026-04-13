<?php
/**
 * API: Customer Notifications Dropdown
 * Reuses the same notification payload as customer/notifications.php.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_customer()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$customer_id = get_user_id();
$limit = 5;

echo json_encode([
    'success' => true,
    'notifications' => get_customer_notifications_for_display($customer_id, $limit),
    'unread_count' => get_unread_notification_count($customer_id, 'Customer'),
]);
