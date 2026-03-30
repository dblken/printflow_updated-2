<?php
/**
 * T-Shirt Printing - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

// Print placement options with image mapping (placement name -> image filename)
// One image per unique print type; Left/Right Chest combined to avoid duplicate image
$placement_options = [
    'Front Center Print' => 'Front Center Print.webp',
    'Back Upper Print' => 'Back Upper Print.webp',
    'Left/Right Chest Print' => 'Left Right Chest Print.webp',
    'Bottom Hem Print' => 'Buttom Hem Print.webp',
    'Sleeve Print' => 'Sleeve Print.webp',
    'Long Sleeve Arm Print' => 'Long Sleeve Arm Print.webp',
];

$img_base = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/assets/images/tshirt_replacement/';
$edit_item_key = trim((string)($_GET['edit_item'] ?? $_POST['edit_item'] ?? ''));
$is_edit_mode = false;
$edit_existing_item = null;

if ($edit_item_key !== '' && isset($_SESSION['cart'][$edit_item_key]) && is_array($_SESSION['cart'][$edit_item_key])) {
    $candidate = $_SESSION['cart'][$edit_item_key];
    $cat_name = strtolower(((string)($candidate['category'] ?? '')) . ' ' . ((string)($candidate['name'] ?? '')));
    if (strpos($cat_name, 't-shirt') !== false || strpos($cat_name, 'shirt') !== false) {
        $is_edit_mode = true;
        $edit_existing_item = $candidate;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $cust = (array)($candidate['customization'] ?? []);
            $saved_type = (string)($cust['shirt_type'] ?? '');
            $saved_color = (string)($cust['shirt_color'] ?? '');
            $saved_size = (string)($cust['size'] ?? '');
            $known_types = ['Crew Neck', 'V-Neck', 'Polo', 'Raglan', 'Long Sleeve'];
            $known_colors = ['Black', 'White', 'Red', 'Blue', 'Navy', 'Grey'];
            $known_sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
            $_POST['branch_id'] = (string)($candidate['branch_id'] ?? '');
            $_POST['shirt_source'] = (string)($cust['shirt_source'] ?? '');
            $_POST['shirt_type'] = in_array($saved_type, $known_types, true) ? $saved_type : 'Others';
            $_POST['shirt_type_other'] = in_array($saved_type, $known_types, true) ? '' : $saved_type;
            $_POST['shirt_color'] = in_array($saved_color, $known_colors, true) ? $saved_color : 'Other';
            $_POST['color_other'] = in_array($saved_color, $known_colors, true) ? '' : $saved_color;
            $_POST['sizes'] = in_array($saved_size, $known_sizes, true) ? $saved_size : 'Others';
            $_POST['sizes_other'] = in_array($saved_size, $known_sizes, true) ? '' : $saved_size;
            $_POST['print_placement'] = (string)($cust['print_placement'] ?? '');
            $_POST['lamination'] = (string)($cust['lamination'] ?? '');
            $_POST['quantity'] = (string)($candidate['quantity'] ?? ($cust['quantity'] ?? 1));
            $_POST['needed_date'] = (string)($cust['needed_date'] ?? '');
            $_POST['notes'] = (string)($cust['notes'] ?? '');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $shirt_source = trim($_POST['shirt_source'] ?? '');
    $shirt_type = trim($_POST['shirt_type'] ?? '');
    $shirt_type_other = trim($_POST['shirt_type_other'] ?? '');
    $shirt_color = trim($_POST['shirt_color'] ?? '');
    $color_other = trim($_POST['color_other'] ?? '');
    $sizes = trim($_POST['sizes'] ?? '');
    $sizes_other = trim($_POST['sizes_other'] ?? '');
    $print_placement = trim($_POST['print_placement'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $design_type = trim($_POST['design_type'] ?? '');
    $print_color = trim($_POST['print_color'] ?? '');
    $text_content = trim($_POST['text_content'] ?? '');
    $font_style = trim($_POST['font_style'] ?? '');
    $font_size = trim($_POST['font_size'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $color_display = ($shirt_color === 'Other') ? $color_other : $shirt_color;
    $shirt_type_display = ($shirt_type === 'Others') ? $shirt_type_other : $shirt_type;

    $shop_provides = ($shirt_source === 'Shop provides shirt');
    $is_text_only = ($design_type === 'Text Only');
    $is_logo_only = ($design_type === 'Logo Only');
    $proceed = false;
    
    if ($branch_id <= 0) {
        $error = 'Please select a branch.';
    } elseif (empty($shirt_source)) {
        $error = 'Please select whether the shop or customer will provide the shirt.';
    } elseif (empty($design_type)) {
        $error = 'Please select a design type.';
    } elseif ($is_text_only && (empty($text_content) || empty($print_color))) {
        $error = 'Please provide text content and print color for text designs.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif ($shop_provides) {
        if (empty($shirt_type_display) || empty($color_display) || empty($size) || empty($print_placement) || empty($lamination) || empty($needed_date) || $quantity < 1) {
            $error = 'Please fill in required fields: Shirt Type, Color, Sizes, Print Placement, Lamination, Needed Date, and Quantity.';
        } elseif ($shirt_type === 'Others' && empty($shirt_type_other)) {
            $error = 'Please enter your custom shirt type.';
        } elseif ($sizes === 'Others' && empty($sizes_other)) {
            $error = 'Please enter your custom size.';
        } elseif ($shirt_color === 'Other' && empty($color_other)) {
            $error = 'Please enter your custom shirt color.';
        } else {
            $proceed = true;
        }
    } else {
        if (empty($shirt_type_display) || empty($color_display) || empty($print_placement) || empty($lamination) || empty($needed_date) || $quantity < 1) {
            $error = 'Please fill in required fields: Shirt Type, Color, Print Placement, Lamination, Needed Date, and Quantity.';
        } elseif ($shirt_color === 'Other' && empty($color_other)) {
            $error = 'Please enter your custom shirt color.';
        } elseif ($shirt_type === 'Others' && empty($shirt_type_other)) {
            $error = 'Please enter your custom shirt type.';
        } else {
            $proceed = true;
        }
    }

    $has_new_upload = isset($_FILES['design_file']) && ($_FILES['design_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    if ($proceed && $is_logo_only && !$has_new_upload && !$is_edit_mode) {
        $error = 'Please upload your design file for logo printing.';
        $proceed = false;
    }

    if ($proceed) {
        $tmp_dest = '';
        $original_name = '';
        $mime = '';
        if ($has_new_upload) {
            $valid = service_order_validate_file($_FILES['design_file']);
            if (!$valid['ok']) {
                $error = $valid['error'];
                $proceed = false;
            } else {
                $original_name = $_FILES['design_file']['name'];
                $mime = $valid['mime'];
                $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $new_name = uniqid('tmp_') . '.' . $ext;
                $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;
                if (!move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                    $error = 'Failed to process uploaded file.';
                    $proceed = false;
                }
            }
        } elseif ($is_edit_mode && is_array($edit_existing_item)) {
            $tmp_dest = (string)($edit_existing_item['design_tmp_path'] ?? '');
            $original_name = (string)($edit_existing_item['design_name'] ?? '');
            $mime = (string)($edit_existing_item['design_mime'] ?? '');
        }

        if ($proceed) {
            $item_key = ($is_edit_mode && $edit_item_key !== '' && isset($_SESSION['cart'][$edit_item_key])) ? $edit_item_key : ('tshirt_' . time() . '_' . rand(100, 999));
            $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'T-Shirt Printing',
                    'price' => 350.00,
                    'quantity' => $quantity,
                    'category' => 'T-Shirts',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'shirt_source' => $shirt_source,
                        'design_type' => $design_type,
                        'print_color' => $print_color,
                        'text_content' => $text_content,
                        'font_style' => $font_style,
                        'font_size' => $font_size,
                        'shirt_type' => $shirt_type_display,
                        'shirt_color' => $color_display,
                        'size' => $size ?? '',
                        'print_placement' => $print_placement,
                        'lamination' => $lamination,
                        'quantity' => $quantity,
                        'needed_date' => $needed_date,
                        'notes' => $notes
                    ]
            ];
            
            if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                redirect("order_review.php?item=" . urlencode($item_key));
            } else {
                redirect("cart.php");
            }
        }
    }
}

$page_title = 'Order T-Shirt - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">T-Shirt Printing</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card">
            <form action="" method="POST" enctype="multipart/form-data" id="tshirtForm" novalidate>
                <?php echo csrf_field(); ?>
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="edit_item" value="<?php echo htmlspecialchars($edit_item_key); ?>">
                <?php endif; ?>

                <!-- Top Notice -->
                <div class="tshirt-top-notice mb-4">
                    Please choose whether the shirt will be provided by the shop or by the customer. It is recommended that customers provide their own shirt so they can ensure the correct size and preferred quality for their use.
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <option value="" selected disabled>Select Branch</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ((string)($b['id']) === (string)($_POST['branch_id'] ?? '')) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1. Shirt Source (must be first) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shirt Source *</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_source" value="Shop will provide the shirt" required> <span>Shop will provide the shirt</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_source" value="Customer will provide the shirt" required> <span>Customer will provide the shirt</span></label>
                    </div>
                    <div id="shop-provides-note" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 0.875rem; color: #92400e;">
                        Additional charges apply since shirt is included. Shirt cost + print cost will be charged.
                    </div>
                </div>

                <!-- 1b. Design Type -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Design Type *</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap"><input type="radio" name="design_type" value="Logo Only" required> <span>Logo Only</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="design_type" value="Text Only" required> <span>Text Only</span></label>
                    </div>
                </div>

                <!-- 1c. File Upload (Moved here) -->
                <div class="mb-4" id="upload-section">
                    <label class="block text-sm font-medium text-gray-700 mb-1" id="upload-label">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field">
                    <?php if ($is_edit_mode): ?>
                        <p class="sintra-hint mt-2">Leave empty to keep your current uploaded design.</p>
                    <?php endif; ?>
                </div>

                <!-- 1d. Text Design Details (Visible only for Text Only) -->
                <div id="text-design-section" style="display: none;">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Text Content *</label>
                        <input type="text" name="text_content" id="text_content" class="input-field" placeholder="Enter the text to be printed">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Print Color * (Required for text designs)</label>
                        <input type="text" name="print_color" id="print_color" class="input-field" placeholder="e.g. White, Gold, Red">
                    </div>
                    <div class="mb-4 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Font Style</label>
                            <input type="text" name="font_style" id="font_style" class="input-field" placeholder="e.g. Arial, Script">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Font Size</label>
                            <input type="text" name="font_size" id="font_size" class="input-field" placeholder="e.g. 2 inch, 48px">
                        </div>
                    </div>
                    <p class="tshirt-top-notice mb-4" style="background: rgba(83, 197, 224, 0.05); border-left: 3px solid #53c5e0; font-size: 0.8rem;">
                        Not sure about font size or style? You can place your order and message our staff afterward for assistance.
                    </p>
                </div>

                <!-- 2. Shirt Type (3×2 grid) -->
                <div class="mb-4" id="shirt-type-section">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shirt Type <span id="shirt-type-required-mark">*</span></label>
                    <div class="option-grid option-grid-3x2">
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Crew Neck"> <span>Crew Neck</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="V-Neck"> <span>V-Neck</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Polo"> <span>Polo</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Raglan"> <span>Raglan</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Long Sleeve"> <span>Long Sleeve</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Others"> <span>Others</span></label>
                    </div>
                    <div id="shirt-type-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="shirt_type_other" id="shirt_type_other" class="input-field" placeholder="Enter custom shirt type">
                    </div>
                </div>

                <!-- 3. Shirt Color (3×3, Others centered) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shirt Color *</label>
                    <div class="option-grid option-grid-3x3 option-grid-color">
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Black" required> <span>Black</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="White" required> <span>White</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Red" required> <span>Red</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Blue" required> <span>Blue</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Navy" required> <span>Navy</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Grey" required> <span>Grey</span></label>
                        <label class="opt-btn-wrap opt-btn-others"><input type="radio" name="shirt_color" value="Other" required> <span>Others</span></label>
                    </div>
                    <div id="color-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="color_other" id="color_other" class="input-field" placeholder="Enter custom color">
                    </div>
                </div>

                <!-- 4. Sizes (shown only when Shop provides) -->
                <div class="mb-4" id="size-section" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sizes <span id="size-required-mark">*</span></label>
                    <div class="option-grid option-grid-3x2">
                        <label class="opt-btn-wrap"><input type="radio" name="sizes" value="XS"> <span>XS</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="sizes" value="S"> <span>S</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="sizes" value="M"> <span>M</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="sizes" value="L"> <span>L</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="sizes" value="XL"> <span>XL</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="sizes" value="XXL"> <span>XXL</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="sizes" value="XXXL"> <span>XXXL</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="sizes" value="Others" id="sizes-others-radio"> <span>Others</span></label>
                    </div>
                    <div id="sizes-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="sizes_other" id="sizes_other" class="input-field" placeholder="Enter custom size or measurements">
                    </div>
                </div>

                <!-- 5. Print Placement (with hover preview) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Print Placement *</label>
                    <div class="placement-grid">
                        <?php foreach ($placement_options as $name => $img_file): 
                            $img_url = $img_base . rawurlencode($img_file);
                        ?>
                        <label class="placement-card" data-img="<?php echo htmlspecialchars($img_url); ?>" data-name="<?php echo htmlspecialchars($name); ?>">
                            <input type="radio" name="print_placement" value="<?php echo htmlspecialchars($name); ?>" required>
                            <div class="placement-img-wrap">
                                <img src="<?php echo $img_url; ?>" alt="<?php echo htmlspecialchars($name); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="placement-fallback" style="display:none; width:100%; height:100%; background:#f3f4f6; align-items:center; justify-content:center; font-size:0.7rem; color:#6b7280; text-align:center; padding:0.5rem;"><?php echo htmlspecialchars($name); ?></div>
                            </div>
                            <span class="placement-label"><?php echo htmlspecialchars($name); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 6. Removed from here -->

                <!-- 7. Lamination + Quantity (One Row) -->
                <div class="mb-4">
                    <div class="lam-qty-row">
                        <div class="lam-qty-lam">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lamination *</label>
                            <div class="lam-options">
                                <label class="opt-btn-wrap lam-opt"><input type="radio" name="lamination" value="With Laminate" required> <span>With Laminate</span></label>
                                <label class="opt-btn-wrap lam-opt"><input type="radio" name="lamination" value="Without Laminate" required> <span>Without Laminate</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="need-qty-row">
                        <div class="need-qty-date">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="lam-qty-qty need-qty-qty">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="qty-control qty-control-shopee">
                                <button type="button" onclick="tshirtDecreaseQty()" class="qty-btn">−</button>
                                <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                                <button type="button" onclick="tshirtIncreaseQty()" class="qty-btn">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field" placeholder="Any special instructions..." maxlength="500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <!-- 7. Buttons - Bottom-right, side-by-side, no icons, same style as other services -->
                <div class="tshirt-actions-row">
                    <?php if ($is_edit_mode): ?>
                        <a href="cart.php" class="tshirt-btn tshirt-btn-secondary">Cancel</a>
                        <button type="submit" name="action" value="save_changes" id="buyNowBtn" class="tshirt-btn tshirt-btn-primary">Save Changes</button>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                        <button type="submit" name="action" value="add_to_cart" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                        <button type="submit" name="action" value="buy_now" id="buyNowBtn" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.opt-btn-wrap { padding: 0.65rem 1rem; border: 2px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; color: #374151; transition: all 0.25s ease; }
.opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn-wrap:has(input:checked) { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: rgba(10,37,48,0.03); }
.opt-btn-wrap input { margin-right: 0.5rem; }
#tshirtForm input[type="radio"] {
    accent-color: #53c5e0;
}
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
#tshirtForm .opt-btn-group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
}
.option-grid { display: grid; gap: 0.5rem; }
.option-grid-3x2 { grid-template-columns: repeat(3, 1fr); }
.option-grid-3x3 { grid-template-columns: repeat(3, 1fr); }
.option-grid-color .opt-btn-others { grid-column: 2; }
.lam-qty-row { display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap; }
.lam-qty-lam { flex: 1; min-width: 0; }
.lam-qty-qty { flex-shrink: 0; }
.lam-options { display: flex; flex-wrap: nowrap; gap: 0.5rem; }
.lam-opt { white-space: nowrap; padding: 0.5rem 0.75rem; font-size: 0.85rem; }
.qty-control { display: flex; align-items: center; height: 42px; border: 2px solid #d1d5db; border-radius: 8px; background: #fff; overflow: hidden; transition: border-color 0.2s ease; }
.qty-control:focus-within { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.qty-control-shopee { width: 110px; flex-shrink: 0; }
.qty-btn { flex: 0 0 36px; width: 36px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.1rem; cursor: pointer; transition: background 0.2s; }
.qty-btn:hover { background: #e5e7eb; }
.qty-control input { flex: 1; min-width: 28px; border: none; text-align: center; font-weight: 700; font-size: 0.95rem; outline: none; background: transparent; }
.tshirt-top-notice { padding: 1rem; background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0ea5e9; border-radius: 8px; font-size: 0.875rem; color: #0369a1; line-height: 1.5; }

.placement-preview-area { width: 100%; max-width: 280px; aspect-ratio: 1; margin: 0 auto 1rem; border-radius: 10px; overflow: hidden; background: #f9fafb; border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.placement-preview-placeholder { font-size: 0.8rem; color: #9ca3af; text-align: center; padding: 1rem; }
.placement-preview-img { width: 100%; height: 100%; object-fit: contain; transition: opacity 0.2s; }
.placement-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
.placement-card { display: flex; flex-direction: column; align-items: center; cursor: pointer; border: 2px solid #d1d5db; border-radius: 8px; padding: 0.5rem; background: #fff; transition: all 0.2s ease; }
.placement-card:hover { border-color: #0a2530; background: #f9fafb; }
.placement-card:has(input:checked) { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.placement-card input { position: absolute; opacity: 0; pointer-events: none; }
.placement-img-wrap { width: 100%; aspect-ratio: 1; border-radius: 6px; overflow: hidden; background: #f3f4f6; position: relative; }
.placement-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.placement-label { font-size: 0.7rem; font-weight: 600; text-align: center; margin-top: 0.5rem; line-height: 1.2; color: #374151; }

/* =========================
   T-shirt form redesign (UI only)
   ========================= */
#tshirtForm {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
#tshirtForm .mb-4 {
    margin-bottom: 0 !important;
    padding: 1rem;
    border: 1px solid rgba(83,197,224,.2);
    border-radius: 12px;
    background: rgba(255,255,255,.03);
}
#tshirtForm label.block {
    font-size: .95rem !important;
    font-weight: 700 !important;
    color: #d9e6ef !important;
    margin-bottom: .55rem !important;
}
#tshirtForm .input-field {
    min-height: 44px;
    padding: .72rem .9rem;
    border-radius: 10px;
}
.tshirt-top-notice {
    font-size: .82rem;
    color: #9fc6d9;
    border-left-width: 3px;
    line-height: 1.6;
}
.opt-btn-wrap {
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    font-size: .86rem;
    border-radius: 10px;
}
.opt-btn-wrap:has(input:checked) {
    border-color: #53c5e0;
    box-shadow: 0 0 0 2px rgba(83,197,224,.25);
    background: rgba(83,197,224,.12);
    color: #e8f7fc;
}
.option-grid-3x2,
.option-grid-3x3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .6rem;
}
.lam-qty-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
.lam-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .6rem;
    width: 100%;
}
.lam-options .lam-opt {
    width: 100%;
}
.lam-qty-lam,
.lam-qty-qty {
    display: flex;
    flex-direction: column;
    border: 1px solid transparent;
    border-radius: 10px;
    padding: .15rem;
    position: relative;
}
.lam-qty-lam {
    flex: 1;
    min-width: 100%;
}
.lam-qty-qty {
    flex-shrink: 0;
}
.lam-qty-qty .field-error {
    max-width: 220px;
}
.need-qty-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
.need-qty-date {
    flex: 1;
    min-width: 0;
}
.need-qty-qty {
    flex: 1;
    min-width: 0;
}
.need-qty-qty .qty-control-shopee {
    width: 100%;
}
.need-qty-qty .field-error {
    display: none !important;
}
.placement-preview-area {
    max-width: 220px;
    border-radius: 12px;
    margin-bottom: .8rem;
}
.placement-grid {
    gap: .6rem;
}
.placement-card {
    border-radius: 10px;
    min-height: 132px;
}
.placement-label {
    font-size: .76rem;
    font-weight: 500;
    color: #c0d7e3;
}
.tshirt-actions-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: .75rem;
    margin-top: 1.1rem;
    flex-wrap: wrap;
}
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
    background: rgba(255,255,255,.06);
    color: #d9e6ef;
    border: 1px solid rgba(83,197,224,.28);
}
.tshirt-btn-secondary:hover {
    background: rgba(83,197,224,.12);
    color: #fff;
}
.tshirt-btn-primary {
    border: none;
    background: linear-gradient(135deg, #53C5E0, #32a1c4);
    color: #fff;
    text-transform: uppercase;
    letter-spacing: .02em;
    cursor: pointer;
    box-shadow: 0 10px 22px rgba(50,161,196,0.3);
}
.tshirt-btn:active {
    transform: translateY(1px) scale(0.99);
}

/* Dark theme color harmonization (replace white-heavy controls) */
#tshirtForm .mb-4 {
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    backdrop-filter: blur(4px);
}
.tshirt-top-notice {
    background: rgba(9, 46, 60, 0.72) !important;
    border: 1px solid rgba(83, 197, 224, 0.32) !important;
    border-left: 3px solid #53c5e0 !important;
    color: #b9dcea !important;
}
.opt-btn-wrap {
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(83, 197, 224, 0.2) !important;
    color: #d2e7f1 !important;
}
.opt-btn-wrap:hover {
    background: rgba(83, 197, 224, 0.12) !important;
    border-color: rgba(83, 197, 224, 0.5) !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.12);
}
.opt-btn-wrap:has(input:checked) {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important;
    border-color: #53c5e0 !important;
    color: #f8fcff !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.22), 0 8px 18px rgba(11, 42, 56, 0.35);
}

#tshirtForm .input-field {
    background: rgba(13, 43, 56, 0.94) !important;
    border: 1px solid rgba(83, 197, 224, 0.3) !important;
    color: #eef7fb !important;
}
#tshirtForm .input-field::placeholder {
    color: #a3bdca !important;
}
#tshirtForm textarea[name="notes"].input-field {
    overflow-y: auto;
    resize: vertical;
    min-height: 110px;
    max-height: 220px;
    max-width: 100%;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08);
}
#tshirtForm textarea[name="notes"].input-field::-webkit-scrollbar {
    width: 10px;
}
#tshirtForm textarea[name="notes"].input-field::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 999px;
}
#tshirtForm textarea[name="notes"].input-field::-webkit-scrollbar-thumb {
    background: rgba(83, 197, 224, 0.65);
    border-radius: 999px;
    border: 2px solid rgba(10, 37, 48, 0.55);
}
#tshirtForm textarea[name="notes"].input-field::-webkit-scrollbar-thumb:hover {
    background: rgba(83, 197, 224, 0.85);
}
#tshirtForm .input-field:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
}
#tshirtForm select.input-field option {
    background: #0a2530 !important;
    color: #f8fafc !important;
}
#tshirtForm select.input-field option:hover,
#tshirtForm select.input-field option:focus {
    background: #53c5e0 !important;
    color: #06232c !important;
}
#tshirtForm select.input-field option:checked {
    background: #53c5e0 !important;
    color: #06232c !important;
}
#tshirtForm .input-field[type="number"]::-webkit-outer-spin-button,
#tshirtForm .input-field[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
#tshirtForm .input-field[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}
#tshirtForm .input-field[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1) brightness(1.35);
    opacity: .95;
    cursor: pointer;
}

.qty-control {
    display: flex;
    align-items: center;
    height: 44px;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
    border-radius: 10px;
    overflow: hidden;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.qty-control:focus-within {
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
}
.qty-btn {
    flex: 0 0 44px;
    height: 100%;
    border: none;
    background: rgba(83, 197, 224, 0.12) !important;
    color: #d8edf5 !important;
    font-weight: 800;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    outline: none;
}
.qty-btn:hover {
    background: rgba(83, 197, 224, 0.28) !important;
    color: #fff !important;
}
.qty-btn:active {
    background: rgba(83, 197, 224, 0.4) !important;
}
.qty-control input {
    flex: 1;
    border: none;
    text-align: center;
    background: transparent !important;
    color: #f8fafc !important;
    font-weight: 700;
    font-size: 1rem;
    outline: none;
    -moz-appearance: textfield;
}
#quantity-input::-webkit-outer-spin-button,
#quantity-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
#quantity-input {
    -moz-appearance: textfield;
    appearance: textfield;
}

.placement-preview-area {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
}
.placement-card {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(83, 197, 224, 0.2) !important;
}
.placement-card:hover {
    background: rgba(83, 197, 224, 0.1) !important;
    border-color: rgba(83, 197, 224, 0.5) !important;
}
.placement-card:has(input:checked) {
    background: rgba(83, 197, 224, 0.18) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.2), 0 6px 16px rgba(11, 42, 56, 0.28) !important;
    transform: translateY(-1px);
}
.placement-img-wrap {
    background: rgba(255, 255, 255, 0.06) !important;
}
.placement-label {
    color: #cce2ed !important;
}

.tshirt-btn-secondary {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(83, 197, 224, 0.28) !important;
    color: #d9e6ef !important;
}
.tshirt-btn-secondary:hover {
    background: rgba(83, 197, 224, 0.14) !important;
    border-color: rgba(83, 197, 224, 0.52) !important;
}
.field-error {
    margin-top: .4rem;
    font-size: .75rem;
    color: #fca5a5;
    line-height: 1.3;
    display: block;
    width: 100%;
}
#tshirtForm .mb-4.is-invalid {
    border-color: rgba(239, 68, 68, 0.35) !important;
    box-shadow: none !important;
}
#tshirtForm .mb-4.is-invalid .input-field,
#tshirtForm .mb-4.is-invalid .qty-control {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
@media (max-width: 640px) {
    #tshirtForm .opt-btn-group { grid-template-columns: 1fr; }
    .option-grid-3x2, .option-grid-3x3 { grid-template-columns: repeat(2, 1fr); }
    .option-grid-color .opt-btn-others { grid-column: 1 / -1; justify-self: center; }
    .placement-grid { grid-template-columns: repeat(2, 1fr); }
    #tshirtForm .mb-4 {
        padding: .85rem;
    }
    .tshirt-actions-row {
        flex-direction: column;
        align-items: stretch;
    }
    .tshirt-btn {
        width: 100%;
    }
}
@media (max-width: 480px) {
    .lam-qty-row { flex-direction: column; align-items: stretch; }
    .lam-qty-qty { width: 100%; }
    .qty-control-shopee { width: 100%; }
    .lam-options { grid-template-columns: 1fr; }
    .need-qty-row { flex-direction: column; align-items: stretch; }
    .need-qty-qty { width: 100%; }
}
</style>

<script>
const TSHIRT_IS_EDIT_MODE = <?php echo $is_edit_mode ? 'true' : 'false'; ?>;
const TSHIRT_PREFILL = <?php echo json_encode([
    'branch_id' => (string)($_POST['branch_id'] ?? ''),
    'shirt_source' => (string)($_POST['shirt_source'] ?? ''),
    'design_type' => (string)($_POST['design_type'] ?? ''),
    'print_color' => (string)($_POST['print_color'] ?? ''),
    'text_content' => (string)($_POST['text_content'] ?? ''),
    'font_style' => (string)($_POST['font_style'] ?? ''),
    'font_size' => (string)($_POST['font_size'] ?? ''),
    'shirt_type' => (string)($_POST['shirt_type'] ?? ''),
    'shirt_type_other' => (string)($_POST['shirt_type_other'] ?? ''),
    'shirt_color' => (string)($_POST['shirt_color'] ?? ''),
    'color_other' => (string)($_POST['color_other'] ?? ''),
    'sizes' => (string)($_POST['sizes'] ?? ''),
    'sizes_other' => (string)($_POST['sizes_other'] ?? ''),
    'print_placement' => (string)($_POST['print_placement'] ?? ''),
    'lamination' => (string)($_POST['lamination'] ?? ''),
], JSON_UNESCAPED_UNICODE); ?>;

function tshirtSetRadio(name, value) {
    if (!value) return;
    const input = Array.from(document.querySelectorAll('input[name="' + name + '"]')).find(function(el) {
        return el.value === value;
    });
    if (input) {
        input.checked = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function tshirtIncreaseQty() {
    const i = document.getElementById('quantity-input');
    i.value = Math.min(999, (parseInt(i.value) || 1) + 1);
    checkFormValid();
}
function tshirtDecreaseQty() {
    const i = document.getElementById('quantity-input');
    const v = parseInt(i.value) || 1;
    if (v > 1) { i.value = v - 1; }
    checkFormValid();
}

function clearFieldError(container) {
    if (!container) return;
    const err = container.querySelector('.field-error');
    container.classList.remove('is-invalid');
    if (err) {
        err.textContent = '';
        err.style.display = 'none';
    }
}

function setFieldError(container, message) {
    if (!container) return;
    let err = container.querySelector('.field-error');
    if (!err) {
        err = document.createElement('div');
        err.className = 'field-error';
        container.appendChild(err);
    }
    if (message) {
        container.classList.add('is-invalid');
        err.textContent = message;
        err.style.display = 'block';
    } else {
        container.classList.remove('is-invalid');
        err.textContent = '';
        err.style.display = 'none';
    }
}

function checkFormValid() {
    const showErrors = window.__tshirtValidationTriggered === true;

    const branch = document.querySelector('select[name="branch_id"]');
    const shirtSource = document.querySelector('input[name="shirt_source"]:checked');
    const designType = document.querySelector('input[name="design_type"]:checked');
    const shirtType = document.querySelector('input[name="shirt_type"]:checked');
    const shirtColor = document.querySelector('input[name="shirt_color"]:checked');
    const placement = document.querySelector('input[name="print_placement"]:checked');
    const lamination = document.querySelector('input[name="lamination"]:checked');
    const qty = parseInt(document.getElementById('quantity-input').value) || 0;
    const file = document.getElementById('design_file');
    const neededDate = document.getElementById('needed_date');

    const textContent = document.getElementById('text_content');
    const printColor = document.getElementById('print_color');

    const cBranch = branch?.closest('.mb-4');
    const cShirtSource = document.querySelector('input[name="shirt_source"]')?.closest('.mb-4');
    const cDesignType = document.querySelector('input[name="design_type"]')?.closest('.mb-4');
    const cShirtType = document.querySelector('input[name="shirt_type"]')?.closest('.mb-4');
    const cShirtColor = document.querySelector('input[name="shirt_color"]')?.closest('.mb-4');
    const cPlacement = document.querySelector('input[name="print_placement"]')?.closest('.mb-4');
    const cFile = file?.closest('.mb-4');
    const cLamination = document.querySelector('input[name="lamination"]')?.closest('.lam-qty-lam');
    const cDate = neededDate?.closest('.mb-4');
    const cTextContent = textContent?.closest('.mb-4');
    const cPrintColor = printColor?.closest('.mb-4');

    const shopProvides = shirtSource && shirtSource.value === 'Shop provides shirt';
    const isTextOnly = designType && designType.value === 'Text Only';
    const isLogoOnly = designType && designType.value === 'Logo Only';
    const hasFile = !!(file && file.files && file.files.length > 0);

    let ok = !!branch?.value && !!shirtSource && !!designType && !!shirtType && !!shirtColor && !!placement && !!lamination && qty >= 1 && neededDate?.value.trim() !== '';
    
    // Conditional validation for Logo Only
    if (isLogoOnly && !TSHIRT_IS_EDIT_MODE) {
        ok = ok && hasFile;
    }
    
    // Conditional validation for Text Only
    if (isTextOnly) {
        ok = ok && !!textContent.value.trim() && !!printColor.value.trim();
    }

    if (showErrors) {
        setFieldError(cBranch, branch && !branch.value ? 'Please select a branch' : '');
        setFieldError(cShirtSource, !shirtSource ? 'Please select shirt source' : '');
        setFieldError(cDesignType, !designType ? 'Please select design type' : '');
        setFieldError(cShirtType, !shirtType ? 'Please select shirt type' : '');
        setFieldError(cShirtColor, !shirtColor ? 'Please select shirt color' : '');
        setFieldError(cPlacement, !placement ? 'Please select placement' : '');
        setFieldError(cFile, (isLogoOnly && !TSHIRT_IS_EDIT_MODE && !hasFile) ? 'Please upload your design for logo printing' : '');
        setFieldError(cLamination, !lamination ? 'Please select lamination' : '');
        setFieldError(cDate, !neededDate.value.trim() ? 'Please select a date' : '');
        
        if (isTextOnly) {
            setFieldError(cTextContent, !textContent.value.trim() ? 'Text content is required for text designs' : '');
            setFieldError(cPrintColor, !printColor.value.trim() ? 'Print color is required for text designs' : '');
        } else {
            clearFieldError(cTextContent);
            clearFieldError(cPrintColor);
        }
    } else {
        [cBranch, cShirtSource, cDesignType, cShirtType, cShirtColor, cPlacement, cFile, cLamination, cDate, cTextContent, cPrintColor].forEach(clearFieldError);
    }

    return ok;
}

// ── Event Listeners ──
document.getElementById('tshirtForm').addEventListener('change', checkFormValid);
document.getElementById('tshirtForm').addEventListener('input', checkFormValid);
document.getElementById('design_file').addEventListener('change', checkFormValid);
document.getElementById('quantity-input').addEventListener('input', checkFormValid);
document.getElementById('needed_date').addEventListener('change', checkFormValid);

document.querySelectorAll('input[name="design_type"]').forEach(r => {
    r.addEventListener('change', function() {
        const isLogo = this.value === 'Logo Only';
        const isText = this.value === 'Text Only';
        const uploadLabel = document.getElementById('upload-label');
        const fileInput = document.getElementById('design_file');
        const textSection = document.getElementById('text-design-section');
        
        // Toggle Upload Section Label/Requirement
        if (isLogo) {
            uploadLabel.textContent = 'Upload Design * (Required for logo printing)';
            if (!TSHIRT_IS_EDIT_MODE) fileInput.required = true;
        } else {
            uploadLabel.textContent = 'Upload Design (Optional - for reference only)';
            fileInput.required = false;
        }

        // Toggle Text Section
        textSection.style.display = isText ? 'block' : 'none';
        
        checkFormValid();
    });
});

document.querySelectorAll('input[name="shirt_source"]').forEach(r => {
    r.addEventListener('change', function() {
        const shopProvides = this.value === 'Shop provides shirt';
        document.getElementById('shop-provides-note').style.display = shopProvides ? 'block' : 'none';
        document.getElementById('size-section').style.display = shopProvides ? 'block' : 'none';
        document.querySelectorAll('input[name="sizes"]').forEach(s => { 
            s.required = shopProvides;
            if (!shopProvides) s.checked = false; 
        });
        document.getElementById('size-required-mark').textContent = shopProvides ? '*' : '';
    });
});

document.querySelectorAll('input[name="sizes"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('sizes-other-wrap').style.display = this.value === 'Others' ? 'block' : 'none';
    });
});

document.querySelectorAll('input[name="shirt_type"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('shirt-type-other-wrap').style.display = this.value === 'Others' ? 'block' : 'none';
    });
});

document.querySelectorAll('input[name="shirt_color"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('color-other-wrap').style.display = this.value === 'Other' ? 'block' : 'none';
    });
});

document.getElementById('tshirtForm').addEventListener('submit', function(e) {
    window.__tshirtValidationTriggered = true;
    if (!checkFormValid()) {
        e.preventDefault();
        const firstErr = document.querySelector('.is-invalid');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

// Prefill values
if (TSHIRT_PREFILL.text_content) document.getElementById('text_content').value = TSHIRT_PREFILL.text_content;
if (TSHIRT_PREFILL.print_color) document.getElementById('print_color').value = TSHIRT_PREFILL.print_color;
if (TSHIRT_PREFILL.font_style) document.getElementById('font_style').value = TSHIRT_PREFILL.font_style;
if (TSHIRT_PREFILL.font_size) document.getElementById('font_size').value = TSHIRT_PREFILL.font_size;
if (TSHIRT_PREFILL.shirt_type_other) document.getElementById('shirt_type_other').value = TSHIRT_PREFILL.shirt_type_other;
if (TSHIRT_PREFILL.color_other) document.getElementById('color_other').value = TSHIRT_PREFILL.color_other;
if (TSHIRT_PREFILL.sizes_other) document.getElementById('sizes_other').value = TSHIRT_PREFILL.sizes_other;

tshirtSetRadio('branch_id', TSHIRT_PREFILL.branch_id);
tshirtSetRadio('shirt_source', TSHIRT_PREFILL.shirt_source);
tshirtSetRadio('design_type', TSHIRT_PREFILL.design_type);
tshirtSetRadio('shirt_type', TSHIRT_PREFILL.shirt_type);
tshirtSetRadio('shirt_color', TSHIRT_PREFILL.shirt_color);
tshirtSetRadio('sizes', TSHIRT_PREFILL.sizes);
tshirtSetRadio('print_placement', TSHIRT_PREFILL.print_placement);
tshirtSetRadio('lamination', TSHIRT_PREFILL.lamination);

checkFormValid();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
