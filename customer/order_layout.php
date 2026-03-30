<?php
/**
 * Layout Design Service - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = trim($_POST['branch_id'] ?? '1');
    $layout_type = trim($_POST['layout_type'] ?? '');
    $rush = trim($_POST['rush'] ?? '');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($layout_type) || empty($needed_date)) {
        $error = 'Please select type of layout and provide needed date.';
    } else {
        $tmp_dir = service_order_temp_dir();
        $tmp_path = null;
        $mime = null;
        $design_name = null;

        if (isset($_FILES['reference_file']) && $_FILES['reference_file']['error'] === UPLOAD_ERR_OK) {
            $valid = service_order_validate_file($_FILES['reference_file']);
            if ($valid['ok']) {
                $db_data = file_get_contents($_FILES['reference_file']['tmp_name']);
                $ext = pathinfo($_FILES['reference_file']['name'], PATHINFO_EXTENSION);
                $tmp_filename = uniqid('layout_') . '.' . $ext;
                $tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
                file_put_contents($tmp_path, $db_data);
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES['reference_file']['tmp_name']);
                finfo_close($finfo);
                $design_name = $_FILES['reference_file']['name'];
            }
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $item_key = 'layout_' . time();
        
        $_SESSION['cart'][$item_key] = [
            'product_id'     => 0,
            'source_page'    => 'services',
            'branch_id'      => $branch_id,
            'name'           => 'Layout Design Service',
            'category'       => 'Graphic Design',
            'price'          => 0, // Determined after review
            'quantity'       => 1,
            'image'          => '🎨',
            'customization'  => [
                'Layout_Type' => $layout_type,
                'Rush_Order'  => $rush ?: 'No',
                'needed_date' => $needed_date
            ],
            'design_notes'   => $description,
            'design_tmp_path'=> $tmp_path,
            'design_mime'    => $mime,
            'design_name'    => $design_name,
            'reference_tmp_path' => null,
            'reference_mime'     => null,
            'reference_name'     => null
        ];

        if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
            redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
        } else {
            redirect(BASE_URL . '/customer/cart.php');
        }
    }
}
$page_title = 'Layout Design Service - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$layout_types = ['Logo', 'Banner', 'Invitation', 'Poster', 'Other'];
?>
<div class="min-h-screen py-8 layout-order-page">
    <div class="container mx-auto px-4 layout-order-container">
        <h1 class="text-2xl font-bold mb-6 layout-page-title">Layout Design Service</h1>
        <?php if ($error): ?><div class="layout-form-error mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card layout-order-card">
            <form method="POST" enctype="multipart/form-data" id="layoutForm" class="layout-order-form" novalidate>
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

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type of Layout *</label>
                    <div class="opt-btn-group layout-type-grid">
                        <?php foreach ($layout_types as $lt): ?>
                        <label class="opt-btn-wrap"><input type="radio" name="layout_type" value="<?php echo htmlspecialchars($lt); ?>" required <?php echo (($_POST['layout_type'] ?? '') === $lt) ? 'checked' : ''; ?>> <span><?php echo htmlspecialchars($lt); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rush Order? *</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap"><input type="radio" name="rush" value="No" required <?php echo (($_POST['rush'] ?? 'No') === 'No') ? 'checked' : ''; ?>> <span>No</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="rush" value="Yes" <?php echo (($_POST['rush'] ?? '') === 'Yes') ? 'checked' : ''; ?>> <span>Yes</span></label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                    <input type="date" name="needed_date" id="layout_needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="4" class="input-field layout-notes" placeholder="Describe your layout needs..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Reference File (JPG, PNG, PDF – max 5MB) <span class="layout-optional-tag">(Optional)</span></label>
                    <input type="file" name="reference_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field layout-file-input">
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

<script>
document.querySelectorAll('#layoutForm .opt-btn-wrap').forEach(function(w) {
    w.addEventListener('click', function() {
        var inp = w.querySelector('input[type="radio"]');
        if (!inp) return;
        var name = inp.name;
        document.querySelectorAll('#layoutForm input[name="' + name + '"]').forEach(function(r) {
            var p = r.closest('.opt-btn-wrap'); if (p) p.classList.remove('active');
        });
        inp.checked = true;
        w.classList.add('active');
    });
});
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#layoutForm .opt-btn-wrap').forEach(function(w) {
        if (w.querySelector('input:checked')) w.classList.add('active');
    });
});
</script>

<style>
.layout-order-container { max-width: 640px; }
.layout-page-title { color: #eaf6fb !important; }
.layout-form-error { padding: 0.85rem 1rem; border-radius: 10px; border: 1px solid rgba(248, 113, 113, 0.45); background: rgba(127, 29, 29, 0.25); color: #fecaca !important; font-size: 0.875rem; font-weight: 600; }
.layout-order-card.card { background: rgba(10, 37, 48, 0.55); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 1.25rem; box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35); }
#layoutForm.layout-order-form { display: flex; flex-direction: column; gap: 1rem; color-scheme: dark; }
#layoutForm .mb-4 { margin-bottom: 0 !important; padding: 1rem; background: rgba(10, 37, 48, 0.48); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 12px; backdrop-filter: blur(4px); }
#layoutForm label.block { font-size: 0.95rem !important; font-weight: 700 !important; color: #d9e6ef !important; margin-bottom: 0.55rem !important; }
.layout-optional-tag { font-size: 0.75rem; font-weight: 500; color: #9fc6d9; }
#layoutForm .input-field { min-height: 44px; padding: 0.72rem 0.9rem; border-radius: 10px; font-size: 0.95rem; width: 100%; box-sizing: border-box; background: rgba(13, 43, 56, 0.92) !important; border: 1px solid rgba(83, 197, 224, 0.26) !important; color: #e9f6fb !important; box-shadow: none !important; }
#layoutForm .input-field::placeholder { color: #a9c1cd !important; }
#layoutForm .input-field:focus { background: rgba(16, 52, 67, 0.98) !important; border-color: #53c5e0 !important; box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important; outline: none !important; }
#layoutForm .input-field[type="date"]::-webkit-calendar-picker-indicator { filter: brightness(0) invert(1); cursor: pointer; }
#layoutForm select.input-field option { background: #0a2530 !important; color: #f8fafc !important; }
.layout-file-input { padding-top: 8px !important; height: auto !important; min-height: 42px; }
.layout-notes { overflow-y: auto; resize: vertical; min-height: 110px; max-height: 220px; scrollbar-width: thin; scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08); }

#layoutForm .opt-btn-group { display: flex !important; flex-wrap: wrap !important; justify-content: center !important; gap: 0.5rem; }
#layoutForm .layout-type-grid { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; }
#layoutForm .layout-type-grid .opt-btn-wrap:last-child { grid-column: 2; }
#layoutForm .opt-btn-wrap { min-height: 44px; padding: 0.65rem 1rem; display: flex; align-items: center; justify-content: center; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.86rem; min-width: 100px; background: rgba(255, 255, 255, 0.04) !important; border: 1px solid rgba(83, 197, 224, 0.2) !important; color: #d2e7f1 !important; transition: all 0.2s; }
#layoutForm .opt-btn-wrap:hover { background: rgba(83, 197, 224, 0.12) !important; border-color: rgba(83, 197, 224, 0.5) !important; }
#layoutForm .opt-btn-wrap:has(input:checked), #layoutForm .opt-btn-wrap.active { background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important; border-color: #53c5e0 !important; color: #f8fcff !important; }
#layoutForm .opt-btn-wrap input { margin-right: 0.5rem; }

.tshirt-actions-row { display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 1.1rem; flex-wrap: wrap; }
.tshirt-btn { height: 46px; min-width: 150px; padding: 0 1.15rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; text-decoration: none; font-size: 0.9rem; font-weight: 700; transition: all 0.2s; }
.tshirt-btn-secondary { background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(83, 197, 224, 0.28) !important; color: #d9e6ef !important; }
.tshirt-btn-secondary:hover { background: rgba(83, 197, 224, 0.14) !important; border-color: rgba(83, 197, 224, 0.52) !important; color: #fff !important; }
.tshirt-btn-primary { border: none; background: linear-gradient(135deg, #53c5e0, #32a1c4) !important; color: #fff !important; text-transform: uppercase; cursor: pointer; box-shadow: 0 10px 22px rgba(50, 161, 196, 0.3); }
.tshirt-btn:active { transform: translateY(1px) scale(0.99); }

@media (max-width: 640px) {
    #layoutForm .layout-type-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    #layoutForm .layout-type-grid .opt-btn-wrap:last-child { grid-column: 1 / -1; justify-self: center; width: calc(50% - 0.3rem); }
    .tshirt-actions-row { flex-direction: column; align-items: stretch; }
    .tshirt-btn { width: 100%; }
}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
