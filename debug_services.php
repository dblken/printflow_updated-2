<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SELECT service_id, name FROM services");
echo "SERVICES:\n";
foreach($res ?: [] as $r) {
    echo "ID:{$r['service_id']}, NAME:{$r['name']}\n";
}
