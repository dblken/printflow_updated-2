<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SHOW COLUMNS FROM branches");
print_r($res);
