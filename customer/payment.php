<?php
/**
 * Customer Order Payment Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_ui_helper.php';

require_role('Customer');

$order_id = (int)($_GET['order_id'] ?? 0);
$customer_id = get_user_id();
$is_job_order = false;

// Mark notification as read if parameter present
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
}

if (!$order_id) {
    die('<div style="text-align:center; padding: 50px; font-family: sans-serif;">
            <h2 style="color: #e11d48;">Invalid Order</h2>
            <p>The order ID is missing or invalid.</p>
            <a href="orders.php" style="color: #2563eb; text-decoration: none; font-weight: bold;">Back to My Orders</a>
         </div>');
}

// 1. First check regular orders
$order_result = db_query("
    SELECT * FROM orders 
    WHERE order_id = ? AND customer_id = ?
", 'ii', [$order_id, $customer_id]);

if (!empty($order_result)) {
    $order = $order_result[0];
    
    // Get order items
    $has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
    $has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
    $product_image_select = "'' AS product_image";
    if ($has_product_image && $has_photo_path) {
        $product_image_select = "COALESCE(p.photo_path, p.product_image) AS product_image";
    } elseif ($has_product_image) {
        $product_image_select = "p.product_image AS product_image";
    } elseif ($has_photo_path) {
        $product_image_select = "p.photo_path AS product_image";
    }

    $items = db_query("
        SELECT oi.*, p.name as product_name, p.category, {$product_image_select}
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ", 'i', [$order_id]);
    
    // Dynamically calculate total from items to ensure accuracy
    $calculated_total = 0;
    foreach ($items as $item) {
        $calculated_total += (float)$item['unit_price'] * (int)$item['quantity'];
    }

    $total_amount = ($calculated_total > 0) ? $calculated_total : (float)$order['total_amount'];

    // If items have zero unit_price but the order has a staff-set total_amount,
    // distribute that total across items in-memory so the item cards display correctly.
    // This handles existing orders where price was set before the order_items sync fix.
    if ($calculated_total <= 0 && $total_amount > 0 && !empty($items)) {
        $_total_qty = array_sum(array_column($items, 'quantity'));
        if ($_total_qty > 0) {
            $_remaining = $total_amount;
            $_count     = count($items);
            foreach ($items as $_idx => &$_item) {
                $_is_last   = ($_idx === $_count - 1);
                $_item_tot  = $_is_last ? $_remaining : round($total_amount * $_item['quantity'] / $_total_qty, 2);
                $_item['unit_price'] = ($_item['quantity'] > 0) ? round($_item_tot / $_item['quantity'], 4) : 0;
                $_remaining -= $_item_tot;
            }
            unset($_item);
        }
    }
    $payment_status = $order['payment_status']; // 'Paid', 'Unpaid'
    $order_status = $order['status'];
    $show_payment_form = ($payment_status !== 'Paid' && !in_array($order_status, ['Downpayment Submitted', 'To Verify', 'Cancelled']));
    
} else {
    // 2. Fallback to job orders
    $job_result = db_query("
        SELECT * FROM job_orders 
        WHERE id = ? AND customer_id = ?
    ", 'ii', [$order_id, $customer_id]);
    
    if (empty($job_result)) {
        die('<div style="text-align:center; padding: 50px; font-family: sans-serif;">
                <h2 style="color: #e11d48;">Order Not Found</h2>
                <p>The requested order was not found or you do not have permission to view it.</p>
                <a href="orders.php" style="color: #2563eb; text-decoration: none; font-weight: bold;">Back to My Orders</a>
             </div>');
    }
    
    $order = $job_result[0];
    $is_job_order = true;
    $total_amount = (float)$order['estimated_total'];
    $payment_status = $order['payment_status']; // 'PAID', 'UNPAID', 'PARTIAL'
    $order_status = $order['status'];
    
    // Normalize status names for consistent UI
    if ($payment_status === 'PAID') $payment_status = 'Paid';
    if ($payment_status === 'UNPAID') $payment_status = 'Unpaid';
    
    $show_payment_form = ($order['payment_status'] !== 'PAID' && $order['payment_proof_status'] !== 'SUBMITTED' && $order_status !== 'CANCELLED');
}

// Logic for redirecting to the correct tab in orders.php
$order_tab = 'all';
$s = strtolower($order_status);
if (strpos($s, 'pending') !== false || strpos($s, 'revision') !== false) $order_tab = 'pending';
elseif (strpos($s, 'approved') !== false) $order_tab = 'approved';
elseif (strpos($s, 'verify') !== false || strpos($s, 'submitted') !== false) $order_tab = 'toverify';
elseif (strpos($s, 'pay') !== false) $order_tab = 'topay';
elseif (strpos($s, 'production') !== false || strpos($s, 'process') !== false || strpos($s, 'print') !== false || strpos($s, 'paid') !== false) $order_tab = 'production';
elseif (strpos($s, 'ready') !== false || strpos($s, 'pickup') !== false) $order_tab = 'pickup';
elseif (strpos($s, 'completed') !== false || strpos($s, 'rate') !== false || strpos($s, 'rated') !== false) $order_tab = 'completed';
elseif (strpos($s, 'cancelled') !== false) $order_tab = 'cancelled';

$orders_redirect_url = "orders.php?tab={$order_tab}&highlight={$order_id}";

$page_title = "Payment - Order #{$order_id}";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* === PAYMENT PAGE — WIDE TWO-COLUMN LAYOUT === */
    .payment-container {
        max-width: 1280px;
        margin: 0 auto;
        padding-bottom: 4rem;
    }
    .payment-layout {
        display: grid;
        grid-template-columns: 1fr 420px;
        gap: 1.5rem;
        align-items: start;
    }
    @media (max-width: 900px) {
        .payment-layout { grid-template-columns: 1fr; }
        .payment-sidebar { order: -1; }
    }
    .payment-card {
        background: #ffffff !important;
        color: #1e293b !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
        overflow: hidden;
        margin-bottom: 1.25rem;
        backdrop-filter: none;
    }
    /* Fix all dark section titles → white */
    .payment-section-title {
        font-size: 0.95rem;
        font-weight: 800;
        color: #ffffff !important;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    /* Fix input label → visible light */
    .input-label {
        display: block;
        font-size: 0.82rem;
        font-weight: 700;
        color: #f1f5f9 !important;
        margin-bottom: 0.6rem;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }
    .amount-badge {
        background: linear-gradient(135deg, rgba(83,197,224,0.12), rgba(50,161,196,0.05));
        border: none;
        color: #1e293b;
        padding: 1.5rem 1.25rem;
        border-radius: 0;
        text-align: center;
        margin-bottom: 1.5rem;
    }
    .amount-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 0.5rem;
    }
    .amount-value {
        font-size: 2.5rem;
        font-weight: 900;
        color: #0d9488;
        letter-spacing: -0.02em;
    }
    .pm-tab-btn {
        flex: 1;
        padding: 12px;
        border-radius: 0;
        border: none;
        background: #0d9488;
        color: #fff;
        width: 100%;
        border-radius: 0.25rem;
        transition: all 0.25s;
        text-align: center;
        font-size: 0.875rem;
    }
    .pm-tab-btn.active {
        background: #ffffff;
        color: #0d9488;
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
    }
    .input-group { margin-bottom: 1.5rem; }
    .custom-input {
        width: 100%;
        padding: 12px 16px;
        background: #f8fafc;
        border: none;
        border-radius: 0;
        font-weight: 600;
        color: #1e293b;
        transition: all 0.25s;
        font-size: 1rem;
    }
    .custom-input:focus {
        outline: none;
        background: #f1f5f9;
        box-shadow: 0 0 0 3px rgba(83,197,224,0.12);
    }
    .dropzone {
        border: 2px dashed rgba(255,255,255,0.35);
        border-radius: 0;
        padding: 2rem 1.25rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s;
        background: #f8fafc;
    }
    .dropzone:hover {
        accent-color: #0d9488;
        background: rgba(83,197,224,0.06);
    }
    /* Fix dark dropzone text → white */
    .dropzone .dz-title { font-weight: 700; color: #1e293b !important; font-size: 0.9rem; }
    .dropzone .dz-sub   { font-size: 0.78rem; color: #64748b !important; }
    /* Fix Items heading */
    .items-heading { font-size: 0.88rem; font-weight: 800; color: #1e293b !important; }
    /* Show More btn */
    .show-more-btn {
        width: 100%;
        padding: 0.65rem;
        background: rgba(83,197,224,0.08);
        border: none;
        border-radius: 0;
        color: #0d9488 !important;
        font-weight: 700;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }
    .show-more-btn:hover { background: rgba(83,197,224,0.15); }
    .show-more-btn svg   { transition: transform 0.3s; }
    .show-more-btn.expanded svg { transform: rotate(180deg); }
    .items-hidden { display: none; }
    /* Compact specs in item card */
    .order-spec-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 0.6rem;
    }
    @media (max-width: 640px) {
        h1 { font-size: 1rem !important; }
        .payment-card { margin-bottom: 0.5rem !important; }
    }
    /* Spinner for OCR */
    .spinner-sm {
        width: 18px;
        height: 18px;
        border: 2px solid rgba(83, 197, 224, 0.2);
        border-top-color: #0369a1;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 payment-container">
            
            <div style="display: flex; align-items: center; justify-content: space-between; position: relative; margin-bottom: 2rem;">
                <?php 
                $back_url = 'orders.php';
                if (!empty($_SESSION['last_order_item_key'])) {
                    $back_url = 'order_review.php?item=' . urlencode($_SESSION['last_order_item_key']);
                }
                ?>
                <a href="<?php echo $back_url; ?>" style="text-decoration: none; display: flex; align-items: center; gap: 4px; color: #64748b; font-weight: 600; transition: color 0.2s;" onmouseover="this.style.color='#53c5e0'" onmouseout="this.style.color='#9fc4d4'">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <h1 class="text-2xl font-bold" style="margin: 0; position: absolute; left: 50%; transform: translateX(-50%); color: #1e293b;">Complete Payment</h1>
            </div>
            
            <!-- TWO COLUMN LAYOUT -->
            <div class="payment-layout">

                <!-- LEFT: Order Summary -->
                <div class="payment-main">
                <div class="payment-card p-6">
                <!-- Grand Total -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: none; text-align: center;">
                    <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 0.4rem;">Order Total Amount</span>
                    <span style="font-size: 2.25rem; font-weight: 900; color: #0369a1; letter-spacing: -0.01em;">₱ <?php echo number_format($total_amount, 2); ?></span>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <?php if (!$is_job_order): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <h3 class="items-heading">Items (<?php echo count($items); ?>)</h3>
                        </div>
                        <?php 
                        $item_index = 0;
                        foreach ($items as $item): 
                            $item_index++;
                            $is_hidden = ($item_index > 3);
                        ?>
                            <div class="<?php echo $is_hidden ? 'items-hidden' : ''; ?>" style="margin-bottom: 0.75rem; border-bottom: 1px solid rgba(83,197,224,0.12); padding-bottom: 0.75rem; <?php echo ($item_index === count($items)) ? 'border-bottom: none;' : ''; ?>">
                                <?php render_order_item_clean($item, false, true); ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($items) > 3): ?>
                        <button type="button" class="show-more-btn" onclick="toggleItems(this)" style="margin-bottom: 1rem;">
                            <span class="show-more-text">Show All <?php echo count($items); ?> Items</span>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <?php endif; ?>
                    <?php else: ?>
                            <!-- Job Order item style -->
                            <!-- Job Order item style (Matches the new dark renderer) -->
                            <div style="background: #ffffff; padding: 0; overflow: hidden; border: none; border-radius: 0; margin-bottom: 1.25rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">
                                <div style="padding: 1.25rem; display: flex; gap: 1.25rem; align-items: flex-start; border-bottom: 1px solid rgba(83, 197, 224, 0.15); background: #f8fafc;">
                                    <div style="width: 130px; height: 130px; border-radius: 0; overflow: hidden; background: #f1f5f9; border: none; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);">
                                        <?php if (!empty($order['artwork_path'])): ?>
                                            <img src="/printflow/<?php echo htmlspecialchars($order['artwork_path']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease-in-out;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                                        <?php else: ?>
                                            <span style="font-size: 2.2rem; color: #cbd5e1;">🛠️</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column;">
                                        <h3 style="font-size: 0.95rem; line-height: 1.3rem; font-weight: 600; color: #0f172a !important; margin: 0 0 0.3rem 0;"><?php echo htmlspecialchars($order['job_title']); ?></h3>
                                        <div style="display: inline-flex; font-size: 0.72rem; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.08em; padding: 3px 10px; border-radius: 0; background: rgba(3, 105, 161, 0.05); border: none; margin-bottom: 1.25rem; align-self: flex-start;">
                                            <?php echo htmlspecialchars($order['service_type']); ?>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: auto;">
                                            <div style="flex: 1; min-width: 80px;">
                                                <div style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Quantity</div>
                                                <div style="font-size: 1rem; color: #1e293b; font-weight: 700;"><?php echo $order['quantity']; ?></div>
                                            </div>
                                            <div style="flex: 1; min-width: 100px;">
                                                <div style="font-size: 0.68rem; color: #0369a1; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Estimated Total</div>
                                                <div style="font-size: 1rem; color: #0369a1; font-weight: 800;"><?php echo format_currency($total_amount); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div style="padding: 1.25rem; background: #ffffff;">
                                    <h4 style="font-size: 0.85rem; font-weight: 800; color: #1e293b; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">Order Specifications</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.85rem;">
                                        <div style="background: #f8fafc; border: none; padding: 0.75rem 0.85rem; border-radius: 0;">
                                            <div style="font-size: 0.65rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Size</div>
                                            <div style="font-size: 0.95rem; font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($order['width_ft'] . ' x ' . $order['height_ft']); ?> ft</div>
                                        </div>
                                        <?php if (!empty($order['notes'])): ?>
                                            <div style="grid-column: 1 / -1; margin-top: 0.75rem; padding: 1.15rem; background: rgba(3, 105, 161, 0.05); border: none; border-radius: 0;">
                                                <div style="font-size: 0.75rem; font-weight: 800; color: #0369a1; text-transform: uppercase; margin-bottom: 6px;">📝 Special Instructions & Notes</div>
                                                <div style="font-size: 0.95rem; color: #1e293b; line-height: 1.6; font-weight: 600;"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                    <?php endif; ?>
                </div>
                </div><!-- end .payment-card -->
                </div><!-- end .payment-main -->

                <!-- RIGHT: Payment Sidebar -->
                <div class="payment-sidebar">
                <div class="payment-card p-6">

                <!-- Divider between order info top and payment form -->

                <!-- Payment Section -->
                <?php if ($payment_status === 'Paid'): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, #059669, #047857); display: flex; align-items: center; justify-content: center; position: relative;">
                            <svg style="width: 48px; height: 48px; color: #fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h3 style="font-weight: 800; color: #059669; margin-bottom: 0.5rem;">Payment Completed</h3>
                        <p style="color: #64748b; font-size: 0.875rem;">This order has already been fully paid.</p>
                        <a href="<?php echo $orders_redirect_url; ?>" class="btn-primary w-full mt-6 text-center block" style="text-decoration: none;">View Order Status</a>
                    </div>
                <?php elseif (!$show_payment_form): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 1.5rem; background: rgba(83,197,224,0.12); border: 2px solid #53c5e0; display: flex; align-items: center; justify-content: center; position: relative;">
                            <svg style="width: 48px; height: 48px; color: #0369a1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">Payment Verifying</h3>
                        <p style="color: #64748b; font-size: 0.875rem;">Your payment proof is currently under review by our staff.</p>
                        <a href="<?php echo $orders_redirect_url; ?>" class="btn-primary w-full mt-6 text-center block" style="text-decoration: none;">Track Order Status</a>
                    </div>
                <?php else: ?>
                    <form id="paymentForm" enctype="multipart/form-data">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        <input type="hidden" name="is_job" value="<?php echo $is_job_order ? '1' : '0'; ?>">
                        <input type="hidden" name="payment_method" id="selected_payment_method" value="">
                        <?php echo csrf_field(); ?>

                        <h2 class="payment-section-title" style="margin-bottom: 1rem; font-size: 1rem;">1. Choose Method</h2>
                        
                        <!-- Important Note - Moved above QR code -->
                        <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.08)); border-left: 4px solid #ef4444; padding: 1rem 1.25rem; margin-bottom: 1.5rem; border-radius: 0;">
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                                <div style="font-size: 1.5rem; line-height: 1; flex-shrink: 0;">⚠️</div>
                                <div>
                                    <div style="font-weight: 800; color: #dc2626; font-size: 0.875rem; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Important Reminder</div>
                                    <div style="color: #991b1b; font-size: 0.875rem; line-height: 1.6; font-weight: 600;">
                                        <strong style="color: #dc2626;">Take a screenshot</strong> of your payment transaction <strong style="color: #dc2626;">before closing</strong> the payment app. You'll need to upload it here as proof of payment.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php 
                        $qr_dir = __DIR__ . '/../public/assets/uploads/qr/';
                        $payment_cfg_path = $qr_dir . 'payment_methods.json';
                        $payment_methods = file_exists($payment_cfg_path) ? json_decode(file_get_contents($payment_cfg_path), true) : [];
                        $enabled_methods = array_filter($payment_methods ?: [], function($m) { return !empty($m['enabled']); });
                        
                        // Determine if this is a product order (no customization) or service order
                        $is_product_order = true;
                        if (!$is_job_order && !empty($items)) {
                            foreach ($items as $item) {
                                $custom_data = json_decode($item['customization_data'] ?? '{}', true);
                                if (!empty($custom_data) && count($custom_data) > 0) {
                                    $is_product_order = false;
                                    break;
                                }
                            }
                        } elseif ($is_job_order) {
                            $is_product_order = false;
                        }
                        ?>

                        <?php if (empty($enabled_methods)): ?>
                            <div style="background: #fff1f2; border: none; border-radius: 0; padding: 1rem; color: #be123c; font-size: 0.875rem; font-weight: 600; margin-bottom: 1.5rem;">
                                Online payment is currently unavailable. Please contact the shop.
                            </div>
                        <?php else: ?>
                            <div style="display: flex; gap: 8px; margin-bottom: 1.5rem;">
                                <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                    <button type="button" onclick="selectPM(<?php echo $index; ?>, '<?php echo addslashes($pm['provider']); ?>')" id="btn-pm-<?php echo $index; ?>" class="pm-tab-btn <?php echo $first ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($pm['provider']); ?>
                                    </button>
                                <?php $first = false; endforeach; ?>
                            </div>

                            <div id="pm-details-container" style="background: #ffffff; border: none; border-radius: 0; padding: 1.75rem; margin-bottom: 2.25rem; text-align: center; backdrop-filter: blur(8px);">
                                <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                    <div id="pm-info-<?php echo $index; ?>" style="display: <?php echo $first ? 'block' : 'none'; ?>;">
                                        <?php if (!empty($pm['file'])): ?>
                                            <img src="/printflow/public/assets/uploads/qr/<?php echo htmlspecialchars($pm['file']); ?>" style="width: 170px; height: 170px; object-fit: contain; margin: 0 auto 1.25rem; display: block; border-radius: 0; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                                        <?php endif; ?>
                                        <div style="font-weight: 800; color: #1e293b; font-size: 1.05rem; letter-spacing: 0.01em;"><?php echo htmlspecialchars($pm['provider']); ?></div>
                                        <div style="color: #64748b; font-size: 0.875rem; font-weight: 600; margin-top: 6px;"><?php echo htmlspecialchars($pm['label']); ?></div>
                                    </div>
                                <?php $first = false; endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Simplified Flow: Always Full Payment -->
                        <input type="hidden" name="amount" value="<?php echo number_format($order['total_amount'], 2, '.', ''); ?>">
                        <input type="hidden" name="payment_choice" value="full">

                        <h2 class="payment-section-title" style="margin-bottom: 1rem; font-size: 1rem; color: #ffffff;">2. Upload Reference Receipt</h2>
                        
                        <div class="input-group">
                            <label class="input-label" style="color: #ffffff;">Upload Reference Receipt</label>
                            <input type="file" name="payment_proof" id="proofInput" style="display: none;" accept="image/*" required>
                            <div id="dropzone" class="dropzone" onclick="document.getElementById('proofInput').click()">
                                <div id="placeholder" style="display: block;">
                                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📸</div>
                                    <div class="dz-title">Click to upload receipt</div>
                                    <div class="dz-sub">JPG, PNG or PDF</div>
                                </div>
                                <div id="preview" style="display: none; align-items: center; justify-content: center; flex-direction: column; width: 100%; overflow: hidden;">
                                    <img id="previewImg" src="" style="max-height: 120px; border-radius: 8px; margin-bottom: 10px; max-width: 100%; object-fit: contain;">
                                    <p id="fileName" style="font-size: 0.8125rem; font-weight: 700; color: #1e293b; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px;"></p>
                                </div>
                            </div>
                            <!-- Real-time OCR Result Container -->
                            <div id="ocrInfo" style="display: none; margin-top: 12px;"></div>

                            <!-- Manual Fallback Fields (Hidden by default) -->
                            <div id="manualFields" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8fafc; border: 1px solid rgba(83,197,224,0.15);">
                                <h4 style="font-size: 0.75rem; font-weight: 800; color: #0369a1; margin-bottom: 0.75rem; text-transform: uppercase;">Manual Correction</h4>
                                <div class="input-group" style="margin-bottom: 0.75rem;">
                                    <label class="input-label" style="font-size: 0.7rem; color: #64748b !important;">Enter Reference Number</label>
                                    <input type="text" name="manual_ref" id="manual_ref" class="custom-input" placeholder="Refer to your receipt">
                                </div>
                                <div class="input-group" style="margin-bottom: 0;">
                                    <label class="input-label" style="font-size: 0.7rem; color: #64748b !important;">Enter Exact Amount Paid</label>
                                    <input type="number" step="0.01" name="manual_amount" id="manual_amount" class="custom-input" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="submitBtn" class="shopee-btn-primary" style="width: 100%; padding: 0.75rem; white-space: nowrap; text-decoration: none; text-align: center; display: block; font-weight: 700; font-size: 0.9rem; border-radius: 0; border: none; background: #0d9488 !important; color: #ffffff !important; text-transform: uppercase; letter-spacing: 0.02em; cursor: pointer; box-shadow: 0 10px 22px rgba(13, 148, 136, 0.3); transition: all 0.2s;" <?php echo empty($enabled_methods) ? 'disabled style="opacity:0.5;"' : ''; ?> onmouseover="this.style.background='#32a1c4'" onmouseout="this.style.background='#53c5e0'">
                            Submit Payment Proof
                        </button>
                    </form>
                <?php endif; ?>
                </div><!-- end payment-card sidebar -->
                </div><!-- end payment-sidebar -->

            </div><!-- end payment-layout -->

    </div>
</div>

<script>
    {
        const toggleItems = function(btn) {
            const hiddenItems = document.querySelectorAll('.items-hidden');
            const isExpanded = btn.classList.contains('expanded');
            const textSpan = btn.querySelector('.show-more-text');
            const totalItems = <?php echo count($items); ?>;
            
            if (isExpanded) {
                // Collapse
                hiddenItems.forEach(item => item.style.display = 'none');
                btn.classList.remove('expanded');
                textSpan.textContent = 'Show All ' + totalItems + ' Items';
            } else {
                // Expand
                hiddenItems.forEach(item => item.style.display = 'block');
                btn.classList.add('expanded');
                textSpan.textContent = 'Show Less';
            }
        };
        window.toggleItems = toggleItems;

        const selectPM = function(idx, provider) {
            document.querySelectorAll('.pm-tab-btn').forEach(b => b.classList.remove('active'));
            const btn = document.getElementById('btn-pm-' + idx);
            if (btn) btn.classList.add('active');
            
            document.querySelectorAll('[id^="pm-info-"]').forEach(i => i.style.display = 'none');
            const info = document.getElementById('pm-info-' + idx);
            if (info) info.style.display = 'block';

            const methodInput = document.getElementById('selected_payment_method');
            if (methodInput) methodInput.value = provider;
        };
        window.selectPM = selectPM;

        // Initialize default selection
        document.addEventListener('DOMContentLoaded', () => {
            const activeBtn = document.querySelector('.pm-tab-btn.active');
            if (activeBtn) {
                activeBtn.click();
            }
        });

        const proofInput = document.getElementById('proofInput');
        const placeholder = document.getElementById('placeholder');
        const preview = document.getElementById('preview');
        const previewImg = document.getElementById('previewImg');
        const fileName = document.getElementById('fileName');

        if (proofInput) {
            proofInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (file) {
                    // UI Preview
                    fileName.textContent = file.name;
                    const reader = new FileReader();
                    reader.onload = (re) => {
                        previewImg.src = re.target.result;
                        previewImg.style.borderRadius = '0';
                        placeholder.style.display = 'none';
                        preview.style.display = 'flex';
                    };
                    reader.readAsDataURL(file);

                    // Start OCR Real-time extraction
                    const ocrInfo = document.getElementById('ocrInfo');
                    if (ocrInfo) {
                        ocrInfo.style.display = 'block';
                        ocrInfo.innerHTML = `
                            <div style="background: rgba(83, 197, 224, 0.05); border: 1px dashed rgba(83, 197, 224, 0.2); padding: 12px; border-radius: 4px; text-align: center;">
                                <div class="spinner-sm" style="margin: 0 auto 8px;"></div>
                                <div style="color: #64748b; font-size: 0.75rem; font-weight: 600;">EXTRACTING RECEIPT DATA...</div>
                            </div>`;
                    }

                    const formData = new FormData();
                    formData.append('payment_proof', file);
                    
                    try {
                        const res = await fetch('api_ocr_preview.php', { 
                            method: 'POST', 
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const result = await res.json();
                        
                        if (result.success && ocrInfo) {
                            const d = result.details;
                            const hasAmt = d.amount && parseFloat(d.amount) > 0;
                            const hasRef = d.reference_id && d.reference_id.length >= 8;
                            const hasData = hasAmt && hasRef;
                            
                            let html = `<div style="background: ${hasData ? 'rgba(83, 197, 224, 0.1)' : 'rgba(239, 68, 68, 0.1)'}; border: 1px solid ${hasData ? 'rgba(83, 197, 224, 0.2)' : 'rgba(239, 68, 68, 0.2)'}; padding: 12px; border-radius: 4px; position: relative;">
                                <div style="font-size: 0.65rem; color: ${hasData ? '#53c5e0' : '#ef4444'}; font-weight: 800; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.08em; display: flex; align-items: center; gap: 6px;">
                                    ${hasData ? '<span>✨ AI EXTRACTION SUCCESS</span>' : '<span>⚠️ PARTIAL DETECTION</span>'}
                                </div>`;
                            
                            // Amount detected
                            if (hasAmt) {
                                html += `<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <span style="color: #64748b; font-size: 0.78rem; font-weight: 600;">TOTAL AMOUNT:</span>
                                    <span style="color: #1e293b; font-weight: 800; font-size: 1.1rem;">₱${parseFloat(d.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                                </div>`;
                                document.getElementById('manual_amount').value = d.amount;
                            } else {
                                html += `<div style="color: #fca5a5; font-size: 0.75rem; margin-bottom: 6px; font-weight: 600;">• Could not read amount automatically</div>`;
                            }
                            
                            // Reference detected
                            if (hasRef) {
                                html += `<div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #64748b; font-size: 0.78rem; font-weight: 600;">REFERENCE NO:</span>
                                    <span style="color: #1e293b; font-weight: 800; font-family: monospace;">${d.reference_id}</span>
                                </div>`;
                                document.getElementById('manual_ref').value = d.reference_id;
                            } else {
                                html += `<div style="color: #fca5a5; font-size: 0.75rem; font-weight: 600;">• Could not find reference number</div>`;
                            }
                            
                            html += `</div>`;
                            ocrInfo.innerHTML = html;

                            // Show manual fields if anything is missing
                            if (!hasAmt || !hasRef) {
                                document.getElementById('manualFields').style.display = 'block';
                            } else {
                                document.getElementById('manualFields').style.display = 'none';
                            }
                        } else if (ocrInfo) {
                            ocrInfo.innerHTML = `<div style="color: #ef4444; font-size: 0.75rem; font-weight: 600; text-align: center;">Unable to process image for preview.</div>`;
                        }
                    } catch (err) {
                        console.error('OCR Preview error:', err);
                        if (ocrInfo) ocrInfo.style.display = 'none';
                    }
                }
            });
        }

        // Reliable Form Submission (Vanilla JS)
        const paymentForm = document.getElementById('paymentForm');
        const submitBtn = document.getElementById('submitBtn');

        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Prevent double submission
                if (submitBtn.disabled) return;
                
                // Final validation: check if file is present
                const fileInput = document.getElementById('proofInput');
                if (!fileInput || !fileInput.files[0]) {
                    alert('Please select a proof of payment image first.');
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.innerHTML = '<span style="display:flex; align-items:center; justify-content:center; gap:8px;"><div class="spinner-sm"></div> Processing...</span>';

                const formData = new FormData(this);
                // Explicitly set the file to ensure it's included correctly
                formData.set('payment_proof', fileInput.files[0]);
                
                fetch('api_submit_payment.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Server returned an invalid response. Please check the logs.');
                    }
                })
                .then(data => {
                    if (data.success) {
                        if (typeof window.showSuccessModal === 'function') {
                            window.showSuccessModal(
                                'Payment Success',
                                'Your payment proof has been submitted and is now under review. We\'ll notify you once verified!',
                                'orders.php?tab=toverify&highlight=<?php echo $order_id; ?>',
                                'services.php',
                                'View Order',
                                'Back to Services',
                                'services.php',
                                4000
                            );
                        } else {
                            alert('Payment submitted successfully! Redirecting...');
                            window.location.href = 'orders.php?tab=toverify&highlight=<?php echo $order_id; ?>';
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                        submitBtn.innerHTML = 'Submit Payment Proof';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.innerHTML = 'Submit Payment Proof';
                });
            });
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
