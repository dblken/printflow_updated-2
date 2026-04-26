<?php
/**
 * Protected Payment Proof Viewer
 * Serves files from outside direct web access
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Must be logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

// 1. Properly decode and sanitize parameters
$file_param = (string)($_GET['file'] ?? '');
$order_item_id = (int)($_GET['order_item_id'] ?? 0);

if ($order_item_id > 0) {
    // Handle database-stored design images (BLOBs)
    $item = db_query(
        "SELECT design_image, design_image_mime FROM order_items WHERE order_item_id = ?",
        'i', [$order_item_id]
    );
    
    if (empty($item) || empty($item[0]['design_image'])) {
        http_response_code(404);
        die('Image not found');
    }
    
    $mime = $item[0]['design_image_mime'] ?: 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($item[0]['design_image']));
    echo $item[0]['design_image'];
    exit;
}

$file = rawurldecode($file_param);

// 2. Remove incorrect prefixes like "/printflow/" or "printflow/"
// This handles cases where the path was stored with or without the root folder
$clean_path = str_replace('\\', '/', $file);
$clean_path = preg_replace('/^\/?printflow\//i', '', $clean_path);
$clean_path = ltrim($clean_path, '/');

// Security: Prevent directory traversal
if (strpos($clean_path, '..') !== false) {
    http_response_code(403);
    die('Forbidden: Invalid path');
}

// 3. Resolve candidate locations safely.
// We check multiple possible locations to ensure backward compatibility.
$candidates = [
    __DIR__ . '/' . $clean_path, // If $clean_path already includes "uploads/payments/..."
    __DIR__ . '/uploads/payments/' . basename($clean_path),
    __DIR__ . '/uploads/secure_payments/' . basename($clean_path),
];

$filepath = '';
foreach ($candidates as $candidate) {
    if (file_exists($candidate) && is_file($candidate)) {
        $filepath = $candidate;
        break;
    }
}

// Debug Support (Internal logging if needed)
// error_log("[DEBUG] api_view_proof: Param=$file_param | Clean=$clean_path | Resolved=$filepath");

// Validate file existence
if ($filepath === '') {
    http_response_code(404);
    die('File not found');
}

// Security: Final check to ensure we are still inside the project uploads/ directory
$real_filepath = realpath($filepath);
$real_uploads = realpath(__DIR__ . '/uploads');
if (!$real_filepath || !$real_uploads || strpos($real_filepath, $real_uploads) !== 0) {
    http_response_code(403);
    die('Forbidden: Access denied');
}

// 4. Check authorization
$is_staff = isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['Admin', 'Staff', 'Manager'], true);
$is_owner = false;

if (!$is_staff && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Customer') {
    $customer_id = (int)$_SESSION['user_id'];
    $basename = basename($real_filepath);
    
    // Check both orders and job_orders tables for ownership
    $check = db_query(
        "SELECT id FROM job_orders WHERE customer_id = ? AND (payment_proof_path LIKE ? OR payment_proof_path = ?) LIMIT 1",
        'iss', [$customer_id, "%$basename%", $clean_path]
    );
    if (!empty($check)) $is_owner = true;
    
    if (!$is_owner) {
        $check_o = db_query(
            "SELECT order_id FROM orders WHERE customer_id = ? AND (payment_proof LIKE ? OR payment_proof = ?) LIMIT 1",
            'iss', [$customer_id, "%$basename%", $clean_path]
        );
        if (!empty($check_o)) $is_owner = true;
    }
    
    if (!$is_owner) {
        // Also check payments table
        $check_p = db_query(
            "SELECT id FROM payments p 
             INNER JOIN orders o ON p.order_id = o.order_id
             WHERE o.customer_id = ? AND (p.proof_image LIKE ? OR p.proof_image = ?) LIMIT 1",
            'iss', [$customer_id, "%$basename%", $clean_path]
        );
        if (!empty($check_p)) $is_owner = true;
    }
}

if (!$is_staff && !$is_owner) {
    http_response_code(403);
    die('Forbidden');
}

// 5. Serve the file with correct Content-Type and Cache-Control
$mime = mime_content_type($real_filepath);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real_filepath));

// Security/Privacy: Force cache disable for dynamic authorization checks
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

readfile($real_filepath);
exit;
