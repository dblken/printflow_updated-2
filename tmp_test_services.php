<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/customer_service_catalog.php';

// mock auth
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Customer';
$_SESSION['role_name'] = 'Customer';

$core_services = printflow_default_customer_service_catalog();

require __DIR__ . '/customer/services.php';
