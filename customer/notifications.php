<?php
/**
 * Customer Notifications Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
    $back_filter = isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : '';
    redirect('/printflow/customer/notifications.php' . $back_filter);
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    db_execute("UPDATE notifications SET is_read = 1 WHERE customer_id = ? AND is_read = 0", 'i', [$customer_id]);
    redirect('/printflow/customer/notifications.php');
}

// Pagination settings
$items_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Get total count
$count_result = db_query(
    "SELECT COUNT(*) as total FROM notifications WHERE customer_id = ?",
    'i',
    [$customer_id]
);
$total_items = $count_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

// Choose available product image column for compatibility across DB versions.
$has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
$has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
$product_image_column = 'NULL';
if ($has_photo_path && $has_product_image) {
    $product_image_column = "COALESCE(p.photo_path, p.product_image)";
} elseif ($has_photo_path) {
    $product_image_column = "p.photo_path";
} elseif ($has_product_image) {
    $product_image_column = "p.product_image";
}

// Get all notifications with order and product details
$notifications = db_query("
    SELECT 
        n.*,
        o.order_id,
        CASE WHEN n.type = 'Job Order' THEN jo.job_title ELSE 
            (SELECT p.name FROM order_items oi 
             LEFT JOIN products p ON oi.product_id = p.product_id 
             WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1)
        END as service_name,
        CASE WHEN n.type = 'Job Order' THEN jo.service_type ELSE NULL END as jo_service_category,
        CASE WHEN n.type = 'Job Order' THEN jo.artwork_path ELSE 
            (SELECT {$product_image_column} FROM products p 
             INNER JOIN order_items oi ON oi.product_id = p.product_id 
             WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1)
        END as product_image,
        (SELECT oi.customization_data FROM order_items oi 
         WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization,
        (SELECT oi.order_item_id FROM order_items oi 
         WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_id,
        (SELECT oi.design_image FROM order_items oi 
         WHERE oi.order_id = n.data_id AND oi.design_image IS NOT NULL ORDER BY oi.order_item_id ASC LIMIT 1) as design_image
    FROM notifications n
    LEFT JOIN orders o ON n.data_id = o.order_id AND (n.type = 'Order' OR n.type = 'Payment' OR n.type = 'Status')
    LEFT JOIN job_orders jo ON n.data_id = jo.id AND n.type = 'Job Order'
    LEFT JOIN users u ON n.user_id = u.user_id
    WHERE n.customer_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT ? OFFSET ?
", 'iii', [$customer_id, $items_per_page, $offset]);

// Categorize by read status for display
$grouped_notifications = [
    'New' => [],
    'Earlier' => []
];
foreach ($notifications as $n) {
    if ($n['is_read'] == 0) {
        $grouped_notifications['New'][] = $n;
    } else {
        $grouped_notifications['Earlier'][] = $n;
    }
}
// Remove empty groups
$grouped_notifications = array_filter($grouped_notifications);
$unread_total = array_reduce($notifications, function($carry, $item) {
    return $carry + ($item['is_read'] ? 0 : 1);
}, 0);

$page_title = 'Notifications - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* Container */
    .notif-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Header Section */
    .notif-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        gap: 1rem;
    }
    .notif-page-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: #eaf6fb;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .notif-mark-all-btn {
        padding: 0.65rem 1.25rem;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        background: rgba(83, 197, 224, 0.1);
        border: 1px solid rgba(83, 197, 224, 0.25);
        color: #53c5e0;
        text-decoration: none;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .notif-mark-all-btn:hover {
        background: rgba(83, 197, 224, 0.2);
        border-color: #53c5e0;
        transform: translateY(-1px);
    }

    /* Group Label */
    .notif-group-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: rgba(83, 197, 224, 0.6);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin: 2rem 0 0.75rem;
        padding-left: 0.25rem;
    }
    .notif-group-label:first-child {
        margin-top: 0;
    }

    /* Notification Card */
    .notif-card {
        background: #0a2530;
        border: 1px solid rgba(83, 197, 224, 0.15);
        border-radius: 14px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: all 0.2s;
        text-decoration: none;
        display: block;
    }
    .notif-card:hover {
        background: #0d3240;
        border-color: rgba(83, 197, 224, 0.3);
        transform: translateX(4px);
    }
    .notif-card.unread {
        background: #0d3240;
        border-left: 3px solid #53c5e0;
    }

    /* Desktop Layout */
    .notif-card-inner {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    .notif-image-wrap {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 10px;
        overflow: hidden;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(83, 197, 224, 0.2);
        flex-shrink: 0;
    }
    .notif-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .notif-content-wrap {
        flex: 1;
        min-width: 0;
    }
    .notif-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #eaf6fb;
        margin-bottom: 0.35rem;
    }
    .notif-card.unread .notif-title {
        color: #53c5e0;
    }
    .notif-description {
        font-size: 0.85rem;
        line-height: 1.5;
        color: #9fc4d4;
        margin-bottom: 0.5rem;
        word-wrap: break-word;
    }
    .notif-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .notif-time {
        font-size: 0.75rem;
        color: rgba(83, 197, 224, 0.6);
        font-weight: 600;
    }
    .notif-view-btn {
        padding: 0.4rem 0.9rem;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        background: rgba(83, 197, 224, 0.1);
        border: 1px solid rgba(83, 197, 224, 0.25);
        color: #53c5e0;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .notif-card:hover .notif-view-btn {
        background: #53c5e0;
        color: #030d11;
        border-color: #53c5e0;
    }

    /* Empty State */
    .notif-empty {
        text-align: center;
        padding: 4rem 2rem;
        color: rgba(255, 255, 255, 0.4);
    }
    .notif-empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    /* Mobile Responsive */
    @media (max-width: 640px) {
        .notif-page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .notif-page-title {
            font-size: 1.5rem;
        }
        .notif-mark-all-btn {
            width: 100%;
            text-align: center;
        }
        .notif-card-inner {
            flex-direction: column;
        }
        .notif-image-wrap {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9;
            max-height: 180px;
        }
        .notif-meta {
            flex-direction: column;
            align-items: flex-start;
        }
        .notif-view-btn {
            width: 100%;
            text-align: center;
            padding: 0.6rem 1rem;
        }
    }
</style>

<div class="min-h-screen py-8">
    <div class="notif-container">
        <!-- Header -->
        <div class="notif-page-header">
            <h1 class="notif-page-title">
                Notifications
                <?php if ($unread_total > 0): ?>
                    <span style="background: #53c5e0; color: #030d11; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 900; box-shadow: 0 0 15px rgba(83, 197, 224, 0.4);"><?php echo $unread_total; ?></span>
                <?php endif; ?>
            </h1>
            <?php if ($unread_total > 0): ?>
                <a href="?mark_all_read=1" class="notif-mark-all-btn">Mark all as read</a>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="notif-empty">
                <div class="notif-empty-icon">🔔</div>
                <p style="font-size: 1rem; font-weight: 600;">No notifications yet</p>
                <p style="font-size: 0.85rem; margin-top: 0.5rem;">We'll notify you when something important happens</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_notifications as $group => $notifs): ?>
                <?php if ($group === 'New'): ?>
                    <div class="notif-group-label"><?php echo htmlspecialchars($group); ?></div>
                <?php endif; ?>
                <?php foreach ($notifs as $notif): 
                    // Determine service name
                    $name_data = !empty($notif['first_item_customization']) ? json_decode($notif['first_item_customization'], true) : [];
                    $raw_service_name = trim((string)($name_data['service_type'] ?? $notif['jo_service_category'] ?? $notif['service_name'] ?? ''));
                    if (empty($raw_service_name) || in_array(strtolower($raw_service_name), ['custom order', 'customer order', 'service order', 'order item', 'order update'])) {
                        $raw_service_name = get_service_name_from_customization($name_data, $notif['service_name'] ?? 'Order Update');
                    }
                    $display_name = normalize_service_name($raw_service_name, 'Order Update');

                    // Determine image
                    $final_image_url = "";
                    if (!empty($notif['design_image'])) {
                        $final_image_url = "/printflow/staff/get_design_image.php?id=" . $notif['first_item_id'];
                    } elseif (!empty($notif['product_image']) && strtolower(trim($display_name)) === strtolower(trim($notif['service_name'] ?? ''))) {
                        $final_image_url = $notif['product_image'];
                        if (strpos($final_image_url, 'uploads/') === 0) {
                            $final_image_url = '/printflow/' . $final_image_url;
                        }
                    } else {
                        $final_image_url = get_service_image_url($raw_service_name ?: $display_name);
                    }
                    $fallback_img = '/printflow/public/assets/images/services/default.png';

                    // Determine link
                    $link = "/printflow/customer/notifications.php?mark_read=" . $notif['notification_id'];
                    $is_rating_notif = (
                        (string)$notif['type'] === 'Rating' ||
                        stripos((string)$notif['message'], 'rate your experience') !== false ||
                        stripos((string)$notif['message'], 'rate your order') !== false
                    );
                    $is_payment_notif = (
                        (string)$notif['type'] === 'Payment' ||
                        (string)$notif['type'] === 'Payment Issue' ||
                        stripos((string)$notif['message'], 'Payment Required') !== false ||
                        stripos((string)$notif['message'], 'TO_PAY') !== false ||
                        stripos((string)$notif['message'], 'To Pay') !== false ||
                        stripos((string)$notif['message'], 'rejected') !== false ||
                        stripos((string)$notif['message'], 'proceed to payment') !== false ||
                        stripos((string)$notif['message'], 'ready for payment') !== false ||
                        stripos((string)$notif['message'], 'submit payment') !== false
                    );

                    $current_order_status = null;
                    if (!empty($notif['data_id']) && in_array($notif['type'], ['Order', 'Status', 'Payment'])) {
                        $ord_row = db_query("SELECT status FROM orders WHERE order_id = ? AND customer_id = ? LIMIT 1", 'ii', [$notif['data_id'], $customer_id]);
                        if (!empty($ord_row)) {
                            $current_order_status = $ord_row[0]['status'];
                            if (stripos($current_order_status, 'To Pay') !== false || stripos($current_order_status, 'TO_PAY') !== false) {
                                $is_payment_notif = true;
                            }
                        }
                    }

                    if (!function_exists('map_status_to_tab')) {
                        function map_status_to_tab(string $status): string {
                            $s = strtolower(trim($status));
                            if (in_array($s, ['pending', 'pending approval', 'pending review', 'for revision'])) return 'pending';
                            if ($s === 'approved') return 'approved';
                            if (in_array($s, ['to verify', 'downpayment submitted', 'pending verification'])) return 'toverify';
                            if ($s === 'to pay') return 'topay';
                            if (in_array($s, ['in production', 'processing', 'printing', 'paid – in process'])) return 'production';
                            if ($s === 'ready for pickup') return 'pickup';
                            if (in_array($s, ['completed', 'to rate', 'rated'])) return 'completed';
                            if ($s === 'cancelled') return 'cancelled';
                            return 'all';
                        }
                    }

                    if (!empty($notif['data_id'])) {
                        if ($is_rating_notif) {
                            $link = "/printflow/customer/rate_order.php?order_id=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                        } elseif ($is_payment_notif) {
                            $link = "/printflow/customer/payment.php?order_id=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                        } elseif ($notif['type'] === 'Order' || $notif['type'] === 'Status') {
                            $tab = $current_order_status ? map_status_to_tab($current_order_status) : 'all';
                            $link = "/printflow/customer/orders.php?tab=" . $tab . "&highlight=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                        } elseif ($notif['type'] === 'Job Order') {
                            $link = "/printflow/customer/order_details.php?id=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                        } elseif ($notif['type'] === 'Message') {
                            $link = "/printflow/customer/chat.php?order_id=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                        }
                    }

                    $msg = htmlspecialchars($notif['message']);
                    $msg = preg_replace('/(Order #\d+)/', '<b>$1</b>', $msg);
                ?>
                <a href="<?php echo $link; ?>" class="notif-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                    <div class="notif-card-inner">
                        <div class="notif-image-wrap">
                            <img src="<?php echo htmlspecialchars($final_image_url); ?>" 
                                 alt="<?php echo htmlspecialchars($display_name); ?>" 
                                 class="notif-image" 
                                 onerror="this.src='<?php echo $fallback_img; ?>';">
                        </div>
                        <div class="notif-content-wrap">
                            <div class="notif-title"><?php echo htmlspecialchars($display_name); ?></div>
                            <div class="notif-description"><?php echo $msg; ?></div>
                            <div class="notif-meta">
                                <div class="notif-time"><?php echo time_elapsed_string($notif['created_at']); ?></div>
                                <?php if (!empty($notif['data_id'])): ?>
                                    <span class="notif-view-btn"><?php echo $is_rating_notif ? 'Rate Now' : 'View'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="margin-top: 2rem;">
                <?php echo render_pagination($current_page, $total_pages, [], 'page'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

