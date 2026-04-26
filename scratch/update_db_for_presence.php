<?php
include 'includes/db.php';

$queries = [
    "ALTER TABLE users ADD COLUMN online_status ENUM('online', 'offline') DEFAULT 'offline'",
    "ALTER TABLE users ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL",
    "ALTER TABLE customers ADD COLUMN online_status ENUM('online', 'offline') DEFAULT 'offline'",
    "ALTER TABLE customers ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL"
];

foreach ($queries as $query) {
    if ($conn->query($query)) {
        echo "Executed: $query\n";
    } else {
        echo "Error executing $query: " . $conn->error . "\n";
    }
}
?>
