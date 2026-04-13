<?php
require_once 'c:/xampp/htdocs/printflow/includes/db.php';
$id = 3;
echo "Inspecting Product: $id\n";
$product = db_query("SELECT * FROM products WHERE product_id = ?", 'i', [$id]);
print_r($product);

echo "\nAll Services:\n";
$svcs = db_query("SELECT service_id, name FROM services");
print_r($svcs);
