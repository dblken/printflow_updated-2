<?php
require_once __DIR__ . '/includes/db.php';
$cols = db_query("SHOW COLUMNS FROM products");
print_r($cols);
