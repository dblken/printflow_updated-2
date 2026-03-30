<?php
/**
 * Transparent Stickers - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $surface_application = trim($_POST['surface_application'] ?? '');
    $surface_other = trim($_POST['surface_other'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');

    $surface_display = ($surface_application === 'Others' && $surface_other) ? $surface_other : $surface_application;

    if (empty($surface_display) || empty($dimensions) || empty($layout) || empty($lamination) || $quantity < 1 || empty($needed_date)) {
        $error = 'Please fill in Branch, Surface, Dimensions, Layout, Lamination, Quantity, and Needed Date.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($additional_notes) : strlen($additional_notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif ($surface_application === 'Others' && empty($surface_other)) {
        $error = 'Please specify your surface type when Others is selected.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design file.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'trans_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Transparent Sticker Printing',
                    'price' => 0, // Calculated at checkout or review
                    'quantity' => $quantity,
                    'category' => 'Sticker Printing',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'surface_application' => $surface_display,
                        'dimensions' => $dimensions,
                        'layout' => $layout,
                        'lamination' => $lamination,
                        'needed_date' => $needed_date,
                        'additional_notes' => $additional_notes
                    ]
                ];

                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                    redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
                } else {
                    redirect(BASE_URL . '/customer/cart.php');
                }
            } else {
                $error = 'Failed to process uploaded file.';
            }
        }
    }
}

$page_title = 'Order Transparent Stickers - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 trans-order-container">
        <h1 class="text-2xl font-bold mb-6 trans-page-title">Transparent Sticker Printing</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="card order-container">
            <form method="POST" enctype="multipart/form-data" id="transForm" novalidate>
                <?php echo csrf_field(); ?>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <option value="" selected disabled>Select Branch</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Where will the sticker be applied? *</label>
                    <div class="option-grid option-grid-3x2">
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Glass (Window/Door/Storefront)" required> <span>Glass</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Plastic / Acrylic" required> <span>Plastic/Acrylic</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Metal" required> <span>Metal</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Smooth Painted Wall" required> <span>Painted Wall</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Mirror" required> <span>Mirror</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Others" required> <span>Others</span></label>
                    </div>
                    <div id="surface-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="surface_other" id="surface_other" class="input-field" placeholder="Specify surface type">
                    </div>
                </div>

                <div class="mb-4" id="card-dimensions">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dimensions *</label>
                    <p class="dim-feet-note">Common sizes (in feet)</p>
                    <div class="option-grid option-grid-dim">
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="2x2" onclick="selectDimPreset('2x2', event)">2×2</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="3x3" onclick="selectDimPreset('3x3', event)">3×3</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="4x4" onclick="selectDimPreset('4x4', event)">4×4</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="2x3" onclick="selectDimPreset('2x3', event)">2×3</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="3x4" onclick="selectDimPreset('3x4', event)">3×4</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="4x6" onclick="selectDimPreset('4x6', event)">4×6</button>
                        <button type="button" class="opt-btn opt-btn-compact dim-others-btn" id="dim-others-btn" onclick="selectDimOthers(event)">Others</button>
                    </div>
                    <input type="hidden" name="dimensions" id="dimensions_hidden">
                    <div id="dim-others-inputs" class="dim-others-row" style="display: none; margin-top: 1rem;">
                        <div>
                            <label class="dim-label">Width (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_width" class="input-field" placeholder="e.g. 5">
                        </div>
                        <div class="dim-sep">×</div>
                        <div>
                            <label class="dim-label">Height (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_height" class="input-field" placeholder="e.g. 6">
                        </div>
                    </div>
                </div>

                <div class="mb-4" id="card-layout">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Layout *</label>
                    <div class="opt-btn-group opt-btn-compact-row">
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="layout" value="With Layout" required> <span>With Layout</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="layout" value="Without Layout" required> <span>Without Layout</span></label>
                    </div>
                </div>

                <div class="mb-4" id="card-lamination">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lamination *</label>
                    <div class="opt-btn-group opt-btn-compact-row">
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="lamination" value="With Laminate" required> <span>With Laminate</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="lamination" value="Without Laminate" required> <span>Without Laminate</span></label>
                    </div>
                </div>

                <div class="mb-4" id="card-upload">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" id="design_file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
                </div>

                <div class="mb-4 need-qty-card" id="card-date-qty">
                    <div class="need-qty-row">
                        <div class="need-qty-date" style="flex:1;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="need-qty-qty" style="flex:1;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="qty-control">
                                <button type="button" onclick="transDecreaseQty()" class="qty-btn">−</button>
                                <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                                <button type="button" onclick="transIncreaseQty()" class="qty-btn">&plus;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="additional_notes" rows="3" class="input-field" placeholder="Any special instructions..." maxlength="500"><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                </div>

                <div class="tshirt-actions-row">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                    <button type="submit" name="action" value="add_to_cart" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                    <button type="submit" name="action" value="buy_now" id="buyNowBtn" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.trans-order-container { max-width: 640px; }
.trans-page-title { color: #eaf6fb !important; }
.dim-feet-note { font-size: 0.75rem; color: #9fc6d9; margin-bottom: 0.5rem; }
.dim-label { font-size: 0.75rem; color: #9fc6d9; font-weight: 600; text-transform: uppercase; }
.dim-sep { color: #9fc6d9; font-weight: 700; align-self: end; height: 44px; display: flex; align-items: center; }
.dim-others-row { display: grid; grid-template-columns: minmax(0, 1fr) 1.2ch minmax(0, 1fr); align-items: end; column-gap: 0.3rem; }

#transForm .mb-4, #transForm .need-qty-card { padding: 1rem; background: rgba(10, 37, 48, 0.48); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 12px; backdrop-filter: blur(4px); }
#transForm label { font-size: .95rem !important; font-weight: 700 !important; color: #d9e6ef !important; margin-bottom: .55rem !important; }
#transForm .input-field { min-height: 44px; padding: .72rem .9rem; border-radius: 10px; background: rgba(13, 43, 56, 0.92) !important; border: 1px solid rgba(83, 197, 224, 0.26) !important; color: #e9f6fb !important; }

#transForm .option-grid { display: grid; gap: 0.4rem; }
#transForm .option-grid-3x2 { grid-template-columns: repeat(3, 1fr); }
#transForm .option-grid-dim { grid-template-columns: repeat(3, 1fr); }
#transForm .opt-btn, #transForm .opt-btn-wrap { background: rgba(13, 43, 56, 0.92) !important; border: 1px solid rgba(83, 197, 224, 0.26) !important; color: #d6eaf3 !important; padding: 0.4rem; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; }
#transForm .opt-btn-group { display: flex !important; flex-wrap: wrap !important; justify-content: center !important; gap: 0.5rem; }
#transForm .opt-btn-group .opt-btn-wrap { min-width: 120px; }
#transForm .opt-btn.active, #transForm .opt-btn-wrap:has(input:checked) { background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important; border-color: #53c5e0 !important; color: #f8fcff !important; }

.qty-control { display: flex; align-items: center; height: 44px; border: 1px solid rgba(83, 197, 224, 0.24); border-radius: 10px; overflow: hidden; background: rgba(13, 43, 56, 0.92); transition: border-color 0.2s, box-shadow 0.2s; }
.qty-control:focus-within { border-color: #53c5e0; box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16); }
.qty-btn { flex: 0 0 44px; height: 100%; border: none; background: rgba(83, 197, 224, 0.12); color: #d8edf5; font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; outline: none; }
.qty-btn:hover { background: rgba(83, 197, 224, 0.25); color: #fff; }
.qty-btn:active { background: rgba(83, 197, 224, 0.35); }
.qty-control input { flex: 1; min-width: 0; width: 50px; border: none; text-align: center; background: transparent; color: #fff; font-weight: 700; font-size: 1rem; outline: none; -moz-appearance: textfield; }
.qty-control input::-webkit-inner-spin-button, .qty-control input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

.need-qty-row { display: flex; gap: 1rem; align-items: flex-start; }
.need-qty-date, .need-qty-qty { flex: 1; min-width: 0; }

.tshirt-actions-row { display: flex; justify-content: flex-end; align-items: center; gap: .75rem; margin-top: 1.1rem; }
.tshirt-btn { height: 46px; min-width: 150px; padding: 0 1.15rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 700; text-decoration: none; }
.tshirt-btn-secondary { background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(83, 197, 224, 0.28) !important; color: #d9e6ef !important; }
.tshirt-btn-primary { background: linear-gradient(135deg, #53C5E0, #32a1c4); color: #fff; cursor: pointer; border: none; }

@media (max-width: 640px) {
    .option-grid-3x2 { grid-template-columns: repeat(2, 1fr); }
    .need-qty-row { flex-direction: column; align-items: stretch; }
    .tshirt-actions-row { flex-direction: column; align-items: stretch; }
}
</style>

<script>
let dimensionMode = 'preset';

function transIncreaseQty() { const i = document.getElementById('quantity-input'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function transDecreaseQty() { const i = document.getElementById('quantity-input'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }

function syncDimensions() {
    const h = document.getElementById('dimensions_hidden');
    if (dimensionMode === 'preset') {
        const btn = document.querySelector('.opt-btn.active');
        h.value = btn ? btn.dataset.dim : '';
    } else {
        const w = document.getElementById('custom_width').value.trim();
        const g = document.getElementById('custom_height').value.trim();
        h.value = (w && g) ? w + 'x' + g + ' ft' : '';
    }
}

function selectDimPreset(dim, e) {
    e.preventDefault(); dimensionMode = 'preset';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    e.target.closest('.opt-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'none';
    syncDimensions();
}

function selectDimOthers(e) {
    e.preventDefault(); dimensionMode = 'others';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'flex';
    syncDimensions();
}

document.querySelectorAll('input[name="surface_application"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('surface-other-wrap').style.display = this.value === 'Others' ? 'block' : 'none';
    });
});

document.getElementById('transForm').addEventListener('submit', function(e) {
    syncDimensions();
    if (!document.getElementById('dimensions_hidden').value) {
        alert('Please fill in Dimensions.'); e.preventDefault(); return false;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
