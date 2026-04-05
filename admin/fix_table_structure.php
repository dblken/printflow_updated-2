<?php
/**
 * Fix service_form_fields table structure
 */

$host = 'localhost';
$user = 'root';
$pass = '1234';
$db = 'printflow_1';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errors = [];
$success = [];

// Drop the old table
if ($conn->query("DROP TABLE IF EXISTS `service_form_fields`")) {
    $success[] = "✓ Dropped old service_form_fields table";
} else {
    $errors[] = "✗ Error dropping table: " . $conn->error;
}

// Recreate with correct structure
$sql = "
CREATE TABLE `service_form_fields` (
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
  KEY `idx_config_step` (`config_id`, `step_number`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

if ($conn->query($sql)) {
    $success[] = "✓ Created service_form_fields table with correct structure";
} else {
    $errors[] = "✗ Error creating table: " . $conn->error;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Table Structure - PrintFlow</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { font-size: 1.875rem; font-weight: 700; color: #1f2937; margin-bottom: 1.5rem; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; margin-bottom: 0.5rem; color: #065f46; border-radius: 4px; }
        .error { background: #fee2e2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 0.5rem; color: #991b1b; border-radius: 4px; }
        .info { background: #dbeafe; padding: 1.5rem; border-radius: 8px; margin-top: 2rem; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #00232b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 1.5rem; }
        .btn:hover { background: #0F4C5C; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Table Structure</h1>
        
        <?php if (!empty($success)): ?>
            <?php foreach ($success as $msg): ?>
                <div class="success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $msg): ?>
                <div class="error"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($errors)): ?>
            <div class="info">
                <p style="color: #1e40af; font-weight: 600; margin-bottom: 0.5rem;">✓ Table structure fixed!</p>
                <p style="color: #1e3a8a; font-size: 0.875rem;">The service_form_fields table now has the correct structure.</p>
            </div>
            <a href="service_forms.php" class="btn">Go to Service Forms →</a>
        <?php else: ?>
            <a href="check_tables.php" class="btn">Check Tables Again</a>
        <?php endif; ?>
    </div>
</body>
</html>
