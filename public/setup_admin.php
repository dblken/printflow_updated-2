<?php
require_once __DIR__ . '/../includes/db.php';

// First, clear rate limits
db_execute("DELETE FROM rate_limit_log WHERE action = 'login'");

// Check if admin@printflow.com already exists
$existing = db_query("SELECT user_id FROM users WHERE email = 'admin@printflow.com'");

if (!empty($existing)) {
    // Delete existing admin account
    db_execute("DELETE FROM users WHERE email = 'admin@printflow.com'");
}

// Create new admin account
$email = 'admin@printflow.com';
$password = 'password';
$first_name = 'Admin';
$last_name = 'User';

// Hash password
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Insert admin user
$sql = "INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at) 
        VALUES (?, ?, ?, ?, 'Admin', 'Activated', NOW())";

$result = db_execute($sql, 'ssss', [$first_name, $last_name, $email, $password_hash]);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Account Created</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .success { 
            background: #d4edda; 
            border: 2px solid #28a745; 
            color: #155724; 
            padding: 20px; 
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .credentials {
            background: white;
            border: 2px solid #53C5E0;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .credentials p {
            margin: 10px 0;
            font-size: 16px;
        }
        .credentials strong {
            color: #00313d;
        }
        .btn { 
            display: inline-block; 
            margin-top: 20px; 
            padding: 12px 30px; 
            background: linear-gradient(135deg, #53C5E0, #32a1c4);
            color: white; 
            text-decoration: none; 
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .btn:hover { 
            background: linear-gradient(135deg, #32a1c4, #53C5E0);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        h1 { color: #00313d; }
        h2 { color: #155724; margin-top: 0; }
    </style>
</head>
<body>
    <?php if ($result): ?>
        <div class="success">
            <h2>✓ Admin Account Created Successfully!</h2>
            <p>Your new admin account has been created and is ready to use.</p>
        </div>
        
        <div class="credentials">
            <h1>Login Credentials</h1>
            <p><strong>Email:</strong> admin@printflow.com</p>
            <p><strong>Password:</strong> password</p>
        </div>
        
        <div class="success">
            <p>✓ Rate limits have been cleared</p>
            <p>✓ Account status: Activated</p>
            <p>✓ Role: Admin</p>
        </div>
        
        <a href="login.php" class="btn">Go to Login Page →</a>
        
        <div class="warning">
            <strong>⚠ Security Warning:</strong> Please delete this file (setup_admin.php) immediately after logging in!
        </div>
    <?php else: ?>
        <div style="background: #f8d7da; border: 2px solid #dc3545; color: #721c24; padding: 20px; border-radius: 8px;">
            <h2>❌ Error Creating Admin Account</h2>
            <p>Failed to create the admin account. Please check your database connection.</p>
            <a href="check_admin.php" class="btn">Go Back</a>
        </div>
    <?php endif; ?>
</body>
</html>
