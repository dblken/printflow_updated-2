<?php
/**
 * Sintraboard Standees - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = trim($_POST['branch_id'] ?? '1');
    $size = trim($_POST['size'] ?? ''); $with_stand = trim($_POST['with_stand'] ?? '');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1); $notes = trim($_POST['notes'] ?? '');
    if (empty($size) || empty($needed_date) || $quantity < 1) {
        $error = 'Please fill in Size and Quantity.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    }

    if (empty($error)) {
        // Process file for session
        $tmp_dir = service_order_temp_dir();
        $db_data = file_get_contents($_FILES['design_file']['tmp_name']);
        $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
        $tmp_filename = uniqid('standee_') . '.' . $ext;
        $tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
        file_put_contents($tmp_path, $db_data);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['design_file']['tmp_name']);
        finfo_close($finfo);

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $product_id = 0; // Service order
        $item_key = 'stand_' . time();
        
        $_SESSION['cart'][$item_key] = [
            'product_id'     => $product_id,
            'source_page'    => 'services',
            'branch_id'      => $branch_id,
            'name'           => 'Sintraboard Standees',
            'category'       => 'Sintraboard Standees',
            'price'          => 0, // Determined at review or staff side
            'quantity'       => $quantity,
            'image'          => '🕴️',
            'customization'  => [
                'Size' => $size,
                'With_Stand' => $with_stand ?: 'No',
                'needed_date' => $needed_date
            ],
            'design_notes'   => $notes,
            'design_tmp_path'=> $tmp_path,
            'design_mime'    => $mime,
            'design_name'    => $_FILES['design_file']['name'],
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
$page_title = 'Order Sintraboard Standees - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$qty_default = max(1, min(999, (int)($_GET['qty'] ?? 1)));
?>
<div class="min-h-screen py-8 standee-order-page">
    <div class="container mx-auto px-4 standee-order-container">
        <h1 class="text-2xl font-bold mb-6 standee-page-title">Sintraboard Standees</h1>
        <?php if ($error): ?><div class="standee-form-error mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card standee-order-card">
            <form method="POST" enctype="multipart/form-data" id="standeeForm" class="standee-order-form" novalidate>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Size *</label>
                    <input type="text" name="size" class="input-field" required placeholder="e.g. 22x28 inches" value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">With Stand? *</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap"><input type="radio" name="with_stand" value="Yes" required> <span>Yes</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="with_stand" value="No"> <span>No</span></label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF – max 5MB)</label>
                    <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field standee-file-input" required>
                </div>

                <div class="mb-4" id="standee-need-qty-card">
                    <div class="need-qty-row">
                        <div class="need-qty-date">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="standee_needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>">
                        </div>
                        <div class="need-qty-qty">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="qty-control">
                                <button type="button" class="qty-btn" onclick="standeeQtyDown()">−</button>
                                <input type="number" id="standee-qty" name="quantity" min="1" max="999" required value="<?php echo $qty_default; ?>" oninput="standeeQtyClamp()">
                                <button type="button" class="qty-btn" onclick="standeeQtyUp()">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field standee-notes" placeholder="Any special instructions..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
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
function standeeQtyUp() { const i = document.getElementById('standee-qty'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function standeeQtyDown() { const i = document.getElementById('standee-qty'); i.value = Math.max(1, (parseInt(i.value) || 1) - 1); }
function standeeQtyClamp() { const i = document.getElementById('standee-qty'); let v = parseInt(i.value) || 1; i.value = Math.max(1, Math.min(999, v)); }
document.querySelectorAll('#standeeForm .opt-btn-wrap').forEach(function(w) {
    w.addEventListener('click', function() {
        var name = w.querySelector('input[type="radio"]').name;
        document.querySelectorAll('#standeeForm input[name="' + name + '"]').forEach(function(r) {
            var p = r.closest('.opt-btn-wrap'); if (p) p.classList.remove('active');
        });
        var inp = w.querySelector('input[type="radio"]'); if (inp) { inp.checked = true; w.classList.add('active'); }
    });
});
</script>

<style>
.standee-order-container { max-width: 640px; }
.standee-page-title { color: #eaf6fb !important; }
.standee-form-error { padding: 0.85rem 1rem; border-radius: 10px; border: 1px solid rgba(248, 113, 113, 0.45); background: rgba(127, 29, 29, 0.25); color: #fecaca !important; font-size: 0.875rem; font-weight: 600; }
.standee-order-card.card { background: rgba(10, 37, 48, 0.55); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 1.25rem; box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35); }
#standeeForm.standee-order-form { display: flex; flex-direction: column; gap: 1rem; color-scheme: dark; }
#standeeForm .mb-4 { margin-bottom: 0 !important; padding: 1rem; background: rgba(10, 37, 48, 0.48); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 12px; backdrop-filter: blur(4px); }
#standeeForm label.block { font-size: 0.95rem !important; font-weight: 700 !important; color: #d9e6ef !important; margin-bottom: 0.55rem !important; }
#standeeForm .input-field { min-height: 44px; padding: 0.72rem 0.9rem; border-radius: 10px; font-size: 0.95rem; width: 100%; box-sizing: border-box; background: rgba(13, 43, 56, 0.92) !important; border: 1px solid rgba(83, 197, 224, 0.26) !important; color: #e9f6fb !important; box-shadow: none !important; }
#standeeForm .input-field::placeholder { color: #a9c1cd !important; }
#standeeForm .input-field:focus { background: rgba(16, 52, 67, 0.98) !important; border-color: #53c5e0 !important; box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important; outline: none !important; }
#standeeForm .input-field[type="date"]::-webkit-calendar-picker-indicator { filter: brightness(0) invert(1); cursor: pointer; }
#standeeForm select.input-field option { background: #0a2530 !important; color: #f8fafc !important; }
.standee-file-input { padding-top: 8px !important; height: auto !important; min-height: 42px; }

#standeeForm .opt-btn-group { display: flex !important; flex-wrap: wrap !important; justify-content: center !important; gap: 0.5rem; }
#standeeForm .opt-btn-wrap { min-height: 44px; padding: 0.65rem 1rem; display: flex; align-items: center; justify-content: center; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.86rem; min-width: 120px; background: rgba(255, 255, 255, 0.04) !important; border: 1px solid rgba(83, 197, 224, 0.2) !important; color: #d2e7f1 !important; transition: all 0.2s; }
#standeeForm .opt-btn-wrap:hover { background: rgba(83, 197, 224, 0.12) !important; border-color: rgba(83, 197, 224, 0.5) !important; }
#standeeForm .opt-btn-wrap:has(input:checked), #standeeForm .opt-btn-wrap.active { background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important; border-color: #53c5e0 !important; color: #f8fcff !important; }
#standeeForm .opt-btn-wrap input { margin-right: 0.5rem; }

.need-qty-row { display: flex; gap: 1rem; align-items: flex-start; }
.need-qty-date, .need-qty-qty { flex: 1; min-width: 0; }

.qty-control { display: flex; align-items: center; height: 44px; border: 1px solid rgba(83, 197, 224, 0.24); border-radius: 10px; overflow: hidden; background: rgba(13, 43, 56, 0.92); transition: border-color 0.2s, box-shadow 0.2s; width: 100%; }
.qty-control:focus-within { border-color: #53c5e0; box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16); }
.qty-btn { flex: 0 0 44px; height: 100%; border: none; background: rgba(83, 197, 224, 0.12); color: #d8edf5; font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; outline: none; }
.qty-btn:hover { background: rgba(83, 197, 224, 0.25); color: #fff; }
.qty-btn:active { background: rgba(83, 197, 224, 0.35); }
.qty-control input { flex: 1; border: none; text-align: center; background: transparent; color: #fff; font-weight: 700; font-size: 1rem; outline: none; -moz-appearance: textfield; }
.qty-control input::-webkit-inner-spin-button, .qty-control input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

.standee-notes { overflow-y: auto; resize: vertical; min-height: 110px; max-height: 220px; scrollbar-width: thin; scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08); }

.tshirt-actions-row { display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 1.1rem; flex-wrap: wrap; }
.tshirt-btn { height: 46px; min-width: 150px; padding: 0 1.15rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; text-decoration: none; font-size: 0.9rem; font-weight: 700; transition: all 0.2s; }
.tshirt-btn-secondary { background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(83, 197, 224, 0.28) !important; color: #d9e6ef !important; }
.tshirt-btn-secondary:hover { background: rgba(83, 197, 224, 0.14) !important; border-color: rgba(83, 197, 224, 0.52) !important; color: #fff !important; }
.tshirt-btn-primary { border: none; background: linear-gradient(135deg, #53c5e0, #32a1c4) !important; color: #fff !important; text-transform: uppercase; cursor: pointer; box-shadow: 0 10px 22px rgba(50, 161, 196, 0.3); }
.tshirt-btn:active { transform: translateY(1px) scale(0.99); }

@media (max-width: 640px) {
    .need-qty-row { flex-direction: column; align-items: stretch; }
    .tshirt-actions-row { flex-direction: column; align-items: stretch; }
    .tshirt-btn { width: 100%; }
}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
