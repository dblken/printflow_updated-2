<?php
require 'includes/functions.php';
$res = db_query("DESCRIBE users");
foreach($res ?: [] as $r) echo $r['Field'] . "\n";
?>
