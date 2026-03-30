<?php
require_once 'includes/db.php';
$res1 = db_query("SELECT user_id, role, first_name FROM users");
print_r($res1);
$res2 = db_query("SHOW CREATE TABLE orders");
print_r($res2);
?>
