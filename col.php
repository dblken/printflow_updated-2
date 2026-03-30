<?php
require 'includes/db.php';
$cols = db_query('SHOW COLUMNS FROM orders');
foreach($cols as $c) echo $c['Field']. " (".$c['Type'].")\n";
