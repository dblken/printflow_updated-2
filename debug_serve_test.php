<?php
require_once 'includes/db.php';
$_GET['type'] = 'order_item';
$_GET['id'] = 2589;

$htdocs_root = realpath(__DIR__ . '/../');
$item = db_query("SELECT design_image, design_image_mime, design_file, reference_image_file FROM order_items WHERE order_item_id = ?", 'i', [2589])[0] ?? null;

if ($item['design_file'] && file_exists($htdocs_root . $item['design_file'])) {
    $full_path = $htdocs_root . $item['design_file'];
    $mime = mime_content_type($full_path);
    echo "Found file. Mime: $mime\n";
    // echo readfile($full_path);
} else {
    echo "Not found!";
}
