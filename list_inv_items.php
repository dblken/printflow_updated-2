<?php
require 'includes/db.php';
$r = db_query('SELECT id, category_id, name FROM inv_items WHERE status = "ACTIVE"');
foreach ($r as $i) {
    echo $i['category_id'] . ' | ' . $i['id'] . ' | ' . $i['name'] . "\n";
}
