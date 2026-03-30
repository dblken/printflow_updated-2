<?php
require 'includes/functions.php';

$id = 279;
$email = 'kentlloydvillanueva@gmail.com';

echo "Deleting customer with ID $id and email $email...\n";

// First, ensure they exist with that email
$check = db_query("SELECT email FROM customers WHERE customer_id = ?", "i", [$id]);
if (empty($check) || $check[0]['email'] !== $email) {
    die("Error: Customer mismatch or not found.\n");
}

// Clean up any other potential tables (just in case they were added recently or missed)
db_execute("DELETE FROM customer_cart WHERE customer_id = ?", "i", [$id]);
db_execute("DELETE FROM notifications WHERE customer_id = ?", "i", [$id]);

// Delete the customer
$res = db_execute("DELETE FROM customers WHERE customer_id = ?", "i", [$id]);

if ($res) {
    echo "SUCCESS: Customer $email (ID $id) has been deleted.\n";
} else {
    echo "FAILURE: Failed to delete customer $email.\n";
}
?>
