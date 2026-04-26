<?php
require 'includes/auth.php';
require 'includes/db.php';
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'Staff';
$_GET['type'] = 'order_item';
$_GET['id'] = 2589;
ob_start();
include 'public/serve_design.php';
$out = ob_get_clean();
echo "Output length: " . strlen($out) . "\n";
if (strlen($out) < 100) {
    echo "Output: " . $out;
}
