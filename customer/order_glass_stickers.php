<?php
/**
 * Glass & Wall Sticker Printing - Service Order Form
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';
$addr_api = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/api_address_public.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $width = trim($_POST['width'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $unit = trim($_POST['unit'] ?? 'ft');
    $surface_type = trim($_POST['surface_type'] ?? '');
    $surface_other = trim($_POST['surface_type_other'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $installation = trim($_POST['installation'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    $province = trim($_POST['install_province'] ?? '');
    $city = trim($_POST['install_city'] ?? '');
    $barangay = trim($_POST['install_barangay'] ?? '');
    $street = trim($_POST['install_street'] ?? '');

    $surface_display = ($surface_type === 'Others' && $surface_other) ? $surface_other : $surface_type;

    if (empty($width) || empty($height) || $quantity < 1 || empty($needed_date) || empty($surface_type) || empty($lamination) || empty($installation)) {
        $error = 'Please fill in all required fields marked with *.';
    } elseif ($installation === 'With Installation' && (empty($province) || empty($city) || empty($barangay) || empty($street))) {
        $error = 'Please complete the installation address.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'glass_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $w = (float)$width;
                $h = (float)$height;
                $area = $w * $h;
                if ($unit === 'in') $area = $area / 144;
                
                $unit_price = 45.00;
                $base_price = $area * $unit_price * $quantity;
                $installation_fee = ($installation === 'With Installation') ? (500 + ($area * 15)) : 0;

                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Glass & Wall Sticker Printing',
                    'price' => $base_price + $installation_fee,
                    'quantity' => $quantity,
                    'category' => 'Glass & Wall Sticker Printing',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'width' => $width,
                        'height' => $height,
                        'unit' => $unit,
                        'surface_type' => $surface_display,
                        'lamination' => $lamination,
                        'installation' => $installation,
                        'installation_fee' => $installation_fee,
                        'install_province' => $province,
                        'install_city' => $city,
                        'install_barangay' => $barangay,
                        'install_street' => $street,
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

$page_title = 'Order Glass & Wall Sticker - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 glass-order-container">
        <h1 class="text-2xl font-bold mb-6 glass-page-title">Glass & Wall Sticker Printing</h1>
        
        <?php if ($error): ?>
            <div class="glass-form-error mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card glass-form-card">
            <form action="" method="POST" enctype="multipart/form-data" id="glassForm" novalidate>
                <?php echo csrf_field(); ?>

                <!-- Branch -->
                <div class="mb-4" id="card-branch">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <option value="" selected disabled>Select Branch</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dimensions -->
                <div class="mb-4" id="card-dimensions">
                    <label class="block text-sm font-medium text-gray-700 mb-1 glass-dim-label-oneline">Dimensions (ft) * <span class="glass-dim-note">(All values are in feet)</span></label>
                    <div class="opt-btn-group dim-preset-row">
                        <button type="button" class="opt-btn" data-width="2" data-height="3" onclick="selectDimension(2, 3, event)">2×3</button>
                        <button type="button" class="opt-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)">4×6</button>
                        <button type="button" class="opt-btn" data-width="6" data-height="8" onclick="selectDimension(6, 8, event)">6×8</button>
                        <button type="button" class="opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                    </div>
                    <input type="hidden" name="width" id="width_hidden">
                    <input type="hidden" name="height" id="height_hidden">
                    <input type="hidden" name="unit" value="ft">
                    
                    <div id="dim-others-inputs" class="dim-others-row" style="display: none; margin-top: 1rem;">
                        <div class="dim-field">
                            <label class="dim-label">WIDTH (FT)</label>
                            <input type="text" inputmode="decimal" id="custom_width" class="input-field" placeholder="e.g. 5">
                        </div>
                        <div class="dim-sep">×</div>
                        <div class="dim-field">
                            <label class="dim-label">HEIGHT (FT)</label>
                            <input type="text" inputmode="decimal" id="custom_height" class="input-field" placeholder="e.g. 10">
                        </div>
                    </div>
                </div>

                <!-- Surface Type -->
                <div class="mb-4" id="card-surface">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Surface Type *</label>
                    <div class="option-grid-3x2">
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Glass (Window/Door/Storefront)" required onchange="toggleSurfaceOther()"> <span>Glass</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Wall (Painted/Concrete)" onchange="toggleSurfaceOther()"> <span>Wall</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Frosted Glass" onchange="toggleSurfaceOther()"> <span>Frosted Glass</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Mirror" onchange="toggleSurfaceOther()"> <span>Mirror</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Acrylic/Panel" onchange="toggleSurfaceOther()"> <span>Acrylic/Panel</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Others" onchange="toggleSurfaceOther()"> <span>Others</span></label>
                    </div>
                    <div id="surface-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="surface_type_other" id="surface_type_other" class="input-field" placeholder="Specify surface type...">
                    </div>
                </div>

                <!-- Lamination -->
                <div class="mb-4" id="card-lamination">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lamination *</label>
                    <div class="opt-btn-group opt-btn-expand">
                        <label class="opt-btn-wrap"><input type="radio" name="lamination" value="With Laminate" required> <span>With Laminate</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="lamination" value="Without Laminate"> <span>Without Laminate</span></label>
                    </div>
                </div>

                <!-- Installation -->
                <div class="mb-4" id="card-installation">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Installation *</label>
                    <div class="opt-btn-group opt-btn-expand">
                        <label class="opt-btn-wrap"><input type="radio" name="installation" value="With Installation" required onchange="toggleInstallationAddress(true)"> <span>With Installation</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="installation" value="Without Installation" onchange="toggleInstallationAddress(false)"> <span>Without Installation</span></label>
                    </div>
                </div>

                <!-- Installation Address (Conditional) -->
                <div id="install-address-section" style="display: none; margin-bottom: 1rem; padding: 1.25rem; background: rgba(8, 32, 42, 0.65); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 12px;">
                    <div class="address-notice mb-4">
                        <strong>Installation fee varies based on distance.</strong> A base fee is applied; final amount may be adjusted after location confirmation.
                    </div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Installation Address *</label>
                    <div class="space-y-4">
                        <div class="address-field">
                            <label>Province</label>
                            <select name="install_province" id="install_province" class="input-field">
                                <option value="">— Select Province —</option>
                            </select>
                        </div>
                        <div class="address-field">
                            <label>City / Municipality</label>
                            <select name="install_city" id="install_city" class="input-field" disabled>
                                <option value="">— Select City / Municipality —</option>
                            </select>
                        </div>
                        <div class="address-field">
                            <label>Barangay</label>
                            <select name="install_barangay" id="install_barangay" class="input-field" disabled>
                                <option value="">— Select Barangay —</option>
                            </select>
                        </div>
                        <div class="address-field">
                            <label>Street / Purok</label>
                            <input type="text" name="install_street" id="install_street" class="input-field" placeholder="Street name, Purok, etc.">
                        </div>
                    </div>
                </div>

                <!-- Needed Date + Quantity -->
                <div class="mb-4 need-qty-card" id="card-date-qty">
                    <div class="need-qty-row">
                        <div class="need-qty-date">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="need-qty-qty">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="qty-control group-focus-within">
                                <button type="button" class="qty-btn" onclick="decreaseQty()">−</button>
                                <input type="number" id="quantity-input" name="quantity" min="1" max="999" value="1" oninput="clampQty()">
                                <button type="button" class="qty-btn" onclick="increaseQty()">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Upload -->
                <div class="mb-4" id="card-upload">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field" placeholder="Any special instructions..." maxlength="500"></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="tshirt-actions-row">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                    <button type="submit" name="action" value="add_to_cart" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                    <button type="submit" name="action" value="buy_now" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Base Styles */
.glass-order-container { max-width: 640px; margin: 0 auto; }
.glass-page-title { color: #eaf6fb !important; }

.glass-form-card.card {
    background: rgba(10, 37, 48, 0.55);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 1.25rem;
    box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35);
    padding: 1.5rem;
}

#glassForm .mb-4 {
    margin-bottom: 0 !important;
    padding: 1rem;
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 12px;
    backdrop-filter: blur(4px);
    margin-top: 1rem;
}
#glassForm .mb-4:first-child { margin-top: 0; }

#glassForm label.block {
    font-size: .95rem; font-weight: 700; color: #d9e6ef; margin-bottom: .55rem; display: block;
}

.input-field {
    width: 100%; min-height: 44px; padding: .72rem .9rem; border-radius: 10px;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #e9f6fb !important; outline: none; transition: all 0.2s;
}
.input-field:focus { border-color: #53c5e0 !important; box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important; }

/* Dimensions Row */
.glass-dim-label-oneline { display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap; }
.glass-dim-note { font-size: 0.75rem; color: #9fc6d9; font-weight: 500; }
.dim-preset-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; }
.dim-others-row { display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.5rem; align-items: center; }
.dim-field label { display: block; font-size: 0.75rem; color: #9fc6d9; font-weight: 600; margin-bottom: 0.25rem; }
.dim-sep { color: #d2e7f1; font-weight: 700; font-size: 1.2rem; padding-top: 1.2rem; }

/* Option Grid (3x2) */
.option-grid-3x2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
@media (min-width: 480px) { .option-grid-3x2 { grid-template-columns: repeat(3, 1fr); } }

.opt-btn-group { display: flex; gap: 0.5rem; width: 100%; justify-content: center; }
.opt-btn-expand .opt-btn-wrap { flex: 1; }

.opt-btn, .opt-btn-wrap {
    min-height: 44px; padding: 0.65rem; border-radius: 10px; border: 1px solid rgba(83, 197, 224, 0.2);
    background: rgba(255, 255, 255, 0.04); color: #d2e7f1; font-weight: 500; font-size: 0.86rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.opt-btn:hover, .opt-btn-wrap:hover { background: rgba(83, 197, 224, 0.12); border-color: rgba(83, 197, 224, 0.5); }
.opt-btn.active, .opt-btn-wrap:has(input:checked) {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24));
    border-color: #53c5e0; color: #f8fcff;
}
.opt-btn-wrap input { margin-right: 0.5rem; }

/* Quantity Control */
.qty-control { display: flex; align-items: center; border: 1px solid rgba(83, 197, 224, 0.24); border-radius: 10px; overflow: hidden; background: rgba(13, 43, 56, 0.92); height: 44px; width: 100%; transition: border-color 0.2s, box-shadow 0.2s; }
.qty-control:focus-within { border-color: #53c5e0; box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16); }
.qty-btn { flex: 0 0 44px; height: 100%; border: none; background: rgba(83, 197, 224, 0.12); color: #d8edf5; font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; outline: none; }
.qty-btn:hover { background: rgba(83, 197, 224, 0.25); color: #fff; }
.qty-btn:active { background: rgba(83, 197, 224, 0.35); }
.qty-control input { flex: 1; border: none; text-align: center; background: transparent; color: #fff; font-weight: 700; font-size: 1rem; outline: none; -moz-appearance: textfield; }
.qty-control input::-webkit-inner-spin-button, .qty-control input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

.need-qty-row { display: flex; gap: 1rem; align-items: flex-start; }
.need-qty-date, .need-qty-qty { flex: 1; }

/* Address Section */
.address-notice { background: rgba(84, 56, 7, 0.35); border: 1px solid rgba(253, 230, 138, 0.45); color: #facc15; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem; }
.address-field label { display: block; font-size: 0.75rem; color: #9fc6d9; font-weight: 600; margin-bottom: 0.25rem; }

/* Error Styling */
.glass-form-error { background: rgba(127, 29, 29, 0.25); border: 1px solid rgba(248, 113, 113, 0.45); color: #fecaca; padding: 0.75rem; border-radius: 10px; font-weight: 600; font-size: 0.875rem; }

/* Actions */
.tshirt-actions-row { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.tshirt-btn { height: 46px; min-width: 150px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s; }
.tshirt-btn-secondary { background: rgba(255,255,255,0.05); color: #d9e6ef; border: 1px solid rgba(83,197,224,0.28); }
.tshirt-btn-primary { background: linear-gradient(135deg, #53c5e0, #32a1c4); color: #fff; text-transform: uppercase; }

@media (max-width: 640px) {
    .need-qty-row { flex-direction: column; }
    .tshirt-actions-row { flex-direction: column; }
    .tshirt-btn { width: 100%; }
}
</style>

<script>
const ADDR_API = '<?php echo $addr_api; ?>';
let dimensionMode = 'preset';

function selectDimension(w, h, e) {
    e.preventDefault();
    dimensionMode = 'preset';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    e.target.closest('.opt-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'none';
    document.getElementById('width_hidden').value = w;
    document.getElementById('height_hidden').value = h;
}

function selectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'grid';
    syncOthersDimensions();
}

function syncOthersDimensions() {
    document.getElementById('width_hidden').value = document.getElementById('custom_width').value;
    document.getElementById('height_hidden').value = document.getElementById('custom_height').value;
}

document.getElementById('custom_width').addEventListener('input', syncOthersDimensions);
document.getElementById('custom_height').addEventListener('input', syncOthersDimensions);

function toggleSurfaceOther() {
    const r = document.querySelector('input[name="surface_type"]:checked');
    document.getElementById('surface-other-wrap').style.display = (r && r.value === 'Others') ? 'block' : 'none';
}

function toggleInstallationAddress(show) {
    const sec = document.getElementById('install-address-section');
    sec.style.display = show ? 'block' : 'none';
    const fields = sec.querySelectorAll('.input-field');
    fields.forEach(f => {
        if (f.id !== 'install_province') f.disabled = !show;
        if (show) f.setAttribute('required', '');
        else f.removeAttribute('required');
    });
    if (show && document.getElementById('install_province').options.length <= 1) {
        loadProvinces();
    }
}

async function loadProvinces() {
    const p = document.getElementById('install_province');
    try {
        const r = await fetch(ADDR_API + '?address_action=provinces');
        const d = await r.json();
        if (d.success && d.data) {
            p.innerHTML = '<option value="">— Select Province —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
        }
    } catch(e) { console.error(e); }
}

document.getElementById('install_province').addEventListener('change', async function() {
    const city = document.getElementById('install_city');
    city.innerHTML = '<option value="">— Select City / Municipality —</option>';
    city.disabled = true;
    if (!this.value) return;
    try {
        const r = await fetch(ADDR_API + '?address_action=cities&province_code=' + this.value);
        const d = await r.json();
        if (d.success && d.data) {
            city.innerHTML = '<option value="">— Select City / Municipality —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
            city.disabled = false;
        }
    } catch(e) { console.error(e); }
});

document.getElementById('install_city').addEventListener('change', async function() {
    const b = document.getElementById('install_barangay');
    b.innerHTML = '<option value="">— Select Barangay —</option>';
    b.disabled = true;
    if (!this.value) return;
    try {
        const r = await fetch(ADDR_API + '?address_action=barangays&city_code=' + this.value);
        const d = await r.json();
        if (d.success && d.data) {
            b.innerHTML = '<option value="">— Select Barangay —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
            b.disabled = false;
        }
    } catch(e) { console.error(e); }
});

function decreaseQty() { const i = document.getElementById('quantity-input'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }
function increaseQty() { const i = document.getElementById('quantity-input'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function clampQty() { const i = document.getElementById('quantity-input'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }

document.getElementById('glassForm').addEventListener('submit', function(e) {
    if (dimensionMode === 'preset') {
        const active = document.querySelector('.opt-btn.active');
        if (!active) { alert('Please select a dimension.'); e.preventDefault(); return; }
    } else {
        if (!document.getElementById('custom_width').value || !document.getElementById('custom_height').value) {
            alert('Please enter custom dimensions.'); e.preventDefault(); return;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
