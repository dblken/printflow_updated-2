<?php
require_once 'includes/db.php';
$res = db_query("
    SELECT oi.order_item_id, oi.product_id, oi.sku, jo.service_type, p.name as p_name, s.name as s_name, jo.job_title
    FROM order_items oi
    LEFT JOIN job_orders jo ON oi.order_item_id = jo.order_item_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN services s ON (oi.product_id = s.service_id OR jo.service_type = s.name)
    WHERE p.name IS NULL AND s.name IS NULL AND jo.service_type IS NULL
    LIMIT 10
");
print_r($res);
