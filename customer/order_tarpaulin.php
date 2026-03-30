<?php
/**
 * Tarpaulin Printing - Service Order Form
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

// Pricelist image - check both possible locations
$base_path = __DIR__ . '/../public/';
$pricelist_url = null;
foreach (['images/tarp price range/', 'assets/images/tarp price range/'] as $subpath) {
    foreach (['.webp', '.jpg', '.jpeg', '.png'] as $ext) {
        if (file_exists($base_path . $subpath . 'pricelist' . $ext)) {
            $pricelist_url = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/' . $subpath . 'pricelist' . $ext;
            break 2;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $width = trim($_POST['width'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $unit = trim($_POST['unit'] ?? 'ft');
    $lamination = trim($_POST['lamination'] ?? '');
    $finish = trim($_POST['finish'] ?? '');
    $with_eyelets = trim($_POST['with_eyelets'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($width) || empty($height) || $quantity < 1) {
        $error = 'Please fill in Dimensions (Width, Height) and Quantity.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif (empty($needed_date)) {
        $error = 'Please select when you need the order.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'tarp_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $w = (float)$width;
                $h = (float)$height;
                $area = $w * $h;
                if ($unit === 'in') {
                    $area = $area / 144;
                }
                $unit_price = 20.00;
                
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Tarpaulin Printing',
                    'price' => $area * $unit_price * $quantity,
                    'quantity' => $quantity,
                    'category' => 'Tarpaulin',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'width' => $width,
                        'height' => $height,
                        'unit' => $unit,
                        'lamination' => $lamination,
                        'finish' => $finish,
                        'with_eyelets' => $with_eyelets,
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

$page_title = 'Order Tarpaulin - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 tarp-order-container">
        <h1 class="text-2xl font-bold mb-6 tarp-page-title">Tarpaulin Printing</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="mb-6">
            <button type="button" onclick="openPricelistModal()" class="view-pricelist-btn">
                View Price List
            </button>
        </div>

        <div class="card tarp-form-card">
            <form action="" method="POST" enctype="multipart/form-data" id="tarpForm" novalidate>
                <?php echo csrf_field(); ?>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <option value="" selected disabled>Select Branch</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4" id="card-dimensions">
                    <label class="block text-sm font-medium text-gray-700 mb-1 dim-label-oneline">Dimensions * <span class="dim-feet-note">(All values are in feet)</span></label>
                    <div class="opt-btn-group dim-preset-row">
                        <button type="button" class="opt-btn" data-width="3" data-height="4" onclick="selectDimension(3, 4, event)">3×4</button>
                        <button type="button" class="opt-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)">4×6</button>
                        <button type="button" class="opt-btn" data-width="5" data-height="8" onclick="selectDimension(5, 8, event)">5×8</button>
                        <button type="button" class="opt-btn" data-width="6" data-height="8" onclick="selectDimension(6, 8, event)">6×8</button>
                        <button type="button" class="opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                    </div>
                    <input type="hidden" name="width" id="width_hidden">
                    <input type="hidden" name="height" id="height_hidden">
                    <input type="hidden" name="unit" value="ft">
                    <div id="dim-others-inputs" class="dim-others-row" style="display: none;">
                        <div>
                            <label class="dim-label">Width (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_width" class="input-field" placeholder="e.g. 10">
                        </div>
                        <div class="dim-sep">×</div>
                        <div>
                            <label class="dim-label">Height (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_height" class="input-field" placeholder="e.g. 12">
                        </div>
                    </div>
                </div>

                <div class="tarp-option-cards-3">
                    <div class="mb-4 tarp-option-col" id="card-finish">
                        <label class="label-with-info">Finish Type * <span class="info-icon" id="finish-info-icon" tabindex="0" role="button" aria-label="Finish type info">ⓘ</span></label>
                        <div class="finish-tooltip" id="finish-tooltip" role="tooltip">
                            <div class="tooltip-row"><strong>Glossy:</strong> Shiny finish · More vibrant colors · Reflective under light</div>
                            <div class="tooltip-row"><strong>Matte:</strong> Non-reflective · Softer look · Better readability outdoors</div>
                        </div>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="finish" value="Matte" required> <span>Matte</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="finish" value="Glossy" required> <span>Glossy</span></label>
                        </div>
                    </div>

                    <div class="mb-4 tarp-option-col" id="card-lamination">
                        <label>Lamination *</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="lamination" value="With Laminate" required> <span>With Laminate</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="lamination" value="Without Laminate" required> <span>Without Laminate</span></label>
                        </div>
                    </div>

                    <div class="mb-4 tarp-option-col" id="card-eyelets">
                        <label>Eyelets *</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="with_eyelets" value="Yes" required> <span>Yes</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="with_eyelets" value="No" required> <span>No</span></label>
                        </div>
                    </div>
                </div>

                <div class="mb-4" id="card-upload">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
                </div>

                <div class="mb-4" id="card-layout">
                    <label>Layout *</label>
                    <div class="opt-btn-group opt-btn-inline opt-btn-expand">
                        <label class="opt-btn-wrap"><input type="radio" name="layout" value="With Layout" required> <span>With Layout</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="layout" value="Without Layout" required> <span>Without Layout</span></label>
                    </div>
                </div>

                <div class="mb-4 need-qty-card" id="card-date-qty">
                    <div class="need-qty-row">
                        <div class="tarp-option-col need-qty-date" style="flex:1;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="tarp-option-col tarp-qty-col need-qty-qty" style="flex:1;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="tarp-qty-stepper group-focus-within">
                                <button type="button" class="tarp-qty-btn" onclick="decreaseQty()">−</button>
                                <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                                <button type="button" class="tarp-qty-btn" onclick="increaseQty()">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 tarp-notes-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field tarp-notes" placeholder="Any special instructions..." maxlength="500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
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

<!-- Pricelist Modal -->
<div id="pricelist-modal" style="display: none; position: fixed; inset: 0; z-index: 99999; align-items: center; justify-content: center; padding: 1.5rem; background: rgba(0,0,0,0.5);">
    <div onclick="closePricelistModal()" style="position: absolute; inset: 0;"></div>
    <div style="position: relative; background: #0a2530; border: 1px solid rgba(83, 197, 224, 0.28); border-radius: 1rem; max-width: 72vw; max-height: 90vh; overflow: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.45);">
        <button onclick="closePricelistModal()" style="position: absolute; top: 0.75rem; right: 0.75rem; z-index: 10; width: 36px; height: 36px; border: 1px solid rgba(83, 197, 224, 0.28); background: rgba(15, 53, 68, 0.95); border-radius: 50%; cursor: pointer; font-size: 1.25rem; line-height: 1; color: #d8edf5;">×</button>
        <?php if ($pricelist_url): ?>
        <img src="<?php echo htmlspecialchars($pricelist_url); ?>" alt="Tarpaulin Pricelist" style="width: 100%; max-width: 640px; display: block;">
        <?php else: ?>
        <div style="padding: 3rem; text-align: center; color: #6b7280;">Pricelist image not found.</div>
        <?php endif; ?>
    </div>
</div>

<style>
.tarp-order-container { max-width: 640px; }
.tarp-page-title { color: #eaf6fb !important; }
.view-pricelist-btn { padding: 0.6rem 1.25rem; background: linear-gradient(135deg, #53C5E0, #32a1c4); color: #fff; font-weight: 700; border-radius: 8px; border: none; cursor: pointer; box-shadow: 0 6px 16px rgba(50,161,196,0.25); }

.dim-label-oneline { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem; }
.dim-feet-note { font-size: 0.75rem; font-weight: 500; color: #9fc6d9; }
.opt-btn-group.dim-preset-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem; margin-bottom: 1rem; }
.opt-btn-group.dim-preset-row #dim-others-btn { grid-column: 1 / -1; justify-self: center; width: calc(50% - 0.25rem); }
.dim-others-row { display: grid; grid-template-columns: minmax(0, 1fr) 1.2ch minmax(0, 1fr); align-items: end; column-gap: 0.3rem; }
.dim-label { font-size: 0.75rem; color: #9fc6d9; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 0.25rem; }
.dim-sep { font-size: 1.15rem; font-weight: 700; color: #9fc6d9; align-self: end; height: 44px; display: flex; align-items: center; }

#tarpForm .mb-4, #tarpForm .need-qty-card { padding: 1rem; background: rgba(10, 37, 48, 0.48); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 12px; backdrop-filter: blur(4px); }
#tarpForm label { font-size: .95rem !important; font-weight: 700 !important; color: #d9e6ef !important; margin-bottom: .55rem !important; }
#tarpForm .input-field { min-height: 44px; padding: .72rem .9rem; border-radius: 10px; background: rgba(13, 43, 56, 0.92) !important; border: 1px solid rgba(83, 197, 224, 0.26) !important; color: #e9f6fb !important; }
#tarpForm .opt-btn-group:not(.dim-preset-row) { display: flex !important; flex-wrap: wrap !important; justify-content: center !important; gap: 0.5rem; }
#tarpForm .opt-btn-expand .opt-btn-wrap { flex: 1 !important; }
#tarpForm .opt-btn, #tarpForm .opt-btn-wrap { background: rgba(13, 43, 56, 0.92) !important; border: 1px solid rgba(83, 197, 224, 0.26) !important; color: #d6eaf3 !important; padding: 0.65rem 1rem; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; min-width: 120px; }
#tarpForm .opt-btn.active, #tarpForm .opt-btn-wrap:has(input:checked) { background: linear-gradient(135deg, rgba(83, 197, 224, 0.24), rgba(50, 161, 196, 0.22)) !important; border-color: #53c5e0 !important; color: #f5fcff !important; }
#tarpForm .tarp-qty-stepper { display: flex; align-items: center; border: 1px solid rgba(83, 197, 224, 0.26); border-radius: 10px; overflow: hidden; height: 44px; transition: border-color 0.2s, box-shadow 0.2s; background: rgba(13, 43, 56, 0.92); }
#tarpForm .tarp-qty-stepper:focus-within { border-color: #53c5e0; box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16); }
#tarpForm .tarp-qty-btn { flex: 0 0 44px; height: 100%; border: none; background: rgba(83, 197, 224, 0.12); color: #d8edf5; font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; outline: none; }
#tarpForm .tarp-qty-btn:hover { background: rgba(83, 197, 224, 0.25); color: #fff; }
#tarpForm .tarp-qty-btn:active { background: rgba(83, 197, 224, 0.35); }
#tarpForm .tarp-qty-stepper input { flex: 1; border: none; text-align: center; background: transparent; color: #fff; font-weight: 700; width: 50px; outline: none; -moz-appearance: textfield; font-size: 1rem; }
#tarpForm .tarp-qty-stepper input::-webkit-inner-spin-button, #tarpForm .tarp-qty-stepper input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

.need-qty-row { display: flex; gap: 1rem; align-items: flex-start; }
.need-qty-date, .need-qty-qty { flex: 1; min-width: 0; }
.tshirt-actions-row { display: flex; justify-content: flex-end; align-items: center; gap: .75rem; margin-top: 1.1rem; }
.tshirt-btn { height: 46px; min-width: 150px; padding: 0 1.15rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 700; text-decoration: none; }
.tshirt-btn-secondary { background: rgba(255,255,255,.05) !important; border: 1px solid rgba(83, 197, 224, .28) !important; color: #d9e6ef !important; }
.tshirt-btn-primary { background: linear-gradient(135deg, #53C5E0, #32a1c4) !important; color: #fff !important; cursor: pointer; border: none; }

.label-with-info { display: inline-flex; align-items: center; gap: 0.35rem; }
.info-icon { opacity: 0.7; cursor: help; }
.finish-tooltip { display: none; position: absolute; z-index: 50; padding: 0.75rem; background: rgba(10, 37, 48, 0.98); border: 1px solid #53c5e0; border-radius: 8px; font-size: 0.8rem; color: #e2f2f8; max-width: 250px; }
.finish-tooltip.visible { display: block; }

@media (max-width: 640px) {
    .need-qty-row { flex-direction: column; align-items: stretch; }
    .tshirt-actions-row { flex-direction: column; align-items: stretch; }
}
</style>

<script>
let dimensionMode = 'preset';

function openPricelistModal() { document.getElementById('pricelist-modal').style.display = 'flex'; document.body.style.overflow = 'hidden'; }
function closePricelistModal() { document.getElementById('pricelist-modal').style.display = 'none'; document.body.style.overflow = ''; }

function syncDimensionToHidden() {
    const wh = document.getElementById('width_hidden');
    const hh = document.getElementById('height_hidden');
    if (dimensionMode === 'preset') {
        const btn = document.querySelector('.opt-btn.active');
        wh.value = btn ? btn.dataset.width : '';
        hh.value = btn ? btn.dataset.height : '';
    } else {
        wh.value = document.getElementById('custom_width').value;
        hh.value = document.getElementById('custom_height').value;
    }
}

function selectDimension(w, h, e) {
    e.preventDefault();
    dimensionMode = 'preset';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    e.target.closest('.opt-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'none';
    syncDimensionToHidden();
}

function selectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'flex';
    syncDimensionToHidden();
}

function increaseQty() { const i = document.getElementById('quantity-input'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function decreaseQty() { const i = document.getElementById('quantity-input'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }

document.getElementById('tarpForm').addEventListener('submit', function(e) {
    syncDimensionToHidden();
    if (!document.getElementById('width_hidden').value || !document.getElementById('height_hidden').value) {
        alert('Please fill in Dimensions.'); e.preventDefault(); return false;
    }
});

var finishIcon = document.getElementById('finish-info-icon');
var finishTooltip = document.getElementById('finish-tooltip');
if (finishIcon && finishTooltip) {
    finishIcon.addEventListener('mouseenter', () => finishTooltip.classList.add('visible'));
    finishIcon.addEventListener('mouseleave', () => finishTooltip.classList.remove('visible'));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
