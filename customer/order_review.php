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

// ── Accept the "buy_now" item key(s) from session ──────────────────
$item_key = $_REQUEST['item'] ?? '';
$cart     = $_SESSION['cart'] ?? [];

// Support multiple items separated by comma
$item_keys = array_filter(array_map('trim', explode(',', $item_key)));
if (empty($item_keys)) {
    redirect('products.php');
}

// Collect all valid items
$items_to_review = [];
foreach ($item_keys as $key) {
    if (isset($cart[$key])) {
        $items_to_review[$key] = $cart[$key];
    }
}

if (empty($items_to_review)) {
    redirect('products.php');
}

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

// Fetch active branches for selection
$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'") ?: [];

// ── Determine if branch selection is needed ──────────────────────
$is_multiple_checkout = count($items_to_review) > 1;
$needs_branch_selection = $is_multiple_checkout; // Forced for multiple
if (!$needs_branch_selection) {
    foreach ($items_to_review as $item) {
        if (empty($item['branch_id'])) {
            $needs_branch_selection = true;
            break;
        }
    }
}

// Get branch_id from items if already selected
$selected_branch_id_from_item = null;
if (!$needs_branch_selection) {
    foreach ($items_to_review as $item) {
        if (!empty($item['branch_id'])) {
            $selected_branch_id_from_item = $item['branch_id'];
            break;
        }
    }
}

// Handle Place Order FIRST (to allow clearing cart without trigger redirect)
$order_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    error_log('=== ORDER REVIEW POST RECEIVED ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('Current URL: ' . $_SERVER['REQUEST_URI']);
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $order_error = 'Invalid request. Please try again.';
    } else {
        // Validate branch selection
        $selected_branch_id = (int)($_POST['branch_id'] ?? 0);
        
        // STRICT REQUIREMENT: If the field was shown ($needs_branch_selection), it MUST be in POST.
        // ONLY fallback if selection was NOT required (single item with existing branch).
        if ($selected_branch_id < 1 && !$needs_branch_selection) {
            foreach ($items_to_review as $item) {
                if (!empty($item['branch_id'])) {
                    $selected_branch_id = (int)$item['branch_id'];
                    break;
                }
            }
        }
        
        if ($selected_branch_id < 1 && $needs_branch_selection) {
            $order_error = 'Please select a branch for pickup.';
        } else {
            // 1. Calculate totals and determine order properties
            $grand_total = 0;
            $order_type = 'product';
            $reference_id = null;
            $all_notes = [];
            
            foreach ($items_to_review as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $grand_total += $subtotal;
                
                if ($reference_id === null && !empty($item['product_id'])) {
                    $reference_id = $item['product_id'];
                }
                
                if (!empty($item['customization'])) {
                    $order_type = 'custom';
                    $note = $item['customization']['notes'] ?? $item['customization']['additional_notes'] ?? null;
                    if ($note) $all_notes[] = $note;
                }
            }
            
            $notes_summary = !empty($all_notes) ? implode('; ', $all_notes) : null;

            // Check restriction AGAIN at submission
            $is_restricted = is_customer_restricted($customer_id);

            if ($is_restricted) {
                $order_error = "🚫 Your account is restricted from placing new orders.";
            } else {
                global $conn;
                $downpayment_amount = 0;
                $payment_type = 'full_payment';
                $payment_status = 'Unpaid';
                // For service/custom orders, set status to 'Pending' so staff can set price (Step 1)
                // For product orders, set status to 'To Pay' so customer can pay immediately
                $order_status = ($order_type === 'custom') ? 'Pending' : 'To Pay';
                $branch_id = $selected_branch_id;
                
                // For service/custom orders, save estimated_price and set total_amount to estimated total
                $estimated_price = ($order_type === 'custom') ? $grand_total : null;
                $order_total_amount = $grand_total;

                // 2. Create Single Order with order_source = 'customer'
                $order_sql = "INSERT INTO orders (customer_id, branch_id, reference_id, order_date, total_amount, estimated_price, downpayment_amount, status, payment_status, payment_type, notes, order_type, order_source)
                              VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'customer')";
                $order_id  = db_execute($order_sql, 'iiidddsssss', [$customer_id, $branch_id, $reference_id, $order_total_amount, $estimated_price, $downpayment_amount, $order_status, $payment_status, $payment_type, $notes_summary, $order_type]);

                if ($order_id) {
                    error_log('Order created successfully with ID: ' . $order_id);
                    error_log('Order type: ' . $order_type);
                    error_log('Branch ID: ' . $branch_id);
                    
                    // 3. Process each item and insert into order_items
                    foreach ($items_to_review as $key => $item) {
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
                        
                        // Guard FK: ensure product_id exists
                        $product_exists = false;
                        if ($product_id !== null && $product_id > 0) {
                            $chk = db_query("SELECT product_id FROM products WHERE product_id = ? LIMIT 1", 'i', [$product_id]);
                            $product_exists = !empty($chk);
                        }
                        if (!$product_exists) {
                            $product_id = 3; // Fallback
                        }

                        // FIXED: Save estimated price to order_items so it displays correctly
                        // For service/custom orders, save estimated unit_price (staff can update later)
                        // For product orders, use the price as-is (it's already per-item unit price)
                        $unit_price = (float)$item['price'];
                        $quantity_val = (int)$item['quantity'];

                        if ($design_binary) {
                            $stmt = $conn->prepare(
                                "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, 
                                                        design_image, design_image_mime, design_image_name, design_file, reference_image_file)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            if ($stmt) {
                                $null = NULL;
                                $stmt->bind_param('iiidssssss', $order_id, $product_id, $item['quantity'], $unit_price, $custom_data, $null, $design_mime, $design_name, $design_file_path, $reference_file_path);
                                $stmt->send_long_data(5, $design_binary);
                                $stmt->execute();
                                $stmt->close();
                            }
                        } else {
                            db_execute(
                                "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, design_file, reference_image_file) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                                'iiidsss',
                                [$order_id, $product_id, $item['quantity'], $unit_price, $custom_data, $design_file_path, $reference_file_path]
                            );
                        }

                        if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) @unlink($item['design_tmp_path']);
                        if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path'])) @unlink($item['reference_tmp_path']);
                    }

                    // 4. Clear cart items and redirect
                    $item_keys_to_clear = array_keys($items_to_review);
                    foreach ($item_keys_to_clear as $key) {
                        if (isset($_SESSION['cart'][$key])) {
                            unset($_SESSION['cart'][$key]);
                        }
                    }
                    
                    $_SESSION['last_order_item_key'] = implode(',', $item_keys_to_clear);
                    sync_cart_to_db($customer_id);
                    
                    // Create notification for all staff members
                    $customer_name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                    if (empty($customer_name)) $customer_name = 'Customer';
                    
                    notify_staff_new_order($order_id, $customer_name);
                    
                    // Log activity (skip for customers as log_activity only works for staff users)
                    // log_activity($customer_id, 'Order Placed', "Customer placed order #$order_id");
                    
                    // For service orders (custom), redirect to orders page instead of payment
                    if ($order_type === 'custom') {
                        error_log('Service order placed, redirecting to orders page: ' . $order_id);
                        $_SESSION['order_success'] = "Order #$order_id placed successfully! Our team will review and price your order shortly.";
                        header("Location: orders.php");
                        exit();
                    } else {
                        error_log('Redirecting to payment page for order: ' . $order_id);
                        header("Location: payment.php?order_id=$order_id");
                        exit();
                    }
                } else {
                    $order_error = 'Failed to place order. Please try again.';
                }
            }
        }
    }
}

// Calculate total for all items
$grand_total = 0;
foreach ($items_to_review as $key => $item) {
    // Use the price as-is for service/custom orders (it's already the estimated_price)
    $grand_total += $item['price'] * $item['quantity'];
}

// Determine if any item has customization
$is_product_order = true;
foreach ($items_to_review as $item) {
    if (!empty($item['customization']) && is_array($item['customization']) && count($item['customization']) > 0) {
        $is_product_order = false;
        break;
    }
}

$page_title      = 'Review Your Order — PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .order-container { max-width: 650px; margin: 0 auto; }
    .compact-section { margin-bottom: 1.25rem; }
    .compact-card { padding: 1.25rem !important; }
    .review-title { text-align: center; margin-bottom: 2rem; color: #1f2937 !important; }
    .review-card {
        background: rgba(0,49,61,0.85) !important;
        border: 1px solid rgba(83,197,224,0.2) !important;
        border-radius: 12px !important;
        backdrop-filter: blur(8px);
    }
    .review-heading {
        color: #111827 !important;
        border-bottom-color: #e5e7eb !important;
    }
    .review-info-note {
        margin-top: 1rem;
        background: #f0f9ff !important;
        border: 1px solid #bae6fd !important;
        border-left: 4px solid #0ea5e9 !important;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }
    .review-info-note-title { font-size: 0.82rem; font-weight: 700; color: #0c4a6e !important; margin-bottom: 3px; }
    .review-info-note-text { font-size: 0.75rem; color: #075985 !important; line-height: 1.5; }
    .review-contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .review-contact-full { grid-column: span 2; }
    .review-input-label {
        display: block;
        font-size: 0.65rem;
        font-weight: 600;
        color: #6b7280 !important;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .review-input-disabled {
        background: rgba(0,49,61,0.5) !important;
        border: 1px solid rgba(83,197,224,0.2) !important;
        color: #e0f2fe !important;
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
        color: #374151 !important;
    }
    .review-policy-card {
        background: #fef3c7 !important;
        border: 1px solid #fde68a !important;
    }
    .review-policy-title { color: #78350f !important; }
    .review-policy-text { color: #92400e !important; line-height: 1.6; margin: 0; font-size: 0.82rem; }
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
        background: rgba(83,197,224,0.08) !important;
        border: 1px solid rgba(83,197,224,0.3) !important;
        color: #e0f2fe !important;
    }
    .tshirt-btn-secondary:hover {
        background: rgba(83,197,224,0.15) !important;
        border-color: rgba(83,197,224,0.5) !important;
        color: #ffffff !important;
    }
    .tshirt-btn-primary {
        border: none;
        background: #0a2530 !important;
        color: #fff !important;
        text-transform: uppercase;
        letter-spacing: .02em;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(10, 37, 48, 0.3);
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
        
        /* CRITICAL: Disable horizontal layout completely */
        .review-order-item > div:first-child {
            display: block !important;
            flex-direction: column !important;
            padding: 1rem !important;
        }
        
        /* TOP: Product Image - Centered */
        .review-order-item > div:first-child > div:first-child {
            width: 100% !important;
            max-width: 100% !important;
            height: auto !important;
            aspect-ratio: 1 / 1 !important;
            margin: 0 0 2rem 0 !important;
            display: block !important;
            float: none !important;
        }
        
        /* Product Details Container */
        .review-order-item > div:first-child > div:last-child {
            width: 100% !important;
            display: block !important;
        }
        
        .review-order-item .order-item-content {
            padding-top: 1rem !important;
        }
        
        /* Product Name - Single Line with Ellipsis */
        .review-order-item > div:first-child > div:last-child > h3 {
            font-size: 0.95rem !important;
            margin-bottom: 0.5rem !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            max-width: 100% !important;
        }
        
        /* Product Tag/Badge */
        .review-order-item > div:first-child > div:last-child > div:first-of-type {
            margin-bottom: 1rem !important;
        }
        
        /* MIDDLE: Details Section - Vertical Stack with Left-Right Alignment */
        .review-order-item > div:first-child > div:last-child > div:last-child {
            display: block !important;
            margin-top: 0 !important;
        }
        
        /* Hide desktop flex layout */
        .review-order-item > div:first-child > div:last-child > div:last-child > div {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 0.6rem 0 !important;
            border-bottom: 1px solid rgba(83, 197, 224, 0.15) !important;
            flex: none !important;
            min-width: 100% !important;
        }
        
        /* Remove border from last detail row before total */
        .review-order-item > div:first-child > div:last-child > div:last-child > div:nth-last-child(2) {
            border-bottom: none !important;
            padding-bottom: 0.6rem !important;
        }
        
        /* Remove border from Unit Price row specifically */
        .review-order-item .review-detail-row:nth-child(2) {
            border-bottom: 2px solid rgba(83, 197, 224, 0.3) !important;
            padding-bottom: 0.8rem !important;
        }
        
        /* Labels - Left Aligned */
        .review-order-item .review-detail-label,
        .review-order-item .review-total-label {
            font-size: 0.8rem !important;
            color: #9fc4d4 !important;
            font-weight: 600 !important;
            text-align: left !important;
            margin-bottom: 0 !important;
        }
        
        /* Values - Right Aligned */
        .review-order-item .review-detail-value,
        .review-order-item .review-total-value {
            font-size: 0.9rem !important;
            color: #eaf6fb !important;
            font-weight: 700 !important;
            text-align: right !important;
            margin-bottom: 0 !important;
        }
        
        /* DIVIDER before Total */
        .review-order-item .review-total-row {
            border-top: 2px solid rgba(83, 197, 224, 0.3) !important;
            padding-top: 0.8rem !important;
            margin-top: 0.8rem !important;
            border-bottom: none !important;
        }
        
        /* BOTTOM: Total Row - Emphasized */
        .review-order-item .review-total-label {
            font-size: 0.9rem !important;
            font-weight: 700 !important;
            color: #53c5e0 !important;
        }
        
        .review-order-item .review-total-value {
            font-size: 1.15rem !important;
            font-weight: 800 !important;
            color: #53c5e0 !important;
        }
        
        .review-order-item .review-total-row {
            border-top: none !important;
            padding-top: 0.8rem !important;
        }
        
        /* Fix Card Overflow */
        .review-order-item {
            max-width: 100% !important;
            overflow: hidden !important;
            padding: 0 !important;
        }
        
        .review-order-item > div {
            max-width: 100% !important;
            overflow: hidden !important;
        }
        
        /* Form Section - Single Column */
        .review-contact-grid {
            grid-template-columns: 1fr !important;
            gap: 0.75rem !important;
        }
        
        .review-input-disabled,
        .review-input-disabled-textarea {
            font-size: 0.85rem !important;
            padding: 0.65rem 0.75rem !important;
        }
        
        /* Buttons - Stacked Vertically */
        .card > div:last-child {
            flex-direction: column-reverse !important;
            gap: 0.75rem !important;
        }
        
        .shopee-btn-outline,
        .shopee-btn-primary {
            width: 100% !important;
            min-width: auto !important;
        }
        
        /* Compact Spacing */
        .compact-card {
            padding: 1rem !important;
        }
        
        .review-heading {
            font-size: 0.95rem !important;
            margin-bottom: 0.75rem !important;
        }
        
        .review-info-note {
            padding: 0.75rem !important;
            gap: 0.5rem !important;
        }
        
        .review-info-note-title {
            font-size: 0.75rem !important;
        }
        
        .review-info-note-text {
            font-size: 0.7rem !important;
        }
        
        /* Page Title */
        h1 {
            font-size: 1rem !important;
        }
        
        /* Back Button and Title - Single Line on Mobile */
        .container > div:first-child {
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 0.75rem !important;
        }
        
        .container > div:first-child a {
            position: static !important;
            margin-bottom: 0 !important;
        }
        
        .container > div:first-child h1 {
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
    @media (max-width: 480px) {
        .review-order-item .review-spec-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<!-- Success Modal -->
<?php if (false): // Disabled - redirect directly instead ?>
    <div class="success-modal-card">
        <div class="success-icon-wrap">
            <span class="success-checkmark">✓</span>
        </div>
        <h2 class="success-title">Order Placed Successfully!</h2>
        <p class="success-msg">Your order <strong>#<?php echo $order_placed_id ?? ''; ?></strong> has been sent to our team for review. You'll receive a notification shortly.</p>
        
        <div class="loading-bar-wrap">
            <div id="loadingBar" class="loading-bar-fill"></div>
        </div>
        <p class="redirect-msg">Redirecting to payment...</p>
    </div>
</div>
<?php endif; ?>
<div id="imagePreviewModal" class="image-preview-modal" aria-hidden="true">
    <img id="imagePreviewModalImg" src="" alt="Design preview">
</div>

<script>
    function toggleItems(btn) {
        const hiddenItems = document.querySelectorAll('.items-hidden');
        const isExpanded = btn.classList.contains('expanded');
        const textSpan = btn.querySelector('.show-more-text');
        if (isExpanded) {
            hiddenItems.forEach(item => item.style.display = 'none');
            btn.classList.remove('expanded');
            textSpan.textContent = 'Show ' + hiddenItems.length + ' More Item' + (hiddenItems.length > 1 ? 's' : '');
        } else {
            hiddenItems.forEach(item => item.style.display = 'block');
            btn.classList.add('expanded');
            textSpan.textContent = 'Show Less';
        }
    }
</script>

<div class="min-h-screen py-8">
    <?php if (!isset($order_placed_id)): ?>
    <div class="container mx-auto px-4 order-container">
        <div style="display: flex; align-items: center; justify-content: space-between; position: relative; margin-bottom: 2rem;">
            <a href="cart.php" style="text-decoration: none; display: flex; align-items: center; gap: 4px; color: #374151; font-weight: 600; transition: color 0.2s;" onmouseover="this.style.color='#111827'" onmouseout="this.style.color='#374151'">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            <h1 class="text-2xl font-bold text-gray-800" style="margin: 0; position: absolute; left: 50%; transform: translateX(-50%);">Review Your Order</h1>
        </div>

        <form method="POST" action="order_review.php?item=<?php echo urlencode($item_key); ?>" novalidate data-pf-skip-guard>
            <input type="hidden" name="item" value="<?php echo htmlspecialchars($item_key); ?>">
            <?php echo csrf_field(); ?>
            
            <?php if ($order_error): ?>
                <div class="alert-error" style="margin-bottom: 1.25rem;"><?php echo htmlspecialchars($order_error); ?></div>
            <?php endif; ?>

            <!-- Single Consolidated Card -->
            <div class="card compact-card review-card">
                <!-- 1. Order Summary -->
                <h2 class="review-heading" style="font-size:1rem; font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:8px;">
                    Order Summary (<?php echo count($items_to_review); ?> item<?php echo count($items_to_review) > 1 ? 's' : ''; ?>)
                </h2>
                <?php 
                $item_index = 0;
                foreach ($items_to_review as $key => $item): 
                    $item_index++;
                    $is_hidden = ($item_index > 3);
                ?>
                <div class="review-order-item <?php echo $is_hidden ? 'items-hidden' : ''; ?>" style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; <?php echo $key !== array_key_last($items_to_review) ? 'border-bottom: 1px solid #e5e7eb;' : ''; ?>">
                    <?php render_order_item_clean($item, true, true, true); ?>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($items_to_review) > 3): ?>
                <button type="button" class="show-more-btn" onclick="toggleItems(this)">
                    <span class="show-more-text">Show <?php echo count($items_to_review) - 3; ?> More Item<?php echo (count($items_to_review) - 3) > 1 ? 's' : ''; ?></span>
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <?php endif; ?>
                
                <!-- Grand Total -->
                <?php if (count($items_to_review) > 1): ?>
                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 1rem; font-weight: 700; color: #0c4a6e;">Total Amount:</span>
                        <span style="font-size: 1.25rem; font-weight: 800; color: #0369a1;">₱ <?php echo number_format($grand_total, 2); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pricing Notice -->
                <div class="review-info-note" style="margin-bottom: 2rem;">
                    <span style="font-size:1.25rem; flex-shrink:0;">ℹ️</span>
                    <div>
                        <div class="review-info-note-title">Order Review Process</div>
                        <div class="review-info-note-text">Your order will be reviewed by our team. You'll receive a notification when it's ready for payment or pickup.</div>
                    </div>
                </div>

                <?php if ($needs_branch_selection): ?>
                <!-- 2. Branch Selection -->
                <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #e5e7eb; margin-bottom: 1.5rem;">
                    <label class="review-input-label" style="margin-bottom: 0.5rem; display: block;">Pickup Branch *</label>
                    <select name="branch_id" id="branch_id" class="input-field" required style="background: #ffffff; border: 1px solid #d1d5db; color: #374151; font-weight: 500; font-size: 0.9rem; padding: 0.75rem; border-radius: 8px; cursor: pointer; transition: all 0.2s; width: 100%; display: block;">
                        <option value="">-- Select Branch --</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="branch-error" style="display: <?php echo ($order_error === 'Please select a branch for pickup.') ? 'flex' : 'none'; ?>; align-items: center; gap: 6px; color: #ef4444; font-size: 0.875rem; margin-top: 0.5rem; font-weight: 600;">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink: 0;"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        Please select a branch for pickup.
                    </div>
                </div>
                <?php endif; ?>

                <!-- 3. Contact Information -->
                <h2 class="review-heading" style="font-size:1rem; font-weight:700; margin-bottom:1rem; padding-bottom:0.5rem; padding-top:1rem; border-top: 1px solid #e5e7eb; display:flex; align-items:center; gap:8px;">
                    Contact Information
                </h2>
                <div class="review-contact-grid" style="margin-bottom: 1.5rem;">
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

                <?php if (!$is_product_order): ?>
                <!-- 3. Payment Policy Notice -->
                <div style="background: rgba(0,49,61,0.7); border: 1px solid #53c5e0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; backdrop-filter: blur(8px);">
                    <h3 style="font-size:0.95rem; font-weight:700; color:#53c5e0; margin-bottom:0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Payment Policy</h3>
                    <p style="font-size:0.85rem; color:#e0f2fe; line-height:1.6; margin:0;">
                        The payment option (100% Full Payment) will become available once staff reviews your order and sets the price. 
                        You will receive a notification when your order is ready for payment.
                    </p>
                </div>
                <?php endif; ?>

                <!-- 4. Final Actions -->
                <div style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    <a href="cart.php" 
                       class="shopee-btn-outline" style="width: 150px; text-align: center; padding: 0.75rem; text-decoration: none; white-space: nowrap;">
                        Back to Cart
                    </a>
                    
                    <button type="submit" name="confirm_order" value="1" class="shopee-btn-primary" style="width: 150px; white-space: nowrap;">Inquire Now</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const branchSelect = document.getElementById('branch_id');
    const branchError = document.getElementById('branch-error');

    if (form && branchSelect) {
        form.addEventListener('submit', function(e) {
            if (!branchSelect.value || branchSelect.value === '') {
                e.preventDefault();
                branchError.style.display = 'flex';
                branchSelect.style.borderColor = '#ef4444';
                branchSelect.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
                branchSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        branchSelect.addEventListener('change', function() {
            if (this.value) {
                branchError.style.display = 'none';
                this.style.borderColor = '#d1d5db';
                this.style.boxShadow = 'none';
            }
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

