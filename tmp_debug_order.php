<?php
require 'includes/db.php';
$res = db_query('SELECT order_item_id, design_image, design_file, customization_data FROM order_items WHERE order_id = 2269');
foreach($res as $r) {
    echo "ID: " . $r['order_item_id'] . "\n";
    echo "BLOB Size: " . strlen($r['design_image'] ?? '') . "\n";
    echo "Design File Column: " . ($r['design_file'] ?: 'NULL') . "\n";
    echo "Custom Data: " . $r['customization_data'] . "\n";
    echo "-------------------\n";
}
?>
