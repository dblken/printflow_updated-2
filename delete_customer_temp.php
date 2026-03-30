<?php
require_once __DIR__ . '/includes/db.php';

$email = 'kentlloydvillanueva@gmail.com';

try {
    $result = db_execute("DELETE FROM customers WHERE email = ?", 's', [$email]);
    echo "Customer with email '{$email}' has been deleted successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
