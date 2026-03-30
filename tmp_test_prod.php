<?php
require_once 'includes/db.php';
$res = db_query("SELECT product_id, name FROM products");
print_r($res);
?>
