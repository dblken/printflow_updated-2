<?php
require_once 'includes/db.php';
$columns = db_query("SHOW COLUMNS FROM orders");
file_put_contents('c:/xampp/htdocs/printflow/orders_schema.txt', print_r($columns, true));
echo "Schema dumped to orders_schema.txt\n";
