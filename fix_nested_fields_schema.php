<?php
require_once 'includes/db.php';

echo "<h2>Adding Missing Columns to service_field_configs</h2>";

// Check if parent_field_key column exists
$result = $conn->query("SHOW COLUMNS FROM service_field_configs LIKE 'parent_field_key'");
if($result->num_rows == 0) {
    echo "Adding parent_field_key column...<br>";
    $conn->query("ALTER TABLE service_field_configs ADD COLUMN parent_field_key VARCHAR(100) DEFAULT NULL AFTER display_order");
    echo "✅ Added parent_field_key column<br>";
} else {
    echo "✅ parent_field_key column already exists<br>";
}

// Check if parent_value column exists
$result = $conn->query("SHOW COLUMNS FROM service_field_configs LIKE 'parent_value'");
if($result->num_rows == 0) {
    echo "Adding parent_value column...<br>";
    $conn->query("ALTER TABLE service_field_configs ADD COLUMN parent_value VARCHAR(255) DEFAULT NULL AFTER parent_field_key");
    echo "✅ Added parent_value column<br>";
} else {
    echo "✅ parent_value column already exists<br>";
}

// Check if unit column exists
$result = $conn->query("SHOW COLUMNS FROM service_field_configs LIKE 'unit'");
if($result->num_rows == 0) {
    echo "Adding unit column...<br>";
    $conn->query("ALTER TABLE service_field_configs ADD COLUMN unit VARCHAR(10) DEFAULT 'ft' AFTER default_value");
    echo "✅ Added unit column<br>";
} else {
    echo "✅ unit column already exists<br>";
}

// Check if allow_others column exists
$result = $conn->query("SHOW COLUMNS FROM service_field_configs LIKE 'allow_others'");
if($result->num_rows == 0) {
    echo "Adding allow_others column...<br>";
    $conn->query("ALTER TABLE service_field_configs ADD COLUMN allow_others TINYINT(1) DEFAULT 1 AFTER unit");
    echo "✅ Added allow_others column<br>";
} else {
    echo "✅ allow_others column already exists<br>";
}

echo "<h2>Updated Schema</h2>";
$schema = $conn->query("DESCRIBE service_field_configs");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while($row = $schema->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><a href='debug_nested_fields.php'>Check Current Data</a>";
?>