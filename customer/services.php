<?php
/**
 * Customer Services
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer_service_catalog.php';

// Fetch services from DB
$visible_rows = db_query(
    "SELECT s.*, 
    (SELECT COUNT(*) FROM job_orders jo WHERE (jo.service_type LIKE CONCAT('%', s.name, '%') OR jo.service_type = s.name) AND jo.status != 'CANCELLED') as sold_count,
    (SELECT AVG(rating) FROM reviews r WHERE r.reference_id = s.service_id AND r.review_type = 'custom') as avg_rating,
    (SELECT COUNT(*) FROM reviews r WHERE r.reference_id = s.service_id AND r.review_type = 'custom') as review_count
    FROM services s WHERE s.status = 'Activated' ORDER BY name ASC",
    '',
    []
) ?: [];

$core_services = [];
foreach ($visible_rows as $row) {
    $img = trim((string) ($row['hero_image'] ?? ''));
    if ($img === '') {
        $img = '/printflow/public/assets/images/services/default.png';
    }
    if ($img !== '' && $img[0] !== '/') {
        $img = '/' . ltrim($img, '/');
    }
    
    $core_services[] = [
        'id' => $row['service_id'],
        'name' => $row['name'],
        'category' => $row['category'] ?? '',
        'img' => $img,
        'link' => $row['customer_link'] ?: 'order_create.php',
        'modal_text' => $row['customer_modal_text'] ?: printflow_default_customer_service_modal_text(),
        'sold_count' => (int)$row['sold_count'],
        'avg_rating' => (float)$row['avg_rating'],
        'review_count' => (int)$row['review_count']
    ];
}

$csrf_token = generate_csrf_token();
$page_title = 'Services - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

// Reusable card template function
function render_service_card($srv) {
    $img = $srv['img'];
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $img) && strpos($img, 'http') === false) {
        $img = '/printflow/public/assets/images/services/default.png';
    }
    
    $json_name = htmlspecialchars(json_encode($srv['name']), ENT_QUOTES, 'UTF-8');
    $json_category = htmlspecialchars(json_encode($srv['category']), ENT_QUOTES, 'UTF-8');
    $json_img = htmlspecialchars(json_encode($img), ENT_QUOTES, 'UTF-8');
    $json_link = htmlspecialchars(json_encode($srv['link']), ENT_QUOTES, 'UTF-8');
    $json_modal_text = htmlspecialchars(json_encode($srv['modal_text']), ENT_QUOTES, 'UTF-8');
    
    $ravg = $srv['avg_rating'];
    $rcount = $srv['review_count'];
    $sold = $srv['sold_count'];
    // If sold is 0 but there are reviews, use review_count as minimum sold
    $display_sold = ($sold <= 0 && $rcount > 0) ? $rcount : $sold;
    ?>
    <div class="ct-product-card cursor-pointer group" onclick="openServiceModal(<?php echo $srv['id']; ?>, <?php echo $json_name; ?>, <?php echo $json_category; ?>, <?php echo $json_img; ?>, <?php echo $json_link; ?>, true, '', '', <?php echo $json_modal_text; ?>, <?php echo $ravg; ?>, <?php echo $rcount; ?>)">
        <div class="ct-product-img overflow-hidden">
            <div class="ct-product-img-inner transition-transform duration-500 group-hover:scale-110">
                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($srv['name']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:0.5rem;">
            </div>
        </div>
        <div class="ct-product-body" style="text-align: left; padding: 1.25rem 1rem;">
            <span class="ct-product-category" style="margin-bottom: 0.5rem; display: inline-block;"><?php echo htmlspecialchars($srv['category']); ?></span>
            <h3 class="ct-product-name" style="margin-bottom: 0.4rem; font-weight: 700; font-size: 1.1rem; line-height: 1.3;">
                <?php echo htmlspecialchars($srv['name']); ?>
            </h3>
            
            <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 1rem;">
                <!-- Stars -->
                <div style="display: flex; gap: 1px;">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <svg width="14" height="14" fill="<?php echo ($i <= round($ravg)) ? '#FBBF24' : '#374151'; ?>" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    <?php endfor; ?>
                </div>
                
                <?php if ($rcount > 0): ?>
                    <a href="reviews.php?service_id=<?php echo $srv['id']; ?>" onclick="event.stopPropagation();" style="font-size: 0.75rem; color: #9ca3af; margin-left: 2px; text-decoration: none;" class="hover:underline hover:text-[#53C5E0]">(<?php echo $rcount; ?>) Reviews</a>
                <?php endif; ?>
                
                <span style="font-size: 0.75rem; color: #64748b; margin-left: auto;"><?php echo $display_sold; ?> sold</span>
            </div>

            <span class="ct-view-product-btn" style="flex: 1; text-align: center; pointer-events: none;">
                ORDER NOW
            </span>
        </div>
    </div>
    <?php
}
?>

<style>
    body.customer-theme {
        background: #ffffff !important;
        background-image: none !important;
    }
    .ct-page-title { color: #1e293b !important; text-shadow: none !important; }
    .ct-product-card {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1) !important;
    }
    .ct-product-card:hover {
        border-color: #53C5E0 !important;
        box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1) !important;
    }
    .ct-product-name { color: #1e293b !important; }
    .ct-product-category { background: #f1f5f9 !important; color: #475569 !important; border-color: #e2e8f0 !important; }
    
    #service-modal-content {
        background: #ffffff !important;
        border-color: #e2e8f0 !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
    }
    #modal-name { color: #1e293b !important; }
    #modal-intro-text { color: #475569 !important; }
    #modal-cart-section {
        background: #f8fafc !important;
        border-top-color: #e2e8f0 !important;
    }
    .ct-card-footer {
        display: flex;
        gap: 8px;
        padding: 0;
        margin-top: 0.5rem;
    }
    .ct-cart-icon-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 0.5rem;
        border: 1px solid #0a2530;
        background: rgba(10, 37, 48, 0.08);
        color: #0a2530;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .ct-cart-icon-btn:hover {
        background: #53C5E0;
        border-color: #53C5E0;
        color: #ffffff;
    }
    .ct-view-product-btn { 
        background: #0a2530 !important; 
        color: #ffffff !important; 
        border: none !important; 
        box-shadow: 0 4px 10px rgba(10, 37, 48, 0.2) !important;
        transition: all 0.3s ease !important;
        border-radius: 0.5rem;
        padding: 0.6rem 1rem;
        font-weight: 600;
        font-size: 0.8125rem;
        flex: 1;
    }
    .ct-product-card:hover .ct-view-product-btn {
        background: #53C5E0 !important;
        box-shadow: 0 8px 18px rgba(83, 197, 224, 0.3) !important;
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">

        <div class="mb-8 mt-4"></div>
        
        <?php if (empty($core_services)): ?>
            <div class="ct-empty" style="padding:4rem;text-align:center;color:#6b7280; background: rgba(15, 23, 42, 0.5); border-radius: 1rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🏢</div>
                <p>No services are available at the moment.</p>
            </div>
        <?php else: ?>
        <div class="ct-product-grid mb-12">
            <?php foreach ($core_services as $srv): ?>
                <?php render_service_card($srv); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Service Detail Modal -->
<div id="service-modal" style="display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 9999999; padding: 1.5rem; transition: opacity 0.2s ease;">
    <!-- Backdrop -->
    <div onclick="closeServiceModal()" style="position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.45);"></div>
    
    <div id="service-modal-content" style="position: relative; background: rgba(10, 37, 48, 0.96); border: 1px solid rgba(83, 197, 224, 0.28); border-radius: 1.25rem; width: 620px; max-width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); transform: translateY(20px); transition: all 0.3s ease;">
        
        <style>
            #service-modal-scroll-body::-webkit-scrollbar { width: 6px; }
            #service-modal-scroll-body::-webkit-scrollbar-track { background: transparent; }
            #service-modal-scroll-body::-webkit-scrollbar-thumb { background: rgba(83, 197, 224, 0.7); border-radius: 10px; }
            .modal-action-row { display: flex; align-items: stretch; gap: 1rem; }
            .modal-qty-block { display: flex; align-items: center; border: 1px solid rgba(83, 197, 224, 0.32); border-radius: 0.75rem; height: 48px; flex-shrink: 0; background: rgba(12, 43, 56, 0.92); }
            .modal-qty-btn { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; cursor: pointer; color: #e8f4f8; font-weight: 700; transition: all 0.2s; }
            .modal-action-buttons { display: grid; grid-template-columns: 1fr; gap: 0.75rem; flex: 1; }
            .modal-action-btn { height: 48px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; border-radius: 0.75rem; border: none; cursor: pointer; transition: all 0.3s; font-size: 0.9rem; text-transform: uppercase; background: #0a2530; color: #ffffff; box-shadow: 0 8px 18px rgba(10, 37, 48, 0.25); }
            .modal-action-btn:hover { background: #53C5E0; box-shadow: 0 8px 22px rgba(83, 197, 224, 0.35); transform: translateY(-1px); }
        </style>
        
        <button onclick="closeServiceModal()" style="position: absolute; top: 1rem; right: 1rem; z-index: 100; padding: 0.5rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 9999px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center;">
            <svg style="width: 1.5rem; height: 1.5rem; color: #1e293b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div id="service-modal-scroll-body" style="overflow-y: auto; flex: 1; display: flex; flex-direction: column;">
            <div style="width: 100%; height: 280px; position: relative; flex-shrink: 0;">
                <img id="modal-img" src="" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <div style="position: absolute; top: 1.25rem; left: 1.25rem;">
                    <span id="modal-category" style="padding: 0.35rem 0.85rem; background: #ffffff; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border-radius: 0.5rem; color: #4F46E5; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">CATEGORY</span>
                </div>
            </div>

            <div style="padding: 1.5rem 2rem; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <h2 id="modal-name" style="font-size: 1.5rem; font-weight: 800; color: #eaf6fb; margin: 0;">Service Name</h2>
                    <div id="modal-rating-pill" style="display: flex; align-items: center; gap: 6px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); padding: 4px 10px; border-radius: 999px;">
                        <span style="color: #f59e0b; font-size: 14px;">★</span>
                        <span id="modal-rating-val" style="color: #f59e0b; font-size: 0.85rem; font-weight: 800;">0.0</span>
                    </div>
                </div>
                <p id="modal-intro-text" style="color: #b9d4df; margin: 0 0 1.25rem; line-height: 1.6; font-size: 0.9rem;"></p>
                <a id="modal-reviews-link" href="#" style="font-size: 0.82rem; color: #53c5e0; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                    Read Customer Reviews &rarr;
                </a>
            </div>
        </div>

        <div id="modal-cart-section" style="padding: 1.25rem 2rem; background: rgba(8, 30, 39, 0.95); border-top: 1px solid rgba(83, 197, 224, 0.24);">
            <div class="modal-action-row">
                <div class="modal-action-buttons">
                    <button type="button" onclick="buyNowService()" class="modal-action-btn">
                        Proceed to Customization
                        <svg style="width: 1.2rem; height: 1.2rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentModalData = {};

function openServiceModal(id, name, category, img, link, is_service, price, stock, modalIntro, avgRating, reviewCount) {
    document.getElementById('modal-name').textContent = name;
    document.getElementById('modal-category').textContent = category;
    document.getElementById('modal-img').src = img;
    document.getElementById('modal-intro-text').textContent = modalIntro;
    
    const ratingVal = parseFloat(avgRating || 0).toFixed(1);
    document.getElementById('modal-rating-val').textContent = ratingVal;
    document.getElementById('modal-reviews-link').href = 'reviews.php?service_id=' + id;
    
    currentModalData = { id, name, link };
    
    const modal = document.getElementById('service-modal');
    const content = document.getElementById('service-modal-content');
    
    modal.style.display = 'flex';
    void modal.offsetWidth;
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

function buyNowService() {
    if (currentModalData.link) window.location.href = currentModalData.link;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
