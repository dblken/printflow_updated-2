<?php
require_once 'c:/xampp/htdocs/printflow/includes/db.php';
$types = db_query("SELECT DISTINCT type FROM notifications");
print_r($types);
$sample = db_query("SELECT * FROM notifications WHERE customer_id IS NOT NULL ORDER BY created_at DESC LIMIT 5");
print_r($sample);

// Test subquery logic for images
foreach($sample as $n) {
    if (empty($n['data_id'])) continue;
    $id = $n['data_id'];
    echo "\nTesting data_id: $id (Type: {$n['type']})\n";
    $imgRows = db_query("
        SELECT 
            (SELECT SUBSTRING_INDEX(s.display_image, ',', 1) FROM job_orders jo JOIN services s ON jo.service_type = s.name WHERE jo.order_id = ? OR jo.id = ? LIMIT 1) AS service_image,
            (SELECT COALESCE(p.product_image, p.photo_path) FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ? LIMIT 1) AS product_image,
            (SELECT profile_picture FROM users WHERE user_id = ? LIMIT 1) AS sender_image
    ", 'iiii', [$id, $id, $id, $id]);
    print_r($imgRows);
}
