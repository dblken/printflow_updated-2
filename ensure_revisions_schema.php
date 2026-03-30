<?php
require_once 'includes/db.php';

// 1. Ensure order_item_revisions table exists
$checkTable = db_query("SHOW TABLES LIKE 'order_item_revisions'");
if (empty($checkTable)) {
    echo "Creating order_item_revisions table...\n";
    $sql = "CREATE TABLE order_item_revisions (
        revision_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        order_item_id INT NOT NULL,
        staff_id INT DEFAULT NULL,
        revision_reason TEXT,
        design_image LONGBLOB,
        design_image_name VARCHAR(255),
        design_image_mime VARCHAR(100),
        design_file VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    db_execute($sql);
} else {
    echo "order_item_revisions table already exists.\n";
}

// 2. Ensure revision_count column exists in orders table
$checkCols = db_query("DESC orders");
$cols = array_column($checkCols, 'Field');
if (!in_array('revision_count', $cols)) {
    echo "Adding revision_count column to orders table...\n";
    db_execute("ALTER TABLE orders ADD COLUMN revision_count INT DEFAULT 0 AFTER design_status");
} else {
    echo "revision_count column already exists in orders table.\n";
}

echo "Database schema ensured.\n";
