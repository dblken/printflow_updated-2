<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/service_field_config_helper.php';

require_role(['Admin', 'Manager']);

$service_id = 27;
$error = '';
$success = '';

// Handle test form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['test_save'])) {
        echo "<h2>🔍 POST Data Analysis</h2>";
        echo "<pre>POST Data: " . print_r($_POST, true) . "</pre>";
        
        if (isset($_POST['field_configs'])) {
            echo "<h3>Field Configs JSON:</h3>";
            echo "<pre>" . htmlspecialchars($_POST['field_configs']) . "</pre>";
            
            $configs = json_decode($_POST['field_configs'], true);
            echo "<h3>Decoded Configs:</h3>";
            echo "<pre>" . print_r($configs, true) . "</pre>";
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "<h3>❌ JSON Error:</h3>";
                echo "<p style='color:red;'>" . json_last_error_msg() . "</p>";
            } else {
                echo "<h3>✅ JSON is valid</h3>";
                
                // Try to save the test config
                foreach ($configs as $field_key => $config) {
                    echo "<h4>Saving field: $field_key</h4>";
                    try {
                        save_service_field_config($service_id, $field_key, $config);
                        echo "<p style='color:green;'>✅ Saved successfully</p>";
                    } catch (Exception $e) {
                        echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
                    }
                }
                
                $success = "Test configuration saved!";
            }
        } else {
            echo "<h3>❌ No field_configs in POST data</h3>";
        }
    }
}

$field_configs = get_service_field_config($service_id);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Nested Field Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .test-section { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 10px; border-radius: 5px; margin: 10px 0; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; } .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; } .btn-success:hover { background: #218838; }
        pre { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .pass { background: #d4edda; color: #155724; }
        .fail { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>🔧 Nested Field Diagnostic Tool</h1>
    
    <?php if ($success): ?>
        <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="test-section">
        <h2>📊 Current Database State</h2>
        <h3>Service 27 Field Configurations:</h3>
        <pre><?php print_r($field_configs); ?></pre>
        
        <h3>Raw Database Records:</h3>
        <?php
        $raw_data = db_query("SELECT * FROM service_field_configs WHERE service_id = ? ORDER BY display_order", 'i', [$service_id]);
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Field Key</th><th>Label</th><th>Type</th><th>Options (JSON)</th><th>Required</th></tr>";
        foreach ($raw_data as $row) {
            echo "<tr>";
            echo "<td>" . $row['config_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['field_key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['field_label']) . "</td>";
            echo "<td>" . htmlspecialchars($row['field_type']) . "</td>";
            echo "<td><pre style='max-width: 300px; overflow: auto; font-size: 11px;'>" . htmlspecialchars($row['field_options']) . "</pre></td>";
            echo "<td>" . ($row['is_required'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        ?>
    </div>
    
    <div class="test-section">
        <h2>🧪 JavaScript Function Tests</h2>
        <button onclick="testJavaScriptFunctions()">Run JavaScript Tests</button>
        <div id="js-test-results"></div>
    </div>
    
    <div class="test-section">
        <h2>📝 Manual Test Form</h2>
        <p>This form simulates what should happen when you add a nested field:</p>
        
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="field_configs" id="testFieldConfigs">
            
            <h3>Test Configuration:</h3>
            <textarea id="configTextarea" rows="15" cols="80" style="width: 100%; font-family: monospace;">{
  "test_radio_field": {
    "label": "Test Radio Field",
    "type": "radio",
    "required": true,
    "visible": true,
    "order": 10,
    "options": [
      {
        "value": "Option 1",
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
      "Option 2",
      {
        "value": "Option 3",
        "nested_fields": [
          {
            "label": "Another Nested Field",
            "type": "textarea",
            "required": false
          }
        ]
      }
    ]
  }
}</textarea>
            
            <br><br>
            <button type="button" onclick="loadTestConfig()">Load Test Config</button>
            <button type="submit" name="test_save" class="btn-success">Save Test Configuration</button>
        </form>
    </div>
    
    <div class="test-section">
        <h2>🔍 Live Form Monitoring</h2>
        <p>Open the actual service field config page and monitor what happens:</p>
        <a href="service_field_config.php?service_id=27" target="_blank" style="display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Open Service Field Config</a>
        
        <h3>Instructions:</h3>
        <ol>
            <li>Open the link above in a new tab</li>
            <li>Open browser console (F12)</li>
            <li>Try to add a new radio field with nested options</li>
            <li>Watch the console for debug messages</li>
            <li>Come back here and check if data was saved</li>
        </ol>
        
        <button onclick="refreshData()">Refresh Database Data</button>
    </div>
    
    <div class="test-section">
        <h2>⚙️ System Checks</h2>
        <div id="system-checks">
            <div class="test-result">
                <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?>
                <?php if (version_compare(PHP_VERSION, '7.4.0', '>=')): ?>
                    <span style="color: green;">✅ OK</span>
                <?php else: ?>
                    <span style="color: red;">❌ Too old</span>
                <?php endif; ?>
            </div>
            
            <div class="test-result">
                <strong>JSON Extension:</strong>
                <?php if (extension_loaded('json')): ?>
                    <span style="color: green;">✅ Available</span>
                <?php else: ?>
                    <span style="color: red;">❌ Missing</span>
                <?php endif; ?>
            </div>
            
            <div class="test-result">
                <strong>Database Connection:</strong>
                <?php 
                try {
                    $test_query = db_query("SELECT 1");
                    echo '<span style="color: green;">✅ Connected</span>';
                } catch (Exception $e) {
                    echo '<span style="color: red;">❌ Error: ' . $e->getMessage() . '</span>';
                }
                ?>
            </div>
            
            <div class="test-result">
                <strong>service_field_configs Table:</strong>
                <?php 
                try {
                    $table_check = db_query("DESCRIBE service_field_configs");
                    echo '<span style="color: green;">✅ Exists (' . count($table_check) . ' columns)</span>';
                } catch (Exception $e) {
                    echo '<span style="color: red;">❌ Error: ' . $e->getMessage() . '</span>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
    function testJavaScriptFunctions() {
        const results = document.getElementById('js-test-results');
        let html = '<h3>JavaScript Test Results:</h3>';
        
        // Test 1: Check if functions exist
        const functions = [
            'addNewField',
            'collectNestedFieldConfigurations',
            'toggleNestedFieldPanel',
            'addNestedFieldItem'
        ];
        
        functions.forEach(funcName => {
            if (typeof window[funcName] === 'function') {
                html += `<div class="test-result pass">✅ ${funcName} function exists</div>`;
            } else {
                html += `<div class="test-result fail">❌ ${funcName} function missing</div>`;
            }
        });
        
        // Test 2: JSON handling
        const testData = {
            "test_field": {
                "label": "Test",
                "type": "radio",
                "options": [
                    {
                        "value": "Option 1",
                        "nested_fields": [{"label": "Nested", "type": "text", "required": true}]
                    }
                ]
            }
        };
        
        try {
            const jsonString = JSON.stringify(testData);
            const parsed = JSON.parse(jsonString);
            html += '<div class="test-result pass">✅ JSON serialization works</div>';
        } catch (e) {
            html += `<div class="test-result fail">❌ JSON error: ${e.message}</div>`;
        }
        
        // Test 3: Form elements
        const form = document.getElementById('configForm');
        if (form) {
            html += '<div class="test-result pass">✅ Config form found</div>';
        } else {
            html += '<div class="test-result fail">❌ Config form not found</div>';
        }
        
        results.innerHTML = html;
    }
    
    function loadTestConfig() {
        const config = document.getElementById('configTextarea').value;
        document.getElementById('testFieldConfigs').value = config;
        alert('Test configuration loaded into hidden field');
    }
    
    function refreshData() {
        window.location.reload();
    }
    
    // Auto-run tests on page load
    document.addEventListener('DOMContentLoaded', function() {
        testJavaScriptFunctions();
    });
    </script>
</body>
</html>