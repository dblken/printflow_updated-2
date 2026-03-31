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

<link rel="stylesheet" href="/printflow/public/assets/css/chat.css">

<style>
/* Modern Professional Light Theme for Orders */
.orders-theme-page {
    background-color: #f8fafc;
    color: #1e293b;
    font-family: 'Outfit', 'Inter', system-ui, sans-serif;
    padding: 2.5rem 0;
}

body.customer-theme.orders-page #main-content {
    background-color: #f8fafc !important;
}

.orders-theme-page .container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

/* Tabs: Apple/Modern Style Pills */
.tt-tabs-wrapper {
    position: sticky;
    top: 64px;
    z-index: 40;
    background: rgba(248, 250, 252, 0.85); /* Matches body bg but with blur */
    backdrop-filter: blur(12px);
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 2rem;
    padding: 0.75rem 0.5rem;
}

.tt-tabs {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: nowrap;
    padding-bottom: 2px;
}

.tt-tabs::-webkit-scrollbar { display: none; }

.tt-tab {
    padding: 0.45rem 1rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    white-space: nowrap;
    border-radius: 99px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    background: transparent;
    border: 1px solid transparent;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    text-decoration: none !important;
}

.tt-tab:hover {
    color: #0f172a;
    background: #f1f5f9;
}

.tt-tab.active {
    background: #0a2530;
    color: #fff;
    box-shadow: 0 4px 12px rgba(10, 37, 48, 0.15);
}

.tt-tab-count {
    font-size: 0.7rem;
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 99px;
    min-width: 1.5rem;
    text-align: center;
    display: inline-block;
    transition: all 0.2s;
    font-weight: 800;
}

.tt-tab.active .tt-tab-count {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

/* Order Cards: High-End Clean Design */
.ct-order-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 1.5rem;
    padding: 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -2px rgba(0, 0, 0, 0.04);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    display: block !important; /* Ensure visibility */
    opacity: 1 !important;
}

.ct-order-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.08), 0 8px 8px -8px rgba(0, 0, 0, 0.04);
    border-color: #cbd5e1;
}

.card-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f8fafc;
}

.order-id-chip {
    font-size: 0.7rem;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    background: #f8fafc;
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
}

.card-content {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}

.img-preview-box {
    width: 96px;
    height: 96px;
    border-radius: 1.25rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.img-preview-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.ct-order-card:hover .img-preview-box img { transform: scale(1.08); }

.details-column {
    flex: 1;
    min-width: 0;
}

.order-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 0.4rem 0;
    letter-spacing: -0.01em;
}

.qty-tag {
    font-size: 0.75rem;
    font-weight: 800;
    background: #f1f5f9;
    color: #475569;
    padding: 3px 10px;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 0.5rem;
}

.timestamp-text {
    font-size: 0.78rem;
    color: #94a3b8;
    font-weight: 600;
    margin: 0;
}

.pricing-column {
    text-align: right;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.final-price {
    font-size: 1.35rem;
    font-weight: 950;
    color: #0f172a;
    margin: 0;
}

.hidden-price-msg {
    font-size: 0.72rem;
    color: #94a3b8;
    font-style: italic;
    font-weight: 600;
    margin: 0;
    max-width: 140px;
    line-height: 1.4;
}

/* Actions Bar */
.card-footer-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.85rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f8fafc;
}

.action-button {
    padding: 0.7rem 1.4rem;
    border-radius: 1rem;
    font-weight: 700;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none !important;
    border: none;
    cursor: pointer;
}

.btn-chat {
    background: #f8fafc;
    color: #0a2530;
    border: 1px solid #e2e8f0;
}
.btn-chat:hover { background: #f1f5f9; border-color: #cbd5e1; color: #000; }

.btn-main {
    background: #0a2530;
    color: #fff;
    box-shadow: 0 4px 12px rgba(10, 37, 48, 0.12);
}
.btn-main:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 8px 16px rgba(10, 37, 48, 0.2); }

.btn-rate-order {
    background: #f59e0b;
    color: #fff;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2);
}

.rated-status-tag {
    font-size: 0.75rem;
    font-weight: 800;
    color: #059669;
    background: #ecfdf5;
    padding: 0.5rem 0.85rem;
    border-radius: 0.75rem;
    border: 1px solid #d1fae5;
}

/* Status Labels */
.status-pill {
    padding: 5px 12px;
    border-radius: 99px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: inline-block;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.st-pending { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
.st-approved { background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; }
.st-production { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
.st-ready { background: #f5f3ff; color: #5b21b6; border: 1px solid #ede9fe; }
.st-completed { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
.st-cancelled { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

/* Empty Placeholder */
.empty-view {
    padding: 6rem 2rem;
    text-align: center;
    background: #fff;
    border-radius: 2rem;
    border: 1px dashed #e2e8f0;
}
.empty-view-icon { font-size: 4rem; opacity: 0.6; margin-bottom: 1.5rem; }
.empty-view-title { font-size: 1.4rem; font-weight: 900; color: #0f172a; margin-bottom: 0.5rem; }
.empty-view-sub { color: #64748b; font-weight: 500; font-size: 0.95rem; }

@media (max-width: 768px) {
    .tt-tabs-wrapper { margin-left: -1rem; margin-right: -1rem; }
}

@media (max-width: 640px) {
    .ct-order-card { padding: 1.15rem; }
    .card-content { flex-direction: column; gap: 1rem; }
    .img-preview-box { width: 100%; height: 160px; }
    .img-preview-box img { object-fit: contain; background: #fafafa; }
    .pricing-column { text-align: left; margin-top: 5px; }
    .card-footer-actions { flex-direction: column; gap: 0.65rem; }
    .action-button { width: 100%; justify-content: center; }
}
</style>

<div class="orders-theme-page">
    <div class="container">
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

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="empty-view">
                <div class="empty-view-icon">📦</div>
                <div class="empty-view-title">No orders found</div>
                <div class="empty-view-sub">Orders from this category will show up here.</div>
                <a href="/printflow/customer/services.php" class="inline-block mt-8 px-8 py-3 bg-slate-900 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all">Browse Services</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $index => $order): ?>
                <?php 
                    // Determine Status CSS Class
                    $s = strtolower($order['status']);
                    $st_cls = 'st-pending';
                    if (strpos($s, 'approved') !== false) $st_cls = 'st-approved';
                    elseif (strpos($s, 'production') !== false || strpos($s, 'processing') !== false || strpos($s, 'printing') !== false) $st_cls = 'st-production';
                    elseif (strpos($s, 'ready') !== false || strpos($s, 'pickup') !== false) $st_cls = 'st-ready';
                    elseif (strpos($s, 'completed') !== false || strpos($s, 'rated') !== false || strpos($s, 'rate') !== false) $st_cls = 'st-completed';
                    elseif (strpos($s, 'cancelled') !== false) $st_cls = 'st-cancelled';
                    
                    // Display Name Logic
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

                    // Preview Image Logic
                    $preview_url = get_preview_image_for_order_ui($order, $d_name);
                ?>
                <div class="ct-order-card" id="order-card-<?php echo $order['order_id']; ?>" data-order-id="<?php echo $order['order_id']; ?>" data-status="<?php echo htmlspecialchars($order['status']); ?>" onclick="openItemsModal(<?php echo $order['order_id']; ?>)">
                    <div class="card-top-row">
                        <span class="order-id-chip">Order #<?php echo $order['order_id']; ?></span>
                        <div class="status-pill <?php echo $st_cls; ?>"><?php echo htmlspecialchars($order['status']); ?></div>
                    </div>

                    <div class="card-content">
                        <div class="img-preview-box">
                            <img src="<?php echo htmlspecialchars($preview_url); ?>" alt="Preview" onerror="this.src='/printflow/public/assets/images/services/default.png';">
                        </div>

                        <div class="details-column">
                            <h3 class="order-title"><?php echo htmlspecialchars($d_name); ?></h3>
                            <div class="qty-tag"><?php echo max(1, (int)($order['total_quantity'] ?? 0)); ?> Items</div>
                            <p class="timestamp-text"><?php echo format_datetime($order['order_date']); ?></p>
                            
                            <?php 
                                $unread = get_unread_chat_count($order['order_id'], 'Customer');
                                if ($unread > 0): 
                            ?>
                                <div class="mt-2 inline-flex items-center gap-1.5 bg-red-50 text-red-600 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border border-red-100">
                                    <span class="w-1.5 h-1.5 bg-red-600 rounded-full animate-pulse"></span>
                                    <?php echo $unread; ?> NEW MESSAGE<?php echo $unread > 1 ? 'S' : ''; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="pricing-column">
                            <?php if (in_array($order['status'], $HIDDEN_PRICE_STATUSES)): ?>
                                <p class="hidden-price-msg">Quote is being finalized by our production team</p>
                            <?php else: ?>
                                <p class="final-price"><?php echo format_currency($order['total_amount']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-footer-actions" onclick="event.stopPropagation()">
                        <a href="<?php echo BASE_URL; ?>/customer/chat.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-chat">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            Message Shop
                        </a>
                        
                        <?php if (in_array($order['status'], ['Completed', 'To Rate', 'Rated'], true)): ?>
                            <?php $rat_val = (int)($order['rating_value'] ?? 0); ?>
                            <?php if ($rat_val > 0): ?>
                                <div class="rated-status-tag">Rated <?php echo str_repeat('★', $rat_val); ?></div>
                            <?php else: ?>
                                <a href="/printflow/customer/rate_order.php?order_id=<?php echo (int)$order['order_id']; ?>" class="action-button btn-rate-order">Rate Now</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <button class="action-button btn-main" onclick="openItemsModal(<?php echo $order['order_id']; ?>)">View Details</button>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($active_tab !== 'all'): ?>
                <div class="mt-12">
                    <?php echo get_pagination_links($current_page, $total_pages, ['tab' => $active_tab]); ?>
                </div>
            <?php endif; ?>
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
        if (!empty($order['first_product_image'])) return $order['first_product_image'];
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

<!-- ══ Order Items Modal ══ -->
<style>
/* Refined Modal Overlay & Panel */
#itemsModal {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem; opacity: 0; pointer-events: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(8px);
}
#itemsModal.open { opacity: 1; pointer-events: auto; }

.im-panel {
    background: #fff;
    border-radius: 2rem;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    transform: translateY(20px) scale(0.98);
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
#itemsModal.open .im-panel { transform: translateY(0) scale(1); }

.im-header {
    background: #fff;
    padding: 1.25rem 1.75rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.im-title { font-size: 1.35rem; font-weight: 900; color: #0f172a; margin: 0; }
.im-subtitle { font-size: 0.8rem; color: #64748b; margin-top: 4px; font-weight: 600; }

.im-close {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;
    cursor: pointer; transition: all 0.2s; font-size: 1.25rem;
}
.im-close:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }

.im-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.im-thumb {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 16px;
    border: 2px solid #f1f5f9;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

/* Modal Table */
.im-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.im-table th {
    text-align: left; padding: 0.75rem 0.5rem;
    font-size: 0.7rem; font-weight: 800; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 0.08em;
    border-bottom: 1px solid #f1f5f9;
}
.im-table td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.im-table tr:last-child td { border-bottom: none; }

.im-summary-box {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid #f1f5f9;
}

.im-chips { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.85rem; }
.im-chip {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding: 0.35rem 0.85rem;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}

.im-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
    margin-top: 2rem;
}
.im-card {
    padding: 1.25rem;
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 1.25rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.im-label { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; font-weight: 900; margin-bottom: 6px; letter-spacing: 0.1em; }
.im-val { font-size: 0.95rem; font-weight: 800; color: #1e293b; }

/* Cancel Modal Override */
#cancelModal {
    position: fixed; inset: 0; z-index: 100000;
    display: flex; align-items: center; justify-content: center;
    padding: 1.5rem; opacity: 0; pointer-events: none;
    transition: all 0.25s ease;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
}
#cancelModal.open { opacity: 1; pointer-events: auto; }
.cm-box {
    background: #fff; border-radius: 1.75rem; width: 100%; max-width: 440px;
    padding: 2.25rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    transform: scale(0.95); transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
#cancelModal.open .cm-box { transform: scale(1); }

.cm-opt-label {
    display: flex; align-items: center; gap: 1rem; padding: 1.15rem;
    border: 2px solid #f1f5f9; border-radius: 1.25rem; margin-bottom: 0.65rem;
    cursor: pointer; transition: all 0.2s;
}
.cm-opt-label:hover { background: #f8fafc; border-color: #e2e8f0; }
.cm-opt-label.active { border-color: #0a2530; background: #f8fafc; }
.cm-opt-label input { width: 1.25rem; height: 1.25rem; accent-color: #0a2530; }

.btn-secondary { background: #f1f5f9; color: #475569; border: none; padding: 1rem; border-radius: 1.25rem; font-weight: 800; cursor: pointer; transition: all 0.2s; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-danger { background: #ef4444; color: #fff; border: none; padding: 1rem; border-radius: 1.25rem; font-weight: 800; cursor: pointer; transition: all 0.2s; }
.btn-danger:hover { background: #dc2626; box-shadow: 0 8px 16px rgba(239, 68, 68, 0.25); }
</style>

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
                <div class="w-10 h-10 border-4 border-slate-200 border-t-slate-900 rounded-full animate-spin"></div>
                <p class="mt-4 text-slate-400 font-bold text-sm">Gathering details...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cancellation -->
<div id="cancelModal" onclick="if(event.target === this) closeCancelModal()">
    <div class="cm-box">
        <h2 class="text-2xl font-black text-slate-900 mb-2">Cancel Order?</h2>
        <p class="text-slate-500 font-medium text-sm mb-6">Please tell us why you want to cancel. This action cannot be undone once confirmed.</p>
        
        <div class="space-y-2">
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Change of mind"><span class="font-bold text-slate-700">Change of mind</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Incorrect order details"><span class="font-bold text-slate-700">Incorrect details</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Found another provider"><span class="font-bold text-slate-700">Found cheaper elsewhere</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Other"><span class="font-bold text-slate-700">Other reasons</span></label>
            <textarea id="cmOtherInput" class="w-full mt-3 p-4 border-2 border-slate-100 rounded-2xl hidden focus:border-slate-900 transition-all outline-none text-sm font-medium" placeholder="Please specify your reason..."></textarea>
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
    if (s.includes('approved')) cls = 'st-approved';
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

        const rows = data.items.map(item => {
            let chips = '';
            if (item.customization) {
                const chipItems = Object.entries(item.customization)
                    .filter(([k,v]) => v && v !== 'No' && v !== 'None' && !['service_type','product_type','quantity','notes','design_upload','reference_upload'].includes(k))
                    .map(([k,v]) => `<span class="im-chip">${k.replace(/_/g,' ')}: ${escIM(v)}</span>`)
                    .join('');
                if (chipItems) chips = `<div class="im-chips">${chipItems}</div>`;
            }
            
            const design = item.has_design ? `<a href="${item.design_url}" target="_blank" class="block mt-4"><div class="text-[9px] font-black text-slate-400 uppercase mb-1">Final Design</div><img src="${item.design_url}" class="im-thumb hover:scale-105 transition-transform cursor-zoom-in" alt="Design"></a>` : '';
            const reference = item.has_reference ? `<a href="${item.reference_url}" target="_blank" class="block mt-4"><div class="text-[9px] font-black text-slate-400 uppercase mb-1">Reference</div><img src="${item.reference_url}" class="im-thumb hover:scale-105 transition-transform cursor-zoom-in" alt="Reference"></a>` : '';

            return `<tr>
                <td class="min-w-[280px]">
                    <div class="font-black text-slate-900 text-[15px] leading-tight">${escIM(item.product_name)}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">${escIM(item.category || 'General')}</div>
                        <span class="text-slate-300">•</span>
                        <div class="text-[10px] text-slate-500 font-black uppercase tracking-wider">${escIM(data.branch_name)}</div>
                    </div>
                    ${chips}
                    <div class="flex gap-4">${design}${reference}</div>
                </td>
                <td class="text-center font-black text-slate-700 text-sm">${item.quantity}</td>
                <td class="text-slate-600 font-bold text-sm whitespace-nowrap text-right">${escIM(item.unit_price).replace(' ', '&nbsp;')}</td>
                <td class="font-black text-slate-900 text-base whitespace-nowrap text-right">${escIM(item.subtotal).replace(' ', '&nbsp;')}</td>
            </tr>`;
        }).join('');

        document.getElementById('imBody').innerHTML = `
            <div class="space-y-6">
                <!-- Status & Summary -->
                <div class="p-5 bg-slate-50 border border-slate-100 rounded-2xl flex justify-between items-center shadow-sm">
                    <div>
                        <div class="text-[10px] uppercase font-black text-slate-400 tracking-widest mb-1">Current Status</div>
                        <div class="text-lg font-black text-slate-900 leading-none">${data.status}</div>
                    </div>
                    ${imBadge(data.status)}
                </div>

                <!-- Product Table -->
                <div class="overflow-x-auto -mx-2">
                    <table class="im-table">
                        <thead><tr><th>Item Details</th><th class="text-center">Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                     <div class="im-card !p-3"><div class="im-label">Payment Method</div><div class="im-val text-xs">${escIM(data.payment_method)}</div></div>
                     <div class="im-card !p-3"><div class="im-label">Payment Status</div><div class="im-val">${imBadge(data.payment_status)}</div></div>
                     <div class="im-card !p-3"><div class="im-label">Estimated Completion</div><div class="im-val text-xs">${escIM(data.estimated_comp || 'Processing')}</div></div>
                </div>

                ${data.notes ? `
                    <div class="p-6 bg-blue-50 border border-blue-100 rounded-2xl">
                        <div class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-3">Order Instructions</div>
                        <div class="text-sm text-blue-900 font-medium leading-relaxed italic">"${escIM(data.notes)}"</div>
                    </div>
                ` : ''}

                <!-- Re-upload Section if Revision Requested -->
                ${data.design_status === 'Revision Requested' ? `
                    <div class="p-6 bg-amber-50 border border-amber-100 rounded-3xl">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-xl">⚠️</span>
                            <div class="font-black text-amber-900">Revision Requested</div>
                        </div>
                        <p class="text-sm text-amber-800 font-medium mb-5">${escIM(data.revision_reason)}</p>
                        <button onclick="triggerDesignReupload(${data.order_id})" class="w-full py-4 bg-amber-500 text-white font-black rounded-2xl shadow-lg shadow-amber-200 hover:bg-amber-600 transition-all flex items-center justify-center gap-2">
                             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                             Upload Corrected Design
                        </button>
                        <input type="file" id="designReuploadInput-${data.order_id}" style="display:none;" onchange="handleDesignReupload(this, ${data.order_id}, '${data.csrf_token}')" accept="image/*,application/pdf">
                    </div>
                ` : ''}

                ${data.can_cancel ? `
                    <div class="pt-4 border-t border-slate-100">
                        <button onclick="openCancelModal(${data.order_id}, '${data.csrf_token}')" class="w-full py-4 text-red-500 font-black border-2 border-red-50 rounded-2xl hover:bg-red-50 transition-all">Cancel Order</button>
                    </div>
                ` : ''}
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
                
                card.style.background = '#fef3c7'; // Brief amber highlight
                setTimeout(() => card.style.background = '#fff', 1500);
            });
        });
    }
    window.__ordersPollingInterval = setInterval(poll, 10000);
})();

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeItemsModal(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


