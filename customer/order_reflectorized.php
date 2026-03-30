<?php
/**
 * Reflectorized (Subdivision Stickers / Signages) - Service Order Form
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$page_title = 'Order Reflectorized Signage - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");

// Standard reflectorized sizes (inches)
$dimension_presets = [
    '6 x 12' => ['w' => 6, 'h' => 12],
    '9 x 12' => ['w' => 9, 'h' => 12],
    '12 x 18' => ['w' => 12, 'h' => 18],
    '18 x 24' => ['w' => 18, 'h' => 24],
    '24 x 36' => ['w' => 24, 'h' => 36],
];
?>

<div class="min-h-screen py-8">
    <div class="refl-container">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Reflectorized Signage</h1>

        <div class="card refl-order-card">
            <form id="reflectorizedForm" class="refl-form" enctype="multipart/form-data" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="service_type" value="Reflectorized Signage">

                <div class="refl-main">
                    <!-- Branch -->
                    <div class="refl-field">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                        <select name="branch_id" class="input-field" required>
                            <option value="" selected disabled>Select Branch</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Product Type -->
                    <div class="refl-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type of Reflectorized Product *</label>
                        <select name="product_type" id="refl_product_type" class="input-field refl-select-btn" required onchange="reflToggleProductFields()">
                            <option value="" selected disabled>Select Type of Reflectorized Product</option>
                            <option value="Subdivision / Gate Pass (Vehicle Sticker)">Subdivision / Gate Pass (Vehicle Sticker)</option>
                            <option value="Plate Number / Temporary Plate">Plate Number / Temporary Plate</option>
                            <option value="Custom Reflectorized Sign">Custom Reflectorized Sign</option>
                        </select>
                    </div>

                    <!-- Temporary Plate Fields -->
                    <div class="refl-expand refl-tempPlateFields" style="display: none;">
                        <div class="refl-subsection mb-4">
                            <p class="refl-note mb-2">Please choose the material before proceeding.</p>
                            <div class="opt-btn-group">
                                <label class="opt-btn-wrap"><input type="radio" name="temp_plate_material" value="Acrylic" onchange="reflUpdateOptionVisuals(this)"> <span>Acrylic</span></label>
                                <label class="opt-btn-wrap"><input type="radio" name="temp_plate_material" value="Aluminum Sheet" onchange="reflUpdateOptionVisuals(this)"> <span>Aluminum Sheet</span></label>
                                <label class="opt-btn-wrap refl-temp-material-center"><input type="radio" name="temp_plate_material" value="Aluminum Coated (Steel Plate)" onchange="reflUpdateOptionVisuals(this)"> <span>Aluminum Coated (Steel)</span></label>
                            </div>
                        </div>
                        <div class="refl-grid-2 mt-3">
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Plate Number *</label><input type="text" name="temp_plate_number" id="temp_plate_number" class="input-field" placeholder="Must match OR/CR"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">TEMPORARY PLATE text</label><input type="text" name="temp_plate_text" class="input-field refl-readonly-fixed" value="TEMPORARY PLATE" readonly tabindex="-1"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">MV File Number</label><input type="text" name="mv_file_number" class="input-field" placeholder="Optional"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Dealer Name</label><input type="text" name="dealer_name" class="input-field" placeholder="Optional"></div>
                        </div>
                        <div id="reflNeedQtyCardTemp" class="refl-need-qty-card mt-3">
                            <div class="refl-need-qty-row">
                                <div class="refl-need-qty-date">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                                    <input type="date" id="needed_date_temp" class="input-field" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="refl-need-qty-qty">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <div class="refl-qty-stepper">
                                        <button type="button" onclick="reflQtyDownTemp()" class="refl-qty-btn">−</button>
                                        <input type="number" id="quantity_temp" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="refl-qty-input-inline" oninput="reflQtyClampTemp()">
                                        <button type="button" onclick="reflQtyUpTemp()" class="refl-qty-btn">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gate Pass Fields -->
                    <div class="refl-expand refl-gatePassFields" style="display: none;">
                        <div class="refl-grid-2">
                            <div class="refl-col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Subdivision / Company Name *</label><input type="text" name="gate_pass_subdivision" id="gate_pass_subdivision" class="input-field" placeholder="e.g. GREEN VALLEY SUBDIVISION"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Gate Pass Number *</label><input type="text" name="gate_pass_number" id="gate_pass_number" class="input-field" placeholder="e.g. GP-0215"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Plate Number *</label><input type="text" name="gate_pass_plate" id="gate_pass_plate" class="input-field" placeholder="e.g. ABC 1234"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Year / Validity *</label><input type="text" name="gate_pass_year" id="gate_pass_year" class="input-field" placeholder="e.g. VALID UNTIL: 2026"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Type</label><select name="gate_pass_vehicle_type" class="input-field"><option value="">Select</option><option value="Car">Car</option><option value="Motorcycle">Motorcycle</option></select></div>
                        </div>
                        <div class="refl-size-unit-card mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Exact Size *</label>
                            <div class="refl-size-split-row">
                                <div class="refl-size-split-field">
                                    <label class="refl-size-split-label" for="dimensions_gatepass_w">WIDTH (IN)</label>
                                    <input type="text" id="dimensions_gatepass_w" class="input-field" placeholder="e.g. 10">
                                </div>
                                <span class="refl-size-split-x" aria-hidden="true">×</span>
                                <div class="refl-size-split-field">
                                    <label class="refl-size-split-label" for="dimensions_gatepass_h">HEIGHT (IN)</label>
                                    <input type="text" id="dimensions_gatepass_h" class="input-field" placeholder="e.g. 12">
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                            <input type="file" name="gate_pass_logo" id="gate_pass_logo" accept=".jpg,.jpeg,.png,.pdf" class="input-field">
                        </div>
                        <div id="reflNeedQtyCard" class="refl-need-qty-card mt-3">
                            <div class="refl-need-qty-row">
                                <div class="refl-need-qty-date">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                                    <input type="date" id="needed_date" class="input-field" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="refl-need-qty-qty">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <div class="refl-qty-stepper">
                                        <button type="button" onclick="reflQtyDown()" class="refl-qty-btn">−</button>
                                        <input type="number" id="quantity" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="refl-qty-input-inline" oninput="reflQtyClamp()">
                                        <button type="button" onclick="reflQtyUp()" class="refl-qty-btn">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="reflNotesSection" class="refl-field" style="display:none;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="notes_shared" rows="2" class="input-field" placeholder="Any special requests?"></textarea>
                    </div>

                    <!-- Custom Reflectorized Sign Section -->
                    <div id="reflCustomSection" class="refl-custom-block" style="display: none;">
                        <div class="refl-custom-inner">
                            <div class="refl-field refl-custom-card">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Dimensions *</label>
                                <p class="refl-hint mb-2">Select a standard size or enter custom dimensions.</p>
                                <div class="opt-btn-group refl-dim-presets" id="reflDimPresets">
                                    <?php foreach($dimension_presets as $label => $d): ?>
                                    <label class="opt-btn-wrap refl-dim-btn" data-w="<?php echo $d['w']; ?>" data-h="<?php echo $d['h']; ?>">
                                        <input type="radio" name="dimension_preset" value="<?php echo $label; ?>" onchange="reflSelectDimension('<?php echo $label; ?>', <?php echo $d['w']; ?>, <?php echo $d['h']; ?>)">
                                        <span><?php echo $label; ?> in</span>
                                    </label>
                                    <?php endforeach; ?>
                                    <label class="opt-btn-wrap refl-dim-btn" data-others="1">
                                        <input type="radio" name="dimension_preset" value="Others" onchange="reflSelectDimensionOthers()">
                                        <span>Others</span>
                                    </label>
                                </div>
                                <input type="hidden" id="reflDimensionsHidden">
                                <div id="reflDimOthersWrap" class="refl-dim-others mt-3" style="display: none;">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Custom Size *</label>
                                    <div class="refl-size-split-row">
                                        <div class="refl-size-split-field">
                                            <label class="refl-size-split-label" for="reflDimOthersW">WIDTH (IN)</label>
                                            <input type="text" id="reflDimOthersW" class="input-field" placeholder="e.g. 10" oninput="reflSyncDimOthers()">
                                        </div>
                                        <span class="refl-size-split-x" aria-hidden="true">×</span>
                                        <div class="refl-size-split-field">
                                            <label class="refl-size-split-label" for="reflDimOthersH">HEIGHT (IN)</label>
                                            <input type="text" id="reflDimOthersH" class="input-field" placeholder="e.g. 14" oninput="reflSyncDimOthers()">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="refl-row">
                                <div class="refl-field refl-custom-card">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Lamination *</label>
                                    <div class="opt-btn-group">
                                        <label class="opt-btn-wrap"><input type="radio" name="laminate_option" value="With Laminate" onchange="reflUpdateOptionVisuals(this)"> <span>With Lamination</span></label>
                                        <label class="opt-btn-wrap"><input type="radio" name="laminate_option" value="Without Laminate" onchange="reflUpdateOptionVisuals(this)"> <span>Without Lamination</span></label>
                                    </div>
                                </div>
                                <div class="refl-field refl-custom-card">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Layout *</label>
                                    <div class="opt-btn-group">
                                        <label class="opt-btn-wrap"><input type="radio" name="layout" value="With Layout" onchange="reflUpdateOptionVisuals(this)"> <span>With Layout</span></label>
                                        <label class="opt-btn-wrap"><input type="radio" name="layout" value="Without Layout" onchange="reflUpdateOptionVisuals(this)"> <span>Without Layout</span></label>
                                    </div>
                                </div>
                            </div>

                            <div class="refl-field refl-custom-card">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Material Brand *</label>
                                <div class="opt-btn-group">
                                    <label class="opt-btn-wrap"><input type="radio" name="material_type" value="Kiwalite (Japan Brand)" onchange="reflUpdateOptionVisuals(this)"> <span>Kiwalite (Japan Brand)</span></label>
                                    <label class="opt-btn-wrap"><input type="radio" name="material_type" value="3M Brand" onchange="reflUpdateOptionVisuals(this)"> <span>3M Brand</span></label>
                                </div>
                            </div>

                            <div class="refl-field refl-custom-card">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                                <input type="file" name="signage_logo" id="signage_logo" accept=".jpg,.jpeg,.png,.pdf" class="input-field">
                            </div>

                            <div id="reflNeedQtyCardCustom" class="refl-need-qty-card">
                                <div class="refl-need-qty-row">
                                    <div class="refl-need-qty-date">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                                        <input type="date" id="needed_date_custom" class="input-field" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="refl-need-qty-qty">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                        <div class="refl-qty-stepper">
                                            <button type="button" onclick="reflQtyDownCustom()" class="refl-qty-btn">−</button>
                                            <input type="number" id="quantity_custom" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="refl-qty-input-inline" oninput="reflQtyClampCustom()">
                                            <button type="button" onclick="reflQtyUpCustom()" class="refl-qty-btn">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="refl-field">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea id="notes_custom" rows="2" class="input-field" placeholder="Any special requests?"></textarea>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="quantity_gatepass" id="quantity_gatepass">
                    <input type="hidden" name="quantity_signage" id="quantity_signage">
                    <input type="hidden" name="quantity" id="quantity_hidden">
                    <input type="hidden" name="needed_date" id="needed_date_hidden">
                    <input type="hidden" name="other_instructions" id="notes_hidden">
                    <input type="hidden" name="dimensions" id="dimensions_submit">
                    <input type="hidden" name="unit" id="unit_submit" value="in">

                    <div class="refl-actions">
                        <a href="<?php echo BASE_URL; ?>/customer/services.php" class="refl-btn-secondary">Back to Services</a>
                        <button type="button" onclick="reflSubmitOrder('add_to_cart')" class="refl-btn-secondary">Add to Cart</button>
                        <button type="button" onclick="reflSubmitOrder('buy_now')" class="refl-btn-primary">Buy Now</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let reflLastSelectedType = '';
window.__reflValidationTriggered = false;

function reflUpdateOptionVisuals(input) {
    const name = input.name;
    document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
        const wrap = r.closest('.opt-btn-wrap');
        if (wrap) wrap.classList.toggle('active', r.checked);
    });
    if (window.__reflValidationTriggered) reflCheckFormValid();
}

function reflSelectDimension(label, w, h) {
    document.getElementById('reflDimensionsHidden').value = label;
    document.getElementById('reflDimOthersWrap').style.display = 'none';
    document.querySelectorAll('#reflCustomSection .refl-dim-btn').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector('#reflCustomSection .refl-dim-btn[data-w="' + w + '"][data-h="' + h + '"]');
    if (btn) btn.classList.add('active');
    if (window.__reflValidationTriggered) reflCheckFormValid();
}

function reflSelectDimensionOthers() {
    document.getElementById('reflDimOthersWrap').style.display = 'block';
    document.querySelectorAll('#reflCustomSection .refl-dim-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('#reflCustomSection .refl-dim-btn[data-others="1"]')?.classList.add('active');
    reflSyncDimOthers();
}

function reflSyncDimOthers() {
    const w = (document.getElementById('reflDimOthersW')?.value || '').trim();
    const h = (document.getElementById('reflDimOthersH')?.value || '').trim();
    document.getElementById('reflDimensionsHidden').value = (w && h) ? (w + ' x ' + h) : '';
    if (window.__reflValidationTriggered) reflCheckFormValid();
}

function reflClearFieldError(container) {
    if (!container) return;
    container.classList.remove('is-invalid');
    const err = container.querySelector('.field-error');
    if (err) { err.textContent = ''; err.style.display = 'none'; }
}

function reflSetFieldError(container, message) {
    if (!container) return;
    let err = container.querySelector('.field-error');
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

function reflCheckFormValid() {
    const show = window.__reflValidationTriggered === true;
    const form = document.getElementById('reflectorizedForm');
    const branch = form.querySelector('select[name="branch_id"]');
    const type = document.getElementById('refl_product_type').value;

    const cBranch = branch.closest('.refl-field');
    const cType = document.getElementById('refl_product_type').closest('.refl-field');

    let ok = !!(branch.value && type);

    if (show) {
        reflSetFieldError(cBranch, !branch.value ? 'This field is required' : '');
        reflSetFieldError(cType, !type ? 'This field is required' : '');
    }

    // Reset section error states
    reflClearFieldError(document.querySelector('.refl-tempPlateFields'));
    reflClearFieldError(document.querySelector('.refl-gatePassFields'));
    reflClearFieldError(document.getElementById('reflCustomSection'));

    if (type === 'Plate Number / Temporary Plate') {
        const mat = document.querySelector('input[name="temp_plate_material"]:checked');
        const plate = document.getElementById('temp_plate_number').value.trim();
        const nd = document.getElementById('needed_date_temp').value;
        const cFields = document.querySelector('.refl-tempPlateFields');
        const okTemp = !!mat && !!plate && !!nd;
        if (show && !okTemp) {
            let msg = 'Please complete all required fields';
            if (!mat) msg = 'Please select a material';
            else if (!plate) msg = 'Please enter the plate number';
            else if (!nd) msg = 'Please select a needed date';
            reflSetFieldError(cFields, msg);
        }
        ok = ok && okTemp;
    } else if (type === 'Subdivision / Gate Pass (Vehicle Sticker)') {
        const sub = document.getElementById('gate_pass_subdivision').value.trim();
        const num = document.getElementById('gate_pass_number').value.trim();
        const gplate = document.getElementById('gate_pass_plate').value.trim();
        const year = document.getElementById('gate_pass_year').value.trim();
        const w = document.getElementById('dimensions_gatepass_w').value.trim();
        const h = document.getElementById('dimensions_gatepass_h').value.trim();
        const file = document.getElementById('gate_pass_logo').files.length > 0;
        const nd = document.getElementById('needed_date').value;
        const cFields = document.querySelector('.refl-gatePassFields');
        const okGate = !!sub && !!num && !!gplate && !!year && !!w && !!h && file && !!nd;
        if (show && !okGate) {
            let msg = 'Please complete all required fields';
            if (!sub) msg = 'Please enter subdivision name';
            else if (!num) msg = 'Please enter gate pass number';
            else if (!gplate) msg = 'Please enter plate number';
            else if (!year) msg = 'Please enter year/validity';
            else if (!w || !h) msg = 'Please enter dimensions';
            else if (!file) msg = 'Please upload a design file';
            else if (!nd) msg = 'Please select a needed date';
            reflSetFieldError(cFields, msg);
        }
        ok = ok && okGate;
    } else if (type === 'Custom Reflectorized Sign') {
        const dim = document.getElementById('reflDimensionsHidden').value.trim();
        const lam = document.querySelector('input[name="laminate_option"]:checked');
        const layout = document.querySelector('input[name="layout"]:checked');
        const mat = document.querySelector('input[name="material_type"]:checked');
        const file = document.getElementById('signage_logo').files.length > 0;
        const nd = document.getElementById('needed_date_custom').value;
        const cFields = document.getElementById('reflCustomSection');
        const okCustom = !!dim && !!lam && !!layout && !!mat && file && !!nd;
        if (show && !okCustom) {
            let msg = 'Please complete all required fields';
            if (!dim) msg = 'Please select or enter dimensions';
            else if (!lam) msg = 'Please select a lamination option';
            else if (!layout) msg = 'Please select a layout option';
            else if (!mat) msg = 'Please select a material brand';
            else if (!file) msg = 'Please upload a design file';
            else if (!nd) msg = 'Please select a needed date';
            reflSetFieldError(cFields, msg);
        }
        ok = ok && okCustom;
    }

    return ok;
}

function reflToggleProductFields() {
    const type = document.getElementById('refl_product_type').value;
    document.querySelectorAll('.refl-expand, .refl-custom-block').forEach(el => el.style.display = 'none');
    document.getElementById('reflNotesSection').style.display = 'none';

    // Reset required attributes first
    document.querySelectorAll('.refl-form input, .refl-form select, .refl-form textarea').forEach(el => {
        if (el.name !== 'branch_id' && el.name !== 'product_type') {
            el.removeAttribute('required');
        }
    });

    if (type === 'Plate Number / Temporary Plate') {
        document.querySelector('.refl-tempPlateFields').style.display = 'block';
        document.getElementById('reflNotesSection').style.display = 'block';
        document.getElementById('temp_plate_number').setAttribute('required', 'required');
        document.getElementById('needed_date_temp').setAttribute('required', 'required');
    } else if (type === 'Subdivision / Gate Pass (Vehicle Sticker)') {
        document.querySelector('.refl-gatePassFields').style.display = 'block';
        document.getElementById('reflNotesSection').style.display = 'block';
        document.getElementById('gate_pass_subdivision').setAttribute('required', 'required');
        document.getElementById('gate_pass_number').setAttribute('required', 'required');
        document.getElementById('gate_pass_plate').setAttribute('required', 'required');
        document.getElementById('gate_pass_year').setAttribute('required', 'required');
        document.getElementById('dimensions_gatepass_w').setAttribute('required', 'required');
        document.getElementById('dimensions_gatepass_h').setAttribute('required', 'required');
        document.getElementById('gate_pass_logo').setAttribute('required', 'required');
        document.getElementById('needed_date').setAttribute('required', 'required');
    } else if (type === 'Custom Reflectorized Sign') {
        const c = document.getElementById('reflCustomSection');
        c.style.display = 'block';
        setTimeout(() => c.classList.add('refl-visible'), 10);
        document.getElementById('signage_logo').setAttribute('required', 'required');
        document.getElementById('needed_date_custom').setAttribute('required', 'required');
    }
    if (window.__reflValidationTriggered) reflCheckFormValid();
}

function reflQtyUp() { const i = document.getElementById('quantity'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function reflQtyDown() { const i = document.getElementById('quantity'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }
function reflQtyUpTemp() { const i = document.getElementById('quantity_temp'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function reflQtyDownTemp() { const i = document.getElementById('quantity_temp'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }
function reflQtyUpCustom() { const i = document.getElementById('quantity_custom'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function reflQtyDownCustom() { const i = document.getElementById('quantity_custom'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }

function reflQtyClamp() { const i = document.getElementById('quantity'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }
function reflQtyClampTemp() { const i = document.getElementById('quantity_temp'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }
function reflQtyClampCustom() { const i = document.getElementById('quantity_custom'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }

function reflSubmitOrder(action) {
    window.__reflValidationTriggered = true;
    if (!reflCheckFormValid()) return;

    const form = document.getElementById('reflectorizedForm');
    const type = document.getElementById('refl_product_type').value;

    if (type === 'Plate Number / Temporary Plate') {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity_temp').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date_temp').value;
        document.getElementById('notes_hidden').value = document.getElementById('notes_shared').value;
        document.getElementById('dimensions_submit').value = 'Standard';
    } else if (type === 'Subdivision / Gate Pass (Vehicle Sticker)') {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date').value;
        document.getElementById('notes_hidden').value = document.getElementById('notes_shared').value;
        document.getElementById('dimensions_submit').value = document.getElementById('dimensions_gatepass_w').value + ' x ' + document.getElementById('dimensions_gatepass_h').value;
    } else if (type === 'Custom Reflectorized Sign') {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity_custom').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date_custom').value;
        document.getElementById('notes_hidden').value = document.getElementById('notes_custom').value;
        document.getElementById('dimensions_submit').value = document.getElementById('reflDimensionsHidden').value;
    }

    const formData = new FormData(form);
    formData.append('action', action);

    const buttons = form.querySelectorAll('button');
    buttons.forEach(b => b.disabled = true);

    fetch('api_add_to_cart_reflectorized.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = action === 'buy_now' ? 'order_review.php?item=' + data.item_key : 'cart.php';
        } else {
            alert('Error: ' + data.message);
            buttons.forEach(b => b.disabled = false);
        }
    })
    .catch(err => {
        console.error(err);
        alert('An unexpected error occurred.');
        buttons.forEach(b => b.disabled = false);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    reflToggleProductFields();
});
</script>

<style>
.refl-container { max-width: 640px; margin: 0 auto; padding: 0 1rem; }
.refl-container h1 { color: #eaf6fb !important; }
.refl-order-card.card { background: rgba(10, 37, 48, 0.55); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 1.25rem; box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35); padding: 1.5rem; }
.refl-main { display: flex; flex-direction: column; gap: 1rem; }

#reflectorizedForm .mb-4, .refl-field, .refl-expand, .refl-custom-card, .refl-need-qty-card, .refl-size-unit-card {
    padding: 1rem; background: rgba(10, 37, 48, 0.48); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 12px; backdrop-filter: blur(4px);
}
#reflectorizedForm label.block { font-size: .95rem !important; font-weight: 700 !important; color: #d9e6ef !important; margin-bottom: .55rem !important; }
#reflectorizedForm .input-field { min-height: 44px; padding: .72rem .9rem; border-radius: 10px; background: rgba(13, 43, 56, 0.92) !important; border: 1px solid rgba(83, 197, 224, 0.26) !important; color: #e9f6fb !important; width: 100%; }
.refl-select-btn { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%23d8edf5' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; }

.opt-btn-group { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.6rem; }
.opt-btn-wrap { min-height: 44px; padding: 0.65rem; border-radius: 10px; background: rgba(255, 255, 255, 0.04) !important; border: 1px solid rgba(83, 197, 224, 0.2) !important; color: #d2e7f1 !important; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.86rem; }
.opt-btn-wrap.active { background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important; border-color: #53c5e0 !important; color: #f8fcff !important; }
.refl-temp-material-center { grid-column: span 2; }

.refl-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.refl-col-span-2 { grid-column: span 2; }
.refl-size-split-row { display: flex; align-items: flex-end; gap: 0.75rem; }
.refl-size-split-label { font-size: 0.75rem; font-weight: 700; color: #9fc6d9; text-transform: uppercase; display: block; margin-bottom: 0.45rem; }
.refl-size-split-x { padding-bottom: 0.75rem; color: #d2e7f1; }

.refl-qty-stepper { display: flex; align-items: center; border: 1px solid rgba(83, 197, 224, 0.26); border-radius: 10px; overflow: hidden; background: rgba(13, 43, 56, 0.92); height: 44px; transition: border-color 0.2s, box-shadow 0.2s; }
.refl-qty-stepper:focus-within { border-color: #53c5e0; box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16); }
.refl-qty-btn { flex: 0 0 44px; height: 100%; border: none; background: rgba(83, 197, 224, 0.12); color: #d8edf5; font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; outline: none; }
.refl-qty-btn:hover { background: rgba(83, 197, 224, 0.25); color: #fff; }
.refl-qty-btn:active { background: rgba(83, 197, 224, 0.35); }
.refl-qty-input-inline { flex: 1; border: none; text-align: center; background: transparent; color: #fff; font-weight: 700; font-size: 1rem; outline: none; -moz-appearance: textfield; }
.refl-qty-input-inline::-webkit-inner-spin-button, .refl-qty-input-inline::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.refl-need-qty-row { display: flex; gap: 1rem; align-items: flex-start; }
.refl-need-qty-date { flex: 1; min-width: 0; }
.refl-need-qty-qty { flex: 1; min-width: 0; }

.refl-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; }
.refl-btn-secondary { height: 46px; min-width: 140px; padding: 0 1rem; border-radius: 10px; background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(83, 197, 224, 0.28) !important; color: #d9e6ef !important; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.refl-btn-primary { height: 46px; min-width: 140px; border-radius: 10px; background: linear-gradient(135deg, #53c5e0, #32a1c4) !important; color: #fff !important; font-weight: 700; border: none; cursor: pointer; text-transform: uppercase; }

.field-error { color: #fca5a5; font-size: 0.75rem; margin-top: 0.4rem; display: none; }
#reflectorizedForm .is-invalid { border-color: rgba(239, 68, 68, 0.45) !important; }

@media (max-width: 640px) {
    .refl-grid-2 { grid-template-columns: 1fr; }
    .refl-col-span-2 { grid-column: span 1; }
    .refl-need-qty-row { flex-direction: column; }
    .refl-actions { flex-direction: column; }
    .refl-btn-secondary, .refl-btn-primary { width: 100%; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
