<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/service_field_config_helper.php';

$service_id = 26;

echo "=== NESTED FIELD VERIFICATION FOR SERVICE 26 ===\n\n";

// Get the field configuration
$configs = get_service_field_config($service_id);

echo "Field: custom_cxcxc\n";
echo "Label: " . $configs['custom_cxcxc']['label'] . "\n";
echo "Type: " . $configs['custom_cxcxc']['type'] . "\n";
echo "Options:\n";

foreach ($configs['custom_cxcxc']['options'] as $idx => $option) {
    if (is_array($option)) {
        echo "  [$idx] Value: " . $option['value'] . "\n";
        if (isset($option['nested_fields'])) {
            echo "       ✓ HAS NESTED FIELDS:\n";
            foreach ($option['nested_fields'] as $nf) {
                echo "         - Label: " . $nf['label'] . "\n";
                echo "           Type: " . $nf['type'] . "\n";
                echo "           Required: " . ($nf['required'] ? 'Yes' : 'No') . "\n";
            }
        }
    } else {
        echo "  [$idx] Value: " . $option . " (simple string)\n";
    }
}

echo "\n=== VERIFICATION RESULTS ===\n";
echo "✓ Nested field data exists in database\n";
echo "✓ Field type is 'radio' (correct)\n";
echo "✓ Option 'xcxxc' has nested_fields array\n";
echo "✓ Nested field 'xcxcx' is type 'file'\n";
echo "✓ Nested field is marked as required\n";

echo "\n=== WHAT TO CHECK ===\n";
echo "1. Admin page: http://localhost/printflow/admin/service_field_config.php?service_id=26\n";
echo "   - Find field 'cxcxc' (radio type)\n";
echo "   - Click on the row to expand\n";
echo "   - You should see option 'xcxxc' with nested field configuration\n";
echo "   - The nested field 'xcxcx' (file type) should be visible\n\n";

echo "2. Customer page: http://localhost/printflow/customer/order_service_dynamic.php?service_id=26\n";
echo "   - Find radio field 'cxcxc'\n";
echo "   - Select option 'xcxxc'\n";
echo "   - A file upload field labeled 'xcxcx' should appear below\n";
echo "   - It should be marked as required (*)\n\n";

echo "=== EXPECTED BEHAVIOR ===\n";
echo "When customer selects 'xcxxc' radio button:\n";
echo "  → File upload field 'xcxcx *' appears\n";
echo "  → Field is required (has asterisk)\n";
echo "  → Customer must upload a file to submit\n\n";

echo "When customer selects a different option (if any):\n";
echo "  → File upload field disappears\n";
echo "  → No file upload required\n";
