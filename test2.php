<?php
require 'includes/db.php';
require 'includes/functions.php';
require 'includes/order_ui_helper.php';

$res = get_customer_notification_service_match(0, 'T-SHIRT PRINT');
print_r($res);

$res2 = get_customer_notification_service_match(0, 'Sticker Pack');
print_r($res2);

$res3 = get_customer_notification_service_match(0, 'Eunsoyaa');
print_r($res3);
