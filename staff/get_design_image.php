<?php
/**
 * Serve design image from BLOB
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_role(['Admin', 'Staff', 'Customer']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("ID required");
}

$res = db_query("SELECT design_image FROM order_items WHERE order_item_id = ?", 'i', [$id]);
if (!$res || empty($res[0]['design_image'])) {
    // Return placeholder
    header('Content-Type: image/png');
    readfile(__DIR__ . '/../public/assets/uploads/profiles/default.png');
    exit;
}

$image = $res[0]['design_image'];
// Try to detect content type or default to png
header('Content-Type: image/png');
echo $image;
exit;
