<?php
/**
 * Migration: Add profile_completion_fields_to_clear column
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$success = false;
$error = '';

try {
    // Check if column exists
    $columns = db_query("SHOW COLUMNS FROM users LIKE 'profile_completion_fields_to_clear'");
    
    if (empty($columns)) {
        // Add the column
        db_execute("ALTER TABLE users ADD COLUMN profile_completion_fields_to_clear TEXT NULL AFTER profile_completion_expires");
        $success = true;
        $message = "Column 'profile_completion_fields_to_clear' added successfully!";
    } else {
        $success = true;
        $message = "Column 'profile_completion_fields_to_clear' already exists.";
    }
} catch (Exception $e) {
    $error = "Migration failed: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #111827; margin: 0 0 20px 0; font-size: 24px; }
        .success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #0d9488; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .btn:hover { background: #0f766e; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Migration</h1>
        
        <?php if ($success): ?>
            <div class="success">
                ✓ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                ✗ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 20px;">
            <a href="/printflow/admin/user_staff_management.php" class="btn">Go to User Management</a>
        </p>
    </div>
</body>
</html>
