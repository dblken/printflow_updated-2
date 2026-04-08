<?php
/**
 * Create customizations table for POS services
 */
require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><title>Setup Customizations Table</title></head><body>";
echo "<h2>Setting up Customizations Table</h2>";

try {
    global $conn;
    
    // Create customizations table
    $sql = "CREATE TABLE IF NOT EXISTS `customizations` (
      `customization_id` INT AUTO_INCREMENT PRIMARY KEY,
      `order_id` INT NOT NULL,
      `order_item_id` INT DEFAULT NULL,
      `customer_id` INT NOT NULL,
      `service_type` VARCHAR(100) NOT NULL,
      `customization_details` TEXT,
      `status` VARCHAR(50) NOT NULL DEFAULT 'Pending Review',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_order` (`order_id`),
      KEY `idx_customer` (`customer_id`),
      KEY `idx_status` (`status`),
      KEY `idx_order_item` (`order_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green; font-size:18px;'>✓ Customizations table created successfully!</p>";
    } else {
        throw new Exception($conn->error);
    }
    
    // Verify
    $check = db_query("SHOW TABLES LIKE 'customizations'");
    if (!empty($check)) {
        echo "<p style='color:green;'>✓ Table verified!</p>";
        
        echo "<h3>Table Structure:</h3>";
        $desc = db_query("DESCRIBE customizations");
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
        echo "<tr style='background:#f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($desc as $col) {
            echo "<tr>";
            echo "<td><strong>{$col['Field']}</strong></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<hr>";
        echo "<h3 style='color:green;'>✓ Setup Complete!</h3>";
        echo "<p><strong>Now you can:</strong></p>";
        echo "<ol style='font-size:16px;'>";
        echo "<li>Go to <a href='/printflow/staff/pos.php' target='_blank'>POS</a></li>";
        echo "<li>Add a service (click on any service button)</li>";
        echo "<li>Fill in the service details and set the price</li>";
        echo "<li>Complete checkout</li>";
        echo "<li>Service will appear in <a href='/printflow/staff/customizations.php' target='_blank'>Customizations</a> page</li>";
        echo "</ol>";
        
    } else {
        throw new Exception("Table creation verification failed");
    }
    
} catch (Exception $e) {
    echo "<p style='color:red; font-size:18px;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
