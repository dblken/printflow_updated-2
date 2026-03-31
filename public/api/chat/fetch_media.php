<?php
/**
 * Fetch all shared media for a specific conversation.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Global Output Buffer to trap notices
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode([]);
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    ob_end_clean();
    echo json_encode([]);
    exit();
}

// Security: Check if customer belongs to the order
if ($user_type === 'Customer') {
    $check_sql = "SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ?";
    $check = db_query($check_sql, 'ii', [$order_id, $user_id]);
    if (empty($check)) {
        ob_end_clean();
        echo json_encode([]);
        exit();
    }
}

// Fetch all media using proper columns
$sql = "SELECT message_file, file_type 
        FROM order_messages 
        WHERE order_id = ? 
        AND file_type NOT IN ('none', 'text')
        AND message_type != 'voice'
        AND message_file IS NOT NULL
        ORDER BY created_at DESC";
$media = db_query($sql, 'i', [$order_id]);

if ($media === false) {
    ob_end_clean();
    echo json_encode([]);
    exit();
}

// Prepend BASE_URL if needed
$results = [];
foreach ($media as $item) {
    $path = (string)$item['message_file'];
    if ($path !== '' && !preg_match('#^https?://#i', $path)) {
        if (strpos($path, '/printflow/') !== 0) $path = '/printflow/' . ltrim($path, '/');
    }
    $results[] = [
        'message_file' => $path,
        'file_type' => $item['file_type']
    ];
}

ob_end_clean();
echo json_encode($results);
