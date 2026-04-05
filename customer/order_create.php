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
     (SELECT image_path FROM review_images WHERE review_id = r.id LIMIT 1) as image_path,
     (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id) as helpful_count,
     (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id AND user_id = ?) as user_voted
     FROM reviews r
     LEFT JOIN customers c ON r.customer_id = c.customer_id
     WHERE r.service_type COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
     ORDER BY r.created_at DESC",
    'is', [$current_user_id, $product['name']]
) ?: [];

$total_reviews = count($reviews);
$avg_rating = $total_reviews > 0 ? array_sum(array_column($reviews, 'rating')) / $total_reviews : 0;
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$with_comments = 0; $with_media = 0;
foreach ($reviews as $r) {
    $rt = (int)$r['rating'];
    if ($rt >= 1 && $rt <= 5) $rating_counts[$rt]++;
    if (!empty(trim($r['message'] ?? ''))) $with_comments++;
    if (!empty($r['image_path'])) $with_media++;
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
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="products.php" class="hover:text-blue-600">Products</a>
            <span>/</span>
            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($product['name']); ?></span>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="shopee-card">
            <div class="shopee-image-section">
                <div class="sticky top-24">
                    <div class="shopee-main-image-wrap">
                        <img src="<?php echo htmlspecialchars($display_img); ?>"
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="shopee-main-image"
                             onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Product'">
                    </div>
                    <?php if (!empty($product['description'])): ?>
                        <div style="margin-top:20px;padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">
                            <h3 style="font-size:14px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">Description</h3>
                            <p style="font-size:14px;line-height:1.6;color:#64748b;white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>

                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                    <div class="flex items-center gap-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg class="w-4 h-4" style="fill:<?php echo ($i <= round($avg_rating)) ? '#FBBF24' : '#E2E8F0'; ?>;" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        <?php endfor; ?>
                        <?php if ($total_reviews > 0): ?>
                            <span class="text-sm text-gray-500 ml-1">(<?php echo number_format($total_reviews); ?> Reviews)</span>
                        <?php endif; ?>
                    </div>
                    <div class="h-4 w-px bg-gray-200"></div>
                    <div class="text-sm text-gray-500"><?php echo $sold_display; ?> Sold</div>
                    <div class="h-4 w-px bg-gray-200"></div>
                    <div class="text-lg font-bold text-gray-900"><?php echo format_currency($product['price']); ?></div>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" id="productOrderForm" data-pf-skip-validation="true" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="shopee-form-row">
                        <div class="shopee-form-label">Branch *</div>
                        <div class="shopee-form-field">
                            <select name="branch_id" class="shopee-opt-btn" required style="width: 175px; cursor: pointer;">
                                <option value="">Select Branch</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="shopee-form-row">
                        <div class="shopee-form-label">Quantity *</div>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <div class="quantity-container shopee-opt-btn" style="display: inline-flex; justify-content: space-between; gap: 1rem; width: 175px; cursor: default;">
                                    <button type="button" style="background: none; border: none; color: #6b7280; font-size: 1.125rem; font-weight: 600; cursor: pointer; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;" onclick="const i=document.getElementById('poc-qty');if(parseInt(i.value)>1)i.value=parseInt(i.value)-1;">&minus;</button>
                                    <input type="number" id="poc-qty" name="quantity" class="qty-input-field" style="border: none; text-align: center; width: 60px; font-size: 0.875rem; font-weight: 500; color: #374151; background: transparent; outline: none; -moz-appearance: textfield;" min="1" max="999" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>" onwheel="return false;">
                                    <button type="button" style="background: none; border: none; color: #6b7280; font-size: 1.125rem; font-weight: 600; cursor: pointer; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;" onclick="const i=document.getElementById('poc-qty');i.value=Math.min(999,(parseInt(i.value)||1)+1);">+</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row pt-8">
                        <div style="width: 130px;"></div>
                        <div class="flex gap-4 flex-1">
                            <a href="products.php" class="shopee-btn-outline" style="flex: 1; min-width: 0;">Back</a>
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
            <!-- Rating summary box -->
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; gap: 2rem; align-items: center; flex-wrap: wrap;">
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; font-weight: 700; color: #f97316; line-height: 1;"><?php echo number_format($avg_rating, 1); ?></div>
                        <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem;">out of 5</div>
                        <div style="display: flex; gap: 2px; margin-top: 0.5rem; justify-content: center;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg width="22" height="22" fill="<?php echo ($i <= round($avg_rating)) ? '#f97316' : '#d1d5db'; ?>" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div style="flex: 1; min-width: 300px;">
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <button class="poc-filter-btn active" data-filter="all" style="padding: 0.5rem 1rem; border: 1px solid #e5e7eb; border-radius: 6px; background: white; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;">All</button>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <button class="poc-filter-btn" data-filter="<?php echo $i; ?>" style="padding: 0.5rem 1rem; border: 1px solid #e5e7eb; border-radius: 6px; background: white; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;"><?php echo $i; ?> Star (<?php echo $rating_counts[$i]; ?>)</button>
                            <?php endfor; ?>
                            <button class="poc-filter-btn" data-filter="comments" style="padding: 0.5rem 1rem; border: 1px solid #e5e7eb; border-radius: 6px; background: white; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;">With Comments (<?php echo $with_comments; ?>)</button>
                            <button class="poc-filter-btn" data-filter="media" style="padding: 0.5rem 1rem; border: 1px solid #e5e7eb; border-radius: 6px; background: white; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;">With Media (<?php echo $with_media; ?>)</button>
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
                    $comment     = htmlspecialchars($review['message'] ?? '');
                    $variation   = htmlspecialchars($review['variation'] ?? '');
                    $has_comment = !empty(trim($review['message'] ?? ''));
                    $has_media   = !empty($review['image_path']);
                ?>
                <div class="poc-review-item" data-rating="<?php echo $rating; ?>" data-has-comment="<?php echo $has_comment ? '1' : '0'; ?>" data-has-media="<?php echo $has_media ? '1' : '0'; ?>" style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                    <div style="display: flex; gap: 1rem;">
                        <div style="flex-shrink: 0;">
                            <?php if ($profile_pic): ?>
                                <img src="<?php echo $profile_pic; ?>" alt="<?php echo $reviewer_name; ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 48px; height: 48px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #6b7280;">
                                    <?php echo strtoupper(substr($reviewer_name, 0, 1) ?: '?'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;"><?php echo $reviewer_name; ?></div>
                            <div style="display: flex; gap: 2px; margin-bottom: 0.5rem;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg width="16" height="16" fill="<?php echo ($i <= $rating) ? '#f97316' : '#d1d5db'; ?>" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">
                                <?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?>
                                <?php if ($variation): ?> | Variation: <?php echo $variation; ?><?php endif; ?>
                            </div>
                            <?php if ($has_comment): ?>
                                <div style="color: #374151; line-height: 1.6; margin-bottom: 0.75rem;"><?php echo nl2br($comment); ?></div>
                            <?php endif; ?>
                            <?php if ($has_media): ?>
                                <div style="margin-bottom: 0.75rem;">
                                    <img src="<?php echo htmlspecialchars($review['image_path']); ?>" alt="Review image" style="max-width: 200px; border-radius: 8px; border: 1px solid #e5e7eb;">
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
.dim-label { font-size:0.7rem;color:#94a3b8;font-weight:600;margin-bottom:4px;display:block;text-transform:uppercase; }
.need-qty-row { display:flex;gap:16px;width:100%; }
@media (max-width:640px) { .need-qty-row { flex-direction:column; } }
.shopee-opt-btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem 1rem; border: 2px solid #e5e7eb; border-radius: 0.5rem; background: white; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; color: #374151; min-height: 2.5rem; }
.quantity-container:hover { border-color: #e5e7eb !important; background: white !important; }
.qty-input-field::-webkit-outer-spin-button, .qty-input-field::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-input-field[type=number] { -moz-appearance: textfield; appearance: textfield; }
.shopee-form-field { flex: 1; position: relative; display: flex !important; flex-direction: column !important; min-width: 0; gap: 4px; }
.field-error { display: flex !important; align-items: center; gap: 0.375rem; color: #ef4444; font-size: 0.875rem; margin-top: 0.5rem; width: 100% !important; flex-basis: 100% !important; order: 999; }
.field-error::before { content: '⚠'; font-size: 1rem; flex-shrink: 0; }

/* Ratings section */
.poc-section-title { font-size:1.1rem;font-weight:700;color:#111827;margin:0 0 0.75rem; }

.poc-filter-btn.active {
    background: #0a2530 !important;
    color: white !important;
    border-color: #0a2530 !important;
}

.poc-filter-btn:hover {
    border-color: #0a2530;
    background: #f0f4f5;
}

/* Review items */
.poc-review-item { border-bottom:1px solid #f3f4f6;padding:1.25rem 0; }
.poc-review-item:last-child { border-bottom:none; }

.poc-empty { text-align:center;padding:3rem 1rem;color:#6b7280; }
.helpful-btn { display:inline-flex;align-items:center;gap:5px;padding:4px 0;border:none;background:transparent;color:#9ca3af;font-size:0.82rem;font-weight:400;cursor:pointer;transition:color 0.2s; }
.helpful-btn:hover { color:#6b7280; }
.helpful-btn.voted { color:#f97316; }
.helpful-btn.voted svg { fill:#f97316; }
</style>

<script>
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
