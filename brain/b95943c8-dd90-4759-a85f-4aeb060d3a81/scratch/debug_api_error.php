<?php
require_once 'c:/xampp/htdocs/printflow/includes/db.php';
try {
    $test = db_query("SELECT JSON_UNQUOTE(JSON_EXTRACT(customization_data, '$.service_type')) as st FROM order_items WHERE order_id = 2449");
    echo "JSON result: "; print_r($test);
} catch (Exception $e) {
    echo "JSON test failed: " . $e->getMessage() . "\n";
}
