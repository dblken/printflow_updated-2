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
    
    $total_amount = (float)$order['total_amount'];
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
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2.5rem;
        border-bottom: 1px solid rgba(83, 197, 224, 0.15);
        padding-bottom: 1.5rem;
    }
    .payment-card {
        background: rgba(10, 37, 48, 0.48);
        backdrop-filter: blur(12px);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        overflow: hidden;
        border: 1px solid rgba(83, 197, 224, 0.2);
        margin-bottom: 2rem;
        transition: transform 0.3s ease;
    }
    .payment-section-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #eaf6fb;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 12px;
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
        border-radius: 14px;
        border: 1px solid rgba(83, 197, 224, 0.24);
        background: rgba(255, 255, 255, 0.05);
        color: #bfdce8;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.25s;
        text-align: center;
        font-size: 0.9rem;
    }
    .pm-tab-btn.active {
        border-color: #53c5e0;
        background: #53c5e0;
        color: #030d11;
        box-shadow: 0 4px 15px rgba(83, 197, 224, 0.3);
    }
    .input-group {
        margin-bottom: 1.75rem;
    }
    .input-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        color: #eaf6fb;
        margin-bottom: 0.75rem;
        letter-spacing: 0.01em;
    }
    .custom-input {
        width: 100%;
        padding: 14px 18px;
        background: rgba(13, 43, 56, 0.94);
        border: 1px solid rgba(83, 197, 224, 0.3);
        border-radius: 14px;
        font-weight: 600;
        color: #eaf6fb;
        transition: all 0.25s;
        font-size: 1rem;
    }
    .custom-input:focus {
        border-color: #53c5e0;
        outline: none;
        background: rgba(13, 43, 56, 1);
        box-shadow: 0 0 0 4px rgba(83, 197, 224, 0.1);
    }
    .dropzone {
        border: 2px dashed rgba(83, 197, 224, 0.3);
        border-radius: 20px;
        padding: 3rem 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s;
        background: rgba(255, 255, 255, 0.03);
    }
    .dropzone:hover {
        border-color: #53c5e0;
        background: rgba(83, 197, 224, 0.06);
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 payment-container">
        
        <div class="payment-header">
            <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn-secondary" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            <h1 class="ct-page-title" style="margin:0; flex:1; text-align:center;">Complete Payment</h1>
            <div style="min-width: 100px; text-align: right;">
                <?php echo status_badge($order['status'], 'order'); ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem;">
            
            <div class="space-y-6">
                <!-- Order Items Summary -->
                <div class="payment-card p-6">
                    <h2 class="payment-section-title">
                        Order Summary
                    </h2>
                    <div class="space-y-4">
                        <?php if (!$is_job_order): ?>
                            <?php foreach ($items as $item): ?>
                                <?php render_order_item_clean($item, false, true); ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Job Order item style -->
                            <!-- Job Order item style (Matches the new dark renderer) -->
                            <div style="background: rgba(255, 255, 255, 0.02); overflow: hidden; border: 1px solid rgba(83, 197, 224, 0.24); border-radius: 16px; margin-bottom: 1.25rem;">
                                <div style="padding: 1.25rem; display: flex; gap: 1.25rem; align-items: flex-start; border-bottom: 1px solid rgba(83, 197, 224, 0.15);">
                                    <div style="width: 130px; height: 130px; border-radius: 12px; overflow: hidden; background: rgba(0,0,0,0.2); border: 1px solid rgba(83, 197, 224, 0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php if (!empty($order['artwork_path'])): ?>
                                            <img src="/printflow/<?php echo htmlspecialchars($order['artwork_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <span style="font-size: 2.2rem;">🛠️</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <h3 style="font-size: 1.35rem; font-weight: 800; color: #eaf6fb; margin-bottom: 0.3rem;"><?php echo htmlspecialchars($order['job_title']); ?></h3>
                                        <div style="display: inline-flex; font-size: 0.72rem; font-weight: 700; color: #53c5e0; text-transform: uppercase; letter-spacing: 0.08em; padding: 3px 10px; border-radius: 20px; background: rgba(83, 197, 224, 0.12); border: 1px solid rgba(83, 197, 224, 0.18); margin-bottom: 1.25rem;">
                                            <?php echo htmlspecialchars($order['service_type']); ?>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem;">
                                            <div>
                                                <div style="font-size: 0.68rem; color: #9fc4d4; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Quantity</div>
                                                <div style="font-size: 1.1rem; color: #eaf6fb; font-weight: 700;"><?php echo $order['quantity']; ?></div>
                                            </div>
                                            <div>
                                                <div style="font-size: 0.68rem; color: #53c5e0; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Estimated Total</div>
                                                <div style="font-size: 1.1rem; color: #53c5e0; font-weight: 800;"><?php echo format_currency($total_amount); ?></div>
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
                </div>
            </div>

            <div>
                <!-- Payment Submission Form -->
                <div class="payment-card p-6" style="position: sticky; top: 1.5rem;">
                    <div class="amount-badge">
                        <div class="amount-label">Amount Due</div>
                        <div class="amount-value"><?php echo format_currency($total_amount); ?></div>
                    </div>

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

                                <div id="pm-details-container" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(83, 197, 224, 0.2); border-radius: 18px; padding: 1.75rem; margin-bottom: 2.25rem; text-align: center;">
                                    <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                        <div id="pm-info-<?php echo $index; ?>" style="display: <?php echo $first ? 'block' : 'none'; ?>;">
                                            <?php if (!empty($pm['file'])): ?>
                                                <img src="/printflow/public/assets/uploads/qr/<?php echo htmlspecialchars($pm['file']); ?>" style="width: 170px; height: 170px; object-fit: contain; margin: 0 auto 1.25rem; display: block; border-radius: 16px; border: 4px solid rgba(255,255,255,0.05); box-shadow: 0 10px 20px rgba(0,0,0,0.2);">
                                            <?php endif; ?>
                                            <div style="font-weight: 900; color: #eaf6fb; font-size: 1.2rem; letter-spacing: 0.02em;"><?php echo htmlspecialchars($pm['provider']); ?></div>
                                            <div style="color: #9fc4d4; font-size: 0.9rem; font-weight: 600; margin-top: 6px;"><?php echo htmlspecialchars($pm['label']); ?></div>
                                        </div>
                                    <?php $first = false; endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <h2 class="payment-section-title" style="margin-bottom: 1rem; font-size: 1rem;">2. Payment Info</h2>
                            
                            <div class="input-group">
                                <label class="input-label">Amount to Pay (PHP)</label>
                                <select name="amount" id="paymentAmountInput" class="custom-input" required onchange="document.getElementById('pchoice').value = this.options[this.selectedIndex].dataset.choice;">
                                    <option value="<?php echo number_format($order['total_amount'], 2, '.', ''); ?>" data-choice="full">Full Payment (<?php echo format_currency($order['total_amount']); ?>)</option>
                                    <option value="<?php echo number_format($order['total_amount'] * 0.5, 2, '.', ''); ?>" data-choice="half">50% Downpayment (<?php echo format_currency($order['total_amount'] * 0.5); ?>)</option>
                                </select>
                                <input type="hidden" name="payment_choice" id="pchoice" value="full">
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 6px; font-weight: 600;">Select whether you want to pay in full or the minimum 50% downpayment required to start production.</p>
                            </div>

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

                            <button type="submit" id="submitBtn" class="btn-primary w-full py-4 text-center block font-black uppercase tracking-widest mt-4" <?php echo empty($enabled_methods) ? 'disabled style="opacity:0.5;"' : ''; ?>>
                                Submit Payment Proof
                            </button>

                                <p style="text-align: center; font-size: 0.75rem; color: #53c5e0; font-weight: 700; margin-top: 1.25rem; opacity: 0.8;">
                                    <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                                    Secure Payment Verification
                                </p>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
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
