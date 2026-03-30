<?php
/**
 * Staff Reports - Visual Analytics Dashboard
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$staffBranchId = printflow_branch_filter_for_user() ?? (int)($_SESSION['branch_id'] ?? 1);
$range = $_GET['range'] ?? 'week';
$report_date = $_GET['date'] ?? date('Y-m-d');

// Define parameters based on the selected range
if ($range === 'year') {
    $interval_label = 'Last 12 Months';
    $group_by = "DATE_FORMAT(order_date, '%Y-%m')"; // 2023-05
    $sql_interval = '11 MONTH';
} elseif ($range === 'month') {
    $interval_label = 'Last 30 Days';
    $group_by = "DATE(order_date)";
    $sql_interval = '29 DAY';
} else {
    $range = 'week';
    $interval_label = 'Last 7 Days';
    $group_by = "DATE(order_date)";
    $sql_interval = '6 DAY';
}

$status_filter = $_GET['status'] ?? 'ALL';
$status_where = "";
$status_p = [];
$status_t = "";
if ($status_filter !== 'ALL' && !empty($status_filter)) {
    $status_where = " AND status = ? ";
    $status_p = [$status_filter];
    $status_t = "s";
}

// ---- 1. RANGE-AWARE KPI METRICS (DYNAMIC) ----
// Total revenue for THE SELECTED PERIOD (Paid only)
$rev_res = db_query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL $sql_interval) AND payment_status = 'Paid' AND branch_id = ? $status_where", ($status_t ? "i" . $status_t : "i"), array_merge([$staffBranchId], $status_p));
$period_revenue = $rev_res[0]['total'] ?? 0;

// Total orders count for THE SELECTED PERIOD
$ord_res = db_query("SELECT COUNT(*) as count FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL $sql_interval) AND branch_id = ? $status_where", ($status_t ? "i" . $status_t : "i"), array_merge([$staffBranchId], $status_p));
$period_orders = $ord_res[0]['count'] ?? 0;

// Pending/Active orders received in THE SELECTED PERIOD (if status is not filtered specifically)
$active_statuses_sql = "status IN ('Pending', 'Pending Review', 'Pending Verification', 'Approved', 'Downpayment Submitted', 'In Production')";
if ($status_filter !== 'ALL') {
    $pend_res = db_query("SELECT COUNT(*) as count FROM orders WHERE status = ? AND branch_id = ? AND order_date >= DATE_SUB(CURDATE(), INTERVAL $sql_interval)", 'si', [$status_filter, $staffBranchId]);
} else {
    $pend_res = db_query("SELECT COUNT(*) as count FROM orders WHERE $active_statuses_sql AND branch_id = ? AND order_date >= DATE_SUB(CURDATE(), INTERVAL $sql_interval)", 'i', [$staffBranchId]);
}
$pending_period_orders = $pend_res[0]['count'] ?? 0;

// GLOBAL Backlog (All pending/active orders ever)
$global_back_res = db_query("SELECT COUNT(*) as count FROM orders WHERE status NOT IN ('Completed', 'Cancelled') AND branch_id = ? $status_where", ($status_t ? "i" . $status_t : "i"), array_merge([$staffBranchId], $status_p));
$global_backlog = $global_back_res[0]['count'] ?? 0;

// Low stock finished goods alert (This is ALWAYS current status)
$stock_res = db_query("SELECT COUNT(*) as count FROM products WHERE status = 'Activated' AND stock_quantity < 20");
$low_stock_count = $stock_res[0]['count'] ?? 0;

// ---- 2. REVENUE TREND (DYNAMIC) ----
$trend_res = db_query("
    SELECT $group_by as dte, COALESCE(SUM(total_amount), 0) as daily_total 
    FROM orders 
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL $sql_interval) AND branch_id = ?
    $status_where
    GROUP BY dte
    ORDER BY dte ASC
", ($status_t ? "i" . $status_t : "i"), array_merge([$staffBranchId], $status_p));

// Fill empty data points so the chart doesn't break
$trend_dates = [];
$trend_totals = [];

if ($range === 'year') {
    for ($i = 11; $i >= 0; $i--) {
        $date_str = date('Y-m', strtotime("-$i months"));
        $trend_dates[] = date('M Y', strtotime($date_str . '-01'));
        
        $found = 0;
        foreach ($trend_res as $r) { if ($r['dte'] === $date_str) { $found = (float)$r['daily_total']; break; } }
        $trend_totals[] = $found;
    }
} elseif ($range === 'month') {
    for ($i = 29; $i >= 0; $i--) {
        $date_str = date('Y-m-d', strtotime("-$i days"));
        $trend_dates[] = date('M d', strtotime($date_str));
        
        $found = 0;
        foreach ($trend_res as $r) { if ($r['dte'] === $date_str) { $found = (float)$r['daily_total']; break; } }
        $trend_totals[] = $found;
    }
} else {
    for ($i = 6; $i >= 0; $i--) {
        $date_str = date('Y-m-d', strtotime("-$i days"));
        $trend_dates[] = date('M d', strtotime($date_str));
        
        $found = 0;
        foreach ($trend_res as $r) { if ($r['dte'] === $date_str) { $found = (float)$r['daily_total']; break; } }
        $trend_totals[] = $found;
    }
}

// ---- 3. ORDER STATUS DISTRIBUTION (FIXED LABELS) ----
$std_statuses = [
    'Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled',
    'Pending Review', 'Approved', 'Downpayment Submitted', 'To Pay'
];

$status_res = db_query("
    SELECT status, COUNT(*) as status_count 
    FROM orders 
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL $sql_interval) AND branch_id = ?
    GROUP BY status
", 'i', [$staffBranchId]);

$status_map = [];
foreach ($status_res as $s) {
    if ($s['status']) $status_map[$s['status']] = (int)$s['status_count'];
}

$status_labels = $std_statuses;
$status_counts = array_map(fn($s) => $status_map[$s] ?? 0, $std_statuses);

// Use 'No Data Yet' only if EVERYTHING is zero across the period
if (array_sum($status_counts) === 0) {
    $status_labels = ['No Data Yet'];
    $status_counts = [1];
}

// ---- 4. TOP 5 BEST SELLING PRODUCTS (DYNAMIC) ----
$top_products = db_query("
    SELECT p.name, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL $sql_interval) AND o.branch_id = ?
    GROUP BY oi.product_id
    ORDER BY total_sold DESC
    LIMIT 5
", 'i', [$staffBranchId]);

$page_title = 'Visual Reports & Analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .rpt-kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        @media (max-width: 1024px) { .rpt-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .rpt-kpi-grid { grid-template-columns: 1fr; } }

        .kpi-box { 
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .kpi-box:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        
        .kpi-icon-wrap { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .kpi-green { background: #dcfce7; color: #15803d; }
        .kpi-blue { background: #dbeafe; color: #1d4ed8; }
        .kpi-amber { background: #fef3c7; color: #b45309; }
        .kpi-red { background: #fee2e2; color: #b91c1c; }

        .kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 2px; letter-spacing: 0.05em; display: block; }
        .kpi-value { 
            font-size: clamp(18px, 4vw, 24px); 
            font-weight: 800; 
            color: #0f172a; 
            line-height: 1.2; 
            display: block; 
            white-space: nowrap !important; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* 2/3 width for primary chart, 1/3 for secondary */
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } }

        /* Split Export Button Styles */
        .btn-excel-split { transition: transform 0.2s; }
        .btn-excel-split:hover { transform: translateY(-1px); }
        .btn-excel-split:hover span:first-child { background: #1f2937 !important; }
        .btn-excel-split:hover span:last-child { background: #058f8f !important; }
        
        .chart-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .chart-title { font-size: 16px; font-weight: 800; color: #0f172a; }
        .chart-subtitle { font-size: 13px; color: #64748b; font-weight: 600; }
        
        .chart-container-large { position: relative; height: 350px; width: 100%; }
        .chart-container-small { position: relative; height: 380px; width: 100%; padding-bottom: 20px; }

        /* Top Products List Styling */
        .top-product-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .top-product-row:last-child { border-bottom: none; }
        .tp-name { font-size: 14px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .tp-rank { width: 24px; height: 24px; background: #f1f5f9; color: #475569; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; }
        .tp-sold { font-size: 14px; font-weight: 800; color: #059669; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content">
        <header style="margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: flex-end;">
            <div style="flex: 1; min-width: 300px;">
                <h1 class="page-title">Visual Reports & Analytics</h1>
                <p style="color:#64748b; font-size:14px; margin-top:4px;">A quick overview of business performance and metrics.</p>
            </div>
            
            <div style="display: flex; gap: 8px; align-items: center; justify-content: flex-end;">
                <!-- Status Filter -->
                <select id="report-status-select" name="status" onchange="window.location.href='?range=<?php echo $range; ?>&status=' + this.value" style="width: 150px; padding: 7px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #334155; cursor: pointer; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <option value="ALL">All Statuses</option>
                    <?php 
                    $all_opts = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled', 'Pending Review', 'Approved', 'Downpayment Submitted', 'To Pay'];
                    foreach($all_opts as $opt): 
                        $sel = (isset($_GET['status']) && $_GET['status'] === $opt) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Range Selector -->
                <select id="report-range-select" name="range" onchange="window.location.href='?status=<?php echo $_GET['status'] ?? 'ALL'; ?>&range=' + this.value" style="width: 140px; padding: 7px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #334155; cursor: pointer; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <option value="week" <?php echo $range === 'week' ? 'selected' : ''; ?>>7 Days</option>
                    <option value="month" <?php echo $range === 'month' ? 'selected' : ''; ?>>30 Days</option>
                    <option value="year" <?php echo $range === 'year' ? 'selected' : ''; ?>>12 Months</option>
                </select>

                <!-- Export Button -->
                <a href="export_reports.php?range=<?php echo $range; ?>&status=<?php echo $_GET['status'] ?? 'ALL'; ?>" 
                   style="height: 34px; display: inline-flex; align-items: stretch; border-radius: 8px; overflow: hidden; text-decoration: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #374151; flex-shrink: 0;"
                   class="btn-excel-split">
                   <span style="background: #374151; color: #fff; padding: 0 14px; font-size: 11px; font-weight: 900; display: flex; align-items: center; letter-spacing: 0.1em;">EXPORT</span>
                   <span style="background: #06A1A1; padding: 0 10px; display: flex; align-items: center; justify-content: center; border-left: 1px solid rgba(255,255,255,0.1);">
                       <svg width="14" height="14" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                           <path d="M12 5v14M19 12l-7 7-7-7"></path>
                       </svg>
                   </span>
                </a>
            </div>
        </header>

        <main>
            <!-- ROW 1: QUICK PERFORMANCE METRICS -->
            <div class="rpt-kpi-grid">
                <div class="kpi-box">
                    <div class="kpi-icon-wrap kpi-green"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php echo $interval_label; ?> Revenue</span>
                        <span class="kpi-value" style="display:block; margin-top:2px;"><?php echo format_currency($period_revenue); ?></span>
                    </div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-icon-wrap kpi-blue"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg></div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php echo $interval_label; ?> Orders</span>
                        <span class="kpi-value" style="display:block; margin-top:2px;"><?php echo $period_orders; ?> <small style="font-size:14px;color:#94a3b8;">orders</small></span>
                    </div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-icon-wrap kpi-amber"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                    <div class="kpi-content">
                        <span class="kpi-label">Active Jobs</span>
                        <span class="kpi-value" style="display:block; margin-top:2px;"><?php echo $pending_period_orders; ?></span>
                        <div style="font-size:10px; color:#64748b; font-weight:700; margin-top:4px;">(<?php echo $global_backlog; ?> total backlog)</div>
                    </div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-icon-wrap kpi-red"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div>
                    <div class="kpi-content">
                        <span class="kpi-label">Low Stock</span>
                        <span class="kpi-value" style="display:block; margin-top:2px;"><?php echo $low_stock_count; ?> <small style="font-size:14px;color:#94a3b8;">items</small></span>
                    </div>
                </div>
            </div>

            <!-- ROW 2: VISUAL CHARTS -->
            <div class="dashboard-grid">
                
                <!-- Main Line Chart: Revenue Trend -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">Revenue Trend</div>
                            <div class="chart-subtitle">
                                <?php 
                                    if ($range === 'year') echo "Income over the past 12 months";
                                    elseif ($range === 'month') echo "Income over the past 30 days";
                                    else echo "Income over the past 7 days"; 
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container-large">
                        <canvas id="revenueLineChart"></canvas>
                    </div>
                </div>

                <!-- Secondary Chart: Order Status Doughnut -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">Order Status</div>
                            <div class="chart-subtitle">
                                <?php 
                                    if ($range === 'year') echo "Distribution over the past 12 months";
                                    elseif ($range === 'month') echo "Distribution over the past 30 days";
                                    else echo "Distribution over the past 7 days"; 
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container-small">
                        <canvas id="statusDoughnutChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- ROW 3: LISTS & INSIGHTS -->
            <div class="dashboard-grid">
                
                <!-- Top Selling Products -->
                <div class="chart-card">
                    <div class="chart-header" style="margin-bottom:12px;">
                        <div>
                            <div class="chart-title">Top Selling Products</div>
                            <div class="chart-subtitle">
                                <?php 
                                    if ($range === 'year') echo "Most popular items ordered in the last 12 months";
                                    elseif ($range === 'month') echo "Most popular items ordered in the last 30 days";
                                    else echo "Most popular items ordered in the last 7 days"; 
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($top_products)): ?>
                        <?php $rank = 1; foreach ($top_products as $tp): ?>
                        <div class="top-product-row">
                            <div class="tp-name">
                                <span class="tp-rank">#<?php echo $rank++; ?></span>
                                <?php echo htmlspecialchars($tp['name']); ?>
                            </div>
                            <div class="tp-sold">
                                <?php echo $tp['total_sold']; ?> <span style="font-size:12px;color:#64748b;font-weight:600;">sold</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 14px;">No products sold in the last 30 days.</div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- ==========================================
     INITIALIZE CHART.JS VISUALIZATIONS 
=========================================== -->
<script>
/**
 * Global variables to store chart instances.
 * Using 'var' to prevent SyntaxError: Identifier '...' has already been declared
 * when Turbo re-executes this script on navigation.
 */
var revenueChartInstance = null;
var statusChartInstance = null;

function renderReportsCharts() {
    // ⚙️ Global Chart.js Defaults
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.color = "#64748b";

    // --- 1. REVENUE LINE CHART ---
    const revCanvas = document.getElementById('revenueLineChart');
    if (revCanvas) {
        const revCtx = revCanvas.getContext('2d');
        if (revenueChartInstance && typeof revenueChartInstance.destroy === 'function') {
            revenueChartInstance.destroy();
        }

        const gradient = revCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(6, 161, 161, 0.4)');
        gradient.addColorStop(1, 'rgba(6, 161, 161, 0.0)');

        revenueChartInstance = new Chart(revCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_dates); ?>,
                datasets: [{
                    label: 'Total Revenue (₱)',
                    data: <?php echo json_encode($trend_totals); ?>,
                    borderColor: '#06A1A1',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#06A1A1',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        titleFont: { size: 13 },
                        bodyFont: { size: 14, weight: 'bold' },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '₱ ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9', drawBorder: false },
                        ticks: {
                            callback: function(value) { return '₱' + value.toLocaleString(); }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false }
                    }
                }
            }
        });
    }

    // --- 2. ORDER STATUS DOUGHNUT CHART ---
    const statusCanvas = document.getElementById('statusDoughnutChart');
    if (statusCanvas) {
        const statusCtx = statusCanvas.getContext('2d');
        if (statusChartInstance && typeof statusChartInstance.destroy === 'function') {
            statusChartInstance.destroy();
        }

        const statusColors = {
            'Pending': '#fef08a',
            'Pending Review': '#fde047',
            'Approved': '#86efac',
            'Downpayment Submitted': '#67e8f9',
            'Processing': '#3b82f6',
            'In Production': '#2563eb',
            'Ready for Pickup': '#a855f7',
            'Completed': '#22c55e',
            'Cancelled': '#ef4444',
            'No Data Yet': '#e2e8f0'
        };

        const rawLabels = <?php echo json_encode($status_labels); ?>;
        const rawData = <?php echo json_encode($status_counts); ?>;
        const bgColors = rawLabels.map(label => statusColors[label] || '#94a3b8');

        statusChartInstance = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: rawLabels,
                datasets: [{
                    data: rawData,
                    backgroundColor: bgColors,
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
                options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 10,
                        right: 10
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 16,
                            font: { size: 12, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return ' ' + (context.parsed || 0) + ' Orders';
                            }
                        }
                    }
                }
            }
        });
    }
}

// Initial Load + Turbo Navigation
if (typeof renderReportsChartsScheduled === 'undefined') {
    var renderReportsChartsScheduled = true;
    document.addEventListener('DOMContentLoaded', renderReportsCharts);
    window.addEventListener('turbo:load', renderReportsCharts);
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (!revenueChartInstance && !statusChartInstance)) {
            renderReportsCharts();
        }
    });
} else {
    // If script re-runs via Turbo, just run the direct call
    renderReportsCharts();
}
</script>

</body>
</html>
