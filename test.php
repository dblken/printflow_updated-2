<?php
require 'includes/db.php';
$res = db_query("SELECT o.order_id, oi.product_id, oi.customization_data, p.name FROM orders o JOIN order_items oi ON o.order_id = oi.order_id LEFT JOIN products p ON oi.product_id = p.product_id ORDER BY o.order_id DESC LIMIT 10");
echo json_encode($res, JSON_PRETTY_PRINT);
