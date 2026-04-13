<?php
/**
 * Customer Order Details Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_ui_helper.php';

require_role('Customer');

$order_id = (int)($_GET['id'] ?? 0);
$customer_id = get_user_id();
// Mark notification as read if parameter present
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
}

if (!$order_id) {
    redirect('orders.php');
}

// Get order details (ensure it belongs to the customer)
$order_result = db_query("
    SELECT * FROM orders 
    WHERE order_id = ? AND customer_id = ?
", 'ii', [$order_id, $customer_id]);

if (empty($order_result)) {
    // Order not found or doesn't belong to customer
    redirect('orders.php');
}
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

// Determine if price/payment should be shown.
// Hide while order is still in pending/design-review stages.
$price_pending_statuses = ['Pending', 'Pending Review', 'Pending Approval', 'For Revision', 'Approved'];
$show_price = !in_array($order['status'], $price_pending_statuses, true);

// Derive a more descriptive title based on items
$display_title = "Order #{$order_id}";
if (!empty($items)) {
    // Collect unique item names
    $names = [];
    foreach ($items as $item) {
        $custom = json_decode($item['customization_data'] ?? '{}', true);
        
        // Prioritize service_type from customization, then product_name, then fallback
        $itemName = $custom['service_type'] ?? ($item['product_name'] ?? '');
        
        // Clean up generic names
        if (empty($itemName) || $itemName === 'Customer Order' || $itemName === 'Custom Order' || $itemName === 'Custom Item' || $itemName === 'Order Item') {
            $itemName = get_service_name_from_customization($custom, 'Order #' . $order_id);
        }
        $itemName = normalize_service_name($itemName, 'Order #' . $order_id);

        if (!in_array($itemName, $names)) {
            $names[] = $itemName;
        }
    }
    
    if (count($names) === 1) {
        $display_title = $names[0];
    } else {
        $display_title = implode(", ", $names);
    }
    
    // Truncate if too long
    if (strlen($display_title) > 60) {
        $display_title = substr($display_title, 0, 57) . '...';
    }
}

$page_title = htmlspecialchars($display_title) . " - PrintFlow";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/printflow/public/assets/css/chat.css">

<div class="min-h-screen py-8" style="background: #ffffff;">
    <div class="container mx-auto px-4" style="max-width: 1080px;">
        <!-- Header with back button -->
        <div style="display:flex; align-items:center; margin-bottom: 2rem; gap: 1rem;">
            <a href="orders.php" class="btn-secondary" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            <h1 class="ct-page-title" style="margin:0; flex:1; text-align:center; font-size: 1.5rem;"><?php echo htmlspecialchars($display_title); ?></h1>

            <div style="text-align:right;">
                <?php echo status_badge($order['status'], 'order'); ?>
            </div>
        </div>

        <style>
            .order-container { max-width: 960px; margin: 0 auto; }
            .compact-card { padding: 1.25rem !important; }
            .section-header { font-size:0.95rem; font-weight:700; margin-bottom:1rem; color:#111827; display:flex; align-items:center; gap:8px; }
            .order-summary-row { display:flex; justify-content:space-between; align-items:center; gap:1rem; font-size:0.9rem; color:#4b5563; font-weight:600; }
            .order-summary-row .label { white-space:nowrap; }
            .order-summary-row .value { text-align:right; }
        </style>

        <div style="display:flex; flex-direction:column; gap:1.25rem;">
            
            <!-- 1. Order Status & Date Alert -->
            <div style="padding:1rem; background:#000; color:#fff; border-radius:12px; font-weight:700; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                <span>Placed on: <?php echo format_datetime($order['order_date']); ?></span>
                <a href="<?php echo BASE_URL; ?>/customer/chat.php?order_id=<?php echo $order_id; ?>" style="background:#fff; color:#000; border:none; padding:5px 12px; border-radius:6px; font-weight:800; font-size:0.75rem; text-decoration:none; display:inline-block;">
                    💬 Chat Support
                </a>
            </div>



            <!-- Revision Required Alert -->
            <?php if ($order['status'] === 'For Revision'): ?>
                <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start;">
                    <div style="background: #ef4444; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem;">⚠️</div>
                    <div>
                        <h3 style="color: #991b1b; font-weight: 700; font-size: 0.9rem; margin-bottom: 0.25rem;">Revision Required</h3>
                        <p style="color: #b91c1c; font-size: 0.8rem; line-height: 1.5; margin-bottom:0.75rem;">
                            The shop has requested a revision for this order. Please review the reason below and update your order details.
                        </p>
                        <div style="background:white; border:1px solid #fca5a5; padding:10px; border-radius:8px; font-size:0.85rem; color:#991b1b; font-weight:600; line-height:1.55; white-space:normal; overflow-wrap:anywhere; word-break:break-word; max-width:100%;">
                            <strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($order['revision_reason'] ?? 'Not specified')); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cancellation Alert for Cancelled Orders -->
            <?php if ($order['status'] === 'Cancelled'): ?>
                <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start;">
                    <div style="background: #ef4444; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem;">✕</div>
                    <div style="font-size: 0.85rem;">
                        <h3 style="color: #991b1b; font-weight: 700; margin-bottom: 0.25rem;">Order Cancelled</h3>
                        <p style="color: #b91c1c; line-height: 1.5; margin:0;">
                            <strong>Cancelled By:</strong> <?php echo htmlspecialchars($order['cancelled_by'] ?? 'N/A'); ?><br>
                            <strong>Reason:</strong> <?php echo htmlspecialchars($order['cancel_reason'] ?? 'Not specified'); ?><br>
                            <strong>Date:</strong> <?php echo !empty($order['cancelled_at']) ? format_datetime($order['cancelled_at']) : 'N/A'; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Downpayment Required Alert (Only show if status is 'To Pay') -->
            <?php if ($order['status'] === 'To Pay' && $order['payment_status'] === 'Unpaid'): ?>
                <div style="background-color: #fff7ed; border: 1px solid #ffedd5; border-radius: 12px; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start;">
                    <div style="background: #f97316; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem;">💳</div>
                    <div style="flex: 1;">
                        <h3 style="color: #9a3412; font-weight: 700; font-size: 0.9rem; margin-bottom: 0.25rem;">Downpayment Required</h3>
                        <p style="color: #c2410c; font-size: 0.8rem; line-height: 1.5; margin-bottom: 0.75rem;">
                            Your order requires a 50% downpayment (<?php echo format_currency($order['total_amount'] * 0.5); ?>) to begin production.
                        </p>
                        <button onclick="openPaymentModal()" class="btn-primary" style="background:#f97316; padding:6px 14px; font-size:0.75rem;">
                            Submit Payment Proof
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payment Submitted Status Alert -->
            <?php if ($order['status'] === 'Downpayment Submitted'): ?>
                <div style="background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start;">
                    <div style="background: #22c55e; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem;">⏳</div>
                    <div>
                        <h3 style="color: #166534; font-weight: 700; font-size: 0.9rem; margin-bottom: 0.25rem;">Payment Under Review</h3>
                        <p style="color: #15803d; font-size: 0.8rem; line-height: 1.5; margin:0;">
                            Your payment proof has been submitted. We'll notify you once it's verified and production begins.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

        <!-- Payment Modal -->
        <style>
            /* Custom scrollbar for modal */
            #paymentModal .card::-webkit-scrollbar {
                width: 8px;
            }
            #paymentModal .card::-webkit-scrollbar-track {
                background: #f1f5f9; 
                border-radius: 10px;
            }
            #paymentModal .card::-webkit-scrollbar-thumb {
                background: #cbd5e1; 
                border-radius: 10px;
            }
            #paymentModal .card::-webkit-scrollbar-thumb:hover {
                background: #94a3b8; 
            }
        </style>
        <div id="paymentModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center; padding:20px;">
            <div class="card" style="max-width:500px; width:100%; position:relative; border-radius: 20px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
                <h2 style="font-size:1.5rem; font-weight:800; margin-bottom:0.5rem; color:#111827; display: flex; align-items: center; gap: 10px;">
                    Submit Payment
                </h2>
                <?php 
                $qr_dir = __DIR__ . '/../public/assets/uploads/qr/';
                $payment_cfg_path = $qr_dir . 'payment_methods.json';
                $payment_methods = file_exists($payment_cfg_path) ? json_decode(file_get_contents($payment_cfg_path), true) : [];
                if (!is_array($payment_methods)) $payment_methods = [];
                $enabled_methods = array_filter($payment_methods, function($m) { return !empty($m['enabled']); });
                ?>

                <form id="paymentForm" enctype="multipart/form-data">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <?php echo csrf_field(); ?>

                    <p style="color:#6b7280; font-size:0.9rem; margin-bottom:1rem;">Follow the steps below to finalize your order.</p>
                    
                    <!-- Step 1: Payment Policy -->
                    <div style="margin-bottom: 2rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 1.5rem;">
                        <label style="display:block; font-size:0.875rem; font-weight:800; color: #111827; margin-bottom:1rem; text-transform:uppercase; letter-spacing:0.04em;">Step 1: Choose Payment Policy</label>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem;">
                            <label class="payment-policy-option" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                                <input type="radio" name="payment_choice" value="full" checked onclick="updatePaymentUI('full')" style="width: 18px; height: 18px;">
                                <div>
                                    <div style="font-weight: 800; font-size: 0.95rem; color: #111827;">Full Payment (100%)</div>
                                    <div style="font-size: 0.8rem; color: #6b7280;">Pay the full amount of <?php echo format_currency($order['total_amount']); ?></div>
                                </div>
                            </label>

                            <label class="payment-policy-option" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                                <input type="radio" name="payment_choice" value="half" onclick="updatePaymentUI('half')" style="width: 18px; height: 18px;">
                                <div>
                                    <div style="font-weight: 800; font-size: 0.95rem; color: #111827;">Downpayment (50%)</div>
                                    <div style="font-size: 0.8rem; color: #6b7280;">Pay at least <?php echo format_currency($order['total_amount'] * 0.5); ?> to start production.</div>
                                </div>
                            </label>

                        </div>
                    </div>

                    <div id="paymentDetailsSection">
                        <label style="display:block; font-size:0.875rem; font-weight:800; color: #111827; margin-bottom:1rem; text-transform:uppercase; letter-spacing:0.04em;">Step 2: Transfer & Upload Proof</label>
                        <?php if (empty($enabled_methods)): ?>
                            <div style="background: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; color: #b91c1c; font-size: 0.9rem;">
                                No online payment methods are currently configured by the shop. Please contact support.
                            </div>
                        <?php else: ?>
                            <!-- Payment Methods Tabs/Selector -->
                            <div style="display: flex; gap: 8px; margin-bottom: 1rem; overflow-x: auto; padding-bottom: 4px;">
                                <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                    <button type="button" onclick="selectPaymentMethod(<?php echo $index; ?>)" id="btn-pm-<?php echo $index; ?>" class="pm-tab-btn" style="flex: 1; padding: 10px; border-radius: 10px; border: 2px solid <?php echo $first ? '#4F46E5' : '#e5e7eb'; ?>; background: <?php echo $first ? '#e0e7ff' : '#f9fafb'; ?>; color: <?php echo $first ? '#4F46E5' : '#4b5563'; ?>; font-weight: 700; font-family: inherit; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                        <?php echo htmlspecialchars($pm['provider']); ?>
                                    </button>
                                <?php $first = false; endforeach; ?>
                            </div>

                            <!-- Payment Provider Details -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; text-align: center; min-height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                    <div id="pm-details-<?php echo $index; ?>" style="display: <?php echo $first ? 'block' : 'none'; ?>; width: 100%;">
                                        <?php if (!empty($pm['file'])): ?>
                                            <img src="/printflow/public/assets/uploads/qr/<?php echo htmlspecialchars($pm['file']); ?>?t=<?php echo time(); ?>" style="width: 120px; height: 120px; object-fit: contain; border-radius: 12px; border: 2px solid #e2e8f0; margin: 0 auto 10px auto; display: block; background: white;" alt="QR Code">
                                        <?php endif; ?>
                                        <div style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-bottom: 4px;"><?php echo htmlspecialchars($pm['provider']); ?></div>
                                        <?php if (!empty($pm['label'])): ?>
                                            <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;"><?php echo htmlspecialchars($pm['label']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php $first = false; endforeach; ?>
                            </div>
                        <?php endif; ?>


                    
                        <div id="proofUploadSection">
                            <div style="margin-bottom:1.25rem;">
                                <label style="display:block; font-size:0.875rem; font-weight:700; color: #374151; margin-bottom:0.5rem;">Amount to Pay (PHP)</label>
                                <input type="number" name="amount" id="paymentAmountInput" step="0.01" class="input-field" 
                                       value="<?php echo number_format($order['total_amount'], 2, '.', ''); ?>" 
                                       min="<?php echo number_format($order['total_amount'], 2, '.', ''); ?>" 
                                       style="width:100%; font-size: 1.1rem; font-weight: 700; color: #4F46E5;" required>
                                <p id="minPaymentText" style="font-size: 0.75rem; color: #6b7280; margin-top: 8px;">Total: <?php echo format_currency($order['total_amount']); ?></p>
                            </div>
                            
                            <div style="margin-bottom:1.5rem;">
                                <label style="display:block; font-size:0.875rem; font-weight:700; color: #374151; margin-bottom:0.5rem;">Upload Proof of Payment</label>
                                <div id="dropzone" style="border: 2px dashed #e2e8f0; border-radius: 12px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#4F46E5'; this.style.background='#f5f3ff'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='transparent'">
                                    <input type="file" name="payment_proof" id="proofInput" style="display: none;" accept="image/*">
                                    <div id="uploadPlaceholder">
                                        <span style="font-size: 2rem;">📸</span>
                                        <p style="font-size: 0.875rem; color: #64748b; margin-top: 8px;">Click to upload or drag image</p>
                                    </div>
                                    <div id="filePreview" style="display: none; align-items: center; justify-content: center; flex-direction: column;">
                                        <img id="previewImg" src="" style="max-height: 100px; border-radius: 8px; margin-bottom: 8px;">
                                        <p id="fileName" style="font-size: 0.8rem; color: #1e293b; font-weight: 600;"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" onclick="closePaymentModal()" class="btn-secondary" style="border-radius: 10px; font-weight: 500; font-family: inherit; font-size: 0.9375rem;">Cancel</button>
                        <button type="submit" id="submitPaymentBtn" class="btn-primary" <?php echo empty($enabled_methods) ? 'disabled' : ''; ?> style="background:#4F46E5; color:white; border-radius: 10px; padding: 10px 24px; font-weight: 800; font-family: inherit; font-size: 0.9375rem; text-transform:uppercase; letter-spacing:0.02em; <?php echo empty($enabled_methods) ? 'opacity:0.6; cursor:not-allowed;' : ''; ?>">Submit & Confirm</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cancellation Modal -->
        <div id="cancelModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center; padding:20px;">
            <div class="card" style="max-width:500px; width:100%; position:relative;">
                <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:1rem; color:#111827;">Cancel Order #<?php echo $order_id; ?></h2>
                <p style="color:#6b7280; font-size:0.875rem; margin-bottom:1.5rem;">Please tell us why you want to cancel this order. This cannot be undone.</p>
                
                <form action="cancel_order.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    
                    <div style="margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.75rem;">Reason for Cancellation</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                                <input type="radio" name="reason" value="Wrong item ordered" required> Wrong item ordered
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                                <input type="radio" name="reason" value="Found better price elsewhere"> Found better price elsewhere
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                                <input type="radio" name="reason" value="Changed my mind"> Changed my mind
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                                <input type="radio" name="reason" value="Other"> Other (Please specify below)
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.5rem;">Additional Details (Optional)</label>
                        <textarea name="details" class="input-field" style="width:100%; min-height:80px; font-size:0.9rem;" placeholder="e.g. personal issue..."></textarea>
                    </div>
                    
                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" onclick="closeCancelModal()" class="btn-secondary">Keep Order</button>
                        <button type="submit" name="confirm_cancel" class="btn-primary" style="background:#dc2626; color:white;">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openCancelModal() {
                document.getElementById('cancelModal').style.display = 'flex';
            }
            function closeCancelModal() {
                document.getElementById('cancelModal').style.display = 'none';
            }

            function openPaymentModal() {
                document.getElementById('paymentModal').style.display = 'flex';
            }
            function closePaymentModal() {
                document.getElementById('paymentModal').style.display = 'none';
            }

            function updatePaymentUI(choice) {
                // Update active state of containers
                document.querySelectorAll('.payment-policy-option').forEach(el => {
                    el.style.borderColor = '#e5e7eb';
                    el.style.background = '#fff';
                    el.querySelector('div div').style.color = '#111827';
                });
                
                const selected = document.querySelector(`input[name="payment_choice"][value="${choice}"]`);
                if (selected) {
                    const parent = selected.closest('.payment-policy-option');
                    parent.style.borderColor = '#4F46E5';
                    parent.style.background = '#f5f3ff';
                    parent.querySelector('div div').style.color = '#4F46E5';
                }

                const detailsSection = document.getElementById('paymentDetailsSection');
                const proofSection = document.getElementById('proofUploadSection');
                const amountInput = document.getElementById('paymentAmountInput');
                const minText = document.getElementById('minPaymentText');
                const proofInput = document.getElementById('proofInput');

                const total = <?php echo (float)$order['total_amount']; ?>;
                const half = total * 0.5;

                detailsSection.style.display = 'block';
                proofSection.style.display = 'block';
                proofInput.required = true;
                amountInput.required = true;

                if (choice === 'half') {
                    amountInput.value = half.toFixed(2);
                    amountInput.min = half.toFixed(2);
                    minText.textContent = 'Min. 50%: PHP ' + half.toLocaleString(undefined, {minimumFractionDigits: 2});
                } else {
                    amountInput.value = total.toFixed(2);
                    amountInput.min = total.toFixed(2);
                    minText.textContent = 'Total: PHP ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
                }
            }

            function selectPaymentMethod(selectedIndex) {
                // Reset all tabs
                document.querySelectorAll('.pm-tab-btn').forEach(btn => {
                    btn.style.borderColor = '#e5e7eb';
                    btn.style.backgroundColor = '#f9fafb';
                    btn.style.color = '#4b5563';
                });
                
                // Set active tab
                const activeBtn = document.getElementById('btn-pm-' + selectedIndex);
                if (activeBtn) {
                    activeBtn.style.borderColor = '#4F46E5';
                    activeBtn.style.backgroundColor = '#e0e7ff';
                    activeBtn.style.color = '#4F46E5';
                }

                // Hide all details
                document.querySelectorAll('[id^="pm-details-"]').forEach(el => {
                    el.style.display = 'none';
                });
                
                // Show active details
                const activeDetails = document.getElementById('pm-details-' + selectedIndex);
                if (activeDetails) {
                    activeDetails.style.display = 'block';
                }
            }

            // File upload UI handling
            const dropzone = document.getElementById('dropzone');
            const proofInput = document.getElementById('proofInput');

            // Auto-open payment modal if ?pay=1 is in URL
            window.addEventListener('DOMContentLoaded', () => {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('pay') === '1') {
                    openPaymentModal();
                }
            });
            const uploadPlaceholder = document.getElementById('uploadPlaceholder');
            const filePreview = document.getElementById('filePreview');
            const previewImg = document.getElementById('previewImg');
            const fileName = document.getElementById('fileName');

            if (dropzone) {
                dropzone.addEventListener('click', () => proofInput.click());
                proofInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        fileName.textContent = file.name;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            previewImg.src = e.target.result;
                            uploadPlaceholder.style.display = 'none';
                            filePreview.style.display = 'flex';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // AJAX Submission
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = document.getElementById('submitPaymentBtn');
                    btn.disabled = true;
                    btn.textContent = 'Submitting...';

                    const formData = new FormData(this);
                    
                    fetch('api_submit_payment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showSuccessModal(
                                'Payment Submitted',
                                data.message,
                                'order_details.php?id=<?php echo $order_id; ?>',
                                'orders.php',
                                'View Order',
                                'Back to Orders'
                            );
                            closePaymentModal();
                        } else {
                            showToast('Error: ' + data.message);
                            btn.disabled = false;
                            btn.textContent = 'Submit Payment';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An unexpected error occurred. Please try again.');
                        btn.disabled = false;
                        btn.textContent = 'Submit Payment';
                    });
                });
            }

            // Trigger success modal if success message exists
            window.addEventListener('DOMContentLoaded', () => {
                <?php if (isset($_SESSION['success'])): 
                    $msg = $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
                showSuccessModal(
                    'Order Placed!',
                    '<?php echo addslashes($msg); ?>',
                    'orders.php',
                    'services.php',
                    'View My Orders',
                    'Go to Services',
                    'services.php',
                    3500
                );
                <?php endif; ?>
            });
        </script>

            <!-- 2. Order Summary (Items & Total) -->
            <div class="card compact-card">
                <h2 class="section-header">
                    <span>🛒</span> Order Summary
                </h2>
                
                <div style="display:flex; flex-direction:column; gap: 0.5rem;">
                    <?php foreach ($items as $item): ?>
                        <?php render_order_item_clean($item, false, $show_price); ?>
                    <?php endforeach; ?>
                </div>

                <div style="border-top:1px solid #f3f4f6; padding-top:1rem; margin-top:1rem; display:flex; flex-direction:column; gap:0.5rem;">
                    <div class="order-summary-row">
                        <span class="label">Order Status:</span>
                        <span class="value"><?php echo status_badge($order['status'], 'order'); ?></span>
                    </div>
                    <?php if ($show_price): ?>
                        <div class="order-summary-row">
                            <span class="label">Payment Status:</span>
                            <span class="value"><?php echo status_badge($order['payment_status'], 'payment'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div style="border-top:1px solid #e5e7eb; padding-top:1rem; margin-top:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:700; color:#111827; font-size:0.95rem;">Grand Total</span>
                        <?php if ($show_price): ?>
                            <span style="font-size:1.5rem; font-weight:800; color:#4F46E5;"><?php echo format_currency($order['total_amount']); ?></span>
                        <?php else: ?>
                            <span style="font-size:0.85rem; font-weight:700; color:#6b7280; font-style:italic;">Price will be confirmed by the shop</span>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:1rem; display:flex; flex-direction:column; gap:0.75rem;">
                        <?php if ($show_price && $order['payment_status'] === 'Unpaid' && !in_array($order['status'], ['Downpayment Submitted', 'Cancelled'], true)): ?>
                            <button type="button" onclick="openPaymentModal()" class="btn-primary" style="width:100%; padding:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.04em;">
                                Pay now
                            </button>
                        <?php elseif (!$show_price): ?>
                            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-left:4px solid #0ea5e9; border-radius:10px; padding:12px 14px; display:flex; gap:10px; align-items:center;">
                                <span style="font-size:1.1rem;">⏳</span>
                                <div style="font-size:0.75rem; color:#0369a1; font-weight:600; line-height:1.4;">Order is under review. Pricing and payment options will be available soon.</div>
                            </div>
                        <?php endif; ?>

                        <?php if ($order['status'] === 'For Revision'): ?>
                            <a href="edit_order.php?id=<?php echo $order_id; ?>" class="btn-primary" style="width:100%; padding:12px; background:#f59e0b; font-weight:800; text-transform:uppercase; text-decoration:none; text-align:center;">
                                Edit order
                            </a>
                        <?php endif; ?>

                        <?php if (can_customer_cancel_order($order)): ?>
                            <button type="button" onclick="openCancelModal()" style="width:100%; padding:10px; background:transparent; color:#ef4444; font-size:0.8rem; font-weight:700; border:1px solid #fee2e2; border-radius:10px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                                ✕ Cancel order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 3. Contact Information -->
            <div class="card compact-card">
                <h2 class="section-header">
                    <span>👤</span> Contact Information
                </h2>
                <?php 
                $cust_res = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$order['customer_id']]);
                $customer_info = $cust_res[0] ?? [];
                ?>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div>
                        <div style="font-size:0.65rem; color:#6b7280; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Full Name</div>
                        <div style="font-weight:700; color:#111827; font-size:0.9rem;"><?php echo htmlspecialchars(trim(($customer_info['first_name'] ?? '') . ' ' . ($customer_info['middle_name'] ?? '') . ' ' . ($customer_info['last_name'] ?? ''))); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.65rem; color:#6b7280; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Phone Number</div>
                        <div style="font-weight:700; color:#111827; font-size:0.9rem;"><?php echo htmlspecialchars($customer_info['contact_number'] ?? '—'); ?></div>
                    </div>
                    <div style="grid-column: span 2;">
                        <div style="font-size:0.65rem; color:#6b7280; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Email Address</div>
                        <div style="font-weight:700; color:#111827; font-size:0.9rem;"><?php echo htmlspecialchars($customer_info['email'] ?? ''); ?></div>
                    </div>
                </div>
            </div>

            <!-- 4. Order Notes -->
            <?php if (!empty($order['notes'])): ?>
                <div class="card compact-card" style="background:#fffbeb; border:1px solid #fde68a;">
                    <h2 class="section-header" style="color:#92400e; margin-bottom:0.75rem;">
                        <span>📝</span> Order Notes
                    </h2>
                    <div style="font-size:0.85rem; color:#b45309; line-height:1.5; font-weight:600; max-height: 120px; overflow-y: auto; word-break: break-word;">
                        <?php echo nl2br(htmlspecialchars($order['notes'] ?? '')); ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>


<script>
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('chat') === 'open') {
        window.location.href = '<?php echo BASE_URL; ?>/customer/chat.php?order_id=<?php echo $order_id; ?>';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

