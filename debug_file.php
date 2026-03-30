<?php
require 'includes/db.php';
$r = db_query('SELECT design_file FROM order_items WHERE order_id = 2263');
file_put_contents('debug.txt', $r[0]['design_file'] ?? 'NULL');
