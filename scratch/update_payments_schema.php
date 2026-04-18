<?php
require_once 'includes/functions.php';

// Check if customer_name column exists
$cols = db_query("SHOW COLUMNS FROM payments LIKE 'customer_name'");
if (empty($cols)) {
    echo "Adding customer_name column to payments table..." . PHP_EOL;
    $res = db_execute("ALTER TABLE payments ADD COLUMN customer_name VARCHAR(255) NULL AFTER order_id");
    if ($res) {
        echo "Column 'customer_name' added successfully." . PHP_EOL;
    } else {
        echo "Error adding column 'customer_name'." . PHP_EOL;
    }
} else {
    echo "Column 'customer_name' already exists." . PHP_EOL;
}
