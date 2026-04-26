<?php
/**
 * Customer Design Upload Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();
$error = '';
$success = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $design_notes = sanitize($_POST['design_notes'] ?? '');
        
        // Verify order belongs to customer
        $order = db_query("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
        
        if (empty($order)) {
            $error = 'Invalid order';
        } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a file to upload';
        } else {
            $file     = $_FILES['design_file'];
            $max_size = 50 * 1024 * 1024; // 50 MB

            if ($file['size'] > $max_size) {
                $error = 'File size exceeds the 50 MB limit.';
            } else {
                // 1. Extension allowlist (case-insensitive) — never trust browser filename alone
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'psd', 'ai'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_extensions, true)) {
                    $error = 'Invalid file type. Allowed formats: JPG, PNG, PDF, PSD, AI';
                } else {
                    // 2. Server-side MIME detection — NEVER rely on browser-supplied $_FILES['type']
                    $finfo         = finfo_open(FILEINFO_MIME_TYPE);
                    $detected_mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    // Per-extension MIME allowlist (tighter than a global allow-all)
                    $mime_ok = false;
                    if (in_array($ext, ['jpg', 'jpeg'], true)) {
                        $mime_ok = ($detected_mime === 'image/jpeg');
                    } elseif ($ext === 'png') {
                        $mime_ok = ($detected_mime === 'image/png');
                    } elseif ($ext === 'pdf') {
                        $mime_ok = ($detected_mime === 'application/pdf');
                    } elseif ($ext === 'psd') {
                        // PSD MIME varies by OS/libmagic version
                        $mime_ok = in_array($detected_mime, [
                            'image/vnd.adobe.photoshop',
                            'application/x-photoshop',
                            'application/octet-stream',
                        ], true);
                    } elseif ($ext === 'ai') {
                        $mime_ok = in_array($detected_mime, [
                            'application/postscript',
                            'application/illustrator',
                            'application/octet-stream',
                        ], true);
                    }

                    if (!$mime_ok) {
                        $error = 'File content does not match the expected format. Please upload a valid ' . strtoupper($ext) . ' file.';
                    } else {
                        // 3. Cryptographically random filename — prevents enumeration & path issues
                        $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
                        $upload_path = __DIR__ . '/../uploads/designs/' . $filename;

                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            // Save to database
                            db_execute(
                                "INSERT INTO order_designs (order_id, file_path, notes, status, uploaded_at) VALUES (?, ?, ?, 'Pending Approval', NOW())",
                                'iss',
                                [$order_id, '/uploads/designs/' . $filename, $design_notes]
                            );

                            // Notify all admins/staff of the new design upload
                            $designAdmins = db_query("SELECT user_id, role FROM users WHERE role IN ('Admin','Manager') AND status = 'Activated'", '', []);
                            foreach ((array)$designAdmins as $u) {
                                create_notification((int)$u['user_id'], 'User', "New design uploaded for Order #{$order_id}", 'Design', false, false, $order_id);
                            }
                            $designStaff = db_query("SELECT user_id FROM users WHERE role = 'Staff' AND status = 'Activated'", '', []);
                            foreach ((array)$designStaff as $u) {
                                create_notification((int)$u['user_id'], 'User', "New design uploaded for Order #{$order_id}", 'Design', false, false, $order_id);
                            }

                            $success = 'Design uploaded successfully! Awaiting approval.';
                        } else {
                            $error = 'Failed to upload file. Please try again.';
                        }
                    }
                }
            }
        }
    }
}

// Get customer's orders
$orders = db_query("SELECT order_id, order_date, status FROM orders WHERE customer_id = ? AND status NOT IN ('Completed', 'Cancelled') ORDER BY order_date DESC", 'i', [$customer_id]);

$page_title = 'Upload Design - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">Upload Custom Design</h1>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Order *</label>
                        <select name="order_id" class="input-field" required>
                            <option value="">Choose an order</option>
                            <?php foreach ($orders as $order): ?>
                                <option value="<?php echo $order['order_id']; ?>">
                                    Order #<?php echo $order['order_id']; ?> - <?php echo format_date($order['order_date']); ?> (<?php echo $order['status']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Design File *</label>
                        <input 
                            type="file" 
                            name="design_file" 
                            class="input-field" 
                            accept=".jpg,.jpeg,.png,.pdf,.psd"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, PDF, PSD (Max 50MB)</p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Design Notes (Optional)</label>
                        <textarea 
                            name="design_notes" 
                            class="input-field" 
                            rows="4"
                            placeholder="Any special instructions or notes about your design..."
                        ></textarea>
                    </div>

                    <button type="submit" class="btn-primary w-full">Upload Design</button>
                </form>
            </div>

            <!-- Upload Guidelines -->
            <div class="card mt-6 bg-blue-50 border border-blue-200">
                <h3 class="font-bold mb-2">Design Upload Guidelines:</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li>• Ensure your design is in high resolution (at least 300 DPI)</li>
                    <li>• Use CMYK color mode for printing accuracy</li>
                    <li>• Include 0.25" bleed for designs requiring edge-to-edge printing</li>
                    <li>• Convert all text to outlines or embed fonts</li>
                    <li>• File size should not exceed 50MB</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
