<?php
/**
 * Emergency Account Unlock Script
 * Delete this file after use for security
 */

require_once __DIR__ . '/../includes/db.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Clear all login rate limits
db_execute("DELETE FROM rate_limit_log WHERE action = 'login'");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Account Unlocked</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin-top: 20px; }
        a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #53C5E0; color: white; text-decoration: none; border-radius: 5px; }
        a:hover { background: #32a1c4; }
    </style>
</head>
<body>
    <div class='success'>
        <h2>✓ Account Unlocked Successfully!</h2>
        <p>All login rate limits have been cleared for your IP address: <strong>{$ip}</strong></p>
        <p>You can now try logging in again.</p>
    </div>
    
    <div class='warning'>
        <strong>Security Notice:</strong> Please delete this file (unlock_account.php) after use to prevent unauthorized access.
    </div>
    
    <a href='login.php'>Go to Login Page</a>
</body>
</html>";
?>
