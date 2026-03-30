<?php
/**
 * Sintraboard & Standees - Service Order Form
 * PrintFlow - Clean flow: Product Type → Dimensions → Options → Upload → Needed Date + Quantity → Notes
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';

// Common Sintraboard dimension presets (inches)
$dimension_presets = [
    '8 x 10'  => ['w' => 8,  'h' => 10],
    '12 x 18' => ['w' => 12, 'h' => 18],
    '18 x 24' => ['w' => 18, 'h' => 24],
    '24 x 36' => ['w' => 24, 'h' => 36],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id_raw = trim($_POST['branch_id'] ?? '');
    $branch_id = $branch_id_raw === '' ? 0 : (int)$branch_id_raw;
    $sintra_type = trim($_POST['sintra_type'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $thickness = trim($_POST['thickness'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $quantity = max(1, min(999, $quantity));

    $valid_types = ['Flat Type', '2D Type (with Frame)', 'Standee (Back Stand Support)'];
    if ($branch_id < 1) {
        $error = 'Please select a branch.';
    } elseif (empty($sintra_type) || !in_array($sintra_type, $valid_types, true)) {
        $error = 'Please select a Sintraboard Type.';
    } elseif ($dimensions === '') {
        $error = 'Please specify dimensions.';
    } elseif ($unit === '' || !in_array($unit, ['in', 'ft'], true)) {
        $error = 'Please select a unit.';
    } elseif ($lamination === '' || !in_array($lamination, ['With Lamination', 'Without Lamination'], true)) {
        $error = 'Please select lamination.';
    } elseif ($layout === '' || !in_array($layout, ['With Layout', 'Without Layout'], true)) {
        $error = 'Please select layout.';
    } elseif ($thickness === '' || !in_array($thickness, ['3mm', '5mm', '10mm'], true)) {
        $error = 'Please select thickness.';
    } elseif ($needed_date === '') {
        $error = 'Please select a needed date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $needed_date)) {
        $error = 'Please enter a valid needed date.';
    } elseif (strtotime($needed_date . ' 00:00:00') < strtotime('today')) {
        $error = 'Needed date cannot be in the past.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $width = '';
            $height = '';
            if (preg_match('/^(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)$/i', $dimensions, $m)) {
                $width = $m[1];
                $height = $m[2];
            } else {
                $parts = preg_split('/[\s,]+/', $dimensions, 2);
                if (count($parts) >= 2) {
                    $width = trim($parts[0]);
                    $height = trim($parts[1]);
                }
            }

            if ($width === '' || $height === '') {
                $error = 'Please enter valid dimensions (e.g. 12 x 18).';
            } else {
                $tmp_dir = service_order_temp_dir();
                $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
                $tmp_filename = uniqid('sintra_') . '.' . $ext;
                $tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
                file_put_contents($tmp_path, file_get_contents($_FILES['design_file']['tmp_name']));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['design_file']['tmp_name']);
                finfo_close($finfo);

                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }

                $product_id = ($sintra_type === 'Flat Type') ? 51 : 54;
                $product_name = $sintra_type;
                $sintra_price = ($sintra_type === 'Flat Type') ? 150.00 : 800.00;

                $item_key = $product_id . '_' . time();
                $_SESSION['cart'][$item_key] = [
                    'product_id'       => $product_id,
                    'source_page'      => 'services',
                    'branch_id'        => $branch_id,
                    'name'             => $product_name,
                    'category'         => 'Sintraboard Standees',
                    'price'            => $sintra_price,
                    'quantity'         => $quantity,
                    'image'            => '📦',
                    'customization'    => [
                        'Sintra_Type' => $sintra_type,
                        'Dimensions'  => $dimensions,
                        'Unit'        => $unit,
                        'Width'       => $width,
                        'Height'      => $height,
                        'Thickness'   => $thickness,
                        'Lamination'  => $lamination,
                        'Layout'      => $layout,
                        'needed_date' => $needed_date,
                        'notes'       => $notes,
                    ],
                    'design_notes'     => $notes,
                    'design_tmp_path'  => $tmp_path,
                    'design_mime'     => $mime,
                    'design_name'     => $_FILES['design_file']['name'],
                    'reference_tmp_path' => null,
                    'reference_mime'  => null,
                    'reference_name'  => null,
                ];

                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                    redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
                } else {
                    redirect(BASE_URL . '/customer/cart.php');
                }
            }
        }
    }
}

$page_title = 'Order Sintraboard Standees - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$qty_default = (int)($_GET['qty'] ?? 1);
$qty_default = max(1, min(999, $qty_default));
$sel_type = $_POST['sintra_type'] ?? $_GET['sintra_type'] ?? '';
$sel_unit = $_POST['unit'] ?? '';
$sel_lamination = $_POST['lamination'] ?? '';
$sel_layout = $_POST['layout'] ?? '';
$sel_thickness = $_POST['thickness'] ?? '';
$other_w = '';
$other_h = '';
if (!empty($_POST['dimensions']) && preg_match('/^(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)$/i', trim((string)($_POST['dimensions'] ?? '')), $sintra_dim_m)) {
    $other_w = $sintra_dim_m[1];
    $other_h = $sintra_dim_m[2];
}
?>
<div class="min-h-screen py-8 sintra-order-page">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <h1 class="text-2xl font-bold mb-6 sintra-page-title">Sintraboard Standees</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card sintra-order-card">
            <form method="POST" enctype="multipart/form-data" id="sintraForm" class="sintra-order-form" novalidate>
                <?php echo csrf_field(); ?>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" id="sintra_branch_id" class="input-field" required>
                        <?php $branch_post = $_POST['branch_id'] ?? ''; ?>
                        <option value="" disabled <?php echo ($branch_post === '' || $branch_post === null) ? 'selected' : ''; ?>>Select Branch</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ((string)($b['id']) === (string)$branch_post) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sintraboard Type <span class="sintra-req-mark">*</span></label>
                    <div class="option-grid option-grid-3x2">
                        <?php
                        $types = ['Flat Type', '2D Type (with Frame)', 'Standee (Back Stand Support)'];
                        $type_info = [
                            'Flat Type' => 'A standard flat sintraboard panel ideal for wall signs, labels, and simple display boards.',
                            '2D Type (with Frame)' => 'A framed 2D board with cleaner edge presentation, great for more premium display use.',
                            'Standee (Back Stand Support)' => 'A freestanding board with rear support so it can stand on floors or counters without wall mounting.',
                        ];
                        foreach ($types as $t):
                            $checked = ($sel_type === $t) ? 'checked' : '';
                        ?>
                        <label class="opt-btn-wrap sintra-type-row" data-info-title="<?php echo htmlspecialchars($t); ?>" data-info-body="<?php echo htmlspecialchars($type_info[$t] ?? ''); ?>">
                            <input type="radio" name="sintra_type" value="<?php echo htmlspecialchars($t); ?>" <?php echo $checked; ?> onchange="sintraUpdateOptionVisuals(this)">
                            <span><?php echo htmlspecialchars($t); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dimensions <span class="sintra-req-mark">*</span></label>
                    <p class="sintra-hint mb-2">Select a preset or enter custom size.</p>
                    <div class="option-grid option-grid-3x2 sintra-dim-grid">
                        <?php foreach ($dimension_presets as $label => $d): ?>
                        <label class="opt-btn-wrap sintra-dim-btn" data-w="<?php echo $d['w']; ?>" data-h="<?php echo $d['h']; ?>">
                            <input type="radio" name="dimension_preset" value="<?php echo htmlspecialchars($label); ?>" onchange="sintraSelectDimension('<?php echo htmlspecialchars($label); ?>', <?php echo $d['w']; ?>, <?php echo $d['h']; ?>)">
                            <span><?php echo htmlspecialchars($label); ?> in</span>
                        </label>
                        <?php endforeach; ?>
                        <label class="opt-btn-wrap sintra-dim-btn opt-btn-others" data-others="1">
                            <input type="radio" name="dimension_preset" value="Others" onchange="sintraSelectDimensionOthers()">
                            <span>Others</span>
                        </label>
                    </div>
                    <input type="hidden" name="dimensions" id="sintra_dimensions" value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>">
                    <div id="sintraDimOthersWrap" class="sintra-dim-others-wrap" style="display: none;">
                        <div class="sintra-dim-others-row">
                            <div class="sintra-dim-others-field">
                                <label class="sintra-dim-others-label" for="sintra_dim_other_w">WIDTH <span class="sintra-dim-others-unit"><?php echo $sel_unit === 'ft' ? '(FT)' : ($sel_unit === 'in' ? '(IN)' : ''); ?></span></label>
                                <input type="text" id="sintra_dim_other_w" class="input-field sintra-dim-others-input" inputmode="decimal" placeholder="e.g. 10" value="<?php echo htmlspecialchars($other_w); ?>" autocomplete="off" oninput="sintraSyncDimOthers()">
                            </div>
                            <span class="sintra-dim-others-x" aria-hidden="true">×</span>
                            <div class="sintra-dim-others-field">
                                <label class="sintra-dim-others-label" for="sintra_dim_other_h">HEIGHT <span class="sintra-dim-others-unit"><?php echo $sel_unit === 'ft' ? '(FT)' : ($sel_unit === 'in' ? '(IN)' : ''); ?></span></label>
                                <input type="text" id="sintra_dim_other_h" class="input-field sintra-dim-others-input" inputmode="decimal" placeholder="e.g. 12" value="<?php echo htmlspecialchars($other_h); ?>" autocomplete="off" oninput="sintraSyncDimOthers()">
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit <span class="sintra-req-mark">*</span></label>
                        <div class="opt-btn-group">
                            <label class="opt-btn-wrap">
                                <input type="radio" name="unit" value="in" required <?php echo $sel_unit === 'in' ? 'checked' : ''; ?> onchange="sintraUpdateOptionVisuals(this)">
                                <span>Inches (in)</span>
                            </label>
                            <label class="opt-btn-wrap">
                                <input type="radio" name="unit" value="ft" <?php echo $sel_unit === 'ft' ? 'checked' : ''; ?> onchange="sintraUpdateOptionVisuals(this)">
                                <span>Feet (ft)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lamination <span class="sintra-req-mark">*</span></label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap">
                            <input type="radio" name="lamination" value="With Lamination" required <?php echo $sel_lamination === 'With Lamination' ? 'checked' : ''; ?> onchange="sintraUpdateOptionVisuals(this)">
                            <span>With Lamination</span>
                        </label>
                        <label class="opt-btn-wrap">
                            <input type="radio" name="lamination" value="Without Lamination" <?php echo $sel_lamination === 'Without Lamination' ? 'checked' : ''; ?> onchange="sintraUpdateOptionVisuals(this)">
                            <span>Without Lamination</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Layout <span class="sintra-req-mark">*</span></label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap">
                            <input type="radio" name="layout" value="With Layout" required <?php echo $sel_layout === 'With Layout' ? 'checked' : ''; ?> onchange="sintraUpdateOptionVisuals(this)">
                            <span>With Layout</span>
                        </label>
                        <label class="opt-btn-wrap">
                            <input type="radio" name="layout" value="Without Layout" <?php echo $sel_layout === 'Without Layout' ? 'checked' : ''; ?> onchange="sintraUpdateOptionVisuals(this)">
                            <span>Without Layout</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Thickness <span class="sintra-req-mark">*</span></label>
                    <div class="opt-btn-group opt-btn-group-sintra-3">
                        <?php foreach (['3mm', '5mm', '10mm'] as $ti => $th): ?>
                        <label class="opt-btn-wrap">
                            <input type="radio" name="thickness" value="<?php echo htmlspecialchars($th); ?>" <?php echo $ti === 0 ? 'required' : ''; ?> <?php echo $sel_thickness === $th ? 'checked' : ''; ?> onchange="sintraUpdateOptionVisuals(this)">
                            <span><?php echo htmlspecialchars($th); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" id="sintra_design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
                </div>

                <div class="mb-4" id="sintra-need-qty-card">
                    <div class="need-qty-row">
                        <div class="need-qty-date">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date <span class="sintra-req-mark">*</span></label>
                            <input type="date" name="needed_date" id="sintra_needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="lam-qty-qty need-qty-qty">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity <span class="sintra-req-mark">*</span></label>
                            <div class="qty-control qty-control-shopee">
                                <button type="button" onclick="sintraQtyDown()" class="qty-btn">−</button>
                                <input type="number" name="quantity" id="sintra_quantity" min="1" max="999" value="<?php echo (int)($_POST['quantity'] ?? $qty_default); ?>" oninput="sintraQtyClamp()">
                                <button type="button" onclick="sintraQtyUp()" class="qty-btn">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field" placeholder="Any special requests?" maxlength="500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="tshirt-actions-row">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                    <button type="submit" name="action" value="add_to_cart" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                    <button type="submit" name="action" value="buy_now" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="sintraTypeInfoModal" class="sintra-info-modal" style="display:none;">
    <div class="sintra-info-modal-backdrop" onclick="sintraCloseInfoModal()"></div>
    <div class="sintra-info-modal-card" role="dialog" aria-modal="true" aria-labelledby="sintraInfoTitle">
        <button type="button" class="sintra-info-modal-close" onclick="sintraCloseInfoModal()" aria-label="Close">×</button>
        <h3 id="sintraInfoTitle" class="sintra-info-modal-title"></h3>
        <p id="sintraInfoBody" class="sintra-info-modal-body"></p>
    </div>
</div>

<script>
function sintraOpenInfoModal(title, body) {
    var modal = document.getElementById('sintraTypeInfoModal');
    var titleEl = document.getElementById('sintraInfoTitle');
    var bodyEl = document.getElementById('sintraInfoBody');
    if (!modal || !titleEl || !bodyEl) return;
    titleEl.textContent = title || 'Sintraboard Type';
    bodyEl.textContent = body || '';
    modal.style.display = 'flex';
}

function sintraCloseInfoModal() {
    var modal = document.getElementById('sintraTypeInfoModal');
    if (modal) modal.style.display = 'none';
}

function sintraClearFieldError(container) {
    if (!container) return;
    var err = container.querySelector('.field-error');
    container.classList.remove('is-invalid');
    if (err) {
        err.textContent = '';
        err.style.display = 'none';
    }
}

function sintraSetFieldError(container, message) {
    if (!container) return;
    var err = container.querySelector('.field-error');
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

function sintraCheckFormValid() {
    var showErrors = window.__sintraValidationTriggered === true;
    var form = document.getElementById('sintraForm');
    if (!form) return true;

    var branch = form.querySelector('select[name="branch_id"]');
    var sintraType = form.querySelector('input[name="sintra_type"]:checked');
    var dimEl = document.getElementById('sintra_dimensions');
    var dim = (dimEl && dimEl.value.trim()) || '';
    var othersWrap = document.getElementById('sintraDimOthersWrap');
    var othersOpen = othersWrap && othersWrap.style.display !== 'none';
    var owIn = document.getElementById('sintra_dim_other_w');
    var ohIn = document.getElementById('sintra_dim_other_h');
    var wv = (owIn && owIn.value.trim()) || '';
    var hv = (ohIn && ohIn.value.trim()) || '';
    var unit = form.querySelector('input[name="unit"]:checked');
    var lamination = form.querySelector('input[name="lamination"]:checked');
    var layout = form.querySelector('input[name="layout"]:checked');
    var thickness = form.querySelector('input[name="thickness"]:checked');
    var file = document.getElementById('sintra_design_file');
    var neededDate = document.getElementById('sintra_needed_date');
    var qtyInput = document.getElementById('sintra_quantity');
    var qty = qtyInput ? (parseInt(qtyInput.value, 10) || 0) : 0;

    var cBranch = branch && branch.closest('.mb-4');
    var cType = form.querySelector('input[name="sintra_type"]') && form.querySelector('input[name="sintra_type"]').closest('.mb-4');
    var cDim = dimEl && dimEl.closest('.mb-4');
    var cLam = form.querySelector('input[name="lamination"]') && form.querySelector('input[name="lamination"]').closest('.mb-4');
    var cLayout = form.querySelector('input[name="layout"]') && form.querySelector('input[name="layout"]').closest('.mb-4');
    var cThick = form.querySelector('input[name="thickness"]') && form.querySelector('input[name="thickness"]').closest('.mb-4');
    var cFile = file && file.closest('.mb-4');
    var cNeedQty = document.getElementById('sintra-need-qty-card');

    var ok = !!(branch && branch.value && sintraType && dim && (!othersOpen || (wv && hv)) && unit && lamination && layout && thickness && file && file.files && file.files.length > 0 && neededDate && neededDate.value.trim() !== '' && qty >= 1);

    if (showErrors) {
        sintraSetFieldError(cBranch, (branch && !branch.value) ? 'This field is required' : '');
        sintraSetFieldError(cType, !sintraType ? 'This field is required' : '');
        var dimMsg = '';
        if (!dim) dimMsg = 'This field is required';
        else if (othersOpen && (!wv || !hv)) dimMsg = 'This field is required';
        else if (!unit) dimMsg = 'This field is required';
        sintraSetFieldError(cDim, dimMsg);
        sintraSetFieldError(cLam, !lamination ? 'This field is required' : '');
        sintraSetFieldError(cLayout, !layout ? 'This field is required' : '');
        sintraSetFieldError(cThick, !thickness ? 'This field is required' : '');
        sintraSetFieldError(cFile, !(file && file.files && file.files.length > 0) ? 'This field is required' : '');
        sintraSetFieldError(cNeedQty, (!neededDate || !neededDate.value.trim() || qty < 1) ? 'This field is required' : '');
    } else {
        [cBranch, cType, cDim, cLam, cLayout, cThick, cFile, cNeedQty].forEach(sintraClearFieldError);
    }

    return ok;
}

function sintraUpdateOthersUnitLabels() {
    var u = document.querySelector('#sintraForm input[name="unit"]:checked');
    var t = '';
    if (u && u.value === 'ft') t = '(FT)';
    else if (u && u.value === 'in') t = '(IN)';
    document.querySelectorAll('#sintraDimOthersWrap .sintra-dim-others-unit').forEach(function(el) {
        el.textContent = t;
    });
}

function sintraUpdateOptionVisuals(input) {
    const name = input.name;
    document.querySelectorAll('#sintraForm input[name="' + name + '"]').forEach(function(r) {
        const wrap = r.closest('.opt-btn-wrap');
        if (wrap) { wrap.classList.remove('active'); if (r.checked) wrap.classList.add('active'); }
    });
    if (input && input.name === 'unit') sintraUpdateOthersUnitLabels();
    sintraCheckFormValid();
}

function sintraSelectDimension(label, w, h) {
    document.getElementById('sintra_dimensions').value = w + ' x ' + h;
    document.getElementById('sintraDimOthersWrap').style.display = 'none';
    var ow = document.getElementById('sintra_dim_other_w');
    var oh = document.getElementById('sintra_dim_other_h');
    if (ow) ow.value = '';
    if (oh) oh.value = '';
    document.querySelectorAll('.sintra-dim-btn').forEach(function(b) { b.classList.remove('active'); });
    var btn = document.querySelector('.sintra-dim-btn[data-w="' + w + '"][data-h="' + h + '"]');
    if (btn) btn.classList.add('active');
    sintraCheckFormValid();
}

function sintraSelectDimensionOthers() {
    document.getElementById('sintraDimOthersWrap').style.display = 'block';
    document.getElementById('sintra_dimensions').value = '';
    document.querySelectorAll('.sintra-dim-btn').forEach(function(b) { b.classList.remove('active'); });
    var others = document.querySelector('.sintra-dim-btn[data-others="1"]');
    if (others) others.classList.add('active');
    sintraCheckFormValid();
}

function sintraSyncDimOthers() {
    var w = document.getElementById('sintra_dim_other_w');
    var h = document.getElementById('sintra_dim_other_h');
    var wv = w ? w.value.trim() : '';
    var hv = h ? h.value.trim() : '';
    document.getElementById('sintra_dimensions').value = (wv && hv) ? (wv + ' x ' + hv) : '';
    sintraCheckFormValid();
}

function sintraQtyUp() {
    var q = document.getElementById('sintra_quantity');
    q.value = Math.min(999, (parseInt(q.value, 10) || 1) + 1);
    sintraCheckFormValid();
}
function sintraQtyDown() {
    var q = document.getElementById('sintra_quantity');
    q.value = Math.max(1, (parseInt(q.value, 10) || 1) - 1);
    sintraCheckFormValid();
}
function sintraQtyClamp() {
    var q = document.getElementById('sintra_quantity');
    var v = parseInt(q.value, 10) || 1;
    q.value = Math.max(1, Math.min(999, v));
    sintraCheckFormValid();
}

document.getElementById('sintra_design_file').addEventListener('change', function(e) {
    sintraCheckFormValid();
});

document.getElementById('sintraForm').addEventListener('invalid', function(e) {
    e.preventDefault();
}, true);

document.getElementById('sintraForm').addEventListener('change', sintraCheckFormValid);
document.getElementById('sintraForm').addEventListener('input', sintraCheckFormValid);

document.getElementById('sintraForm').addEventListener('submit', function(e) {
    window.__sintraValidationTriggered = true;
    if (!sintraCheckFormValid()) {
        e.preventDefault();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#sintraForm .opt-btn-wrap').forEach(function(w) {
        if (w.querySelector('input:checked')) w.classList.add('active');
    });
    document.querySelectorAll('#sintraForm .sintra-type-row').forEach(function(row) {
        row.addEventListener('click', function() {
            sintraOpenInfoModal(row.getAttribute('data-info-title') || '', row.getAttribute('data-info-body') || '');
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') sintraCloseInfoModal();
    });
    var dimVal = document.getElementById('sintra_dimensions').value.trim();
    if (dimVal) {
        var matched = false;
        document.querySelectorAll('.sintra-dim-btn[data-w]').forEach(function(btn) {
            var w = btn.getAttribute('data-w');
            var h = btn.getAttribute('data-h');
            if (dimVal === w + ' x ' + h) {
                btn.classList.add('active');
                var inp = btn.querySelector('input[type="radio"]');
                if (inp) inp.checked = true;
                matched = true;
            }
        });
        if (!matched && dimVal) {
            var others = document.querySelector('.sintra-dim-btn[data-others="1"]');
            if (others) {
                others.classList.add('active');
                var or = others.querySelector('input[type="radio"]');
                if (or) or.checked = true;
            }
            document.getElementById('sintraDimOthersWrap').style.display = 'block';
            var m = dimVal.match(/^(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)$/i);
            var ow = document.getElementById('sintra_dim_other_w');
            var oh = document.getElementById('sintra_dim_other_h');
            if (m && ow && oh) {
                ow.value = m[1];
                oh.value = m[2];
            } else if (ow && oh) {
                ow.value = dimVal;
                oh.value = '';
            }
        }
    }
    sintraUpdateOthersUnitLabels();
    sintraCheckFormValid();
});
</script>

<style>
/* ——— Aligned with customer/order_tshirt.php form shell & dark controls ——— */
.sintra-order-page .sintra-page-title {
    color: #eaf6fb !important;
}
.sintra-order-card.card {
    background: rgba(10, 37, 48, 0.55);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 1.25rem;
    box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35);
}
.field-error {
    margin-top: 0.4rem;
    font-size: 0.75rem;
    color: #fca5a5;
    line-height: 1.3;
    display: block;
    width: 100%;
}
#sintraForm .mb-4.is-invalid {
    border-color: rgba(239, 68, 68, 0.35) !important;
    box-shadow: none !important;
}
#sintraForm .mb-4.is-invalid .input-field,
#sintraForm .mb-4.is-invalid .qty-control {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#sintraForm .mb-4.is-invalid .opt-btn-wrap {
    border-color: rgba(239, 68, 68, 0.45) !important;
}
.sintra-req-mark {
    color: #eaf6fb;
}
#sintraForm.sintra-order-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    color-scheme: dark;
}
#sintraForm .mb-4 {
    margin-bottom: 0 !important;
    padding: 1rem;
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
#sintraForm label.block {
    font-size: 0.95rem !important;
    font-weight: 700 !important;
    color: #d9e6ef !important;
    margin-bottom: 0.55rem !important;
}
.sintra-hint {
    font-size: 0.75rem;
    color: #9fc6d9 !important;
}
#sintraForm .input-field {
    min-height: 44px;
    padding: 0.72rem 0.9rem;
    border-radius: 10px;
    font-size: 0.95rem;
    width: 100%;
    box-sizing: border-box;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #e9f6fb !important;
    box-shadow: none !important;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
#sintraForm .input-field::placeholder {
    color: #a9c1cd !important;
}
#sintraForm textarea[name="notes"].input-field {
    overflow-y: auto;
    resize: vertical;
    min-height: 110px;
    max-height: 220px;
    max-width: 100%;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08);
}
#sintraForm textarea[name="notes"].input-field::-webkit-scrollbar {
    width: 10px;
}
#sintraForm textarea[name="notes"].input-field::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 999px;
}
#sintraForm textarea[name="notes"].input-field::-webkit-scrollbar-thumb {
    background: rgba(83, 197, 224, 0.65);
    border-radius: 999px;
    border: 2px solid rgba(10, 37, 48, 0.55);
}
#sintraForm textarea[name="notes"].input-field::-webkit-scrollbar-thumb:hover {
    background: rgba(83, 197, 224, 0.85);
}
#sintraForm .input-field:focus,
#sintraForm .input-field:focus-visible {
    background: rgba(16, 52, 67, 0.98) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
    outline: none !important;
}
#sintraForm select.input-field option {
    background: #0a2530 !important;
    color: #f8fafc !important;
}
#sintraForm input[type="radio"] {
    accent-color: #53c5e0;
}
#sintraForm .opt-btn-wrap input {
    margin-right: 0.5rem;
}
#sintraForm .option-grid {
    display: grid;
    gap: 0.5rem;
    width: 100%;
}
#sintraForm .option-grid-3x2 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.6rem;
}
#sintraForm .sintra-dim-grid .opt-btn-others {
    grid-column: 2;
}
.sintra-dim-others-wrap {
    margin-top: 0.75rem;
}
.sintra-dim-others-row {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    flex-wrap: nowrap;
}
.sintra-dim-others-field {
    flex: 1;
    min-width: 0;
}
.sintra-dim-others-label {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #9fc6d9 !important;
    text-transform: uppercase;
    margin-bottom: 0.45rem;
}
.sintra-dim-others-unit {
    font-weight: 700;
    color: #c5dfe9;
}
.sintra-dim-others-x {
    flex: 0 0 auto;
    padding-bottom: 0.72rem;
    font-size: 1.15rem;
    font-weight: 600;
    color: #d2e7f1;
    line-height: 1;
    user-select: none;
}
.sintra-dim-others-input {
    width: 100%;
    box-sizing: border-box;
}
@media (max-width: 480px) {
    .sintra-dim-others-row {
        flex-direction: column;
        align-items: stretch;
    }
    .sintra-dim-others-x {
        align-self: center;
        padding: 0.35rem 0;
    }
}
#sintraForm .opt-btn-group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem;
    width: 100%;
}
#sintraForm .opt-btn-group-sintra-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
#sintraForm .opt-btn-wrap {
    min-height: 44px;
    padding: 0.65rem 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.86rem;
    text-align: center;
    line-height: 1.25;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(83, 197, 224, 0.2) !important;
    color: #d2e7f1 !important;
    transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
}
#sintraForm .opt-btn-wrap:hover {
    background: rgba(83, 197, 224, 0.12) !important;
    border-color: rgba(83, 197, 224, 0.5) !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.12);
}
#sintraForm .opt-btn-wrap:has(input:checked),
#sintraForm .opt-btn-wrap.active {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important;
    border-color: #53c5e0 !important;
    color: #f8fcff !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.22), 0 8px 18px rgba(11, 42, 56, 0.35);
}
.sintra-preview-img {
    max-width: 200px;
    max-height: 150px;
    border-radius: 8px;
    border: 1px solid rgba(83, 197, 224, 0.35);
    object-fit: contain;
}
#sintraForm .need-qty-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
#sintraForm .need-qty-date {
    flex: 1;
    min-width: 0;
}
#sintraForm .need-qty-qty {
    flex: 1;
    min-width: 0;
}
#sintraForm .need-qty-qty .qty-control-shopee {
    width: 100%;
    max-width: none;
}
#sintraForm .qty-control {
    display: flex;
    align-items: center;
    height: 42px;
    border-radius: 10px;
    overflow: hidden;
    box-sizing: border-box;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
}
#sintraForm .qty-control:focus-within {
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.16);
}
#sintraForm .qty-btn {
    flex: 0 0 36px;
    width: 36px;
    height: 42px;
    border: none;
    background: rgba(83, 197, 224, 0.12) !important;
    color: #d8edf5 !important;
    font-weight: 800;
    font-size: 1.1rem;
    cursor: pointer;
    transition: background 0.2s;
}
#sintraForm .qty-btn:hover {
    background: rgba(83, 197, 224, 0.22) !important;
}
#sintraForm .qty-control input {
    flex: 1;
    min-width: 36px;
    border: none;
    text-align: center;
    font-weight: 700;
    font-size: 0.95rem;
    outline: none;
    background: transparent !important;
    color: #f8fafc !important;
    height: 42px;
}
#sintra_quantity::-webkit-outer-spin-button,
#sintra_quantity::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
#sintra_quantity {
    -moz-appearance: textfield;
    appearance: textfield;
}
.tshirt-actions-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
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
    font-size: 0.9rem;
    font-weight: 700;
    transition: all 0.2s;
    box-sizing: border-box;
}
.tshirt-btn-secondary {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(83, 197, 224, 0.28) !important;
    color: #d9e6ef !important;
}
.tshirt-btn-secondary:hover {
    background: rgba(83, 197, 224, 0.14) !important;
    border-color: rgba(83, 197, 224, 0.52) !important;
    color: #fff !important;
}
.tshirt-btn-primary {
    border: none;
    background: linear-gradient(135deg, #53c5e0, #32a1c4) !important;
    color: #fff !important;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow: 0 10px 22px rgba(50, 161, 196, 0.3);
}
.tshirt-btn:active {
    transform: translateY(1px) scale(0.99);
}
.sintra-info-modal {
    position: fixed;
    inset: 0;
    z-index: 100000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.sintra-info-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 12, 18, 0.62);
}
.sintra-info-modal-card {
    position: relative;
    width: 100%;
    max-width: 520px;
    background: rgba(10, 37, 48, 0.97);
    border: 1px solid rgba(83, 197, 224, 0.3);
    border-radius: 14px;
    padding: 1.2rem 1.25rem 1.1rem;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.5);
}
.sintra-info-modal-close {
    position: absolute;
    top: 0.65rem;
    right: 0.7rem;
    width: 30px;
    height: 30px;
    border-radius: 999px;
    border: 1px solid rgba(83, 197, 224, 0.35);
    background: rgba(255, 255, 255, 0.06);
    color: #d8edf5;
    font-size: 1.1rem;
    line-height: 1;
    cursor: pointer;
}
.sintra-info-modal-title {
    margin: 0 2rem 0.6rem 0;
    color: #eaf6fb;
    font-size: 1.05rem;
    font-weight: 800;
}
.sintra-info-modal-body {
    margin: 0;
    color: #b9d4df;
    line-height: 1.55;
    font-size: 0.92rem;
}
@media (max-width: 640px) {
    #sintraForm .opt-btn-group {
        grid-template-columns: 1fr;
    }
    #sintraForm .opt-btn-group-sintra-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    #sintraForm .option-grid-3x2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    #sintraForm .sintra-dim-grid .opt-btn-others {
        grid-column: 1 / -1;
        justify-self: center;
        width: calc(50% - 0.3rem);
        max-width: 100%;
    }
    #sintraForm .need-qty-row {
        flex-direction: column;
        align-items: stretch;
    }
    #sintraForm .need-qty-qty {
        width: 100%;
    }
    #sintraForm .need-qty-qty .qty-control-shopee {
        width: 100%;
    }
    .tshirt-actions-row {
        flex-direction: column;
        align-items: stretch;
    }
    .tshirt-btn {
        width: 100%;
    }
}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
