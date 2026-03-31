<?php
/**
 * Order Review & Confirm Page
 * PrintFlow — Shown when customer clicks "Buy Now"
 * Displays full order summary with design image preview,
 * customization details, price, and Cancel / Confirm buttons.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_ui_helper.php';

require_role('Customer');

// ── Accept the "buy_now" item key from session ──────────────────
$item_key = $_GET['item'] ?? '';
$cart     = $_SESSION['cart'] ?? [];

if (!$item_key || !isset($cart[$item_key])) {
    redirect('products.php');
}

$item        = $cart[$item_key];
$customer_id = get_user_id();
$customer    = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0] ?? [];
$customer_type = $customer['customer_type'] ?? 'new';
$address_parts = [
    trim((string)($customer['address'] ?? '')),
    trim((string)($customer['street'] ?? '')),
    trim((string)($customer['barangay'] ?? '')),
    trim((string)($customer['city'] ?? '')),
    trim((string)($customer['province'] ?? '')),
];
$address_parts = array_values(array_filter($address_parts, fn($p) => $p !== ''));
$customer_address = !empty($address_parts) ? implode(', ', $address_parts) : '—';

// ── Handle Place Order FIRST (to allow clearing cart without trigger redirect) ──
$order_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $order_error = 'Invalid request. Please try again.';
    } else {
        // Fetch cart again for current POST request
        $item        = $cart[$item_key] ?? null;
        if ($item) {
            $subtotal = $item['price'] * $item['quantity'];

            // Check restriction AGAIN at submission
            $cancel_count = get_customer_cancel_count($customer_id);
            $is_restricted = is_customer_restricted($customer_id);

            if ($is_restricted) {
                $order_error = "🚫 Your account is restricted from placing new orders.";
            } else {
                global $conn;
                $downpayment_amount = 0;
                $payment_type = 'full_payment';
                $payment_status = 'Unpaid';

                $notes = $item['customization']['notes'] ?? $item['customization']['additional_notes'] ?? null;
                $branch_id = $item['branch_id'] ?? null;
                
                $order_type = 'product';
                $reference_id = $item['product_id'] ?? null;
                if (($item['type'] ?? '') === 'Service' || !empty($item['customization'])) {
                    $order_type = 'custom';
                }
                
                $order_sql = "INSERT INTO orders (customer_id, branch_id, reference_id, order_date, total_amount, downpayment_amount, status, payment_status, payment_type, notes, order_type)
                              VALUES (?, ?, ?, NOW(), ?, ?, 'Pending Review', ?, ?, ?, ?)";
                $order_id  = db_execute($order_sql, 'iiiddssss', [$customer_id, $branch_id, $reference_id, $subtotal, $downpayment_amount, $payment_status, $payment_type, $notes, $order_type]);

                if ($order_id) {
                    $custom = $item['customization'] ?? [];
                    if (empty($custom['service_type']) && !empty($item['name']) && ($item['type'] ?? '') === 'Service') {
                        $custom['service_type'] = $item['name'];
                    }
                    $custom_data   = json_encode($custom);
                    $design_binary = null;
                    $design_mime   = $item['design_mime']   ?? null;
                    $design_name   = $item['design_name']   ?? null;
                    
                    $upload_dir = __DIR__ . '/../uploads/orders';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    $design_file_path = null;
                    $reference_file_path = null;

                    if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) {
                        $design_binary = file_get_contents($item['design_tmp_path']);
                        $ext = strtolower(pathinfo($design_name, PATHINFO_EXTENSION));
                        $new_name = uniqid('design_') . '_' . time() . '.' . $ext;
                        if (copy($item['design_tmp_path'], $upload_dir . '/' . $new_name)) {
                            $design_file_path = '/printflow/uploads/orders/' . $new_name;
                        }
                    }

                    if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path'])) {
                        $ref_name = $item['reference_name'] ?? 'reference.jpg';
                        $ext = strtolower(pathinfo($ref_name, PATHINFO_EXTENSION));
                        $new_name = uniqid('ref_') . '_' . time() . '.' . $ext;
                        if (copy($item['reference_tmp_path'], $upload_dir . '/' . $new_name)) {
                            $reference_file_path = '/printflow/uploads/orders/' . $new_name;
                        }
                    }

                    $product_id = !empty($item['product_id']) ? (int)$item['product_id'] : null;
                    $service_type = $custom['service_type'] ?? ($item['category'] ?? ($item['name'] ?? ''));
                    $service_product_map = [
                        'Tarpaulin Printing' => 4,
                        'T-Shirt Printing' => 1,
                        'Glass & Wall Sticker Printing' => 3,
                        'Transparent Sticker Printing' => 3,
                        'Decals / Stickers' => 3,
                        'Sintraboard Standees' => 3,
                        'Sintraboard & Standees' => 3,
                        'Layout Design Service' => 3,
                    ];

                    // Guard FK: if incoming product_id is missing/invalid, fall back to service map.
                    $product_exists = false;
                    if ($product_id !== null && $product_id > 0) {
                        $chk = db_query("SELECT product_id FROM products WHERE product_id = ? LIMIT 1", 'i', [$product_id]);
                        $product_exists = !empty($chk);
                    }
                    if (!$product_exists) {
                        $product_id = $service_product_map[$service_type] ?? 3;
                    }

                    if ($design_binary) {
                        $stmt = $conn->prepare(
                            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, 
                                                    design_image, design_image_mime, design_image_name, design_file, reference_image_file)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        if ($stmt) {
                            $null = NULL;
                            $stmt->bind_param('iiidssssss', $order_id, $product_id, $item['quantity'], $item['price'], $custom_data, $null, $design_mime, $design_name, $design_file_path, $reference_file_path);
                            $stmt->send_long_data(5, $design_binary);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } else {
                        db_execute(
                            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, design_file, reference_image_file) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)",
                            'iiidsss',
                            [$order_id, $product_id, $item['quantity'], $item['price'], $custom_data, $design_file_path, $reference_file_path]
                        );
                    }

                    if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) @unlink($item['design_tmp_path']);
                    if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path'])) @unlink($item['reference_tmp_path']);
                    unset($_SESSION['cart'][$item_key]);

                    $srv_name = get_service_name_from_customization($custom, 'Service Order');
                    $welcomeMsg = "Your order for {$srv_name} has been placed successfully! Our team will review it shortly.";
                    create_notification($customer_id, 'Customer', $welcomeMsg, 'Order', true, false, $order_id);
                    add_order_system_message($order_id, $welcomeMsg);
                    notify_staff_new_order((int)$order_id, (string)($customer['first_name'] ?? 'Customer'));

                    $_SESSION['success'] = "Your order for {$srv_name} has been placed successfully!";
                    $order_placed_id = $order_id;
                } else {
                    $order_error = 'Failed to place order. Please try again.';
                }
            }
        }
    }
}

$cart = $_SESSION['cart'] ?? [];
if (!$item_key || (!isset($cart[$item_key]) && !isset($order_placed_id))) {
    redirect('products.php');
}

$item     = $cart[$item_key] ?? null;
$subtotal = $item ? ($item['price'] * $item['quantity']) : 0;

// ── Build design preview (base64 for inline display) ───────────
$design_preview_src = null;
if (!isset($order_placed_id) && !empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
    $binary = file_get_contents($item['design_tmp_path']);
    if ($binary) {
        $design_preview_src = 'data:' . $item['design_mime'] . ';base64,' . base64_encode($binary);
    }
}

$ref_preview_src = null;
if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path']) && !empty($item['reference_mime'])) {
    $binary = file_get_contents($item['reference_tmp_path']);
    if ($binary) {
        $ref_preview_src = 'data:' . $item['reference_mime'] . ';base64,' . base64_encode($binary);
    }
}

// Fetch branch name
$branch_name = 'Multiple/Selected Branch';
if (!empty($item['branch_id'])) {
    $b = db_query("SELECT branch_name FROM branches WHERE id = ?", 'i', [$item['branch_id']])[0] ?? [];
    if (!empty($b)) $branch_name = $b['branch_name'];
}

$page_title      = 'Review Your Order — PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .order-container { max-width: 650px; margin: 0 auto; }
    .compact-section { margin-bottom: 1.25rem; }
    .compact-card { padding: 1.25rem !important; }
    .review-title { text-align: center; margin-bottom: 2rem; color: #eaf6fb !important; }
    .review-card {
        background: rgba(10, 37, 48, 0.48) !important;
        border: 1px solid rgba(83, 197, 224, 0.24) !important;
        border-radius: 12px !important;
        backdrop-filter: blur(4px);
    }
    .review-heading {
        color: #e2f2f8 !important;
        border-bottom-color: rgba(83, 197, 224, 0.2) !important;
    }
    .review-info-note {
        margin-top: 1rem;
        background: linear-gradient(135deg, rgba(83, 197, 224, 0.18), rgba(50, 161, 196, 0.14)) !important;
        border: 1px solid rgba(83, 197, 224, 0.34) !important;
        border-left: 4px solid #53c5e0 !important;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }
    .review-info-note-title { font-size: 0.82rem; font-weight: 700; color: #dff3fa !important; margin-bottom: 3px; }
    .review-info-note-text { font-size: 0.75rem; color: #c2dfeb !important; line-height: 1.5; }
    .review-contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .review-contact-full { grid-column: span 2; }
    .review-input-label {
        display: block;
        font-size: 0.65rem;
        font-weight: 600;
        color: #9fc4d4 !important;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .review-input-disabled {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(83, 197, 224, 0.24) !important;
        color: #eaf6fb !important;
        font-weight: 600;
        font-size: 0.85rem;
        font-family: inherit !important;
        padding: 8px 12px;
    }
    .review-input-disabled-textarea {
        min-height: 44px;
        resize: none;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.45;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        font-family: inherit !important;
        color: #eaf6fb !important;
    }
    .review-policy-card {
        background: linear-gradient(135deg, rgba(83, 197, 224, 0.14), rgba(50, 161, 196, 0.1)) !important;
        border: 1px solid rgba(83, 197, 224, 0.34) !important;
    }
    .review-policy-title { color: #def1f8 !important; }
    .review-policy-text { color: #c2deea !important; line-height: 1.6; margin: 0; font-size: 0.82rem; }
    .review-buy-btn {
        width: auto;
        font-weight: 700;
        font-size: .9rem;
        border-radius: 10px;
        border: none;
        background: linear-gradient(135deg, #53C5E0, #32a1c4) !important;
        color: #ffffff !important;
        text-transform: uppercase;
        letter-spacing: .02em;
        cursor: pointer;
        box-shadow: 0 10px 22px rgba(50,161,196,0.3);
        transition: all .2s;
    }
    .review-cancel-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: auto;
        border: 1px solid rgba(83,197,224,.28);
        border-radius: 10px;
        font-size: .9rem;
        color: #d9e6ef;
        text-decoration: none;
        font-weight: 700;
        padding: 0 1.15rem;
        transition: all 0.2s;
        background: rgba(255,255,255,.06);
    }
    .review-cancel-btn:hover {
        background: rgba(83,197,224,.12);
        border-color: rgba(83,197,224,.52);
        color: #fff;
    }
    .review-actions-row {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: .75rem;
        margin-top: 1.1rem;
        flex-wrap: wrap;
    }
    .review-buy-btn,
    .review-cancel-btn {
        height: 46px;
        min-width: 150px;
        padding: 0 1.15rem;
        width: auto;
    }
    .review-image-clickable {
        cursor: zoom-in;
    }

    /* Match order_tshirt action buttons */
    .tshirt-btn {
        height: 46px;
        min-width: 150px;
        padding: 0 1.15rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        text-decoration: none;
        font-size: .9rem;
        font-weight: 700;
        transition: all .2s;
    }
    .tshirt-btn-secondary {
        background: rgba(255,255,255,.05) !important;
        border: 1px solid rgba(83, 197, 224, .28) !important;
        color: #d9e6ef !important;
    }
    .tshirt-btn-secondary:hover {
        background: rgba(83,197,224,.14) !important;
        border-color: rgba(83,197,224,.52) !important;
        color: #fff !important;
    }
    .tshirt-btn-primary {
        border: none;
        background: linear-gradient(135deg, #53C5E0, #32a1c4) !important;
        color: #fff !important;
        text-transform: uppercase;
        letter-spacing: .02em;
        cursor: pointer;
        box-shadow: 0 10px 22px rgba(50,161,196,0.3);
    }
    .tshirt-btn:active {
        transform: translateY(1px) scale(0.99);
    }

    .image-preview-modal {
        position: fixed;
        inset: 0;
        background: rgba(2, 12, 18, 0.78);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 1rem;
    }
    .image-preview-modal.active {
        display: flex;
    }
    .image-preview-modal img {
        max-width: min(96vw, 1100px);
        max-height: 92vh;
        border-radius: 12px;
        border: 1px solid rgba(83, 197, 224, 0.35);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.55);
        background: #0a2530;
    }

    /* 
     * Target the image clickable state 
     */
    .review-order-item img {
        cursor: pointer;
        transition: transform 0.2s;
    }
    .review-order-item img:hover {
        transform: scale(1.02);
    }

    /* Success Modal Styles */
    .success-modal-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6);
        backdrop-filter: none; z-index: 9999;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none; transition: opacity 0.4s ease;
    }
    .success-modal-overlay.active { opacity: 1; pointer-events: auto; }
    
    .success-modal-card {
        background: linear-gradient(160deg, #0f3340, #0a2530); width: 90%; max-width: 400px; padding: 40px 30px;
        border-radius: 24px; text-align: center;
        transform: scale(0.9); transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
        border: 1px solid rgba(83, 197, 224, 0.28);
    }
    .success-modal-overlay.active .success-modal-card { transform: scale(1); }

    .success-icon-wrap {
        width: 80px; height: 80px; background: rgba(83, 197, 224, 0.2); border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 24px; color: #53c5e0;
    }
    .success-checkmark { font-size: 3rem; animation: checkmarkScale 0.5s ease 0.2s both; }
    @keyframes checkmarkScale { 
        0% { transform: scale(0); opacity: 0; }
        60% { transform: scale(1.2); }
        100% { transform: scale(1); opacity: 1; }
    }

    .success-title { font-size: 1.25rem; font-weight: 800; color: #e8f6fb; margin-bottom: 8px; }
    .success-msg { font-size: 0.95rem; color: #bfdce8; line-height: 1.5; margin-bottom: 24px; }
    
    .loading-bar-wrap { width: 100%; height: 6px; background: rgba(255, 255, 255, 0.14); border-radius: 10px; overflow: hidden; margin-bottom: 8px; }
    .loading-bar-fill { width: 0%; height: 100%; background: #53c5e0; transition: width 3s linear; }
    .redirect-msg { font-size: 0.75rem; color: #9ec3d3; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    @media (max-width: 640px) {
        .review-contact-grid { grid-template-columns: 1fr; }
        .review-contact-full { grid-column: span 1; }
        .review-order-item .review-spec-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
        .review-order-item .review-spec-grid > .review-spec-tile:last-child:nth-child(3n + 1) {
            grid-column: auto;
        }
        .review-actions-row {
            flex-direction: column;
            align-items: stretch;
        }
        .review-buy-btn,
        .review-cancel-btn,
        .tshirt-btn {
            width: 100%;
        }
    }
    @media (max-width: 480px) {
        .review-order-item .review-spec-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<!-- Success Modal -->
<div id="successModal" class="success-modal-overlay <?php echo isset($order_placed_id) ? 'active' : ''; ?>">
    <div class="success-modal-card">
        <div class="success-icon-wrap">
            <span class="success-checkmark">✓</span>
        </div>
        <h2 class="success-title">Order Placed Successfully!</h2>
        <p class="success-msg">Your order <strong>#<?php echo $order_placed_id ?? ''; ?></strong> has been sent to our team for review. You'll receive a notification shortly.</p>
        
        <div class="loading-bar-wrap">
            <div id="loadingBar" class="loading-bar-fill"></div>
        </div>
        <p class="redirect-msg">Redirecting to services...</p>
    </div>
</div>
<div id="imagePreviewModal" class="image-preview-modal" aria-hidden="true">
    <img id="imagePreviewModalImg" src="" alt="Design preview">
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('successModal');
        const bar = document.getElementById('loadingBar');
        
        if (modal && modal.classList.contains('active')) {
            // Start loading bar animation
            setTimeout(() => {
                bar.style.width = '100%';
            }, 100);

            // Redirect after 3 seconds
            setTimeout(() => {
                window.location.href = '/printflow/customer/services.php';
            }, 3100);
        }

        const previewModal = document.getElementById('imagePreviewModal');
        const previewImg = document.getElementById('imagePreviewModalImg');
        document.querySelectorAll('.review-order-item img').forEach((img) => {
            img.classList.add('review-image-clickable');
            img.addEventListener('click', () => {
                previewImg.src = img.src;
                previewModal.classList.add('active');
            });
        });
        previewModal?.addEventListener('click', () => {
            previewModal.classList.remove('active');
            previewImg.src = '';
        });
    });
</script>

<div class="min-h-screen py-8">
    <?php if (!isset($order_placed_id)): ?>
    <div class="container mx-auto px-4 order-container">
        <h1 class="ct-page-title review-title">Review Your Order</h1>

        <form method="POST">
            <?php echo csrf_field(); ?>
            
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <?php if ($order_error): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($order_error); ?></div>
                <?php endif; ?>

                <!-- 1. Order Summary (Prominent, no price) -->
                <div class="card compact-card review-card">
                    <h2 class="review-heading" style="font-size:1rem; font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:8px;">
                        Order Summary
                    </h2>
                    <div class="review-order-item">
                        <?php render_order_item_clean($item, true, false, false); ?>
                    </div>

                    <!-- Pricing Notice (replaces price display) -->
                    <div class="review-info-note">
                        <span style="font-size:1.25rem; flex-shrink:0;">ℹ️</span>
                        <div>
                            <div class="review-info-note-title">Price will be confirmed by the shop</div>
                            <div class="review-info-note-text">Your order will be reviewed and priced by our team. Payment options will be available once your order reaches the <strong>To Pay</strong> stage.</div>
                        </div>
                    </div>
                </div>

                <!-- 2. Contact Information -->
                <div class="card compact-card review-card">
                    <h2 class="review-heading" style="font-size:1rem; font-weight:700; margin-bottom:1rem; padding-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                        Contact Information
                    </h2>
                    <div class="review-contact-grid">
                        <div>
                            <label class="review-input-label">First Name</label>
                            <input type="text" class="input-field review-input-disabled" value="<?php echo htmlspecialchars($customer['first_name'] ?? ''); ?>" disabled>
                        </div>
                        <div>
                            <label class="review-input-label">Last Name</label>
                            <input type="text" class="input-field review-input-disabled" value="<?php echo htmlspecialchars($customer['last_name'] ?? ''); ?>" disabled>
                        </div>
                        <div>
                            <label class="review-input-label">Email Address</label>
                            <input type="text" class="input-field review-input-disabled" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled>
                        </div>
                        <div>
                            <label class="review-input-label">Phone Number</label>
                            <input type="text" class="input-field review-input-disabled" value="<?php echo htmlspecialchars($customer['contact_number'] ?? '—'); ?>" disabled>
                        </div>
                        <div class="review-contact-full">
                            <label class="review-input-label">Address</label>
                            <textarea class="input-field review-input-disabled review-input-disabled-textarea" rows="2" disabled><?php echo htmlspecialchars($customer_address); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 3. Payment Policy Notice (no options shown yet) -->
                <div class="card compact-card review-policy-card">
                    <h2 class="review-policy-title" style="font-size:1rem; font-weight:700; margin-bottom:0.75rem; display:flex; align-items:center; gap:8px;">
                        Payment Policy
                    </h2>
                    <p class="review-policy-text">
                        Payment options (100% Full Payment or 50% Downpayment) will become available once staff reviews your order and sets the price.
                        You will receive a notification when your order is ready for payment.
                    </p>
                </div>

                <!-- 4. Final Actions -->
                <div class="review-actions-row">
                    <a href="?item=<?php echo urlencode($item_key); ?>&cancel=1" 
                       onclick="return confirm('Cancel this order?');"
                       class="tshirt-btn tshirt-btn-secondary">
                        Cancel Order
                    </a>
                    
                    <button type="submit" name="confirm_order" value="1" id="buyNowBtn" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

