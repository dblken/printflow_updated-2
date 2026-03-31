<?php
/**
 * Customer Order Creation / Product Details Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$product_id = $_GET['product_id'] ?? 0;
$product = null;

if ($product_id) {
    $result = db_query("SELECT * FROM products WHERE product_id = ? AND status = 'Activated'", 'i', [$product_id]);
    if (!empty($result)) {
        $product = $result[0];
        if (isset($product['category'])) {
            $cat_lower = strtolower($product['category']);
            if (strpos($cat_lower, 'shirt') !== false) {
                $product['category'] = 'T-Shirts';
            } elseif (strpos($cat_lower, 'tarpaulin') !== false) {
                $product['category'] = 'Tarpaulin';
            } elseif (strpos($cat_lower, 'decal') !== false || strpos($cat_lower, 'sticker') !== false) {
                $product['category'] = 'Decals & Stickers';
            } elseif (strpos($cat_lower, 'sintra') !== false) {
                $product['category'] = 'Sintraboard & Standees';
            }
        }
    }
}

if (!$product) {
    // Product not found or not activated
    header("Location: products.php");
    exit;
}

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");

// Handle Add to Cart / Buy Now
$error_msg = '';
$success_msg = '';

$customer_id = get_user_id();
$cancel_count = get_customer_cancel_count($customer_id);
$is_restricted = is_customer_restricted($customer_id);

if ($is_restricted) {
    $error_msg = "🚫 <strong>Account Restricted:</strong> You are currently blocked from placing new orders due to excessive cancellations (7+). Please contact support.";
}

$postedAction = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($postedAction === 'add_to_cart' || $postedAction === 'buy_now' || isset($_POST['add_to_cart']) || isset($_POST['buy_now']))) {
    // CSRF Check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid session. Please refresh the page and try again.";
    } elseif ($is_restricted) {
        $error_msg = "Account restricted. Cannot place order.";
    } else {
        $quantity = (int)$_POST['quantity'];
        $branch_id = (int)($_POST['branch_id'] ?? 1);
        
        // ----------------------------------------------------------------
        // File Upload handling — images stored in session as temp paths
        // ----------------------------------------------------------------
        
        $active_design_field = 'design_upload';
        if (isset($product['category'])) {
            if ($product['category'] === 'T-Shirts') $active_design_field = 'tshirt_design_upload';
            elseif ($product['category'] === 'Tarpaulin') $active_design_field = 'tarp_design_upload';
        }
        
        $files_to_handle = [
            $active_design_field => ['required' => true, 'allowed_ext' => ['jpg', 'jpeg', 'png', 'ai', 'pdf', 'psd']],
            'reference_upload' => ['required' => false, 'allowed_ext' => ['jpg', 'jpeg', 'png']]
        ];

        $uploaded_files = [];

        foreach ($files_to_handle as $field_name => $config) {
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                $file_tmp  = $_FILES[$field_name]['tmp_name'];
                $file_name = $_FILES[$field_name]['name'];
                $file_size = $_FILES[$field_name]['size'];
                $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                // 1. Extension whitelist
                if (!in_array($file_ext, $config['allowed_ext'])) {
                    $error_msg = "Invalid file type for " . ucwords(str_replace('_', ' ', $field_name)) . ". Allowed: " . implode(', ', $config['allowed_ext']);
                    break;
                }
                // 2. File size limit (10MB for design, 5MB for reference)
                $max_size = (strpos($field_name, 'design_upload') !== false) ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
                if ($file_size > $max_size) {
                    $max_mb = $max_size / (1024 * 1024);
                    $error_msg = "File too large (" . ucwords(str_replace('_', ' ', $field_name)) . "). Maximum size is {$max_mb}MB.";
                    break;
                }
                else {
                    // Read binary data
                    $data = file_get_contents($file_tmp);
                    if ($data === false || $data === '') {
                        $error_msg = "Failed to read uploaded file. Please try again.";
                        break;
                    } else {
                        // Store temp file path
                        $tmp_path = tempnam(sys_get_temp_dir(), 'pf_design_');
                        file_put_contents($tmp_path, $data);
                        
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime  = finfo_file($finfo, $file_tmp);
                        finfo_close($finfo);

                        $uploaded_files[$field_name] = [
                            'tmp_path' => $tmp_path,
                            'mime'     => $mime,
                            'name'     => $file_name
                        ];
                    }
                }
            } elseif ($config['required'] && (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE)) {
                $error_msg = "Please upload the required " . ucwords(str_replace('_', ' ', $field_name)) . ".";
                break;
            } elseif (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] !== UPLOAD_ERR_NO_FILE && $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
                $error_msg = "File upload error for " . ucwords(str_replace('_', ' ', $field_name)) . ".";
                break;
            }
        }

        // Collect customization data
        $customization = [];
        $customization['Branch_ID'] = $_POST['branch_id'] ?? '1';
        $category = $product['category'] ?? '';
        
        $allowed_fields = [
            'T-Shirts' => [
                'shirt_source', 'shirt_type', 'shop_shirt_color', 'shop_shirt_size', 
                'client_shirt_color', 'client_shirt_material', 'material_disclaimer',
                'printing_method', 'shirt_placement', 'print_size', 
                'tshirt_custom_width', 'tshirt_custom_height', 'tshirt_design_description'
            ],
            'Tarpaulin' => [
                'tarp_size_option', 'tarp_preset_size', 'tarp_width', 'tarp_height', 'tarp_unit',
                'tarp_material', 'tarp_finish', 'tarp_edges', 'tarp_grommet_option', 
                'tarp_grommet_instructions', 'tarp_design_service', 'tarp_design_description'
            ],
            'Decals & Stickers' => [
                'vehicle_brand', 'vehicle_model', 'vehicle_year', 'placement', 
                'size_option', 'custom_width', 'custom_height', 'material_type', 'design_description'
            ],
            'Sintraboard & Standees' => [
                'width', 'height', 'thickness', 'stand_type', 'lamination', 'cut_type', 'design_description', 'flat_type'
            ],
            'Merchandise' => [
                'design_description'
            ]
        ];

        $current_allowed = $allowed_fields[$category] ?? [];

        foreach ($_POST as $key => $value) {
            if (in_array($key, $current_allowed) && $value !== '') {
                $customization[$key] = sanitize($value);
            }
        }

        if (empty($error_msg) && $quantity > 0) {
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            // Adjust price for specialized products
            $item_price = $product['price'];
            if ($product['category'] === 'T-Shirts') {
                if (isset($_POST['shirt_source']) && $_POST['shirt_source'] === 'Client') {
                    $item_price = 150.00;
                }
            } elseif ($product['category'] === 'Tarpaulin') {
                // Tarpaulin Pricing Logic
                $width = (float)($_POST['tarp_width'] ?? 2);
                $height = (float)($_POST['tarp_height'] ?? 3);
                $unit = $_POST['tarp_unit'] ?? 'ft';
                
                if (isset($_POST['tarp_size_option']) && $_POST['tarp_size_option'] === 'Preset') {
                    $preset = $_POST['tarp_preset_size'] ?? '2x3';
                    $parts = explode('x', $preset);
                    if (count($parts) === 2) {
                        $width = (float)$parts[0];
                        $height = (float)$parts[1];
                        $unit = 'ft';
                    }
                }
                
                // Convert to SqFt if in inches
                $sqft = ($unit === 'in') ? ($width * $height) / 144 : ($width * $height);
                
                $base_rate = (float)$product['price']; // Base rate per sqft
                if ($base_rate <= 0) $base_rate = 25.00; // Default fallback rate
                
                $total_price = $sqft * $base_rate;
                
                // Material Multipliers
                $material = $_POST['tarp_material'] ?? '10oz';
                if ($material === '13oz') $total_price *= 1.25;
                if ($material === '15oz') $total_price *= 1.5;
                
                // Finishing Add-ons
                $edges = $_POST['tarp_edges'] ?? 'Heat Cut Only';
                if ($edges === 'Folded & Sewn Edges') $total_price += ($sqft * 2);
                
                $grommets = $_POST['tarp_grommet_option'] ?? 'No Grommets';
                if ($grommets !== 'No Grommets') $total_price += 10.00; // Small flat fee for grommets
                
                // Design Service
                $design_service = $_POST['tarp_design_service'] ?? 'Ready';
                if ($design_service === 'Requested') $total_price += 50.00; // Fixed layout fee
                
                $item_price = round($total_price, 2);
            } elseif ($product['category'] === 'Decals & Stickers' || $product['category'] === 'Stickers') {
                if (isset($_POST['size_option']) && $_POST['size_option'] === 'Custom Size') {
                    $item_price += 15.00;
                }
            }

            // Unique key per line item
            $item_key = $product_id . '_' . time();

            $_SESSION['cart'][$item_key] = [
                'product_id'     => $product_id,
                'name'           => $product['name'],
                'category'       => $product['category'] ?? '',
                'source_page'    => 'products',
                'branch_id'      => $branch_id,
                'price'          => $item_price,
                'quantity'       => $quantity,
                'image'          => '📦',
                'customization'  => $customization,
                
                // Spec capture (general & specialized)
                'width'          => $_POST['width'] ?? ($_POST['custom_width'] ?? ($_POST['tarp_width'] ?? null)),
                'height'         => $_POST['height'] ?? ($_POST['custom_height'] ?? ($_POST['tarp_height'] ?? null)),
                'thickness'      => $_POST['thickness'] ?? null,
                'stand_type'     => $_POST['stand_type'] ?? ($_POST['flat_type'] ?? null),
                'lamination'     => $_POST['lamination'] ?? null,
                'cut_type'       => $_POST['cut_type'] ?? null,
                'design_notes'   => $_POST['design_description'] ?? ($_POST['tshirt_design_description'] ?? ($_POST['tarp_design_description'] ?? null)),

                // Primary design
                'design_tmp_path' => $uploaded_files[$active_design_field]['tmp_path'] ?? null,
                'design_mime'     => $uploaded_files[$active_design_field]['mime'] ?? null,
                'design_name'     => $uploaded_files[$active_design_field]['name'] ?? null,
                
                // Reference image
                'reference_tmp_path' => $uploaded_files['reference_upload']['tmp_path'] ?? null,
                'reference_mime'     => $uploaded_files['reference_upload']['mime'] ?? null,
                'reference_name'     => $uploaded_files['reference_upload']['name'] ?? null,
            ];
            
            // Redirect
            if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                header("Location: order_review.php?item=" . urlencode($item_key));
            } else {
                header("Location: cart.php");
            }
            exit;
        }
    }
}

$page_title = $product['name'] . ' - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8 bg-gray-50" x-data="orderModal">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="products.php" class="hover:text-blue-600">Products</a>
            <span>/</span>
            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($product['name']); ?></span>
        </div>

        <?php if ($cancel_count >= 3 && !$is_restricted): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 flex gap-3 items-start">
                <span class="text-2xl">⚠️</span>
                <div>
                    <h3 class="text-yellow-800 font-bold text-sm mb-1">Shopping Experience Warning</h3>
                    <p class="text-yellow-700 text-xs leading-relaxed">
                        You have <strong><?php echo $cancel_count; ?></strong> recent cancellations. 
                        <?php if ($cancel_count >= 4): ?>
                            Because you have 4 or more cancellations, <strong>'Pay Later' orders will require a 50% downpayment</strong> to proceed.
                        <?php else: ?>
                            Excessive cancellations may lead to payment restrictions or account suspension.
                        <?php endif; ?>
                        Complete a successful order to reset this counter!
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="sticky top-24">
                    <div class="shopee-main-image-wrap">
                        <?php 
                        $display_img = "";
                        // 1. Try photo_path first
                        if (!empty($product['photo_path'])) {
                            $display_img = $product['photo_path'];
                        }
                        // 2. Try product_image column
                        elseif (!empty($product['product_image']) && file_exists(__DIR__ . "/../" . $product['product_image'])) {
                            $display_img = "/printflow/" . ltrim($product['product_image'], '/');
                        }
                        // 3. Try default image path
                        else {
                            $img_link = "/printflow/public/images/products/product_" . $product['product_id'];
                            $img_path = __DIR__ . "/../public/images/products/product_" . $product['product_id'];
                            if (file_exists($img_path . ".jpg")) {
                                $display_img = $img_link . ".jpg";
                            } elseif (file_exists($img_path . ".png")) {
                                $display_img = $img_link . ".png";
                            }
                        }
                        
                        if ($display_img): ?>
                            <img src="<?php echo htmlspecialchars($display_img); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="shopee-main-image">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-gray-50 text-5xl">📦</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side: Product Details & Form -->
            <div class="shopee-form-section">
                <!-- Product Details Header -->
                <div class="mb-6 pb-6 border-b border-gray-100">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="text-3xl font-bold text-blue-600 mb-4">
                        <?php echo format_currency($product['price']); ?>
                    </div>

                    <div class="text-sm text-gray-600 leading-relaxed mb-4">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                    
                    <?php if ($product['stock_quantity'] <= 0): ?>
                        <div class="p-3 bg-red-50 text-red-700 rounded-lg text-center font-bold">
                            Out of Stock
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($product['stock_quantity'] > 0): ?>
                
                <!-- Multi-Step Form Wrapper -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <!-- Form Header (Steps) -->
                    <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 m-0 uppercase tracking-wide">Product Customization</h2>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="bg-gray-900 text-white text-xs font-bold px-2 py-0.5 rounded" x-text="'STEP ' + step"></span>
                                <p class="text-sm text-gray-500 font-semibold uppercase m-0" 
                                   x-text="(productCategory === 'Sintraboard & Standees' || productCategory === 'Sintraboard Flat') ? 
                                           (step==1?'Board Details':step==2?'Design Upload':step==3?'Stand & Finishing':'Final Review') : 
                                           (productCategory === 'T-Shirts') ? 
                                           (step==1?'Shirt Details':step==2?'Printing Details':step==3?'Design Upload':'Final Review') : 
                                           (productCategory === 'Tarpaulin') ? 
                                           (step==1?'Size & Material':step==2?'Finishing Options':step==3?'Design Details':'Final Review') : 
                                           (step==1?'Vehicle Details':step==2?'Design Upload':step==3?'Size & Options':'Final Review')"></p>
                            </div>
                        </div>
                    </div>

                <div style="height:4px; width:100%; background:#f1f5f9; display:flex;">
                    <div :style="'width: ' + (step/4*100) + '%; transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);'" style="height:100%; background:black;"></div>
                </div>

                                <form method="POST" enctype="multipart/form-data" id="customization-form" style="display:flex; flex-direction:column; flex:1; ">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                    
                                    <!-- Scrollable Form Content -->
                                    <div style="padding:2rem; flex:1;">
                                        
                                        <?php if ($error_msg): ?>
                                            <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius:0; padding:1.25rem; margin-bottom:1.5rem; color: #b91c1c; font-size:0.85rem; display:flex; gap:0.75rem; align-items:flex-start; border-left:4px solid #b91c1c;" id="modal-error-gate">
                                                 <span style="font-size:1.1rem;">🚫</span>
                                                 <div style="font-weight:600;"><?php echo $error_msg; ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Step 1: Specific Details -->
                                        <div x-show="step === 1">
                                            <div style="margin-bottom:1.5rem; background:#f9fafb; padding:1.25rem; border:1px solid #e5e7eb;">
                                                <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Select Branch *</label>
                                                <select name="branch_id" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;" required>
                                                    <?php foreach($branches as $b): ?>
                                                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Sintra Board Fields (Standees & Flat) -->
                                            <div x-show="productCategory === 'Sintraboard & Standees' || productCategory === 'Sintraboard Flat'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Size & Board Details</h3>
                                                </div>
                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Width (inches) *</label>
                                                        <input type="number" name="width" step="0.01" min="0.01" class="input-field" placeholder="e.g. 24" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory.includes('Sintraboard')">
                                                    </div>
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Height (inches) *</label>
                                                        <input type="number" name="height" step="0.01" min="0.01" class="input-field" placeholder="e.g. 36" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory.includes('Sintraboard')">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Thickness *</label>
                                                    <select name="thickness" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory.includes('Sintraboard')">
                                                        <option value="" disabled selected>Select Thickness</option>
                                                        <option value="3mm">3mm</option>
                                                        <option value="5mm">5mm</option>
                                                        <option value="10mm">10mm</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- T-Shirt Details -->
                                            <div x-show="productCategory === 'T-Shirts'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Shirt Details</h3>
                                                </div>

                                                <div style="background:#f9fafb; padding:1.25rem; border-radius:0; border:1px solid #e5e7eb;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Shirt Source *</label>
                                                    <div style="display:flex; gap:0.75rem;">
                                                        <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:0.75rem; background:white; border:2px solid; border-radius:0; cursor:pointer; font-weight:700; transition:all 0.2s; text-transform:uppercase; font-size:0.85rem;" :style="shirtSource === 'Shop' ? 'border-color:black; color:black' : 'border-color:#e5e7eb; color:#6b7280'">
                                                            <input type="radio" name="shirt_source" value="Shop" x-model="shirtSource" class="sr-only">
                                                            Shop-Provided Shirt
                                                        </label>
                                                        <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:0.75rem; background:white; border:2px solid; border-radius:0; cursor:pointer; font-weight:700; transition:all 0.2s; text-transform:uppercase; font-size:0.85rem;" :style="shirtSource === 'Client' ? 'border-color:black; color:black' : 'border-color:#e5e7eb; color:#6b7280'">
                                                            <input type="radio" name="shirt_source" value="Client" x-model="shirtSource" class="sr-only">
                                                            Client-Provided Shirt
                                                        </label>
                                                    </div>
                                                </div>

                                                <!-- Shop-Provided Fields -->
                                                <div x-show="shirtSource === 'Shop'" x-transition style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                        <div>
                                                            <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Shirt Type *</label>
                                                            <select name="shirt_type" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                                <option value="" disabled selected>Select Type</option>
                                                                <option value="Round Neck">Round Neck</option>
                                                                <option value="V-Neck">V-Neck</option>
                                                                <option value="Polo Shirt">Polo Shirt</option>
                                                                <option value="Oversized">Oversized</option>
                                                                <option value="Long Sleeve">Long Sleeve</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Shirt Color *</label>
                                                            <input type="text" name="shop_shirt_color" class="input-field" placeholder="e.g. Black, White, Navy..." style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'T-Shirts' && shirtSource === 'Shop'">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Size *</label>
                                                        <select name="shop_shirt_size" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'T-Shirts' && shirtSource === 'Shop'">
                                                            <option value="" disabled selected>Select Size</option>
                                                            <option value="XS">XS</option>
                                                            <option value="S">S</option>
                                                            <option value="M">M</option>
                                                            <option value="L">L</option>
                                                            <option value="XL">XL</option>
                                                            <option value="XXL">XXL</option>
                                                            <option value="Custom Size">Custom Size</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Client-Provided Fields -->
                                                <div x-show="shirtSource === 'Client'" x-transition style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                        <div>
                                                            <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Shirt Color *</label>
                                                            <input type="text" name="client_shirt_color" class="input-field" placeholder="Color of your shirt" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'T-Shirts' && shirtSource === 'Client'">
                                                        </div>
                                                        <div>
                                                            <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Material *</label>
                                                            <select name="client_shirt_material" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'T-Shirts' && shirtSource === 'Client'">
                                                                <option value="" disabled selected>Select Material</option>
                                                                <option value="Cotton">Cotton</option>
                                                                <option value="Polyester">Polyester</option>
                                                                <option value="Dry-fit">Dry-fit</option>
                                                                <option value="Unknown">Unknown</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div style="background:#fef3c7; border:1px solid #fde68a; padding:1rem; display:flex; gap:0.5rem; align-items:flex-start;">
                                                        <input type="checkbox" name="material_disclaimer" id="material_disclaimer" value="Agreed" style="margin-top:0.25rem; accent-color:black;">
                                                        <div>
                                                            <label for="material_disclaimer" style="font-size:0.8rem; font-weight:700; color:#92400e; cursor:pointer;">I understand that printing quality depends on the shirt material provided.</label>
                                                            <p style="font-size:0.75rem; color:#b45309; margin-top:0.25rem;">Please bring your shirt before printing starts.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Tarpaulin Details -->
                                            <div x-show="productCategory === 'Tarpaulin'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Size & Material</h3>
                                                </div>

                                                <div style="background:#f9fafb; padding:1.25rem; border-radius:0; border:1px solid #e5e7eb;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Size Specification *</label>
                                                    <div style="display:flex; gap:0.75rem; margin-bottom:1rem;">
                                                        <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:0.75rem; background:white; border:2px solid; border-radius:0; cursor:pointer; font-weight:700; transition:all 0.2s; text-transform:uppercase; font-size:0.85rem;" :style="tarpSizeOption === 'Preset' ? 'border-color:black; color:black' : 'border-color:#e5e7eb; color:#6b7280'">
                                                            <input type="radio" name="tarp_size_option" value="Preset" x-model="tarpSizeOption" class="sr-only">
                                                            Preset Sizes
                                                        </label>
                                                        <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:0.75rem; background:white; border:2px solid; border-radius:0; cursor:pointer; font-weight:700; transition:all 0.2s; text-transform:uppercase; font-size:0.85rem;" :style="tarpSizeOption === 'Custom' ? 'border-color:black; color:black' : 'border-color:#e5e7eb; color:#6b7280'">
                                                            <input type="radio" name="tarp_size_option" value="Custom" x-model="tarpSizeOption" class="sr-only">
                                                            Custom Size
                                                        </label>
                                                    </div>

                                                    <!-- Preset Sizes -->
                                                    <div x-show="tarpSizeOption === 'Preset'" x-transition>
                                                        <select name="tarp_preset_size" x-model="tarpPresetSize" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                            <option value="2x3">2x3 ft</option>
                                                            <option value="3x5">3x5 ft</option>
                                                            <option value="4x6">4x6 ft</option>
                                                            <option value="5x7">5x7 ft</option>
                                                            <option value="6x8">6x8 ft</option>
                                                        </select>
                                                    </div>

                                                    <!-- Custom Sizes -->
                                                    <div x-show="tarpSizeOption === 'Custom'" x-transition style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                        <div>
                                                            <label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.25rem;">WIDTH</label>
                                                            <input type="number" name="tarp_width" x-model="tarpWidth" min="0.1" step="0.1" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        </div>
                                                        <div>
                                                            <label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.25rem;">HEIGHT</label>
                                                            <input type="number" name="tarp_height" x-model="tarpHeight" min="0.1" step="0.1" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        </div>
                                                        <div style="grid-column: span 2;">
                                                            <label style="display:block; font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:0.25rem;">UNIT</label>
                                                            <select name="tarp_unit" x-model="tarpUnit" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                                <option value="ft">Feet (ft)</option>
                                                                <option value="in">Inches (in)</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Material *</label>
                                                        <select name="tarp_material" x-model="tarpMaterial" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                            <option value="10oz">10oz (Standard)</option>
                                                            <option value="13oz">13oz (Thicker)</option>
                                                            <option value="15oz">15oz (Heavy Duty)</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Finish Type *</label>
                                                        <select name="tarp_finish" x-model="tarpFinish" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                            <option value="Glossy">Glossy</option>
                                                            <option value="Matte">Matte</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Generic Vehicle/Decal Fields (all other categories) -->
                                            <div x-show="productCategory !== 'Sintraboard & Standees' && productCategory !== 'Sintraboard Flat' && productCategory !== 'T-Shirts' && productCategory !== 'Tarpaulin'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Brand *</label>
                                                        <input type="text" name="vehicle_brand" class="input-field" placeholder="e.g. Honda" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'Decals & Stickers' || productCategory === 'Stickers'">
                                                    </div>
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Model *</label>
                                                        <input type="text" name="vehicle_model" class="input-field" placeholder="e.g. Click 125i" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'Decals & Stickers' || productCategory === 'Stickers'">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Year Model *</label>
                                                    <input type="number" name="vehicle_year" min="1900" max="<?php echo date('Y') + 1; ?>" class="input-field" placeholder="e.g. 2024" style="width:100%; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'Decals & Stickers' || productCategory === 'Stickers'">
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Step 2: Design Details -->
                                        <div x-show="step === 2">
                                            <!-- T-Shirt Printing Details -->
                                            <div x-show="productCategory === 'T-Shirts'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;" x-cloak>
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Printing Details</h3>
                                                </div>
                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Printing Method *</label>
                                                        <select name="printing_method" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                            <option value="" disabled selected>Select Method</option>
                                                            <option value="Sublimation">Sublimation</option>
                                                            <option value="Heat Transfer Vinyl (HTV)">Heat Transfer Vinyl (HTV)</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Design Placement *</label>
                                                        <select name="shirt_placement" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                            <option value="" disabled selected>Select Placement</option>
                                                            <option value="Front Only">Front Only</option>
                                                            <option value="Back Only">Back Only</option>
                                                            <option value="Front & Back">Front & Back</option>
                                                            <option value="Left Chest">Left Chest</option>
                                                            <option value="Full Front">Full Front</option>
                                                            <option value="Sleeve Print">Sleeve Print</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Tarpaulin Finishing Options -->
                                            <div x-show="productCategory === 'Tarpaulin'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Finishing Options</h3>
                                                </div>

                                                <div>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Edges *</label>
                                                    <select name="tarp_edges" x-model="tarpEdges" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        <option value="Heat Cut Only">Heat Cut Only (Standard)</option>
                                                        <option value="Folded & Sewn Edges">Folded & Sewn Edges (+₱2/sqft)</option>
                                                    </select>
                                                </div>

                                                <div style="background:#f9fafb; padding:1.25rem; border-radius:0; border:1px solid #e5e7eb;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Grommets / Eyelets *</label>
                                                    <select name="tarp_grommet_option" x-model="tarpGrommetOption" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        <option value="No Grommets">No Grommets</option>
                                                        <option value="With Grommets (All Sides)">With Grommets (All Sides)</option>
                                                        <option value="Top & Bottom Only">Top & Bottom Only</option>
                                                        <option value="Custom Placement">Custom Placement</option>
                                                    </select>
                                                    
                                                    <div x-show="tarpGrommetOption === 'Custom Placement'" x-transition style="margin-top:1rem;">
                                                        <textarea name="tarp_grommet_instructions" class="input-field" placeholder="Describe where you want the eyelets placed..." style="width:100%; border-radius:0; border:1px solid #d1d5db; min-height:60px;"></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <div x-show="productCategory !== 'T-Shirts' && productCategory !== 'Tarpaulin'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Design Details</h3>
                                                </div>

                                                <div style="background:#f9fafb; border:2px dashed #d1d5db; border-radius:0; padding:1.25rem;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Upload Final Design * <span style="font-weight:500; font-size:0.75rem; color:#6b7280; text-transform:none;">(JPG, PNG, AI, PSD)</span></label>
                                                    <input type="file" name="design_upload" id="design_upload_field" class="input-field" accept=".jpg,.jpeg,.png,.ai,.psd" style="width:100%; background:white; padding:0.5rem; border-radius:0; border:1px solid #d1d5db;" :required="productCategory !== 'T-Shirts' && productCategory !== 'Tarpaulin'">
                                                    <p style="font-size:0.7rem; color:#6b7280; margin-top:6px;">📎 Mandatory for manufacturing. Max: 5MB</p>
                                                </div>
                                                
                                                <div>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Upload Reference Image <span style="font-weight:500; font-size:0.75rem; color:#6b7280; text-transform:none;">(Optional)</span></label>
                                                    <input type="file" name="reference_upload" class="input-field" accept=".jpg,.jpeg,.png" style="width:100%; border-radius:0; padding:0.5rem; border:1px solid #d1d5db;">
                                                    <p style="font-size:0.7rem; color:#6b7280; margin-top:6px;">📎 Accepted: JPG, PNG</p>
                                                </div>
                                                
                                                <div>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Design Description <span style="font-weight:500; font-size:0.75rem; color:#6b7280; text-transform:none;">(Optional)</span></label>
                                                    <textarea name="design_description" rows="3" class="input-field" placeholder="Describe your preferred style, colors, or special instructions..." style="width:100%; border-radius:0; border:1px solid #d1d5db;"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Step 3: Mixed (Upload Design / Options) -->
                                        <div x-show="step === 3">

                                            <!-- T-Shirt Design Upload & Size Options -->
                                            <div x-show="productCategory === 'T-Shirts'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;" x-cloak>
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Design Upload</h3>
                                                </div>

                                                <div style="background:#f9fafb; border:2px dashed #d1d5db; border-radius:0; padding:1.25rem;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Upload Final Design * <span style="font-weight:500; font-size:0.75rem; color:#6b7280; text-transform:none;">(JPG, PNG, PDF, AI)</span></label>
                                                    <input type="file" name="tshirt_design_upload" id="tshirt_design_upload_field" class="input-field" accept=".jpg,.jpeg,.png,.pdf,.ai" style="width:100%; background:white; padding:0.5rem; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'T-Shirts'">
                                                    <p style="font-size:0.7rem; color:#6b7280; margin-top:6px;">📎 Max file size: 10MB</p>
                                                </div>
                                                
                                                <div style="background:#f9fafb; padding:1.25rem; border-radius:0; border:1px solid #e5e7eb;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Print Size *</label>
                                                    <select name="print_size" class="input-field" x-model="printSize" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        <option value="" disabled selected>Select Print Size</option>
                                                        <option value="Small (Pocket Size)">Small (Pocket Size)</option>
                                                        <option value="Medium (A4)">Medium (A4)</option>
                                                        <option value="Large (A3)">Large (A3)</option>
                                                        <option value="Custom Size">Custom Size</option>
                                                    </select>
                                                    
                                                    <div x-show="printSize === 'Custom Size'" x-transition style="margin-top:1rem; display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                                        <div>
                                                            <input type="text" name="tshirt_custom_width" class="input-field" placeholder="Width (in or cm)" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        </div>
                                                        <div>
                                                            <input type="text" name="tshirt_custom_height" class="input-field" placeholder="Height (in or cm)" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Additional Notes <span style="font-weight:500; font-size:0.75rem; color:#6b7280; text-transform:none;">(Optional)</span></label>
                                                    <textarea name="tshirt_design_description" rows="3" class="input-field" placeholder="Add special instructions (e.g., palakihin ang logo, tanggalin background, center design)." style="width:100%; border-radius:0; border:1px solid #d1d5db;"></textarea>
                                                </div>
                                            </div>

                                            <!-- Tarpaulin Design Details -->
                                            <div x-show="productCategory === 'Tarpaulin'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Design Details</h3>
                                                </div>

                                                <div style="background:#f9fafb; border:2px dashed #d1d5db; border-radius:0; padding:1.25rem;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.5rem; color:black; text-transform:uppercase;">📎 Upload Your File (Design, Image, or PDF) – Max 5MB</label>
                                                    <input type="file" name="tarp_design_upload" id="tarp_design_upload_field" class="input-field" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; background:white; padding:0.5rem; border-radius:0; border:1px solid #d1d5db;" :required="productCategory === 'Tarpaulin'">
                                                    <p style="font-size:0.7rem; color:#6b7280; margin-top:6px;">Max file size: 10MB</p>
                                                </div>

                                                <div style="background:#f9fafb; padding:1.25rem; border-radius:0; border:1px solid #e5e7eb;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Design Service Option *</label>
                                                    <div style="display:flex; gap:0.75rem; margin-bottom:1rem;">
                                                        <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:0.75rem; background:white; border:2px solid; border-radius:0; cursor:pointer; font-weight:700; transition:all 0.2s; text-transform:uppercase; font-size:0.85rem;" :style="tarpDesignService === 'Ready' ? 'border-color:black; color:black' : 'border-color:#e5e7eb; color:#6b7280'">
                                                            <input type="radio" name="tarp_design_service" value="Ready" x-model="tarpDesignService" class="sr-only">
                                                            I have ready design
                                                        </label>
                                                        <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:0.75rem; background:white; border:2px solid; border-radius:0; cursor:pointer; font-weight:700; transition:all 0.2s; text-transform:uppercase; font-size:0.85rem;" :style="tarpDesignService === 'Requested' ? 'border-color:black; color:black' : 'border-color:#e5e7eb; color:#6b7280'">
                                                            <input type="radio" name="tarp_design_service" value="Requested" x-model="tarpDesignService" class="sr-only">
                                                            I need layout
                                                        </label>
                                                    </div>
                                                    
                                                    <div x-show="tarpDesignService === 'Requested'" x-transition>
                                                        <textarea name="tarp_design_description" class="input-field" placeholder="Describe your event or theme (e.g., Birthday, Graduation, Business promotion)..." style="width:100%; border-radius:0; border:1px solid #d1d5db; min-height:80px;"></textarea>
                                                        <p style="font-size:0.7rem; color:#92400e; margin-top:6px; font-weight:700;">Note: Design service adds ₱50.00 to total.</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Sintraboard Stand & Finishing -->
                                            <div x-show="productCategory === 'Sintraboard & Standees' || productCategory === 'Sintraboard Flat'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;" x-cloak>
                                                <div style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Stand & Finishing Options</h3>
                                                </div>

                                                <!-- Stand Type (hidden for Sintra Board Flat) -->
                                                <div x-show="productCategory === 'Sintraboard & Standees'">
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Stand Type *</label>
                                                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.5rem;">
                                                        <?php foreach(['With Metal Stand'=>'Metal Stand','With Foldable Support (Easel Type)'=>'Foldable','Board Only (No Stand)'=>'Board Only'] as $val=>$label): ?>
                                                        <label style="display:flex; align-items:center; justify-content:center; gap:6px; padding:0.75rem; border:1px solid #d1d5db; border-radius:0; cursor:pointer; font-size:0.8rem; font-weight:700; color:black; text-align:center; transition: all 0.2s; text-transform:uppercase;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                                                            <input type="radio" name="stand_type" value="<?php echo $val; ?>" style="accent-color:black;">
                                                            <?php echo $label; ?>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>

                                                <!-- Flat Type Options (visible for Sintra Board Flat) -->
                                                <div x-show="productCategory === 'Sintraboard Flat'" x-cloak>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Flat Type Options *</label>
                                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                                                        <?php foreach(['With Double-Sided Foam Tape'=>'With Tape', 'With Holes/Eyelets'=>'With Eyelets', 'Board Only (No Adhesive)'=>'Board Only'] as $val=>$label): ?>
                                                        <label style="display:flex; align-items:center; justify-content:center; gap:6px; padding:0.75rem; border:1px solid #d1d5db; border-radius:0; cursor:pointer; font-size:0.8rem; font-weight:700; color:black; text-align:center; transition: all 0.2s; text-transform:uppercase;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                                                            <input type="radio" name="flat_type" value="<?php echo $val; ?>" style="accent-color:black;">
                                                            <?php echo $label; ?>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>

                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Lamination</label>
                                                        <select name="lamination" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                            <option value="No Lamination">No Lamination</option>
                                                            <option value="Matte">Matte</option>
                                                            <option value="Gloss">Gloss</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Cut Type *</label>
                                                        <select name="cut_type" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                            <option value="Standard Rectangle Cut">Standard Rectangle Cut</option>
                                                            <option value="Die-Cut (Custom Shape)">Die-Cut (Custom Shape)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Generic Decal Size & Options -->
                                            <div x-show="productCategory !== 'Sintraboard & Standees' && productCategory !== 'Sintraboard Flat' && productCategory !== 'T-Shirts' && productCategory !== 'Tarpaulin'" style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div x-show="productCategory === 'Decals & Stickers' || productCategory === 'Stickers'" style="text-align:center; padding-bottom:0.75rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Size & Options</h3>
                                                </div>

                                                <div>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.5rem; color:black; text-transform:uppercase;">Placement *</label>
                                                    <select name="placement" class="input-field" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        <option value="" disabled selected>Select placement</option>
                                                        <option value="Side Fairings">Side Fairings</option>
                                                        <option value="Tank">Tank</option>
                                                        <option value="Fender">Fender</option>
                                                        <option value="Door Panel">Door Panel</option>
                                                        <option value="Hood">Hood</option>
                                                        <option value="Windshield">Windshield</option>
                                                        <option value="Full Body">Full Body</option>
                                                        <option value="Others">Others</option>
                                                    </select>
                                                </div>
                                                
                                                <div style="background:#f9fafb; padding:1.25rem; border-radius:0; border:1px solid #e5e7eb;">
                                                    <label style="display:block; font-size:0.875rem; font-weight:800; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Size Options *</label>
                                                    <div style="display:flex; gap:0.75rem;">
                                                        <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:0.75rem; background:white; border:2px solid; border-radius:0; cursor:pointer; font-weight:700; transition:all 0.2s; text-transform:uppercase; font-size:0.85rem;" :style="sizeOption === 'OEM Size' ? 'border-color:black; color:black' : 'border-color:#e5e7eb; color:#6b7280'">
                                                            <input type="radio" name="size_option" value="OEM Size" x-model="sizeOption" style="display:none;">
                                                            OEM Standard
                                                        </label>
                                                        <label style="flex:1; display:flex; align-items:center; justify-content:center; gap:8px; padding:0.75rem; background:white; border:2px solid; border-radius:0; cursor:pointer; font-weight:700; transition:all 0.2s; text-transform:uppercase; font-size:0.85rem;" :style="sizeOption === 'Custom Size' ? 'border-color:black; color:black' : 'border-color:#e5e7eb; color:#6b7280'">
                                                            <input type="radio" name="size_option" value="Custom Size" x-model="sizeOption" style="display:none;">
                                                            Custom Size
                                                        </label>
                                                    </div>
                                                    
                                                    <div x-show="sizeOption === 'Custom Size'" x-transition style="margin-top:1rem; display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                                        <div>
                                                            <input type="text" name="custom_width" class="input-field" placeholder="Width (in)" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        </div>
                                                        <div>
                                                            <input type="text" name="custom_height" class="input-field" placeholder="Height (in)" style="width:100%; border-radius:0; border:1px solid #d1d5db;">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label style="display:block; font-size:0.875rem; font-weight:700; margin-bottom:0.75rem; color:black; text-transform:uppercase;">Select Material *</label>
                                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                                                        <?php foreach(['Glossy Vinyl', 'Matte Vinyl', 'Reflectorized', 'Chrome', 'Carbon Fiber Texture'] as $mat): ?>
                                                            <label style="display:flex; align-items:center; gap:6px; padding:0.6rem; border:1px solid #d1d5db; border-radius:0; cursor:pointer; font-size:0.8rem; font-weight:600; color:black; transition: background 0.2s; text-transform:uppercase;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                                                                <input type="radio" name="material_type" value="<?php echo $mat; ?>" style="accent-color:black;">
                                                                <?php echo $mat; ?>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Step 4: Final Review -->
                                        <div x-show="step === 4">
                                            <div style="display:grid; grid-template-columns:1fr; gap:1.25rem;">
                                                <div style="text-align:center; padding-bottom:1rem;">
                                                    <h3 style="font-weight:800; color:black; margin:0; text-transform:uppercase; letter-spacing:0.05em;">Final Review</h3>
                                                </div>

                                                <div style="background:#f9fafb; border-radius:0; border:1px solid #e5e7eb; padding:1.5rem; text-align:center;">
                                                    <p style="font-size:0.85rem; color:#6b7280; margin-bottom:0.25rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">Total Amount</p>
                                                    <div style="font-size:2rem; font-weight:900; color:black; margin-bottom:1rem;" 
                                                         x-text="productCategory === 'T-Shirts' && shirtSource === 'Client' ? 
                                                                '₱' + (150 * orderQty).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : 
                                                                productCategory === 'Tarpaulin' ? 
                                                                '₱' + (totalTarpaulinPrice * orderQty).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : 
                                                                (productCategory === 'Decals & Stickers' || productCategory === 'Stickers') ? 
                                                                '₱' + (totalDecalPrice * orderQty).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : 
                                                                '₱' + (<?php echo $product['price']; ?> * orderQty).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')">
                                                        <?php echo format_currency($product['price']); ?>
                                                    </div>
                                                    
                                                    <div style="height:1px; background:#e5e7eb; margin:1rem 0;"></div>

                                                    <label style="display:block; font-size:0.875rem; font-weight:800; color:black; margin-bottom:1rem; text-transform:uppercase;">Select Quantity</label>
                                                    <div style="display:flex; align-items:center; justify-content:center; gap:15px;">
                                                        <button type="button" @click="if(orderQty > 1) orderQty--" style="width:40px; height:40px; border-radius:0; border:1px solid black; background:white; font-size:1.2rem; display:flex; align-items:center; justify-content:center; cursor:pointer; transition: all 0.2s;" onmouseover="this.style.background='black'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='black';">-</button>
                                                        <input type="number" name="quantity" x-model="orderQty" min="1" max="<?php echo $product['stock_quantity']; ?>" style="width:70px; height:40px; border-radius:0; border:2px solid black; text-align:center; font-size:1.1rem; font-weight:800; color:black;">
                                                        <button type="button" @click="if(orderQty < <?php echo $product['stock_quantity']; ?>) orderQty++" style="width:40px; height:40px; border-radius:0; border:1px solid black; background:white; font-size:1.2rem; display:flex; align-items:center; justify-content:center; cursor:pointer; transition: all 0.2s;" onmouseover="this.style.background='black'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='black';">+</button>
                                                    </div>
                                                    <p style="font-size:0.75rem; color:#6b7280; margin-top:10px; font-weight:600; text-transform:uppercase;"><?php echo $product['stock_quantity']; ?> items in stock</p>
                                                </div>
                                                
                                                <div style="background:white; border:1px solid black; border-radius:0; padding:1rem; text-align:center;">
                                                    <p style="margin:0; font-size:0.85rem; color:black; font-weight:600; line-height:1.4;">Please double-check all details before proceeding to your cart or checkout.</p>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                    <!-- Modal Footer -->
                                    <div class="p-6 border-t border-gray-100 bg-gray-50 flex gap-4">
                                        <button type="button" 
                                                x-show="step !== 1" 
                                                @click="if(step > 1) step = step - 1" 
                                                class="shopee-btn-outline" style="flex:1;">
                                            Back
                                        </button>
                                        
                                        <button type="button" 
                                                x-show="step !== 4" 
                                                @click="validateStepForward()" 
                                                class="shopee-btn-primary" style="flex:2;">
                                            Next Step
                                        </button>

                                        <div x-show="step === 4" style="flex:2; display:flex; gap:0.75rem; width:100%; justify-content:flex-end;">
                                            <a href="<?php echo isset($base_url) ? $base_url : '/printflow'; ?>/customer/products.php" class="shopee-btn-outline flex-1 text-center decoration-none">Back to Products</a>
                                            <button type="submit" name="action" value="add_to_cart" @click="checkFinalValidation($event)" class="shopee-btn-outline" style="width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;" title="Add to Cart"><svg style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg></button>
                                            <button type="submit" name="action" value="buy_now" @click="checkFinalValidation($event)" class="shopee-btn-primary flex-1">
                                                Buy Now
                                            </button>
                                        </div>
                                    </div>
                                </form>
                </div> <!-- .bg-white wrapper -->
                <?php endif; ?>
            </div> <!-- .shopee-form-section -->
        </div> <!-- .shopee-card -->
    </div> <!-- .shopee-layout-container -->
</div> <!-- x-data="orderModal" -->

<style>
    [x-cloak] { display: none !important; }
    
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border-width: 0;
    }
</style>

<script>
    /* alpine:init runs only once per session; Turbo visits need Alpine.data registered when this script runs. */
    (function () {
        function registerOrderModalData() {
            if (typeof window.Alpine === 'undefined' || typeof Alpine.data !== 'function') return;
            Alpine.data('orderModal', () => ({
            step: <?php echo !empty($error_msg) ? '4' : '1'; ?>,
            sizeOption: 'OEM Size',
            productCategory: '<?php echo addslashes($product['category'] ?? ''); ?>',
            shirtSource: 'Shop',
            printSize: '',
            orderQty: <?php echo (int)($_GET['qty'] ?? 1); ?>,

            // Tarpaulin specific
            tarpSizeOption: 'Preset',
            tarpPresetSize: '2x3',
            tarpWidth: 2,
            tarpHeight: 3,
            tarpUnit: 'ft',
            tarpMaterial: '10oz',
            tarpFinish: 'Glossy',
            tarpDesignService: 'Ready',
            tarpEdges: 'Heat Cut Only',
            tarpGrommetOption: 'With Grommets (All Sides)',

            get totalTarpaulinPrice() {
                let w = parseFloat(this.tarpWidth);
                let h = parseFloat(this.tarpHeight);
                if (this.tarpSizeOption === 'Preset') {
                    let parts = this.tarpPresetSize.split('x');
                    w = parseFloat(parts[0]);
                    h = parseFloat(parts[1]);
                }
                
                let sqft = (this.tarpUnit === 'in') ? (w * h) / 144 : (w * h);
                let baseRate = <?php echo (float)($product['price'] > 0 ? $product['price'] : 25); ?>;
                let price = sqft * baseRate;

                if (this.tarpMaterial === '13oz') price *= 1.25;
                if (this.tarpMaterial === '15oz') price *= 1.5;
                if (this.tarpEdges === 'Folded & Sewn Edges') price += (sqft * 2);
                if (this.tarpGrommetOption !== 'No Grommets') price += 10.00;
                if (this.tarpDesignService === 'Requested') price += 50.00;

                return Math.round(price * 100) / 100;
            },

            // Decals & Stickers Pricing (Base + Size Logic)
            get totalDecalPrice() {
                let baseRate = <?php echo (float)($product['price'] > 0 ? $product['price'] : 45); ?>;
                if (this.sizeOption === 'Custom Size') {
                    // Slight premium for choosing custom specs
                    return baseRate + 15;
                }
                return baseRate;
            },

            validateStepForward() {
                const currentStepEl = this.$el.querySelector(`[x-show="step === ${this.step}"]`);
                if (!currentStepEl) {
                    if (this.step < 4) this.step++;
                    return;
                }

                // Check standard inputs
                const inputs = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (!input.checkValidity()) {
                        input.reportValidity();
                        isValid = false;
                    }
                });

                if (isValid) {
                    if (this.step < 4) this.step++;
                    // Scroll to top of page for new step
                    this.$nextTick(() => {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                }
            },

            checkFinalValidation(e) {
                const form = document.getElementById('customization-form');
                if (!form.checkValidity()) {
                    e.preventDefault();
                    form.reportValidity();
                    // If invalid, find which step has the error and jump to it
                    const allInputs = form.querySelectorAll('input[required], select[required], textarea[required]');
                    for (let input of allInputs) {
                        if (!input.checkValidity()) {
                            // Find parent step
                            let parent = input.closest('[x-show*="step === "]');
                            if (parent) {
                                const stepMatch = parent.getAttribute('x-show').match(/step === (\d+)/);
                                if (stepMatch) {
                                    this.step = parseInt(stepMatch[1]);
                                    setTimeout(() => input.reportValidity(), 100);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }));
        }
        document.addEventListener('alpine:init', registerOrderModalData);
        registerOrderModalData();
    })();

    // Form submission helper
    document.addEventListener('submit', (e) => {
        if (e.target.id === 'customization-form') {
            const formData = new FormData(e.target);
            console.log('--- FORM SUBMITTING ---');
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value instanceof File ? `File: ${value.name}` : value);
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

