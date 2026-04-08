<?php
/**
 * Create customizations table and migrate existing data
 */
require_once __DIR__ . '/../includes/db.php';

echo "<h2>Creating Customizations Table</h2>";

try {
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
    
    global $conn;
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✓ Customizations table created successfully!</p>";
    } else {
        echo "<p style='color:red;'>❌ Error creating table: " . $conn->error . "</p>";
    }
    
    // Verify table was created
    $check = db_query("SHOW TABLES LIKE 'customizations'");
    if (!empty($check)) {
        echo "<p style='color:green;'>✓ Table verified - customizations table exists!</p>";
        
        // Show structure
        echo "<h3>Table Structure:</h3>";
        $desc = db_query("DESCRIBE customizations");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($desc as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>❌ Table creation failed!</p>";
    }
    
    echo "<hr>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Go to POS: <a href='/printflow/staff/pos.php'>http://localhost/printflow/staff/pos.php</a></li>";
    echo "<li>Add a service (e.g., 'Sample' or 'T-Shirt')</li>";
    echo "<li>Complete checkout with Walk-in Customer</li>";
    echo "<li>Check customizations page: <a href='/printflow/staff/customizations.php'>http://localhost/printflow/staff/customizations.php</a></li>";
    echo "<li>The service should appear in the PENDING tab</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
