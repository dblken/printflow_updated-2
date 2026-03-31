<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Emergency Cache Clear</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            text-align: center;
        }
        h1 {
            color: #1f2937;
            margin: 0 0 20px 0;
            font-size: 32px;
        }
        .error-box {
            background: #fef2f2;
            border: 2px solid #ef4444;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: left;
        }
        .error-box h3 {
            color: #991b1b;
            margin: 0 0 10px 0;
        }
        .error-box code {
            background: #fee2e2;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: #991b1b;
        }
        .btn {
            display: inline-block;
            padding: 16px 32px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            margin: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
        }
        .btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
        }
        .btn-secondary {
            background: #3b82f6;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }
        .btn-secondary:hover {
            background: #2563eb;
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.6);
        }
        .instructions {
            background: #eff6ff;
            border: 2px solid #3b82f6;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: left;
        }
        .instructions h3 {
            color: #1e40af;
            margin: 0 0 15px 0;
        }
        .instructions ol {
            margin: 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 10px 0;
            line-height: 1.6;
        }
        .kbd {
            display: inline-block;
            padding: 4px 8px;
            background: #1f2937;
            color: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            font-weight: 600;
        }
        #countdown {
            font-size: 48px;
            font-weight: 700;
            color: #10b981;
            margin: 20px 0;
        }
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #10b981;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🔥 Emergency Cache Clear</h1>
    
    <div class="error-box">
        <h3>⚠️ Your Browser Has Cached Errors</h3>
        <p>You're seeing these errors:</p>
        <ul style="font-size: 13px; line-height: 1.8;">
            <li><code>Uncaught SyntaxError: Invalid or unexpected token</code></li>
            <li><code>Unexpected token &#39;&lt;&#39;</code></li>
            <li><code>Error updating table: SyntaxError</code></li>
        </ul>
        <p style="margin-top: 15px;"><strong>This means your browser is showing an OLD cached version with HTML entities.</strong></p>
    </div>

    <div class="instructions">
        <h3>🎯 Follow These Steps NOW:</h3>
        <ol>
            <li>Press <span class="kbd">Ctrl</span> + <span class="kbd">Shift</span> + <span class="kbd">Delete</span></li>
            <li>Check: <strong>"Cached images and files"</strong></li>
            <li>Time range: <strong>"All time"</strong></li>
            <li>Click: <strong>"Clear data"</strong></li>
            <li>Then click the button below</li>
        </ol>
    </div>

    <div id="countdown"></div>
    <div id="spinner" class="spinner" style="display: none;"></div>

    <a href="#" id="clearBtn" class="btn" onclick="clearAndRedirect(); return false;">
        ✅ I Cleared Cache - Open Inventory Ledger
    </a>

    <br>

    <a href="test_ajax_diagnostic.php" class="btn btn-secondary" target="_blank">
        🧪 Run Diagnostic Test First
    </a>

    <div style="margin-top: 30px; padding: 20px; background: #fef3c7; border-radius: 12px; border: 2px solid #f59e0b;">
        <h3 style="color: #92400e; margin: 0 0 10px 0;">⚡ Quick Alternative</h3>
        <p style="margin: 0; color: #78350f;">Press <span class="kbd">Ctrl</span> + <span class="kbd">Shift</span> + <span class="kbd">N</span> to open Incognito mode, then paste this URL:</p>
        <input type="text" readonly value="http://localhost/printflow/admin/inv_transactions_ledger.php" 
               style="width: 100%; padding: 10px; margin-top: 10px; border: 1px solid #f59e0b; border-radius: 6px; font-family: monospace;"
               onclick="this.select()">
    </div>
</div>

<script>
    // Generate unique timestamp to bust cache
    const timestamp = new Date().getTime();
    
    function clearAndRedirect() {
        // Show spinner
        document.getElementById('countdown').style.display = 'none';
        document.getElementById('spinner').style.display = 'block';
        document.getElementById('clearBtn').style.display = 'none';
        
        // Wait a moment then redirect with cache-busting parameter
        setTimeout(() => {
            window.location.href = 'inv_transactions_ledger.php?_nocache=' + timestamp + '&_cleared=1';
        }, 1000);
    }

    // Auto-countdown
    let count = 10;
    const countdownEl = document.getElementById('countdown');
    
    function updateCountdown() {
        if (count > 0) {
            countdownEl.textContent = count;
            count--;
            setTimeout(updateCountdown, 1000);
        } else {
            countdownEl.innerHTML = '<p style="font-size: 16px; color: #6b7280;">Click the button above when ready</p>';
        }
    }
    
    // Check if user came from a redirect
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auto') === '1') {
        countdownEl.innerHTML = '<p style="font-size: 18px; color: #10b981;">✅ Redirecting with cache bypass...</p>';
        setTimeout(() => {
            window.location.href = 'inv_transactions_ledger.php?_nocache=' + timestamp + '&_cleared=1';
        }, 2000);
    } else {
        updateCountdown();
    }

    // Prevent caching of this page itself
    window.addEventListener('beforeunload', () => {
        // Clear this page from cache
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => {
                    caches.delete(name);
                });
            });
        }
    });
</script>

</body>
</html>
```