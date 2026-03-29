<?php
/**
 * Admin Orders Management
 * PrintFlow - Printing Shop PWA  
 * Full CRUD for orders with status updates, filtering, and search (branch-aware)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/branch_ui.php';

require_role(['Admin', 'Manager']);

$current_user = get_logged_in_user();

// ── Branch Context (operational page) ─────────────────
$branchCtx = init_branch_context(false); // Admin: may use All; Manager/Staff locked in branch_context.php
$branchId  = $branchCtx['selected_branch_id'];

// Get filter parameters
$status_filter  = $_GET['status']   ?? '';
$search         = $_GET['search']   ?? '';
$date_from      = $_GET['date_from'] ?? '';
$date_to        = $_GET['date_to']   ?? '';
$sort_by        = $_GET['sort']      ?? 'newest';
$branch_filter  = '';
if ($branchId !== 'all') {
    $branch_filter = (int)$branchId;
}
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Build query (always join branches)
$sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email as customer_email, b.branch_name,
               GROUP_CONCAT(DISTINCT p.sku ORDER BY p.sku SEPARATOR '-') as order_sku
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.customer_id 
        LEFT JOIN branches b ON o.branch_id = b.id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE 1=1";
$params = [];
$types = '';

// ── Branch filter ──────────────────────────────────
if ($branch_filter !== '') {
    $sql .= " AND o.branch_id = ?";
    $params[] = $branch_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $sql .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $sql .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $sql .= " AND (o.order_id LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR o.notes LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sssss';
}

// Count total results - wrap the grouped query as subquery
$count_sql = "SELECT COUNT(*) as total FROM (
    SELECT o.order_id
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.customer_id 
    LEFT JOIN branches b ON o.branch_id = b.id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE 1=1";

if ($branch_filter !== '') {
    $count_sql .= " AND o.branch_id = " . (int)$branch_filter;
}

if (!empty($status_filter)) {
    $count_sql .= " AND o.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if (!empty($date_from)) {
    $count_sql .= " AND DATE(o.order_date) >= '" . $conn->real_escape_string($date_from) . "'";
}

if (!empty($date_to)) {
    $count_sql .= " AND DATE(o.order_date) <= '" . $conn->real_escape_string($date_to) . "'";
}

if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $count_sql .= " AND (o.order_id LIKE '%{$search_term}%' OR c.first_name LIKE '%{$search_term}%' OR c.last_name LIKE '%{$search_term}%' OR CONCAT(c.first_name, ' ', c.last_name) LIKE '%{$search_term}%' OR o.notes LIKE '%{$search_term}%')";
}

$count_sql .= " GROUP BY o.order_id
) as count_wrap";

$total_orders = db_query($count_sql)[0]['total'];
$total_pages = max(1, ceil($total_orders / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sort_clause = match($sort_by) {
    'oldest'        => " ORDER BY o.order_date ASC",
    'az'            => " ORDER BY customer_name ASC",
    'za'            => " ORDER BY customer_name DESC",
    default         => " ORDER BY o.order_date DESC"
};
$sql .= " GROUP BY o.order_id" . $sort_clause . " LIMIT $per_page OFFSET $offset";

$orders = db_query($sql, $types, $params);

// Get statistics (branch-aware)
[$bSqlFrag, $bT, $bP] = branch_where_parts('o', $branchId);

$total_count      = db_query("SELECT COUNT(*) as count FROM orders o WHERE 1=1 {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;
$pending_count    = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Pending' {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;
$processing_count = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Processing' {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;
$ready_count      = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Ready for Pickup' {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;
$completed_count  = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Completed' {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;

$page_title = 'Orders Management - Admin';

// AJAX Partial Response
if (isset($_GET['ajax'])) {
    ob_start();
    ?>
    <table class="orders-table">
        <thead>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <th style="width:12%;white-space:nowrap;">Order #</th>
                <th style="text-align:left;width:18%;">Customer</th>
                <th style="width:15%;">Date</th>
                <th style="width:12%;">Branch</th>
                <th style="width:12%;">Amount</th>
                <th style="width:12%;">Status</th>
                <th style="width:1%; text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody id="ordersTableBody">
            <?php if (empty($orders)): ?>
                <tr id="emptyOrdersRow">
                    <td colspan="7" style="padding:40px; text-align:center; color:#9ca3af; font-size:14px; cursor:default;">
                        <?php echo $search ? 'No orders found matching "' . htmlspecialchars($search) . '"' : 'No orders found'; ?>
                    </td>
                </tr>
            <?php else: ?>
                <tr id="emptyOrdersRow" style="display:none;">
                    <td colspan="7" style="padding:40px; text-align:center; color:#9ca3af; font-size:14px; cursor:default;">No orders found</td>
                </tr>
                <?php foreach ($orders as $order): ?>
                    <tr onclick="openOrderModal(<?php echo $order['order_id']; ?>)" title="Click to view Order #<?php echo $order['order_id']; ?>" style="border-bottom: 1px solid #f3f4f6;">
                        <td style="color:#1f2937;white-space:nowrap;"><?php echo $order['order_sku'] ? htmlspecialchars($order['order_sku']) . '-' . $order['order_id'] : 'ORD-' . $order['order_id']; ?></td>
                        <td>
                            <div class="cell-ellipsis" style="color:#1f2937; max-width:160px;" title="<?php echo htmlspecialchars($order['customer_name']); ?>"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            <div class="cell-ellipsis" style="font-size:11px; color:#9ca3af; max-width:160px;" title="<?php echo htmlspecialchars($order['customer_email']); ?>"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                        </td>
                        <td style="color:#6b7280; font-size: 12px;"><?php echo format_date($order['order_date']); ?></td>
                        <td><?php
                            echo get_branch_badge_html(
                                (int)($order['branch_id'] ?? 0),
                                $order['branch_name'] ?? 'Main'
                            );
                        ?></td>
                        <td style="color:#1f2937;">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <?php
                                $sc = match($order['status']) {
                                    'Pending'           => 'background:#fef3c7;color:#92400e;',
                                    'Processing'        => 'background:#dbeafe;color:#1e40af;',
                                    'Ready for Pickup'  => 'background:#ede9fe;color:#5b21b6;',
                                    'Completed'         => 'background:#dcfce7;color:#166534;',
                                    'Cancelled'         => 'background:#fecaca;color:#b91c1c;',
                                    default             => 'background:#f3f4f6;color:#374151;'
                                };
                            ?>
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?php echo $sc; ?>" class="cell-ellipsis" title="<?php echo htmlspecialchars($order['status']); ?>"><?php echo $order['status']; ?></span>
                        </td>
                        <td style="text-align:right;">
                            <button 
                                onclick="event.stopPropagation(); openOrderModal(<?php echo $order['order_id']; ?>)"
                                class="btn-action blue"
                            >View</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    $table_html = ob_get_clean();

    ob_start();
    $pagination_params = array_filter(['search'=>$search, 'status'=>$status_filter, 'date_from'=>$date_from, 'date_to'=>$date_to, 'sort'=>$sort_by], function($v) { return $v !== null && $v !== ''; });
    echo render_pagination($page, $total_pages, $pagination_params); 
    $pagination_html = ob_get_clean();

    echo json_encode([
        'success'    => true,
        'table'      => $table_html,
        'pagination' => $pagination_html,
        'count'      => number_format($total_orders),
        'badge'      => count(array_filter([$status_filter, $search, $date_from, $date_to], function($v) { return $v !== null && $v !== ''; }))
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <?php render_branch_css(); ?>
    <style>
        /* KPI Row - matches reports page */
        .kpi-row { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px; }
        @media (max-width:768px) { .kpi-row { grid-template-columns:repeat(2, 1fr); } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-card.blue::before { background:linear-gradient(90deg,#3b82f6,#60a5fa); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }
        /* Modal */
        [x-cloak] { display: none !important; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .modal-panel { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-width:720px; max-height:85vh; overflow-y:auto; margin:16px; position:relative; }
        
        /* Action Button Style */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border: 1px solid transparent;
            background: transparent;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-action.blue {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        .btn-action.blue:hover {
            background: #3b82f6;
            color: white;
        }

        /* ── Toolbar Buttons (Sort / Filter) ─── */
        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .toolbar-btn:hover { border-color: #9ca3af; background: #f9fafb; }
        .toolbar-btn.active { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
        .toolbar-btn svg { flex-shrink: 0; }

        /* ── Filter Panel ─── */
        .filter-panel {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 320px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 200;
            overflow: hidden;
        }
        .filter-panel-header {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .filter-section {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
        }
        .filter-section:last-of-type { border-bottom: none; }
        .filter-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .filter-section-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        .filter-reset-link {
            font-size: 12px;
            font-weight: 600;
            color: #0d9488;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-input {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            box-sizing: border-box;
            transition: border-color 0.15s;
        }
        .filter-input:focus { outline: none; border-color: #0d9488; }
        .filter-date-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .filter-date-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
        .filter-select {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            background: #fff;
            box-sizing: border-box;
            cursor: pointer;
        }
        .filter-select:focus { outline: none; border-color: #0d9488; }
        .filter-search-wrap { position: relative; }
        .filter-search-wrap svg {
            position: absolute;
            left: 9px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }
        .filter-search-input {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 12px;
            color: #1f2937;
            box-sizing: border-box;
        }
        .filter-search-input:focus { outline: none; border-color: #0d9488; }
        .filter-actions {
            display: flex;
            gap: 8px;
            padding: 14px 18px;
            border-top: 1px solid #f3f4f6;
        }
        .filter-btn-reset {
            flex: 1;
            height: 36px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }
        .filter-btn-reset:hover { background: #f9fafb; }
        .filter-btn-apply {
            flex: 1;
            height: 36px;
            border: none;
            background: #0d9488;
            color: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .filter-btn-apply:hover { background: #0f766e; }

        /* ── Sort Dropdown ─── */
        .sort-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            min-width: 200px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 200;
            padding: 6px 0;
            overflow: hidden;
        }
        .sort-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
            transition: background 0.1s;
        }
        .sort-option:hover { background: #f9fafb; }
        .sort-option.selected { color: #0d9488; font-weight: 600; background: #f0fdfa; }
        .sort-option .check { margin-left: auto; color: #0d9488; }

        /* ── Active filter badge ─── */
        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #0d9488;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
        }

        /* ── Table improvements ─── */
        .orders-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .orders-table th {
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .orders-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            color: #374151;
        }
        .orders-table tbody tr {
            cursor: pointer;
            transition: background 0.1s;
        }
        .orders-table tbody tr:hover { background: #f9fafb; }
        .orders-table tbody tr:last-child td { border-bottom: none; }
        .cell-ellipsis {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Pagination Styling */
        #ordersPagination nav {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin-top: 20px;
        }
        #ordersPagination nav a, 
        #ordersPagination nav span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #374151;
            transition: all 0.2s;
        }
        #ordersPagination nav a:hover {
            border-color: #0d9488;
            color: #0d9488;
            background: #f0fdfa;
        }
        #ordersPagination nav .active,
        #ordersPagination nav span[aria-current="page"] {
            background: #0d9488 !important;
            color: #fff !important;
            border-color: #0d9488 !important;
        }

        .mobile-header { display:none; }
        @media (max-width:768px) {
            .mobile-header { display:flex;position:fixed;top:0;left:0;right:0;height:60px;background:#fff;z-index:60;padding:0 20px;align-items:center;justify-content:space-between;border-bottom:1px solid #e5e7eb; }
        }

        .detail-row { display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px; }
        .detail-block { flex:1;min-width:140px;background:#f9fafb;border-radius:8px;padding:12px 14px; }
        .detail-block label { font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px; }
        .detail-block span  { font-size:13px;font-weight:400;color:#1f2937; }

        /* Transaction History Tabs */
        .tab-btn { padding: 8px 16px; font-size: 13px; font-weight: 500; border-radius: 8px; transition: all 0.2s; cursor: pointer; border: 1px solid transparent; }
        .tab-btn.active { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
        .tab-btn:not(.active) { color: #6b7280; }
        .history-item { padding: 10px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
        .history-item:last-child { border-bottom: none; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/' . ($current_user['role'] === 'Admin' ? 'admin_sidebar.php' : 'manager_sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content">
        <script>
            var searchDebounceTimer = null;

            function buildFilterURL(overrides = {}, isAjax = false) {
                const params = new URLSearchParams(window.location.search);
                const fields = {
                    status:    () => document.getElementById('fp_status')?.value    || '',
                    payment:   () => document.getElementById('fp_payment')?.value   || '',
                    search:    () => document.getElementById('fp_search')?.value    || '',
                    date_from: () => document.getElementById('fp_date_from')?.value || '',
                    date_to:   () => document.getElementById('fp_date_to')?.value   || '',
                };
                for (const [key, getter] of Object.entries(fields)) {
                    val = (overrides[key] !== undefined) ? overrides[key] : getter();
                    if (val) params.set(key, val);
                    else params.delete(key);
                }
                if (overrides.sort !== undefined) {
                    if (overrides.sort && overrides.sort !== 'newest') params.set('sort', overrides.sort);
                    else params.delete('sort');
                }
                if (isAjax) params.set('ajax', '1');
                else params.delete('ajax');
                params.delete('page');
                return window.location.pathname + '?' + params.toString();
            }

            async function fetchUpdatedTable(overrides = {}) {
                const url = buildFilterURL(overrides, true);
                try {
                    const resp = await fetch(url);
                    const data = await resp.json();
                    if (data.success) {
                        const tc = document.getElementById('ordersTableContainer');
                        if (tc) {
                            tc.innerHTML = data.table;
                            if (typeof Alpine !== 'undefined' && typeof Alpine.initTree === 'function') {
                                Alpine.initTree(tc);
                            }
                        }
                        const pc = document.getElementById('ordersPagination');
                        if (pc) pc.innerHTML = data.pagination;
                        const bc = document.getElementById('filterBadgeContainer');
                        if (bc) bc.innerHTML = data.badge > 0 ? `<span class="filter-badge">${data.badge}</span>` : '';
                        
                        window.dispatchEvent(new CustomEvent('filter-badge-update', { detail: { badge: data.badge } }));
                        const displayUrl = buildFilterURL(overrides, false);
                        window.history.replaceState({ path: displayUrl }, '', displayUrl);
                    }
                } catch (e) { console.error('Error updating table:', e); }
            }

            function applyFilters(resetAll = false) {
                if (resetAll) {
                    const base = window.location.pathname;
                    const branch = new URLSearchParams(window.location.search).get('branch_id');
                    const target = base + (branch ? '?branch_id=' + encodeURIComponent(branch) : '');
                    window.location.href = target;
                } else { fetchUpdatedTable(); }
            }

            function applySortFilter(sortKey) {
                window.dispatchEvent(new CustomEvent('sort-changed', { detail: { sortKey } }));
                fetchUpdatedTable({ sort: sortKey });
            }

            function resetFilterField(fields) {
                fields.forEach(f => {
                    const el = document.getElementById('fp_' + f);
                    if (el) el.value = '';
                });
                fetchUpdatedTable();
            }

            function filterPanel() {
                return {
                    filterOpen: false,
                    sortOpen:   false,
                    activeSort: '<?php echo $sort_by; ?>',
                    hasActiveFilters: <?php echo count(array_filter([$status_filter, $search, $date_from, $date_to])) > 0 ? 'true' : 'false'; ?>,
                };
            }

            function orderModal() {
                return {
                    showModal: false,
                    loading: false,
                    errorMsg: '',
                    order: null,
                    items: [],
                    selectedStatus: 'Pending',
                    updatingStatus: false,
                    statusUpdateMsg: '',
                    statusUpdateError: false,

                    init() {
                        window.addEventListener('open-order-modal', e => this.openModal(e.detail.orderId));
                        window.addEventListener('filter-badge-update', e => { this.hasActiveFilters = (e.detail.badge > 0); });
                        window.addEventListener('sort-changed', e => { this.activeSort = e.detail.sortKey; this.sortOpen = false; });
                    },

                    openModal(orderId) {
                        this.showModal = true;
                        this.loading = true;
                        this.errorMsg = '';
                        this.statusUpdateMsg = '';
                        this.order = null;
                        this.items = [];
                        fetch('/printflow/admin/api_order_details.php?id=' + orderId)
                            .then(r => r.json())
                            .then(data => {
                                this.loading = false;
                                if (data.success) {
                                    this.order = data.order;
                                    this.items = data.items.map(i => ({
                                        ...i,
                                        editingTarp: false,
                                        savingTarp: false,
                                        tempWidth: i.tarp_details?.width_ft || 0,
                                        tempHeight: i.tarp_details?.height_ft || 0,
                                        tempRollId: i.tarp_details?.roll_id || '',
                                        availableRolls: []
                                    }));
                                    this.selectedStatus = data.order.status;
                                } else { this.errorMsg = data.error || 'Failed to load order details.'; }
                            })
                            .catch(err => {
                                this.loading = false;
                                this.errorMsg = 'Network error.';
                            });
                    },

                    startTarpEdit(item) {
                        item.editingTarp = true;
                        if (item.tempWidth > 0 && item.availableRolls.length === 0) { this.fetchRolls(item); }
                    },

                    fetchRolls(item) {
                        if (!item.tempWidth || item.tempWidth <= 0) return;
                        fetch('/printflow/admin/api_tarp_rolls.php?action=list_available&width=' + item.tempWidth)
                            .then(r => r.json())
                            .then(data => { if (data.success) item.availableRolls = data.rolls; });
                    },

                    async saveTarpSpecs(item) {
                        if (!item.tempWidth || !item.tempHeight || !item.tempRollId) { alert('Please fill all tarpaulin specifications.'); return; }
                        item.savingTarp = true;
                        try {
                            const resp = await fetch('/printflow/admin/api_save_tarp_specs.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({
                                    order_item_id: item.order_item_id,
                                    roll_id: item.tempRollId,
                                    width_ft: item.tempWidth,
                                    height_ft: item.tempHeight,
                                    csrf_token: '<?php echo $_SESSION["csrf_token"] ?? ""; ?>'
                                })
                            });
                            const data = await resp.json();
                            if (data.success) {
                                item.tarp_details = {
                                    width_ft: item.tempWidth,
                                    height_ft: item.tempHeight,
                                    roll_id: item.tempRollId,
                                    roll_code: item.availableRolls.find(r => r.id == item.tempRollId)?.roll_code || 'Assigned'
                                };
                                item.editingTarp = false;
                            } else { alert(data.error || 'Failed to save.'); }
                        } catch (e) { alert('Network error.'); }
                        item.savingTarp = false;
                    },

                    async updateStatus() {
                        if (!this.order) return;
                        this.updatingStatus = true;
                        this.statusUpdateMsg = '';
                        try {
                            const resp = await fetch('/printflow/admin/api_update_order_status.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({
                                    order_id: this.order.order_id,
                                    status: this.selectedStatus,
                                    csrf_token: '<?php echo $_SESSION["csrf_token"] ?? ""; ?>'
                                })
                            });
                            const data = await resp.json();
                            if (data.success) {
                                this.statusUpdateMsg = data.message;
                                this.statusUpdateError = false;
                                this.order.status = this.selectedStatus;
                                setTimeout(() => location.reload(), 1200);
                            } else { this.statusUpdateMsg = data.error || 'Update failed.'; this.statusUpdateError = true; }
                        } catch (e) { this.statusUpdateMsg = 'Network error.'; this.statusUpdateError = true; }
                        this.updatingStatus = false;
                    },

                    statusBadge(status, type) {
                        const colors = {
                            order: {
                                'Pending': 'background:#fef3c7;color:#92400e;',
                                'Processing': 'background:#dbeafe;color:#1e40af;',
                                'Ready for Pickup': 'background:#ede9fe;color:#5b21b6;',
                                'Completed': 'background:#dcfce7;color:#166534;',
                                'Cancelled': 'background:#fecaca;color:#b91c1c;'
                            },
                            payment: {
                                'Pending': 'background:#fef3c7;color:#92400e;',
                                'Unpaid': 'background:#fee2e2;color:#991b1b;',
                                'Paid': 'background:#dcfce7;color:#166534;',
                                'Refunded': 'background:#f3f4f6;color:#374151;',
                                'Failed': 'background:#fee2e2;color:#991b1b;'
                            }
                        };
                        const style = (colors[type] && colors[type][status]) || 'background:#f3f4f6;color:#374151;';
                        return `<span style="display:inline-flex;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;${style}">${status || 'N/A'}</span>`;
                    }
                };
            }

            function ordersPage() { return { ...orderModal(), ...filterPanel() }; }
            window.ordersPage = ordersPage;

            function printflowInitOrdersPage() {
                if (typeof Alpine === 'undefined' || typeof Alpine.initTree !== 'function') return;
                var main = document.querySelector('main[x-data="ordersPage()"]');
                if (main && !main._x_dataStack) { try { Alpine.initTree(main); } catch (e0) { console.error(e0); } }
                /* #ordersTableContainer is plain HTML inside main; do not initTree (already walked). */
                const inputs = ['fp_status', 'fp_date_from', 'fp_date_to'];
                inputs.forEach(id => {
                    const el = document.getElementById(id);
                    if (el && !el._pf_bound) {
                        el._pf_bound = true;
                        el.addEventListener('change', () => fetchUpdatedTable());
                    }
                });
                const searchInput = document.getElementById('fp_search');
                if (searchInput && !searchInput._pf_bound) {
                    searchInput._pf_bound = true;
                    searchInput.addEventListener('input', () => {
                        clearTimeout(searchDebounceTimer);
                        searchDebounceTimer = setTimeout(() => { fetchUpdatedTable(); }, 500);
                    });
                }
            }
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', printflowInitOrdersPage); }
            else { printflowInitOrdersPage(); }
            document.addEventListener('printflow:page-init', printflowInitOrdersPage);

            function openOrderModal(orderId) { window.dispatchEvent(new CustomEvent('open-order-modal', { detail: { orderId } })); }

            function printflowOpenOrderFromQuery() {
                var oo = new URLSearchParams(window.location.search).get('open_order');
                if (!oo) return;
                var oid = parseInt(oo, 10);
                if (!(oid > 0)) return;
                requestAnimationFrame(function () { openOrderModal(oid); });
            }
            printflowOpenOrderFromQuery();
            document.addEventListener('printflow:page-init', printflowOpenOrderFromQuery);
        </script>
        <header>
            <h1 class="page-title">Orders Management</h1>
            <?php render_branch_selector($branchCtx); ?>
        </header>

        <main x-data="ordersPage()" x-init="init()">
            <?php render_branch_context_banner($branchCtx['branch_name']); ?>
            <!-- KPI Summary Row (matches reports page style) -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?php echo $total_count; ?></div>
                    <div class="kpi-sub"><?php echo $completed_count; ?> completed</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Pending Orders</div>
                    <div class="kpi-value"><?php echo $pending_count; ?></div>
                    <div class="kpi-sub">Awaiting action</div>
                </div>
                <div class="kpi-card blue">
                    <div class="kpi-label">Processing</div>
                    <div class="kpi-value"><?php echo $processing_count; ?></div>
                    <div class="kpi-sub">In progress</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Ready for Pickup</div>
                    <div class="kpi-value"><?php echo $ready_count; ?></div>
                    <div class="kpi-sub">Awaiting customer</div>
                </div>
            </div>

            <!-- Orders List & Filters -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">
                        Orders List
                    </h3>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <!-- Branch Selector (re-using the existing logic but fitting the layout) -->

                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: sortOpen }" @click="sortOpen = !sortOpen; filterOpen = false" id="sortBtn" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/>
                                </svg>
                                Sort by
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <?php
                                $sorts = [
                                    'newest' => 'Newest to Oldest',
                                    'oldest' => 'Oldest to Newest',
                                    'az'     => 'A → Z',
                                    'za'     => 'Z → A',
                                ];
                                foreach ($sorts as $key => $label): ?>
                                <div class="sort-option" 
                                     :class="{ 'selected': activeSort === '<?php echo $key; ?>' }"
                                     @click="applySortFilter('<?php echo $key; ?>')">
                                    <?php echo htmlspecialchars($label); ?>
                                    <svg x-show="activeSort === '<?php echo $key; ?>'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: filterOpen || hasActiveFilters }" @click="filterOpen = !filterOpen; sortOpen = false" id="filterBtn" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                </svg>
                                Filter
                                <span id="filterBadgeContainer">
                                    <?php
                                    $active_filters = array_filter([$status_filter, $search, $date_from, $date_to], function($v) { return $v !== null && $v !== ''; });
                                    if (count($active_filters) > 0): ?>
                                    <span class="filter-badge"><?php echo count($active_filters); ?></span>
                                    <?php endif; ?>
                                </span>
                            </button>

                            <!-- Filter Panel -->
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false" id="filterPanel">
                                <div class="filter-panel-header">Filter</div>

                                <!-- Date Range -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Date range</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['date_from','date_to'])">Reset</button>
                                    </div>
                                    <div class="filter-date-row">
                                        <div>
                                            <div class="filter-date-label">From:</div>
                                            <input type="date" id="fp_date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                                        </div>
                                        <div>
                                            <div class="filter-date-label">To:</div>
                                            <input type="date" id="fp_date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Status</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['status'])">Reset</button>
                                    </div>
                                    <select id="fp_status" class="filter-select">
                                        <option value="">All statuses</option>
                                        <option value="Pending"          <?php echo $status_filter === 'Pending'          ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Processing"       <?php echo $status_filter === 'Processing'       ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                        <option value="Completed"        <?php echo $status_filter === 'Completed'        ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Cancelled"        <?php echo $status_filter === 'Cancelled'        ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>

                                <!-- Keyword Search -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Keyword search</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['search'])">Reset</button>
                                    </div>
                                    <div class="filter-search-wrap">
                                        <input type="text" id="fp_search" class="filter-search-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="filter-actions">
                                    <button class="filter-btn-reset" style="width: 100%;" onclick="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto" id="ordersTableContainer">
                    <table class="orders-table">
                        <thead>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <th style="width:12%;white-space:nowrap;">Order #</th>
                                <th style="text-align:left;width:18%;">Customer</th>
                                <th style="width:15%;">Date</th>
                                <th style="width:12%;">Branch</th>
                                <th style="width:12%;">Amount</th>
                                <th style="width:12%;">Status</th>
                                <th style="width:1%; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php if (empty($orders)): ?>
                                <tr id="emptyOrdersRow">
                                    <td colspan="7" style="padding:40px; text-align:center; color:#9ca3af; font-size:14px; cursor:default;">
                                        <?php echo $search ? 'No orders found matching "' . htmlspecialchars($search) . '"' : 'No orders found'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr data-order-id="<?php echo $order['order_id']; ?>" @click="openModal(<?php echo $order['order_id']; ?>)" title="Click to view Order #<?php echo $order['order_id']; ?>" style="border-bottom: 1px solid #f3f4f6; cursor:pointer;">
                        <td style="color:#1f2937;white-space:nowrap;"><?php echo $order['order_sku'] ? htmlspecialchars($order['order_sku']) . '-' . $order['order_id'] : 'ORD-' . $order['order_id']; ?></td>
                        <td>
                            <div class="cell-ellipsis" style="color:#1f2937; max-width:160px;" title="<?php echo htmlspecialchars($order['customer_name']); ?>"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            <div class="cell-ellipsis" style="font-size:11px; color:#9ca3af; max-width:160px;" title="<?php echo htmlspecialchars($order['customer_email']); ?>"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                        </td>
                        <td style="color:#6b7280; font-size: 12px;"><?php echo format_date($order['order_date']); ?></td>
                        <td><?php
                            echo get_branch_badge_html(
                                (int)($order['branch_id'] ?? 0),
                                $order['branch_name'] ?? 'Main'
                            );
                        ?></td>
                        <td style="color:#1f2937;">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <?php
                                $sc = match($order['status']) {
                                    'Pending'           => 'background:#fef3c7;color:#92400e;',
                                    'Processing'        => 'background:#dbeafe;color:#1e40af;',
                                    'Ready for Pickup'  => 'background:#ede9fe;color:#5b21b6;',
                                    'Completed'         => 'background:#dcfce7;color:#166534;',
                                    'Cancelled'         => 'background:#fecaca;color:#b91c1c;',
                                    default             => 'background:#f3f4f6;color:#374151;'
                                };
                            ?>
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;<?php echo $sc; ?>" class="cell-ellipsis" title="<?php echo htmlspecialchars($order['status']); ?>"><?php echo $order['status']; ?></span>
                        </td>
                        <td style="text-align:right;" @click.stop>
                            <button 
                                @click="openModal(<?php echo $order['order_id']; ?>)"
                                class="btn-action blue"
                            >View</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="ordersPagination">
                    <?php 
                    $pagination_params = array_filter(['search'=>$search, 'status'=>$status_filter, 'date_from'=>$date_from, 'date_to'=>$date_to, 'sort'=>$sort_by], function($v) { return $v !== null && $v !== ''; });
                    echo render_pagination($page, $total_pages, $pagination_params); 
                    ?>
                </div>
            </div>

<!-- Order Details Modal (inside main x-data="ordersPage()" for Alpine scope) -->
<div x-show="showModal"
     x-cloak>
    
    <!-- Overlay -->
    <div class="modal-overlay" @click.self="showModal = false">
        <!-- Modal Panel -->
        <div class="modal-panel" @click.stop>
            
            <!-- Loading State -->
            <div x-show="loading" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading order details...</p>
            </div>

            <!-- Error State -->
            <div x-show="errorMsg && !loading" style="padding:32px;text-align:center;">
                <p style="color:#ef4444;font-size:14px;margin-bottom:12px;" x-text="errorMsg"></p>
                <button @click="showModal = false" class="btn-secondary">Close</button>
            </div>

            <!-- Order Details Content -->
            <div x-show="order && !loading">
                <!-- Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;">Order #PF-<span x-text="order?.order_id"></span></h3>
                        <p style="font-size:13px;color:#6b7280;margin:2px 0 0;" x-text="order?.order_date"></p>
                        <p style="font-size:12px;color:#4F46E5;margin:3px 0 0;font-weight:600;"><span x-text="order?.branch_name"></span></p>
                    </div>
                    <button @click="showModal = false" style="width:32px;height:32px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Customer & Order Info Grid -->
                <div style="padding:24px;">
                    <div class="detail-row">
                        <div class="detail-block">
                            <label>Customer Name</label>
                            <span x-text="order?.customer_name"></span>
                        </div>
                        <div class="detail-block">
                            <label>Customer Email</label>
                            <span x-text="order?.customer_email"></span>
                        </div>
                        <div class="detail-block">
                            <label>Customer Phone</label>
                            <span x-text="order?.customer_phone"></span>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-block">
                            <label>Order Status</label>
                            <span x-html="statusBadge(order?.status, 'order')"></span>
                        </div>
                        <div class="detail-block">
                            <label>Payment Status</label>
                            <span x-html="statusBadge(order?.payment_status, 'payment')"></span>
                        </div>
                        <div class="detail-block">
                            <label>Total Amount</label>
                            <span x-text="order?.total_amount" style="font-size: 15px; font-weight: 700; color: #10b981;"></span>
                        </div>
                    </div>
                </div>

                <!-- Order Items Table -->
                <div style="padding:0 24px 20px;">
                    <h4 style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 12px;">Order Items</h4>
                    <div style="border:1px solid #f3f4f6;border-radius:10px;overflow:hidden;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#f9fafb;">
                                    <th style="text-align:left;padding:10px 14px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Product</th>
                                    <th style="text-align:center;padding:10px 14px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Qty</th>
                                    <th style="text-align:right;padding:10px 14px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Price</th>
                                    <th style="text-align:right;padding:10px 14px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="items.length === 0">
                                    <tr><td colspan="4" style="text-align:center;padding:20px;color:#9ca3af;">No items found</td></tr>
                                </template>
                                <template x-for="item in items" :key="item.sku">
                                    <tr style="border-top:1px solid #f3f4f6;">
                                        <td style="padding:10px 14px;">
                                            <div x-text="item.product_name" style="font-weight:500;color:#1f2937;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" :title="item.product_name"></div>
                                            <template x-if="item.variant_name">
                                                <div style="margin-top:3px;">
                                                    <span x-text="'📐 ' + item.variant_name"
                                                          style="display:inline-flex;align-items:center;background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:500;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" :title="'📐 ' + item.variant_name"></span>
                                                </div>
                                            </template>
                                            <div x-text="item.category" style="font-size:11px;color:#9ca3af;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" :title="item.category"></div>
                                            
                                            <!-- Tarpaulin/Sticker Specific Specs (Roll-based) -->
                                            <template x-if="item.category && (item.category.toUpperCase().includes('TARPAULIN') || item.category.toUpperCase().includes('STKR'))">
                                                <div style="margin-top:8px;">
                                                    <div x-show="!item.editingTarp" style="font-size:12px; background:#f0fdf4; padding:8px; border-radius:8px; border:1px solid #dcfce7;">
                                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                                            <div>
                                                                <template x-if="item.tarp_details">
                                                                    <div>
                                                                        <span style="color:#166534; font-weight:600;" x-text="item.tarp_details.width_ft + ' x ' + item.tarp_details.height_ft + ' ft'"></span>
                                                                        <span style="color:#6b7280; margin-left:8px;" x-text="'Roll: ' + (item.tarp_details.roll_code || 'Not Assigned')"></span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="!item.tarp_details">
                                                                    <span style="color:#991b1b; font-weight:600;">Dimensions not set</span>
                                                                </template>
                                                            </div>
                                                            <button @click="startTarpEdit(item)" style="font-size:11px; color:#4F46E5; background:none; border:none; cursor:pointer; font-weight:600; text-decoration:underline;">Configure</button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div x-show="item.editingTarp" style="font-size:12px; background:#fff; padding:12px; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                                                            <div>
                                                                <label style="display:block; font-size:10px; color:#6b7280; margin-bottom:2px;">Width (FT)</label>
                                                                <input type="number" x-model="item.tempWidth" @change="fetchRolls(item)" style="width:100% !important; height:32px; border:1px solid #e5e7eb; border-radius:6px; padding:0 8px;">
                                                            </div>
                                                            <div>
                                                                <label style="display:block; font-size:10px; color:#6b7280; margin-bottom:2px;">Height (FT)</label>
                                                                <input type="number" x-model="item.tempHeight" style="width:100% !important; height:32px; border:1px solid #e5e7eb; border-radius:6px; padding:0 8px;">
                                                            </div>
                                                        </div>
                                                        <div style="margin-bottom:8px;">
                                                            <label style="display:block; font-size:10px; color:#6b7280; margin-bottom:2px;">Inventory Roll</label>
                                                            <select x-model="item.tempRollId" style="width:100% !important; height:32px; border:1px solid #e5e7eb; border-radius:6px; padding:0 8px; display:block;">
                                                                <option value="">Select a Roll</option>
                                                                <template x-for="roll in item.availableRolls || []" :key="roll.id">
                                                                    <option :value="roll.id" x-text="roll.roll_code + ' (' + roll.remaining_length_ft + ' ft left)'"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                        <div style="display:flex; gap:8px; justify-content:flex-end;">
                                                            <button @click="item.editingTarp = false" style="padding:4px 10px; font-size:11px; background:#f3f4f6; border-radius:6px; border:none; cursor:pointer;">Cancel</button>
                                                            <button @click="saveTarpSpecs(item)" style="padding:4px 10px; font-size:11px; background:#4F46E5; color:white; border-radius:6px; border:none; cursor:pointer;" :disabled="item.savingTarp">Save</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </td>
                                        <td style="padding:10px 14px;text-align:center;" x-text="item.quantity"></td>
                                        <td style="padding:10px 14px;text-align:right;color:#6b7280;" x-text="item.unit_price_formatted"></td>
                                        <td style="padding:10px 14px;text-align:right;font-weight:600;color:#1f2937;" x-text="item.subtotal_formatted"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot x-show="items.length > 0">
                                <tr style="border-top:2px solid #e5e7eb;background:#f9fafb;">
                                    <td colspan="3" style="padding:12px 14px;text-align:right;font-weight:600;font-size:14px;">Total</td>
                                    <td style="padding:12px 14px;text-align:right;font-weight:700;font-size:15px;color:#1f2937;" x-text="order?.total_amount"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Notes -->
                <template x-if="order?.notes">
                    <div style="padding:0 24px 20px;">
                        <h4 style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 8px;">Notes</h4>
                        <p x-text="order.notes" style="font-size:13px;color:#6b7280;background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #f3f4f6;margin:0;word-wrap:break-word;overflow-wrap:break-word;white-space:pre-wrap;"></p>
                    </div>
                </template>

                <!-- Footer -->
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;">
                    <button @click="showModal = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

        </main>
    </div>
</div>

</body>

</html>
