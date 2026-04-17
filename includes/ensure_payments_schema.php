<?php
/**
 * Ensure Payments Table Schema
 */
function printflow_ensure_payments_schema() {
    global $conn;

    // Create table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        sender_name VARCHAR(255),
        payment_method VARCHAR(50),
        amount DECIMAL(10,2),
        proof_image VARCHAR(255),
        reference_id VARCHAR(100),
        source ENUM('POS', 'Online') DEFAULT 'Online',
        payment_status ENUM('Pending', 'Verified', 'Rejected', 'To Verify', 'Incomplete') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (order_id),
        INDEX (reference_id)
    )";
    mysqli_query($conn, $sql);
    
    // Check for missing columns in case table already exists
    $columns = mysqli_query($conn, "SHOW COLUMNS FROM payments");
    $existing_cols = [];
    while ($col = mysqli_fetch_assoc($columns)) {
        $existing_cols[] = $col['Field'];
    }

    if (!in_array('sender_name', $existing_cols)) {
        mysqli_query($conn, "ALTER TABLE payments ADD COLUMN sender_name VARCHAR(255) AFTER order_id");
    }
    if (!in_array('reference_id', $existing_cols)) {
        mysqli_query($conn, "ALTER TABLE payments ADD COLUMN reference_id VARCHAR(100) AFTER proof_image");
    }
    if (!in_array('source', $existing_cols)) {
        mysqli_query($conn, "ALTER TABLE payments ADD COLUMN source ENUM('POS', 'Online') DEFAULT 'Online' AFTER reference_id");
    }
    if (!in_array('payment_status', $existing_cols)) {
        mysqli_query($conn, "ALTER TABLE payments ADD COLUMN payment_status ENUM('Pending', 'Verified', 'Rejected', 'To Verify', 'Incomplete') DEFAULT 'Pending' AFTER source");
    } else {
        mysqli_query($conn, "ALTER TABLE payments MODIFY COLUMN payment_status ENUM('Pending', 'Verified', 'Rejected', 'To Verify', 'Incomplete') DEFAULT 'Pending'");
    }
}
