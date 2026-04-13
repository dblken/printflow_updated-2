<?php
require_once __DIR__ . '/includes/db.php';
global $conn;

echo "Correcting order_messages column types...\n";

// Change ENUMs to VARCHAR for better flexibility with new message types (voice, etc)
$queries = [
    "ALTER TABLE order_messages MODIFY COLUMN message_type VARCHAR(20) DEFAULT 'text'",
    "ALTER TABLE order_messages MODIFY COLUMN file_type VARCHAR(20) DEFAULT 'none'",
    "UPDATE order_messages SET message_type = 'voice' WHERE message_file LIKE '%.webm' OR message_file LIKE '%.wav'"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Executed: " . substr($q, 0, 60) . "...\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

echo "Schema Correction Complete.\n";
