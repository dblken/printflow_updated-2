<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SHOW COLUMNS FROM ratings");
print_r($res);
