<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$order_id = 2272; 

try {
    // Attempt 1: original (likely wrong)
    echo "Testing Attempt 1: original query...\n";
    $res1 = db_query("SELECT o.*, b.name as branch_name FROM orders o LEFT JOIN branches b ON o.branch_id = b.branch_id WHERE o.order_id = $order_id");
    if ($res1 === false) echo "Query 1 failed!\n";

    // Attempt 2: corrected
    echo "Testing Attempt 2: corrected names (b.branch_name, b.id)...\n";
    $res2 = db_query("SELECT o.*, b.branch_name FROM orders o LEFT JOIN branches b ON o.branch_id = b.id WHERE o.order_id = $order_id");
    if ($res2 !== false) echo "Query 2 SUCCESS! Found: " . ($res2[0]['branch_name'] ?? 'NULL') . "\n";
    else echo "Query 2 failed!\n";

} catch (Throwable $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
