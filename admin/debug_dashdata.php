<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/reports_dashboard_queries.php';

$branchCtx = init_branch_context(false);
$branchId  = $branchCtx['selected_branch_id'];

$from = date('Y-m-d', strtotime('-30 days'));
$to = date('Y-m-d');
$toEnd = $to . ' 23:59:59';

$getAllTimeWhere = function(string $alias = 'o', string $col = 'order_date') {
    return ["", "", []];
};

// 1. KPI
[$b, $bt, $bp] = branch_where_parts('o', $branchId);
[$dw, $dt, $dp] = $getAllTimeWhere('o', 'order_date');
$row = db_query(
    "SELECT COUNT(*) as total_orders,
            SUM(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE 0 END) as revenue
     FROM orders o WHERE 1=1 {$dw} {$b}",
    $dt . $bt, array_merge($dp, $bp)
)[0] ?? [];

// 2. Top products (All Time)
$top_products = pf_reports_top_products_merged('', '', $branchId, 10);

header('Content-Type: application/json');
echo json_encode([
    'total_orders' => $row['total_orders'] ?? 0,
    'revenue' => $row['revenue'] ?? 0,
    'top_products' => $top_products
], JSON_PRETTY_PRINT);
