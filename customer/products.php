<?php
/**
 * Customer Products Page (Fixed Products Only)
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Get filter parameters
$category = $_GET['category'] ?? '';

// Build query — show all Activated products
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.product_id AND pv.status = 'Active') as variant_count,
        (SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE oi.product_id = p.product_id AND o.status != 'Cancelled' AND (oi.customization_data IS NULL OR oi.customization_data = '' OR oi.customization_data NOT LIKE '%\"service_type\"%')) as sold_count,
        (SELECT AVG(rating) FROM reviews r WHERE r.service_type COLLATE utf8mb4_unicode_ci = p.name COLLATE utf8mb4_unicode_ci) as avg_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.service_type COLLATE utf8mb4_unicode_ci = p.name COLLATE utf8mb4_unicode_ci) as review_count
        FROM products p 
        WHERE p.status = 'Activated'";
$params = [];
$types = '';

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

// Pagination settings
$items_per_page = 12;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Count total items for pagination
$count_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Activated'";
$count_params = [];
$count_types = '';

if (!empty($category)) {
    $count_sql .= " AND category = ?";
    $count_params[] = $category;
    $count_types .= 's';
}

$total_result = db_query($count_sql, $count_types, $count_params);
$total_items = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

$sql .= " ORDER BY name ASC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$products = db_query($sql, $types, $params);

$page_title = 'Products - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --shopee-orange: #53c5e0;
        --shopee-bg: #00151b;
        --shopee-card-bg: rgba(0,49,61,0.85);
        --shopee-text: #e0f2fe;
        --shopee-muted: #94a3b8;
        --shopee-border: rgba(83,197,224,0.2);
    }

    .shopee-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 20px;
    }

    @media (max-width: 640px) {
        .shopee-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
    }

    .shopee-card {
        background: var(--shopee-card-bg);
        border: none;
        border-radius: 0;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
        backdrop-filter: blur(8px);
    }

    .shopee-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.4), 0 0 20px rgba(83,197,224,0.1);
    }

    .shopee-img {
        width: 100%;
        aspect-ratio: 1.2;
        object-fit: cover;
    }

    .shopee-body {
        padding: 10px 10px 0px 10px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .shopee-name {
        font-size: 0.95rem;
        line-height: 1.3rem;
        height: 2.6rem;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        color: var(--shopee-text);
        margin-bottom: 6px;
    }

    .shopee-category {
        font-size: 0.75rem;
        color: var(--shopee-muted);
        margin-bottom: 4px;
    }

    .shopee-price-row {
        margin-top: auto;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .shopee-price {
        color: var(--shopee-orange);
        font-weight: 800;
        font-size: 1.2rem;
        letter-spacing: -0.02em;
    }

    .shopee-footer {
        padding: 8px 10px;
        border-top: 1px solid var(--shopee-border);
        display: flex;
        gap: 8px;
    }

    .shopee-btn {
        flex: 1;
        padding: 8px 0;
        border-radius: 0;
        font-size: 0.8rem;
        font-weight: 700;
        text-align: center;
        text-transform: uppercase;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        cursor: pointer;
    }

    .shopee-btn-cart {
        background: rgba(83, 197, 224, 0.08);
        color: var(--shopee-orange);
        border: none;
        flex: 0 0 45px;
    }
    
    .shopee-btn-cart:hover {
        background: rgba(83, 197, 224, 0.15);
    }

    .shopee-btn-buy {
        background: var(--shopee-orange);
        color: #001c24;
        box-shadow: 0 0 12px rgba(83, 197, 224, 0.2);
    }

    .shopee-btn-buy:hover {
        background: #7adcf5;
        box-shadow: 0 0 20px rgba(83, 197, 224, 0.5);
        transform: translateY(-1px);
    }

    .rating-stars {
        color: #ffca11;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 2px;
        margin-bottom: 2px;
    }

    .rating-text {
        font-size: 0.75rem;
        color: var(--shopee-muted);
        margin-left: 5px;
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h1 class="text-2xl font-bold text-gray-800">Available Products</h1>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="bg-white rounded-lg p-12 text-center shadow-sm">
                <div class="text-6xl mb-4">📦</div>
                <p class="text-gray-500 text-lg">No products found.</p>
                <a href="products.php" class="text-shopee-orange mt-4 inline-block hover:underline font-semibold">Browse all products</a>
            </div>
        <?php else: ?>
            <div class="shopee-grid">
                <?php foreach ($products as $product): 
                    $display_img = $product['photo_path'] ?: $product['product_image'] ?: "/printflow/public/assets/images/services/default.png";
                    if ($display_img[0] !== '/' && strpos($display_img, 'http') === false) $display_img = '/' . $display_img;
                    
                    $sold_count = (int)$product['sold_count'];
                    $avg_rating = (float)$product['avg_rating'];
                    $review_count = (int)$product['review_count'];
                    $stock = (int)$product['stock_quantity'];
                    $sold_display = $sold_count >= 1000 ? number_format($sold_count / 1000, 1) . 'k' : $sold_count;
                    $stock_display = $stock >= 1000 ? number_format($stock / 1000, 1) . 'k' : $stock;
                ?>
                    <div class="shopee-card" onclick="window.location.href='order_create.php?product_id=<?php echo $product['product_id']; ?>'">
                        <img src="<?php echo htmlspecialchars($display_img); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="shopee-img">
                        <div class="shopee-body">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <span class="shopee-category"><?php echo htmlspecialchars($product['category']); ?></span>
                                <span style="font-size: 0.75rem; font-weight: 600; color: <?php echo $stock > 10 ? '#059669' : ($stock > 0 ? '#f59e0b' : '#dc2626'); ?>"><?php echo $stock_display; ?> in stock</span>
                            </div>
                            <h3 class="shopee-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            
                            <div class="rating-stars">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <svg style="width: 14px; height: 14px;" fill="<?php echo ($i <= round($avg_rating)) ? '#ffca11' : '#e5e7eb'; ?>" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                <?php endfor; ?>
                                <span class="rating-text"><?php echo $review_count > 0 ? "($review_count)" : ''; ?></span>
                                <span style="margin-left: auto; font-size: 0.75rem; color: var(--shopee-muted);"><?php echo $sold_display; ?> sold</span>
                            </div>

                            <div class="shopee-price-row">
                                <span class="shopee-price"><?php echo format_currency($product['price']); ?></span>
                            </div>
                        </div>
                        <div class="shopee-footer" onclick="event.stopPropagation()">
                            <button onclick="addToCartDirect(<?php echo $product['product_id']; ?>)" class="shopee-btn shopee-btn-cart" title="Add to Cart">
                                <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </button>
                            <a href="order_create.php?product_id=<?php echo $product['product_id']; ?>&buy_now=1" class="shopee-btn shopee-btn-buy">Buy Now</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <div class="mt-12 flex justify-center">
                <?php echo get_pagination_links($current_page, $total_pages, ['category' => $category]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
var PF_CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

async function addToCartDirect(productId) {
    try {
        const response = await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                product_id: productId,
                quantity: 1,
                csrf_token: PF_CSRF_TOKEN
            })
        });

        const data = await response.json();

        if (data.success) {
            if (window.updateCartBadge) updateCartBadge(data.cart_count);
            showToast('Added to cart!');
        } else {
            showToast(data.message || 'Failed to add to cart.', true);
        }
    } catch (err) {
        console.error('Cart Error:', err);
        alert('An error occurred. Please try again.');
    }
}

function showToast(msg, isError) {
    let toast = document.getElementById('shopee-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'shopee-toast';
        document.body.appendChild(toast);
    }
    
    toast.textContent = msg;
    toast.style.cssText = `
        position: fixed;
        bottom: 5rem;
        left: 50%;
        transform: translateX(-50%);
        background: ${isError ? 'rgba(239,68,68,0.92)' : 'rgba(0,0,0,0.85)'};
        color: white;
        padding: 12px 24px;
        border-radius: 0;
        font-size: 0.9rem;
        font-weight: 500;
        z-index: 10000;
        transition: opacity 0.3s;
        pointer-events: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    `;
    
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.opacity = '0';
    }, 2500);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
