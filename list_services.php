<?php
require 'includes/db.php';
$r = db_query('SELECT service_id, name, status FROM services');
foreach ($r as $s) {
    echo $s['service_id'] . ' - ' . $s['name'] . ' (' . $s['status'] . ")\n";
}
