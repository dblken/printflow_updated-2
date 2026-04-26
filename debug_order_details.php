<?php
$_GET['order_id'] = 2541;
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'Staff';
ob_start();
include 'public/api/chat/order_details.php';
$out = ob_get_clean();
echo $out;
