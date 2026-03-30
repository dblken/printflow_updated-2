<?php
require 'includes/db.php';
$r = db_query('SELECT service_id, name, status FROM services');
$out = "";
foreach ($r as $s) {
    $out .= $s['service_id'] . ' - ' . $s['name'] . ' (' . $s['status'] . ")\n";
}
file_put_contents('services_list.txt', $out);
