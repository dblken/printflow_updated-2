<?php
require 'includes/db.php';
$cols = db_query("SHOW COLUMNS FROM orders");
foreach ($cols as $col) {
    echo $col['Field'] . "\n";
}
