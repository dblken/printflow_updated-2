<?php
/**
 * Database Migration: Create Dynamic Service Form Tables
 * Run this file once to create the required tables
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_role('Admin');

$errors = [];
$success = [];

// SQL statements to execute
$migrations = [
    'service_form_configs' => "
        CREATE TABLE IF NOT EXISTS `service_form_configs` (
          `config_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `product_id` INT UNSIGNED NOT NULL,
          `is_active` TINYINT(1) DEFAULT 0,
          `allow_custom_design` TINYINT(1) DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
          UNIQUE KEY `unique_product` (`product_id`)
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
    try {
        $conn = get_db_connection();
        if ($conn->query($sql)) {
            $success[] = "✓ Table '$table' created successfully";
        } else {
            $errors[] = "✗ Error creating table '$table': " . $conn->error;
        }
    } catch (Exception $e) {
        $errors[] = "✗ Exception creating table '$table': " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - PrintFlow</title>
    <link href="/printflow/public/assets/css/output.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { font-size: 1.875rem; font-weight: 700; color: #1f2937; margin-bottom: 1.5rem; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; margin-bottom: 0.5rem; color: #065f46; }
        .error { background: #fee2e2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 0.5rem; color: #991b1b; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #00232b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 1.5rem; }
        .btn:hover { background: #0F4C5C; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Migration</h1>
        
        <?php if (!empty($success)): ?>
            <h2 style="color: #10b981; font-size: 1.25rem; margin-bottom: 1rem;">✓ Success</h2>
            <?php foreach ($success as $msg): ?>
                <div class="success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <h2 style="color: #ef4444; font-size: 1.25rem; margin-bottom: 1rem;">✗ Errors</h2>
            <?php foreach ($errors as $msg): ?>
                <div class="error"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($errors)): ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: #dbeafe; border-radius: 8px;">
                <p style="color: #1e40af; font-weight: 600; margin-bottom: 0.5rem;">✓ Migration completed successfully!</p>
                <p style="color: #1e3a8a; font-size: 0.875rem;">All required tables have been created. You can now use the dynamic service form system.</p>
            </div>
            <a href="service_forms.php" class="btn">Go to Service Forms →</a>
        <?php else: ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: #fef3c7; border-radius: 8px;">
                <p style="color: #92400e; font-weight: 600; margin-bottom: 0.5rem;">⚠ Migration completed with errors</p>
                <p style="color: #78350f; font-size: 0.875rem;">Some tables may already exist or there were permission issues. Check the errors above.</p>
            </div>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>
