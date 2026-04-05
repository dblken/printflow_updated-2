<?php
require_once 'includes/functions.php';
$service_id = 26;
$res = db_query('SELECT field_key FROM service_field_configs WHERE service_id = ?', 'i', [$service_id]);
if ($res) {
    foreach ($res as $row) {
        echo "Key: [" . $row['field_key'] . "]\n";
    }
} else {
    echo "No fields found or query failed\n";
}
