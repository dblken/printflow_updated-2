<?php
require 'includes/functions.php';
$tables = ['orders', 'order_messages', 'users'];
foreach($tables as $t) {
    echo "--- $t ---\n";
    $res = db_query("DESCRIBE $t");
    foreach($res ?: [] as $r) echo $r['Field'].' ('.$r['Type'].')\n';
}
