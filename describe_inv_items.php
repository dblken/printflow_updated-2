<?php
require 'includes/db.php';
$r = db_query('DESCRIBE inv_items');
foreach ($r as $f) {
    echo $f['Field'] . ' - ' . $f['Type'] . "\n";
}
