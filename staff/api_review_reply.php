<?php
/**
 * Staff API - Review Reply
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_xhr()) {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// Ensure tables exist before processing
ensure_ratings_table_exists();

if (!in_array($_SESSION['user_type'] ?? '', ['Staff', 'Admin'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid data input.']);
    exit;
}

if (!verify_csrf_token($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$review_id = (int)($input['review_id'] ?? 0);
$message = trim((string)($input['message'] ?? ''));
$staff_id = get_user_id();

if ($review_id <= 0 || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

if (mb_strlen($message) > 1000) {
    echo json_encode(['success' => false, 'error' => 'Reply is too long (max 1000 chars).']);
    exit;
}

try {
    // Check if review exists
    $review_check = db_query("SELECT id, user_id, order_id FROM reviews WHERE id = ? LIMIT 1", 'i', [$review_id]);
    if (empty($review_check)) {
        echo json_encode(['success' => false, 'error' => 'Review not found.']);
        exit;
    }

    $customer_id = (int)$review_check[0]['user_id'];
    $order_id = (int)$review_check[0]['order_id'];

    db_execute("
        INSERT INTO review_replies (review_id, staff_id, reply_message, created_at)
        VALUES (?, ?, ?, NOW())
    ", 'iis', [$review_id, $staff_id, $message]);

    // Notify customer
    $notif_msg = "PrintFlow Staff replied to your review for Order #ORD-" . str_pad((string)$order_id, 5, '0', STR_PAD_LEFT);
    create_notification($customer_id, 'Customer', $notif_msg, 'Rating', false, false, $order_id);

    echo json_encode(['success' => true, 'message' => 'Reply posted successfully.']);
} catch (Throwable $e) {
    error_log("Error in api_review_reply: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}
