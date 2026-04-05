<?php
/**
 * Dynamic Service Order Page
 * Renders form based on admin field configuration
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_once __DIR__ . '/../includes/service_field_renderer.php';

require_role('Customer');
require_once __DIR__ . '/../includes/require_id_verified.php';
$customer_id = get_user_id();

$service_id = (int)($_GET['service_id'] ?? 0);
$error = '';

if ($service_id < 1) {
    header('Location: services.php');
    exit;
}

$service = db_query("SELECT * FROM services WHERE service_id = ? AND status = 'Activated'", 'i', [$service_id]);
if (empty($service)) {
    header('Location: services.php');
    exit;
}
$service = $service[0];

// Check if service has field configuration
if (!service_has_field_config($service_id)) {
    // Fallback to hardcoded page if exists
    if (!empty($service['customer_link']) && file_exists(__DIR__ . '/' . $service['customer_link'])) {
        header('Location: ' . $service['customer_link']);
        exit;
    }
    // If no customer_link and no field config, show error instead of redirecting
    $error = 'This service is not yet configured. Please contact administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    // Get field configurations to validate dynamically
    $field_configs = get_service_field_config($service_id);
    
    // Extract values from POST based on field configuration
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $quantity = max(1, min(999, (int)($_POST['quantity'] ?? 1)));
    $notes = '';
    $needed_date = '';
    $has_design_field = false;
    
    // Validate branch
    if ($branch_id < 1) {
        $error = 'Please select a branch.';
    }
    
    // Validate all required fields dynamically
    if (empty($error)) {
        foreach ($field_configs as $key => $config) {
            if (!$config['visible']) continue;
            
            // --- Conditional Logic Check ---
            if (!empty($config['parent_field_key']) && !empty($config['parent_value'])) {
                $parent_key = $config['parent_field_key'];
                $trigger_value = $config['parent_value'];
                
                // Get the value of the parent field from POST
                // For radio/select, it's just $_POST[$parent_key]
                $parent_submitted_value = $_POST[$parent_key] ?? null;
                
                // Special case for 'branch' if it were possible, but here it's custom fields
                if ($parent_key === 'branch') {
                    $parent_submitted_value = $_POST['branch_id'] ?? null;
                }
                
                // If parent condition not met, skip this field (it was hidden)
                if ($parent_submitted_value != $trigger_value) {
                    continue;
                }
            }
            // --- End Conditional Logic Check ---
            
            if ($config['type'] === 'date') {
                $needed_date = trim($_POST[$key] ?? '');
                if ($config['required'] && empty($needed_date)) {
                    $error = 'Please select when you need the order.';
                    break;
                }
            } elseif ($config['type'] === 'textarea') {
                $notes = trim($_POST[$key] ?? '');
                // Notes field is optional, so only validate if required flag is true
                if ($config['required'] && empty($notes)) {
                    $error = 'Please provide ' . strtolower($config['label']) . '.';
                    break;
                }
            } elseif ($config['type'] === 'file') {
                $has_design_field = true;
                if ($config['required'] && (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK)) {
                    $error = 'Please upload your design.';
                    break;
                }
            } elseif ($config['type'] === 'radio' || $config['type'] === 'select') {
                // Skip branch validation as it's already validated above
                if ($key === 'branch') continue;
                
                if ($config['required'] && empty($_POST[$key])) {
                    $error = 'Please select ' . strtolower($config['label']) . '.';
                    break;
                }
            } elseif ($config['type'] === 'dimension') {
                $width = trim($_POST['width'] ?? '');
                $height = trim($_POST['height'] ?? '');
                if ($config['required'] && (empty($width) || empty($height))) {
                    $error = 'Please select dimensions.';
                    break;
                }
                if (!empty($width) && (!is_numeric($width) || $width <= 0)) {
                    $error = 'Invalid width dimension.';
                    break;
                }
                if (!empty($height) && (!is_numeric($height) || $height <= 0)) {
                    $error = 'Invalid height dimension.';
                    break;
                }
            } elseif (in_array($config['type'], ['text', 'number'])) {
                if ($config['required'] && empty(trim($_POST[$key] ?? ''))) {
                    $error = 'Please provide ' . strtolower($config['label']) . '.';
                    break;
                }
            }
        }
    }
    
    if (empty($error) && $quantity < 1) {
        $error = 'Please enter a valid quantity.';
    }
    
    if (empty($error)) {
        // Validate and process file upload if design field exists
        $design_tmp_path = null;
        $design_name = null;
        $design_mime = null;
        
        if ($has_design_field && isset($_FILES['design_file']) && $_FILES['design_file']['error'] === UPLOAD_ERR_OK) {
            $valid = service_order_validate_file($_FILES['design_file']);
            if (!$valid['ok']) {
                $error = $valid['error'];
            } else {
                $item_key = 'service_' . $service_id . '_' . time() . '_' . rand(100, 999);
                $original_name = $_FILES['design_file']['name'];
                $mime = $valid['mime'];
                $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $new_name = uniqid('tmp_') . '.' . $ext;
                $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

                if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                    $design_tmp_path = $tmp_dest;
                    $design_name = $original_name;
                    $design_mime = $mime;
                } else {
                    $error = 'Failed to process uploaded file.';
                }
            }
        } elseif (!$has_design_field) {
            // No design field configured, create item key without file
            $item_key = 'service_' . $service_id . '_' . time() . '_' . rand(100, 999);
        }
        
        if (empty($error)) {
            // Collect all custom fields dynamically
            $customization = [];
            foreach ($field_configs as $key => $config) {
                if (!$config['visible']) continue;
                
                // Check conditional logic again for data collection
                if (!empty($config['parent_field_key']) && !empty($config['parent_value'])) {
                    $parent_key = $config['parent_field_key'];
                    $trigger_value = $config['parent_value'];
                    $parent_submitted_value = $_POST[$parent_key] ?? null;
                    if ($parent_key === 'branch') $parent_submitted_value = $_POST['branch_id'] ?? null;
                    
                    if ($parent_submitted_value != $trigger_value) {
                        continue;
                    }
                }
                
                if (in_array($key, ['branch', 'needed_date', 'quantity', 'notes'])) continue;
                
                if ($config['type'] === 'dimension') {
                    if (!empty($_POST['width']) && !empty($_POST['height'])) {
                        $customization[$config['label']] = $_POST['width'] . '×' . $_POST['height'] . ' ' . ($config['unit'] ?? 'ft');
                    }
                } else {
                    if (isset($_POST[$key]) && $_POST[$key] !== '') {
                        $customization[$config['label']] = $_POST[$key];
                    }
                }
            }
            
            // Ensure needed_date is in customization
            if (!empty($needed_date)) {
                $customization['needed_date'] = $needed_date;
            }
            if (!empty($notes)) {
                $customization['notes'] = $notes;
            }
            
            // Calculate price (basic calculation)
            $base_price = 100.00;
            $total_price = $base_price * $quantity;
            
            $_SESSION['cart'][$item_key] = [
                'type' => 'Service',
                'source_page' => 'services',
                'name' => $service['name'],
                'price' => $total_price,
                'quantity' => $quantity,
                'category' => $service['category'],
                'branch_id' => $branch_id,
                'design_tmp_path' => $design_tmp_path,
                'design_name' => $design_name,
                'design_mime' => $design_mime,
                'customization' => $customization
            ];
            
            if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                redirect("order_review.php?item=" . urlencode($item_key));
            } else {
                redirect("cart.php");
            }
        }
    }
}

$page_title = 'Order ' . $service['name'] . ' - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

// Ensure review_helpful table exists
global $conn;
$conn->query("CREATE TABLE IF NOT EXISTS review_helpful (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user (review_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");

// Get all display images
$display_images = [];
if (!empty($service['display_image'])) {
    $images = explode(',', $service['display_image']);
    foreach ($images as $img) {
        $img = trim($img);
        if ($img !== '') {
            if (strpos($img, 'http') === false && $img[0] !== '/') {
                $img = '/' . ltrim($img, '/');
            }
            $display_images[] = $img;
        }
    }
}

// Fallback to hero_image if no display images
if (empty($display_images) && !empty($service['hero_image'])) {
    $img = $service['hero_image'];
    if (strpos($img, 'http') === false && $img[0] !== '/') {
        $img = '/' . ltrim($img, '/');
    }
    $display_images[] = $img;
}

// Use first image or placeholder
$display_img = !empty($display_images) ? $display_images[0] : 'https://placehold.co/600x600/f8fafc/0f172a?text=' . urlencode($service['name']);

$stats = service_order_get_page_stats($service['customer_link'] ?? '');
$avg_rating = number_format((float)($stats['avg_rating'] ?? 0), 1);
$review_count = (int)($stats['review_count'] ?? 0);
$sold_count = (int)($stats['sold_count'] ?? 0);
$sold_display = $sold_count >= 1000 ? number_format($sold_count / 1000, 1) . 'k' : $sold_count;
?>

<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($service['name']); ?></span>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6" id="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="shopee-card">
            <div class="shopee-image-section">
                <div class="sticky top-24">
                    <div class="shopee-main-image-wrap" style="position:relative;">
                        <?php if (count($display_images) > 1): ?>
                            <!-- Image Carousel -->
                            <div id="image-carousel" style="position:relative;width:100%;height:500px;overflow:hidden;border-radius:8px;background:#f9fafb;">
                                <?php foreach ($display_images as $index => $img): ?>
                                    <img src="<?php echo htmlspecialchars($img); ?>" 
                                         alt="<?php echo htmlspecialchars($service['name']); ?>" 
                                         class="carousel-image" 
                                         data-index="<?php echo $index; ?>"
                                         style="position:absolute;top:0;left:<?php echo $index === 0 ? '0' : '100%'; ?>;width:100%;height:100%;object-fit:cover;transition:left 0.4s ease-in-out;">
                                <?php endforeach; ?>
                                
                                <!-- Navigation Arrows -->
                                <button type="button" id="carousel-prev" onclick="changeImage(-1)" class="carousel-arrow carousel-prev" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.85);color:#374151;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;display:none;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,0.15);z-index:10;transition:all 0.2s;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <button type="button" id="carousel-next" onclick="changeImage(1)" class="carousel-arrow carousel-next" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.85);color:#374151;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,0.15);z-index:10;transition:all 0.2s;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                                
                                <!-- Image Counter -->
                                <div style="position:absolute;bottom:32px;right:16px;background:rgba(0,0,0,0.65);color:white;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600;z-index:10;">
                                    <span id="current-image">1</span> / <?php echo count($display_images); ?>
                                </div>
                            </div>
                            
                            <!-- Thumbnail Navigation -->
                            <div style="display:none;gap:8px;margin-top:12px;overflow-x:auto;padding:4px;">
                                <?php foreach ($display_images as $index => $img): ?>
                                    <img src="<?php echo htmlspecialchars($img); ?>" 
                                         alt="Thumbnail <?php echo $index + 1; ?>" 
                                         onclick="goToImage(<?php echo $index; ?>)"
                                         class="carousel-thumbnail"
                                         data-index="<?php echo $index; ?>"
                                         style="width:70px;height:70px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid <?php echo $index === 0 ? '#0d9488' : '#e5e7eb'; ?>;transition:all 0.2s;flex-shrink:0;">
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Single Image -->
                            <div style="width:100%;height:500px;border-radius:8px;background:#f9fafb;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                                <img src="<?php echo htmlspecialchars($display_img); ?>" 
                                     alt="<?php echo htmlspecialchars($service['name']); ?>" 
                                     style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Service Description -->
                    <?php if (!empty($service['description'])): ?>
                        <div style="margin-top:20px;padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">
                            <h3 style="font-size:14px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">Description</h3>
                            <p style="font-size:14px;line-height:1.6;color:#64748b;white-space:pre-wrap;text-align:justify;"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($service['name']); ?></h1>
                
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                    <div class="flex items-center gap-1">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <svg class="w-4 h-4" style="fill: <?php echo ($i <= round((float)($stats['avg_rating'] ?? 0))) ? '#FBBF24' : '#E2E8F0'; ?>;" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        <?php endfor; ?>
                        <?php if ($review_count > 0): ?>
                            <a href="reviews.php?service_id=<?php echo $service_id; ?>" class="text-sm text-gray-500 hover:text-blue-500 hover:underline ml-1">
                                (<?php echo number_format($review_count); ?> Reviews)
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="h-4 w-px bg-gray-200"></div>
                    <div class="text-sm text-gray-500"><?php echo $sold_display; ?> Sold</div>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" id="serviceForm" data-pf-skip-validation="true" novalidate>
                    <?php echo csrf_field(); ?>
                    
                    <?php echo render_service_fields($service_id, $branches); ?>
                    
                    <div class="shopee-form-row pt-8">
                        <div style="width: 130px;"></div>
                        <div class="flex gap-4 flex-1">
                            <a href="<?php echo BASE_URL; ?>/customer/services.php" class="shopee-btn-outline" style="flex: 1; min-width: 0;">Back</a>
                            <button type="submit" name="action" value="add_to_cart" class="shopee-btn-outline" style="flex: 1.2; min-width: 140px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; white-space: nowrap; padding: 0.5rem 1.25rem;" title="Add to Cart">
                                <svg style="width: 1.125rem; height: 1.125rem; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <span>Add to Cart</span>
                            </button>
                            <button type="submit" name="action" value="buy_now" class="shopee-btn-primary" style="flex: 1; min-width: 0; white-space: nowrap; display: flex; align-items: center; justify-content: center; padding: 0.5rem 1.25rem;">
                                <span>Buy Now</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Product Ratings Section -->
        <?php
        $reviews = db_query(
            "SELECT r.*, c.first_name, c.last_name, c.profile_picture,
             (SELECT image_path FROM review_images WHERE review_id = r.id LIMIT 1) as image_path,
             (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id) as helpful_count,
             (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id AND user_id = ?) as user_voted
             FROM reviews r
             LEFT JOIN customers c ON r.customer_id = c.customer_id
             WHERE r.service_type COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
             ORDER BY r.created_at DESC",
            'is', [$customer_id, $service['name']]
        ) ?: [];

        $total_reviews = count($reviews);
        $avg_rating = $total_reviews > 0 ? array_sum(array_column($reviews, 'rating')) / $total_reviews : 0;
        $rating_counts = [5=>0,4=>0,3=>0,2=>0,1=>0];
        $with_comments = 0; $with_media = 0;
        foreach ($reviews as $r) {
            $rt = (int)$r['rating'];
            if ($rt >= 1 && $rt <= 5) $rating_counts[$rt]++;
            if (!empty(trim($r['message'] ?? ''))) $with_comments++;
            if (!empty($r['image_path'])) $with_media++;
        }
        $reviews_per_page = 10;
        $poc_page = max(1, (int)($_GET['rpage'] ?? 1));
        $poc_total_pages = $total_reviews > 0 ? (int)ceil($total_reviews / $reviews_per_page) : 1;
        $poc_page = min($poc_page, $poc_total_pages);
        $poc_offset = ($poc_page - 1) * $reviews_per_page;
        $reviews_paged = array_slice($reviews, $poc_offset, $reviews_per_page);
        ?>
        <div style="margin-top:24px;padding:1.5rem 2rem;background:#fff;border:1px solid #e5e7eb;border-radius:4px;">
            <h2 class="poc-section-title">Product Ratings</h2>

            <?php if ($total_reviews > 0): ?>
            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;">
                <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;">
                    <div style="text-align:center;">
                        <div style="font-size:3rem;font-weight:700;color:#f97316;line-height:1;"><?php echo number_format($avg_rating,1); ?></div>
                        <div style="font-size:0.875rem;color:#6b7280;margin-top:0.25rem;">out of 5</div>
                        <div style="display:flex;gap:2px;margin-top:0.5rem;justify-content:center;">
                            <?php for($i=1;$i<=5;$i++): ?>
                                <svg width="22" height="22" fill="<?php echo ($i<=round($avg_rating))?'#f97316':'#d1d5db'; ?>" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div style="flex:1;min-width:300px;">
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
                            <button class="poc-filter-btn active" data-filter="all" style="padding:0.5rem 1rem;border:1px solid #e5e7eb;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;transition:all 0.2s;">All</button>
                            <?php for($i=5;$i>=1;$i--): ?>
                                <button class="poc-filter-btn" data-filter="<?php echo $i; ?>" style="padding:0.5rem 1rem;border:1px solid #e5e7eb;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;transition:all 0.2s;"><?php echo $i; ?> Star (<?php echo $rating_counts[$i]; ?>)</button>
                            <?php endfor; ?>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                            <button class="poc-filter-btn" data-filter="comments" style="padding:0.5rem 1rem;border:1px solid #e5e7eb;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;transition:all 0.2s;">With Comments (<?php echo $with_comments; ?>)</button>
                            <button class="poc-filter-btn" data-filter="media" style="padding:0.5rem 1rem;border:1px solid #e5e7eb;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;transition:all 0.2s;">With Media (<?php echo $with_media; ?>)</button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="poc-reviews-container">
                <?php foreach ($reviews_paged as $review):
                    $reviewer_name = htmlspecialchars(trim(($review['first_name']??'').' '.($review['last_name']??'')));
                    $profile_pic = !empty($review['profile_picture']) ? '/printflow/public/assets/uploads/profiles/'.htmlspecialchars($review['profile_picture']) : '';
                    $rating = (int)$review['rating'];
                    $comment = htmlspecialchars($review['message'] ?? '');
                    $has_comment = !empty(trim($review['message'] ?? ''));
                    $has_media = !empty($review['image_path']);
                ?>
                <div class="poc-review-item" data-rating="<?php echo $rating; ?>" data-has-comment="<?php echo $has_comment?'1':'0'; ?>" data-has-media="<?php echo $has_media?'1':'0'; ?>" style="padding:1.5rem;border-bottom:1px solid #e5e7eb;">
                    <div style="display:flex;gap:1rem;">
                        <div style="flex-shrink:0;">
                            <?php if($profile_pic): ?>
                                <img src="<?php echo $profile_pic; ?>" alt="<?php echo $reviewer_name; ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                                <div style="width:48px;height:48px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-weight:600;color:#6b7280;"><?php echo strtoupper(substr($reviewer_name,0,1)?:'?'); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:600;color:#1f2937;margin-bottom:0.25rem;"><?php echo $reviewer_name; ?></div>
                            <div style="display:flex;gap:2px;margin-bottom:0.5rem;">
                                <?php for($i=1;$i<=5;$i++): ?>
                                    <svg width="16" height="16" fill="<?php echo ($i<=$rating)?'#f97316':'#d1d5db'; ?>" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <?php endfor; ?>
                            </div>
                            <div style="font-size:0.875rem;color:#6b7280;margin-bottom:0.5rem;"><?php echo date('Y-m-d H:i',strtotime($review['created_at'])); ?></div>
                            <?php if($has_comment): ?><div style="color:#374151;line-height:1.6;margin-bottom:0.75rem;"><?php echo nl2br($comment); ?></div><?php endif; ?>
                            <?php if($has_media): ?><div style="margin-bottom:0.75rem;"><img src="<?php echo htmlspecialchars($review['image_path']); ?>" alt="Review image" style="max-width:200px;border-radius:8px;border:1px solid #e5e7eb;"></div><?php endif; ?>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:8px;">
                                <button onclick="markHelpful(<?php echo $review['id']; ?>, this)" class="helpful-btn<?php echo $review['user_voted'] ? ' voted' : ''; ?>" <?php echo $review['user_voted'] ? 'data-voted="1"' : ''; ?>>
                                    <svg width="15" height="15" fill="currentColor" viewBox="0 0 20 20"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/></svg>
                                    <span class="helpful-label"><?php echo $review['user_voted'] ? (int)$review['helpful_count'] : 'Helpful'; ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php echo render_pagination($poc_page, $poc_total_pages, ['service_id' => $service_id], 'rpage'); ?>
            <?php else: ?>
            <div class="poc-empty">
                <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                <p style="font-size:1rem;font-weight:600;margin:0.75rem 0 0.25rem;">No Reviews Yet</p>
                <p style="font-size:0.875rem;color:#9ca3af;">Be the first to review this service!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.poc-section-title { font-size:1.1rem;font-weight:700;color:#111827;margin:0 0 0.75rem; }
.poc-filter-btn.active { background:#0a2530 !important;color:white !important;border-color:#0a2530 !important; }
.poc-filter-btn:hover { border-color:#0a2530;background:#f0f4f5; }
.poc-review-item { border-bottom:1px solid #f3f4f6;padding:1.25rem 0; }
.poc-review-item:last-child { border-bottom:none; }
.poc-empty { text-align:center;padding:3rem 1rem;color:#6b7280; }
.helpful-btn { display:inline-flex;align-items:center;gap:5px;padding:4px 0;border:none;background:transparent;color:#9ca3af;font-size:0.82rem;font-weight:400;cursor:pointer;transition:color 0.2s; }
.helpful-btn:hover { color:#6b7280; }
.helpful-btn.voted { color:#f97316; }
.helpful-btn.voted svg { fill:#f97316; }

.carousel-arrow:hover { background: rgba(0,0,0,0.75) !important; color: white !important; transform: translateY(-50%) scale(1.15) !important; }
.carousel-thumbnail:hover { border-color: #0d9488 !important; opacity: 0.8; }
.shopee-opt-group { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-start; flex-direction: row; }
.shopee-opt-group .field-error { flex-basis: 100%; width: 100%; }
.shopee-opt-btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem 1rem; border: 2px solid #e5e7eb; border-radius: 0.5rem; background: white; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; color: #374151; min-height: 2.5rem; }
.shopee-opt-btn:hover { border-color: #0d9488; background: #f0fdfa; }
.shopee-opt-btn.active { border-color: #0d9488; background: #0d9488; color: white; }
.quantity-container:hover { border-color: #e5e7eb !important; background: white !important; }
textarea.shopee-opt-btn:hover, textarea.shopee-opt-btn:focus { border-color: #e5e7eb !important; background: white !important; outline: none; }
.notes-textarea { font-size: 0.875rem; font-weight: 500; color: #374151; resize: none !important; overflow-y: auto !important; min-height: 100px !important; max-height: 100px !important; scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }
.notes-textarea::placeholder { font-size: 0.875rem; font-weight: 500; color: #9ca3af; }
textarea.notes-textarea { resize: none !important; }
textarea.notes-textarea::-webkit-resizer { display: none !important; }
.notes-textarea::-webkit-scrollbar { width: 8px; }
.notes-textarea::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
.notes-textarea::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.notes-textarea::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.qty-input-field::-webkit-outer-spin-button, .qty-input-field::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-input-field[type=number] { -moz-appearance: textfield; appearance: textfield; }
.dim-label { font-size:0.7rem;color:#94a3b8;font-weight:600;margin-bottom:4px;display:block;text-transform:uppercase; }
.shopee-form-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: flex-start; position: relative; flex-wrap: wrap; }
.shopee-form-label { min-width: 130px; padding-top: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #374151; flex-shrink: 0; }
.shopee-form-field { flex: 1; position: relative; display: flex !important; flex-direction: column !important; min-width: 0; gap: 4px; }
.field-error { display: flex !important; align-items: center; gap: 0.375rem; color: #ef4444; font-size: 0.875rem; margin-top: 0.5rem; padding-left: 0; width: 100% !important; min-width: 100% !important; flex-basis: 100% !important; order: 999; clear: both; }
.field-error::before { content: '⚠'; font-size: 1rem; flex-shrink: 0; }
.input-field, .shopee-opt-group, .shopee-qty-control { margin-top: 0; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.poc-filter-btn');
    const reviewItems = document.querySelectorAll('.poc-review-item');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            reviewItems.forEach(item => {
                const show = filter === 'all'
                    || (filter === 'comments' && item.dataset.hasComment === '1')
                    || (filter === 'media'    && item.dataset.hasMedia   === '1')
                    || item.dataset.rating === filter;
                item.style.display = show ? '' : 'none';
            });
        });
    });
});

async function markHelpful(reviewId, btn) {
    const voted = btn.dataset.voted === '1';
    try {
        const res = await fetch('/printflow/public/api/review_helpful.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'review_id=' + reviewId
        });
        const data = await res.json();
        if (data.success) {
            const newVoted = !voted;
            btn.dataset.voted = newVoted ? '1' : '0';
            btn.classList.toggle('voted', newVoted);
            btn.querySelector('.helpful-label').textContent = newVoted ? data.count : 'Helpful';
        }
    } catch(e) {}
}
</script>

<?php echo get_service_field_scripts(); ?>

<script>
let currentImageIndex = 0;
const totalImages = <?php echo count($display_images); ?>;
let isAnimating = false;

function updateArrowVisibility() {
    const prevBtn = document.getElementById('carousel-prev');
    const nextBtn = document.getElementById('carousel-next');
    
    if (prevBtn) {
        prevBtn.style.display = currentImageIndex === 0 ? 'none' : 'flex';
    }
    if (nextBtn) {
        nextBtn.style.display = currentImageIndex === totalImages - 1 ? 'none' : 'flex';
    }
}

function changeImage(direction) {
    if (isAnimating) return;
    
    const newIndex = currentImageIndex + direction;
    
    // Prevent going out of bounds
    if (newIndex < 0 || newIndex >= totalImages) return;
    
    isAnimating = true;
    
    const images = document.querySelectorAll('.carousel-image');
    const oldIndex = currentImageIndex;
    currentImageIndex = newIndex;
    
    // Set up positions for slide animation
    const oldImg = images[oldIndex];
    const newImg = images[currentImageIndex];
    
    // Position new image off-screen in the direction it will come from
    if (direction > 0) {
        newImg.style.left = '100%'; // Coming from right
    } else {
        newImg.style.left = '-100%'; // Coming from left
    }
    
    // Force reflow
    newImg.offsetHeight;
    
    // Animate both images
    if (direction > 0) {
        oldImg.style.left = '-100%'; // Exit to left
    } else {
        oldImg.style.left = '100%'; // Exit to right
    }
    newImg.style.left = '0'; // Enter to center
    
    // Update counter and arrows immediately
    const counter = document.getElementById('current-image');
    if (counter) counter.textContent = currentImageIndex + 1;
    updateArrowVisibility();
    
    // Reset animation lock after transition completes
    setTimeout(() => {
        isAnimating = false;
    }, 400);
}

function goToImage(index) {
    if (isAnimating || index === currentImageIndex || index < 0 || index >= totalImages) return;
    const direction = index > currentImageIndex ? 1 : -1;
    currentImageIndex = index - direction;
    changeImage(direction);
}

// Initialize arrow visibility on page load
document.addEventListener('DOMContentLoaded', function() {
    updateArrowVisibility();
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (totalImages > 1) {
        if (e.key === 'ArrowLeft') changeImage(-1);
        if (e.key === 'ArrowRight') changeImage(1);
    }
});
</script>

<script>
// Final Validation Script Update - v4.0.0
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('serviceForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // Clear all previous field errors
            document.querySelectorAll('.field-error').forEach(el => el.remove());
            
            let hasError = false;
            let firstErrorField = null;
            
            const setError = (field, message) => {
                showFieldError(field, message);
                if (!firstErrorField) firstErrorField = field;
                hasError = true;
            };

            // Process every form row to check for required fields (*)
            const rows = form.querySelectorAll('.shopee-form-row');
            rows.forEach(row => {
                const labelEl = row.querySelector('.shopee-form-label');
                if (!labelEl) return;

                const labelText = labelEl.innerText.trim();
                const isRequired = labelText.includes('*');
                if (!isRequired) return;

                // Extract a clean field name for the error message
                const fieldName = labelText.replace('*', '').replace('id', '').replace('ID', '').trim();
                
                let rowHasValue = false;
                let hasControls = false;

                // Check all possible control types in the row
                // 1. SELECTS (includes Branch selection)
                const selects = row.querySelectorAll('select');
                selects.forEach(select => {
                    hasControls = true;
                    if (select.value && select.value !== '') rowHasValue = true;
                });

                // 2. RADIO BUTTONS (includes Color, Size buttons)
                const radios = row.querySelectorAll('input[type="radio"]');
                if (radios.length > 0) {
                    hasControls = true;
                    const checkedRadio = row.querySelector('input[type="radio"]:checked');
                    if (checkedRadio) rowHasValue = true;
                }

                // 3. DIMENSIONS (hidden width/height fields)
                const widthHidden = row.querySelector('#width_hidden');
                const heightHidden = row.querySelector('#height_hidden');
                if (widthHidden && heightHidden) {
                    hasControls = true;
                    if (widthHidden.value && heightHidden.value) rowHasValue = true;
                }

                // 4. DATE INPUTS
                const dates = row.querySelectorAll('input[type="date"]');
                dates.forEach(date => {
                    hasControls = true;
                    if (date.value) rowHasValue = true;
                });

                // 5. FILE UPLOADS
                const files = row.querySelectorAll('input[type="file"]');
                files.forEach(file => {
                    hasControls = true;
                    if (file.files && file.files.length > 0) rowHasValue = true;
                });

                // 6. TEXT / NUMBER / TEXTAREA
                const genericInputs = row.querySelectorAll('input[type="text"], input[type="number"], textarea');
                genericInputs.forEach(input => {
                    if (input.type === 'hidden' || input.id === 'width_hidden' || input.id === 'height_hidden') return;
                    hasControls = true;
                    if (input.value && input.value.trim() !== '') rowHasValue = true;
                });

                // Final check: If row is required (*) but has NO value detected in ANY control
                if (hasControls && !rowHasValue) {
                    const firstControl = row.querySelector('select, label, input:not([type="hidden"]), textarea') || row;
                    setError(firstControl, `${fieldName} is required.`);
                }
            });
            
            if (hasError) {
                e.preventDefault();
                // Scroll to first error
                if (firstErrorField) {
                    const errorRow = firstErrorField.closest('.shopee-form-row') || firstErrorField;
                    errorRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                // Hide top error message if exists
                const topError = document.getElementById('error-message');
                if (topError) topError.style.display = 'none';
            }
        });
    }
    
    function showFieldError(element, message) {
        if (!element) return;
        
        const errorSpan = document.createElement('span');
        errorSpan.className = 'field-error';
        errorSpan.textContent = message;
        
        // Find the top-most field container for this row to ensure it appears at the absolute bottom
        const row = element.closest('.shopee-form-row');
        const container = row ? row.querySelector('.shopee-form-field') : element.closest('.shopee-form-field');
        
        if (container) {
            // Force it to the absolute end of the flex-column container
            container.appendChild(errorSpan);
        } else {
            // Absolute fallback
            element.parentNode.insertBefore(errorSpan, element.nextSibling);
        }
    }
});

</script>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>






