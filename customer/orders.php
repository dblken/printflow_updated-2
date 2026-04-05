<?php
/**
 * Customer Orders Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');
ensure_ratings_table_exists();

$customer_id = get_user_id();
if (!defined('BASE_URL')) define('BASE_URL', '/printflow');
// Mark notification as read if parameter present
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
}

// Get order statistics for the summary cards
$total_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?", 'i', [$customer_id]);
$total_orders = $total_orders_result[0]['count'] ?? 0;

$pending_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status IN ('Pending', 'Pending Approval', 'For Revision')", 'i', [$customer_id]);
$pending_orders = $pending_orders_result[0]['count'] ?? 0;

$processing_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status IN ('Processing', 'In Production', 'Printing')", 'i', [$customer_id]);
$processing_orders = $processing_orders_result[0]['count'] ?? 0;

$ready_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'Ready for Pickup'", 'i', [$customer_id]);
$ready_orders = $ready_orders_result[0]['count'] ?? 0;

// TikTok style tabs (redirect removed tabs to completed)
$active_tab = $_GET['tab'] ?? 'all';
if (in_array($active_tab, ['torate', 'totalorders'], true)) {
    $active_tab = 'completed';
}

// Tab mappings to exact statuses
$tab_status_map = [
    'pending'    => ['Pending', 'Pending Approval', 'Pending Review', 'For Revision'],
    'approved'   => ['Approved'],
    'toverify'   => ['To Verify', 'Downpayment Submitted', 'Pending Verification'],
    'topay'      => ['To Pay'],
    'production' => ['In Production', 'Processing', 'Printing', 'Paid – In Process'],
    'pickup'     => ['Ready for Pickup'],
    'torate'     => ['To Rate', 'Rated', 'Completed'],
    'completed'  => ['Completed', 'To Rate', 'Rated'],
    'cancelled'  => ['Cancelled'],
    'totalorders' => ['Completed', 'To Rate', 'Rated', 'Finished', 'Released', 'Claimed'],
];

// Statuses where price is hidden from customer
$HIDDEN_PRICE_STATUSES = ['Pending', 'Pending Approval', 'Pending Review', 'For Revision', 'Approved'];

$has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
$has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
$first_product_image_expr = "''";
if ($has_product_image && $has_photo_path) {
    $first_product_image_expr = "COALESCE(p.photo_path, p.product_image)";
} elseif ($has_product_image) {
    $first_product_image_expr = "p.product_image";
} elseif ($has_photo_path) {
    $first_product_image_expr = "p.photo_path";
}

// Per-tab order counts for status indicators.
$status_counts_raw = db_query("
    SELECT status, COUNT(*) AS total
    FROM orders
    WHERE customer_id = ?
    GROUP BY status
", 'i', [$customer_id]);

$status_counts = [];
foreach ($status_counts_raw as $row) {
    $status_counts[$row['status']] = (int)$row['total'];
}

$tab_counts = [
    'all' => (int)$total_orders,
    'pending' => 0,
    'approved' => 0,
    'toverify' => 0,
    'topay' => 0,
    'production' => 0,
    'pickup' => 0,
    'torate' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'totalorders' => 0
];

foreach ($tab_status_map as $tab_key => $statuses) {
    foreach ($statuses as $status_name) {
        $tab_counts[$tab_key] += $status_counts[$status_name] ?? 0;
    }
}

// Build query
$sql = "SELECT o.*, 
        (SELECT GROUP_CONCAT(DISTINCT p.sku ORDER BY p.sku SEPARATOR '-') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as order_sku,
        (SELECT GROUP_CONCAT(COALESCE(p.name, 'Service Order') SEPARATOR ', ') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as item_names,
        (SELECT COALESCE(p.name, 'Service Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_product_name,
        (SELECT p.product_id FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_product_id,
        (SELECT {$first_product_image_expr} FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_product_image,
        (SELECT oi.customization_data FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization,
        (SELECT oi.order_item_id FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_id,
        (SELECT IF(oi.design_image IS NOT NULL AND oi.design_image != '', 1, 0) FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_has_design,
        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.order_id) as total_quantity,
        (SELECT r.rating FROM reviews r WHERE r.order_id = o.order_id LIMIT 1) as rating_value
        FROM orders o WHERE o.customer_id = ?";
$count_sql = "SELECT COUNT(*) as total FROM orders o WHERE o.customer_id = ?";
$params = [$customer_id];
$count_params = [$customer_id]; // Need this for the count query
$types = 'i';
$count_types = 'i'; // Need this for the count query

if ($active_tab !== 'all' && isset($tab_status_map[$active_tab])) {
    $statuses = $tab_status_map[$active_tab];
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    
    $sql .= " AND o.status IN ($placeholders)";
    $count_sql .= " AND o.status IN ($placeholders)";
    
    foreach ($statuses as $s) {
        $params[] = $s;
        $count_params[] = $s; // Also add to count params
        $types .= 's';
        $count_types .= 's'; // Also add to count types
    }
}

// Pagination settings
// "All" tab must always show the complete list (no LIMIT).
$items_per_page = 10;
$current_page = ($active_tab === 'all') ? 1 : max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$total_result = db_query($count_sql, $count_types, $count_params);
$total_items = (int)($total_result[0]['total'] ?? 0);
$total_pages = ($active_tab === 'all') ? 1 : max(1, (int)ceil($total_items / $items_per_page));

// Use inline LIMIT/OFFSET for filtered tabs.
if ($active_tab === 'all') {
    $sql .= " ORDER BY o.order_date DESC";
} else {
    $limit = (int)$items_per_page;
    $offset_val = (int)$offset;
    $sql .= " ORDER BY o.order_date DESC LIMIT {$limit} OFFSET {$offset_val}";
}

$orders_raw = db_query($sql, $types, $params);
$orders = is_array($orders_raw) ? $orders_raw : [];

$page_title = 'My Orders - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Orders Page — light/white background compatible */
.orders-theme-page {
    --lp-text: #0f172a;
    --lp-muted: #64748b;
    --lp-border: #e2e8f0;
    --lp-accent: #0a2530;
    --lp-accent-l: #0e7490;
    color: #0f172a;
    position: relative;
    z-index: 1;
}
.orders-page-container { margin-top: 1rem; margin-bottom: 2rem; max-width: 1100px; margin-left: auto; margin-right: auto; padding: 0 1rem; }

.unified-dashboard {
    background: #ffffff;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    overflow: hidden;
    margin-bottom: 3rem;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
}

.tt-tabs-wrapper {
    position: sticky; top: 0px; z-index: 40;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0 !important;
    border-radius: 0 !important;
    padding: 0.75rem;
}

.tt-tabs {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    overflow-x: auto;
    scrollbar-width: none;
    padding: 0.2rem 0.5rem;
    justify-content: flex-start;
}
@media (min-width: 900px) {
    .tt-tabs { justify-content: space-between; width: 100%; }
    .tt-tab { flex: 1; justify-content: center; }
}
.tt-tabs::-webkit-scrollbar { display: none; }
.tt-tab {
    padding: 0.55rem 0.75rem;
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 700;
    text-decoration: none;
    border-radius: 6px !important;
    transition: all 0.2s;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.tt-tab:hover { color: #0f172a; background: #e2e8f0; }
.tt-tab.active { background: #0a2530; color: #fff; }
.tt-tab-count {
    font-size: 0.65rem;
    background: rgba(0,0,0,0.08);
    padding: 2px 6px;
    border-radius: 4px !important;
    opacity: 0.8;
}
.tt-tab.active .tt-tab-count { background: rgba(255,255,255,0.2); opacity: 1; }

.orders-list-content { background: transparent; }
.ct-order-card {
    padding: 1.15rem 2rem;
    transition: background 0.2s;
    background: #ffffff !important;
    border: none !important;
    border-radius: 0 !important;
    margin-bottom: 0 !important;
    box-shadow: none !important;
    cursor: pointer;
}
.ct-order-card + .ct-order-card { border-top: 1px solid #e2e8f0 !important; }
.ct-order-card:hover { background: #f8fafc !important; }

.card-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}
.order-id-chip {
    font-size: 0.7rem;
    font-weight: 900;
    color: #0e7490;
    background: rgba(14,116,144,0.08);
    padding: 4px 10px;
    border-radius: 6px !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.card-content { display: flex; gap: 1.5rem; align-items: center; }
.img-preview-box {
    width: 70px; height: 70px; flex-shrink: 0;
    border-radius: 8px !important; overflow: hidden;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}
.img-preview-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.ct-order-card:hover .img-preview-box img { transform: scale(1.05); }

.details-column { flex: 1; min-width: 0; }
.order-title { font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 0.35rem; line-height: 1.3; }
.qty-tag { font-size: 0.75rem; color: #0e7490; font-weight: 700; }
.timestamp-text { font-size: 0.72rem; color: #94a3b8; margin-top: 0.4rem; font-weight: 500; }

.pricing-column { text-align: right; min-width: 280px; display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem; }
.final-price { font-size: 1.25rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1; }
.hidden-price-msg { font-size: 0.72rem; color: #94a3b8; font-style: italic; line-height: 1.4; margin-bottom: 0.25rem; }
.card-actions-inline { display: flex; gap: 0.5rem; }

.card-footer-actions {
    display: flex; justify-content: flex-end; gap: 0.85rem;
    margin-top: 0.75rem; padding-top: 0.85rem;
    border-top: 1px solid #e2e8f0;
}
.action-button {
    padding: 0.5rem 1rem;
    border-radius: 6px !important;
    font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
    transition: all 0.2s; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;
}
.btn-chat { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.btn-chat:hover { background: #e2e8f0; color: #0f172a; }
.btn-main { background: #0a2530; color: #fff; border: 1px solid #0a2530; }
.btn-main:hover { background: #0e3a4d; }
.btn-rate-order { background: rgba(251,191,36,0.1); color: #b45309; border: 1px solid rgba(251,191,36,0.4); border-radius: 6px !important; }
.btn-rate-order:hover { background: rgba(251,191,36,0.2); }

.status-pill {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 999px !important;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
}
.st-pending  { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.st-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.st-production { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
.st-ready    { background: #cffafe; color: #155e75; border: 1px solid #a5f3fc; }
.st-completed { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.st-cancelled, .st-unpaid { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.rated-status-tag {
    font-size: 0.72rem; font-weight: 700; color: #b45309;
    padding: 4px 10px; background: #fef9c3;
    border-radius: 6px !important; border: 1px solid #fde68a;
}

.empty-view {
    text-align: center; padding: 5rem 2rem;
    background: transparent; border: none;
}
.empty-view-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem; }
.empty-view-sub { color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem; }

/* Modal stays dark — it overlays everything */
#itemsModal {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 2rem 1rem; opacity: 0; pointer-events: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(8, 12, 15, 0.85);
}
#itemsModal.open { opacity: 1; pointer-events: auto; }
.im-panel {
    background: rgba(10, 37, 48, 0.99) !important;
    border: 1px solid rgba(83,197,224,0.2);
    border-radius: 12px !important;
    width: 100%; max-width: 1150px;
    max-height: calc(100vh - 4rem);
    display: flex; flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    overflow: hidden;
    transform: scale(0.95);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
#itemsModal.open .im-panel { transform: scale(1); }
.im-header {
    padding: 0.75rem 1.25rem;
    background: rgba(83,197,224,0.05);
    border-bottom: 1px solid rgba(83,197,224,0.15);
    display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
}
.im-title { font-size: 1rem; font-weight: 800; color: #fff !important; margin: 0; }
.im-subtitle { font-size: 0.82rem; color: rgba(255,255,255,0.5) !important; margin-top: 4px; font-weight: 600; }
.im-close {
    width: 42px; height: 42px; border-radius: 8px !important;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6);
    cursor: pointer; transition: all 0.2s; font-size: 1.2rem;
}
.im-close:hover { background: rgba(255,100,100,0.1); color: #ff6b6b; }
.im-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
.im-dashboard { display: grid; grid-template-columns: 1fr 340px; gap: 2rem; }
.im-main { display: flex; flex-direction: column; gap: 1.5rem; min-width: 0; }
.im-sidebar { display: flex; flex-direction: column; gap: 1.25rem; }
.im-table { width: 100%; border-collapse: collapse; }
.im-table th { text-align: left; padding: 0.75rem 0.5rem; font-size: 0.7rem; font-weight: 700; color: rgba(255,255,255,0.4); border-bottom: 2px solid rgba(255,255,255,0.08); }
.im-table td { padding: 1.25rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.06); vertical-align: top; color: #eaf6fb; }
.im-sec-card { background: rgba(255,255,255,0.02); border-left: 2px solid rgba(255,255,255,0.1); padding: 0.75rem 1.25rem; display: flex; flex-direction: column; }
.im-sec-card.accent { border-left-color: #53c5e0; background: rgba(83,197,224,0.05); }
.im-label { font-size: 0.68rem; color: rgba(255,255,255,0.4); font-weight: 700; margin-bottom: 6px; }
.im-val { font-size: 0.95rem; font-weight: 800; color: #fff; }
.im-chip { display: inline-flex; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); padding: 2px 8px; border-radius: 4px !important; font-size: 0.65rem; }
.im-thumb { width: 90px; height: 90px; object-fit: cover; border-radius: 6px !important; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); }

#cancelModal {
    position: fixed; inset: 0; z-index: 100000;
    display: flex; align-items: center; justify-content: center;
    padding: 1.5rem; opacity: 0; pointer-events: none;
    transition: all 0.25s ease;
    background: rgba(15,23,42,0.6);
    backdrop-filter: blur(4px);
}
#cancelModal.open { opacity: 1; pointer-events: auto; }
.cm-box {
    background: #0a2530 !important;
    border: 1px solid rgba(83,197,224,0.2);
    border-radius: 20px;
    width: 100%; max-width: 460px;
    padding: 2rem;
    box-shadow: 0 40px 100px rgba(0,0,0,0.5);
}
.cm-opt-label {
    display: flex; align-items: center; gap: 1rem; padding: 1rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px; margin-bottom: 0.6rem;
    cursor: pointer; transition: all 0.2s; color: #fff;
}
.cm-opt-label:hover { background: rgba(255,255,255,0.06); }
.cm-opt-label.active { border-color: #53c5e0; background: rgba(83,197,224,0.1); }

@media (max-width: 640px) {
    .card-content { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .pricing-column { text-align: left; align-items: flex-start; min-width: unset; width: 100%; }
    .card-actions-inline { width: 100%; flex-wrap: wrap; }
    .action-button { flex: 1; justify-content: center; }
    .im-dashboard { grid-template-columns: 1fr; }
}

.capitalize-first { display: inline-block; }
.capitalize-first::first-letter { text-transform: uppercase; }
</style>


<div class="orders-theme-page min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <div class="mb-8 mt-4"></div>
        <!-- Unified Dashboard Container -->
        <div class="unified-dashboard">
            <!-- Sticky Navigation Tabs -->
            <div class="tt-tabs-wrapper">
                <div class="tt-tabs" id="ttTabsScrollContainer">
                    <a href="?tab=all" class="tt-tab <?php echo $active_tab === 'all' ? 'active' : ''; ?>">All <span class="tt-tab-count"><?php echo $tab_counts['all']; ?></span></a>
                    <a href="?tab=pending" class="tt-tab <?php echo $active_tab === 'pending' ? 'active' : ''; ?>">Pending <span class="tt-tab-count"><?php echo $tab_counts['pending']; ?></span></a>
                    <a href="?tab=approved" class="tt-tab <?php echo $active_tab === 'approved' ? 'active' : ''; ?>">Approved <span class="tt-tab-count"><?php echo $tab_counts['approved']; ?></span></a>
                    <a href="?tab=topay" class="tt-tab <?php echo $active_tab === 'topay' ? 'active' : ''; ?>">To Pay <span class="tt-tab-count"><?php echo $tab_counts['topay']; ?></span></a>
                    <a href="?tab=toverify" class="tt-tab <?php echo $active_tab === 'toverify' ? 'active' : ''; ?>">To Verify <span class="tt-tab-count"><?php echo $tab_counts['toverify']; ?></span></a>
                    <a href="?tab=production" class="tt-tab <?php echo $active_tab === 'production' ? 'active' : ''; ?>">Production <span class="tt-tab-count"><?php echo $tab_counts['production']; ?></span></a>
                    <a href="?tab=pickup" class="tt-tab <?php echo $active_tab === 'pickup' ? 'active' : ''; ?>">Ready <span class="tt-tab-count"><?php echo $tab_counts['pickup']; ?></span></a>
                    <a href="?tab=completed" class="tt-tab <?php echo $active_tab === 'completed' ? 'active' : ''; ?>">Completed <span class="tt-tab-count"><?php echo $tab_counts['completed']; ?></span></a>
                    <a href="?tab=cancelled" class="tt-tab <?php echo $active_tab === 'cancelled' ? 'active' : ''; ?>">Cancelled <span class="tt-tab-count"><?php echo $tab_counts['cancelled']; ?></span></a>
                </div>
            </div>

            <!-- Dashboard Content Area -->
            <div class="orders-list-content">
                <?php if (empty($orders)): ?>
                    <div class="empty-view">
                        <div class="empty-view-title">No orders found</div>
                        <div class="empty-view-sub">Orders from this category will show up here.</div>
                        <a href="/printflow/customer/services.php" class="inline-block px-10 py-3.5 bg-slate-900 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all border border-white/5">Browse Services</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $index => $order): ?>
                        <?php 
                            // ... (logic remains same)
                            $s = strtolower($order['status']);
                            $st_cls = 'st-pending';
                            if (strpos($s, 'approved') !== false) $st_cls = 'st-approved';
                            elseif (strpos($s, 'production') !== false || strpos($s, 'processing') !== false || strpos($s, 'printing') !== false) $st_cls = 'st-production';
                            elseif (strpos($s, 'ready') !== false || strpos($s, 'pickup') !== false) $st_cls = 'st-ready';
                            elseif (strpos($s, 'completed') !== false || strpos($s, 'rated') !== false || strpos($s, 'rate') !== false) $st_cls = 'st-completed';
                            elseif (strpos($s, 'cancelled') !== false) $st_cls = 'st-cancelled';
                            
                            $c_json = !empty($order['first_item_customization']) ? json_decode($order['first_item_customization'], true) : [];
                            $d_name = '';
                            if (!empty($c_json['sintra_type'])) $d_name = 'Sintra Board - ' . $c_json['sintra_type'];
                            elseif (!empty($c_json['tarp_size'])) $d_name = 'Tarpaulin Printing - ' . $c_json['tarp_size'];
                            elseif (!empty($c_json['width']) && !empty($c_json['height'])) $d_name = 'Tarpaulin Printing - ' . $c_json['width'] . 'x' . $c_json['height'] . 'ft';
                            elseif (!empty($c_json['vinyl_type'])) $d_name = 'T-Shirt (Vinyl)';
                            elseif (!empty($c_json['sticker_type'])) $d_name = 'Decals/Stickers';
                            
                            if (!$d_name) {
                                $raw_name = $order['first_product_name'] ?? 'Order Item';
                                $genericNames = ['custom order', 'customer order', 'service order', 'order item', 'sticker pack', 'merchandise'];
                                if (empty($raw_name) || in_array(strtolower(trim($raw_name)), $genericNames)) {
                                    $d_name = get_service_name_from_customization($c_json, 'Order Item');
                                } else {
                                    $d_name = normalize_service_name($raw_name, 'Order Item');
                                }
                            }
                            $preview_url = get_preview_image_for_order_ui($order, $d_name);
                        ?>
                        <div class="ct-order-card" id="order-card-<?php echo $order['order_id']; ?>" data-order-id="<?php echo $order['order_id']; ?>" onclick="openItemsModal(<?php echo $order['order_id']; ?>)">
                            <div class="card-top-row">
                                <span class="order-id-chip">Order #<?php echo $order['order_id']; ?></span>
                                <div class="status-pill <?php echo $st_cls; ?>"><?php echo htmlspecialchars($order['status']); ?></div>
                            </div>

                            <div class="card-content">
                                <div class="img-preview-box"><img src="<?php echo htmlspecialchars($preview_url); ?>" alt="Preview" onerror="this.src='/printflow/public/assets/images/services/default.png';"></div>
                                <div class="details-column">
                                    <h3 class="order-title"><?php echo htmlspecialchars($d_name); ?></h3>
                                    <div class="qty-tag"><?php echo max(1, (int)($order['total_quantity'] ?? 0)); ?> Items</div>
                                    <p class="timestamp-text"><?php echo format_datetime($order['order_date']); ?></p>
                                </div>
                                <div class="pricing-column">
                                    <div class="mb-1">
                                        <?php if (in_array($order['status'], $HIDDEN_PRICE_STATUSES)): ?>
                                            <p class="hidden-price-msg">Quote is being finalized by our production team</p>
                                        <?php else: ?>
                                            <p class="final-price"><?php echo format_currency($order['total_amount']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-actions-inline" onclick="event.stopPropagation()">
                                        <a href="<?php echo BASE_URL; ?>/customer/chat.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-chat" style="padding: 0.45rem 0.85rem; font-size: 0.68rem;">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                            Message
                                        </a>
                                        <?php if (strtolower(trim($order['status'])) === 'to pay'): ?>
                                        <a href="<?php echo BASE_URL; ?>/customer/payment.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-main" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); padding: 0.45rem 0.85rem; font-size: 0.68rem; position: relative; z-index: 10; white-space: nowrap;">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            Pay Now
                                        </a>
                                        <?php endif; ?>
                                        <button class="action-button btn-main" style="padding: 0.45rem 0.85rem; font-size: 0.68rem;" onclick="openItemsModal(<?php echo $order['order_id']; ?>)">View Details</button>
                                        <?php if (in_array($order['status'], ['Completed', 'To Rate', 'Rated'], true)): ?>
                                            <?php if (empty($order['rating_value'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/customer/rate_order.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-rate-order" style="padding: 0.45rem 0.85rem; font-size: 0.68rem;">
                                                    ★ Rate
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo BASE_URL; ?>/customer/reviews.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-rate-order" style="padding: 0.45rem 0.85rem; font-size: 0.68rem; opacity: 0.7;">
                                                    ★ Rated
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Image Lightbox Modal -->
        <div id="lightboxModal" class="fixed inset-0 z-[100001] hidden flex items-center justify-center p-4 bg-black/90" onclick="closeLightbox()">
            <img id="lightboxImg" class="max-w-full max-h-full shadow-2xl transition-transform duration-300 transform scale-95" src="" alt="Full Preview">
            <div class="absolute top-6 right-6 text-white cursor-pointer hover:text-red-400 transition-colors">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
        </div>

            <?php if ($active_tab !== 'all'): ?>
                <div class="mt-12">
                    <?php echo get_pagination_links($current_page, $total_pages, ['tab' => $active_tab]); ?>
                </div>
            <?php endif; ?>
    </div>
</div>

<?php 
// Inline helper for this specific page theme
function get_preview_image_for_order_ui($order, $display_name) {
    if (!empty($order['first_item_has_design']) && !empty($order['first_item_id'])) {
        return "/printflow/public/serve_design.php?type=order_item&id=" . (int)$order['first_item_id'];
    }
    $product_img = "";
    $pn = trim($order['first_product_name'] ?? '');
    if ($pn && strtolower($display_name) === strtolower($pn)) {
        if (!empty($order['first_product_image'])) {
            $img = $order['first_product_image'];
            if ($img[0] !== '/' && strpos($img, 'http') === false) {
                if (file_exists(__DIR__ . '/../uploads/products/' . $img)) {
                    return '/printflow/uploads/products/' . $img;
                }
            } else {
                return $img;
            }
        }
        $prod_id = (int)($order['first_product_id'] ?? 0);
        if ($prod_id > 0) {
            $img_base = __DIR__ . "/../public/images/products/product_" . $prod_id;
            if (file_exists($img_base . ".jpg")) return "/printflow/public/images/products/product_" . $prod_id . ".jpg";
            if (file_exists($img_base . ".png")) return "/printflow/public/images/products/product_" . $prod_id . ".png";
        }
    }
    return get_service_image_url($display_name);
}
?>

<script>
document.body.classList.add('orders-page');

// ── Highlight + scroll to a specific order card from notification ──
window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const highlightId = params.get('highlight');
    if (highlightId) {
        // Open the details modal immediately for maximum responsiveness
        if (typeof openItemsModal === 'function') {
            openItemsModal(highlightId);
        }

        const card = document.querySelector(`[data-order-id="${highlightId}"]`);
        if (card) {
            // Scroll into view with a slight delay so the page fully renders
            setTimeout(() => {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Apply a teal highlight pulse
                card.style.transition = 'box-shadow 0.3s, border-color 0.3s';
                card.style.borderColor = 'rgba(83, 197, 224, 0.85)';
                card.style.boxShadow = '0 0 0 3px rgba(83, 197, 224, 0.35), 0 16px 48px rgba(2, 12, 18, 0.5)';
                // Fade back after 2.5s
                setTimeout(() => {
                    card.style.borderColor = '';
                    card.style.boxShadow = '';
                }, 2500);
            }, 300);
        }
    }

    <?php if (isset($_SESSION['success'])): 
        $msg = $_SESSION['success'];
        unset($_SESSION['success']);
    ?>
    showSuccessModal(
        'Action Completed',
        '<?php echo addslashes($msg); ?>',
        'orders.php',
        'services.php',
        'Refresh List',
        'Back to Services'
    );
    <?php endif; ?>
});
</script>

<!-- Modal: Order Details -->
<div id="itemsModal" onclick="if(event.target === this) closeItemsModal()">
    <div class="im-panel">
        <div class="im-header">
            <div>
                <h2 class="im-title" id="imTitle">Order Details</h2>
                <p class="im-subtitle" id="imSubtitle"></p>
            </div>
            <button class="im-close" onclick="closeItemsModal()">✕</button>
        </div>
        <div class="im-body" id="imBody">
            <div class="flex flex-col items-center justify-center py-16">
                <div class="w-10 h-10 border-4 border-white/10 border-t-blue-400 rounded-full animate-spin"></div>
                <p class="mt-4 text-slate-400 font-bold text-sm">Gathering details...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cancellation -->
<div id="cancelModal" onclick="if(event.target === this) closeCancelModal()">
    <div class="cm-box">
        <h2 class="text-2xl font-black text-white mb-2">Cancel Order?</h2>
        <p class="text-slate-400 font-medium text-sm mb-6">Please tell us why you want to cancel. This action cannot be undone once confirmed.</p>
        
        <div class="space-y-2">
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Change of mind"><span class="font-bold">Change of mind</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Incorrect order details"><span class="font-bold">Incorrect details</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Found another provider"><span class="font-bold">Found cheaper elsewhere</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Other"><span class="font-bold">Other reasons</span></label>
            <textarea id="cmOtherInput" class="w-full mt-3 p-4 bg-white/5 border-2 border-white/10 rounded-2xl hidden focus:border-blue-400 transition-all outline-none text-sm font-medium text-white" placeholder="Please specify your reason..."></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-8">
            <button class="btn-secondary" onclick="closeCancelModal()">Go Back</button>
            <button class="btn-danger" id="cmConfirmBtn" onclick="submitOrderCancellation()" disabled>Cancel Order</button>
        </div>
    </div>
</div>

<script>
function imBadge(val) {
    const s = String(val || '').toLowerCase();
    let cls = 'st-pending';
    if (s.includes('unpaid')) cls = 'st-unpaid';
    else if (s.includes('approved')) cls = 'st-approved';
    else if (s.includes('production') || s.includes('processing') || s.includes('printing')) cls = 'st-production';
    else if (s.includes('ready') || s.includes('pickup')) cls = 'st-ready';
    else if (s.includes('completed') || s.includes('rated') || s.includes('paid')) cls = 'st-completed';
    else if (s.includes('cancelled')) cls = 'st-cancelled';
    return `<span class="status-pill ${cls}">${escIM(val)}</span>`;
}

function openItemsModal(orderId) {
    const modal = document.getElementById('itemsModal');
    document.getElementById('imTitle').textContent = `Order #${orderId}`;
    document.getElementById('imSubtitle').textContent = 'Fetching data...';
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch(`/printflow/customer/get_order_items.php?id=${orderId}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            document.getElementById('imBody').innerHTML = `<p class="text-red-500 font-bold text-center">${escIM(data.error)}</p>`;
            return;
        }

        document.getElementById('imSubtitle').textContent = 'Order placed on ' + data.order_date;

        // Safety check for items
        const itemsList = Array.isArray(data.items) ? data.items : [];
        const rows = itemsList.map(item => {
            let chips = '';
            if (item.customization) {
                const chipItems = Object.entries(item.customization)
                    .filter(([k,v]) => v && v !== 'No' && v !== 'None' && !['service_type','product_type','quantity','notes','design_upload','reference_upload'].includes(k))
                    .map(([k,v]) => `<span class="im-chip capitalize-first">${k.replace(/_/g,' ')}: ${escIM(v)}</span>`)
                    .join('');
                if (chipItems) chips = `<div class="im-chips">${chipItems}</div>`;
            }
            
            const design = item.has_design ? `<div class="block cursor-zoom-in" onclick="openLightbox('${item.design_url}')"><div class="text-[9px] font-black text-slate-400 uppercase mb-1">Final Design</div><img src="${item.design_url}" class="im-thumb hover:scale-105 transition-transform" alt="Design"></div>` : '';
            const reference = item.has_reference ? `<div class="block cursor-zoom-in" onclick="openLightbox('${item.reference_url}')"><div class="text-[9px] font-black text-slate-400 uppercase mb-1">Reference</div><img src="${item.reference_url}" class="im-thumb hover:scale-105 transition-transform" alt="Reference"></div>` : '';

            return `<tr>
                <td style="min-width: 280px;">
                    <div class="font-black text-white text-[15px] leading-tight mb-2 capitalize-first">${escIM(item.product_name)}</div>
                    <div class="space-y-4">
                        <div>
                            <div class="text-[9px] font-bold text-slate-500 tracking-widest mb-1">Specifications</div>
                            <div class="flex flex-wrap gap-1.5">${chips}</div>
                        </div>
                        ${design || reference ? `
                            <div>
                                <div class="text-[9px] font-bold text-slate-500 tracking-widest mb-1">Assets</div>
                                <div class="flex gap-3">${design}${reference}</div>
                            </div>
                        ` : ''}
                    </div>
                </td>
                <td class="text-center font-black text-slate-200 text-base">${item.quantity}</td>
                <td class="font-black text-slate-300 text-[15px] whitespace-nowrap text-right">${escIM(item.unit_price).replace(' ', '&nbsp;')}</td>
                <td class="font-black text-white text-[15px] whitespace-nowrap text-right">${escIM(item.subtotal).replace(' ', '&nbsp;')}</td>
            </tr>`;
        }).join('');

        document.getElementById('imBody').innerHTML = `
            <div class="im-dashboard">
                <!-- Left: Order Items & Designs -->
                <div class="im-main">
                    <div class="overflow-x-auto">
                        <table class="im-table">
                            <thead><tr><th>Service detail</th><th class="text-center">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>

                    ${data.notes ? `
                        <div class="im-sec-card" style="border-left-color: var(--lp-accent-l); background: rgba(83, 197, 224, 0.03);">
                            <div class="im-label text-blue-400">Order instructions</div>
                            <div class="text-sm text-blue-100 font-medium italic capitalize-first">"${escIM(data.notes)}"</div>
                        </div>
                    ` : ''}
                </div>

                <!-- Right: Metadata & Status Sidebar -->
                <div class="im-sidebar">
                    <div class="im-sec-card accent">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="im-label">Current status</div>
                                <div class="text-lg font-black text-white leading-tight">${data.status}</div>
                            </div>
                            <div style="transform: scale(0.9); transform-origin: top right;">${imBadge(data.status)}</div>
                        </div>
                        <div class="im-label mt-4">Branch processing</div>
                        <div class="text-xs font-bold text-slate-300 letter-spacing-wider capitalize-first">${escIM(data.branch_name)}</div>
                    </div>

                    <div class="im-sec-card">
                        <div class="im-label">Payment information</div>
                        <div class="space-y-4">
                            <div><div class="text-[9px] text-slate-400 font-bold mb-1">Method</div><div class="im-val text-sm capitalize-first">${escIM(data.payment_method).toLowerCase()}</div></div>
                            <div><div class="text-[9px] text-slate-400 font-bold mb-1">Status</div><div>${imBadge(data.payment_status)}</div></div>
                        </div>
                    </div>

                    <div class="im-sec-card" style="border-left-color: #fbbf24;">
                        <div class="im-label text-amber-400">Estimated completion</div>
                        <div class="im-val text-sm font-black text-amber-500">${escIM(data.estimated_comp || 'Gathering timeframe...')}</div>
                    </div>

                    <!-- Actions Area -->
                    <div class="mt-auto pt-4 space-y-3">
                        ${data.design_status === 'Revision Requested' ? `
                            <div class="p-3 bg-amber-500/10 border border-amber-500/20">
                                <div class="im-label text-amber-400 mb-1">Revision requested</div>
                                <p class="text-[11px] text-amber-200/80 font-medium mb-3">${escIM(data.revision_reason)}</p>
                                <button onclick="triggerDesignReupload(${data.order_id})" class="w-full py-2.5 bg-amber-500 text-white text-xs font-black hover:bg-amber-600 transition-all">Re-upload corrected design</button>
                                <input type="file" id="designReuploadInput-${data.order_id}" style="display:none;" onchange="handleDesignReupload(this, ${data.order_id}, '${data.csrf_token}')" accept="image/*,application/pdf">
                            </div>
                        ` : ''}

                        ${['Completed', 'To Rate', 'Rated'].includes(data.status) ? (
                            data.rating_data
                                ? `<a href="/printflow/customer/reviews.php?order_id=${data.order_id}" class="w-full py-3 text-amber-400 text-xs font-black border border-amber-400/20 hover:bg-amber-400/10 transition-all tracking-widest flex items-center justify-center gap-2">★ View Your Review</a>`
                                : `<a href="/printflow/customer/rate_order.php?order_id=${data.order_id}" class="w-full py-3 bg-amber-500/10 text-amber-400 text-xs font-black border border-amber-400/30 hover:bg-amber-400/20 transition-all tracking-widest flex items-center justify-center gap-2">★ Rate This Order</a>`
                        ) : ''}

                        ${data.can_cancel ? `
                            <button onclick="openCancelModal(${data.order_id}, '${data.csrf_token}')" class="w-full py-3 text-red-400 text-xs font-black border border-red-400/20 hover:bg-red-400/10 transition-all tracking-widest">Cancel order request</button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;             ` : ''}
            </div>
        `;
    })
    .catch(() => {
        document.getElementById('imBody').innerHTML = `<p class="text-red-500 font-bold text-center">Connection error. Please try again.</p>`;
    });
}

function closeItemsModal() {
    document.getElementById('itemsModal').classList.remove('open');
    document.body.style.overflow = '';
}

// Cancellation Logic
let cancelOrderId = null, cancelCsrfToken = null;
function openCancelModal(id, token) {
    cancelOrderId = id; cancelCsrfToken = token;
    document.getElementById('cancelModal').classList.add('open');
}
function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('open');
}

document.addEventListener('change', e => {
    if (e.target.name === 'cancel_reason') {
        document.querySelectorAll('.cm-opt-label').forEach(l => l.classList.remove('active'));
        e.target.closest('.cm-opt-label').classList.add('active');
        document.getElementById('cmOtherInput').classList.toggle('hidden', e.target.value !== 'Other');
        document.getElementById('cmConfirmBtn').disabled = false;
    }
});

function submitOrderCancellation() {
    const reasonEl = document.querySelector('input[name="cancel_reason"]:checked');
    if (!reasonEl) return;
    const reason = reasonEl.value, details = document.getElementById('cmOtherInput').value;
    if (reason === 'Other' && !details.trim()) { alert("Please specify the reason."); return; }

    const btn = document.getElementById('cmConfirmBtn');
    btn.disabled = true; btn.textContent = 'Processing...';

    const fd = new FormData();
    fd.append('ajax', '1'); fd.append('order_id', cancelOrderId);
    fd.append('csrf_token', cancelCsrfToken); fd.append('reason', reason); fd.append('details', details);

    fetch('/printflow/customer/cancel_order.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert("Order Cancelled Successfullly.");
            window.location.reload();
        } else {
            alert(data.error || "Failed to cancel.");
            btn.disabled = false; btn.textContent = 'Cancel Order';
        }
    });
}

function triggerDesignReupload(orderId) { document.getElementById('designReuploadInput-' + orderId).click(); }
function handleDesignReupload(input, orderId, csrfToken) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (!confirm(`Upload "${file.name}"?`)) return;

    const fd = new FormData();
    fd.append('order_id', orderId); fd.append('csrf_token', csrfToken); fd.append('design_file', file);
    
    fetch('/printflow/customer/reupload_design_process.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) window.location.reload();
        else alert(res.error || 'Upload failed');
    });
}

function openLightbox(url) {
    const lb = document.getElementById('lightboxModal');
    const img = document.getElementById('lightboxImg');
    img.src = url;
    lb.classList.remove('hidden');
    lb.classList.add('flex');
    setTimeout(() => { img.classList.remove('scale-95'); img.classList.add('scale-100'); }, 10);
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    const lb = document.getElementById('lightboxModal');
    const img = document.getElementById('lightboxImg');
    img.classList.remove('scale-100'); img.classList.add('scale-95');
    setTimeout(() => {
        lb.classList.remove('flex');
        lb.classList.add('hidden');
        document.body.style.overflow = '';
    }, 200);
}

function escIM(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Polling Logic (Updated for New Classes) ───────────────────
(function startOrdersPolling() {
    const activeTab = '<?php echo addslashes($active_tab); ?>';
    if (activeTab === 'all' || window.__ordersPollingInterval) return;

    const statusMap = {
        'Pending': 'st-pending', 'Pending Approval': 'st-pending', 'Pending Review': 'st-pending',
        'Approved': 'st-approved', 'To Pay': 'st-pending', 'To Verify': 'st-pending',
        'In Production': 'st-production', 'Processing': 'st-production', 'Printing': 'st-production',
        'Ready for Pickup': 'st-ready', 'To Receive': 'st-ready', 'Completed': 'st-completed',
        'To Rate': 'st-completed', 'Rated': 'st-completed', 'Cancelled': 'st-cancelled'
    };

    function poll() {
        fetch('/printflow/customer/api_customer_orders.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            data.orders.forEach(order => {
                const card = document.getElementById('order-card-' + order.order_id);
                if (!card || card.dataset.status === order.status) return;

                card.dataset.status = order.status;
                const pill = card.querySelector('.status-pill');
                if (pill) {
                    pill.textContent = order.status;
                    pill.className = 'status-pill ' + (statusMap[order.status] || 'st-pending');
                }
                const priceEl = card.querySelector('.final-price');
                if (priceEl && order.total_amount) {
                    priceEl.textContent = '₱' + parseFloat(order.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
                }
                
                card.style.transition = 'background 0.3s';
                card.style.background = 'rgba(83, 197, 224, 0.12)'; // Brief teal highlight
                setTimeout(() => { card.style.background = ''; card.style.transition = ''; }, 1800);
            });
        });
    }
    window.__ordersPollingInterval = setInterval(poll, 10000);
})();

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeItemsModal(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>