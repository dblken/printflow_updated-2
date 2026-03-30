<?php
/**
 * Customer Services
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer_service_catalog.php';

// require_role('Customer'); // Make services visible to non-logged in users as well if they land here
ensure_ratings_table_exists();

$legacy_catalog = printflow_default_customer_service_catalog();
$legacy_by_name = [];
foreach ($legacy_catalog as $d) {
    $legacy_by_name[strtolower(trim($d['name']))] = $d;
}

$visible_rows = db_query(
    "SELECT * FROM services WHERE status = 'Activated' ORDER BY name ASC",
    '',
    []
) ?: [];

$services_table_count = 0;
$cnt_row = db_query('SELECT COUNT(*) AS c FROM services', '', []);
if (!empty($cnt_row)) {
    $services_table_count = (int) ($cnt_row[0]['c'] ?? 0);
}

$core_services = [];
if (!empty($visible_rows)) {
    foreach ($visible_rows as $row) {
        $key = strtolower(trim($row['name']));
        $img = trim((string) ($row['hero_image'] ?? ''));
        if ($img === '') {
            $img = $legacy_by_name[$key]['img'] ?? '/printflow/public/assets/images/services/default.png';
        }
        if ($img !== '' && $img[0] !== '/') {
            $img = '/' . ltrim($img, '/');
        }
        $link = trim((string) ($row['customer_link'] ?? ''));
        if ($link === '') {
            $link = $legacy_by_name[$key]['link'] ?? 'products.php';
        }
        $modalRaw = trim((string) ($row['customer_modal_text'] ?? ''));
        $core_services[] = [
            'name' => $row['name'],
            'category' => $row['category'] ?? '',
            'img' => $img,
            'link' => $link,
            'modal_text' => $modalRaw !== '' ? $modalRaw : printflow_default_customer_service_modal_text(),
        ];
    }
} elseif ($services_table_count === 0) {
    // No DB rows yet: show static catalog until services are seeded/managed in Admin.
    $defModal = printflow_default_customer_service_modal_text();
    $core_services = [];
    foreach ($legacy_catalog as $row) {
        $core_services[] = array_merge($row, ['modal_text' => $defModal]);
    }
}

$csrf_token = generate_csrf_token();

$page_title = 'Services - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

// Fetch actual ratings from the database grouped by service
// Mapping common variations of service names saved during order to current core service names
$service_aliases = [
    'glass & wall sticker printing' => 'glass/wall',
    'decals / stickers' => 'stickers',
    'stickers / decals' => 'stickers',
    't-shirt printing' => 't-shirt',
    'tarpaulin printing' => 'tarpaulin',
    'reflectorized signage' => 'reflectorized',
    'sintraboard standees' => 'sintraboard standees',
    'transparent sticker printing' => 'transparent'
];

$rating_rows = db_query("SELECT service_type, COUNT(*) as rcount, AVG(rating) as ravg FROM reviews WHERE service_type IS NOT NULL AND service_type != '' GROUP BY service_type") ?: [];
$ratings_map = [];
foreach ($core_services as $srv) {
    if (isset($srv['name'])) {
        $ratings_map[strtolower(trim($srv['name']))] = ['count' => 0, 'avg' => 0];
    }
}

foreach ($rating_rows as $rr) {
    $db_stype = strtolower(trim((string)$rr['service_type']));
    $rcount = (int)$rr['rcount'];
    $ravg = (float)$rr['ravg'];
    
    // Normalize using predefined aliases
    if (isset($service_aliases[$db_stype])) {
        $db_stype = $service_aliases[$db_stype];
    }
    
    $best_match = null;
    // 1. Exact match against core services array
    foreach ($core_services as $srv) {
        $name_key = strtolower(trim($srv['name']));
        if ($name_key === $db_stype) {
            $best_match = $name_key;
            break;
        }
    }
    // 2. Fallback prefix/contains match
    if (!$best_match) {
        foreach ($core_services as $srv) {
            $name_key = strtolower(trim($srv['name']));
            if (strpos($db_stype, $name_key) !== false || strpos($name_key, $db_stype) !== false) {
                $best_match = $name_key;
                break;
            }
        }
    }
    
    if ($best_match && isset($ratings_map[$best_match])) {
        // Aggregate if multiple variations map to the same core service
        $prev_count = $ratings_map[$best_match]['count'];
        $prev_avg = $ratings_map[$best_match]['avg'];
        $new_count = $prev_count + $rcount;
        $new_avg = (($prev_avg * $prev_count) + ($ravg * $rcount)) / $new_count;
        
        $ratings_map[$best_match]['count'] = $new_count;
        $ratings_map[$best_match]['avg'] = round($new_avg, 1);
    }
}
$GLOBALS['pf_services_ratings'] = $ratings_map;

// Reusable card template function
function render_service_card($name, $category, $img, $link, $is_service = true, $price = null, $stock = null, $modal_text = null) {
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $img)) {
        $img = '/printflow/public/assets/images/services/default.png';
    }
    if ($modal_text === null || trim((string) $modal_text) === '') {
        $modal_text = printflow_default_customer_service_modal_text();
    }
    // Escape values for JS safely using json_encode
    $json_name = htmlspecialchars(json_encode($name), ENT_QUOTES, 'UTF-8');
    $json_category = htmlspecialchars(json_encode($category), ENT_QUOTES, 'UTF-8');
    $json_img = htmlspecialchars(json_encode($img), ENT_QUOTES, 'UTF-8');
    $json_link = htmlspecialchars(json_encode($link), ENT_QUOTES, 'UTF-8');
    $json_price = htmlspecialchars(json_encode($price !== null ? format_currency($price) : ''), ENT_QUOTES, 'UTF-8');
    $json_stock = htmlspecialchars(json_encode($stock !== null ? (string)$stock : ''), ENT_QUOTES, 'UTF-8');
    $json_modal_text = htmlspecialchars(json_encode($modal_text), ENT_QUOTES, 'UTF-8');
    $is_service_str = $is_service ? 'true' : 'false';

    global $pf_services_ratings;
    $r_key = strtolower(trim((string)$name));
    $r_data = $pf_services_ratings[$r_key] ?? ['count' => 0, 'avg' => 0];
    $rcount = $r_data['count'];
    $ravg = $r_data['avg'];
    $yellow_stars = floor($ravg);
    ?>
    <div class="ct-product-card cursor-pointer group" onclick="openServiceModal(<?php echo $json_name; ?>, <?php echo $json_category; ?>, <?php echo $json_img; ?>, <?php echo $json_link; ?>, <?php echo $is_service_str; ?>, <?php echo $json_price; ?>, <?php echo $json_stock; ?>, <?php echo $json_modal_text; ?>)">
        <div class="ct-product-img overflow-hidden">
            <div class="ct-product-img-inner transition-transform duration-500 group-hover:scale-110">
                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($name); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:0.5rem;">
            </div>
        </div>
        <div class="ct-product-body" style="text-align: left; padding: 1.25rem 1rem;">
            <span class="ct-product-category" style="margin-bottom: 0.5rem; display: inline-block;"><?php echo htmlspecialchars($category); ?></span>
            <h3 class="ct-product-name" style="margin-bottom: 0.4rem; font-weight: 700; font-size: 1.1rem; line-height: 1.3;">
                <?php echo htmlspecialchars($name); ?>
            </h3>
            
            <!-- Visible Rating Stars mimicking standard e-commerce -->
            <div style="display: flex; align-items: center; gap: 2px; margin-bottom: <?php echo $is_service ? '1rem;' : '0.5rem;'; ?>">
                <?php for($i=1; $i<=5; $i++): ?>
                    <?php if ($i <= $yellow_stars): ?>
                        <svg width="15" height="15" fill="#FBBF24" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    <?php else: ?>
                        <svg width="15" height="15" fill="#374151" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($rcount > 0): ?>
                    <a href="/printflow/customer/reviews.php?service=<?php echo urlencode($name); ?>" onclick="event.stopPropagation();" style="font-size: 0.75rem; color: #9ca3af; margin-left: 6px; font-weight: 500; text-decoration: none;" onmouseover="this.style.textDecoration='underline'; this.style.color='#53C5E0'" onmouseout="this.style.textDecoration='none'; this.style.color='#9ca3af'"><?php echo number_format($ravg, 1); ?> &bull; <?php echo $rcount; ?> review<?php echo $rcount > 1 ? 's' : ''; ?></a>
                <?php else: ?>
                    <span style="font-size: 0.75rem; color: #4b5563; margin-left: 6px; font-weight: 500;">No ratings yet</span>
                <?php endif; ?>
            </div>
            
            <?php if (!$is_service && $price !== null): ?>
                <p class="ct-product-price" style="margin-bottom: 1rem; font-size: 1.15rem;"><?php echo format_currency($price); ?></p>
            <?php endif; ?>

            <div class="ct-product-actions" style="margin-top: auto; display: flex; flex-direction: column; gap: 0.5rem; border-top: none; padding-top: 0;">
                <?php if (!$is_service && $stock !== null): ?>
                    <div style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.25rem;">
                        <?php if ($stock > 0): ?>
                            <span style="color: #10B981;">✓ In Stock</span>
                        <?php else: ?>
                            <span style="color: #EF4444;">✕ Out of Stock</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <span class="ct-view-product-btn" style="width: 100%; text-align: center; pointer-events: none;">
                    VIEW DETAILS
                </span>
            </div>
        </div>
    </div>
    <?php
}
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">

        <!-- Main Services Grid -->
        <div class="flex justify-between items-end mb-4 mt-4">
            <div>
                <h1 class="ct-page-title" style="margin-bottom: 0;">Order a Service</h1>
            </div>
        </div>
        
        <?php if (empty($core_services)): ?>
            <div class="ct-empty" style="padding:2rem;text-align:center;color:#6b7280;">
                <p>No services are available at the moment.</p>
            </div>
        <?php else: ?>
        <div class="ct-product-grid mb-12">
            <?php foreach ($core_services as $srv): ?>
                <?php render_service_card($srv['name'], $srv['category'], $srv['img'], $srv['link'], true, null, null, $srv['modal_text'] ?? printflow_default_customer_service_modal_text()); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Service Detail Modal -->
<div id="service-modal" style="display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 9999999; padding: 1.5rem; transition: opacity 0.2s ease;">
    <!-- Backdrop (Soft dark tint to highlight modal) -->
    <div onclick="closeServiceModal()" style="position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.45);"></div>
    
    <!-- Modal Content (Wider fixed size with internal scroll) -->
    <div id="service-modal-content" style="position: relative; background: rgba(10, 37, 48, 0.96); border: 1px solid rgba(83, 197, 224, 0.28); border-radius: 1.25rem; width: 620px; max-width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); transform: translateY(20px); transition: all 0.3s ease;">
        
        <style>
            /* Modal Internal Scrollbar */
            #service-modal-scroll-body::-webkit-scrollbar { width: 6px; }
            #service-modal-scroll-body::-webkit-scrollbar-track { background: transparent; }
            #service-modal-scroll-body::-webkit-scrollbar-thumb { background: rgba(83, 197, 224, 0.7); border-radius: 10px; }
            #service-modal-scroll-body::-webkit-scrollbar-thumb:hover { background: rgba(83, 197, 224, 0.9); }
            .modal-action-row {
                display: flex;
                align-items: stretch;
                gap: 1rem;
            }
            .modal-qty-block {
                display: flex;
                align-items: center;
                border: 1px solid rgba(83, 197, 224, 0.32);
                border-radius: 0.75rem;
                height: 48px;
                flex-shrink: 0;
                background: rgba(12, 43, 56, 0.92);
            }
            .modal-qty-btn {
                width: 44px;
                height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: transparent;
                border: none;
                cursor: pointer;
                font-size: 1.2rem;
                color: #e8f4f8;
                font-weight: 700;
                transition: all 0.2s;
            }
            .modal-action-buttons {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
                flex: 1;
            }
            .modal-action-btn {
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-weight: 700;
                border-radius: 0.75rem;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .modal-action-btn:hover {
                transform: translateY(-1px);
                filter: brightness(1.05);
            }
            @media (max-width: 640px) {
                .modal-action-row {
                    flex-direction: column;
                    align-items: stretch;
                }
                .modal-qty-block {
                    justify-content: center;
                    width: 100%;
                }
                .modal-action-buttons {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        
        <!-- Close Button -->
        <button onclick="closeServiceModal()" style="position: absolute; top: 1rem; right: 1rem; z-index: 100; padding: 0.5rem; background: rgba(10, 37, 48, 0.95); border: 1px solid rgba(83, 197, 224, 0.32); border-radius: 9999px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center;">
            <svg style="width: 1.5rem; height: 1.5rem; color: #d9edf5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <!-- Scrollable Body Section (content only - no buttons) -->
        <div id="service-modal-scroll-body" style="overflow-y: auto; flex: 1; display: flex; flex-direction: column; min-height: 0;">
            <!-- Image Section (Fixed Aspect Ratio) -->
            <div style="width: 100%; height: 280px; position: relative; background: #f3f4f6; flex-shrink: 0;">
                <img id="modal-img" src="" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <div style="position: absolute; top: 1.25rem; left: 1.25rem; z-index: 10;">
                    <span id="modal-category" style="padding: 0.35rem 0.85rem; background: #ffffff; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border-radius: 0.5rem; color: #4F46E5; box-shadow: 0 4px 6px rgba(0,0,0,0.1); letter-spacing: 0.05em;">Category</span>
                </div>
            </div>

            <!-- Info Section -->
            <div style="padding: 1.5rem 2rem; display: flex; flex-direction: column; background: rgba(10, 37, 48, 0.92); border-top: 1px solid rgba(83, 197, 224, 0.18);">
                <h2 id="modal-name" style="font-size: 1.5rem; font-weight: 800; color: #eaf6fb; margin: 0 0 0.75rem 0; line-height: 1.2;">Service Name</h2>
                
                <div id="modal-price-container" style="margin-bottom: 1rem; display: none;">
                    <p id="modal-price" style="font-size: 1.25rem; font-weight: 800; color: #eaf6fb; margin: 0;"></p>
                    <div id="modal-stock" style="margin-top: 0.5rem; font-size: 0.85rem; font-weight: 600;"></div>
                </div>

                <p id="modal-intro-text" style="color: #b9d4df; margin: 0; line-height: 1.6; font-size: 0.9rem;"></p>
            </div>
        </div>

        <!-- Fixed Footer - Always Visible (Buy Now & Add to Cart) -->
        <div id="modal-cart-section" style="display: none; flex-shrink: 0; padding: 1.25rem 2rem; background: rgba(8, 30, 39, 0.95); border-top: 1px solid rgba(83, 197, 224, 0.24); box-shadow: 0 -4px 10px rgba(0,0,0,0.18);">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #9fc6d9; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.05em;">Quantity</label>
            <div class="modal-action-row">
                <div class="modal-qty-block">
                    <button type="button" onclick="decreaseModalQuantity()" class="modal-qty-btn" onmouseover="this.style.background='rgba(83, 197, 224, 0.2)'" onmouseout="this.style.background='transparent'">−</button>
                    <span id="modal-quantity-display" style="width: 50px; height: 44px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; border-left: 1px solid rgba(83, 197, 224, 0.28); border-right: 1px solid rgba(83, 197, 224, 0.28); color: #eaf6fb;">1</span>
                    <button type="button" onclick="increaseModalQuantity()" class="modal-qty-btn" onmouseover="this.style.background='rgba(83, 197, 224, 0.2)'" onmouseout="this.style.background='transparent'">+</button>
                </div>
                <div class="modal-action-buttons">
                    <button type="button" onclick="addServiceToCart()" class="modal-action-btn" style="background: rgba(255,255,255,0.06); color: #d7ebf4; border: 1px solid rgba(83, 197, 224, 0.3); box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                        <svg style="width: 1.2rem; height: 1.2rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                        Add to Cart
                    </button>
                    <button type="button" onclick="buyNowService()" class="modal-action-btn" style="background: linear-gradient(135deg, #53C5E0, #32a1c4); color: #ffffff; box-shadow: 0 8px 18px rgba(50, 161, 196, 0.35);">
                        <svg style="width: 1.2rem; height: 1.2rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-4m14 6v-3.87a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 17.25 8.75h-7.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 4.125 2.75h-1.5A3.375 3.375 0 0 0 -0.75 6.125v7.5A3.375 3.375 0 0 0 2.625 17h15.75A3.375 3.375 0 0 0 21.75 13.625Z"></path></svg>
                        Buy Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// CSRF Token
const SERVICE_MODAL_CSRF = '<?php echo $csrf_token; ?>';
const DEFAULT_SERVICE_MODAL_TEXT = <?php echo json_encode(printflow_default_customer_service_modal_text(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let modalQuantity = 1;
let currentModalData = {};

function openServiceModal(name, category, img, link, is_service, price, stock, modalIntro) {
    document.getElementById('modal-name').textContent = name || '';
    document.getElementById('modal-category').textContent = category || '';
    document.getElementById('modal-img').src = img || '';
    const introEl = document.getElementById('modal-intro-text');
    if (introEl) {
        const t = (modalIntro !== undefined && modalIntro !== null && String(modalIntro).trim() !== '') ? String(modalIntro) : DEFAULT_SERVICE_MODAL_TEXT;
        introEl.textContent = t;
    }
    
    // Store current modal data for cart operations
    currentModalData = {
        name: name,
        category: category,
        img: img,
        link: link,
        is_service: is_service,
        price: price,
        stock: stock
    };
    
    // Reset quantity
    modalQuantity = 1;
    
    const priceContainer = document.getElementById('modal-price-container');
    const cartSection = document.getElementById('modal-cart-section');
    
    // Show quantity and cart for all items (services and products)
    cartSection.style.display = 'block';
    document.getElementById('modal-quantity-display').textContent = '1';
    
    // Show price and stock only for products
    if (is_service === false && price !== '') {
        priceContainer.style.display = 'block';
        document.getElementById('modal-price').textContent = price;
        
        const stockEl = document.getElementById('modal-stock');
        if (stock !== '' && parseInt(stock) > 0) {
            stockEl.innerHTML = '<span style="color: #10B981;">✓ In Stock (' + stock + ' available)</span>';
        } else {
            stockEl.innerHTML = '<span style="color: #EF4444;">✕ Out of Stock</span>';
        }
    } else {
        priceContainer.style.display = 'none';
    }
    
    const modal = document.getElementById('service-modal');
    const content = document.getElementById('service-modal-content');
    
    // Show modal container
    modal.style.display = 'flex';
    // Trigger reflow to animate
    void modal.offsetWidth;
    
    // Explicit inline animations
    modal.style.opacity = '1';
    modal.style.pointerEvents = 'auto';
    content.style.transform = 'translateY(0)';
    
    document.body.style.overflow = 'hidden';
}

function closeServiceModal() {
    const modal = document.getElementById('service-modal');
    const content = document.getElementById('service-modal-content');
    
    modal.style.opacity = '0';
    modal.style.pointerEvents = 'none';
    content.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 300);
}

// Quantity Control Functions
function increaseModalQuantity() {
    modalQuantity = Math.min(modalQuantity + 1, 999);
    document.getElementById('modal-quantity-display').textContent = modalQuantity;
}

function decreaseModalQuantity() {
    modalQuantity = Math.max(modalQuantity - 1, 1);
    document.getElementById('modal-quantity-display').textContent = modalQuantity;
}

// Buy Now Function
function buyNowService() {
    if (!currentModalData.link) {
        alert('Unable to proceed. Service information missing.');
        return;
    }
    
    const link = currentModalData.link;
    const is_service = currentModalData.is_service;
    
    // For services and products, redirect to checkout/customization with quantity
    const separator = link.includes('?') ? '&' : '?';
    window.location.href = link + separator + 'qty=' + modalQuantity;
}

// Add to Cart Function
async function addServiceToCart() {
    if (!currentModalData.link) {
        alert('Unable to proceed. Service information missing.');
        return;
    }
    
    const link = currentModalData.link;
    const is_service = currentModalData.is_service;
    
    // Get the button and show loading state
    const cartBtn = event.target.closest('button');
    if (!cartBtn) return;
    
    const originalText = cartBtn.innerHTML;
    cartBtn.disabled = true;
    cartBtn.innerHTML = '<svg style="width: 1.2rem; height: 1.2rem; animation: spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><path d="M12 5v2m0 10v2M7 12H5m12 0h2M8.22 8.22l1.41 1.41m5.74 5.74l1.41 1.41M8.22 15.78l1.41-1.41m5.74-5.74l1.41-1.41"></path></svg> Adding...';
    
    try {
        // For services (custom orders), add to cart with quantity
        if (is_service === true) {
            // Services don't have product_id, so we store order details separately
            // Redirect to customization page with quantity
            const separator = link.includes('?') ? '&' : '?';
            
            // Show success
            cartBtn.innerHTML = '✓ Added to Cart!';
            cartBtn.style.background = '#10B981';
            
            setTimeout(() => {
                closeServiceModal();
                window.location.href = link + separator + 'qty=' + modalQuantity;
            }, 1000);
        } else {
            // This is a fixed product - add via API
            const urlParams = new URLSearchParams(new URL(currentModalData.link, window.location.origin).search);
            const productId = parseInt(urlParams.get('product_id') || '0');
            
            if (productId <= 0) {
                alert('Unable to add to cart. Product ID not found.');
                cartBtn.disabled = false;
                cartBtn.innerHTML = originalText;
                return;
            }
            
            const response = await fetch('/printflow/customer/api_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: modalQuantity,
                    csrf_token: SERVICE_MODAL_CSRF
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                cartBtn.innerHTML = '✓ Added to Cart!';
                cartBtn.style.background = '#10B981';
                
                // Update cart badge if function exists
                if (window.updateCartBadge) {
                    updateCartBadge(data.cart_count || 0);
                }
                
                // Close modal after 1.5 seconds
                setTimeout(() => {
                    closeServiceModal();
                    // Reset button state
                    cartBtn.disabled = false;
                    cartBtn.innerHTML = originalText;
                    cartBtn.style.background = '#111827';
                }, 1500);
            } else {
                alert(data.message || 'Failed to add to cart');
                cartBtn.disabled = false;
                cartBtn.innerHTML = originalText;
            }
        }
    } catch (err) {
        console.error('Error adding to cart:', err);
        alert('An error occurred. Please try again.');
        cartBtn.disabled = false;
        cartBtn.innerHTML = originalText;
    }
}

// Add CSS animation for loading spinner
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Cart Badge Update Function
function updateCartBadge(count) {
    const badge = document.getElementById('cart-count-badge');
    if (!badge) return;
    
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
        // Add a little pop animation
        badge.animate([
            { transform: 'scale(1)' },
            { transform: 'scale(1.3)' },
            { transform: 'scale(1)' }
        ], { duration: 300 });
    } else {
        badge.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
