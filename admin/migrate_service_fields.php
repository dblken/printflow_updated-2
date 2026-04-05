<?php
/**
 * Database Migration: Create service_field_configs table
 */

$host = 'localhost';
$user = 'root';
$pass = '1234';
$db = 'printflow_1';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS service_field_configs (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL COMMENT 'Unique identifier for the field (e.g., branch, dimensions, finish)',
    field_label VARCHAR(255) DEFAULT NULL COMMENT 'Custom label shown to customer',
    field_type ENUM('select', 'radio', 'text', 'number', 'file', 'date', 'textarea', 'dimension', 'quantity') NOT NULL,
    field_options JSON DEFAULT NULL COMMENT 'Array of options for select/radio fields',
    is_visible TINYINT(1) DEFAULT 1 COMMENT '1=show, 0=hide',
    is_required TINYINT(1) DEFAULT 1 COMMENT '1=required, 0=optional',
    default_value VARCHAR(255) DEFAULT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_service_field (service_id, field_key),
    KEY idx_service_id (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'service_field_configs' created successfully\n";
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
