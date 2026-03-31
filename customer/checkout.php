<?php
/**
 * Checkout Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/JobOrderService.php';

require_role('Customer');

$cart_items = $_SESSION['cart'] ?? [];

if (empty($cart_items)) {
    redirect('cart.php');
}

$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

$customer_id = get_user_id();
$customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];

// Fetch cancel count for downpayment check (needed on both GET and POST)
$cancel_count = get_customer_cancel_count($customer_id);
$is_restricted = is_customer_restricted($customer_id);
$customer_type = $customer['customer_type'] ?? 'new';

// Handle Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    global $conn; // needed for send_long_data BLOB insertion
    
    // Check restriction AGAIN at submission
    $cancel_count = get_customer_cancel_count($customer_id);
    $is_restricted = is_customer_restricted($customer_id);
    
    if ($is_restricted) {
        $error = "🚫 Your account is restricted from placing new orders.";
    } elseif (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        // Pricing and payment are determined AFTER staff review.
        // The checkout page does not collect payment choice from the customer initially.
        // Staff will set the price and move to 'To Pay' status when ready.
        $downpayment_amount = 0;
        $payment_type = 'tbd'; 
        $payment_status = 'Unpaid';

        // Start Transaction (if supported, otherwise manual checks)
        // 1. Create Order
        // Extract branch_id from the first item in the cart or from the POST selector
        $branch_id = (int)($_POST['order_branch_id'] ?? 1);
        
        if (!empty($cart_items)) {
            foreach ($cart_items as $item) {
                if (!empty($item['branch_id'])) {
                    $branch_id = (int)$item['branch_id'];
                    break;
                }
                if (isset($item['customization']['Branch_ID'])) {
                    $branch_id = (int)$item['customization']['Branch_ID'];
                    break;
                }
            }
        }

        $notes = $_POST['notes'] ?? null;
        
        // Determine order type based on cart items
        $order_type = 'product';
        $reference_id = null;
        foreach ($cart_items as $item) {
            if ($reference_id === null && !empty($item['product_id'])) {
                $reference_id = $item['product_id'];
            }
            if (($item['type'] ?? '') === 'Service' || !empty($item['customization'])) {
                $order_type = 'custom';
                break;
            }
        }
        
        $order_sql = "INSERT INTO orders (customer_id, branch_id, reference_id, order_date, total_amount, downpayment_amount, status, payment_status, payment_type, notes, order_type) 
                      VALUES (?, ?, ?, NOW(), ?, ?, 'Pending Review', ?, ?, ?, ?)";

        $payment_method = $_POST['payment_method'] ?? 'pay_later';
        
        // Removed payment_method from query as column doesn't exist
        $order_id = db_execute($order_sql, 'iiiddssss', [$customer_id, $branch_id, $reference_id, $total, $downpayment_amount, $payment_status, $payment_type, $notes, $order_type]);
        
        if ($order_id) {
            // 2. Insert Order Items (design stored as LONGBLOB, never on disk)
            $inserted_order_item_ids = [];
            foreach ($cart_items as $pid => $item) {
                // Determine service_type for better display in history/notifications
                $custom = $item['customization'] ?? [];
                if (empty($custom['service_type']) && !empty($item['name']) && ($item['type'] ?? '') === 'Service') {
                    $custom['service_type'] = $item['name'];
                }
                
                $custom_data    = json_encode($custom);
                $design_binary  = null;
                $design_mime    = $item['design_mime']   ?? null;
                $design_name    = $item['design_name']   ?? null;

                // Read binary from temp file (session only stores path, not raw bytes)
                if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) {
                    $design_binary = file_get_contents($item['design_tmp_path']);
                }

                if ($design_binary) {
                    // INSERT with BLOB using send_long_data
                    $item_stmt = $conn->prepare(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, design_image, design_image_mime, design_image_name)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    if ($item_stmt) {
                        $null = NULL;
                        $item_stmt->bind_param('iiidsbss',
                            $order_id,
                            $item['product_id'],
                            $item['quantity'],
                            $item['price'],
                            $custom_data,
                            $null,          // placeholder for BLOB
                            $design_mime,
                            $design_name
                        );
                        $item_stmt->send_long_data(5, $design_binary);
                        $item_stmt->execute();
                        $inserted_order_item_ids[$pid] = $conn->insert_id;
                        $item_stmt->close();
                    }
                } else {
                    // No design uploaded — insert without BLOB
                    $inserted_order_item_ids[$pid] = db_execute(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data)
                         VALUES (?, ?, ?, ?, ?)",
                        'iiids',
                        [$order_id, $item['product_id'], $item['quantity'], $item['price'], $custom_data]
                    );
                }
            }
            
            // 3. Clean up temp design files and clear Cart
            foreach ($cart_items as $ci) {
                if (!empty($ci['design_tmp_path']) && file_exists($ci['design_tmp_path'])) {
                    @unlink($ci['design_tmp_path']);
                }
            }
            
            // 4. Auto-create Job Orders for Production Workflow
            foreach ($cart_items as $pid => $item) {
                // Determine service type accurately for ENUM matching
                $service_type = 'Tarpaulin Printing'; // Default
                $cat_lower = strtolower(($item['category'] ?? '') . ' ' . ($item['name'] ?? ''));

                if (strpos($cat_lower, 'tarpaulin') !== false) {
                    $service_type = 'Tarpaulin Printing';
                } elseif (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'shirt') !== false) {
                    $service_type = 'T-shirt Printing';
                } elseif (strpos($cat_lower, 'reflectorized') !== false) {
                    $service_type = 'Reflectorized (Subdivision Stickers/Signages)';
                } elseif (strpos($cat_lower, 'transparent') !== false) {
                    $service_type = 'Transparent Stickers';
                } elseif (strpos($cat_lower, 'glass') !== false || strpos($cat_lower, 'wall') !== false || strpos($cat_lower, 'frosted') !== false) {
                    $service_type = 'Glass Stickers / Wall / Frosted Stickers';
                } elseif (strpos($cat_lower, 'sintraboard') !== false && (strpos($cat_lower, 'standee') !== false || strpos($cat_lower, 'stand') !== false)) {
                    $service_type = 'Sintraboard Standees';
                } elseif (strpos($cat_lower, 'sintraboard') !== false) {
                    $service_type = 'Stickers on Sintraboard';
                } elseif (strpos($cat_lower, 'sticker') !== false || strpos($cat_lower, 'decal') !== false) {
                    $service_type = 'Decals/Stickers (Print/Cut)';
                } elseif (strpos($cat_lower, 'souvenir') !== false) {
                    $service_type = 'Souvenirs';
                } elseif (strpos($cat_lower, 'layout') !== false) {
                    $service_type = 'Layouts';
                }
                
                // Parse dimensions from customization data
                $custom = $item['customization'] ?? [];
                $dimensions = $custom['dimensions'] ?? $custom['Size'] ?? '';
                $width_ft = 0; $height_ft = 0;
                if ($dimensions && (strpos($dimensions, 'x') !== false || strpos($dimensions, '×') !== false)) {
                    $d_parts = preg_split('/[x×]/', strtolower($dimensions));
                    $width_ft  = (float)(trim($d_parts[0] ?? 0));
                    $height_ft = (float)(trim($d_parts[1] ?? 0));
                }
                
                $job_title = get_service_name_from_customization($custom, $item['name'] ?? $service_type);
                $job_qty   = (int)($item['quantity'] ?? 1);
                $oi_id     = $inserted_order_item_ids[$pid] ?? null;

                // Use JobOrderService for robust creation
                try {
                    JobOrderService::createOrder([
                        'order_id'        => $order_id,
                        'customer_id'     => $customer_id,
                        'job_title'       => $job_title,
                        'service_type'    => $service_type,
                        'width_ft'        => $width_ft,
                        'height_ft'       => $height_ft,
                        'quantity'        => $job_qty,
                        'total_sqft'      => $width_ft * $height_ft * $job_qty,
                        'price_per_sqft'  => null,
                        'price_per_piece' => null,
                        'estimated_total' => $item['price'] * $job_qty,
                        'notes'           => $notes,
                        'due_date'        => null,
                        'priority'        => 'NORMAL',
                        'artwork_path'    => null,
                        'created_by'      => null,
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to create job order for item in Order #$order_id: " . $e->getMessage());
                }
            }
            
            unset($_SESSION['cart']);
            
            // 5. Notification
            $first_item_custom = !empty($inserted_order_item_ids) ? db_query("SELECT customization_data FROM order_items WHERE order_id = ? LIMIT 1", 'i', [$order_id]) : [];
            $srv_name = 'Service Order';
            if (!empty($first_item_custom)) {
                $custom_data = json_decode($first_item_custom[0]['customization_data'] ?? '[]', true);
                $srv_name = get_service_name_from_customization($custom_data, 'Service Order');
            }
            create_notification($customer_id, 'Customer', "Order for {$srv_name} placed successfully!", 'Order', true, false, $order_id);
            notify_staff_new_order((int)$order_id, (string)($customer['first_name'] ?? 'Customer'));
            
            $_SESSION['success'] = "Your order for {$srv_name} has been placed successfully! Our team will review it shortly. You can track the status here.";
            
            // Redirect to the new order's details page
            redirect("order_details.php?id=$order_id");
        } else {
            $error = "Failed to place order. Please try again.";
        }
    } else {
        $error = "Invalid request.";
    }
}

$page_title = 'Checkout - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .order-container { max-width: 650px; margin: 0 auto; }
    .compact-card { padding: 1.25rem !important; }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 order-container">
        <h1 class="ct-page-title" style="text-align: center; margin-bottom: 2rem;">Checkout</h1>

        <form method="POST">
            <?php echo csrf_field(); ?>
            
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <?php if (isset($error)): ?>
                    <div class="alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- 1. Order Summary -->
                <div class="card compact-card">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:#111827; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                        <span>🛒</span> Order Summary
                    </h2>
                    <div style="margin-bottom:1rem; display:flex; flex-direction:column; gap:0.75rem;">
                        <?php foreach ($cart_items as $item):
                            $item_total     = $item['price'] * $item['quantity'];
                            $custom         = $item['customization'] ?? [];
                            $design_preview = null;
                            if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
                                $bin = @file_get_contents($item['design_tmp_path']);
                                if ($bin) $design_preview = 'data:' . $item['design_mime'] . ';base64,' . base64_encode($bin);
                            }
                        ?>
                            <div style="border:1px solid #f1f5f9; border-radius:10px; padding:0.75rem; display:flex; gap:0.75rem; align-items:flex-start; background:#fff;">
                                <div style="flex-shrink:0; width:50px; height:50px; border-radius:8px; overflow:hidden; background:#f9fafb; border:1px solid #e5e7eb; display:flex; align-items:center; justify-content:center;">
                                    <?php if ($design_preview): ?>
                                        <img src="<?php echo $design_preview; ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <span style="font-size:1.5rem;">📦</span>
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:700; font-size:0.85rem; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div style="font-size:0.7rem; color:#6b7280; margin-top:2px;">Qty: <?php echo (int)$item['quantity']; ?></div>
                                </div>
                                <!-- Price column hidden -->
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="border-top:1px solid #f3f4f6; padding-top:1rem; display:flex; flex-direction:column; gap:0.5rem;">
                        <!-- Pricing Notice (replaces subtotal/total) -->
                        <div style="margin-top:0.5rem; background:linear-gradient(135deg,#f0f9ff,#e0f2fe); border:1px solid #bae6fd; border-left:4px solid #0ea5e9; border-radius:10px; padding:14px 16px; display:flex; gap:12px; align-items:flex-start;">
                            <span style="font-size:1.25rem; flex-shrink:0;">ℹ️</span>
                            <div>
                                <div style="font-size:0.82rem; font-weight:700; color:#0c4a6e; margin-bottom:3px;">Price will be confirmed by the shop</div>
                                <div style="font-size:0.75rem; color:#0369a1; line-height:1.5;">Your order will be reviewed and priced by our team. Payment options will be available once your order reaches the <strong>To Pay</strong> stage.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Contact Information -->
                <div class="card compact-card">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:1rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                        <span>👤</span> Contact Information
                    </h2>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Full Name</label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>" disabled style="background:#f9fafb; font-size:0.85rem; padding:8px 12px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Email Address</label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled style="background:#f9fafb; font-size:0.85rem; padding:8px 12px;">
                        </div>
                        <div style="grid-column:span 2;">
                            <label style="display:block; font-size:0.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Phone Number</label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['contact_number']); ?>" disabled style="background:#f9fafb; font-size:0.85rem; padding:8px 12px;">
                        </div>
                    </div>
                </div>

                <!-- 3. Branch Selection -->
                <div class="card compact-card">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:1rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                        <span>📍</span> Select Branch
                    </h2>
                    <?php 
                    $branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'"); 
                    $preset_branch = 1;
                    if (!empty($cart_items)) {
                        foreach($cart_items as $ci) {
                            if (!empty($ci['branch_id'])) { $preset_branch = $ci['branch_id']; break; }
                        }
                    }
                    ?>
                    <select name="order_branch_id" class="input-field" style="font-size:0.85rem; padding:8px 12px;" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($b['id'] == $preset_branch) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Payment Policy -->
                <div class="card compact-card">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:#111827; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                        <span>💳</span> Payment Policy
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem;">
                        <label style="display: flex; flex-direction: column; gap: 4px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="radio" name="payment_choice" value="full" style="width: 16px; height: 16px;">
                                <span style="font-weight: 700; font-size: 0.85rem; color: #1f2937;">Full (100%)</span>
                            </div>
                            <span style="font-size: 0.7rem; color: #6b7280; padding-left:24px;">Pay <?php echo format_currency($total); ?></span>
                        </label>

                        <label style="display: flex; flex-direction: column; gap: 4px; padding: 10px; border: 2px solid #4F46E5; background: #f5f3ff; border-radius: 10px; cursor: pointer;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="radio" name="payment_choice" value="half" checked style="width: 16px; height: 16px;">
                                <span style="font-weight: 700; font-size: 0.85rem; color: #4F46E5;">Half (50%)</span>
                            </div>
                            <span style="font-size: 0.7rem; color: #6b7280; padding-left:24px;">Pay <?php echo format_currency($total * 0.5); ?></span>
                        </label>
                    </div>
                </div>

                <!-- 5. Order Notes -->
                <div class="card compact-card">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:0.75rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                        <span>📝</span> Order Notes
                    </h2>
                    <textarea name="notes" class="input-field" style="width:100%; min-height:80px; resize:vertical; font-size:0.85rem; padding:10px;" placeholder="Add special instructions for your entire order..."></textarea>
                </div>

                <!-- 6. Final Actions -->
                <div style="margin-top:0.5rem; text-align:center;">
                    <button type="submit" name="place_order" class="btn-primary" style="width:100%; padding:14px; font-weight:700; font-size:1.1rem; border-radius:12px; box-shadow:0 4px 6px -1px rgba(79, 70, 229, 0.2);">Place Order</button>
                    <a href="cart.php" style="display:inline-block; margin-top:1.25rem; font-size:0.875rem; color:#6b7280; text-decoration:none; font-weight:600; padding:8px 16px; transition:all 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">Returns to Cart</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
