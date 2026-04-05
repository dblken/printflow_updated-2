<?php
/**
 * Clean Service Field Configuration - No HTML Entity Issues
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_field_config_helper.php';

require_role(['Admin', 'Manager']);

// Force no cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$service_id = (int)($_GET['service_id'] ?? 27);
$error = '';
$success = '';

if ($service_id < 1) {
    header('Location: services_management.php');
    exit;
}

$service = db_query("SELECT * FROM services WHERE service_id = ?", 'i', [$service_id]);
if (empty($service)) {
    header('Location: services_management.php');
    exit;
}
$service = $service[0];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['save_config'])) {
        error_log('Clean version - POST data: ' . print_r($_POST, true));
        
        $configs = json_decode($_POST['field_configs'] ?? '[]', true);
        error_log('Clean version - Decoded configs: ' . print_r($configs, true));
        
        if (json_last_error() === JSON_ERROR_NONE && !empty($configs)) {
            foreach ($configs as $field_key => $config) {
                error_log("Clean version - Saving field {$field_key}: " . print_r($config, true));
                save_service_field_config($service_id, $field_key, $config);
            }
            $success = 'Field configuration saved successfully!';
        } else {
            $error = 'Invalid JSON data: ' . json_last_error_msg();
        }
    }
}

$field_configs = get_service_field_config($service_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Service Field Config</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        input, select, textarea { padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin: 5px; }
        .field-config { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 10px 0; border-radius: 5px; }
        pre { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Clean Service Field Configuration</h1>
        <p><strong>Service:</strong> <?php echo htmlspecialchars($service['name']); ?></p>
        
        <?php if ($success): ?>
            <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="info">
            <strong>🔧 This is a clean version without HTML entity encoding issues.</strong><br>
            Use this to test if nested field saving works when there are no JavaScript syntax errors.
        </div>
        
        <div class="field-config">
            <h3>📊 Current Field Configurations</h3>
            <pre><?php print_r($field_configs); ?></pre>
        </div>
        
        <div class="field-config">
            <h3>🧪 Test Nested Field Save</h3>
            <form method="POST" id="testForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="field_configs" id="fieldConfigsInput">
                
                <h4>Test Configuration (Edit as needed):</h4>
                <textarea id="configTextarea" rows="20" cols="80" style="width: 100%; font-family: monospace;">{
  "test_nested_radio": {
    "label": "Test Nested Radio Field",
    "type": "radio",
    "required": true,
    "visible": true,
    "order": 10,
    "options": [
      {
        "value": "Option with Nested Fields",
        "nested_fields": [
          {
            "label": "Nested Text Field",
            "type": "text",
            "required": true
          },
          {
            "label": "Nested Select Field",
            "type": "select",
            "required": false,
            "options": ["Choice A", "Choice B", "Choice C"]
          }
        ]
      },
      "Simple Option",
      {
        "value": "Another Option with Nested",
        "nested_fields": [
          {
            "label": "Nested Textarea",
            "type": "textarea",
            "required": false
          }
        ]
      }
    ]
  }
}</textarea>
                
                <br><br>
                <button type="button" onclick="loadConfig()" class="btn-success">Load Config to Form</button>
                <button type="submit" name="save_config" class="btn-success">Save Test Configuration</button>
            </form>
        </div>
        
        <div class="field-config">
            <h3>🔍 Debug Information</h3>
            <button onclick="showDebugInfo()">Show Debug Info</button>
            <div id="debugInfo"></div>
        </div>
        
        <div class="field-config">
            <h3>🔗 Navigation</h3>
            <a href="emergency_fix_html_entities.php" style="display: inline-block; background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Emergency Cache Fix</a>
            <a href="nested_field_diagnostic.php" style="display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Full Diagnostic</a>
            <a href="service_field_config.php?service_id=<?php echo $service_id; ?>" style="display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Original Page</a>
        </div>
    </div>

    <script>
    function loadConfig() {
        const config = document.getElementById('configTextarea').value;
        document.getElementById('fieldConfigsInput').value = config;
        alert('Configuration loaded into form. Click "Save Test Configuration" to save.');
    }
    
    function showDebugInfo() {
        const debugDiv = document.getElementById('debugInfo');
        let html = '<h4>Debug Information:</h4>';
        
        // Check current page URL
        html += '<p><strong>Current URL:</strong> ' + window.location.href + '</p>';
        
        // Check if we have the config data
        const configData = document.getElementById('configTextarea').value;
        try {
            const parsed = JSON.parse(configData);
            html += '<p style="color: green;"><strong>JSON Validation:</strong> ✅ Valid</p>';
            html += '<p><strong>Config Keys:</strong> ' + Object.keys(parsed).join(', ') + '</p>';
        } catch (e) {
            html += '<p style="color: red;"><strong>JSON Validation:</strong> ❌ ' + e.message + '</p>';
        }
        
        // Check form elements
        const form = document.getElementById('testForm');
        const hiddenInput = document.getElementById('fieldConfigsInput');
        
        html += '<p><strong>Form Element:</strong> ' + (form ? '✅ Found' : '❌ Missing') + '</p>';
        html += '<p><strong>Hidden Input:</strong> ' + (hiddenInput ? '✅ Found' : '❌ Missing') + '</p>';
        html += '<p><strong>Hidden Input Value Length:</strong> ' + (hiddenInput ? hiddenInput.value.length : 0) + ' characters</p>';
        
        debugDiv.innerHTML = html;
    }
    
    // Auto-load config on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadConfig();
        showDebugInfo();
    });
    </script>
</body>
</html>