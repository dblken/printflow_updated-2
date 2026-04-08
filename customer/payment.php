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

$page_title = "Payment - Order #{$order_id}";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .payment-container {
        max-width: 960px;
        margin: 0 auto;
        padding-bottom: 5rem;
    }
    .payment-header {
        margin-bottom: 1.5rem;
    }
    .payment-card {
        background: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 12px !important;
        box-shadow: none;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .payment-section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #111827 !important;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .amount-badge {
        background: linear-gradient(135deg, rgba(83, 197, 224, 0.1), rgba(50, 161, 196, 0.05));
        border: 1px solid rgba(83, 197, 224, 0.28);
        color: #eaf6fb;
        padding: 2rem 1.5rem;
        border-radius: 20px;
        text-align: center;
        margin-bottom: 2rem;
    }
    .amount-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #9fc4d4;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 0.65rem;
    }
    .amount-value {
        font-size: 2.75rem;
        font-weight: 900;
        color: #53c5e0;
        letter-spacing: -0.02em;
    }
    .pm-tab-btn {
        flex: 1;
        padding: 14px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.25s;
        text-align: center;
        font-size: 0.9rem;
    }
    .pm-tab-btn.active {
        border-color: #0ea5e9;
        background: #0ea5e9;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
    }
    .input-group {
        margin-bottom: 1.75rem;
    }
    .input-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        color: #374151;
        margin-bottom: 0.75rem;
        letter-spacing: 0.01em;
    }
    .custom-input {
        width: 100%;
        padding: 14px 18px;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-weight: 600;
        color: #1f2937;
        transition: all 0.25s;
        font-size: 1rem;
    }
    .custom-input:focus {
        border-color: #0ea5e9;
        outline: none;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }
    .dropzone {
        border: 2px dashed #d1d5db;
        border-radius: 12px;
        padding: 3rem 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s;
        background: #f9fafb;
    }
    .dropzone:hover {
        border-color: #0ea5e9;
        background: #f0f9ff;
    }

    /* Show More/Less Button */
    .show-more-btn {
        width: 100%;
        padding: 0.75rem;
        background: rgba(83, 197, 224, 0.08);
        border: 1px dashed rgba(83, 197, 224, 0.3);
        border-radius: 10px;
        color: #0369a1;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }
    .show-more-btn:hover {
        background: rgba(83, 197, 224, 0.15);
        border-color: rgba(83, 197, 224, 0.5);
    }
    .show-more-btn svg {
        transition: transform 0.3s;
    }
    .show-more-btn.expanded svg {
        transform: rotate(180deg);
    }
    .items-hidden {
        display: none;
    }
    
    @media (max-width: 640px) {
        h1 {
            font-size: 1rem !important;
        }
        
        .payment-card {
            margin-bottom: 0.5rem !important;
        }
        
        .payment-container > div:first-child > div:first-child {
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 0.75rem !important;
            margin-bottom: 1rem !important;
        }
        
        .payment-container > div:first-child > div:first-child a {
            position: static !important;
            margin-bottom: 0 !important;
        }
        
        .payment-container > div:first-child > div:first-child h1 {
            position: static !important;
            transform: none !important;
            left: auto !important;
            width: auto !important;
            text-align: right !important;
            font-size: 1rem !important;
            white-space: nowrap !important;
            margin-left: auto !important;
        }
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 payment-container">
        
        <div style="max-width: 650px; margin: 0 auto;">
            
            <div style="display: flex; align-items: center; justify-content: space-between; position: relative; margin-bottom: 2rem;">
                <?php 
                $back_url = 'orders.php';
                if (!empty($_SESSION['last_order_item_key'])) {
                    $back_url = 'order_review.php?item=' . urlencode($_SESSION['last_order_item_key']);
                }
                ?>
                <a href="<?php echo $back_url; ?>" style="text-decoration: none; display: flex; align-items: center; gap: 4px; color: #374151; font-weight: 600; transition: color 0.2s;" onmouseover="this.style.color='#111827'" onmouseout="this.style.color='#374151'">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <h1 class="text-2xl font-bold text-gray-800" style="margin: 0; position: absolute; left: 50%; transform: translateX(-50%);">Complete Payment</h1>
            </div>
            
            <!-- Single Consolidated Card -->
            <div class="payment-card p-6">
                <!-- Order Summary Section -->
                <!-- Grand Total -->
                <div style="background: linear-gradient(135deg, #0f3340, #0a2530); border: 1px solid rgba(83, 197, 224, 0.3); border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.25); text-align: center;">
                    <span style="font-size: 0.85rem; font-weight: 700; color: #9fc4d4; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 0.5rem;">Order Total Amount</span>
                    <span style="font-size: 2.25rem; font-weight: 900; color: #53c5e0; letter-spacing: -0.01em;">₱ <?php echo number_format($total_amount, 2); ?></span>
                </div>

                <div class="space-y-4" style="margin-bottom: 2rem;">
                    <?php if (!$is_job_order): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="font-size: 0.9rem; font-weight: 700; color: #111827;">Items (<?php echo count($items); ?>)</h3>
                        </div>
                        <?php 
                        $item_index = 0;
                        foreach ($items as $item): 
                            $item_index++;
                            $is_hidden = ($item_index > 3);
                        ?>
                            <div class="<?php echo $is_hidden ? 'items-hidden' : ''; ?>" style="margin-bottom: 1rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 1rem; <?php echo ($item_index === count($items)) ? 'border-bottom: none;' : ''; ?>">
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
                            <div style="background: #0a2530; padding: 0; overflow: hidden; border: 1px solid rgba(83, 197, 224, 0.24); border-radius: 16px; margin-bottom: 1.25rem; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                                <div style="padding: 1.25rem; display: flex; gap: 1.25rem; align-items: flex-start; border-bottom: 1px solid rgba(83, 197, 224, 0.15); background: rgba(255,255,255,0.02);">
                                    <div style="width: 130px; height: 130px; border-radius: 12px; overflow: hidden; background: rgba(0,0,0,0.35); border: 1px solid rgba(83, 197, 224, 0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);">
                                        <?php if (!empty($order['artwork_path'])): ?>
                                            <img src="/printflow/<?php echo htmlspecialchars($order['artwork_path']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease-in-out;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                                        <?php else: ?>
                                            <span style="font-size: 2.2rem; color: rgba(255,255,255,0.15);">🛠️</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column;">
                                        <h3 style="font-size: 0.95rem; line-height: 1.3rem; font-weight: 600; color: #ffffff !important; margin: 0 0 0.3rem 0;"><?php echo htmlspecialchars($order['job_title']); ?></h3>
                                        <div style="display: inline-flex; font-size: 0.72rem; font-weight: 700; color: #53c5e0; text-transform: uppercase; letter-spacing: 0.08em; padding: 3px 10px; border-radius: 20px; background: rgba(83, 197, 224, 0.12); border: 1px solid rgba(83, 197, 224, 0.18); margin-bottom: 1.25rem; align-self: flex-start;">
                                            <?php echo htmlspecialchars($order['service_type']); ?>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: auto;">
                                            <div style="flex: 1; min-width: 80px;">
                                                <div style="font-size: 0.68rem; color: #9fc4d4; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Quantity</div>
                                                <div style="font-size: 1rem; color: #eaf6fb; font-weight: 700;"><?php echo $order['quantity']; ?></div>
                                            </div>
                                            <div style="flex: 1; min-width: 100px;">
                                                <div style="font-size: 0.68rem; color: #53c5e0; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Estimated Total</div>
                                                <div style="font-size: 1rem; color: #53c5e0; font-weight: 800;"><?php echo format_currency($total_amount); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div style="padding: 1.25rem; background: transparent;">
                                    <h4 style="font-size: 0.85rem; font-weight: 800; color: #eaf6fb; margin-bottom: 1rem; border-bottom: 1px solid rgba(83, 197, 224, 0.12); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">Order Specifications</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.85rem;">
                                        <div style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(83, 197, 224, 0.18); padding: 0.75rem 0.85rem; border-radius: 10px;">
                                            <div style="font-size: 0.65rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Size</div>
                                            <div style="font-size: 0.95rem; font-weight: 700; color: #eaf6fb;"><?php echo htmlspecialchars($order['width_ft'] . ' x ' . $order['height_ft']); ?> ft</div>
                                        </div>
                                        <?php if (!empty($order['notes'])): ?>
                                            <div style="grid-column: 1 / -1; margin-top: 0.75rem; padding: 1.15rem; background: rgba(83, 197, 224, 0.08); border: 1px solid rgba(83, 197, 224, 0.22); border-left: 4px solid #53c5e0; border-radius: 12px;">
                                                <div style="font-size: 0.75rem; font-weight: 800; color: #53c5e0; text-transform: uppercase; margin-bottom: 6px;">📝 Special Instructions & Notes</div>
                                                <div style="font-size: 0.95rem; color: #eaf6fb; line-height: 1.6; font-weight: 600;"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                    <?php endif; ?>
                </div>

                <!-- Divider -->
                <div style="border-top: 1px solid #e5e7eb; margin: 2rem 0;"></div>

                <!-- Payment Section -->
                <?php if ($payment_status === 'Paid'): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                        <h3 style="font-weight: 800; color: #059669; margin-bottom: 0.5rem;">Payment Completed</h3>
                        <p style="color: #64748b; font-size: 0.875rem;">This order has already been fully paid.</p>
                        <a href="<?php echo !$is_job_order ? 'order_details.php?id=' . $order_id : 'services.php'; ?>" class="btn-primary w-full mt-6 text-center block" style="text-decoration: none;">View Order Details</a>
                    </div>
                <?php elseif (!$show_payment_form): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">⏳</div>
                        <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">Payment Verifying</h3>
                        <p style="color: #64748b; font-size: 0.875rem;">Your payment proof is currently under review by our staff.</p>
                        <a href="<?php echo !$is_job_order ? 'order_details.php?id=' . $order_id : 'services.php'; ?>" class="btn-primary w-full mt-6 text-center block" style="text-decoration: none;">Track Order Status</a>
                    </div>
                <?php else: ?>
                    <form id="paymentForm" enctype="multipart/form-data">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        <input type="hidden" name="is_job" value="<?php echo $is_job_order ? '1' : '0'; ?>">
                        <?php echo csrf_field(); ?>

                        <h2 class="payment-section-title" style="margin-bottom: 1rem; font-size: 1rem;">1. Choose Method</h2>
                        
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
                            <div style="background: #fff1f2; border: 1px solid #ffe4e6; border-radius: 12px; padding: 1rem; color: #be123c; font-size: 0.875rem; font-weight: 600; margin-bottom: 1.5rem;">
                                Online payment is currently unavailable. Please contact the shop.
                            </div>
                        <?php else: ?>
                            <div style="display: flex; gap: 8px; margin-bottom: 1.5rem;">
                                <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                    <button type="button" onclick="selectPM(<?php echo $index; ?>)" id="btn-pm-<?php echo $index; ?>" class="pm-tab-btn <?php echo $first ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($pm['provider']); ?>
                                    </button>
                                <?php $first = false; endforeach; ?>
                            </div>

                            <div id="pm-details-container" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.75rem; margin-bottom: 2.25rem; text-align: center;">
                                <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                    <div id="pm-info-<?php echo $index; ?>" style="display: <?php echo $first ? 'block' : 'none'; ?>;">
                                        <?php if (!empty($pm['file'])): ?>
                                            <img src="/printflow/public/assets/uploads/qr/<?php echo htmlspecialchars($pm['file']); ?>" style="width: 170px; height: 170px; object-fit: contain; margin: 0 auto 1.25rem; display: block; border-radius: 12px; border: 2px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                                        <?php endif; ?>
                                        <div style="font-weight: 800; color: #1f2937; font-size: 1.1rem; letter-spacing: 0.01em;"><?php echo htmlspecialchars($pm['provider']); ?></div>
                                        <div style="color: #6b7280; font-size: 0.9rem; font-weight: 600; margin-top: 6px;"><?php echo htmlspecialchars($pm['label']); ?></div>
                                    </div>
                                <?php $first = false; endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Simplified Flow: Always Full Payment -->
                        <input type="hidden" name="amount" value="<?php echo number_format($order['total_amount'], 2, '.', ''); ?>">
                        <input type="hidden" name="payment_choice" value="full">

                        <h2 class="payment-section-title" style="margin-bottom: 1rem; font-size: 1rem;">2. Upload Reference Receipt</h2>
                        <div class="input-group">
                            <label class="input-label">Upload Reference Receipt</label>
                            <input type="file" name="payment_proof" id="proofInput" style="display: none;" accept="image/*" required>
                            <div id="dropzone" class="dropzone" onclick="document.getElementById('proofInput').click()">
                                <div id="placeholder" style="display: block;">
                                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📸</div>
                                    <div style="font-weight: 700; color: #1e293b; font-size: 0.875rem;">Click to upload receipt</div>
                                    <div style="font-size: 0.75rem; color: #64748b;">JPG, PNG or PDF</div>
                                </div>
                                <div id="preview" style="display: none; align-items: center; justify-content: center; flex-direction: column; width: 100%; overflow: hidden;">
                                    <img id="previewImg" src="" style="max-height: 120px; border-radius: 8px; margin-bottom: 10px; max-width: 100%; object-fit: contain;">
                                    <p id="fileName" style="font-size: 0.8125rem; font-weight: 700; color: #1e293b; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px;"></p>
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="submitBtn" class="shopee-btn-primary" style="width: 100%; padding: 0.75rem; white-space: nowrap; text-decoration: none; text-align: center; display: block; font-weight: 700; font-size: 0.9rem; border-radius: 8px; border: none; background: #0a2530 !important; color: #fff !important; text-transform: uppercase; letter-spacing: 0.02em; cursor: pointer; box-shadow: 0 4px 12px rgba(10, 37, 48, 0.3); transition: all 0.2s;" <?php echo empty($enabled_methods) ? 'disabled style="opacity:0.5;"' : ''; ?>>
                            Submit Payment Proof
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleItems(btn) {
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
    }

    function selectPM(idx) {
        document.querySelectorAll('.pm-tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('btn-pm-' + idx).classList.add('active');
        
        document.querySelectorAll('[id^="pm-info-"]').forEach(i => i.style.display = 'none');
        document.getElementById('pm-info-' + idx).style.display = 'block';
    }

    const proofInput = document.getElementById('proofInput');
    const placeholder = document.getElementById('placeholder');
    const preview = document.getElementById('preview');
    const previewImg = document.getElementById('previewImg');
    const fileName = document.getElementById('fileName');

    if (proofInput) {
        proofInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                fileName.textContent = file.name;
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    placeholder.style.display = 'none';
                    preview.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span style="display:flex; align-items:center; justify-content:center; gap:8px;">Uploading...</span>';

            const formData = new FormData(this);
            
            fetch('api_submit_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal(
                        'Payment Success',
                        'Your payment proof has been submitted and is now under review. We\'ll notify you once verified!',
                        'order_details.php?id=<?php echo $order_id; ?>',
                        'services.php',
                        'View Order',
                        'Back to Services',
                        'services.php',
                        4000
                    );
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Submit Payment Proof';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Submit Payment Proof';
            });
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
