<?php
require 'includes/db.php';
require 'includes/functions.php';
$_GET['order_id'] = 2543;
ob_start();
include 'public/api/chat/order_details.php';
$json = ob_get_clean();
print_r(json_decode($json, true)['items']);
