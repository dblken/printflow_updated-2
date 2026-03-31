<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SHOW COLUMNS FROM reviews");
$cols = array_column($res, 'Field');
echo implode(',', $cols);
