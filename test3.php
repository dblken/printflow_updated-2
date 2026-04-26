<?php
require 'includes/db.php';
$res = db_query("SELECT product_id, design_image, design_file, reference_image_file FROM order_items WHERE order_id = 2543");
print_r($res);

$res2 = db_query("SELECT product_image FROM products WHERE product_id = 3");
print_r($res2);
