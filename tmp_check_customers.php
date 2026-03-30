<?php
require 'includes/functions.php';
$res = db_query("DESCRIBE customers");
foreach($res ?: [] as $r) echo $r['Field'] . "\n";
?>
