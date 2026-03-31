<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? 'admin@printflow.com';
    $password = $_POST['password'] ?? 'password';
    $first_name = $_POST['first_name'] ?? 'Admin';
    $last_name = $_POST['last_name'] ?? 'User';
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert admin user
    $sql = "INSERT INTO users (first_name, last_name, email, password_hash, role, status) 
            VALUES (?, ?, ?, ?, 'Admin', 'Activated')";
    
    $result = db_execute($sql, 'ssss', [$first_name, $last_name, $email, $password_hash]);
    
    if ($result) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Admin Created</title>
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
                <h2>✓ Admin Account Created Successfully!</h2>
                <p>You can now log in with these credentials:</p>
            </div>
            
            <div class='info'>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Password:</strong> {$password}</p>
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
                <h2>Error Creating Admin Account</h2>
                <p>Failed to create admin account. The email might already exist.</p>
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
