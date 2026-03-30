<?php
require 'includes/functions.php';
$res = db_query("DESCRIBE orders");
foreach($res ?: [] as $r) echo $r['Field'] . "\n";
?>
