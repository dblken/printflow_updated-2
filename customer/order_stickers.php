<?php
/**
 * Decals / Stickers - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $shape = trim($_POST['shape'] ?? 'Custom'); // Shape hidden for now; default
    $w_in = trim((string)($_POST['width_in'] ?? ''));
    $h_in = trim((string)($_POST['height_in'] ?? ''));
    $size = ($w_in !== '' && $h_in !== '') ? ($w_in . 'x' . $h_in) : '';
    $finish = trim($_POST['finish'] ?? '');
    $laminate_option = trim($_POST['laminate_option'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    if (!in_array($finish, ['Glossy', 'Matte'], true)) {
        $finish = 'Glossy';
    }
    if (!in_array($laminate_option, ['With Laminate', 'Without Laminate'], true)) {
        $laminate_option = 'Without Laminate';
    }
    if (!in_array($layout, ['With Layout', 'Without Layout'], true)) {
        $layout = '';
    }
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = max(1, min(999, (int)($_POST['quantity'] ?? 1)));
    $notes = trim($_POST['notes'] ?? '');

    if ($w_in === '' || $h_in === '' || empty($needed_date) || $quantity < 1 || empty($layout)) {
        $error = 'Please fill in all required fields including layout.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'stickers_' . time() . '_' . rand(100, 999);
            
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Decals / Stickers',
                    'price' => 50.00, // Base price per cut/set
                    'quantity' => $quantity,
                    'category' => 'Decals & Stickers',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'shape' => $shape,
                        'size' => $size,
                        'finish' => $finish,
                        'laminate_option' => $laminate_option,
                        'layout' => $layout,
                        'needed_date' => $needed_date,
                        'notes' => $notes
                    ]
                ];
                
                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                    redirect("order_review.php?item=" . urlencode($item_key));
                } else {
                    redirect("cart.php");
                }
            } else {
                $error = 'Failed to process uploaded file.';
            }
        }
    }
}

$page_title = 'Order Stickers - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$stickers_finish_val = $_POST['finish'] ?? '';
if ($stickers_finish_val !== '' && !in_array($stickers_finish_val, ['Glossy', 'Matte'], true)) {
    $stickers_finish_val = 'Glossy';
}
$stickers_lam_val = $_POST['laminate_option'] ?? '';
if ($stickers_lam_val !== '' && !in_array($stickers_lam_val, ['With Laminate', 'Without Laminate'], true)) {
    $stickers_lam_val = 'Without Laminate';
}
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 stickers-order-container">
        <h1 class="text-2xl font-bold mb-6 stickers-page-title">Decals / Stickers</h1>
        <?php if ($error): ?><div class="stickers-form-error mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card">
            <form action="" method="POST" enctype="multipart/form-data" id="stickersForm" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="shape" value="Custom">
                
                <div class="mb-4" id="card-branch-stickers">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" id="stickers_branch_id" class="input-field stickers-input-h" required>
                        <option value="" selected disabled>Select Branch</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4" id="card-dim-stickers">
                    <label class="block text-sm font-medium text-gray-700 mb-1 stickers-dim-label-oneline">Dimensions * <span class="stickers-dim-note">All values are in inches</span></label>
                    <div class="stickers-dim-row">
                        <div>
                            <label class="stickers-dim-field-label" for="stickers_width_in">Width (in.)</label>
                            <input type="text" name="width_in" id="stickers_width_in" class="input-field stickers-input-h" inputmode="decimal" autocomplete="off" required placeholder="e.g. 10" value="<?php echo htmlspecialchars($_POST['width_in'] ?? ''); ?>">
                        </div>
                        <div class="stickers-dim-sep" aria-hidden="true">×</div>
                        <div>
                            <label class="stickers-dim-field-label" for="stickers_height_in">Height (in.)</label>
                            <input type="text" name="height_in" id="stickers_height_in" class="input-field stickers-input-h" inputmode="decimal" autocomplete="off" required placeholder="e.g. 12" value="<?php echo htmlspecialchars($_POST['height_in'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-4" id="card-finish-stickers">
                    <label class="block text-sm font-medium text-gray-700 mb-1 stickers-finish-label"><span>Finish * <button type="button" class="stickers-info-btn" onclick="openFinishInfo()" aria-label="Finish info">i</button></span></label>
                    <div class="opt-btn-group opt-btn-inline opt-btn-expand">
                        <label class="opt-btn-wrap"><input type="radio" name="finish" value="Glossy" required <?php echo $stickers_finish_val === 'Glossy' ? 'checked' : ''; ?>> <span>Glossy</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="finish" value="Matte" <?php echo $stickers_finish_val === 'Matte' ? 'checked' : ''; ?>> <span>Matte</span></label>
                    </div>
                </div>

                <div class="mb-4" id="card-laminate-stickers">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Laminate *</label>
                    <div class="opt-btn-group opt-btn-inline opt-btn-expand">
                        <label class="opt-btn-wrap"><input type="radio" name="laminate_option" value="With Laminate" required <?php echo $stickers_lam_val === 'With Laminate' ? 'checked' : ''; ?>> <span>With Laminate</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="laminate_option" value="Without Laminate" <?php echo $stickers_lam_val === 'Without Laminate' ? 'checked' : ''; ?>> <span>Without Laminate</span></label>
                    </div>
                </div>

                <div class="mb-4" id="card-layout-stickers">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Layout *</label>
                    <div class="opt-btn-group opt-btn-inline opt-btn-expand">
                        <label class="opt-btn-wrap"><input type="radio" name="layout" value="With Layout" required> <span>With Layout</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="layout" value="Without Layout"> <span>Without Layout</span></label>
                    </div>
                </div>

                <div class="mb-4 need-qty-card stickers-need-qty-card" id="card-date-qty-stickers">
                    <div class="need-qty-row">
                        <div class="need-qty-date" style="min-width:0;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="stickers_needed_date" class="input-field stickers-input-h stickers-date-full" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>">
                        </div>
                        <div class="need-qty-qty" style="min-width:0;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="sticker-qty-stepper sticker-qty-stepper-wide">
                                <button type="button" onclick="stickerQtyDown()">−</button>
                                <input type="number" id="sticker-qty" name="quantity" min="1" max="999" required value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>" oninput="stickerQtyClamp()">
                                <button type="button" onclick="stickerQtyUp()">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4" id="card-upload-stickers">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Your File (Design, Image, or PDF) – Max 5MB *</label>
                    <input type="file" name="design_file" id="stickers_design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field stickers-input-h stickers-file-input" required>
                </div>

                <div class="mb-4 stickers-notes-wrap" id="card-notes-stickers">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field stickers-notes" placeholder="Any special instructions..." maxlength="500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="tshirt-actions-row">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                    <button type="submit" name="action" value="add_to_cart" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                    <button type="submit" name="action" value="buy_now" id="stickersBuyNowBtn" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stickers-order-container { max-width: 640px; }
.stickers-page-title { color: #eaf6fb !important; }
.stickers-form-error {
    padding: 0.85rem 1rem;
    border-radius: 10px;
    border: 1px solid rgba(248, 113, 113, 0.45);
    background: rgba(127, 29, 29, 0.25);
    color: #fecaca !important;
    font-size: 0.875rem;
    font-weight: 600;
}
.stickers-finish-label { display: flex; align-items: center; flex-wrap: wrap; gap: 0.25rem; }
.stickers-dim-label-oneline { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem; }
.stickers-dim-note { font-size: 0.75rem; font-weight: 500; color: #9fc6d9; }
.stickers-dim-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 1.2ch minmax(0, 1fr);
    align-items: end;
    column-gap: 0.3rem;
    row-gap: 0;
    width: 100%;
}
.stickers-dim-row > div:first-child,
.stickers-dim-row > div:last-child { width: 100%; min-width: 0; }
.stickers-dim-field-label {
    font-size: 0.75rem; color: #9fc6d9; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;
}
.stickers-dim-sep {
    display: inline-flex; align-items: center; justify-content: center;
    align-self: end; width: 1.2ch; min-width: 0; height: 42px;
    font-size: 1.15rem; font-weight: 700; color: #9fc6d9;
}
.stickers-need-qty-card .need-qty-row { display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
.stickers-need-qty-card .need-qty-date { flex: 1; min-width: 0; }
.stickers-need-qty-card .need-qty-qty { flex: 1; min-width: 0; }
.stickers-need-qty-card .need-qty-qty .sticker-qty-stepper-wide { width: 100%; max-width: 100%; }
.stickers-need-qty-card .need-qty-qty .sticker-qty-stepper-wide input { max-width: none; flex: 1; }
.stickers-input-h { height: 42px; padding-top: 0; padding-bottom: 0; }
.stickers-date-full { width: 100%; }
.stickers-file-input { padding-top: 8px !important; height: auto !important; min-height: 42px; }
.stickers-info-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 18px; height: 18px; border-radius: 50%; border: 1px solid rgba(83, 197, 224, 0.45);
    background: rgba(83, 197, 224, 0.12); color: #d8edf5; font-size: 11px; font-weight: 700;
    cursor: pointer; vertical-align: middle; margin-left: 4px;
}
.stickers-info-btn:hover { background: rgba(83, 197, 224, 0.22); border-color: #53c5e0; color: #fff; }
.sticker-qty-stepper {
    display: inline-flex; align-items: center; width: 110px; height: 42px;
    border: 1px solid rgba(83, 197, 224, 0.24); border-radius: 10px; overflow: hidden;
    background: rgba(13, 43, 56, 0.92); box-sizing: border-box;
}
.sticker-qty-stepper * { box-sizing: border-box; }
.sticker-qty-stepper button {
    flex: 0 0 36px; height: 42px; border: none;
    background: rgba(83, 197, 224, 0.12); color: #d8edf5;
    font-size: 1rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.sticker-qty-stepper button:hover { background: rgba(83, 197, 224, 0.22); }
.sticker-qty-stepper input {
    flex: 1; min-width: 36px; border: none; text-align: center;
    font-weight: 700; font-size: 0.875rem; outline: none;
    background: rgba(13, 43, 56, 0.92); color: #f8fafc; padding: 0 4px; height: 42px;
}
#sticker-qty::-webkit-outer-spin-button,
#sticker-qty::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
#sticker-qty { -moz-appearance: textfield; appearance: textfield; }

#stickersForm {
    display: flex; flex-direction: column; gap: 1rem;
    color-scheme: dark;
}
#stickersForm .mb-4 {
    margin-bottom: 0 !important;
    padding: 1rem;
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
#stickersForm label.block,
#stickersForm .stickers-finish-label {
    font-size: .95rem !important; font-weight: 700 !important; color: #d9e6ef !important; margin-bottom: .55rem !important;
}
#stickersForm input[type="radio"] { accent-color: #53c5e0; }
.opt-btn-inline { display: inline-flex !important; gap: 0.5rem; flex-wrap: nowrap; width: 100%; }
.opt-btn-group.opt-btn-inline { flex-wrap: nowrap !important; }
.opt-btn-expand .opt-btn-wrap { flex: 1 1 0; min-width: 0; }
#stickersForm .opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
#stickersForm .opt-btn-wrap {
    min-height: 44px; padding: 0.65rem 1rem; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 10px; cursor: pointer; font-weight: 600; font-size: .86rem; white-space: nowrap;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #d6eaf3 !important;
    box-sizing: border-box;
}
#stickersForm .opt-btn-wrap:hover {
    background: rgba(18, 56, 72, 0.95) !important;
    border-color: rgba(83, 197, 224, 0.48) !important;
}
#stickersForm .opt-btn-wrap:has(input:checked) {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.24), rgba(50, 161, 196, 0.22)) !important;
    border-color: #53c5e0 !important;
    color: #f5fcff !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.2) !important;
}
#stickersForm .opt-btn-wrap input { margin-right: 0.4rem; }
.field-error {
    margin-top: .4rem; font-size: .75rem; color: #fca5a5; line-height: 1.3;
    display: block; width: 100%;
}
#stickersForm .mb-4.is-invalid,
#stickersForm .need-qty-card.is-invalid {
    border-color: rgba(239, 68, 68, 0.35) !important;
    box-shadow: none !important;
}
#stickersForm .mb-4.is-invalid .input-field,
#stickersForm .need-qty-card.is-invalid .input-field {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#stickersForm .mb-4.is-invalid .opt-btn-wrap {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#stickersForm .need-qty-card.is-invalid .sticker-qty-stepper {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#stickersForm .input-field {
    min-height: 44px; padding: .72rem .9rem; border-radius: 10px; font-size: .95rem;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #e9f6fb !important; box-shadow: none !important;
}
#stickersForm .input-field.stickers-input-h:not(.stickers-file-input) {
    min-height: 42px !important;
    height: 42px !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}
#stickersForm .input-field::placeholder { color: #a9c1cd !important; }
#stickersForm .input-field:focus,
#stickersForm .input-field:focus-visible {
    background: rgba(16, 52, 67, 0.98) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
    outline: none !important;
}
#stickersForm select.input-field option { background: #0a2530 !important; color: #f8fafc !important; }
#stickersForm select.input-field option:hover,
#stickersForm select.input-field option:focus { background: #53c5e0 !important; color: #06232c !important; }
#stickersForm select.input-field option:checked { background: #53c5e0 !important; color: #06232c !important; }
#stickersForm .input-field[type="date"]::-webkit-calendar-picker-indicator {
    filter: brightness(0) invert(1);
    opacity: 1;
    cursor: pointer;
}
.stickers-notes-wrap { max-width: 100%; overflow: hidden; }
.stickers-notes {
    width: 100%; max-width: 100%; box-sizing: border-box;
    overflow-y: auto; resize: vertical; min-height: 110px; max-height: 220px;
    scrollbar-gutter: stable; scrollbar-width: thin;
    scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08);
}
.stickers-notes::-webkit-scrollbar { width: 10px; }
.stickers-notes::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.08); border-radius: 999px; }
.stickers-notes::-webkit-scrollbar-thumb {
    background: rgba(83, 197, 224, 0.65); border-radius: 999px;
    border: 2px solid rgba(10, 37, 48, 0.55);
}
.stickers-notes::-webkit-scrollbar-thumb:hover { background: rgba(83, 197, 224, 0.85); }

.tshirt-actions-row {
    display: flex; justify-content: flex-end; align-items: center; gap: .75rem;
    margin-top: 1.1rem; flex-wrap: wrap;
}
.tshirt-btn {
    height: 46px; min-width: 150px; padding: 0 1.15rem;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 10px; text-decoration: none; font-size: .9rem; font-weight: 700; transition: all .2s;
}
.tshirt-btn-secondary {
    background: rgba(255,255,255,.05) !important; border: 1px solid rgba(83, 197, 224, .28) !important; color: #d9e6ef !important;
}
.tshirt-btn-secondary:hover {
    background: rgba(83,197,224,.14) !important; border-color: rgba(83,197,224,.52) !important; color: #fff !important;
}
.tshirt-btn-primary {
    border: none; background: linear-gradient(135deg, #53C5E0, #32a1c4) !important; color: #fff !important;
    text-transform: uppercase; letter-spacing: .02em; cursor: pointer;
    box-shadow: 0 10px 22px rgba(50,161,196,0.3);
}
.tshirt-btn:active { transform: translateY(1px) scale(0.99); }

@media (max-width: 640px) {
    #stickersForm .opt-btn-group.opt-btn-inline { flex-wrap: wrap !important; }
    .stickers-dim-row { grid-template-columns: 1fr; }
    .stickers-dim-sep { height: auto; justify-self: center; padding: 0.25rem 0; }
    .stickers-need-qty-card .need-qty-row { flex-direction: column; align-items: stretch; }
    .tshirt-actions-row { flex-direction: column; align-items: stretch; }
    .tshirt-btn { width: 100%; }
}
</style>
<div id="finishInfoModal" class="stickers-finish-modal" style="display:none; position:fixed; inset:0; z-index:99999; align-items:center; justify-content:center; padding:1rem;">
    <div onclick="closeFinishInfo()" style="position:absolute; inset:0; background:rgba(2,12,18,0.72);"></div>
    <div class="stickers-finish-modal-inner" style="position:relative; background:#0a2530; border:1px solid rgba(83,197,224,0.28); border-radius:14px; width:min(920px, 96vw); max-height:90vh; overflow:auto; padding:1rem 1rem 1.25rem;">
        <button type="button" onclick="closeFinishInfo()" class="stickers-modal-close" style="position:absolute; top:8px; right:8px; border:1px solid rgba(83,197,224,0.28); background:rgba(15,53,68,0.95); border-radius:999px; width:30px; height:30px; cursor:pointer; color:#d8edf5;">×</button>
        <h3 style="font-size:1.1rem; font-weight:800; margin:0 0 0.8rem 0; color:#eaf6fb;">Finish Type Guide</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:0.9rem;">
            <div style="border:1px solid rgba(83,197,224,0.24); border-radius:12px; padding:0.8rem; background:rgba(13,43,56,0.6);">
                <div style="font-weight:800; margin-bottom:0.5rem; color:#eaf6fb;">Matte Finish</div>
                <img src="/printflow/public/images/products/product_21.jpg" alt="Matte sample" style="width:100%; height:160px; object-fit:cover; border-radius:10px; margin-bottom:0.6rem;">
                <ul style="margin:0; padding-left:1.05rem; font-size:0.86rem; color:#c2deea; line-height:1.5;">
                    <li>Non-shiny surface</li><li>Smooth and elegant appearance</li><li>Reduces glare and reflections</li><li>Ideal for minimalist, professional, or premium designs</li><li>Easier to read under strong lighting</li>
                </ul>
            </div>
            <div style="border:1px solid rgba(83,197,224,0.24); border-radius:12px; padding:0.8rem; background:rgba(13,43,56,0.6);">
                <div style="font-weight:800; margin-bottom:0.5rem; color:#eaf6fb;">Glossy Finish</div>
                <img src="/printflow/public/images/products/product_26.jpg" alt="Glossy sample" style="width:100%; height:160px; object-fit:cover; border-radius:10px; margin-bottom:0.6rem;">
                <ul style="margin:0; padding-left:1.05rem; font-size:0.86rem; color:#c2deea; line-height:1.5;">
                    <li>Shiny and reflective surface</li><li>Colors appear more vibrant and bright</li><li>Eye-catching and smooth texture</li><li>Best for colorful designs, logos, and photos</li><li>Reflects light and gives a polished look</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function openFinishInfo() {
    document.getElementById('finishInfoModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeFinishInfo() {
    document.getElementById('finishInfoModal').style.display = 'none';
    document.body.style.overflow = '';
}
['stickers_width_in', 'stickers_height_in'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').slice(0, 8);
            if (window.__stickersValidationTriggered) checkStickersFormValid();
        });
    }
});

function clearStickersFieldError(container) {
    if (!container) return;
    var err = container.querySelector('.field-error');
    container.classList.remove('is-invalid');
    if (err) { err.textContent = ''; err.style.display = 'none'; }
}
function setStickersFieldError(container, message) {
    if (!container) return;
    var err = container.querySelector('.field-error');
    if (message) {
        if (!err) { err = document.createElement('div'); err.className = 'field-error'; container.appendChild(err); }
        container.classList.add('is-invalid');
        err.textContent = message;
        err.style.display = 'block';
    } else {
        container.classList.remove('is-invalid');
        if (err) { err.textContent = ''; err.style.display = 'none'; }
    }
}
function checkStickersFormValid() {
    var show = window.__stickersValidationTriggered === true;
    var branch = document.getElementById('stickers_branch_id');
    var w = document.getElementById('stickers_width_in');
    var h = document.getElementById('stickers_height_in');
    var finish = document.querySelector('input[name="finish"]:checked');
    var lam = document.querySelector('input[name="laminate_option"]:checked');
    var nd = document.getElementById('stickers_needed_date');
    var qty = parseInt(document.getElementById('sticker-qty').value, 10) || 0;
    var file = document.getElementById('stickers_design_file');
    var notesEl = document.querySelector('#stickersForm textarea[name="notes"]');
    var notesLen = (notesEl && notesEl.value) ? notesEl.value.length : 0;

    var cBranch = document.getElementById('card-branch-stickers');
    var cDim = document.getElementById('card-dim-stickers');
    var cFinish = document.getElementById('card-finish-stickers');
    var cLam = document.getElementById('card-laminate-stickers');
    var cDateQty = document.getElementById('card-date-qty-stickers');
    var cUpload = document.getElementById('card-upload-stickers');
    var cNotes = document.getElementById('card-notes-stickers');

    var okBranch = branch && branch.value;
    var okDim = w && h && w.value.trim() !== '' && h.value.trim() !== '';
    var okFinish = !!finish;
    var okLam = !!lam;
    var okLayout = !!document.querySelector('input[name="layout"]:checked');
    var okDateQty = nd && nd.value.trim() !== '' && qty >= 1;
    var okFile = file && file.files && file.files.length > 0;
    var okNotes = notesLen <= 500;

    var cLayout = document.getElementById('card-layout-stickers');

    if (show) {
        setStickersFieldError(cBranch, !okBranch ? 'This field is required' : '');
        setStickersFieldError(cDim, !okDim ? 'This field is required' : '');
        setStickersFieldError(cFinish, !okFinish ? 'This field is required' : '');
        setStickersFieldError(cLam, !okLam ? 'This field is required' : '');
        setStickersFieldError(cLayout, !okLayout ? 'This field is required' : '');
        setStickersFieldError(cDateQty, !okDateQty ? 'This field is required' : '');
        setStickersFieldError(cUpload, !okFile ? 'This field is required' : '');
        setStickersFieldError(cNotes, !okNotes ? 'Notes must not exceed 500 characters' : '');
    } else {
        [cBranch, cDim, cFinish, cLam, cLayout, cDateQty, cUpload, cNotes].forEach(clearStickersFieldError);
    }
    return okBranch && okDim && okFinish && okLam && okLayout && okDateQty && okFile && okNotes;
}

var stickersFormEl = document.getElementById('stickersForm');
if (stickersFormEl) {
    stickersFormEl.addEventListener('submit', function(e) {
        window.__stickersValidationTriggered = true;
        if (!checkStickersFormValid()) {
            e.preventDefault();
            return false;
        }
    });
    stickersFormEl.addEventListener('change', checkStickersFormValid);
    stickersFormEl.addEventListener('input', checkStickersFormValid);
    stickersFormEl.addEventListener('invalid', function(e) { e.preventDefault(); }, true);
}
var stickersFileEl = document.getElementById('stickers_design_file');
if (stickersFileEl) stickersFileEl.addEventListener('change', checkStickersFormValid);
var sq = document.getElementById('sticker-qty');
if (sq) sq.addEventListener('input', function() { if (window.__stickersValidationTriggered) checkStickersFormValid(); });
function stickerQtyClamp() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10);
    if (!v || v < 1) v = 1;
    if (v > 999) v = 999;
    input.value = v;
    if (window.__stickersValidationTriggered) checkStickersFormValid();
}
function stickerQtyUp() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.min(v + 1, 999);
    if (window.__stickersValidationTriggered) checkStickersFormValid();
}
function stickerQtyDown() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.max(v - 1, 1);
    if (window.__stickersValidationTriggered) checkStickersFormValid();
}
checkStickersFormValid();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
