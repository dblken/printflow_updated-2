<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Debug script to check payments table content
$payments = db_query("SELECT * FROM payments ORDER BY id DESC LIMIT 10");
header('Content-Type: text/plain');
print_r($payments);
