<?php
require 'includes/db.php';
$orders = db_query("SELECT o.order_id, o.total_amount, oi.unit_price, oi.customization_data, oi.design_file, oi.design_image_name FROM orders o JOIN order_items oi ON o.order_id = oi.order_id WHERE o.total_amount = 100.00 ORDER BY o.order_id DESC LIMIT 10");
print_r($orders);
