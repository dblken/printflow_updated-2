<?php
/**
 * Shopping Cart Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Handle updates/removals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] as $pid => $qty) {
            if ($qty > 0 && isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid]['quantity'] = (int)$qty;
            }
        }
    } elseif (isset($_POST['remove_item'])) {
        $pid = $_POST['remove_item'];
        unset($_SESSION['cart'][$pid]);
    }
    header("Location: cart.php");
    exit;
}

$cart_items = $_SESSION['cart'] ?? [];
$total = 0;
$has_custom = false;
foreach ($cart_items as $item) {
    $item_price = (float)($item['price'] ?? 0);
    $is_unpriced_item = ($item_price <= 0);
    if (!$is_unpriced_item) {
        $total += $item['price'] * $item['quantity'];
    } else {
        $has_custom = true;
    }
}

$page_title = 'Shopping Cart - PrintFlow';
$use_customer_css = true;
$base_url = defined('BASE_URL') ? BASE_URL : '/printflow';

// Ensure all items have a selection state
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $pid => &$item) {
        if (!isset($item['selected'])) {
            $item['selected'] = true;
        }
    }
    unset($item);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .cart-theme-page {
        color: #d9e6ef;
    }
    .cart-theme-page .ct-page-title {
        color: #eaf6fb !important;
    }
    .cart-theme-page .card {
        background: rgba(10, 37, 48, 0.55) !important;
        border: 1px solid rgba(83, 197, 224, 0.22) !important;
        border-radius: 1.25rem !important;
        box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35) !important;
        overflow: hidden;
    }
    .cart-theme-page table {
        background: transparent !important;
        color: #d9e6ef !important;
    }
    .cart-theme-page thead {
        background: rgba(8, 30, 39, 0.85) !important;
        color: #9fc6d9 !important;
    }
    .cart-theme-page .cart-row {
        border-bottom: 1px solid rgba(83, 197, 224, 0.14) !important;
    }
    .cart-theme-page .cart-row td {
        color: #d9e6ef !important;
    }
    .cart-theme-page .cart-row:hover {
        background: rgba(83, 197, 224, 0.09) !important;
    }
    .cart-theme-page .btn-primary {
        background: linear-gradient(135deg, #53C5E0, #32a1c4) !important;
        color: #fff !important;
        border: none !important;
        box-shadow: 0 10px 22px rgba(50,161,196,0.3);
    }
    .cart-theme-page #checkout-btn {
        background: linear-gradient(135deg, #53C5E0, #32a1c4) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 10px 22px rgba(50,161,196,0.3) !important;
    }
    .cart-theme-page #checkout-btn:hover {
        filter: brightness(1.05);
    }
    .cart-theme-page .btn-secondary {
        background: rgba(255,255,255,.05) !important;
        color: #d9e6ef !important;
        border: 1px solid rgba(83,197,224,.28) !important;
    }
    .cart-theme-page .btn-secondary:hover {
        background: rgba(83,197,224,.14) !important;
        color: #fff !important;
    }

    /* Modern Circular Quantity Selector */
    .qty-control {
        display: inline-flex;
        align-items: center;
        background: rgba(13, 43, 56, 0.92);
        border-radius: 9999px;
        padding: 4px;
        gap: 12px;
        border: 1px solid rgba(83, 197, 224, 0.26);
    }
    .qty-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: rgba(83, 197, 224, 0.15);
        color: #eaf6fb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .qty-btn:hover:not(:disabled) {
        background: rgba(83, 197, 224, 0.24);
        transform: scale(1.05);
    }
    .qty-btn:active:not(:disabled) {
        transform: scale(0.95);
    }
    .qty-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .qty-val {
        font-weight: 700;
        font-size: 0.95rem;
        color: #eaf6fb;
        min-width: 20px;
        text-align: center;
    }

    /* Checkbox Styling */
    .cart-checkbox {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid rgba(83, 197, 224, 0.35);
        cursor: pointer;
        accent-color: #53c5e0;
    }

    /* Trash Icon Styling */
    .trash-btn {
        color: #fda4af;
        background: rgba(239, 68, 68, 0.12);
        border: none;
        padding: 8px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .trash-btn:hover {
        background: rgba(239, 68, 68, 0.2);
        transform: scale(1.1);
    }

    #removeModal > div:last-child {
        background: rgba(10, 37, 48, 0.97) !important;
        border: 1px solid rgba(83, 197, 224, 0.26) !important;
        box-shadow: 0 24px 42px rgba(0,0,0,0.45) !important;
    }
    #removeModal h3 { color: #eaf6fb !important; }
    #removeModal p { color: #b9d4df !important; }
    .cart-info-modal {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .cart-info-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(2, 12, 18, 0.65);
    }
    .cart-info-modal-card {
        position: relative;
        width: min(560px, 100%);
        max-height: 85vh;
        overflow: hidden;
        background: rgba(10, 37, 48, 0.97);
        border: 1px solid rgba(83, 197, 224, 0.28);
        border-radius: 14px;
        box-shadow: 0 18px 44px rgba(0,0,0,0.45);
    }
    .cart-info-scroll {
        max-height: 85vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 1.1rem 1.15rem 1rem;
        scrollbar-gutter: stable;
        scrollbar-width: thin;
        scrollbar-color: rgba(83, 197, 224, 0.72) rgba(255, 255, 255, 0.08);
    }
    .cart-info-scroll::-webkit-scrollbar {
        width: 10px;
    }
    .cart-info-scroll::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 999px;
    }
    .cart-info-scroll::-webkit-scrollbar-thumb {
        background: rgba(83, 197, 224, 0.72);
        border-radius: 999px;
        border: 2px solid rgba(10, 37, 48, 0.95);
    }
    .cart-info-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(83, 197, 224, 0.9);
    }
    .cart-info-title {
        margin: 0 2rem 0.4rem 0;
        color: #eaf6fb;
        font-size: 1.05rem;
        font-weight: 800;
    }
    .cart-info-sub {
        margin: 0 0 0.85rem;
        color: #9fc6d9;
        font-size: 0.85rem;
    }
    .cart-info-list {
        margin: 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 0.55rem;
    }
    .cart-info-list li {
        padding: 0.6rem 0.7rem;
        border: 1px solid rgba(83, 197, 224, 0.22);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.04);
        color: #d9e6ef;
        font-size: 0.86rem;
        line-height: 1.45;
    }
    .cart-info-close {
        position: absolute;
        top: 0.6rem;
        right: 0.65rem;
        width: 30px;
        height: 30px;
        border-radius: 999px;
        border: 1px solid rgba(83, 197, 224, 0.35);
        background: rgba(255,255,255,0.08);
        color: #d8edf5;
        font-size: 1.05rem;
        cursor: pointer;
    }
    .cart-info-actions {
        margin-top: 0.9rem;
        display: flex;
        justify-content: flex-end;
        gap: 0.65rem;
    }
    .cart-info-btn {
        height: 42px;
        min-width: 120px;
        padding: 0 1rem;
        border-radius: 10px;
        border: 1px solid rgba(83, 197, 224, 0.3);
        background: rgba(255,255,255,0.05);
        color: #d9e6ef;
        font-weight: 700;
        font-size: 0.86rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .cart-info-btn-primary {
        border: none;
        background: linear-gradient(135deg, #53C5E0, #32a1c4);
        color: #fff;
        box-shadow: 0 10px 22px rgba(50,161,196,0.3);
    }
</style>

<div class="min-h-screen py-8 cart-theme-page">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <h1 class="ct-page-title">Shopping Cart</h1>

        <?php 
        $customer_id = get_user_id();
        $cancel_count = get_customer_cancel_count($customer_id);
        $is_restricted = is_customer_restricted($customer_id);
        
        if ($is_restricted): ?>
            <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; color: #b91c1c; font-size: 0.95rem; display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 1.5rem;">🚫</span>
                <div><strong>Account Restricted:</strong> You are currently blocked from placing new orders due to excessive cancellations (7+). Please contact support.</div>
            </div>
        <?php elseif ($cancel_count >= 3): ?>
            <div style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">⚠️</span>
                <div>
                    <h3 style="color: #92400e; font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem;">Shopping Experience Warning</h3>
                    <p style="color: #b45309; font-size: 0.85rem; line-height: 1.5;">
                        You have <strong><?php echo $cancel_count; ?></strong> recent cancellations. 
                        <?php if ($cancel_count >= 4): ?>
                            Because you have 4 or more cancellations, <strong>'Pay Later' orders will require a 50% downpayment</strong> to proceed.
                        <?php else: ?>
                            Excessive cancellations may lead to payment restrictions or account suspension.
                        <?php endif; ?>
                        Complete a successful order to reset this counter!
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="ct-empty">
                <div class="ct-empty-icon">🛒</div>
                <p>Your cart is empty</p>
                <a href="products.php" class="btn-primary">Start Shopping</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="card" style="padding:0;">
                    <div class="overflow-x-auto">
                        <table style="width:100%; border-collapse:collapse;">
                            <thead style="background:rgba(8,30,39,0.85); font-size:0.875rem; text-transform:uppercase; letter-spacing:0.05em; color:#9fc6d9;">
                                <tr>
                                    <th style="padding:1rem; text-align:center; width: 50px;">
                                        <input type="checkbox" id="selectAll" class="cart-checkbox" onchange="toggleAll(this.checked)" <?php 
                                            $all_selected = true;
                                            foreach($cart_items as $item) if(!($item['selected']??true)) $all_selected = false;
                                            echo $all_selected ? 'checked' : '';
                                        ?>>
                                    </th>
                                    <th style="padding:1rem; text-align:left;">Product</th>
                                    <th style="padding:1rem; text-align:center;">Price</th>
                                    <th style="padding:1rem; text-align:center;">Quantity</th>
                                    <th style="padding:1rem; text-align:right;">Total</th>
                                    <th style="padding:1rem; width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody style="font-size:0.95rem;">
                                <?php foreach ($cart_items as $pid => $item): 
                                    $is_selected = $item['selected'] ?? true;
                                    $cat_lower = strtolower(($item['category'] ?? '') . ' ' . ($item['name'] ?? ''));
                                    $is_custom_row = false;
                                    $custom_keywords = ['tarpaulin', 't-shirt', 'shirt', 'sticker', 'decal', 'reflectorized', 'signage', 'glass', 'frosted', 'sintraboard', 'souvenir'];
                                    foreach($custom_keywords as $kw) { if(strpos($cat_lower, $kw) !== false) { $is_custom_row = true; break; } }
                                    $item_price = (float)($item['price'] ?? 0);
                                    $is_unpriced_row = ($item_price <= 0);
                                    $has_customization = !empty($item['customization']) && is_array($item['customization']);
                                    $has_service_artifacts = !empty($item['design_tmp_path']) || !empty($item['reference_tmp_path']);
                                    $explicit_service_type = (strcasecmp((string)($item['type'] ?? ''), 'Service') === 0);
                                    $is_service_item = ((int)($item['product_id'] ?? 0) <= 0) || $has_customization || $has_service_artifacts || $explicit_service_type || $is_custom_row;
                                    $source_page = strtolower(trim((string)($item['source_page'] ?? '')));
                                    if ($source_page === 'services') {
                                        $item_origin = 'Service';
                                    } elseif ($source_page === 'products') {
                                        $item_origin = 'Product';
                                    } else {
                                        $item_origin = $is_service_item ? 'Service' : 'Product';
                                    }
                                    $item_type_bg = ($item_origin === 'Product') ? 'rgba(16, 185, 129, 0.18)' : 'rgba(168, 85, 247, 0.18)';
                                    $item_type_border = ($item_origin === 'Product') ? 'rgba(16, 185, 129, 0.38)' : 'rgba(168, 85, 247, 0.4)';
                                    $item_type_text = ($item_origin === 'Product') ? '#9af0d4' : '#ddc5ff';
                                    $item_name = (string)($item['name'] ?? 'Unknown Product');
                                    $item_category = (string)($item['category'] ?? '');
                                    $qty_for_edit = max(1, (int)($item['quantity'] ?? 1));
                                    $modify_link = $base_url . '/customer/products.php';
                                    if (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'shirt') !== false) {
                                        $modify_link = $base_url . '/customer/order_tshirt.php?qty=' . $qty_for_edit;
                                    } elseif (strpos($cat_lower, 'sintraboard') !== false || strpos($cat_lower, 'standee') !== false) {
                                        $modify_link = $base_url . '/customer/order_sintraboard.php?qty=' . $qty_for_edit;
                                    } elseif (strpos($cat_lower, 'reflectorized') !== false || strpos($cat_lower, 'signage') !== false) {
                                        $modify_link = $base_url . '/customer/order_reflectorized.php?qty=' . $qty_for_edit;
                                    } elseif (strpos($cat_lower, 'glass') !== false || strpos($cat_lower, 'frosted') !== false) {
                                        $modify_link = $base_url . '/customer/order_glass_stickers.php?qty=' . $qty_for_edit;
                                    } elseif (strpos($cat_lower, 'tarpaulin') !== false) {
                                        $modify_link = $base_url . '/customer/order_tarpaulin.php?qty=' . $qty_for_edit;
                                    } elseif (strpos($cat_lower, 'transparent') !== false) {
                                        $modify_link = $base_url . '/customer/order_transparent.php?qty=' . $qty_for_edit;
                                    } elseif (strpos($cat_lower, 'sticker') !== false || strpos($cat_lower, 'decal') !== false) {
                                        $modify_link = $base_url . '/customer/order_stickers.php?qty=' . $qty_for_edit;
                                    } elseif (strpos($cat_lower, 'souvenir') !== false) {
                                        $modify_link = $base_url . '/customer/order_souvenirs.php?qty=' . $qty_for_edit;
                                    }
                                    $modify_link .= (strpos($modify_link, '?') !== false ? '&' : '?') . 'edit_item=' . rawurlencode((string)$pid);
                                    $info_lines = [];
                                    $info_lines[] = ['label' => 'Category', 'value' => $item_category !== '' ? $item_category : 'N/A'];
                                    $info_lines[] = ['label' => 'Quantity', 'value' => (string)$qty_for_edit];
                                    if (!empty($item['customization']) && is_array($item['customization'])) {
                                        foreach ($item['customization'] as $k => $v) {
                                            if (is_array($v)) continue;
                                            $label = trim(str_replace('_', ' ', (string)$k));
                                            $value = trim((string)$v);
                                            if ($value === '') continue;
                                            $info_lines[] = ['label' => $label, 'value' => $value];
                                        }
                                    }
                                    $info_json = htmlspecialchars(json_encode($info_lines), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="cart-row" data-id="<?php echo $pid; ?>" data-price="<?php echo $item['price']; ?>" data-custom="<?php echo $is_unpriced_row ? '1' : '0'; ?>" data-item-name="<?php echo htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8'); ?>" data-item-category="<?php echo htmlspecialchars($item_category, ENT_QUOTES, 'UTF-8'); ?>" data-info-lines="<?php echo $info_json; ?>" data-modify-link="<?php echo htmlspecialchars($modify_link, ENT_QUOTES, 'UTF-8'); ?>" style="border-bottom:1px solid rgba(83,197,224,.14); transition: background 0.2s; cursor:pointer; <?php echo !$is_selected ? 'opacity: 0.6; background: rgba(255,255,255,.035);' : ''; ?>">
                                        <td style="padding:1rem; text-align:center;">
                                            <input type="checkbox" class="cart-checkbox item-checkbox" onchange="toggleItem('<?php echo $pid; ?>', this.checked)" <?php echo $is_selected ? 'checked' : ''; ?>>
                                        </td>
                                        <td style="padding:1rem; display:flex; align-items:center; gap:1rem;">
                                            <?php
                                            $prod_id = (int)($item['product_id'] ?? 0);
                                            $product_img = "";
                                            
                                            // 1. Prefer admin-uploaded photo_path, then legacy product_image
                                            if ($prod_id > 0) {
                                                $prod_data = db_query("SELECT photo_path, product_image FROM products WHERE product_id = ? LIMIT 1", 'i', [$prod_id]);
                                                if (!empty($prod_data)) {
                                                    $photo_path = trim((string)($prod_data[0]['photo_path'] ?? ''));
                                                    $legacy_image = trim((string)($prod_data[0]['product_image'] ?? ''));
                                                    if ($photo_path !== '') {
                                                        $product_img = ($photo_path[0] === '/') ? $photo_path : ('/' . ltrim($photo_path, '/'));
                                                    } elseif ($legacy_image !== '') {
                                                        $product_img = ($legacy_image[0] === '/' || preg_match('/^https?:\/\//i', $legacy_image))
                                                            ? $legacy_image
                                                            : ('/' . ltrim($legacy_image, '/'));
                                                    }
                                                }
                                            }
                                            
                                            // 2. Try explicit product ID (file-based fallback)
                                            if (empty($product_img) && $prod_id > 0) {
                                                $img_base = "../public/images/products/product_" . $prod_id;
                                                if (file_exists($img_base . ".jpg")) {
                                                    $product_img = "/printflow/public/images/products/product_" . $prod_id . ".jpg";
                                                } elseif (file_exists($img_base . ".png")) {
                                                    $product_img = "/printflow/public/images/products/product_" . $prod_id . ".png";
                                                }
                                            }
                                            
                                            // 3. Fallback based on category/service_type for Service Orders
                                            if (empty($product_img)) {
                                                $cat_lower = strtolower(($item['category'] ?? '') . ' ' . ($item['name'] ?? ''));
                                                if (strpos($cat_lower, 'reflectorized') !== false || strpos($cat_lower, 'signage') !== false) {
                                                    $product_img = "/printflow/public/images/products/signage.jpg";
                                                } elseif (strpos($cat_lower, 'tarpaulin') !== false) {
                                                    $product_img = "/printflow/public/images/products/product_41.jpg";
                                                } elseif (strpos($cat_lower, 'sintraboard') !== false || strpos($cat_lower, 'standee') !== false) {
                                                    $product_img = "/printflow/public/images/services/Sintraboard Standees.jpg";
                                                } elseif (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'shirt') !== false) {
                                                    $product_img = "/printflow/public/images/products/product_31.jpg";
                                                } elseif (strpos($cat_lower, 'sticker') !== false || strpos($cat_lower, 'decal') !== false) {
                                                    if (strpos($cat_lower, 'glass') !== false || strpos($cat_lower, 'frosted') !== false) {
                                                        $product_img = "/printflow/public/images/products/Glass Stickers  Wall  Frosted Stickers.png";
                                                    } else {
                                                        $product_img = "/printflow/public/images/products/product_21.jpg";
                                                    }
                                                } elseif (strpos($cat_lower, 'souvenir') !== false) {
                                                    $product_img = "/printflow/public/assets/images/icon-192.png";
                                                }
                                            }
                                            ?>
                                            <div style="width:48px; height:48px; border-radius:6px; overflow:hidden; border:1px solid rgba(83,197,224,.22); background:rgba(255,255,255,.05); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                                <?php if (!empty($product_img)): ?>
                                                    <img src="<?php echo $product_img; ?>" style="width:100%; height:100%; object-fit:cover;" alt="Product">
                                                <?php else: ?>
                                                    <img src="/printflow/public/assets/images/icon-192.png" style="width:70%; height:70%; object-fit:contain; opacity:0.8;" alt="Logo">
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                                                    <div style="font-weight:600;"><?php echo htmlspecialchars($item['name'] ?? 'Unknown Product'); ?></div>
                                                    <span style="display:inline-flex; align-items:center; height:20px; padding:0 0.55rem; border-radius:999px; border:1px solid <?php echo $item_type_border; ?>; background:<?php echo $item_type_bg; ?>; color:<?php echo $item_type_text; ?>; font-size:0.68rem; font-weight:700; letter-spacing:0.02em; text-transform:uppercase;">
                                                        <?php echo $item_origin; ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($item['variant_name'])): ?>
                                                    <div style="font-size:0.75rem; color:#b6d8e6;"><?php echo htmlspecialchars((string)$item['variant_name']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($item['category'])): ?>
                                                    <div style="font-size:0.75rem; color:#9fc6d9;"><?php echo htmlspecialchars($item['category']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding:1rem; text-align:center;">
                                            <?php 
                                            if ($is_unpriced_row): ?>
                                                <span style="font-size:0.75rem; color:#9fc6d9; font-style:italic;">To be confirmed</span>
                                            <?php else: ?>
                                                <?php echo str_replace('PHP', '₱', format_currency($item['price'])); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:1rem; text-align:center;">
                                            <div class="qty-control">
                                                <button type="button" class="qty-btn" onclick="updateQty('<?php echo $pid; ?>', -1)" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>−</button>
                                                <span class="qty-val" id="qty-<?php echo $pid; ?>"><?php echo $item['quantity']; ?></span>
                                                <button type="button" class="qty-btn" onclick="updateQty('<?php echo $pid; ?>', 1)">+</button>
                                            </div>
                                        </td>
                                        <td style="padding:1rem; text-align:right; font-weight:600;" id="total-<?php echo $pid; ?>">
                                            <?php if ($is_unpriced_row): ?>
                                                <span style="font-size:0.75rem; color:#9fc6d9; font-style:italic;">To be confirmed</span>
                                            <?php else: ?>
                                                <?php echo str_replace('PHP', '₱', format_currency($item['price'] * $item['quantity'])); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:1rem; text-align:center;">
                                            <button type="button" class="trash-btn" onclick="confirmRemove('<?php echo $pid; ?>')" title="Remove">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="padding:1.5rem; background:rgba(8,30,39,.8); border-top:1px solid rgba(83,197,224,.18); display:flex; justify-content:space-between; align-items:center;">
                        <a href="products.php" class="btn-secondary" style="padding:0.5rem 1.25rem; border-radius:6px; font-weight: 500; text-decoration: none;">Continue Shopping</a>
                        
                        <div style="text-align:right;">
                            <div style="font-size:0.875rem; color:#9fc6d9; margin-bottom:0.25rem;">Subtotal <?php echo $has_custom ? '(Priced Items only)' : ''; ?></div>
                            <div style="font-size:1.5rem; font-weight:700; color:#eaf6fb; margin-bottom:1rem;" id="cart-total"><?php echo str_replace('PHP', '₱', format_currency($total)); ?></div>
                            <?php if ($has_custom): ?>
                                <div style="font-size:0.75rem; color:#9fc6d9; font-style:italic; margin-top:-0.5rem; margin-bottom:1rem;">+ Custom items (Price will be confirmed by the shop)</div>
                            <?php endif; ?>
                            <?php if ($is_restricted): ?>
                                <button type="button" class="btn-primary" style="padding:0.75rem 2rem; opacity:0.5; cursor:not-allowed;" disabled>Proceed to Checkout</button>
                            <?php else: ?>
                                <a href="checkout.php" id="checkout-btn" class="btn-primary" style="padding:0.75rem 2rem; <?php echo $total <= 0 ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">Proceed to Checkout</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Remove Confirmation Modal -->
<div id="removeModal" style="display:none; position:fixed; inset:0; z-index:50; align-items:center; justify-content:center;">
    <div style="position:absolute; inset:0; background:rgba(15,23,42,0.45);" onclick="closeRemoveModal()"></div>
    <div style="position:relative; background:rgba(10,37,48,.97); padding:2rem; border-radius:12px; max-width:400px; width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.4); z-index:51;">
        <h3 style="font-size:1.25rem; font-weight:700; color:#eaf6fb; margin-bottom:0.5rem;">Remove from Cart?</h3>
        <p style="color:#b9d4df; margin-bottom:1.5rem; line-height:1.5;">Are you sure you want to remove this item from your shopping cart?</p>
        <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
            <button type="button" onclick="closeRemoveModal()" style="padding:0.5rem 1.25rem; border-radius:8px; background:rgba(255,255,255,.08); color:#d9e6ef; font-weight:600; border:1px solid rgba(83,197,224,.24); cursor:pointer; transition:background 0.2s;">Cancel</button>
            <form method="POST" id="removeForm" style="margin:0;">
                <input type="hidden" name="remove_item" id="removeItemId" value="">
                <button type="submit" style="padding:0.5rem 1.25rem; border-radius:8px; background:#ef4444; color:white; font-weight:600; border:none; cursor:pointer; transition:background 0.2s;">Delete</button>
            </form>
        </div>
    </div>
</div>

<div id="cartInfoModal" class="cart-info-modal">
    <div class="cart-info-modal-backdrop" onclick="closeCartInfoModal()"></div>
    <div class="cart-info-modal-card" role="dialog" aria-modal="true" aria-labelledby="cartInfoTitle">
        <button type="button" class="cart-info-close" onclick="closeCartInfoModal()" aria-label="Close">×</button>
        <div class="cart-info-scroll">
            <h3 id="cartInfoTitle" class="cart-info-title"></h3>
            <p id="cartInfoSub" class="cart-info-sub"></p>
            <ul id="cartInfoList" class="cart-info-list"></ul>
            <div class="cart-info-actions">
                <button type="button" class="cart-info-btn" onclick="closeCartInfoModal()">Close</button>
                <button id="cartInfoModifyBtn" type="button" class="cart-info-btn cart-info-btn-primary">View Only</button>
            </div>
        </div>
    </div>
</div>

<script>
var PF_CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

function openCartInfoModal(row) {
    if (!row) return;
    const titleEl = document.getElementById('cartInfoTitle');
    const subEl = document.getElementById('cartInfoSub');
    const listEl = document.getElementById('cartInfoList');
    const modal = document.getElementById('cartInfoModal');
    const modifyBtn = document.getElementById('cartInfoModifyBtn');
    if (!titleEl || !subEl || !listEl || !modal || !modifyBtn) return;

    const itemName = row.getAttribute('data-item-name') || 'Cart Item';
    const itemCategory = row.getAttribute('data-item-category') || '';
    const infoJson = row.getAttribute('data-info-lines') || '[]';
    const modifyLink = row.getAttribute('data-modify-link') || '<?php echo $base_url; ?>/customer/services.php';

    titleEl.textContent = itemName;
    subEl.textContent = itemCategory;
    if (modifyBtn) {
        modifyBtn.onclick = function() {
            closeCartInfoModal();
        };
    }
    listEl.innerHTML = '';

    try {
        const lines = JSON.parse(infoJson);
        lines.forEach(function(line) {
            if (!line || !line.label) return;
            const li = document.createElement('li');
            const strong = document.createElement('strong');
            strong.textContent = String(line.label) + ':';
            li.appendChild(strong);
            li.appendChild(document.createTextNode(' ' + String(line.value || '')));
            listEl.appendChild(li);
        });
    } catch (e) {
        const li = document.createElement('li');
        li.textContent = 'No additional details.';
        listEl.appendChild(li);
    }

    modal.style.display = 'flex';
}

function closeCartInfoModal() {
    const modal = document.getElementById('cartInfoModal');
    if (modal) modal.style.display = 'none';
}

function confirmRemove(pid) {
    document.getElementById('removeItemId').value = pid;
    document.getElementById('removeModal').style.display = 'flex';
}
function closeRemoveModal() {
    document.getElementById('removeModal').style.display = 'none';
    document.getElementById('removeItemId').value = '';
}

async function updateQty(pid, delta) {
    const span = document.getElementById(`qty-${pid}`);
    if (!span) return;
    
    let currentQty = parseInt(span.textContent);
    let newQty = currentQty + delta;
    if (newQty < 1) return;
    
    // Optimistic UI
    span.textContent = newQty;
    const row = document.querySelector(`.cart-row[data-id="${pid}"]`);
    const price = parseFloat(row.dataset.price);
    const lineTotalSpan = document.getElementById(`total-${pid}`);
    lineTotalSpan.textContent = PHP(price * newQty);
    
    // Disable/Enable minus button
    const minusBtn = row.querySelector('.qty-btn:first-child');
    minusBtn.disabled = (newQty <= 1);
    
    recalculateTotal();

    try {
        const res = await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                cart_key: pid,
                quantity: newQty,
                csrf_token: PF_CSRF_TOKEN
            })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Failed to update quantity');
            // Revert on error
            span.textContent = currentQty;
            recalculateTotal();
        } else {
             // Update global count if needed
             if (window.updateCartBadge) updateCartBadge(data.cart_count);
        }
    } catch (err) {
        console.error(err);
    }
}

async function toggleItem(pid, selected) {
    const row = document.querySelector(`.cart-row[data-id="${pid}"]`);
    if (selected) {
        row.style.opacity = '1';
        row.style.background = 'transparent';
    } else {
        row.style.opacity = '0.6';
        row.style.background = 'rgba(255,255,255,.035)';
    }
    
    checkSelectAllState();
    recalculateTotal();
    
    try {
        await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_select',
                cart_key: pid,
                selected: selected,
                csrf_token: PF_CSRF_TOKEN
            })
        });
    } catch (err) { console.error(err); }
}

async function toggleAll(selected) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = selected;
        const pid = cb.closest('.cart-row').dataset.id;
        const row = cb.closest('.cart-row');
        if (selected) {
            row.style.opacity = '1';
            row.style.background = 'transparent';
        } else {
            row.style.opacity = '0.6';
            row.style.background = 'rgba(255,255,255,.035)';
        }
    });
    
    recalculateTotal();
    
    try {
        await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'select_all',
                selected: selected,
                csrf_token: PF_CSRF_TOKEN
            })
        });
    } catch (err) { console.error(err); }
}

function checkSelectAllState() {
    const all = document.querySelectorAll('.item-checkbox');
    const checked = document.querySelectorAll('.item-checkbox:checked');
    document.getElementById('selectAll').checked = (all.length === checked.length);
}

function recalculateTotal() {
    let subtotal = 0;
    const rows = document.querySelectorAll('.cart-row');
    rows.forEach(row => {
        const checkbox = row.querySelector('.item-checkbox');
        if (checkbox.checked) {
            const isCustom = row.dataset.custom === '1';
            if (!isCustom) {
                const pid = row.dataset.id;
                const price = parseFloat(row.dataset.price);
                const qty = parseInt(document.getElementById(`qty-${pid}`).textContent);
                subtotal += price * qty;
            }
        }
    });
    
    document.getElementById('cart-total').textContent = PHP(subtotal);
    
    // Disable/Enable checkout button
    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
        if (subtotal <= 0) {
            checkoutBtn.style.opacity = '0.5';
            checkoutBtn.style.pointerEvents = 'none';
        } else {
            checkoutBtn.style.opacity = '1';
            checkoutBtn.style.pointerEvents = 'auto';
        }
    }
}

function PHP(amount) {
    return '₱' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.cart-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('button, a, input, label, .qty-control, .trash-btn, .cart-checkbox')) return;
            openCartInfoModal(row);
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCartInfoModal();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

