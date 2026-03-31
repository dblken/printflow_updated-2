<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? 'password';
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Update admin password
    $sql = "UPDATE users SET password_hash = ? WHERE email = 'admin@printflow.com'";
    
    $result = db_execute($sql, 's', [$password_hash]);
    
    if ($result) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
                .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
                a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #53C5E0; color: white; text-decoration: none; border-radius: 5px; }
                a:hover { background: #32a1c4; }
            </style>
        </head>
        <body>
            <div class='success'>
                <h2>✓ Password Reset Successfully!</h2>
                <p>The password for admin@printflow.com has been reset.</p>
            </div>
            
            <div class='info'>
                <p><strong>Email:</strong> admin@printflow.com</p>
                <p><strong>New Password:</strong> {$password}</p>
            </div>
            
            <a href='login.php'>Go to Login Page</a>
        </body>
        </html>";
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; }
                a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #53C5E0; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='error'>
                <h2>Error Resetting Password</h2>
                <p>Failed to reset password. The admin account might not exist.</p>
            </div>
            <a href='check_admin.php'>Go Back</a>
        </body>
        </html>";
    }
} else {
    header('Location: check_admin.php');
    exit();
}
?>
