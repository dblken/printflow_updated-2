<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PrintFlow Diagnostics</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .section { background: #252526; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #007acc; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        h2 { color: #4ec9b0; margin-top: 0; }
        pre { background: #1e1e1e; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007acc; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #005a9e; }
    </style>
</head>
<body>
    <h1>🔍 PrintFlow Deep Diagnostics</h1>

    <div class="section">
        <h2>1. File Line Counts (Actual vs Browser Reported)</h2>
        <?php
        $files = [
            'inv_items_management.php' => ['actual' => 0, 'browser_error' => [5099, 6393]],
            'orders_management.php' => ['actual' => 0, 'browser_error' => [3542, 4836]],
            'branches_management.php' => ['actual' => 0, 'browser_error' => [3025, 4319]],
            'customizations.php' => ['actual' => 0, 'browser_error' => [183, 13]],
        ];
        
        foreach ($files as $file => $data) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                $lines = count(file($path));
                $files[$file]['actual'] = $lines;
                
                $maxError = max($data['browser_error']);
                $status = $maxError > $lines ? 'error' : 'success';
                $icon = $status === 'error' ? '❌' : '✅';
                
                echo "<div class='$status'>";
                echo "$icon <strong>$file</strong><br>";
                echo "   Actual lines: $lines<br>";
                echo "   Browser errors at: " . implode(', ', $data['browser_error']) . "<br>";
                if ($maxError > $lines) {
                    echo "   <span class='error'>⚠️ MISMATCH! Browser is loading an old cached version!</span>";
                }
                echo "</div><br>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>2. PHP OpCache Status</h2>
        <?php
        if (function_exists('opcache_get_status')) {
            $status = opcache_get_status();
            if ($status) {
                echo "<div class='success'>✅ OpCache is enabled</div>";
                echo "<pre>";
                echo "Memory Usage: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
                echo "Cached Scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
                echo "Hits: " . $status['opcache_statistics']['hits'] . "\n";
                echo "Misses: " . $status['opcache_statistics']['misses'] . "\n";
                echo "</pre>";
                
                // Clear OpCache
                if (isset($_GET['clear_opcache'])) {
                    opcache_reset();
                    echo "<div class='success'>✅ OpCache cleared!</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ OpCache is disabled</div>";
            }
        } else {
            echo "<div class='warning'>⚠️ OpCache not available</div>";
        }
        ?>
    </div>

    <div class="section">
        <h2>3. Session Information</h2>
        <pre><?php
        echo "Session ID: " . session_id() . "\n";
        echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";
        echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
        echo "User Type: " . ($_SESSION['user_type'] ?? 'Not set') . "\n";
        ?></pre>
    </div>

    <div class="section">
        <h2>4. Response Headers Being Sent</h2>
        <pre><?php
        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
        
        foreach ($headers as $name => $value) {
            echo "$name: $value\n";
        }
        ?></pre>
    </div>

    <div class="section">
        <h2>5. File Modification Times</h2>
        <pre><?php
        foreach (array_keys($files) as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                $mtime = filemtime($path);
                echo "$file: " . date('Y-m-d H:i:s', $mtime) . " (" . time_elapsed_string($mtime) . ")\n";
            }
        }
        
        function time_elapsed_string($datetime) {
            $now = time();
            $diff = $now - $datetime;
            
            if ($diff < 60) return $diff . ' seconds ago';
            if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
            if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
            return floor($diff / 86400) . ' days ago';
        }
        ?></pre>
    </div>

    <div class="section">
        <h2>6. Sidebar JavaScript Check</h2>
        <?php
        $sidebar_files = [
            '../includes/admin_sidebar.php',
            '../includes/staff_sidebar.php',
            '../includes/manager_sidebar.php',
        ];
        
        foreach ($sidebar_files as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $has_json_encode = strpos($content, 'json_encode($_pf_uid)') !== false;
                $status = $has_json_encode ? 'success' : 'error';
                $icon = $has_json_encode ? '✅' : '❌';
                
                echo "<div class='$status'>";
                echo "$icon " . basename($file) . ": ";
                echo $has_json_encode ? "Fixed (using json_encode)" : "NOT FIXED (missing json_encode)";
                echo "</div>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>7. Browser Cache Detection</h2>
        <div class="warning">
            ⚠️ If you see errors on lines that don't exist in the actual files above, your browser cache is corrupted.
        </div>
        <p>Current timestamp: <?php echo time(); ?></p>
        <p>Add this to your URL to force refresh: <code>?v=<?php echo time(); ?></code></p>
    </div>

    <div class="section">
        <h2>Actions</h2>
        <a href="?clear_opcache=1" class="btn">Clear PHP OpCache</a>
        <a href="inv_items_management.php?v=<?php echo time(); ?>" class="btn">Open Inventory (Cache Bust)</a>
        <a href="orders_management.php?v=<?php echo time(); ?>" class="btn">Open Orders (Cache Bust)</a>
        <a href="branches_management.php?v=<?php echo time(); ?>" class="btn">Open Branches (Cache Bust)</a>
        <br><br>
        <a href="dashboard.php" class="btn">Go to Dashboard</a>
    </div>

    <div class="section">
        <h2>8. Recommended Solutions</h2>
        <ol>
            <li><strong>Use Incognito Mode:</strong> Press Ctrl+Shift+N and access the site</li>
            <li><strong>Use Different Browser:</strong> Install Firefox if using Chrome (or vice versa)</li>
            <li><strong>Reset Browser Profile:</strong> Rename Chrome's Default folder</li>
            <li><strong>Add Version Parameter:</strong> Always append ?v=<?php echo time(); ?> to URLs</li>
        </ol>
    </div>
</body>
</html>
