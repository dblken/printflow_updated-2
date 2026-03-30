<?php
/**
 * Edit Order Page
 * Allows customer to modify order during For Revision phase
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$order_id = (int)($_GET['id'] ?? 0);
$customer_id = get_user_id();

if (!$order_id) {
    redirect('orders.php');
}

// Get order
$order_result = db_query("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
if (empty($order_result)) {
    redirect('orders.php');
}
$order = $order_result[0];

// Only allow editing in 'For Revision' status
if ($order['status'] !== 'For Revision') {
    $_SESSION['error'] = "This order is not in a revisable state.";
    redirect("order_details.php?id=$order_id");
}

// Get items
$items = db_query("
    SELECT oi.*, p.name as product_name, p.category, p.description as product_desc
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

// Handle Resubmission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resubmit_order'])) {
    global $conn;

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Invalid security token");
    }

    $all_success = true;

    // Process each item modification
    foreach ($items as $item) {
        $item_id = $item['order_item_id'];
        
        // Collect customization data
        $customization = [];
        $prefix = "item_{$item_id}_";
        foreach ($_POST as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $clean_key = str_replace($prefix, '', $key);
                $customization[$clean_key] = sanitize($value);
            }
        }
        $custom_json = json_encode($customization);

        // Handle File Upload for this item
        $file_field = "design_{$item_id}";
        $design_binary = null;
        $design_mime = $item['design_image_mime'];
        $design_name = $item['design_image_name'];

        if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES[$file_field]['tmp_name'];
            $file_name = $_FILES[$file_field]['name'];
            $file_size = $_FILES[$file_field]['size'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, ['jpg', 'jpeg', 'png']) && $file_size <= 5 * 1024 * 1024) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file_tmp);
                finfo_close($finfo);

                if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'])) {
                    $design_binary = file_get_contents($file_tmp);
                    $design_mime = $mime;
                    $design_name = $file_name;
                }
            }
        }

        // Update Order Item
        if ($design_binary) {
            $stmt = $conn->prepare("UPDATE order_items SET customization_data = ?, design_image = ?, design_image_mime = ?, design_image_name = ? WHERE order_item_id = ?");
            $null = NULL;
            $stmt->bind_param('sbssi', $custom_json, $null, $design_mime, $design_name, $item_id);
            $stmt->send_long_data(1, $design_binary);
            if (!$stmt->execute()) $all_success = false;
            $stmt->close();
        } else {
            $success = db_execute("UPDATE order_items SET customization_data = ? WHERE order_item_id = ?", 'si', [$custom_json, $item_id]);
            if (!$success) $all_success = false;
        }
    }

    if ($all_success) {
        // Update Order Status.
        // Keep backward compatibility: some databases do not have revision_count yet.
        $hasRevisionCount = !empty(db_query("SHOW COLUMNS FROM orders LIKE 'revision_count'"));
        if ($hasRevisionCount) {
            $update_sql = "UPDATE orders
                           SET status = 'Pending Approval',
                               design_status = 'Revision Submitted',
                               revision_count = COALESCE(revision_count, 0) + 1,
                               updated_at = NOW()
                           WHERE order_id = ?";
        } else {
            $update_sql = "UPDATE orders
                           SET status = 'Pending Approval',
                               design_status = 'Revision Submitted',
                               updated_at = NOW()
                           WHERE order_id = ?";
        }
        db_execute($update_sql, 'i', [$order_id]);

        log_activity($customer_id, 'Order Resubmitted', "Customer resubmitted Order #$order_id after revision.");
        
        // Notify Staff with full revision context
        $customer_row = db_query(
            "SELECT first_name, last_name FROM customers WHERE customer_id = ? LIMIT 1",
            'i',
            [$customer_id]
        );
        $customer_name = 'Customer';
        if (!empty($customer_row)) {
            $customer_name = trim(($customer_row[0]['first_name'] ?? '') . ' ' . ($customer_row[0]['last_name'] ?? ''));
            if ($customer_name === '') {
                $customer_name = 'Customer';
            }
        }

        $service_name = 'Custom Order';
        if (!empty($items)) {
            $first_custom = json_decode($items[0]['customization_data'] ?? '{}', true) ?: [];
            $derived_service = $first_custom['service_type'] ?? ($items[0]['product_name'] ?? '');
            $service_name = normalize_service_name($derived_service, 'Custom Order');
        }
        
        $staff_message = "{$customer_name} resubmitted a revised design for {$service_name}";

        // Notify Staff
        $staff_users = db_query("SELECT user_id, role FROM users WHERE role IN ('Staff', 'Admin', 'Manager') AND status = 'Activated'");
        foreach ($staff_users as $staff) {
            create_notification($staff['user_id'], $staff['role'], $staff_message, 'Order', false, false, $order_id);
        }

        $_SESSION['success'] = "Order #{$order_id} has been resubmitted successfully. Please wait for staff approval.";
        redirect("order_details.php?id=$order_id");
    } else {
        $error = "Failed to update some items. Please try again.";
    }
}

$page_title = "Edit Order #{$order_id}";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:900px;">
        <div style="margin-bottom:2rem;">
            <a href="order_details.php?id=<?php echo $order_id; ?>" class="back-link" style="display:inline-flex; align-items:center; gap:6px; color:#6b7280; margin-bottom:1rem; text-decoration:none;">← Back to Order Details</a>
            <h1 class="ct-page-title">Edit & Resubmit Order #<?php echo $order_id; ?></h1>
            <p style="color:#6b7280; font-size:0.95rem;">Please review the requested changes and update the items below.</p>
        </div>

        <?php if (!empty($order['revision_reason'])): ?>
            <div style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem;">
                <h3 style="color: #92400e; font-weight: 700; font-size: 0.95rem; margin-bottom: 0.5rem;">🚩 Staff Feedback</h3>
                <div style="color: #b45309; font-size: 0.9rem; line-height: 1.5; white-space: pre-wrap;"><?php echo htmlspecialchars($order['revision_reason']); ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert-error" style="margin-bottom:2rem;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

            <?php foreach ($items as $item): 
                $custom_data = json_decode($item['customization_data'], true) ?? [];
                $prefix = "item_{$item['order_item_id']}_";
            ?>
                <div class="card" style="margin-bottom:2rem;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; border-bottom:1px solid #f3f4f6; padding-bottom:1rem;">
                        <div>
                            <h2 style="font-size:1.1rem; font-weight:700; color:#111827;"><?php echo htmlspecialchars($item['product_name']); ?></h2>
                            <p style="font-size:0.85rem; color:#6b7280;">Quantity: <?php echo (int)$item['quantity']; ?> | Category: <?php echo htmlspecialchars($item['category']); ?></p>
                        </div>
                        <div style="background:#f3f4f6; color:#4b5563; font-size:0.75rem; font-weight:600; padding:4px 10px; border-radius:6px;"><?php echo htmlspecialchars($item['category']); ?></div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr; gap:1.5rem;">
                        <!-- Customization Fields Based on Category -->
                        <div style="background:#f9fafb; padding:1.5rem; border-radius:12px; border:1px solid #f3f4f6;">
                            <h3 style="font-size:0.9rem; font-weight:700; color:#374151; margin-bottom:1rem; display:flex; align-items:center; gap:6px;">🛠️ Customization Details</h3>
                            
                            <?php $cat = $item['category']; ?>

                            <?php if ($cat === 'Tarpaulin Printing'): ?>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                                    <div>
                                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Width (ft)</label>
                                        <input type="number" name="<?php echo $prefix; ?>width" value="<?php echo htmlspecialchars($custom_data['width'] ?? ''); ?>" required class="input-field">
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Height (ft)</label>
                                        <input type="number" name="<?php echo $prefix; ?>height" value="<?php echo htmlspecialchars($custom_data['height'] ?? ''); ?>" required class="input-field">
                                    </div>
                                </div>
                                <div style="margin-bottom:1rem;">
                                    <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Finish Type</label>
                                    <select name="<?php echo $prefix; ?>finish_type" class="input-field">
                                        <option value="Matte" <?php echo ($custom_data['finish_type'] ?? '') === 'Matte' ? 'selected' : ''; ?>>Matte</option>
                                        <option value="Glossy" <?php echo ($custom_data['finish_type'] ?? '') === 'Glossy' ? 'selected' : ''; ?>>Glossy</option>
                                    </select>
                                </div>

                            <?php elseif ($cat === 'T-Shirt Printing'): ?>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                                    <div>
                                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Size</label>
                                        <select name="<?php echo $prefix; ?>size" class="input-field">
                                            <?php foreach (['S','M','L','XL','XXL'] as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo ($custom_data['size'] ?? '') === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Color</label>
                                        <input type="text" name="<?php echo $prefix; ?>color" value="<?php echo htmlspecialchars($custom_data['color'] ?? ''); ?>" class="input-field">
                                    </div>
                                </div>
                                <div style="margin-bottom:1rem;">
                                    <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Print Placement</label>
                                    <select name="<?php echo $prefix; ?>placement" class="input-field">
                                        <option value="Front" <?php echo ($custom_data['placement'] ?? '') === 'Front' ? 'selected' : ''; ?>>Front Only</option>
                                        <option value="Back" <?php echo ($custom_data['placement'] ?? '') === 'Back' ? 'selected' : ''; ?>>Back Only</option>
                                        <option value="Both" <?php echo ($custom_data['placement'] ?? '') === 'Both' ? 'selected' : ''; ?>>Front & Back</option>
                                    </select>
                                </div>

                            <?php elseif ($cat === 'Stickers'): ?>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                                    <div>
                                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Shape</label>
                                        <input type="text" name="<?php echo $prefix; ?>shape" value="<?php echo htmlspecialchars($custom_data['shape'] ?? ''); ?>" class="input-field">
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Size</label>
                                        <input type="text" name="<?php echo $prefix; ?>size" value="<?php echo htmlspecialchars($custom_data['size'] ?? ''); ?>" class="input-field">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Common Fields -->
                            <div style="margin-top:1.5rem; padding-top:1rem; border-top:1px dashed #d1d5db;">
                                <div style="margin-bottom:1.5rem;">
                                    <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.6rem;">Design Image</label>
                                    <div style="display:flex; align-items:center; gap:1rem;">
                                        <?php if (!empty($item['design_image'])): ?>
                                            <div style="position:relative; width:80px; height:80px; border-radius:8px; overflow:hidden; border:1px solid #e5e7eb;">
                                                <img src="/printflow/public/serve_design.php?type=order_item&id=<?php echo (int)$item['order_item_id']; ?>" style="width:100%; height:100%; object-fit:cover;" alt="Old Design">
                                                <div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.6); color:white; font-size:0.6rem; text-align:center; padding:2px;">Current</div>
                                            </div>
                                        <?php endif; ?>
                                        <div style="flex:1;">
                                            <input type="file" name="design_<?php echo $item['order_item_id']; ?>" class="input-field" accept=".jpg,.jpeg,.png" style="padding:0.4rem;">
                                            <p style="font-size:0.7rem; color:#6b7280; margin-top:4px;">Upload ONLY if you want to replace the current design.</p>
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-bottom:0.5rem;">
                                    <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.4rem;">Item Notes</label>
                                    <textarea name="<?php echo $prefix; ?>notes" rows="2" class="input-field" placeholder="Any specific instructions for this item..."><?php echo htmlspecialchars($custom_data['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card" style="padding:2rem; text-align:center; background:#f8fafc; border:1px solid #e2e8f0;">
                <h3 style="color:#0f172a; font-size:1.1rem; font-weight:700; margin-bottom:1rem;">Ready to resubmit?</h3>
                <p style="color:#475569; font-size:0.9rem; margin-bottom:1.5rem;">Your order will be sent back to the shop for approval. You won't be able to edit it once resubmitted.</p>
                <button type="submit" name="resubmit_order" class="btn-primary" style="background:#0a2530; color:#ffffff; border:none; padding:1rem 2.5rem; font-weight:800; border-radius:12px; font-size:1rem; cursor:pointer; width:100%; max-width:300px;" onmouseover="this.style.background='#0d3038'" onmouseout="this.style.background='#0a2530'">
                    RESUBMIT ORDER
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
