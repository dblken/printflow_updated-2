<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

echo "<h2>Service Field Configs Table Schema</h2>";
$schema = $conn->query("DESCRIBE service_field_configs");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while($row = $schema->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Service 27 Field Configurations</h2>";
$configs = $conn->query("SELECT * FROM service_field_configs WHERE service_id = 27 ORDER BY display_order");
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Field Key</th><th>Label</th><th>Type</th><th>Options</th><th>Required</th><th>Order</th></tr>";
while($row = $configs->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['config_id'] . "</td>";
    echo "<td>" . $row['field_key'] . "</td>";
    echo "<td>" . $row['field_label'] . "</td>";
    echo "<td>" . $row['field_type'] . "</td>";
    echo "<td><pre>" . htmlspecialchars($row['field_options']) . "</pre></td>";
    echo "<td>" . ($row['is_required'] ? 'Yes' : 'No') . "</td>";
    echo "<td>" . $row['display_order'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Check for Missing Columns</h2>";
$columns = $conn->query("SHOW COLUMNS FROM service_field_configs LIKE 'parent_%'");
if($columns->num_rows > 0) {
    echo "✅ Parent columns exist:<br>";
    while($row = $columns->fetch_assoc()) {
        echo "- " . $row['Field'] . "<br>";
    }
} else {
    echo "❌ Parent columns missing - need to add parent_field_key and parent_value columns<br>";
}

echo "<h2>Test JSON Parsing</h2>";
$test_json = '{"options":[{"value":"Option 1","nested_fields":[{"label":"Test Field","type":"text","required":true}]},"Option 2"]}';
echo "Test JSON: " . htmlspecialchars($test_json) . "<br>";
$parsed = json_decode($test_json, true);
echo "Parsed: " . print_r($parsed, true) . "<br>";
echo "JSON Error: " . json_last_error_msg() . "<br>";
?>