<?php
/**
 * Fixed Product Order Page
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
require_once __DIR__ . '/../includes/require_id_verified.php';

$product_id = (int)($_GET['product_id'] ?? 0);
$edit_item_key = $_GET['edit_item'] ?? '';

// Load existing cart data if editing
$existing_data = [];
if ($edit_item_key && isset($_SESSION['cart'][$edit_item_key])) {
    $existing_data = $_SESSION['cart'][$edit_item_key];
}

if ($product_id < 1) { header('Location: products.php'); exit; }

$product = db_query(
    "SELECT * FROM products WHERE product_id = ? AND status = 'Activated'",
    'i', [$product_id]
);
if (empty($product)) { header('Location: products.php'); exit; }
$product = $product[0];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $branch_id  = (int)($_POST['branch_id'] ?? 0);
    $quantity   = max(1, min(999, (int)($_POST['quantity'] ?? 1)));
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if ($branch_id < 1) {
        $error = 'Please select a branch.';
    } elseif ($quantity > (int)$product['stock_quantity']) {
        $error = 'Quantity exceeds available stock.';
    } elseif ((int)$product['stock_quantity'] <= 0) {
        $error = 'This product is currently out of stock.';
    } else {
        $item_key = 'product_' . $product_id . '_' . time() . '_' . rand(100, 999);
        $customization = [];

        if (empty($error)) {
            $_SESSION['cart'][$item_key] = [
                'type'            => 'Product',
                'source_page'     => 'products',
                'product_id'      => $product_id,
                'name'            => $product['name'],
                'price'           => (float)$product['price'] * $quantity,
                'quantity'        => $quantity,
                'category'        => $product['category'],
                'branch_id'       => $branch_id,
                'design_tmp_path' => null,
                'design_name'     => null,
                'design_mime'     => null,
                'customization'   => $customization,
            ];

            if (($_POST['action'] ?? '') === 'buy_now') {
                redirect('order_review.php?item=' . urlencode($item_key));
            } else {
                redirect('cart.php');
            }
        }
    }
}

// Product image
$display_img = $product['photo_path'] ?: $product['product_image'] ?: '';
if ($display_img && strpos($display_img, 'http') === false && $display_img[0] !== '/') {
    $display_img = '/' . ltrim($display_img, '/');
}
if (!$display_img) {
    $display_img = 'https://placehold.co/600x600/f8fafc/0f172a?text=' . urlencode($product['name']);
}

// Ratings
// Ensure review_helpful table exists
global $conn;
$conn->query("CREATE TABLE IF NOT EXISTS review_helpful (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user (review_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$current_user_id = get_user_id();
$reviews = db_query(
    "SELECT r.*, c.first_name, c.last_name, c.profile_picture,
     (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id) as helpful_count,
     (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id AND user_id = ?) as user_voted
     FROM reviews r
     LEFT JOIN customers c ON r.user_id = c.customer_id
     WHERE r.service_type COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
     ORDER BY r.created_at DESC",
    'is', [$current_user_id, $product['name']]
) ?: [];

$total_reviews = count($reviews);
$avg_rating = $total_reviews > 0 ? array_sum(array_column($reviews, 'rating')) / $total_reviews : 0;
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$with_comments = 0; $with_media = 0;
foreach ($reviews as $idx => $r) {
    $rt = (int)$r['rating'];
    if ($rt >= 1 && $rt <= 5) $rating_counts[$rt]++;
    if (!empty(trim($r['comment'] ?? ''))) $with_comments++;
    
    // Fetch all images for this review
    $r_imgs = db_query("SELECT image_path FROM review_images WHERE review_id = ?", "i", [$r['id']]) ?: [];
    
    // Fetch all replies for this review
    $r_replies = db_query("
        SELECT rr.reply_message, rr.created_at, u.first_name, u.last_name
        FROM review_replies rr
        INNER JOIN users u ON u.user_id = rr.staff_id
        WHERE rr.review_id = ?
        ORDER BY rr.created_at ASC
    ", 'i', [$r['id']]) ?: [];

    $reviews[$idx]['images'] = $r_imgs;
    $reviews[$idx]['replies'] = $r_replies;
    $reviews[$idx]['has_video'] = !empty($r['video_path']);
    
    if (!empty($r_imgs) || !empty($r['video_path'])) $with_media++;
}

$sold_count = db_query(
    "SELECT COALESCE(SUM(oi.quantity),0) as cnt FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE oi.product_id = ? AND o.status != 'Cancelled' AND (oi.customization_data IS NULL OR oi.customization_data = '' OR oi.customization_data NOT LIKE '%\"service_type\"%')",
    'i', [$product_id]
);
$sold_count = (int)($sold_count[0]['cnt'] ?? 0);
$sold_display = $sold_count >= 1000 ? number_format($sold_count / 1000, 1) . 'k' : $sold_count;

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'") ?: [];

$page_title = 'Order ' . $product['name'] . ' - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <div class="text-sm mb-6 flex items-center gap-2" style="color: #94a3b8;">
            <a href="products.php" style="color: #53c5e0;" class="hover:underline">Products</a>
            <span>/</span>
            <span class="font-semibold" style="color: #eaf6fb;"><?php echo htmlspecialchars($product['name']); ?></span>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="shopee-card">
            <div class="shopee-image-section">
                <div class="sticky-image-container">
                    <div class="shopee-main-image-wrap">
                        <img src="<?php echo htmlspecialchars($display_img); ?>"
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="shopee-main-image"
                             onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Product'">
                    </div>
                    <?php if (!empty($product['description'])): ?>
                        <div style="margin-top:20px;padding:16px;background:rgba(0,49,61,0.4);border-radius:0;border:1px solid rgba(83,197,224,0.15);">
                            <h3 style="font-size:14px;font-weight:700;color:#9fc4d4;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">Description</h3>
                            <p style="font-size:14px;line-height:1.6;color:#eaf6fb;white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="shopee-form-section">
                <h1 style="font-size: 1.8rem; font-weight: 800; color: #eaf6fb; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($product['name']); ?></h1>

                <div class="flex items-center gap-4 mb-6 pb-6" style="border-bottom: 1px solid rgba(83, 197, 224, 0.15);">
                    <div class="flex items-center gap-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg class="w-4 h-4" style="fill:<?php echo ($i <= round($avg_rating)) ? '#f97316' : 'rgba(255,255,255,0.1)'; ?>;" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        <?php endfor; ?>
                        <?php if ($total_reviews > 0): ?>
                            <span style="font-size: 0.85rem; color: #94a3b8; margin-left: 0.25rem;">(<?php echo number_format($total_reviews); ?> Reviews)</span>
                        <?php endif; ?>
                    </div>
                    <div style="height: 1rem; width: 1px; background: rgba(83, 197, 224, 0.15);"></div>
                    <div style="font-size: 0.85rem; color: #94a3b8;"><?php echo $sold_display; ?> Sold</div>
                    <div style="height: 1rem; width: 1px; background: rgba(83, 197, 224, 0.15);"></div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #53c5e0;"><?php echo format_currency($product['price']); ?></div>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" id="productOrderForm" data-pf-skip-validation="true" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="shopee-form-row">
                        <div class="shopee-form-label">Branch *</div>
                        <div class="shopee-form-field">
                            <select name="branch_id" class="shopee-opt-btn" required style="width: 175px; cursor: pointer;">
                                <option value="">Select Branch</option>
                                <?php 
                                $saved_branch = $existing_data['branch_id'] ?? '';
                                foreach ($branches as $b): 
                                    $selected = ($saved_branch == $b['id']) ? ' selected' : '';
                                ?>
                                    <option value="<?php echo $b['id']; ?>"<?php echo $selected; ?>><?php echo htmlspecialchars($b['branch_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="shopee-form-row">
                        <div class="shopee-form-label" style="color: #eaf6fb;">Quantity *</div>
                        <div class="shopee-form-field">
                            <div class="quantity-container" style="width: 175px;">
                                <button type="button" class="qty-btn" onmouseover="this.style.background='rgba(83, 197, 224, 0.15)'" onmouseout="this.style.background='transparent'" onclick="const i=document.getElementById('poc-qty');if(parseInt(i.value)>1){i.value=parseInt(i.value)-1;hideStockWarning();}">−</button>
                                <input type="number" id="poc-qty" name="quantity" class="qty-input-field" style="border-left: 1.5px solid rgba(83, 197, 224, 0.3); border-right: 1.5px solid rgba(83, 197, 224, 0.3);" min="1" max="<?php echo (int)$product['stock_quantity']; ?>" value="<?php echo (int)($existing_data['quantity'] ?? $_POST['quantity'] ?? $_GET['qty'] ?? 1); ?>" onwheel="return false;" oninput="const max=<?php echo (int)$product['stock_quantity']; ?>;if(parseInt(this.value)>max){this.value=max;showStockWarning(max);}else{hideStockWarning();}"> 
                                <button type="button" class="qty-btn" onmouseover="this.style.background='rgba(83, 197, 224, 0.15)'" onmouseout="this.style.background='transparent'" onclick="const i=document.getElementById('poc-qty');const max=<?php echo (int)$product['stock_quantity']; ?>;if(parseInt(i.value)<max){i.value=parseInt(i.value)+1;hideStockWarning();}else{showStockWarning(max);}">+</button>
                            </div>
                            <div id="stock-warning" style="display: none; font-size: 0.75rem; color: #fb7185; margin-top: 0.65rem; font-weight: 600;"></div>
                        </div>
                    </div>

                    <div class="shopee-form-row pt-8">
                        <div style="width: 140px;"></div>
                        <div class="flex gap-4 flex-1">
                            <a href="products.php" class="shopee-btn-outline" style="flex: 1; min-width: 0;">BACK</a>
                            <?php if ((int)$product['stock_quantity'] > 0): ?>
                                <button type="submit" name="action" value="add_to_cart" class="shopee-btn-outline" style="flex: 1.5; min-width: 0; display: flex; align-items: center; justify-content: center; gap: 0.6rem;" title="Add to Cart">
                                    <svg style="width: 1.125rem; height: 1.125rem; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    <span>ADD TO CART</span>
                                </button>
                                <button type="submit" name="action" value="buy_now" class="shopee-btn-primary" style="flex: 1; min-width: 0;">
                                    <span>BUY NOW</span>
                                </button>
                            <?php else: ?>
                                <button type="button" disabled class="shopee-btn-outline" style="flex: 2.5; opacity: 0.5; cursor: not-allowed;">OUT OF STOCK</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Product Ratings Section -->
        <?php
        $reviews_per_page = 10;
        $poc_page = max(1, (int)($_GET['rpage'] ?? 1));
        $poc_total_pages = $total_reviews > 0 ? (int)ceil($total_reviews / $reviews_per_page) : 1;
        $poc_page = min($poc_page, $poc_total_pages);
        $poc_offset = ($poc_page - 1) * $reviews_per_page;
        $reviews_paged = array_slice($reviews, $poc_offset, $reviews_per_page);
        ?>
        <div style="margin-top:24px;padding:2rem;background:rgba(0,49,61,0.8);border:1px solid rgba(83,197,224,0.18);border-radius:12px;backdrop-filter:blur(10px);">
            <h2 class="poc-section-title" style="color:#eaf6fb; font-size: 1.35rem; margin-bottom: 1.5rem;">Product Ratings</h2>

            <?php if ($total_reviews > 0): ?>
            <!-- Rating summary box -->
            <div style="background: rgba(0,28,36,0.95); border: 1px solid rgba(83,197,224,0.16); border-radius: 12px; padding: 2rem; margin-bottom: 2rem;">
                <div style="display: flex; gap: 3rem; align-items: center; flex-wrap: wrap;">
                    <div style="text-align: center; border-right: 1px solid rgba(83, 197, 224, 0.1); padding-right: 3rem;">
                        <div style="font-size: 3.5rem; font-weight: 800; color: #f97316; line-height: 1;"><?php echo number_format($avg_rating, 1); ?></div>
                        <div style="font-size: 1rem; color: #94a3b8; margin-top: 0.5rem;">out of 5</div>
                        <div style="display: flex; gap: 4px; margin-top: 1rem; justify-content: center;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg width="24" height="24" fill="<?php echo ($i <= round($avg_rating)) ? '#f97316' : 'rgba(255,255,255,0.1)'; ?>" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div style="flex: 1; min-width: 300px;">
                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                            <button class="poc-filter-btn active" data-filter="all">All</button>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <button class="poc-filter-btn" data-filter="<?php echo $i; ?>"><?php echo $i; ?> Star (<?php echo $rating_counts[$i]; ?>)</button>
                            <?php endfor; ?>
                            <button class="poc-filter-btn" data-filter="comments">With Comments (<?php echo $with_comments; ?>)</button>
                            <button class="poc-filter-btn" data-filter="media">With Media (<?php echo $with_media; ?>)</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review list -->
            <div id="poc-reviews-container">
                <?php foreach ($reviews_paged as $review):
                    $reviewer_name = htmlspecialchars(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')));
                    $profile_pic = !empty($review['profile_picture'])
                        ? '/printflow/public/assets/uploads/profiles/' . htmlspecialchars($review['profile_picture'])
                        : '';
                    $rating      = (int)$review['rating'];
                    $comment     = htmlspecialchars($review['comment'] ?? '');
                    $variation   = htmlspecialchars($review['variation'] ?? '');
                    $has_comment = !empty(trim($review['comment'] ?? ''));
                    $rev_imgs    = $review['images'] ?? [];
                    $has_video   = !empty($review['video_path']);
                    $has_media   = !empty($rev_imgs) || $has_video;
                ?>
                <div id="review-<?php echo $review['id']; ?>" class="poc-review-item" data-rating="<?php echo $rating; ?>" data-has-comment="<?php echo $has_comment ? '1' : '0'; ?>" data-has-media="<?php echo $has_media ? '1' : '0'; ?>" style="padding: 2rem 0; border-bottom: 1px solid rgba(83,197,224,0.12);">
                    <div style="display: flex; gap: 1rem;">
                        <div style="flex-shrink: 0;">
                            <?php if ($profile_pic): ?>
                                <img src="<?php echo $profile_pic; ?>" alt="<?php echo $reviewer_name; ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                    <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(83,197,224,0.1); display: flex; align-items: center; justify-content: center; font-weight: 600; color: #53c5e0;">
                                        <?php echo strtoupper(substr($reviewer_name, 0, 1) ?: '?'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: #eaf6fb; margin-bottom: 0.25rem; font-size: 1rem;"><?php echo $reviewer_name; ?></div>
                                <div style="display: flex; gap: 3px; margin-bottom: 0.5rem;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg width="14" height="14" fill="<?php echo ($i <= $rating) ? '#f97316' : 'rgba(255,255,255,0.1)'; ?>" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                                <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.75rem;">
                                    <?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?> 
                                    <?php if ($variation): ?> <span style="margin: 0 8px; opacity: 0.5;">|</span> Variation: <?php echo $variation; ?><?php endif; ?>
                                </div>
                                <?php if ($has_comment): ?>
                                    <div style="color: #b9d4df; line-height: 1.62; margin-bottom: 1rem; font-size: 0.95rem;"><?php echo nl2br($comment); ?></div>
                                <?php endif; ?>
                            
                            <?php if(!empty($rev_imgs)): ?>
                                <div style="display:flex; overflow-x:auto; gap:12px; margin-bottom:1rem; padding-bottom:10px; scrollbar-width: thin;">
                                    <?php foreach($rev_imgs as $img): 
                                        $ipath = $img['image_path'];
                                        if (strpos($ipath, 'http') === false && (!isset($ipath[0]) || $ipath[0] !== '/')) $ipath = '/printflow/' . $ipath;
                                    ?>
                                        <div style="flex: 0 0 160px; aspect-ratio:1; border-radius:10px; overflow:hidden; border:1px solid rgba(83,197,224,0.15);">
                                            <img src="<?php echo htmlspecialchars($ipath); ?>" alt="Review image" style="width:100%; height:100%; object-fit:cover; cursor:pointer; transition: transform 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" onclick="window.open(this.src, '_blank')">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if($has_video): 
                                $vpath = $review['video_path'];
                                if (strpos($vpath, 'http') === false && (!isset($vpath[0]) || $vpath[0] !== '/')) $vpath = '/printflow/' . $vpath;
                            ?>
                                <div style="margin-bottom:1rem; max-width:400px;">
                                    <div style="position:relative; width:100%; aspect-ratio:16/9; border-radius:10px; overflow:hidden; border:1px solid rgba(83,197,224,0.18);">
                                        <video src="<?php echo htmlspecialchars($vpath); ?>" controls style="width:100%; height:100%; object-fit:cover;"></video>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($review['replies'])): ?>
                                <div style="margin-top: 1.25rem; padding: 1.25rem; background: rgba(83, 197, 224, 0.04); border-left: 4px solid #53c5e0; border-radius: 8px;">
                                    <div style="font-size: 0.75rem; font-weight: 800; color: #53c5e0; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.1em; display: flex; align-items: center; gap: 8px;">
                                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l5 5m-5-5l5-5"/></svg>
                                        Staff Response
                                    </div>
                                    <?php foreach ($review['replies'] as $reply): ?>
                                        <div style="margin-bottom: 0.75rem; last-child: margin-bottom: 0;">
                                            <div style="color: #eaf6fb; font-size: 0.95rem; line-height: 1.62;"><?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?></div>
                                            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.5rem; display: flex; align-items: center; gap: 8px;">
                                                <span style="color: #eaf6fb; font-weight: 700;"><?php echo htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']); ?></span>
                                                <span style="opacity: 0.4;">&bull;</span>
                                                <span><?php echo date('Y-m-d', strtotime($reply['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

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

            <!-- Pagination -->
            <?php echo render_pagination($poc_page, $poc_total_pages, ['product_id' => $product_id], 'rpage'); ?>

            <?php else: ?>
            <div class="poc-empty">
                <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                <p style="font-size:1rem;font-weight:600;margin:0.75rem 0 0.25rem;">No Reviews Yet</p>
                <p style="font-size:0.875rem;color:#9ca3af;">Be the first to review this product!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.shopee-card {
    background: rgba(0, 49, 61, 0.82);
    border: 1.5px solid rgba(83, 197, 224, 0.18);
    border-radius: 14px;
    display: flex;
    overflow: hidden;
    backdrop-filter: blur(12px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
}

.shopee-image-section {
    flex: 0 0 450px;
    background: rgba(0, 21, 27, 0.4);
    border-right: 1px solid rgba(83, 197, 224, 0.12);
    padding: 30px;
}

@media (max-width: 1024px) {
    .shopee-card { flex-direction: column; }
    .shopee-image-section { flex: 0 0 auto; border-right: none; border-bottom: 1px solid rgba(83, 197, 224, 0.12); }
}

.shopee-form-section {
    flex: 1;
    padding: 40px;
    display: flex;
    flex-direction: column;
}

.shopee-main-image {
    width: 100%;
    aspect-ratio: 1;
    object-fit: contain;
    background: #fff;
    border-radius: 8px;
}

.shopee-form-row {
    display: flex;
    margin-bottom: 24px;
    align-items: center;
}

.shopee-form-label {
    width: 140px;
    font-size: 0.85rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.dim-label { font-size: 0.75rem; color: #94a3b8; font-weight: 700; margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.05em; }
.need-qty-row { display: flex; gap: 24px; width: 100%; }
@media (max-width: 640px) { .need-qty-row { flex-direction: column; } }

.shopee-opt-btn { 
    display: inline-flex; 
    align-items: center; 
    padding: 0 1rem; 
    border: 1.5px solid rgba(83, 197, 224, 0.25); 
    border-radius: 0; 
    background: rgba(12, 43, 56, 0.95); 
    cursor: pointer; 
    transition: all 0.2s; 
    font-size: 0.9rem; 
    font-weight: 600; 
    color: #eaf6fb; 
    height: 42px; 
    width: 100%;
    outline: none; 
}
.shopee-opt-btn:hover, .shopee-opt-btn:focus { border-color: #53c5e0 !important; box-shadow: 0 0 15px rgba(83, 197, 224, 0.15); }
.shopee-opt-btn option { background: #001c24; color: #eaf6fb; }

.quantity-container {
    display: flex;
    align-items: center;
    border: 1.5px solid rgba(83, 197, 224, 0.25);
    border-radius: 0;
    background: rgba(12, 43, 56, 0.95);
    height: 42px;
    width: 100%;
    overflow: hidden;
    transition: all 0.2s;
}
.quantity-container:hover { border-color: #53c5e0; }

.qty-btn {
    flex: 0 0 48px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: #53c5e0;
    font-size: 1.25rem;
    cursor: pointer;
    transition: all 0.2s;
}
.qty-btn:hover { background: rgba(83, 197, 224, 0.1); }
.qty-input-field {
    flex: 1;
    min-width: 0;
    height: 100%;
    background: rgba(0,0,0,0.1);
    border: none;
    text-align: center;
    color: #eaf6fb;
    font-weight: 700;
    font-size: 1.1rem;
    outline: none;
    margin: 0;
    padding: 0;
}

.qty-input-field::-webkit-outer-spin-button,
.qty-input-field::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.qty-input-field[type=number] {
    -moz-appearance: textfield;
}

.shopee-form-field { flex: 1; position: relative; display: flex !important; flex-direction: column !important; min-width: 0; gap: 4px; }
.field-error { display: flex !important; align-items: center; gap: 0.5rem; color: #fb7185; font-size: 0.85rem; font-weight: 600; margin-top: 0.65rem; width: 100% !important; flex-basis: 100% !important; padding-left: 4px; }
.field-error::before { content: '⚠'; font-size: 1rem; }

.shopee-btn-outline {
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1.5rem;
    border: 1.5px solid #53c5e0;
    border-radius: 0; 
    background: transparent;
    color: #eaf6fb;
    font-weight: 800;
    font-size: 0.85rem;
    letter-spacing: 0.08em;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    cursor: pointer;
    text-transform: uppercase;
}

.shopee-btn-outline:hover {
    background: rgba(83, 197, 224, 0.1);
    box-shadow: 0 0 15px rgba(83, 197, 224, 0.2);
}

.shopee-btn-primary {
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1.5rem;
    border: none;
    border-radius: 0; 
    background: #53c5e0;
    color: #001c24;
    font-weight: 900;
    font-size: 0.95rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    box-shadow: 0 0 15px rgba(83, 197, 224, 0.25);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.shopee-btn-primary:hover {
    background: #7adcf5;
    box-shadow: 0 0 25px rgba(83, 197, 224, 0.5);
    transform: translateY(-2px);
}

/* Ratings section */
.poc-section-title { font-size:1.1rem;font-weight:700;color:#111827;margin:0 0 0.75rem; }

.poc-filter-btn {
    padding: 0.65rem 1.25rem;
    border: 1.5px solid rgba(83, 197, 224, 0.18);
    border-radius: 10px;
    background: rgba(12, 43, 56, 0.8);
    color: #b9d4df;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.2s;
}

.poc-filter-btn.active {
    background: #53c5e0 !important;
    color: #001c24 !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 15px rgba(83, 197, 224, 0.2);
}

.poc-filter-btn:hover:not(.active) {
    border-color: #53c5e0;
    background: rgba(83, 197, 224, 0.1);
}

/* Review items */
.poc-review-item { border-bottom:1px solid rgba(83,197,224,0.1);padding:1.5rem 0; }
.poc-review-item:last-child { border-bottom:none; }

.poc-empty { text-align:center;padding:4rem 1rem;color:#94a3b8; }
.helpful-btn { display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border:1.5px solid rgba(83,197,224,0.18);border-radius:8px;background:rgba(83,197,224,0.05);color:#94a3b8;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.2s; }
.helpful-btn:hover { color:#53c5e0; border-color: #53c5e0; }
.helpful-btn.voted { color:#f97316; border-color: #f97316; background: rgba(249, 115, 22, 0.1); }
.helpful-btn.voted svg { fill:#f97316; }
</style>

<script>
function showStockWarning(max) {
    const warning = document.getElementById('stock-warning');
    if (warning) {
        warning.textContent = 'Maximum stock available: ' + max;
        warning.style.display = 'block';
    }
}

function hideStockWarning() {
    const warning = document.getElementById('stock-warning');
    if (warning) {
        warning.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('productOrderForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            document.querySelectorAll('.field-error').forEach(el => el.remove());
            let hasError = false, firstErrorField = null;

            const setError = (field, message) => {
                const errorSpan = document.createElement('span');
                errorSpan.className = 'field-error';
                errorSpan.textContent = message;
                const container = field.closest('.shopee-form-field') || field.parentNode;
                container.appendChild(errorSpan);
                if (!firstErrorField) firstErrorField = field;
                hasError = true;
            };

            const branchSelect = form.querySelector('select[name="branch_id"]');
            if (branchSelect && !branchSelect.value) setError(branchSelect, 'Branch is required.');

            if (hasError) {
                e.preventDefault();
                if (firstErrorField) firstErrorField.closest('.shopee-form-row')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
});

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
