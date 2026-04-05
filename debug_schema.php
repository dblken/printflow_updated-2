<?php
require_once 'includes/functions.php';
$res = db_query('DESCRIBE service_field_configs');
if ($res) {
    foreach ($res as $row) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Query failed\n";
}
