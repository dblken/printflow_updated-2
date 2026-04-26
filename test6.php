<?php
require 'includes/db.php';
require 'includes/functions.php';

function is_logged_in() { return true; }
function get_user_id() { return 1; }
function get_user_type() { return 'Staff'; }

$_GET['order_id'] = 2543;
ob_start();
include 'public/api/chat/order_details.php';
$json = ob_get_clean();
$data = json_decode($json, true);
print_r($data['customer']);
