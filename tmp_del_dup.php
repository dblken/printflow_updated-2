<?php
require_once __DIR__ . '/includes/functions.php';

// Deactivate or delete duplicate Sintraboard Standees
$sql1 = "DELETE FROM services WHERE name = 'Sintraboard/Standees' LIMIT 1";
db_execute($sql1, "", []);

echo "Deleted duplicate.";
?>
