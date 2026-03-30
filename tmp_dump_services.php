<?php
require_once __DIR__ . '/includes/functions.php';

// Check if db_query is available
if (!function_exists('db_query')) {
    echo "db_query not found";
    exit;
}

$rows = db_query("SELECT id, name, category, status FROM services", "", []);
if ($rows === null) {
    echo "Query failed";
} else {
    echo json_encode($rows, JSON_PRETTY_PRINT);
}
?>
