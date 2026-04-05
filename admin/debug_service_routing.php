<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/service_field_config_helper.php';

echo "=== DEEP INVESTIGATION FOR SERVICE ID 5 ===\n\n";

// Check service details
$service = db_query("SELECT * FROM services WHERE service_id = 5", '', []);
if (empty($service)) {
    echo "ERROR: Service 5 not found!\n";
    exit;
}

$service = $service[0];
echo "Service Name: " . $service['name'] . "\n";
echo "Customer Link: " . ($service['customer_link'] ?: 'NOT SET') . "\n";
echo "Status: " . $service['status'] . "\n";
echo "Hero Image: " . ($service['hero_image'] ?: 'NOT SET') . "\n\n";

// Check field configuration
$has_config = service_has_field_config(5);
echo "Has Field Config: " . ($has_config ? 'YES' : 'NO') . "\n";

if ($has_config) {
    $configs = get_service_field_config(5);
    echo "Number of Fields: " . count($configs) . "\n";
    echo "Fields:\n";
    foreach ($configs as $key => $config) {
        echo "  - $key: " . $config['label'] . " (" . $config['type'] . ") - Visible: " . ($config['visible'] ? 'YES' : 'NO') . "\n";
    }
}

echo "\n=== ROUTING LOGIC ===\n";
if ($has_config) {
    echo "Should route to: order_service_dynamic.php?service_id=5\n";
} elseif (!empty($service['customer_link'])) {
    echo "Should route to: " . $service['customer_link'] . "\n";
} else {
    echo "Should route to: order_create.php (fallback)\n";
}

echo "\n=== CHECKING FILES ===\n";
echo "order_service_dynamic.php exists: " . (file_exists(__DIR__ . '/../customer/order_service_dynamic.php') ? 'YES' : 'NO') . "\n";
echo "order_create.php exists: " . (file_exists(__DIR__ . '/../customer/order_create.php') ? 'YES' : 'NO') . "\n";

if (!empty($service['customer_link'])) {
    echo $service['customer_link'] . " exists: " . (file_exists(__DIR__ . '/../customer/' . $service['customer_link']) ? 'YES' : 'NO') . "\n";
}
