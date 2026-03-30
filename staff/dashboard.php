<?php
/**
 * Staff Dashboard
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

// Require staff access
require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$staffCtx = init_branch_context();
$staffBranchId = $staffCtx['selected_branch_id'] === 'all' ? (int)($_SESSION['branch_id'] ?? 1) : (int)$staffCtx['selected_branch_id'];
$branch_name = $staffCtx['branch_name'];

// --- 1. SET DATE RANGE & FILTERS ---
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';
$timeframe = $_GET['timeframe'] ?? 'today';

$timeframe_sql = "DATE(o.order_date) = CURDATE()";
$timeframe_label = "Today";
$timeframe_sql_no_alias = "DATE(order_date) = CURDATE()";

switch ($timeframe) {
    case 'week': 
        $timeframe_sql = "YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)"; 
        $timeframe_sql_no_alias = "YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1)"; 
        $timeframe_label = "This Week"; 
        break;
    case 'month': 
        $timeframe_sql = "YEAR(o.order_date) = YEAR(CURDATE()) AND MONTH(o.order_date) = MONTH(CURDATE())"; 
        $timeframe_sql_no_alias = "YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE())"; 
        $timeframe_label = "This Month"; 
        break;
    case 'year': 
        $timeframe_sql = "YEAR(o.order_date) = YEAR(CURDATE())"; 
        $timeframe_sql_no_alias = "YEAR(order_date) = YEAR(CURDATE())"; 
        $timeframe_label = "This Year"; 
        break;
    case 'all': 
        $timeframe_sql = "1=1"; 
        $timeframe_sql_no_alias = "1=1"; 
        $timeframe_label = "All Time"; 
        break;
}

// Get dashboard statistics (scoped to this staff member's branch)
$pending_orders_result = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Pending Review') AND branch_id = ?",
    'i',
    [$staffBranchId]
);
$pending_orders = $pending_orders_result[0]['count'] ?? 0;

$processing_orders_result = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status = 'Processing' AND branch_id = ?",
    'i',
    [$staffBranchId]
);
$processing_orders = $processing_orders_result[0]['count'] ?? 0;

$ready_orders_result = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status = 'Ready for Pickup' AND branch_id = ?",
    'i',
    [$staffBranchId]
);
$ready_orders = $ready_orders_result[0]['count'] ?? 0;

// Get today's completed orders
$today_completed_result = db_query(
    "SELECT COUNT(*) as count FROM orders 
    WHERE status = 'Completed' AND $timeframe_sql_no_alias AND branch_id = ?",
    'i',
    [$staffBranchId]
);
$completed_today = $today_completed_result[0]['count'] ?? 0;

// Total Orders Today (Scoped)
$today_orders_res = db_query("SELECT COUNT(*) as count FROM orders WHERE $timeframe_sql_no_alias AND branch_id = ?", 'i', [$staffBranchId]);
$total_orders_today = $today_orders_res[0]['count'] ?? 0;

// Total Sales Today (Scoped)
$sales_today_res = db_query("SELECT SUM(total_amount) as total FROM orders WHERE $timeframe_sql_no_alias AND status != 'Cancelled' AND branch_id = ?", 'i', [$staffBranchId]);
$total_sales_today = $sales_today_res[0]['total'] ?? 0;

// Sales Overview (Last 7 Days) for Trend Chart (Scoped)
$trend_labels = [];
$trend_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trend_labels[] = date('D', strtotime($date));
    $res = db_query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(order_date) = ? AND status != 'Cancelled' AND branch_id = ?", 'si', [$date, $staffBranchId]);
    $trend_values[] = (float)($res[0]['total'] ?? 0);
}

// Top Services (Last 30 Days) (Scoped)
$top_services = db_query("
    SELECT COALESCE(p.name, 'Custom Product') as name, COUNT(*) as order_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND o.branch_id = ?
    GROUP BY name
    ORDER BY order_count DESC
    LIMIT 5
", 'i', [$staffBranchId]);

// Recent Orders with filters (Scoped)
$sql_cond = " WHERE o.branch_id = ?";
$params = [$staffBranchId];
$types = "i";

if ($status_filter) {
    $sql_cond .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($timeframe !== 'all') {
    $sql_cond .= " AND " . $timeframe_sql;
}
if ($search_filter) {
    $sql_cond .= " AND (o.order_id LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $lk = "%$search_filter%";
    $params[] = $lk;
    $params[] = $lk;
    $types .= "ss";
}

// Fetch recent orders
$total_rows_res = db_query("SELECT COUNT(*) as count FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id" . $sql_cond, $types, $params);
$total_rows = $total_rows_res[0]['count'] ?? 0;
$total_pages = ceil($total_rows / $limit);

$recent_orders = db_query("
    SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    (SELECT COALESCE(p.name, 'Custom Service') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) as service_type
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.customer_id 
    $sql_cond
    ORDER BY o.order_date DESC 
    LIMIT $limit OFFSET $offset
", $types, $params);

// Get low-stock products (view-only)
$low_stock = db_query("
    SELECT name, sku, stock_quantity, category 
    FROM products 
    WHERE status = 'Activated' AND stock_quantity < 10 
    ORDER BY stock_quantity ASC LIMIT 5
");

$page_title = 'Staff Dashboard - PrintFlow';

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
        /* Full-Width Executive Layout Extensions */
        .chart-wrap { position: relative; height: 350px; width: 100%; margin-top: 10px; }
        
        .service-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 12px; }
        .service-item:last-child { border-bottom: none; }
        .service-info { font-size: 13px; font-weight: 700; color: #1e293b; flex: 1; min-width: 0; }
        .service-count { font-size: 12px; font-weight: 800; color: var(--staff-primary); background: #e6f6f6; padding: 4px 10px; border-radius: 20px; white-space: nowrap; }

        .filter-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .filter-bar .input-field, .filter-bar select { width: auto; min-width: 140px; height: 34px !important; font-size: 12px; }

        /* Ultra-Fluid KPI Container */
        .kpi-premium-container {
            background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(6, 161, 161, 0.1);
        }
        .kpi-bg-shape {
            position: absolute;
            background: linear-gradient(135deg, rgba(6, 161, 161, 0.1), rgba(6, 161, 161, 0.05));
            border-radius: 50%;
            pointer-events: none;
            filter: blur(50px);
            z-index: 1;
        }
        .shape-1 { width: 400px; height: 400px; top: -150px; right: -50px; animation: float 18s infinite alternate; }
        .shape-2 { width: 300px; height: 300px; bottom: -80px; left: -80px; animation: float 15s infinite alternate-reverse; }

        .kpi-header { 
            position: relative; 
            z-index: 2; 
            margin-bottom: 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
        }
        .kpi-title-group { flex: 1; min-width: 300px; }
        .kpi-title { font-size: 24px; font-weight: 900; color: #013a3a; margin: 0; letter-spacing: -0.02em; }
        .kpi-subtitle { font-size: 12px; color: #064e3b; margin: 2px 0 0; opacity: 0.6; font-weight: 500; }

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            position: relative;
            z-index: 2;
        }
        @media (max-width: 1200px) { .kpi-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .kpi-row { grid-template-columns: 1fr; } }

        .kpi-card-v2 {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 20px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .kpi-card-v2:hover { 
            transform: translateY(-4px); 
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 15px 30px -10px rgba(6, 161, 161, 0.12); 
            border-color: #06A1A1;
        }
        .kpi-v2-value { 
            font-size: 26px; 
            font-weight: 900; 
            color: #013a3a; 
            line-height: 1; 
            margin-bottom: 8px; 
            letter-spacing: -0.03em;
        }
        .kpi-v2-label { 
            font-size: 10px; 
            font-weight: 800; 
            color: #06A1A1; 
            text-transform: uppercase; 
            letter-spacing: 0.08em;
            opacity: 0.8;
        }
        .kpi-v2-sub { 
            font-size: 10px; 
            color: #475569; 
            margin-top: 4px; 
            font-weight: 600;
            opacity: 0.5;
        }
        .kpi-card-indicator { position: absolute; top: 10px; right: 16px; width: 24px; height: 3px; border-radius: 2px; opacity: 0.3; }

        /* Full Width Card Adjustments */
        .card { padding: 20px; border-radius: 16px; margin-bottom: 16px; height: 100%; border: 1px solid #f1f5f9; position: relative; }
        .grid-cols-3 { display: grid; grid-template-columns: 7fr 3fr; gap: 16px; width: 100%; }
        @media (max-width: 1024px) { .grid-cols-3 { grid-template-columns: 1fr; } }

        /* Real-time Loading Transitions: Modern & Responsive */
        .loading-progress {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(to right, transparent, #06A1A1, transparent);
            background-size: 200% 100%;
            animation: loadingMove 1.5s linear infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 20;
            border-radius: 16px 16px 0 0;
        }
        .is-loading .loading-progress { opacity: 1; }
        
        @keyframes loadingMove {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .content-transition { transition: opacity 0.3s ease; }
        .is-loading .content-transition { opacity: 0.6; }

        /* KPI Pulse Effect */
        @keyframes kpiPulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(0.98); opacity: 0.6; }
            100% { transform: scale(1); opacity: 1; }
        }
        .metric-pulse { animation: kpiPulse 0.4s ease-in-out; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content" style="padding: 12px 12px 0;">
        <main style="max-width: 100%;" id="dashboard-main">
            <!-- PREMIUM KPI SECTION (Fluid Layout) -->
            <div class="kpi-premium-container">
                <div class="kpi-bg-shape shape-1"></div>
                <div class="kpi-bg-shape shape-2"></div>
                
                <div class="kpi-header">
                    <div class="kpi-title-group">
                        <h2 class="kpi-title">Operations Distribution</h2>
                        <p class="kpi-subtitle" id="kpi-subtitle">Metrics for <?php echo htmlspecialchars($timeframe_label); ?> at <?php echo htmlspecialchars($branch_name); ?></p>
                    </div>

                    <form method="GET" class="filter-bar" id="filter-form">
                        <select name="status" id="filter-status" class="input-field">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <select name="timeframe" id="filter-timeframe" class="input-field">
                            <option value="today" <?php echo $timeframe === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $timeframe === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $timeframe === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                        <a href="pos.php" class="btn-primary" style="text-decoration: none; height: 34px; display: flex; align-items: center; padding: 0 16px; font-size: 13px;">
                            POS (Walk-in)
                        </a>
                    </form>
                </div>

                <div class="kpi-row content-transition">
                    <div class="kpi-card-v2">
                        <div class="kpi-card-indicator" style="background: #06A1A1;"></div>
                        <div class="kpi-v2-value" id="stat-revenue" style="color: #06A1A1;">₱<?php echo number_format($total_sales_today, 2); ?></div>
                        <div class="kpi-v2-label">Total Revenue</div>
                        <div class="kpi-v2-sub">Gross branch income</div>
                    </div>
                    <div class="kpi-card-v2">
                        <div class="kpi-card-indicator" style="background: #064e3b;"></div>
                        <div class="kpi-v2-value" id="stat-total-orders"><?php echo $total_orders_today; ?></div>
                        <div class="kpi-v2-label">Total Orders</div>
                        <div class="kpi-v2-sub">Requests processed</div>
                    </div>
                    <div class="kpi-card-v2">
                        <div class="kpi-card-indicator" style="background: #d97706;"></div>
                        <div class="kpi-v2-value" id="stat-pending" style="color: #d97706;"><?php echo $pending_orders; ?></div>
                        <div class="kpi-v2-label">Pending</div>
                        <div class="kpi-v2-sub">Active reviews</div>
                    </div>
                    <div class="kpi-card-v2">
                        <div class="kpi-card-indicator" style="background: #059669;"></div>
                        <div class="kpi-v2-value" id="stat-completed" style="color: #059669;"><?php echo $completed_today; ?></div>
                        <div class="kpi-v2-label">Completed</div>
                        <div class="kpi-v2-sub">Finished today</div>
                    </div>
                </div>
            </div>
            <!-- SALES & TOP SERVICES ROW -->
            <div class="grid-cols-3" style="margin-bottom: 16px;">
                <div class="card">
                    <div class="loading-progress"></div>
                    <div class="content-transition">
                        <div id="chart-title" style="font-size: 16px; font-weight: 700; color: #013a3a; margin-bottom: 16px;">Sales Trend (7 Days)</div>
                        <div class="chart-wrap" id="chart-pulse-wrap">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="loading-progress"></div>
                    <div class="content-transition">
                        <div style="font-size: 16px; font-weight: 700; color: #013a3a; margin-bottom: 16px;">Top Services (30 Days)</div>
                        <div style="margin-top: 10px;" id="top-services-list">
                            <?php if (!empty($top_services)): ?>
                                <?php foreach ($top_services as $service): ?>
                                    <div class="service-item">
                                        <span class="service-info"><?php echo htmlspecialchars($service['name']); ?></span>
                                        <span class="service-count"><?php echo $service['order_count']; ?> Orders</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #94a3b8; font-size: 14px; text-align: center; padding: 20px;">No data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECENT ORDERS SECTION -->
            <div class="card">
                <div class="loading-progress"></div>
                <div class="content-transition">
                    <div style="font-size: 16px; font-weight: 700; color: #013a3a; margin-bottom: 16px;">Recent Orders Activity</div>
                    
                    <div class="table-responsive">
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer Name</th>
                                    <th>Service Type</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="recent-orders-tbody">
                                <?php if (!empty($recent_orders)): ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td style="font-weight:700;">#<?php echo $order['order_id']; ?></td>
                                            <td style="font-weight:600;"><?php echo htmlspecialchars($order['customer_name'] ?: 'Guest'); ?></td>
                                            <td style="font-weight:500;"><?php echo htmlspecialchars($order['service_type'] ?: 'General'); ?></td>
                                            <td style="color:#64748b; font-size:13px;"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td style="font-weight:800; color:#013a3a;">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                             <td>
                                                 <?php echo status_badge($order['status'], 'order'); ?>
                                             </td>
                                            <td style="text-align:right;">
                                                <a href="customizations.php?order_id=<?php echo $order['order_id']; ?>&status=<?php echo urlencode($order['status']); ?>&job_type=ORDER" class="btn-staff-action btn-staff-action-blue">Manage</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">No orders found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="paginationWrapper">
                        <?php 
                        $pp = array_filter(['status'=>$status_filter, 'timeframe'=>$timeframe, 'search'=>$search_filter], function($v) { return $v !== null && $v !== ''; });
                        echo render_pagination($page, $total_pages, $pp); 
                        ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div><script>
var salesChartInstance = null;
var dashAbortController = null;

async function refreshDashboard(page = 1) {
    const main = document.getElementById('dashboard-main');
    if (!main) return;

    // 1. Cancel previous pending request if it exists
    if (dashAbortController) dashAbortController.abort();
    dashAbortController = new AbortController();

    const statusEl = document.getElementById('filter-status');
    const timeframeEl = document.getElementById('filter-timeframe');
    if (!statusEl || !timeframeEl) return;

    // 2. Visual Feedback (Immediate)
    main.classList.add('is-loading');
    
    try {
        const response = await fetch(`api_dashboard_stats.php?page=${page}&status=${encodeURIComponent(statusEl.value)}&timeframe=${encodeURIComponent(timeframeEl.value)}`, {
            signal: dashAbortController.signal
        });
        if (!response.ok) throw new Error('Refresh failed');
        const data = await response.json();
        
        if (data.error) throw new Error(data.error);

        // 3. Update DOM with micro-animations
        updateMetric('stat-revenue', data.stats.formatted_revenue || '₱0.00');
        updateMetric('stat-total-orders', data.stats.total_orders);
        updateMetric('stat-pending', data.stats.pending);
        updateMetric('stat-completed', data.stats.completed);
        
        const subtitleEl = document.getElementById('kpi-subtitle');
        if (subtitleEl) subtitleEl.textContent = `Metrics for ${data.timeframe_label} at <?php echo addslashes($branch_name); ?>`;

        // Top Services list update
        const servicesList = document.getElementById('top-services-list');
        if (servicesList) {
            servicesList.style.opacity = '0';
            setTimeout(() => {
                if (data.top_services && data.top_services.length > 0) {
                    servicesList.innerHTML = data.top_services.map(s => `
                        <div class="service-item">
                            <span class="service-info">${s.name}</span>
                            <span class="service-count">${s.order_count} Orders</span>
                        </div>
                    `).join('');
                } else {
                    servicesList.innerHTML = '<p style="color: #94a3b8; font-size: 14px; text-align: center; padding: 20px;">No data available.</p>';
                }
                servicesList.style.opacity = '1';
            }, 100);
        }

        // Table update
        const tbody = document.getElementById('recent-orders-tbody');
        if (tbody) {
            if (data.orders && data.orders.length > 0) {
                tbody.innerHTML = data.orders.map(o => `
                    <tr>
                        <td style="font-weight:700;">#${o.order_id}</td>
                        <td style="font-weight:600;">${o.customer_name || 'Guest'}</td>
                        <td style="font-weight:500;">${o.service_type || 'General'}</td>
                        <td style="color:#64748b; font-size:13px;">${o.formatted_date}</td>
                        <td style="font-weight:800; color:#013a3a;">${o.formatted_total}</td>
                        <td>${o.status_html}</td>
                        <td style="text-align:right;">
                            <a href="${o.manage_url}" class="btn-staff-action btn-staff-action-blue">Manage</a>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">No orders found.</td></tr>';
            }
        }

        // 4. Update Chart (Fluidly without destroy)
        if (data.chart && data.chart.labels) {
            const ctEl = document.getElementById('chart-title');
            if (ctEl && data.chart.title) ctEl.textContent = data.chart.title;
            updateSalesChart(data.chart.labels, data.chart.values);
        }
        
        // 5. Update Pagination
        const pagWrapper = document.getElementById('paginationWrapper');
        if (pagWrapper && data.pagination.total_pages > 1) {
            let pagHtml = '<div style="display:flex; justify-content:center; gap:4px; margin-top:20px;">';
            for (let i = 1; i <= data.pagination.total_pages; i++) {
                const active = i === data.pagination.current_page ? 'background:#06A1A1; color:#fff; border-color:#06A1A1;' : 'background:#fff; color:#64748b; border-color:#e2e8f0;';
                pagHtml += `<button onclick="refreshDashboard(${i})" style="width:32px; height:32px; border:1px solid; border-radius:8px; cursor:pointer; font-weight:700; font-size:12px; transition:all 0.2s; ${active}">${i}</button>`;
            }
            pagHtml += '</div>';
            pagWrapper.innerHTML = pagHtml;
        } else if (pagWrapper) {
            pagWrapper.innerHTML = '';
        }

    } catch (err) {
        if (err.name !== 'AbortError') console.error('Dashboard error:', err);
    } finally {
        setTimeout(() => main.classList.remove('is-loading'), 300);
    }
}

function updateMetric(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.textContent === value.toString()) return;
    
    // Smooth transition
    el.classList.add('metric-pulse');
    setTimeout(() => {
        el.textContent = value;
        setTimeout(() => el.classList.remove('metric-pulse'), 400);
    }, 150);
}

function updateSalesChart(labels, values) {
    const canvas = document.getElementById('salesChart');
    if (!canvas) return;

    if (salesChartInstance) {
        // Fluid transition: update data instead of destroy
        salesChartInstance.data.labels = labels;
        salesChartInstance.data.datasets[0].data = values;
        salesChartInstance.update();
        return;
    }

    const ctx = canvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(6, 161, 161, 0.2)');
    gradient.addColorStop(1, 'rgba(6, 161, 161, 0.05)');

    salesChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Gross Revenue',
                data: values,
                borderColor: '#06A1A1',
                borderWidth: 3,
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#06A1A1',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 800 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#013a3a',
                    callbacks: { label: (ctx) => ` ₱${ctx.parsed.y.toLocaleString()}` }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)', borderDash: [4, 4] },
                    ticks: {
                        callback: (v) => '₱' + v.toLocaleString(),
                        font: { size: 10, weight: '600' }
                    }
                },
                x: { grid: { display: false }, ticks: { font: { size: 10, weight: '600' } } }
            }
        }
    });
}

function initDashboardInteractions() {
    updateSalesChart(<?php echo json_encode($trend_labels); ?>, <?php echo json_encode($trend_values); ?>);
    
    const sEl = document.getElementById('filter-status');
    const tEl = document.getElementById('filter-timeframe');
    if (sEl) sEl.addEventListener('change', () => refreshDashboard(1));
    if (tEl) tEl.addEventListener('change', () => refreshDashboard(1));
    
    const fForm = document.getElementById('filter-form');
    if (fForm) fForm.addEventListener('submit', (e) => { e.preventDefault(); refreshDashboard(1); });
}

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initDashboardInteractions();
} else {
    document.addEventListener('DOMContentLoaded', initDashboardInteractions);
}
document.addEventListener('turbo:load', initDashboardInteractions);
</script>

</body>
</html>

