<?php
require 'includes/functions.php';
$sqls = [
    "ALTER TABLE users ADD COLUMN last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    "ALTER TABLE orders ADD COLUMN is_archived_customer TINYINT(1) DEFAULT 0",
    "ALTER TABLE orders ADD COLUMN is_archived_staff TINYINT(1) DEFAULT 0"
];
foreach($sqls as $sql) {
    try {
        db_execute($sql);
        echo "Success: $sql\n";
    } catch(Exception $e) {
        echo "Error: $sql -> " . $e->getMessage() . "\n";
    }
}
?>
