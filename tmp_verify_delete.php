<?php
require 'includes/functions.php';
$email = 'kentlloydvillanueva@gmail.com';
$row = db_query("SELECT count(*) as count FROM customers WHERE email = ?", "s", [$email]);
echo "COUNT: " . ($row ? $row[0]['count'] : 'Error') . "\n";
?>
