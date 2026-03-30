<?php
require_once 'includes/db.php';
$check = db_query("SELECT 1 FROM products WHERE product_id = 21");
if (empty($check)) {
    db_execute("INSERT INTO products (product_id, name, description, category, price, stock_quantity) VALUES (21, 'Custom / Other Service', 'Miscellaneous custom POS items', 'Other', 0.00, NULL)");
    echo "Product 21 inserted!";
} else {
    echo "Product 21 already exists.";
}
?>
