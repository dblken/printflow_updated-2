<?php
require_once 'includes/db.php';
$timeframe_sql = "YEAR(o.order_date) = YEAR(CURDATE()) AND MONTH(o.order_date) = MONTH(CURDATE())";
$res = db_query("
    SELECT oi.order_item_id, oi.product_id, p.name as p_name, p.status as p_status, 
           s.name as s_name, s.status as s_status, jo.id as jo_id
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN job_orders jo ON oi.order_item_id = jo.order_item_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN services s ON (oi.product_id = s.service_id OR jo.service_type = s.name)
    WHERE $timeframe_sql
      AND o.branch_id = 1
      AND p.name = 'dfgfdg'
");
print_r($res);
