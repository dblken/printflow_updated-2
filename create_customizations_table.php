<?php
/**
 * Create customizations table
 * Run this once to create the missing table
 */
require_once __DIR__ . '/includes/db.php';

echo "<h2>Creating Customizations Table</h2>";

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

try {
    global $conn;
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✓ Customizations table created successfully!</p>";
        
        // Verify it was created
        $result = db_query("SHOW TABLES LIKE 'customizations'");
        if (!empty($result)) {
            echo "<p style='color:green;'>✓ Table verified - it exists now!</p>";
            
            // Show structure
            echo "<h3>Table Structure:</h3>";
            $desc = db_query("DESCRIBE customizations");
            echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($desc as $col) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "<td>{$col['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<hr>";
        echo "<h3>✅ Setup Complete!</h3>";
        echo "<p>You can now:</p>";
        echo "<ol>";
        echo "<li>Go to POS and add a service to an order</li>";
        echo "<li>Complete the checkout</li>";
        echo "<li>The service will appear in <a href='/printflow/staff/customizations.php'>Customizations page</a></li>";
        echo "</ol>";
        
    } else {
        echo "<p style='color:red;'>❌ Error creating table: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Exception: " . $e->getMessage() . "</p>";
}
?>
