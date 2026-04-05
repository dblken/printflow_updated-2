<?php
// Emergency fix for HTML entity encoding in JavaScript
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Emergency Fix - HTML Entity Encoding</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .fix-step { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .warning { background: #fff3cd; color: #856404; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
    </style>
</head>
<body>
    <h1>🚨 Emergency Fix - HTML Entity Encoding Issue</h1>
    
    <div class="fix-step error">
        <h3>❌ Problem Detected</h3>
        <p>Your browser is showing HTML entities (<code>&#39;</code>, <code>&lt;</code>) instead of actual characters in JavaScript.</p>
        <p>This is causing syntax errors and preventing nested fields from working.</p>
    </div>
    
    <div class="fix-step warning">
        <h3>⚡ Quick Fix Steps</h3>
        <ol>
            <li><strong>Clear Browser Cache Completely:</strong>
                <ul>
                    <li>Press <kbd>Ctrl + Shift + Delete</kbd></li>
                    <li>Select "All time" for time range</li>
                    <li>Check "Cached images and files"</li>
                    <li>Click "Clear data"</li>
                </ul>
            </li>
            <li><strong>Hard Refresh:</strong>
                <ul>
                    <li>Press <kbd>Ctrl + Shift + R</kbd> (or <kbd>Cmd + Shift + R</kbd> on Mac)</li>
                </ul>
            </li>
            <li><strong>Test in Incognito Mode:</strong>
                <ul>
                    <li>Press <kbd>Ctrl + Shift + N</kbd></li>
                    <li>Navigate to the service field config page</li>
                </ul>
            </li>
        </ol>
    </div>
    
    <div class="fix-step">
        <h3>🔧 Automated Cache Busting</h3>
        <p>Click the buttons below to open the service field config page with cache-busting parameters:</p>
        
        <button onclick="openWithCacheBust()" class="btn-success">
            Open Service Config (Cache Busted)
        </button>
        
        <button onclick="openIncognito()" class="btn-danger">
            Instructions for Incognito Mode
        </button>
    </div>
    
    <div class="fix-step">
        <h3>🧪 Test After Fix</h3>
        <p>After clearing cache, test the nested field functionality:</p>
        <ol>
            <li>Open the service field config page</li>
            <li>Open browser console (F12)</li>
            <li>Look for these messages:
                <ul>
                    <li>✅ <code>🔧 Debug nested field functions loading...</code></li>
                    <li>✅ <code>✅ Debug nested field functions loaded successfully</code></li>
                    <li>❌ NO syntax errors or HTML entity errors</li>
                </ul>
            </li>
            <li>Try adding a new radio field with nested options</li>
        </ol>
    </div>
    
    <div class="fix-step">
        <h3>📊 Current Status Check</h3>
        <button onclick="checkCurrentStatus()">Check JavaScript Status</button>
        <div id="status-results"></div>
    </div>

    <script>
    function openWithCacheBust() {
        const timestamp = new Date().getTime();
        const url = `service_field_config.php?service_id=27&_nocache=${timestamp}&_bust=${Math.random()}`;
        window.open(url, '_blank');
    }
    
    function openIncognito() {
        alert('To test in Incognito mode:\n\n1. Press Ctrl+Shift+N (or Cmd+Shift+N on Mac)\n2. Copy this URL: ' + window.location.origin + '/printflow/admin/service_field_config.php?service_id=27\n3. Paste and press Enter\n4. Check if the errors are gone');
    }
    
    function checkCurrentStatus() {
        const results = document.getElementById('status-results');
        let html = '<h4>JavaScript Status:</h4>';
        
        // Test if we can access the parent window functions
        try {
            if (typeof window.opener !== 'undefined' && window.opener) {
                if (typeof window.opener.addNewField === 'function') {
                    html += '<p style="color: green;">✅ addNewField function available</p>';
                } else {
                    html += '<p style="color: red;">❌ addNewField function missing</p>';
                }
                
                if (typeof window.opener.collectNestedFieldConfigurations === 'function') {
                    html += '<p style="color: green;">✅ collectNestedFieldConfigurations function available</p>';
                } else {
                    html += '<p style="color: red;">❌ collectNestedFieldConfigurations function missing</p>';
                }
            } else {
                html += '<p style="color: orange;">⚠️ Cannot check parent window functions</p>';
            }
        } catch (e) {
            html += '<p style="color: red;">❌ Error checking functions: ' + e.message + '</p>';
        }
        
        // Test JSON handling
        try {
            const testObj = {"test": "value with 'quotes' and <brackets>"};
            const jsonStr = JSON.stringify(testObj);
            const parsed = JSON.parse(jsonStr);
            html += '<p style="color: green;">✅ JSON handling works correctly</p>';
        } catch (e) {
            html += '<p style="color: red;">❌ JSON error: ' + e.message + '</p>';
        }
        
        results.innerHTML = html;
    }
    
    // Auto-check status on load
    setTimeout(checkCurrentStatus, 1000);
    </script>
</body>
</html>