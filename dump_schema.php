<?php
require_once 'includes/db.php';
$output = "";
function print_cols($table, &$output) {
    $output .= "TABLE: $table\n";
    $res = db_query("DESC $table");
    foreach($res as $r) $output .= " - " . $r['Field'] . " (" . $r['Type'] . ")\n";
    $output .= "\n";
}
print_cols('orders', $output);
print_cols('job_orders', $output);
print_cols('order_items', $output);
file_put_contents('schema_full.txt', $output);
echo "Wrote to schema_full.txt\n";
