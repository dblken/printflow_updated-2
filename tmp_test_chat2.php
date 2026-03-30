<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// mock auth
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Customer';
$_SESSION['role_name'] = 'Customer';

$_GET['order_id'] = 2262;
$_GET['last_id'] = 0;
$_GET['is_active'] = 1;

require __DIR__ . '/public/api/chat/fetch_messages.php';
