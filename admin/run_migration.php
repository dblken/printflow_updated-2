<?php
/**
 * STANDALONE Database Migration Script
 * Run this ONCE to create dynamic service form tables
 */

// Direct database connection (no dependencies)
$host = 'localhost';
$user = 'root';
$pass = '1234'; // Your MySQL password
$db = 'printflow_1'; // Your database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errors = [];
$success = [];

// SQL statements
$migrations = [
    'service_form_configs' => "
        CREATE TABLE IF NOT EXISTS `service_form_configs` (
          `config_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `product_id` INT UNSIGNED NOT NULL,
          `is_active` TINYINT(1) DEFAULT 0,
          `allow_custom_design` TINYINT(1) DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY `unique_product` (`product_id`),
          KEY `idx_product` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'service_form_fields' => "
        CREATE TABLE IF NOT EXISTS `service_form_fields` (
          `field_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `config_id` INT UNSIGNED NOT NULL,
          `step_number` TINYINT UNSIGNED NOT NULL,
          `field_name` VARCHAR(100) NOT NULL,
          `field_label` VARCHAR(255) NOT NULL,
          `field_type` ENUM('text','number','email','tel','textarea','select','radio','checkbox','file','date') NOT NULL,
          `options_json` JSON DEFAULT NULL,
          `placeholder` VARCHAR(255) DEFAULT NULL,
          `default_value` VARCHAR(255) DEFAULT NULL,
          `is_required` TINYINT(1) DEFAULT 0,
          `validation_rules` JSON DEFAULT NULL,
          `help_text` TEXT DEFAULT NULL,
          `display_order` INT UNSIGNED DEFAULT 0,
          `conditional_logic` JSON DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`config_id`) ON DELETE CASCADE,
          KEY `idx_config_step` (`config_id`, `step_number`),
          KEY `idx_order` (`display_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'service_form_steps' => "
        CREATE TABLE IF NOT EXISTS `service_form_steps` (
          `step_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `config_id` INT UNSIGNED NOT NULL,
          `step_number` TINYINT UNSIGNED NOT NULL,
          `step_title` VARCHAR(255) NOT NULL,
          `step_description` TEXT DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`config_id`) ON DELETE CASCADE,
          UNIQUE KEY `unique_config_step` (`config_id`, `step_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'service_pricing_rules' => "
        CREATE TABLE IF NOT EXISTS `service_pricing_rules` (
          `rule_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `config_id` INT UNSIGNED NOT NULL,
          `rule_name` VARCHAR(255) NOT NULL,
          `rule_type` ENUM('base','multiplier','addon','conditional') NOT NULL,
          `trigger_field` VARCHAR(100) DEFAULT NULL,
          `trigger_value` VARCHAR(255) DEFAULT NULL,
          `price_adjustment` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `calculation_formula` TEXT DEFAULT NULL,
          `is_active` TINYINT(1) DEFAULT 1,
          `display_order` INT UNSIGNED DEFAULT 0,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`config_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

// Execute migrations
foreach ($migrations as $table => $sql) {
    if ($conn->query($sql)) {
        $success[] = "✓ Table '$table' created successfully";
    } else {
        $errors[] = "✗ Error creating table '$table': " . $conn->error;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - PrintFlow</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { font-size: 1.875rem; font-weight: 700; color: #1f2937; margin-bottom: 1.5rem; }
        h2 { font-size: 1.25rem; margin: 1.5rem 0 1rem; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; margin-bottom: 0.5rem; color: #065f46; border-radius: 4px; }
        .error { background: #fee2e2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 0.5rem; color: #991b1b; border-radius: 4px; }
        .info { background: #dbeafe; padding: 1.5rem; border-radius: 8px; margin-top: 2rem; }
        .info p { color: #1e40af; margin-bottom: 0.5rem; }
        .info p:last-child { color: #1e3a8a; font-size: 0.875rem; margin-bottom: 0; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #00232b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 1.5rem; transition: background 0.2s; }
        .btn:hover { background: #0F4C5C; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Migration</h1>
        
        <?php if (!empty($success)): ?>
            <h2 style="color: #10b981;">✓ Success</h2>
            <?php foreach ($success as $msg): ?>
                <div class="success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <h2 style="color: #ef4444;">✗ Errors</h2>
            <?php foreach ($errors as $msg): ?>
                <div class="error"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($errors)): ?>
            <div class="info">
                <p style="font-weight: 600;">✓ Migration completed successfully!</p>
                <p>All required tables have been created. You can now use the dynamic service form system.</p>
            </div>
            <a href="service_forms.php" class="btn">Go to Service Forms →</a>
        <?php else: ?>
            <div class="info" style="background: #fef3c7;">
                <p style="color: #92400e; font-weight: 600;">⚠ Migration completed with errors</p>
                <p style="color: #78350f;">Some tables may already exist. If all tables show as created, you can proceed.</p>
            </div>
            <a href="service_forms.php" class="btn">Try Service Forms Anyway →</a>
        <?php endif; ?>
        
        <div style="margin-top: 2rem; padding: 1rem; background: #f9fafb; border-radius: 8px; font-size: 0.875rem; color: #6b7280;">
            <strong>Note:</strong> This script can be safely deleted after running. The tables are now permanent in your database.
        </div>
    </div>
</body>
</html>
