<?php
require_once __DIR__ . '/includes/functions.php';

$rows = db_query("SHOW COLUMNS FROM user_status", "", []);
foreach ($rows as $row) {
    echo $row['Field'] . "\n";
}
?>
