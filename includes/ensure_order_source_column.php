<?php
/**
 * Ensure order_source column exists in orders table
 * This script adds the order_source column if it doesn't exist
 */

require_once __DIR__ . '/db.php';

function ensure_order_source_column() {
    global $conn;
    
    try {
        // Check if column exists
        $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_source'");
        
        if ($result->num_rows === 0) {
            // Column doesn't exist, add it
            $sql = "ALTER TABLE orders 
                    ADD COLUMN order_source ENUM('customer', 'pos', 'walk-in') DEFAULT 'customer' 
                    AFTER order_type";
            
            if ($conn->query($sql)) {
                error_log("Successfully added order_source column to orders table");
                return true;
            } else {
                error_log("Failed to add order_source column: " . $conn->error);
                return false;
            }
        }
        
        return true; // Column already exists
        
    } catch (Exception $e) {
        error_log("Error ensuring order_source column: " . $e->getMessage());
        return false;
    }
}

// Auto-run when included
ensure_order_source_column();
