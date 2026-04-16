<?php
/**
 * Add estimated_price column to orders table
 * This column stores the customer-calculated estimated price for service/custom orders
 * before staff reviews and sets the final price
 */

require_once __DIR__ . '/includes/db.php';

try {
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'estimated_price'");
    
    if ($result->num_rows > 0) {
        echo "✓ Column 'estimated_price' already exists in orders table.\n";
    } else {
        // Add the column after total_amount
        $sql = "ALTER TABLE orders ADD COLUMN estimated_price DECIMAL(10,2) NULL AFTER total_amount";
        
        if ($conn->query($sql)) {
            echo "✓ Successfully added 'estimated_price' column to orders table.\n";
        } else {
            echo "✗ Error adding column: " . $conn->error . "\n";
        }
    }
    
    // Also add reference_id column if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'reference_id'");
    
    if ($result->num_rows > 0) {
        echo "✓ Column 'reference_id' already exists in orders table.\n";
    } else {
        $sql = "ALTER TABLE orders ADD COLUMN reference_id INT NULL AFTER customer_id";
        
        if ($conn->query($sql)) {
            echo "✓ Successfully added 'reference_id' column to orders table.\n";
        } else {
            echo "✗ Error adding column: " . $conn->error . "\n";
        }
    }
    
    // Also add order_type column if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_type'");
    
    if ($result->num_rows > 0) {
        echo "✓ Column 'order_type' already exists in orders table.\n";
    } else {
        $sql = "ALTER TABLE orders ADD COLUMN order_type VARCHAR(50) DEFAULT 'product' AFTER notes";
        
        if ($conn->query($sql)) {
            echo "✓ Successfully added 'order_type' column to orders table.\n";
        } else {
            echo "✗ Error adding column: " . $conn->error . "\n";
        }
    }
    
    // Also add order_source column if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_source'");
    
    if ($result->num_rows > 0) {
        echo "✓ Column 'order_source' already exists in orders table.\n";
    } else {
        $sql = "ALTER TABLE orders ADD COLUMN order_source VARCHAR(50) DEFAULT 'customer' AFTER order_type";
        
        if ($conn->query($sql)) {
            echo "✓ Successfully added 'order_source' column to orders table.\n";
        } else {
            echo "✗ Error adding column: " . $conn->error . "\n";
        }
    }
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
