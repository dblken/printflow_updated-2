<?php
/**
 * Staff Dashboard API
 * Returns real-time statistics and filtered data
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

// Check staff access
if (!has_role('Staff')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$staffCtx = init_branch_context();
$staffBranchId = $staffCtx['selected_branch_id'] === 'all' ? (int)($_SESSION['branch_id'] ?? 1) : (int)$staffCtx['selected_branch_id'];

// --- Inputs ---
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';
$timeframe = $_GET['timeframe'] ?? 'today';

// --- Timeframe Logic ---
$timeframe_sql = "DATE(o.order_date) = CURDATE()";
$timeframe_sql_no_alias = "DATE(order_date) = CURDATE()";

switch ($timeframe) {
    case 'week': 
        $timeframe_sql = "YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)"; 
        $timeframe_sql_no_alias = "YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1)"; 
        break;
    case 'month': 
        $timeframe_sql = "YEAR(o.order_date) = YEAR(CURDATE()) AND MONTH(o.order_date) = MONTH(CURDATE())"; 
        $timeframe_sql_no_alias = "YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE())"; 
        break;
}

// 1. Stats
$completed_products = db_query("
    SELECT COUNT(DISTINCT o.order_id) as count 
    FROM orders o 
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.status = 'Completed' AND o.branch_id = ? AND o.order_type = 'product' AND $timeframe_sql_no_alias
", 'i', [$staffBranchId])[0]['count'] ?? 0;

$completed_custom = db_query("
    SELECT COUNT(DISTINCT o.order_id) as count 
    FROM orders o 
    JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN job_orders jo ON oi.order_item_id = jo.order_item_id
    LEFT JOIN services s ON oi.product_id = s.service_id
    WHERE o.status = 'Completed' AND o.branch_id = ? AND $timeframe_sql_no_alias
      AND (s.service_id IS NOT NULL OR jo.id IS NOT NULL OR o.order_type = 'custom')
", 'i', [$staffBranchId])[0]['count'] ?? 0;

$total_revenue = db_query("SELECT SUM(total_amount) as total FROM orders WHERE $timeframe_sql_no_alias AND status != 'Cancelled' AND branch_id = ?", 'i', [$staffBranchId])[0]['total'] ?? 0;

// 2. Optimized & Dynamic Chart Data
$chart_labels = [];
$chart_values = [];
$chart_title = "Revenue Trend (Last 7 Days)";

$chart_sql_cond = " WHERE o.branch_id = ? AND o.status != 'Cancelled'";
$chart_params = [$staffBranchId];
$chart_types = "i";

if ($status_filter) {
    if ($status_filter === 'Cancelled') {
        $chart_sql_cond = " WHERE o.branch_id = ? AND o.status = 'Cancelled'";
    } else {
        $chart_sql_cond .= " AND o.status = ?";
        $chart_params[] = $status_filter;
        $chart_types .= "s";
    }
}

switch($timeframe) {
    case 'today':
        $chart_title = "Today's Performance (Hourly)";
        for ($i = 0; $i < 24; $i++) {
            $h = str_pad($i, 2, "0", STR_PAD_LEFT);
            $chart_labels[] = $h . ":00";
            $res = db_query("SELECT SUM(o.total_amount) as total FROM orders o $chart_sql_cond AND DATE(o.order_date) = CURDATE() AND HOUR(o.order_date) = ?", $chart_types.'i', array_merge($chart_params, [$i]));
            $chart_values[] = (float)($res[0]['total'] ?? 0);
        }
        break;
        
    case 'week':
        $chart_title = "Weekly Trend (Daily)";
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $chart_labels[] = date('D', strtotime($d));
            $res = db_query("SELECT SUM(o.total_amount) as total FROM orders o $chart_sql_cond AND DATE(o.order_date) = ?", $chart_types.'s', array_merge($chart_params, [$d]));
            $chart_values[] = (float)($res[0]['total'] ?? 0);
        }
        break;
        
    case 'month':
        $chart_title = "Monthly Performance (Daily)";
        $days_in_month = date('t');
        for ($i = 1; $i <= $days_in_month; $i++) {
            $d = date('Y-m-') . str_pad($i, 2, "0", STR_PAD_LEFT);
            $chart_labels[] = $i;
            $res = db_query("SELECT SUM(o.total_amount) as total FROM orders o $chart_sql_cond AND DATE(o.order_date) = ?", $chart_types.'s', array_merge($chart_params, [$d]));
            $chart_values[] = (float)($res[0]['total'] ?? 0);
        }
        break;
        
        break;

    default: // 7 Days
        $chart_title = "Last 7 Days (Trend)";
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $chart_labels[] = date('D', strtotime($d));
            $res = db_query("SELECT SUM(o.total_amount) as total FROM orders o $chart_sql_cond AND DATE(o.order_date) = ?", $chart_types.'s', array_merge($chart_params, [$d]));
            $chart_values[] = (float)($res[0]['total'] ?? 0);
        }
}

// 3. Top Sales (Dynamic Timeframe)
$ts_where = $timeframe_sql;
$top_services = db_query("
    SELECT 
        TRIM(REPLACE(REPLACE(REPLACE(COALESCE(jo.service_type, s.name, p.name), ' Printing', ''), ' (Print/Cut)', ''), ' Print', '')) as name, 
        COUNT(DISTINCT oi.order_item_id) as order_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN job_orders jo ON oi.order_item_id = jo.order_item_id
    LEFT JOIN products p ON (oi.product_id = p.product_id AND o.order_type = 'product')
    LEFT JOIN services s ON ((oi.product_id = s.service_id AND o.order_type = 'custom') OR (jo.service_type = s.name AND s.status = 'Activated'))
    WHERE $ts_where 
      AND o.branch_id = ?
      AND (
          (p.product_id IS NOT NULL AND p.status = 'Activated')
          OR (s.service_id IS NOT NULL AND s.status = 'Activated')
          OR (jo.id IS NOT NULL AND EXISTS (SELECT 1 FROM services WHERE name = jo.service_type AND status = 'Activated'))
      )
    GROUP BY name
    ORDER BY order_count DESC
    LIMIT 10
", 'i', [$staffBranchId]);

// 4. Recent Orders
$sql_cond = " WHERE o.branch_id = ?";
$params = [$staffBranchId];
$types = "i";

if ($status_filter) {
    $sql_cond .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($timeframe !== 'all' && isset($timeframe_sql)) {
    $sql_cond .= " AND " . $timeframe_sql;
}
if ($search_filter) {
    $sql_cond .= " AND (o.order_id LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $lk = "%$search_filter%";
    $params[] = $lk;
    $params[] = $lk;
    $types .= "ss";
}

$total_rows = db_query("SELECT COUNT(*) as count FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id" . $sql_cond, $types, $params)[0]['count'] ?? 0;

$orders = db_query("
    SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    (SELECT COALESCE(p.name, 'Custom Service') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) as service_type,
    o.order_date, o.total_amount, o.status
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.customer_id 
    $sql_cond
    ORDER BY o.order_date DESC 
    LIMIT $limit OFFSET $offset
", $types, $params);

// Add HTML badge to orders for easier rendering on frontend
foreach ($orders as &$order) {
    if (function_exists('status_badge')) {
        $order['status_html'] = status_badge($order['status'], 'order');
    } else {
        $order['status_html'] = '<span class="status-badge">' . $order['status'] . '</span>';
    }
    $order['formatted_date'] = date('M d, Y', strtotime($order['order_date']));
    $order['formatted_total'] = '₱' . number_format($order['total_amount'], 2);
    $order['manage_url'] = "customizations.php?order_id={$order['order_id']}&status=" . urlencode($order['status']) . "&job_type=ORDER";
}

header('Content-Type: application/json');
echo json_encode([
    'stats' => [
        'revenue' => (float)$total_revenue,
        'formatted_revenue' => '₱' . number_format($total_revenue, 2),
        'completed_products' => (int)$completed_products,
        'completed_custom' => (int)$completed_custom
    ],
    'chart' => [
        'labels' => $chart_labels,
        'values' => $chart_values,
        'title' => $chart_title
    ],
    'top_services' => $top_services,
    'orders' => $orders,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => ceil($total_rows / $limit),
        'total_rows' => (int)$total_rows
    ],
    'timeframe_label' => $timeframe === 'today' ? 'Today' : ($timeframe === 'week' ? 'This Week' : 'This Month')
]);
