<?php
require_once __DIR__ . '/includes/functions.php';

$sql = "CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    proof_image VARCHAR(255) NOT NULL,
    reference_id VARCHAR(100) NULL,
    payment_status ENUM('To Verify', 'Verified', 'Rejected') DEFAULT 'To Verify',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (order_id),
    INDEX (reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (db_execute($sql)) {
    echo "Table 'payments' created successfully.";
} else {
    echo "Error creating table 'payments'.";
}
