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
/* TikTok Style Orders Nav */
.orders-theme-page {
    color: #d9e6ef;
    background: transparent !important;
}
body.customer-theme.orders-page #main-content {
    min-height: auto !important;
}
body.customer-theme.orders-page footer.ft-footer {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    z-index: 5 !important;
}
.orders-theme-page .container {
    max-width: 1100px;
    padding-left: 1rem;
    padding-right: 1rem;
}
.orders-theme-page .card,
.orders-theme-page .ct-order-card {
    background: rgba(10, 37, 48, 0.55) !important;
    border: 1px solid rgba(83, 197, 224, 0.22) !important;
    border-radius: 1.25rem !important;
    box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35) !important;
    overflow: visible !important; /* Ensure content is never clipped */
    display: block !important;
    opacity: 1 !important;
    max-height: none !important;
}
.tt-tabs-wrapper {
    position: sticky; top: 72px; z-index: 40;
    background: rgba(8, 30, 39, 0.92); border-bottom: 1px solid rgba(83, 197, 224, 0.22);
    margin: 0 0 1.5rem 0; padding: 0 1rem;
    border-radius: 12px;
    overflow: visible;
}
.tt-tabs {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.65rem 1rem;
    padding: 0.65rem 0.2rem 0.55rem 0.2rem;
    overflow: visible;
}
.tt-tab {
    padding: 0.45rem 0.55rem; font-size: 0.75rem; color: #9fc6d9; font-weight: 700;
    border-bottom: 2px solid transparent; text-decoration: none; position: relative;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    white-space: nowrap;
    flex-shrink: 0;
    border-radius: 8px;
}
.tt-tab:hover { color: #eaf6fb; }
.tt-tab.active {
    color: #eaf6fb; font-weight: 700;
    background: rgba(83, 197, 224, 0.1);
}
.tt-tab.active::after {
    content: ''; position: absolute; bottom: -1px; left: 6px; right: 6px;
    height: 2px; background: #53c5e0; border-radius: 3px 3px 0 0;
}
.tt-tab-count {
    min-width: 15px;
    height: 15px;
    padding: 0 4px;
    border-radius: 999px;
    background: rgba(83, 197, 224, 0.2);
    color: #d9e6ef;
    font-size: 0.62rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.tt-tab.active .tt-tab-count {
    background: #53c5e0;
    color: #fff;
}

/* Scroll Buttons for Tabs */
.tt-tabs-container-outer {
    display: block;
    width: 100%;
}

/* TikTok Style Empty State */
.tt-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 4rem 1rem; text-align: center;
}
.tt-empty-icon {
    width: 120px; height: 120px; margin-bottom: 1rem; opacity: 0.7;
}
.tt-empty-title {
    font-size: 1.1rem; font-weight: 700; color: #eaf6fb; margin-bottom: 0.25rem;
}
.tt-empty-sub {
    font-size: 0.9rem; color: #9fc6d9; font-weight: 500;
}

.orders-theme-page .ct-order-card { transition: background 0.2s, border-color 0.2s, box-shadow 0.2s !important; cursor: default; }
.orders-theme-page .ct-order-card:hover { background: rgba(0, 15, 20, 0.75) !important; border-color: rgba(83, 197, 224, 0.42) !important; box-shadow: 0 16px 48px rgba(2, 12, 18, 0.5) !important; }
.orders-theme-page .ct-order-card [style*="border-bottom:1px solid #f1f5f9"] { border-bottom: 1px solid rgba(83, 197, 224, 0.2) !important; }
.orders-theme-page .ct-order-card [style*="color:#1e293b"],
.orders-theme-page .ct-order-card [style*="color:#111827"] { color: #eaf6fb !important; }
.orders-theme-page .ct-order-card [style*="color:#64748b"],
.orders-theme-page .ct-order-card [style*="color:#94a3b8"],
.orders-theme-page .ct-order-card [style*="color:#9ca3af"] { color: #9fc6d9 !important; }
.orders-theme-page .ct-order-card [style*="background:#f8fafc"],
.orders-theme-page .ct-order-card [style*="background: #f8fafc"],
.orders-theme-page .ct-order-card [style*="background:#fff"],
.orders-theme-page .ct-order-card [style*="background: #fff"],
.orders-theme-page .ct-order-card [style*="background:#f0f7f9"],
.orders-theme-page .ct-order-card [style*="background: #f0f7f9"] {
    background: rgba(255,255,255,.06) !important;
}
.orders-theme-page .ct-order-card a[href*="chat.php"] {
    background: linear-gradient(135deg, #53C5E0, #32a1c4) !important;
    color: #fff !important;
    border: none !important;
    box-shadow: 0 10px 22px rgba(50,161,196,0.3) !important;
}
.orders-theme-page .ct-order-card .ct-view-link {
    background: rgba(255,255,255,.05) !important;
    color: #d9e6ef !important;
    border: 1px solid rgba(83,197,224,.28) !important;
}
.orders-theme-page .ct-order-card .ct-view-link:hover {
    background: rgba(83,197,224,.14) !important;
    color: #fff !important;
}
.orders-theme-page .ct-order-card .order-status-badge .ct-status-badge {
    background: rgba(83, 197, 224, 0.18) !important;
    color: #d9e6ef !important;
    border: 1px solid rgba(83, 197, 224, 0.35) !important;
}

@media (min-width: 768px) {
    .tt-tabs-wrapper { margin: 0 0 2rem 0; padding: 0; border-bottom: 1px solid rgba(83, 197, 224, 0.24); }
}
</style>

<div class="min-h-screen py-8 orders-theme-page">
    <div class="container mx-auto">
        <!-- TikTok Tabs -->
        <div class="tt-tabs-wrapper card" style="padding: 0.55rem 0.85rem;">
            <div class="tt-tabs-container-outer">
                <div class="tt-tabs" id="ttTabsScrollContainer">
                    <a href="?tab=all" class="tt-tab <?php echo $active_tab === 'all' ? 'active' : ''; ?>">All <span class="tt-tab-count"><?php echo $tab_counts['all']; ?></span></a>
                    <a href="?tab=pending" class="tt-tab <?php echo $active_tab === 'pending' ? 'active' : ''; ?>">Pending <span class="tt-tab-count"><?php echo $tab_counts['pending']; ?></span></a>
                    <a href="?tab=approved" class="tt-tab <?php echo $active_tab === 'approved' ? 'active' : ''; ?>">Approved <span class="tt-tab-count"><?php echo $tab_counts['approved']; ?></span></a>
                    <a href="?tab=topay" class="tt-tab <?php echo $active_tab === 'topay' ? 'active' : ''; ?>">To Pay <span class="tt-tab-count"><?php echo $tab_counts['topay']; ?></span></a>
                    <a href="?tab=toverify" class="tt-tab <?php echo $active_tab === 'toverify' ? 'active' : ''; ?>">To Verify <span class="tt-tab-count"><?php echo $tab_counts['toverify']; ?></span></a>
                    <a href="?tab=production" class="tt-tab <?php echo $active_tab === 'production' ? 'active' : ''; ?>">In Production <span class="tt-tab-count"><?php echo $tab_counts['production']; ?></span></a>
                    <a href="?tab=pickup" class="tt-tab <?php echo $active_tab === 'pickup' ? 'active' : ''; ?>">Ready for Pickup <span class="tt-tab-count"><?php echo $tab_counts['pickup']; ?></span></a>
                    <a href="?tab=completed" class="tt-tab <?php echo $active_tab === 'completed' ? 'active' : ''; ?>">Completed <span class="tt-tab-count"><?php echo $tab_counts['completed']; ?></span></a>
                    <a href="?tab=cancelled" class="tt-tab <?php echo $active_tab === 'cancelled' ? 'active' : ''; ?>">Cancelled <span class="tt-tab-count"><?php echo $tab_counts['cancelled']; ?></span></a>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="tt-empty">
                <!-- SVG Shopping Bag Empty State mimicking TikTok -->
                <svg class="tt-empty-icon" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M70 70 L60 140 L130 140 L140 70 Z" stroke="#9ca3af" stroke-width="4" stroke-linejoin="round"/>
                    <path d="M85 70 V55 C85 45 115 45 115 55 V70" stroke="#9ca3af" stroke-width="4" stroke-linecap="round"/>
                    <path d="M85 90 C85 105 115 105 115 90" stroke="#9ca3af" stroke-width="4" stroke-linecap="round"/>
                    <path d="M50 40 L65 55" stroke="#9ca3af" stroke-width="4" stroke-linecap="round"/>
                    <path d="M120 30 L135 45 M135 30 L120 45" stroke="#9ca3af" stroke-width="4" stroke-linecap="round"/>
                    <circle cx="140" cy="50" r="4" fill="#9ca3af"/>
                    <circle cx="55" cy="80" r="3" fill="#9ca3af"/>
                    <path d="M145 90 C155 90 155 100 145 100 C135 100 135 110 145 110" stroke="#9ca3af" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M45 100 C35 100 35 110 45 110 C55 110 55 120 45 120" stroke="#9ca3af" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div class="tt-empty-title">No orders yet</div>
                <div class="tt-empty-sub">Start shopping!</div>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $index => $order): ?>
                <div class="ct-order-card" id="order-card-<?php echo $order['order_id']; ?>" data-order-id="<?php echo $order['order_id']; ?>" data-status="<?php echo htmlspecialchars($order['status']); ?>">
                    <!-- Card Top: product image + info + price -->
                    <div style="display:flex; gap:14px; align-items:flex-start; padding-bottom:12px; border-bottom:1px solid #f1f5f9;">
                        <!-- Product Image -->
                        <div style="flex-shrink:0;">
                            <?php 
                            // Prefer actual service name from customization over product name (e.g. Transparent Sticker not "Sticker Pack")
                            $c_json = !empty($order['first_item_customization']) ? json_decode($order['first_item_customization'], true) : [];
                            
                            // Dynamic Logic (MATCH get_order_items.php)
                            $display_name = '';
                            if (!empty($c_json['sintra_type'])) {
                                $display_name = 'Sintra Board - ' . $c_json['sintra_type'];
                            } elseif (!empty($c_json['tarp_size']) || (!empty($c_json['width']) && !empty($c_json['height']))) {
                                $size = $c_json['tarp_size'] ?? ($c_json['width'] . 'x' . $c_json['height'] . 'ft');
                                $display_name = 'Tarpaulin Printing - ' . $size;
                            } elseif (!empty($c_json['vinyl_type'])) {
                                $display_name = 'T-Shirt Printing (Vinyl)';
                            } elseif (!empty($c_json['sticker_type'])) {
                                $display_name = 'Decals/Stickers';
                            }

                            if (!$display_name) {
                                $raw_name = $order['first_product_name'] ?? 'Order Item';
                                $genericNames = ['custom order', 'customer order', 'service order', 'order item', 'sticker pack', 'merchandise'];
                                if (empty($raw_name) || in_array(strtolower(trim($raw_name)), $genericNames)) {
                                    $display_name = get_service_name_from_customization($c_json, 'Order Item');
                                } else {
                                    $display_name = normalize_service_name($raw_name, 'Order Item');
                                    if (!empty($c_json['product_type'])) {
                                        $display_name .= " (" . $c_json['product_type'] . ")";
                                    }
                                }
                            }
                            $service_category = $c_json['service_type'] ?? '';
                            $service_category = $service_category ?: $display_name;

                            // Determine image or design (80x80, object-fit: cover, border-radius: 8px)
                            $show_design = !empty($order['first_item_has_design']) && !empty($order['first_item_id']);
                            $img_style = 'width:80px; height:80px; object-fit:cover; border-radius:8px;';
                            $img_wrapper = 'width:80px; height:80px; border-radius:8px; overflow:hidden; border:2px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.1); background:#f8fafc;';
                            $fallback_img = '/printflow/public/assets/images/services/default.png';

                            if ($show_design) {
                                $order_img_src = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$order['first_item_id'];
                            } else {
                                $product_img = "";
                                $pn = trim($order['first_product_name'] ?? '');
                                if ($pn && strtolower($display_name) === strtolower($pn)) {
                                    if (!empty($order['first_product_image'])) {
                                        $product_img = $order['first_product_image'];
                                    }
                                    if (empty($product_img) && ($prod_id = (int)($order['first_product_id'] ?? 0)) > 0) {
                                        $img_base = __DIR__ . "/../public/images/products/product_" . $prod_id;
                                        if (file_exists($img_base . ".jpg")) $product_img = "/printflow/public/images/products/product_" . $prod_id . ".jpg";
                                        elseif (file_exists($img_base . ".png")) $product_img = "/printflow/public/images/products/product_" . $prod_id . ".png";
                                    }
                                }
                                $order_img_src = !empty($product_img) ? $product_img : get_service_image_url($service_category ?: $display_name);
                            }
                            ?>

                            <?php if ($show_design): ?>
                                <a href="/printflow/public/serve_design.php?type=order_item&id=<?php echo (int)$order['first_item_id']; ?>" target="_blank" style="display:block; <?php echo $img_wrapper; ?>">
                                    <img src="<?php echo htmlspecialchars($order_img_src); ?>" style="<?php echo $img_style; ?>" alt="<?php echo htmlspecialchars($display_name); ?>" onerror="this.src='<?php echo $fallback_img; ?>';">
                                </a>
                            <?php else: ?>
                                <div style="<?php echo $img_wrapper; ?> display:flex; align-items:center; justify-content:center;">
                                    <img src="<?php echo htmlspecialchars($order_img_src); ?>" style="<?php echo $img_style; ?>" alt="<?php echo htmlspecialchars($display_name); ?>" onerror="this.src='<?php echo $fallback_img; ?>';">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Order Info -->
                        <div style="flex:1; min-width:0;">
                            <!-- Bold product name -->
                            <div style="font-size:1rem; font-weight:800; color:#1e293b; line-height:1.3; margin-bottom:3px;">
                                <?php echo htmlspecialchars($display_name); ?>
                                <?php 
                                // Count additional items
                                if (!empty($order['item_names'])) {
                                    $item_count_arr = explode(', ', $order['item_names']);
                                    if (count($item_count_arr) > 1): ?>
                                        <span style="font-size:0.75rem; color:#94a3b8; font-weight:500;"> +<?php echo count($item_count_arr) - 1; ?> more</span>
                                    <?php endif;
                                }
                                ?>
                            </div>
                            <!-- Quantity -->
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><?php echo max(1, (int)($order['total_quantity'] ?? 0)); ?>x</span>
                                <?php 
                                $unread = get_unread_chat_count($order['order_id'], 'Customer');
                                if ($unread > 0): 
                                ?>
                                    <span style="background:#ef4444; color:white; border-radius:50%; width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; animation:pulse 2s infinite;" title="<?php echo $unread; ?> new messages"><?php echo $unread; ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- Date -->
                            <p style="font-size:0.73rem; color:#94a3b8; font-weight:500; margin-top:2px; margin-bottom:0;"><?php echo format_datetime($order['order_date']); ?></p>
                        </div>

                        <!-- Price + Status -->
                        <div style="text-align:right; flex-shrink:0;">
                            <?php if (in_array($order['status'], $HIDDEN_PRICE_STATUSES)): ?>
                                <p class="ct-order-amount order-price" style="font-size:0.78rem; font-weight:600; color:#9ca3af; margin:0; font-style:italic;">Price will be confirmed by the shop</p>
                            <?php else: ?>
                                <p class="ct-order-amount order-price" style="font-size:1.15rem; font-weight:800; color:#111827; margin:0;"><?php echo format_currency($order['total_amount']); ?></p>
                            <?php endif; ?>
                            <div style="margin-top:4px;" class="order-status-badge"><?php echo status_badge($order['status'], 'order'); ?></div>
                        </div>
                    </div>

                    <!-- Card Bottom: Message + Details -->
                    <div style="display:flex; justify-content:flex-end; align-items:center; margin-top:12px; gap:10px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; margin-left:auto;">
                            <a href="<?php echo BASE_URL; ?>/customer/chat.php?order_id=<?php echo $order['order_id']; ?>" style="background:#0a2530; color:#fff; border:1px solid #0a2530; padding:8px 14px; border-radius:8px; font-weight:700; display:inline-flex; align-items:center; font-size:13px; text-decoration:none; line-height:1; transition: all 0.2s;">
                                Message Shop
                            </a>
                            <?php if (in_array($order['status'], ['Completed', 'To Rate', 'Rated'], true)): ?>
                                <?php $rating_value = (int)($order['rating_value'] ?? 0); ?>
                                <?php if ($rating_value > 0): ?>
                                    <a href="/printflow/customer/rate_order.php?order_id=<?php echo (int)$order['order_id']; ?>" style="display:inline-flex; align-items:center; gap:6px; border:1px solid #fde68a; background:#fffbeb; color:#b45309; font-size:12px; font-weight:800; border-radius:999px; padding:6px 10px; text-decoration:none; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#fef3c7';this.style.borderColor='#f59e0b';" onmouseout="this.style.background='#fffbeb';this.style.borderColor='#fde68a';" title="View your review">
                                        Rated <?php echo str_repeat('★', $rating_value) . str_repeat('☆', max(0, 5 - $rating_value)); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="/printflow/customer/rate_order.php?order_id=<?php echo (int)$order['order_id']; ?>" style="display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:8px 12px; background:#f59e0b; color:#fff; font-size:12px; font-weight:800; text-decoration:none; letter-spacing:0.02em;">
                                        Rate Order
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button
                                onclick="openItemsModal(<?php echo $order['order_id']; ?>)"
                                class="ct-view-link"
                                style="background:#f0f7f9;border:1px solid #0a2530;cursor:pointer;padding:8px 14px;font-family:inherit;border-radius:8px;line-height:1;color:#0a2530;font-weight:700;transition: all 0.2s;"
                            >View Details</button>
                        </div>
                    </div>


                </div>
            <?php endforeach; ?>

            <?php if ($active_tab !== 'all'): ?>
                <!-- Pagination -->
                <div class="mt-8">
                    <?php echo get_pagination_links($current_page, $total_pages, ['tab' => $active_tab]); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

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
/* Base modal */
#itemsModal {
    position:fixed; inset:0; z-index:9999999;
    display:flex; align-items:center; justify-content:center;
    padding:16px;
    opacity:0; pointer-events:none;
    transition:opacity 0.25s ease;
}
#itemsModal.open { opacity:1; pointer-events:all; }

.im-backdrop {
    position:absolute; inset:0;
    background:rgba(0,0,0,0.45);
}
.im-panel {
    position:relative; z-index:1;
    background:rgba(10, 37, 48, 0.97); border-radius:14px;
    width:100%;
    max-width:560px;
    max-height:88vh; 
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    opacity:0; transform:translateY(22px) scale(0.97);
    transition:
        max-width 0.4s cubic-bezier(.34,1.2,.64,1),
        transform 0.32s cubic-bezier(.34,1.56,.64,1),
        opacity 0.25s ease;
}
#itemsModal.open .im-panel { opacity:1; transform:translateY(0) scale(1); }
/* Expanded state – wider panel */
#itemsModal.expanded .im-panel { max-width:780px; }

.im-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 24px 16px;
    border-bottom:1px solid rgba(83, 197, 224, 0.24);
    background:rgba(8, 30, 39, 0.95);
    border-radius:14px 14px 0 0; 
    z-index:2;
    gap:12px;
    flex-shrink: 0;
}
.im-title { font-size:1.1rem; font-weight:800; color:#eaf6fb; flex:1; min-width:0; }
.im-subtitle { font-size:0.75rem; color:#9fc6d9; margin-top:2px; }

.im-close {
    width:32px; height:32px; border-radius:50%; flex-shrink:0;
    border:none; background:rgba(255,255,255,.08); color:#9fc6d9;
    cursor:pointer; font-size:1rem;
    display:flex; align-items:center; justify-content:center;
    transition:background 0.15s;
}
.im-close:hover { background:rgba(83,197,224,.2); color:#fff; }

.im-body { 
    padding:20px 24px 24px; 
    overflow-y: auto;
    flex: 1;
}

/* Custom scrollbar for im-body */
.im-body::-webkit-scrollbar {
    width: 6px;
}
.im-body::-webkit-scrollbar-track {
    background: transparent;
}
.im-body::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.im-body::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}

/* Items table */
.im-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.im-table th {
    text-align:left; padding:8px 10px;
    font-size:0.65rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.06em; color:#9fc6d9;
    border-bottom:2px solid rgba(83, 197, 224, 0.24);
}
.im-table td { padding:11px 10px; border-bottom:1px solid rgba(83, 197, 224, 0.15); vertical-align:top; color:#d9e6ef; }
.im-table tbody tr:last-child td { border-bottom:none; }
.im-total-row { border-top:2px solid rgba(83, 197, 224, 0.24) !important; font-weight:800; }

/* Full details section (always visible) */
.im-full-details-inner { padding-top:20px; border-top:1px solid rgba(83, 197, 224, 0.22); margin-top:18px; }

/* Info grid */
.im-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
@media (max-width:500px) { .im-info-grid { grid-template-columns:1fr; } }
.im-info-card { background:rgba(255,255,255,.04); border:1px solid rgba(83, 197, 224, 0.2); border-radius:12px; padding:14px; }
.im-info-label { font-size:0.7rem; color:#9fc6d9; margin-bottom:4px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
.im-info-value { font-size:13.5px; font-weight:700; color:#eaf6fb; }

/* Notes box */
.im-notes {
    margin-bottom:16px; padding:14px 16px;
    background: rgba(255, 255, 255, 0.04);
    border:1px solid rgba(83, 197, 224, 0.22); border-radius:12px;
    max-height: 150px; overflow-y: auto;
}
.im-notes-title { font-size:12px; font-weight:800; color:#eaf6fb; margin-bottom:6px; }
.im-notes-text { font-size:13px; color:#b9d4df; line-height:1.6; overflow-wrap: anywhere; word-break: break-word; }
.im-chip {
    border: 1px solid rgba(83, 197, 224, 0.35);
    background: rgba(83, 197, 224, 0.15);
    color: #d9e6ef;
}

/* Design thumb */
.im-design-thumb { max-width:100px; border-radius:8px; border:2px solid #e2e8f0; display:block; margin-top:6px; cursor:zoom-in; transition:transform 0.2s; }
.im-design-thumb:hover { transform:scale(1.05); }

/* Custom chips */
.im-chips { display:flex; flex-wrap:wrap; gap:5px; margin-top:5px; }
.im-chip { 
    background:#f0f7f9; color:#0a2530; border: 1px solid #0a2530; border-radius:99px; padding:1px 8px; 
    font-size:11px; font-weight:600; 
    overflow-wrap: anywhere; word-break: break-word; white-space: normal;
}

/* Status badges */
.im-badge { display:inline-block; padding:2px 10px; border-radius:99px; font-size:11px; font-weight:700; }
.im-badge-green { background:#d1fae5; color:#065f46; }
.im-badge-yellow { background:#fef3c7; color:#92400e; }
.im-badge-red { background:#fee2e2; color:#991b1b; }
.im-badge-blue { background:#dbeafe; color:#1e40af; }
.im-badge-gray { background:#f3f4f6; color:#374151; }
.im-badge-purple { background:#ede9fe; color:#5b21b6; }

/* Loader */
.im-loader { text-align:center; padding:48px 0; }
.im-spinner {
    width:36px; height:36px; border-radius:50%;
    border:3px solid #e2e8f0; border-top-color:#0a2530;
    animation:im-spin 0.7s linear infinite; margin:0 auto 10px;
}
@keyframes im-spin { to { transform:rotate(360deg); } }

/* ── Cancel Order Modal ─────────────────────────────────── */
#cancelModal {
    position:fixed; inset:0; z-index:10000000;
    display:flex; align-items:center; justify-content:center;
    padding:16px; opacity:0; pointer-events:none;
    transition:opacity 0.2s ease;
}
#cancelModal.open { opacity:1; pointer-events:all; }
.cm-backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.45); }
.cm-panel {
    position:relative; z-index:1; background:rgba(10, 37, 48, 0.97); border-radius:20px;
    width:100%; max-width:400px; padding:24px;
    box-shadow:0 20px 50px rgba(0,0,0,0.3);
    transform:scale(0.95); transition:transform 0.2s;
}
#cancelModal.open .cm-panel { transform:scale(1); }
.cm-title { font-size:1.25rem; font-weight:800; color:#eaf6fb; margin-bottom:8px; }
.cm-sub { font-size:0.9rem; color:#9fc6d9; margin-bottom:20px; line-height:1.5; }

.cm-options { display:flex; flex-direction:column; gap:10px; margin-bottom:20px; }
.cm-opt {
    display:flex; align-items:center; gap:10px; padding:12px 14px;
    border:1px solid rgba(83,197,224,.24); border-radius:12px; cursor:pointer;
    transition:background 0.1s, border-color 0.1s;
}
.cm-opt:hover { background:rgba(83,197,224,.12); }
.cm-opt.active { background:rgba(83,197,224,.18); border-color:#53c5e0; }
.cm-opt input { display:none; }
.cm-opt-text { font-size:14px; font-weight:600; color:#d9e6ef; }

#cmOtherInput {
    width:100%; margin-top:10px; padding:10px;
    border:1px solid #e2e8f0; border-radius:8px; font-size:13px;
    display:none;
}
.cm-btns { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.cm-btn-cancel {
    padding:12px; border-radius:12px; background:rgba(255,255,255,.05); color:#d9e6ef;
    font-weight:700; font-size:14px; border:1px solid rgba(83,197,224,.28); cursor:pointer;
}
.cm-btn-confirm {
    padding:12px; border-radius:12px; background:linear-gradient(135deg, #53C5E0, #32a1c4); color:#fff;
    font-weight:700; font-size:14px; border:none; cursor:pointer;
    box-shadow:0 10px 22px rgba(50,161,196,0.3);
}
.cm-btn-confirm:disabled { opacity:0.5; cursor:not-allowed; }

/* force dark surface inside dynamically rendered modal content */
#imBody [style*="background:#fff"],
#imBody [style*="background: #fff"],
#imBody [style*="background:#f8fafc"],
#imBody [style*="background: #f8fafc"],
#imBody [style*="background:#fffbeb"],
#imBody [style*="background: #fffbeb"],
#imBody [style*="background:#eff6ff"],
#imBody [style*="background: #eff6ff"] {
    background: rgba(255,255,255,.04) !important;
    border-color: rgba(83,197,224,.22) !important;
}
#imBody [style*="color:#1e293b"],
#imBody [style*="color: #1e293b"],
#imBody [style*="color:#0f172a"],
#imBody [style*="color: #0f172a"],
#imBody [style*="color:#92400e"],
#imBody [style*="color: #92400e"],
#imBody [style*="color:#b45309"],
#imBody [style*="color: #b45309"] {
    color: #d9e6ef !important;
}
#imBody [style*="color:#94a3b8"],
#imBody [style*="color: #94a3b8"],
#imBody [style*="color:#9ca3af"],
#imBody [style*="color: #9ca3af"] {
    color: #9fc6d9 !important;
}
</style>

<div id="itemsModal" role="dialog" aria-modal="true">
    <div class="im-backdrop" onclick="closeItemsModal()"></div>
    <div class="im-panel">
        <div class="im-header">
            <div>
                <div class="im-title" id="imTitle">Order Items</div>
                <div class="im-subtitle" id="imSubtitle"></div>
            </div>
            <button class="im-close" onclick="closeItemsModal()">✕</button>
        </div>
        <div class="im-body" id="imBody">
            <div class="im-loader"><div class="im-spinner"></div></div>
        </div>
    </div>
</div>

<!-- Cancel Order Modal -->
<div id="cancelModal" role="dialog" aria-modal="true">
    <div class="cm-backdrop" onclick="closeCancelModal()"></div>
    <div class="cm-panel">
        <div class="cm-title">Cancel Order</div>
        <p class="cm-sub">Please select a reason for cancelling your order. This helps us improve our service.</p>
        
        <div class="cm-options">
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Change of mind"><span class="cm-opt-text">Change of mind</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Incorrect order details"><span class="cm-opt-text">Incorrect order details</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Budget concerns"><span class="cm-opt-text">Budget concerns</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Found another provider"><span class="cm-opt-text">Found another provider</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Urgent order / Long processing time"><span class="cm-opt-text">Urgent order / Long processing time</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Payment issue"><span class="cm-opt-text">Payment issue</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Other"><span class="cm-opt-text">Other</span></label>
            <textarea id="cmOtherInput" placeholder="Please specify your reason..."></textarea>
        </div>

        <div class="cm-btns">
            <button class="cm-btn-cancel" onclick="closeCancelModal()">Back</button>
            <button class="cm-btn-confirm" id="cmConfirmBtn" onclick="submitOrderCancellation()">Confirm Cancellation</button>
        </div>
    </div>
</div>

<script>
function imBadge(val) {
    const m = {
        'Completed':'im-badge-green','Pending':'im-badge-yellow',
        'Processing':'im-badge-blue',
        'In Production':'im-badge-blue','Printing':'im-badge-blue',
        'To Rate':'im-badge-purple','Rated':'im-badge-green',
        'Ready for Pickup':'im-badge-purple','Cancelled':'im-badge-red',
        'For Revision':'im-badge-blue','Paid':'im-badge-green',
        'Unpaid':'im-badge-gray','Partial':'im-badge-yellow',
    };
    return `<span class="im-badge ${m[val]||'im-badge-gray'}">${escIM(val)}</span>`;
}

function openItemsModal(orderId) {
    const modal = document.getElementById('itemsModal');
    document.getElementById('imTitle').textContent = `Order #${orderId}`;
    document.getElementById('imSubtitle').textContent = '';
    document.getElementById('imBody').innerHTML =
        `<div class="im-loader"><div class="im-spinner"></div><div style="color:#94a3b8;font-size:13px;margin-top:6px;">Loading…</div></div>`;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch(`/printflow/customer/get_order_items.php?id=${orderId}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            document.getElementById('imBody').innerHTML =
                `<p style="color:#ef4444;font-size:13px;">${escIM(data.error)}</p>`;
            return;
        }

        document.getElementById('imSubtitle').textContent = data.order_date;

        // ── Items table rows ──────────────────────────────────
        const rows = data.items.map(item => {
            let chips = '';
            let itemNotes = '';
            if (item.customization && Object.keys(item.customization).length) {
                let chipItems = '';
                Object.entries(item.customization).forEach(([k, v]) => {
                    if (!v || v === 'No' || v === 'None' || v === 'none') return;
                    
                    // Specific exclusions for Reflectorized Temporary Plates
                    const isReflectorized = (item.category || '').toLowerCase().includes('reflectorized') || 
                                           (item.customization.service_type || '').toLowerCase().includes('reflectorized');
                    const isTempPlate = (item.customization.product_type || '').includes('Temporary Plate');
                    const isGatePass = (item.customization.product_type || '').includes('Gate Pass');
                    const exclusions = ['unit', 'bg_color', 'text_color', 'arrow_direction', 'quantity', 'material_type', 'shape', 'with_border', 'rounded_corners', 'with_numbering', 'install_service', 'need_proof', 'reflective_color', 'inches', 'quantity_gatepass', 'dimensions', 'product_type', 'service_type'];
                    const gpOnlyExclusions = ['bg_color', 'text_color', 'reflective_color', 'text_content', 'arrow_direction', 'with_numbering', 'install_service', 'need_proof', 'temp_plate_text', 'product_type', 'dimensions', 'unit', 'shape', 'material_type', 'service_type'];
                    
                    if (isReflectorized && isTempPlate && (exclusions.includes(k) || v === 'inches')) return;
                    if (isReflectorized && isGatePass && (gpOnlyExclusions.includes(k) || k === 'quantity_gatepass')) return;

                    const label = k.replace(/_/g, ' ');
                    
                    // Skip if item note is same as global order note
                    if (k.toLowerCase() === 'notes' && v === data.notes) return;
                    if (k.toLowerCase() === 'notes' || k.toLowerCase().includes('description')) {
                        itemNotes += `
                            <div style="margin-top:8px; padding:10px; background:rgba(83,197,224,0.1); border:1px solid rgba(83,197,224,0.3); border-radius:8px;">
                                <div style="font-size:10px; font-weight:800; color:#9fc6d9; text-transform:uppercase; margin-bottom:4px;">📝 ${escIM(label)}</div>
                                <div style="font-size:12px; color:#d9e6ef; line-height:1.4; max-height:100px; overflow-y:auto; overflow-wrap:anywhere; word-break:break-word;">
                                    ${escIM(String(v)).replace(/\n/g,'<br>')}
                                </div>
                            </div>`;
                    } else {
                        chipItems += `<span class="im-chip">${escIM(label)}: ${escIM(String(v))}</span>`;
                    }
                });
                if (chipItems) chips = `<div class="im-chips">${chipItems}</div>`;
            }
            const design = item.has_design
                ? `<div style="margin-top:8px;">
                      <div style="font-size:9px;color:#94a3b8;font-weight:700;margin-bottom:3px;text-transform:uppercase;">Final Design</div>
                      <a href="${escIM(item.design_url)}" target="_blank">
                        <img src="${escIM(item.design_url)}" class="im-design-thumb"
                             alt="Design"
                             onerror="this.outerHTML='<span style=\\'color:#9ca3af;font-size:11px;\\'>⚠️ No preview</span>'">
                      </a>
                   </div>`
                : `<div style="font-size:11px;color:#9ca3af;margin-top:8px;">No design file</div>`;

            const reference = item.has_reference
                ? `<div style="margin-top:8px;">
                      <div style="font-size:9px;color:#94a3b8;font-weight:700;margin-bottom:3px;text-transform:uppercase;">Reference Image</div>
                      <a href="${escIM(item.reference_url)}" target="_blank">
                        <img src="${escIM(item.reference_url)}" class="im-design-thumb"
                             alt="Reference"
                             onerror="this.outerHTML='<span style=\\'color:#9ca3af;font-size:11px;\\'>⚠️ No preview</span>'">
                      </a>
                   </div>`
                : '';

            return `<tr>
                <td>
                    <div style="font-weight:700;color:#eaf6fb;">${escIM(item.product_name)}</div>
                    ${item.category ? `<div style="font-size:11px;color:#9fc6d9;">${escIM(item.category)}</div>` : ''}
                    ${chips}
                    ${itemNotes}
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        ${design}
                        ${reference}
                    </div>
                </td>
                <td style="text-align:center;color:#d9e6ef;">${item.quantity}</td>
                <td style="color:#d9e6ef;">${escIM(item.unit_price)}</td>
                <td style="font-weight:700;color:#53c5e0;">${escIM(item.subtotal)}</td>
            </tr>`;
        }).join('');

        // ── Full details (hidden initially) ──────────────────
        let notesHTML = '';
        if (data.notes) {
            notesHTML = `<div class="im-notes">
                <div class="im-notes-title">📝 Your Order Notes</div>
                <div class="im-notes-text">${escIM(data.notes).replace(/\n/g,'<br>')}</div>
            </div>`;
        }

        let cancelHTML = '';
        if (data.status === 'Cancelled' && (data.cancelled_by || data.cancel_reason)) {
            cancelHTML = `<div style="margin-top:12px;padding:12px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);border-radius:10px;font-size:12px;color:#fca5a5;">
                <b>Cancelled by:</b> ${escIM(data.cancelled_by)}<br>
                <b>Reason:</b> ${escIM(data.cancel_reason)}
                ${data.cancelled_at ? `<br><b>Date:</b> ${escIM(data.cancelled_at)}` : ''}
            </div>`;
        }

        let revisionHTML = '';
        if (data.status === 'For Revision' && data.revision_reason) {
            revisionHTML = `<div style="margin-top:12px;padding:12px;background:rgba(83,197,224,0.1);border:1px solid rgba(83,197,224,0.3);border-radius:10px;font-size:12px;color:#7dd3fc;">
                <b>Revision needed:</b> ${escIM(data.revision_reason)}
            </div>`;
        }

        document.getElementById('imBody').innerHTML = `
            <!-- Design Review Status for Customer (Top Priority) -->
            <div style="margin-bottom:20px; padding:15px; border-radius:12px; background:rgba(83,197,224,0.08); border:1px solid rgba(83,197,224,0.22); box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                <div style="font-size:11px; font-weight:800; color:#9fc6d9; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                    <span>Design Review Status</span>
                    ${imBadge(data.design_status || 'Pending')}
                </div>
                
                ${data.design_status === 'Revision Requested' ? `
                    <div style="margin-top:10px; padding:12px; background:rgba(83,197,224,0.1); border:1px solid rgba(83,197,224,0.3); border-radius:10px;">
                        <div style="font-weight:700; color:#7dd3fc; font-size:12px; margin-bottom:4px;">Revision Reason:</div>
                        <div style="font-size:12px; color:#bae6fd; line-height:1.4;">${escIM(data.revision_reason)}</div>
                    </div>
                    <div style="margin-top:14px;">
                        <button onclick="triggerDesignReupload(${data.order_id})" style="width:100%; padding:12px; background:#06A1A1; color:#fff; border:none; border-radius:10px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                            <span>📤 Re-upload New Design</span>
                        </button>
                        <input type="file" id="designReuploadInput-${data.order_id}" style="display:none;" onchange="handleDesignReupload(this, ${data.order_id}, '${data.csrf_token}')" accept="image/*,application/pdf">
                    </div>
                ` : ''}

                ${data.design_status === 'Approved' ? `
                    <div style="margin-top:10px; text-align:center; color:#4ade80; font-size:12px; font-weight:600;">
                        Your design has been approved for production.
                    </div>
                ` : ''}
            </div>

            <table class="im-table">
                <thead><tr>
                    <th>Product</th>
                    <th style="text-align:center;">Qty</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr></thead>
                <tbody>${rows}</tbody>
                <tfoot><tr>
                    <td colspan="3" style="text-align:right;padding:12px 10px;color:#d9e6ef;" class="im-total-row">Total</td>
                    <td style="padding:12px 10px;color:#53c5e0;font-size:15px;" class="im-total-row">${escIM(data.total_amount)}</td>
                </tr></tfoot>
            </table>
            <div class="im-full-details-inner">
                    ${notesHTML}
                    <div class="im-info-grid">
                        <div class="im-info-card">
                            <div class="im-info-label">Order Status</div>
                            <div class="im-info-value">${imBadge(data.status)}</div>
                        </div>
                        <div class="im-info-card">
                            <div class="im-info-label">Payment</div>
                            <div class="im-info-value">${imBadge(data.payment_status)}</div>
                        </div>
                        <div class="im-info-card">
                            <div class="im-info-label">Estimated Completion</div>
                            <div class="im-info-value">${escIM(data.estimated_comp)}</div>
                        </div>
                        <div class="im-info-card">
                            <div class="im-info-label">Date Placed</div>
                            <div class="im-info-value">${escIM(data.order_date)}</div>
                        </div>
                    </div>
                    ${cancelHTML}
                    ${revisionHTML}

                    <!-- Customer Rating Section -->
                    ${data.rating_data ? `
                        <div style="margin-top:20px; padding:15px; border-radius:12px; background:rgba(83,197,224,0.08); border:1px solid rgba(83,197,224,0.28); box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                            <div style="font-size:11px; font-weight:800; color:#9fc6d9; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                                <span>⭐ Customer Rating</span>
                                <span style="font-weight:600; opacity:0.8;">${escIM(data.rating_data.created_at)}</span>
                            </div>
                            <div style="color:#fbbf24; font-size:18px; line-height:1; margin-bottom:8px;">
                                ${'★'.repeat(data.rating_data.rating)}${'☆'.repeat(5 - data.rating_data.rating)}
                            </div>
                            ${data.rating_data.comment ? `
                                <div style="font-size:13.5px; color:#d9e6ef; font-weight:500; line-height:1.5; margin-bottom:12px; background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; border:1px solid rgba(83,197,224,0.18);">
                                    "${escIM(data.rating_data.comment)}"
                                </div>
                            ` : ''}
                            ${data.rating_data.image_url ? `
                                <div style="margin-top:10px;">
                                    <div style="font-size:9px; color:#9fc6d9; font-weight:700; margin-bottom:4px; text-transform:uppercase;">Photo Shared:</div>
                                    <a href="${escIM(data.rating_data.image_url)}" target="_blank" style="display:inline-block; border-radius:10px; overflow:hidden; border:2px solid rgba(83,197,224,0.4); box-shadow:0 4px 6px rgba(0,0,0,0.3);">
                                        <img src="${escIM(data.rating_data.image_url)}" style="max-width:120px; display:block; cursor:zoom-in;" alt="Rating Image">
                                    </a>
                                </div>
                            ` : ''}
                        </div>
                    ` : ''}

                    <div id="imCancelSection" style="margin-top:20px; padding-top:20px; border-top:1px solid rgba(83,197,224,0.2);">
                        ${data.can_cancel 
                            ? `<button class="im-cancel-trigger-btn" onclick="openCancelModal(${data.order_id}, '${data.csrf_token}')" 
                                       style="width:100%; padding:14px; background:rgba(239,68,68,0.1); border:2px solid rgba(239,68,68,0.4); border-radius:12px; color:#fca5a5; font-weight:800; font-size:14px; cursor:pointer; transition:all 0.2s;">
                                   Cancel Order
                               </button>`
                            : (data.cancel_restriction_msg 
                                ? `<div style="padding:14px; background:rgba(255,255,255,0.04); border:1px solid rgba(83,197,224,0.2); border-radius:12px; color:#9fc6d9; font-size:13px; text-align:center; font-weight:600;">
                                       ${escIM(data.cancel_restriction_msg)}
                                   </div>`
                                : '')
                        }
                    </div>
            </div>`;
    })
    .catch(() => {
        document.getElementById('imBody').innerHTML =
            `<p style="color:#ef4444;font-size:13px;">Failed to load. Please try again.</p>`;
    });
}

// ── Cancellation Logic ───────────────────────────────────
let cancelOrderId = null;
let cancelCsrfToken = null;

function openCancelModal(orderId, csrfToken) {
    cancelOrderId = orderId;
    cancelCsrfToken = csrfToken;
    const modal = document.getElementById('cancelModal');
    modal.classList.add('open');
    
    // Reset options
    document.querySelectorAll('.cm-opt').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('input[name="cancel_reason"]').forEach(rb => rb.checked = false);
    document.getElementById('cmOtherInput').style.display = 'none';
    document.getElementById('cmOtherInput').value = '';
    document.getElementById('cmConfirmBtn').disabled = true;
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('open');
    cancelOrderId = null;
    cancelCsrfToken = null;
}

// Handle radio button clicks
document.addEventListener('change', e => {
    if (e.target.name === 'cancel_reason') {
        const opts = document.querySelectorAll('.cm-opt');
        opts.forEach(opt => {
            const radio = opt.querySelector('input');
            opt.classList.toggle('active', radio.checked);
        });
        
        const otherInput = document.getElementById('cmOtherInput');
        otherInput.style.display = (e.target.value === 'Other') ? 'block' : 'none';
        
        document.getElementById('cmConfirmBtn').disabled = false;
    }
});

function submitOrderCancellation() {
    const reasonEl = document.querySelector('input[name="cancel_reason"]:checked');
    if (!reasonEl) return;
    
    const reason = reasonEl.value;
    const details = document.getElementById('cmOtherInput').value;
    
    if (reason === 'Other' && !details.trim()) {
        alert("Please specify your reason.");
        return;
    }

    const btn = document.getElementById('cmConfirmBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Processing…';

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('order_id', cancelOrderId);
    fd.append('csrf_token', cancelCsrfToken);
    fd.append('reason', reason);
    fd.append('details', details);

    fetch('/printflow/customer/cancel_order.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeCancelModal();
            closeItemsModal();
            // Show success alert and refresh
            alert("Order #" + cancelOrderId + " has been cancelled.");
            window.location.reload();
        } else {
            alert(data.error || "Failed to cancel order.");
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(() => {
        alert("A network error occurred.");
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

function closeItemsModal() {
    const modal = document.getElementById('itemsModal');
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeItemsModal(); });

function triggerDesignReupload(orderId) {
    document.getElementById('designReuploadInput-' + orderId).click();
}

function handleDesignReupload(input, orderId, csrfToken) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    if (!confirm(`Are you sure you want to upload "${file.name}" as your new design?`)) {
        input.value = '';
        return;
    }

    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('csrf_token', csrfToken);
    fd.append('design_file', file);

    // Show loading state
    const btn = input.previousElementSibling;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>Uploading...</span>';

    fetch('/printflow/customer/reupload_design_process.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            btn.style.background = '#059669';
            btn.style.borderColor = '#059669';
            btn.innerHTML = '<span style="display:flex;align-items:center;gap:6px;"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Success!</span>';
            
            // Optional alert if user is not looking at the button
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            alert(res.error || 'Failed to upload design');
            btn.disabled = false;
            btn.innerHTML = originalContent;
            input.value = '';
        }
    })
    .catch(() => {
        alert('Network error occurred');
        btn.disabled = false;
        btn.innerHTML = originalContent;
        input.value = '';
    });
}

function escIM(str) {
    return String(str || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Real-Time Orders Polling ──────────────────────────────────────────────────
(function startOrdersPolling() {
    const activeTab = '<?php echo addslashes($active_tab); ?>';
    
    if (activeTab === 'all') {
        return;
    }
    if (window.__ordersPollingInterval) {
        clearInterval(window.__ordersPollingInterval);
    }
    // Status → display label map
    const statusLabels = {
        'Pending':          'Pending',
        'Pending Approval': 'Pending Approval',
        'Pending Review':   'Pending Review',
        'For Revision':     'For Revision',
        'Approved':         'Approved',
        'To Pay':           'To Pay',
        'To Verify':        'To Verify',
        'Downpayment Submitted': 'To Verify',
        'Pending Verification': 'To Verify',
        'In Production':    'In Production',
        'Processing':       'In Production',
        'Printing':         'In Production',
        'Ready for Pickup': 'Ready for Pickup',
        'To Receive':       'Ready for Pickup',
        'Completed':        'Completed',
        'To Rate':          'To Rate',
        'Rated':            'Rated',
        'Cancelled':        'Cancelled',
    };

    // Statuses where price is hidden
    const hiddenPriceStatuses = ['Pending', 'Pending Approval', 'Pending Review', 'For Revision', 'Approved'];

    // Status → tab mapping (must match $tab_status_map in PHP)
    const statusToTab = {
        'Pending':          'pending',
        'Pending Approval': 'pending',
        'Pending Review':   'pending',
        'For Revision':     'pending',
        'Approved':         'approved',
        'To Pay':           'topay',
        'To Verify':        'toverify',
        'Downpayment Submitted': 'toverify',
        'Pending Verification': 'toverify',
        'In Production':    'production',
        'Processing':       'production',
        'Printing':         'production',
        'Ready for Pickup': 'pickup',
        'To Receive':       'pickup',
        'To Rate':          'torate',
        'Rated':            'torate',
        'Completed':        'completed',
        'Cancelled':        'cancelled',
    };


    function updateNotifBell(count) {
        const bells = document.querySelectorAll('.notif-count, [data-notif-count]');
        bells.forEach(el => {
            if (count > 0) {
                el.textContent = count;
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    }

    function poll() {
        fetch('/printflow/customer/api_customer_orders.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                // Update notification bell
                updateNotifBell(data.unread_notif_count || 0);

                data.orders.forEach(order => {
                    const card = document.getElementById('order-card-' + order.order_id);
                    if (!card) return;

                    const prevStatus = card.dataset.status;
                    if (prevStatus === order.status) return; // No change

                    // Status changed — update data attribute
                    card.dataset.status = order.status;

                    // Update status badge
                    const badgeContainer = card.querySelector('.order-status-badge');
                    if (badgeContainer) {
                        const label = statusLabels[order.status] || order.status;
                        badgeContainer.innerHTML = `<span class="ct-status-badge" style="display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#e0edff; color:#1d4ed8;">${label}</span>`;
                    }

                    // Update price
                    const priceEl = card.querySelector('.order-price');
                    if (priceEl) {
                        if (hiddenPriceStatuses.includes(order.status)) {
                            priceEl.style.fontSize = '0.78rem';
                            priceEl.style.color = '#9ca3af';
                            priceEl.style.fontWeight = '600';
                            priceEl.style.fontStyle = 'italic';
                            priceEl.textContent = 'Price will be confirmed by the shop';
                        } else if (order.total_amount !== null) {
                            priceEl.style.fontSize = '1.15rem';
                            priceEl.style.color = '#111827';
                            priceEl.style.fontWeight = '800';
                            priceEl.style.fontStyle = 'normal';
                            priceEl.textContent = '₱' + parseFloat(order.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }

                    // Do not auto-remove cards from DOM during polling.
                    // Keep server-rendered list stable; status updates only.

                    // Flash highlight to signal the update
                    card.style.transition = 'background 0.3s ease';
                    card.style.background = '#fffbeb';
                    setTimeout(() => { card.style.background = ''; card.style.transition = ''; }, 1200);
                });
            })
            .catch(() => {}); // Silently ignore network errors
    }

    // Start polling every 8 seconds (single active interval only)
    window.__ordersPollingInterval = setInterval(poll, 8000);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


