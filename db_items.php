<?php
require 'C:/xampp/htdocs/printflow/includes/db.php';
$cols = db_query("SHOW COLUMNS FROM order_items");
foreach ($cols as $col) {
    echo $col['Field'] . "\n";
}
