<?php
/**
 * Customer Orders (All) - Fallback page
 * Simple, stable page that always shows all orders.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();

$orders = db_query(
    "SELECT
        o.order_id,
        o.order_date,
        o.status,
        o.payment_status,
        o.total_amount,
        (
            SELECT COALESCE(p.name, 'Service Order')
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = o.order_id
            ORDER BY oi.order_item_id ASC
            LIMIT 1
        ) AS first_item_name,
        (
            SELECT p.product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = o.order_id
            ORDER BY oi.order_item_id ASC
            LIMIT 1
        ) AS first_item_image,
        (
            SELECT oi.customization_data
            FROM order_items oi
            WHERE oi.order_id = o.order_id
            ORDER BY oi.order_item_id ASC
            LIMIT 1
        ) AS first_item_customization,
        (
            SELECT COALESCE(SUM(oi.quantity), 0)
            FROM order_items oi
            WHERE oi.order_id = o.order_id
        ) AS total_quantity
     FROM orders o
     WHERE o.customer_id = ?
     ORDER BY o.order_date DESC",
    'i',
    [$customer_id]
);

if (!is_array($orders)) {
    $orders = [];
}

$hidden_price_statuses = ['Pending', 'Pending Approval', 'Pending Review', 'For Revision', 'Approved'];

$page_title = 'All Orders - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .orders-all-page {
        color: #1e293b;
    }
    .orders-all-page .card {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 1.25rem !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
        overflow: hidden;
    }
    .orders-all-page .head-row {
        border-bottom: 1px solid #e2e8f0;
        padding: 1.5rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8fafc;
    }
    .orders-all-page .row {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        transition: background 0.2s;
    }
    .orders-all-page .row:hover {
        background: #f8fafc;
    }
    .orders-all-page .row:last-child { border-bottom: 0; }
    .orders-all-page .thumb {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
        background: #ffffff;
    }
    .orders-all-page .meta {
        flex: 1;
        min-width: 0;
    }
    .orders-all-page .name {
        font-weight: 700;
        color: #0f172a;
        line-height: 1.25;
        font-size: 1.05rem;
    }
    .orders-all-page .sub {
        font-size: 0.85rem;
        color: #64748b;
        margin-top: 0.2rem;
    }
    .orders-all-page .right {
        text-align: right;
        flex-shrink: 0;
    }
</style>

<div class="min-h-screen py-8 orders-all-page">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <div class="card">
            <div class="head-row">
                <div style="font-size:1.1rem; font-weight:700; color:#0f172a;">All Customer Orders</div>
                <div style="font-size:0.85rem; color:#64748b;">Total: <?php echo count($orders); ?></div>
            </div>

            <?php if (empty($orders)): ?>
                <div style="padding:3rem 1.5rem; color:#64748b; text-align:center;">No orders found.</div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                        $c = !empty($order['first_item_customization']) ? json_decode((string)$order['first_item_customization'], true) : [];
                        $service_type = trim((string)($c['service_type'] ?? ''));
                        $display_name = $service_type !== '' ? $service_type : (string)($order['first_item_name'] ?? 'Order Item');
                        $qty = max(1, (int)($order['total_quantity'] ?? 0));
                        $img = trim((string)($order['first_item_image'] ?? ''));
                        if ($img === '') {
                            $img = get_service_image_url($display_name);
                        }
                    ?>
                    <div class="row">
                        <img class="thumb" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($display_name); ?>" onerror="this.src='/printflow/public/assets/images/services/default.png';">
                        <div class="meta">
                            <div class="name"><?php echo htmlspecialchars($display_name); ?></div>
                            <div class="sub" style="font-weight:600; color:#1e293b;"><?php echo $qty; ?>x</div>
                            <div class="sub"><?php echo format_datetime($order['order_date']); ?></div>
                            <div class="sub">Order #<?php echo (int)$order['order_id']; ?></div>
                        </div>
                        <div class="right">
                            <?php if (in_array((string)$order['status'], $hidden_price_statuses, true)): ?>
                                <div class="sub" style="font-style:italic;">Price confirmed by shop</div>
                            <?php else: ?>
                                <div style="font-size:1.1rem; font-weight:700; color:#0369a1;"><?php echo format_currency((float)$order['total_amount']); ?></div>
                            <?php endif; ?>
                            <div style="margin-top:0.35rem;"><?php echo status_badge((string)$order['status'], 'order'); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

