<?php
session_start();
// Clear all session data
session_destroy();
session_start();

// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Cache Cleared</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #0056b3; }
        .steps { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .steps ol { margin: 10px 0; padding-left: 20px; }
        .steps li { margin: 8px 0; }
    </style>
</head>
<body>
    <h1>🔄 Cache Clearing Complete</h1>
    
    <div class="success">
        ✅ Server-side session cleared<br>
        ✅ Cache headers set to prevent caching
    </div>

    <div class="steps">
        <h3>Now follow these steps:</h3>
        <ol>
            <li><strong>Close this browser completely</strong> (all windows and tabs)</li>
            <li><strong>Reopen your browser</strong></li>
            <li><strong>Press Ctrl + Shift + Delete</strong></li>
            <li>Select <strong>"Cached images and files"</strong> and <strong>"Cookies"</strong></li>
            <li>Time range: <strong>"All time"</strong></li>
            <li>Click <strong>"Clear data"</strong></li>
            <li>Click the button below to go to login</li>
        </ol>
    </div>

    <a href="/printflow/public/login.php" class="btn">Go to Login Page</a>
    <a href="/printflow/admin/dashboard.php" class="btn">Go to Dashboard</a>

    <hr style="margin: 30px 0;">
    
    <h3>Alternative: Use Incognito Mode</h3>
    <p>If the above doesn't work, open an <strong>Incognito/Private window</strong>:</p>
    <ul>
        <li>Chrome: Press <strong>Ctrl + Shift + N</strong></li>
        <li>Firefox: Press <strong>Ctrl + Shift + P</strong></li>
        <li>Edge: Press <strong>Ctrl + Shift + N</strong></li>
    </ul>
    <p>Then navigate to: <code>http://localhost/printflow/admin/dashboard.php</code></p>
</body>
</html>
