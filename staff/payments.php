<?php
/**
 * Staff: Unified Payment List
 * All POS + Online payments (Products & Services) in one page.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

require_role(['Admin', 'Staff', 'Manager']);
require_once __DIR__ . '/../includes/staff_pending_check.php';

$staffBranchId = null;
if (is_staff() || is_manager()) {
    $staffBranchId = printflow_branch_filter_for_user() ?? (int)($_SESSION['branch_id'] ?? 1);
}

// ─── Filters ─────────────────────────────────────────────────────────────────
$filter_source    = trim($_GET['source']    ?? '');
$filter_type      = trim($_GET['type']      ?? '');
$filter_status    = trim($_GET['status']    ?? '');
$filter_search    = trim($_GET['search']    ?? '');
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to']   ?? '');
$page_num         = max(1, (int)($_GET['page'] ?? 1));
$per_page         = 15;
$offset           = ($page_num - 1) * $per_page;

// ─── Build branch conditions ──────────────────────────────────────────────────
$bp = [];
$bt = '';
$branch_cond_o = '';
$branch_cond_c = '';
if ($staffBranchId !== null) {
    $branch_cond_o = ' AND o.branch_id = ?';
    $branch_cond_c = ' AND o.branch_id = ?';
    $bp[] = $staffBranchId;
    $bp[] = $staffBranchId;
    $bt   = 'ii';
}

// ─── Subquery 1: All regular orders (products + POS) ─────────────────────────
$q1 = "
    SELECT
        o.order_id               AS payment_id,
        o.order_id               AS ref_order_id,
        NULL                     AS ref_cust_id,
        COALESCE(pay.customer_name, NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), 'Walk-in Guest') AS customer_name,
        pay.sender_name          AS sender_name,
        IF(o.order_type = 'product', 'PRODUCT', 'SERVICE') AS order_type_label,
        COALESCE(pay.source, IF(o.order_source = 'pos', 'POS', 'Online')) AS source_label,
        COALESCE(NULLIF(pay.amount, 0), o.downpayment_amount, 0) AS amount,
        COALESCE(o.total_amount, 0)             AS original_total,
        COALESCE(pay.payment_method, o.payment_method, 'Cash') AS payment_method,
        CASE
            WHEN pay.payment_status = 'Incomplete' THEN 'INCOMPLETE'
            WHEN pay.payment_status = 'To Verify'   THEN 'TO_VERIFY'
            WHEN o.status IN ('Pending Verification','Downpayment Submitted','To Verify') THEN 'TO_VERIFY'
            WHEN o.payment_status IN ('Paid','PAID')
              OR o.status IN ('Ready for Pickup','Completed','Processing','In Production','Printing') THEN 'VERIFIED'
            WHEN o.payment_status = 'Rejected' OR o.status = 'Rejected'                             THEN 'REJECTED'
            WHEN o.status = 'To Pay'                                                                 THEN 'TO_PAY'
            ELSE 'OTHER'
        END                      AS pay_status,
        COALESCE(o.payment_proof, o.payment_proof_path, '')  AS proof_path,
        o.order_date             AS paid_at,
        o.status                 AS raw_status,
        c.profile_picture        AS profile_pic,
        pay.reference_id
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN (
        SELECT p1.* FROM payments p1
        INNER JOIN (SELECT MAX(id) AS max_id FROM payments GROUP BY order_id) p2 ON p1.id = p2.max_id
    ) pay ON o.order_id = pay.order_id
    WHERE 1=1 $branch_cond_o
" ;

// ─── Subquery 2: Customisation service orders via orders row ──────────────────
$q2 = "
    SELECT
        cust.customization_id    AS payment_id,
        o.order_id               AS ref_order_id,
        cust.customization_id    AS ref_cust_id,
        COALESCE(pay.customer_name, NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), 'Walk-in Guest') AS customer_name,
        pay.sender_name          AS sender_name,
        'SERVICE'                AS order_type_label,
        COALESCE(pay.source, IF(COALESCE(o.order_source,'online') = 'pos', 'POS', 'Online')) AS source_label,
        COALESCE(NULLIF(pay.amount, 0), o.downpayment_amount, 0) AS amount,
        COALESCE(o.total_amount, 0)                                 AS original_total,
        COALESCE(pay.payment_method, o.payment_method, 'N/A')       AS payment_method,
        CASE
            WHEN pay.payment_status = 'Incomplete' THEN 'INCOMPLETE'
            WHEN pay.payment_status = 'To Verify'   THEN 'TO_VERIFY'
            WHEN cust.status IN ('Pending Verification','Downpayment Submitted','To Verify') THEN 'TO_VERIFY'
            WHEN cust.status IN ('Processing','In Production','Ready for Pickup','Completed') THEN 'VERIFIED'
            WHEN cust.status = 'Rejected'                                                    THEN 'REJECTED'
            WHEN cust.status IN ('To Pay','Approved')                                        THEN 'TO_PAY'
            ELSE 'OTHER'
        END                      AS pay_status,
        COALESCE(o.payment_proof_path, '')  AS proof_path,
        COALESCE(o.updated_at, cust.created_at) AS paid_at,
        cust.status              AS raw_status,
        c.profile_picture        AS profile_pic,
        pay.reference_id
    FROM customizations cust
    INNER JOIN orders o ON cust.order_id = o.order_id
    LEFT JOIN  customers c ON cust.customer_id = c.customer_id
    LEFT JOIN (
        SELECT p1.* FROM payments p1
        INNER JOIN (SELECT MAX(id) AS max_id FROM payments GROUP BY order_id) p2 ON p1.id = p2.max_id
    ) pay ON o.order_id = pay.order_id
    WHERE 1=1 $branch_cond_c
" ;

// Params shared by both subqueries
$params = [];
$types  = '';
if ($staffBranchId !== null) {
    $params = [$staffBranchId, $staffBranchId];
    $types  = 'ii';
}

$base_sql = "($q1) UNION ALL ($q2)";

// ─── Extra filter conditions applied on the outer query ───────────────────────
$extra = '';
$ep    = [];
$et    = '';

if ($filter_source === 'pos')    { $extra .= " AND source_label = 'POS'"; }
if ($filter_source === 'online') { $extra .= " AND source_label = 'ONLINE'"; }
if ($filter_type   === 'product'){ $extra .= " AND order_type_label = 'PRODUCT'"; }
if ($filter_type   === 'service'){ $extra .= " AND order_type_label = 'SERVICE'"; }
if ($filter_status === 'to_verify') { $extra .= " AND pay_status = 'TO_VERIFY'"; }
if ($filter_status === 'verified')  { $extra .= " AND pay_status = 'VERIFIED'"; }
if ($filter_status === 'rejected')  { $extra .= " AND pay_status = 'REJECTED'"; }
if ($filter_status === 'to_pay')    { $extra .= " AND pay_status = 'TO_PAY'"; }
// Default: Only show VERIFIED if no status filter is active (as per strict system rules)
if ($filter_status === '') { $extra .= " AND pay_status = 'VERIFIED'"; }
if ($filter_date_from !== '') { $extra .= " AND DATE(paid_at) >= ?"; $ep[] = $filter_date_from; $et .= 's'; }
if ($filter_date_to   !== '') { $extra .= " AND DATE(paid_at) <= ?"; $ep[] = $filter_date_to;   $et .= 's'; }
if ($filter_search    !== '') {
    $like   = '%' . $filter_search . '%';
    $extra .= " AND (customer_name LIKE ? OR ref_order_id LIKE ?)";
    $ep[]   = $like; $ep[] = $like; $et .= 'ss';
}

$c_types  = $types . $et;
$c_params = array_merge($params, $ep);

// ─── KPI counters ─────────────────────────────────────────────────────────────
$kpi_to_verify = (int)(db_query("SELECT COUNT(*) AS c FROM ($base_sql) AS u WHERE pay_status='TO_VERIFY'", $types ?: null, $params ?: null)[0]['c'] ?? 0);
$kpi_verified  = (int)(db_query("SELECT COUNT(*) AS c FROM ($base_sql) AS u WHERE pay_status='VERIFIED'",  $types ?: null, $params ?: null)[0]['c'] ?? 0);
$kpi_rejected  = (int)(db_query("SELECT COUNT(*) AS c FROM ($base_sql) AS u WHERE pay_status='REJECTED'",  $types ?: null, $params ?: null)[0]['c'] ?? 0);
$kpi_total     = (float)(db_query("SELECT COALESCE(SUM(amount),0) AS s FROM ($base_sql) AS u WHERE pay_status='VERIFIED'", $types ?: null, $params ?: null)[0]['s'] ?? 0);

// ─── Paged list ───────────────────────────────────────────────────────────────
$total_row   = db_query("SELECT COUNT(*) AS total FROM ($base_sql) AS u WHERE 1=1$extra", $c_types ?: null, $c_params ?: null);
$total_items = (int)($total_row[0]['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_items / $per_page));
$page_num = min($page_num, $total_pages);
$offset = ($page_num - 1) * $per_page;

$p_types  = $c_types . 'ii';
$p_params = array_merge($c_params, [$per_page, $offset]);
$payments = db_query(
    "SELECT * FROM ($base_sql) AS u WHERE 1=1$extra ORDER BY paid_at DESC LIMIT ? OFFSET ?",
    $p_types ?: null,
    $p_params ?: null
) ?: [];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function buildQueryString(array $overrides = []): string {
    $base   = array_filter(['source'=>$_GET['source']??'','type'=>$_GET['type']??'','status'=>$_GET['status']??'','search'=>$_GET['search']??'','date_from'=>$_GET['date_from']??'','date_to'=>$_GET['date_to']??'','page'=>$_GET['page']??'']);
    $merged = array_filter(array_merge($base, $overrides), fn($v) => $v !== '' && $v !== null);
    return $merged ? '?' . http_build_query($merged) : '?';
}

$page_title = 'Payment List – PrintFlow Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Unified payment list for all POS and Online transactions.">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .pm-field { display:flex; justify-content:space-between; align-items:flex-start; padding:10px 0; border-bottom:1px solid #f1f5f9; }
        .pm-field:last-child { border-bottom:none; }
        .pm-label { font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; flex-shrink:0; min-width:120px; padding-top:1px; }
        .pm-value { font-size:0.875rem; font-weight:600; color:#1e293b; text-align:right; }
        .pm-btn { border:none; border-radius:9px; padding:11px 22px; font-size:0.875rem; font-weight:700; cursor:pointer; transition:all .2s; }
        .pm-approve { background:#0d9488; color:#fff; }
        .pm-approve:hover { background:#0f766e; transform:translateY(-1px); }
        .pm-reject { background:#fff; color:#ef4444; border:1px solid #fca5a5; }
        .pm-reject:hover { background:#fef2f2; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content" x-data="{ filterOpen: false, hasActiveFilters: <?= (!empty($filter_source) || !empty($filter_type) || !empty($filter_status) || !empty($filter_search) || !empty($filter_date_from) || !empty($filter_date_to)) ? 'true' : 'false' ?> }">
        <header>
            <div>
                <h1 class="page-title">Payment List</h1>
                <p class="page-subtitle">All incoming payments — POS &amp; Online · Products &amp; Services</p>
            </div>
        </header>

        <main>
            <!-- KPI CARDS -->
            <div class="kpi-row">
                <div class="kpi-card amber">
                    <span class="kpi-label">To Verify</span>
                    <span class="kpi-value"><?= $kpi_to_verify ?></span>
                </div>
                <div class="kpi-card emerald">
                    <span class="kpi-label">Verified</span>
                    <span class="kpi-value"><?= $kpi_verified ?></span>
                </div>
                <div class="kpi-card rose">
                    <span class="kpi-label">Rejected</span>
                    <span class="kpi-value"><?= $kpi_rejected ?></span>
                </div>
                <div class="kpi-card indigo">
                    <span class="kpi-label">Total Collected</span>
                    <span class="kpi-value" style="font-size:24px;">₱<?= number_format($kpi_total,2) ?></span>
                </div>
            </div>

            <!-- TOOLBAR & FILTERS -->
            <div class="card overflow-visible">
                <div class="toolbar-container" style="display:block;">
                    <div style="display:flex; align-items:center; width:100%;">
                        <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">Transactions</h3>
                        
                        <div class="toolbar-group" style="margin-left: auto;">
                            <!-- Filter Button -->
                            <div style="position:relative;">
                                <button type="button" class="toolbar-btn" :class="{ active: filterOpen || hasActiveFilters }" @click="filterOpen = !filterOpen">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                    Filter
                                    <template x-if="hasActiveFilters">
                                        <span class="filter-badge"><?= (int)!empty($filter_source) + (int)!empty($filter_type) + (int)!empty($filter_status) + (int)!empty($filter_search) + (int)(!empty($filter_date_from)||!empty($filter_date_to)) ?></span>
                                    </template>
                                </button>

                                <!-- Dropdown Filter Panel -->
                                <div class="dropdown-panel filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                    <form method="GET" action="payments.php" id="payments-filter-form">
                                        <div class="filter-header">Filter Transactions</div>
                                        
                                        <div class="filter-section">
                                            <div class="filter-section-head">
                                                <span class="filter-label" style="margin:0;">Keyword search</span>
                                                <button type="button" onclick="document.forms['payments-filter-form'].elements['search'].value=''; document.getElementById('payments-filter-form').submit()" class="filter-reset-link">Reset</button>
                                            </div>
                                            <input type="text" name="search" class="filter-input" placeholder="Search Order # or customer name…" value="<?= htmlspecialchars($filter_search) ?>" onchange="document.getElementById('payments-filter-form').submit()">
                                        </div>

                                        <div class="filter-section">
                                            <div class="filter-section-head">
                                                <span class="filter-label" style="margin:0;">Source</span>
                                                <button type="button" onclick="document.forms['payments-filter-form'].elements['source'].value=''; document.getElementById('payments-filter-form').submit()" class="filter-reset-link">Reset</button>
                                            </div>
                                            <select name="source" class="filter-select" onchange="document.getElementById('payments-filter-form').submit()">
                                                <option value="">All Sources</option>
                                                <option value="pos" <?= $filter_source==='pos'?'selected':'' ?>>POS only</option>
                                                <option value="online" <?= $filter_source==='online'?'selected':'' ?>>Online only</option>
                                            </select>
                                        </div>

                                        <div class="filter-section">
                                            <div class="filter-section-head">
                                                <span class="filter-label" style="margin:0;">Type</span>
                                                <button type="button" onclick="document.forms['payments-filter-form'].elements['type'].value=''; document.getElementById('payments-filter-form').submit()" class="filter-reset-link">Reset</button>
                                            </div>
                                            <select name="type" class="filter-select" onchange="document.getElementById('payments-filter-form').submit()">
                                                <option value="">All Types</option>
                                                <option value="product" <?= $filter_type==='product'?'selected':'' ?>>Products (Fixed)</option>
                                                <option value="service" <?= $filter_type==='service'?'selected':'' ?>>Services (Custom)</option>
                                            </select>
                                        </div>

                                        <div class="filter-section">
                                            <div class="filter-section-head">
                                                <span class="filter-label" style="margin:0;">Status</span>
                                                <button type="button" onclick="document.forms['payments-filter-form'].elements['status'].value=''; document.getElementById('payments-filter-form').submit()" class="filter-reset-link">Reset</button>
                                            </div>
                                            <select name="status" class="filter-select" onchange="document.getElementById('payments-filter-form').submit()">
                                                <option value="">All Statuses</option>
                                                <option value="to_verify" <?= $filter_status==='to_verify'?'selected':'' ?>>To Verify</option>
                                                <option value="verified" <?= $filter_status==='verified'?'selected':'' ?>>Verified</option>
                                                <option value="rejected" <?= $filter_status==='rejected'?'selected':'' ?>>Rejected</option>
                                                <option value="to_pay" <?= $filter_status==='to_pay'?'selected':'' ?>>To Pay</option>
                                            </select>
                                        </div>

                                        <div class="filter-section">
                                            <div class="filter-section-head">
                                                <span class="filter-label" style="margin:0;">Date range</span>
                                                <button type="button" onclick="document.forms['payments-filter-form'].elements['date_from'].value=''; document.forms['payments-filter-form'].elements['date_to'].value=''; document.getElementById('payments-filter-form').submit()" class="filter-reset-link">Reset</button>
                                            </div>
                                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($filter_date_from) ?>" onchange="document.getElementById('payments-filter-form').submit()" title="Date From">
                                                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($filter_date_to) ?>" onchange="document.getElementById('payments-filter-form').submit()" title="Date To">
                                            </div>
                                        </div>

                                        <div class="filter-footer">
                                            <a href="payments.php" class="filter-btn-reset" style="display:flex;align-items:center;justify-content:center;text-decoration:none;width:100%;">Reset all filters</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="overflow-x-auto">
                    <table class="staff-orders-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer Name</th>
                                <th>Type</th>
                                <th>Source</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Ref ID</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($payments)): ?>
                            <tr><td colspan="11" style="text-align:center;padding:40px;color:#6b7280;">No payment records match your filters.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $p):
                            $isService = ($p['order_type_label'] === 'SERVICE');
                            $isPOS     = (strtoupper($p['source_label'] ?? '') === 'POS');
                            $ps        = $p['pay_status'];
                            
                            $badgeHtml = match($ps) {
                                'TO_VERIFY'  => '<span class="status-badge badge-pending">To Verify</span>',
                                'VERIFIED'   => '<span class="status-badge badge-fulfilled">Verified</span>',
                                'REJECTED'   => '<span class="status-badge badge-cancelled">Rejected</span>',
                                'TO_PAY'     => '<span class="status-badge badge-processing">To Pay</span>',
                                'INCOMPLETE' => '<span class="status-badge" style="background:#fff7ed;color:#9a3412;border:1px solid #ffedd5;">Incomplete</span>',
                                default      => '<span class="status-badge" style="background:#f1f5f9;color:#64748b;">—</span>'
                            };

                            // proof url
                            $proof_url = '';
                            if (!empty($p['proof_path'])) {
                                $pf = $p['proof_path'];
                                if (str_starts_with($pf, 'http')) $proof_url = htmlspecialchars($pf);
                                else {
                                    $proof_url = '/printflow/api_view_proof.php?file=' . rawurlencode($pf);
                                }
                            }

                            // avatar
                            if (!empty($p['profile_pic'])) {
                                $avatarHtml = '<img src="'.get_profile_image($p['profile_pic']).'" style="width:28px;height:28px;border-radius:50%;object-fit:cover;margin-right:8px;flex-shrink:0;">';
                            } else {
                                $dispName = $p['customer_name'] ?: 'Guest';
                                $ini = strtoupper(substr($dispName,0,1)?:'?');
                                $avatarHtml = '<div style="width:28px;height:28px;border-radius:50%;background:#e0f2fe;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#0284c7;margin-right:8px;flex-shrink:0;">'.$ini.'</div>';
                            }

                            // payment method coloring
                            $pm = strtoupper($p['payment_method'] ?? '');
                            $methodStyle = 'background:#f8fafc;color:#64748b;border:1px solid #e2e8f0;';
                            if (str_contains($pm, 'GCASH')) {
                                $methodStyle = 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;'; 
                            } elseif (str_contains($pm, 'MAYAMA') || str_contains($pm, 'MAYA')) {
                                $methodStyle = 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;';
                            }

                            $modal_data = htmlspecialchars(json_encode([
                                'order_id'     => $p['ref_order_id'],
                                'customer_name'=> $p['customer_name'],
                                'sender_name'  => $p['sender_name'] ?: '—',
                                'order_type'   => $p['order_type_label'],
                                'source'       => $p['source_label'],
                                'amount'       => $p['amount'],
                                'original_total'=> $p['original_total'],
                                'method'       => $p['payment_method'],
                                'status'       => $ps,
                                'status_html'  => $badgeHtml,
                                'raw_status'   => $p['raw_status'],
                                'proof_url'    => $proof_url,
                                'paid_at'      => $p['paid_at'],
                                'reference_id' => $p['reference_id']
                            ]),ENT_QUOTES);
                        ?>
                        <tr onclick="openPayModal(<?= $modal_data ?>)">
                            <td class="order-id-wrap">#<?= $p['ref_order_id'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;font-weight:600;color:#111827;">
                                    <?= $avatarHtml ?>
                                    <span style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($p['customer_name']) ?>"><?= htmlspecialchars($p['customer_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;letter-spacing:0.04em; <?= $isService ? 'background:#fefce8;color:#a16207;border:1px solid #fef08a;' : 'background:#f8fafc;color:#64748b;border:1px solid #e2e8f0;' ?>">
                                    <?= $isService ? 'SERVICE' : 'PRODUCT' ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;letter-spacing:0.04em; <?= $isPOS ? 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;' : 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;' ?>">
                                    <?= strtoupper($p['source_label'] ?: 'Online') ?>
                                </span>
                            </td>
                            <td style="font-weight:700;color:#0f172a;"><?= ($p['amount'] !== null && $p['amount'] > 0) ? '₱' . number_format((float)$p['amount'], 2) : '<span style="color:#94a3b8;font-weight:500;">N/A</span>' ?></td>
                            <td>
                                <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;letter-spacing:0.04em; <?= $methodStyle ?>">
                                    <?= htmlspecialchars($p['payment_method']?:'—') ?>
                                </span>
                            </td>
                            <td style="font-size:12px;color:#0f172a;font-family:monospace;"><?= htmlspecialchars($p['reference_id']?:'—') ?></td>
                            <td><?= $badgeHtml ?></td>
                            <td style="font-size:12px;color:#64748b;"><?= $p['paid_at'] ? date('M d, Y',strtotime($p['paid_at'])) : '—' ?></td>
                            <td>
                                <button type="button" class="btn-sm btn-outline" style="border-radius:6px;padding:4px 10px;font-size:11px;" onclick="openPayModal(<?= $modal_data ?>); event.stopPropagation();">View</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINATION -->
            <div style="margin-top:20px;">
                <?php echo get_pagination_links($page_num, $total_pages, [
                    'source' => $filter_source,
                    'type' => $filter_type,
                    'status' => $filter_status,
                    'search' => $filter_search,
                    'date_from' => $filter_date_from,
                    'date_to' => $filter_date_to
                ]); ?>
            </div>

        </main>
    </div>
</div>

<!-- PAYMENT MODAL (Light Theme) -->
<div id="payModal" class="modal-overlay" onclick="if(event.target===this)closePayModal()" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div class="modal-panel" style="width:100%;max-width:560px;max-height:92vh;overflow-y:auto;background:#fff;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:10;border-radius:20px 20px 0 0;">
            <div>
                <h3 style="font-size:1.25rem;font-weight:800;color:#0f172a;margin:0;">Payment Details</h3>
                <p style="font-size:0.75rem;color:#64748b;margin:0;">Transaction reference and proof</p>
            </div>
            <button onclick="closePayModal()" style="background:#f1f5f9;border:none;color:#64748b;border-radius:50%;width:36px;height:36px;font-size:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;" onmouseover="this.style.background='#e2e8f0';this.style.color='#0f172a'" onmouseout="this.style.background='#f1f5f9';this.style.color='#64748b'">&times;</button>
        </div>
        <div id="payModalBody" style="padding:24px;"></div>
    </div>
</div>

<!-- ZOOM MODAL (Dark Fullscreen) -->
<div id="imageZoomModal" class="modal-overlay" onclick="if(event.target===this||event.target.id==='btnZoomClose')closeZModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:10000;align-items:center;justify-content:center;padding:16px;">
    <button id="btnZoomClose" style="position:absolute;top:20px;right:20px;background:rgba(255,255,255,.1);border:none;color:#fff;border-radius:50%;width:44px;height:44px;font-size:26px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;" onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">&times;</button>
    <div style="position:relative;max-width:90%;max-height:90vh;display:flex;flex-direction:column;align-items:center;">
        <img id="imageZoomSrc" src="" style="max-width:100%;max-height:80vh;object-fit:contain;border-radius:8px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        <a id="imageZoomDownload" href="#" download class="pm-btn pm-approve" style="display:inline-block;margin-top:20px;text-decoration:none;padding:10px 24px;border-radius:8px;background:#0d9488;color:#fff;font-weight:700;box-shadow:0 4px 6px rgba(0,0,0,.1);">Download Image &darr;</a>
    </div>
</div>

<script>
function openPayModal(data) {
    const body = document.getElementById('payModalBody');
    if (!body) return;

    const isPOS = data.source === 'POS';
    const isSvc = data.order_type === 'SERVICE';
    const amt = (data.amount !== null && parseFloat(data.amount) > 0) 
        ? '₱' + parseFloat(data.amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) 
        : 'N/A';
    const orderTotal = parseFloat(data.original_total||0).toLocaleString('en-PH',{minimumFractionDigits:2});
    const dateFmt = data.paid_at ? new Date(data.paid_at).toLocaleString('en-PH',{dateStyle:'medium',timeStyle:'short'}) : '—';

    // Amount Mismatch Warning
    let amountWarning = '';
    const diff = Math.abs(parseFloat(data.amount||0) - parseFloat(data.original_total||0));
    if (diff > 0.01) {
        amountWarning = `<div style="margin-bottom:16px;padding:12px;background:#fff7ed;border:1px solid #ffedd5;border-radius:10px;display:flex;gap:12px;align-items:center;">
            <div style="background:#f97316;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0;">!</div>
            <div>
                <div style="font-size:13px;font-weight:700;color:#9a3412;">Amount Mismatch Warning</div>
                <div style="font-size:11px;color:#c2410c;">Paid ₱${amt} vs. Order Total ₱${orderTotal}. Please verify if this is a downpayment or partial payment.</div>
            </div>
        </div>`;
    }

    const proofHtml = data.proof_url
        ? `<div style="margin:20px 0 10px;">
             <div class="pm-label" style="margin-bottom:8px;">Proof of Payment</div>
             <div onclick="openZModal('${data.proof_url}')" style="display:block;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;background:#f8fafc;cursor:zoom-in;">
               <img src="${data.proof_url}" style="width:100%;max-height:300px;object-fit:contain;display:block;">
             </div>
             <button type="button" onclick="openZModal('${data.proof_url}')" style="background:none;border:none;display:block;width:100%;text-align:center;font-size:12px;color:#0d9488;margin-top:8px;font-weight:600;cursor:pointer;">Click to enlarge image ↗</button>
           </div>`
        : `<div style="margin:20px 0;padding:16px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;text-align:center;color:#64748b;font-size:13px;font-weight:500;">No proof of payment uploaded</div>`;

    const actionHtml = (data.status === 'TO_VERIFY' && data.order_id)
        ? `<div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;border-top:1px solid #f1f5f9;padding-top:20px;">
             <button class="pm-btn pm-approve" onclick="doVerify(${data.order_id},'Approve')">Approve Payment</button>
             <button class="pm-btn pm-reject"  onclick="doVerify(${data.order_id},'Reject')">Reject Payment...</button>
           </div>` : '';

    const badgePosHtml = `<span style="font-size:10px;font-weight:800;padding:4px 10px;border-radius:20px;letter-spacing:0.04em; ${isPOS ? 'background:#eff6ff;color:#1d4ed8;' : 'background:#ecfdf5;color:#047857;'}">${data.source.toUpperCase()}</span>`;
    const badgeTypeHtml = `<span style="font-size:10px;font-weight:800;padding:4px 10px;border-radius:20px;letter-spacing:0.04em; ${isSvc ? 'background:#fefce8;color:#a16207;' : 'background:#f8fafc;color:#64748b;'}">${data.order_type}</span>`;

    // Method Badge
    const pm = (data.method || '').toUpperCase();
    let mStyle = 'background:#f1f5f9;color:#64748b;';
    if (pm.includes('GCASH')) mStyle = 'background:#eff6ff;color:#1d4ed8;';
    else if (pm.includes('MAYA')) mStyle = 'background:#ecfdf5;color:#047857;';
    const badgeMethodHtml = `<span style="font-size:10px;font-weight:800;padding:4px 10px;border-radius:20px;letter-spacing:0.04em; ${mStyle}">${data.method||'—'}</span>`;

    body.innerHTML = `
      ${amountWarning}
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;align-items:center;">
        ${badgePosHtml}
        ${badgeTypeHtml}
        ${badgeMethodHtml}
        ${data.status_html}
      </div>
      <div class="pm-field"><span class="pm-label">Order #</span><span class="pm-value order-id-wrap">#${data.order_id}</span></div>
      <div class="pm-field"><span class="pm-label">Customer</span><span class="pm-value">${data.customer_name}</span></div>
      <div class="pm-field">
        <span class="pm-label">Amount Paid</span>
        <div style="text-align:right;">
            <div class="pm-value" style="color:#0f172a;font-size:1.5rem;font-weight:800;">${amt}</div>
            <div style="font-size:11px;color:#64748b;font-weight:500;">Detected via OCR</div>
        </div>
      </div>
      <div class="pm-field">
        <span class="pm-label">Method</span>
        <span class="pm-value">${data.method||'—'}</span>
      </div>
      <div class="pm-field">
        <span class="pm-label">Reference ID</span>
        <div style="display:flex;flex-direction:column;align-items:flex-end;">
            <input type="text" id="edit_ref_id" value="${data.reference_id||''}" class="filter-input" style="text-align:right;width:180px;font-family:monospace;font-weight:700;margin-bottom:4px;" placeholder="No ID detected" readonly>
            <span style="font-size:10px;color:#64748b;">Strict OCR Result (Read-only)</span>
        </div>
      </div>
      <div class="pm-field"><span class="pm-label">Order Status</span><span class="pm-value">${data.raw_status||'—'}</span></div>
      <div class="pm-field"><span class="pm-label">Date</span><span class="pm-value">${dateFmt}</span></div>
      ${proofHtml}
      ${actionHtml}`;

    const modal = document.getElementById('payModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
    document.body.style.overflow = '';
}

function openZModal(url) {
    document.getElementById('imageZoomSrc').src = url;
    document.getElementById('imageZoomDownload').href = url;
    document.getElementById('imageZoomModal').style.display = 'flex';
}
function closeZModal() {
    document.getElementById('imageZoomModal').style.display = 'none';
    document.getElementById('imageZoomSrc').src = '';
}

document.addEventListener('keydown', e => { 
    if(e.key === 'Escape') {
        const zm = document.getElementById('imageZoomModal');
        if (zm && zm.style.display === 'flex') closeZModal();
        else closePayModal();
    }
});

async function doVerify(orderId, action) {
    let reason = null;
    if (action === 'Reject') {
        reason = prompt('Reason for rejection (optional):');
        if (reason === null) return; // cancelled
        if (reason.trim() === '') reason = 'Payment rejected.';
    }
    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('action', action);
    const refInput = document.getElementById('edit_ref_id');
    if (refInput) fd.append('reference_id', refInput.value);
    if (reason) fd.append('reason', reason);
    try {
        const r = await fetch('/printflow/staff/api_verify_payment.php', {method:'POST',body:fd});
        const d = await r.json();
        if (d.success) { closePayModal(); location.reload(); }
        else alert('Error: ' + (d.error || 'Unknown error'));
    } catch(e) { alert('Network error. Please try again.'); }
}
</script>
</body>
</html>
