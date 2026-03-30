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
    ORDER BY n.created_at DESC LIMIT 100
", 'i', [$customer_id]);

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
    .notif-wrapper {
        background: rgba(10, 37, 48, 0.48);
        backdrop-filter: blur(12px);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        padding: 0;
        min-height: 400px;
        margin-bottom: 3rem;
        overflow: hidden;
        border: 1px solid rgba(83, 197, 224, 0.2);
    }
    .notif-header {
        padding: 24px 32px;
        border-bottom: 1px solid rgba(83, 197, 224, 0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .notif-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #eaf6fb;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .notif-group-title {
        background: rgba(83, 197, 224, 0.06);
        padding: 12px 24px;
        font-size: 0.75rem;
        font-weight: 800;
        color: #53c5e0;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        border-bottom: 1px solid rgba(83, 197, 224, 0.1);
    }
    .notif-item {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 20px 32px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        position: relative;
        background: transparent;
        border-bottom: 1px solid rgba(83, 197, 224, 0.08);
        text-decoration: none;
        border-left: 4px solid transparent;
    }
    .notif-item:hover {
        background: rgba(83, 197, 224, 0.08);
        transform: translateX(6px);
    }
    .notif-item.unread {
        background: rgba(83, 197, 224, 0.04);
        border-left-color: #53c5e0;
    }
    .notif-item.unread .notif-text {
        color: #eaf6fb;
        font-weight: 600;
    }
    .notif-avatar {
        width: 68px;
        height: 68px;
        min-width: 68px;
        border-radius: 16px;
        overflow: hidden;
        background: rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid rgba(83, 197, 224, 0.2);
    }
    .notif-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .notif-content {
        flex: 1;
        min-width: 0;
    }
    .notif-text {
        font-size: 0.95rem;
        line-height: 1.6;
        color: #9fc4d4;
        margin-bottom: 6px;
    }
    .notif-text b, .notif-text strong {
        color: #eaf6fb;
        font-weight: 800;
    }
    .notif-time {
        font-size: 0.8rem;
        color: #53c5e0;
        font-weight: 700;
        opacity: 0.8;
    }
    .notif-actions {
        margin-left: auto;
        flex-shrink: 0;
    }
    .notif-btn {
        padding: 8px 18px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.25s;
        border: 1px solid rgba(83, 197, 224, 0.3);
        background: rgba(255, 255, 255, 0.05);
        color: #eaf6fb;
    }
    .notif-item:hover .notif-btn {
        border-color: #53c5e0;
        background: #53c5e0;
        color: #030d11;
        box-shadow: 0 4px 12px rgba(83, 197, 224, 0.3);
    }
    .notif-item.unread .notif-text span[style*="color: #ef4444"] {
        color: #53c5e0 !important;
        text-shadow: 0 0 8px rgba(83, 197, 224, 0.5);
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 1100px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h1 class="ct-page-title" style="margin-bottom: 0;">Notifications</h1>
                <?php if ($unread_total > 0): ?>
                    <span class="count-badge" style="background: #53c5e0; color: #030d11; padding: 2px 10px; border-radius: 8px; font-size: 0.85rem; font-weight: 900; box-shadow: 0 0 15px rgba(83, 197, 224, 0.4);"><?php echo $unread_total; ?></span>
                <?php endif; ?>
            </div>
            <a href="?mark_all_read=1" class="btn-secondary" style="font-size: 0.75rem; border-radius: 12px; padding: 0.65rem 1.25rem; text-decoration: none; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; background: rgba(83, 197, 224, 0.1); border: 1px solid rgba(83, 197, 224, 0.2); color: #53c5e0; transition: all 0.25s;">Mark all as read</a>
        </div>

        <div class="notif-wrapper">

            <?php if (empty($notifications)): ?>
                <div class="text-center py-20">
                    <p class="text-gray-400">No notifications yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-0">
                    <?php foreach ($grouped_notifications as $group => $notifs): ?>
                        <div class="notif-group">
                            <div class="notif-group-title"><?php echo htmlspecialchars($group); ?></div>
                            <div>
                                <?php foreach ($notifs as $notif): 
                            // Determine "Avatar" or Icon based on message
                            $avatar_text = "PF"; // Default
                            $is_chat = (strpos(strtolower($notif['message']), 'message') !== false || strpos(strtolower($notif['message']), 'chat') !== false);
                            $is_order = (strpos(strtolower($notif['message']), 'order') !== false);
                            
                            $icon = "🔔";
                            if ($is_chat) $icon = "💬";
                            if ($is_order) $icon = "📦";

                            // Prefer actual service name from customization over product name (e.g. Transparent Sticker not "Sticker Pack")
                            $name_data = !empty($notif['first_item_customization']) ? json_decode($notif['first_item_customization'], true) : [];
                            $raw_service_name = trim((string)($name_data['service_type'] ?? $notif['jo_service_category'] ?? $notif['service_name'] ?? ''));
                            if (empty($raw_service_name) || in_array(strtolower($raw_service_name), ['custom order', 'customer order', 'service order', 'order item', 'order update'])) {
                                $raw_service_name = get_service_name_from_customization($name_data, $notif['service_name'] ?? 'Order Update');
                            }
                            $display_name = normalize_service_name($raw_service_name, 'Order Update');

                            // Determine image: design first, then service image from correct service name
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

                            // Determine redirection link
                            $link = "/printflow/customer/notifications.php?mark_read=" . $notif['notification_id'];
                            $is_rating_notif = (
                                (string)$notif['type'] === 'Rating' ||
                                stripos((string)$notif['message'], 'rate your experience') !== false ||
                                stripos((string)$notif['message'], 'rate your order') !== false
                            );

                            // Detect "Payment Required" or "TO_PAY" status
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

                            // Resolve current order status from DB for smart tab routing
                            $current_order_status = null;
                            if (!empty($notif['data_id']) && in_array($notif['type'], ['Order', 'Status', 'Payment'])) {
                                $ord_row = db_query("SELECT status FROM orders WHERE order_id = ? AND customer_id = ? LIMIT 1", 'ii', [$notif['data_id'], $customer_id]);
                                if (!empty($ord_row)) {
                                    $current_order_status = $ord_row[0]['status'];
                                    // Override payment detection based on live status
                                    if (stripos($current_order_status, 'To Pay') !== false || stripos($current_order_status, 'TO_PAY') !== false) {
                                        $is_payment_notif = true;
                                    }
                                }
                            }

                            // Map status → tab key (mirrors $tab_status_map in orders.php)
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
                                    // Redirect to orders.php with the correct tab + highlight the order
                                    $tab = $current_order_status ? map_status_to_tab($current_order_status) : 'all';
                                    $link = "/printflow/customer/orders.php?tab=" . $tab . "&highlight=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                                } elseif ($notif['type'] === 'Job Order') {
                                    $link = "/printflow/customer/order_details.php?id=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                                } elseif ($notif['type'] === 'Message') {
                                    $link = "/printflow/customer/chat.php?order_id=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                                }
                            }
                        ?>
                            <a href="<?php echo $link; ?>" class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                
                                <div class="notif-avatar">
                                    <img src="<?php echo htmlspecialchars($final_image_url); ?>" alt="<?php echo htmlspecialchars($display_name); ?>" class="notif-image" onerror="this.src='<?php echo $fallback_img; ?>';">
                                </div>

                                <div class="notif-content">
                                    <div class="notif-text" style="<?php echo $notif['is_read'] ? '' : 'font-weight: 600; color: #eaf6fb;'; ?>">
                                        <strong><?php echo htmlspecialchars($display_name); ?></strong> – 
                                        <?php 
                                            $msg = htmlspecialchars($notif['message']);
                                            $msg = preg_replace('/(Order #\d+)/', '<b>$1</b>', $msg);
                                            echo $msg;
                                        ?>
                                        <?php if ($notif['is_read'] == 0): ?>
                                            <span style="color: #ef4444; font-weight: 800; font-size: 0.6rem; margin-left: 0.25rem;">●</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notif-time"><?php echo time_elapsed_string($notif['created_at']); ?></div>
                                </div>

                                <?php if (!empty($notif['data_id'])): ?>
                                    <div class="notif-actions">
                                        <span class="notif-btn notif-btn-secondary"><?php echo $is_rating_notif ? 'Rate Now' : 'View'; ?></span>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
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

