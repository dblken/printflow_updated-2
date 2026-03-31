<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Buster - Force Reload</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 { color: #1f2937; margin-top: 0; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin: 5px;
        }
        .btn:hover { background: #2563eb; }
        .btn-red { background: #ef4444; }
        .btn-red:hover { background: #dc2626; }
        .btn-green { background: #10b981; }
        .btn-green:hover { background: #059669; }
        .code {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .success {
            background: #ecfdf5;
            border: 2px solid #10b981;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        ol { line-height: 1.8; }
        li { margin: 10px 0; }
    </style>
</head>
<body>

<div class="card">
    <h1>🔄 Cache Buster & Browser Reset</h1>
    <p>Use this page to force your browser to reload all files and clear cache issues.</p>
</div>

<div class="card">
    <h2>⚡ Quick Fix (Try This First)</h2>
    <div class="warning">
        <strong>⚠️ You're seeing HTML entities in JavaScript errors!</strong><br>
        This means your browser is showing an OLD cached version of the files.
    </div>
    
    <h3>Step 1: Hard Refresh</h3>
    <p>Press these keys together:</p>
    <div class="code">
        Ctrl + Shift + R
        <br>OR<br>
        Ctrl + F5
    </div>
    
    <h3>Step 2: Clear Browser Cache</h3>
    <ol>
        <li>Press <code>Ctrl + Shift + Delete</code></li>
        <li>Select "Cached images and files"</li>
        <li>Select "All time" for time range</li>
        <li>Click "Clear data"</li>
    </ol>
    
    <h3>Step 3: Force Reload with Cache Buster</h3>
    <p>Click these buttons to open pages with cache-busting parameters:</p>
    
    <a href="inv_transactions_ledger.php?_nocache=<?php echo time(); ?>" class="btn btn-green" target="_blank">
        Open Inventory Ledger (Cache Busted)
    </a>
    
    <a href="customers_management.php?_nocache=<?php echo time(); ?>" class="btn btn-green" target="_blank">
        Open Customers (Cache Busted)
    </a>
    
    <a href="orders_management.php?_nocache=<?php echo time(); ?>" class="btn btn-green" target="_blank">
        Open Orders (Cache Busted)
    </a>
</div>

<div class="card">
    <h2>🧪 Test AJAX Functionality</h2>
    <p>Run this diagnostic to see if AJAX requests are working:</p>
    
    <a href="test_ajax_diagnostic.php" class="btn" target="_blank">
        Run AJAX Diagnostic Test
    </a>
</div>

<div class="card">
    <h2>🔧 Advanced: Disable Cache in DevTools</h2>
    <ol>
        <li>Open the page with errors (e.g., inv_transactions_ledger.php)</li>
        <li>Press <code>F12</code> to open DevTools</li>
        <li>Go to <strong>Network</strong> tab</li>
        <li>Check the box: <strong>"Disable cache"</strong></li>
        <li>Keep DevTools open and reload the page</li>
    </ol>
    <div class="success">
        ✅ With "Disable cache" checked, the browser will ALWAYS fetch fresh files
    </div>
</div>

<div class="card">
    <h2>🌐 Try Incognito/Private Mode</h2>
    <p>Open the page in a private window to bypass all cache:</p>
    <div class="code">
        Ctrl + Shift + N (Chrome/Edge)
        <br>OR<br>
        Ctrl + Shift + P (Firefox)
    </div>
    <p>Then navigate to:</p>
    <div class="code">
        http://localhost/printflow/admin/inv_transactions_ledger.php
    </div>
    <p><strong>If it works in Incognito:</strong> It's definitely a cache issue</p>
    <p><strong>If it still fails in Incognito:</strong> It's a server-side PHP error</p>
</div>

<div class="card">
    <h2>🚨 Nuclear Option: Clear Everything</h2>
    <div class="warning">
        <strong>⚠️ This will log you out of all websites!</strong>
    </div>
    <ol>
        <li>Close ALL browser windows</li>
        <li>Open browser</li>
        <li>Press <code>Ctrl + Shift + Delete</code></li>
        <li>Select ALL items:
            <ul>
                <li>✅ Browsing history</li>
                <li>✅ Cookies and other site data</li>
                <li>✅ Cached images and files</li>
            </ul>
        </li>
        <li>Time range: <strong>All time</strong></li>
        <li>Click "Clear data"</li>
        <li>Close browser completely</li>
        <li>Reopen browser</li>
        <li>Navigate to: <code>http://localhost/printflow/admin/inv_transactions_ledger.php</code></li>
    </ol>
</div>

<div class="card">
    <h2>📊 Check Current Status</h2>
    <button class="btn" onclick="checkStatus()">Check Browser Cache Status</button>
    <div id="statusResult" style="margin-top: 15px;"></div>
</div>

<script>
    function checkStatus() {
        const result = document.getElementById('statusResult');
        const timestamp = new Date().getTime();
        
        result.innerHTML = '<p>Checking...</p>';
        
        // Test if browser is caching
        fetch('inv_transactions_ledger.php?test=1&_=' + timestamp)
            .then(response => response.text())
            .then(text => {
                const hasHtmlEntities = text.includes('&#39;') || text.includes('&lt;') || text.includes('&gt;');
                const isHtml = text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html');
                
                let html = '<div class="' + (hasHtmlEntities ? 'warning' : 'success') + '">';
                
                if (hasHtmlEntities) {
                    html += '<h3>❌ HTML Entities Detected!</h3>';
                    html += '<p>Your browser is showing HTML entities in the response.</p>';
                    html += '<p><strong>Solution:</strong> Clear cache using the methods above.</p>';
                } else if (isHtml) {
                    html += '<h3>✅ HTML Response (Normal)</h3>';
                    html += '<p>The page is returning HTML as expected.</p>';
                } else {
                    html += '<h3>✅ Response Looks Good</h3>';
                    html += '<p>No HTML entities detected in the response.</p>';
                }
                
                html += '<p style="font-size:12px;color:#6b7280;margin-top:10px;">Response length: ' + text.length + ' characters</p>';
                html += '</div>';
                
                result.innerHTML = html;
            })
            .catch(error => {
                result.innerHTML = '<div class="warning"><h3>❌ Error</h3><p>' + error.message + '</p></div>';
            });
    }
</script>

<div class="card">
    <h2>📝 Summary</h2>
    <p><strong>Your Error:</strong></p>
    <div class="code">
        Uncaught SyntaxError: Unexpected token &#39;&lt;&#39;
        <br>
        Error updating table: SyntaxError: Expected property name or &#39;}&#39; in JSON
    </div>
    
    <p><strong>What This Means:</strong></p>
    <ul>
        <li>The browser is showing <code>&#39;</code> instead of <code>'</code></li>
        <li>The browser is showing <code>&lt;</code> instead of <code>&lt;</code></li>
        <li>This happens when HTML is being double-encoded or cached incorrectly</li>
    </ul>
    
    <p><strong>Most Likely Cause:</strong></p>
    <div class="warning">
        <strong>Browser Cache</strong> - Your browser cached an old/corrupted version of the file
    </div>
    
    <p><strong>Solution:</strong></p>
    <ol>
        <li>Try <strong>Hard Refresh</strong> first (Ctrl + Shift + R)</li>
        <li>If that doesn't work, <strong>Clear Cache</strong> (Ctrl + Shift + Delete)</li>
        <li>If still broken, try <strong>Incognito Mode</strong></li>
        <li>If Incognito works, it confirms it's a cache issue</li>
    </ol>
</div>

</body>
</html>
```