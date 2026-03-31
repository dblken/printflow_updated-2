<?php
/**
 * Staff Reviews Page
 * Enhanced with Filtering by Type and improved data mapping.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Staff', 'Admin']);
require_once __DIR__ . '/../includes/staff_pending_check.php';

// Filters
$search = sanitize($_GET['search'] ?? '');
$review_type = sanitize($_GET['review_type'] ?? '');
$rating = (int)($_GET['rating'] ?? 0);
$service = sanitize($_GET['service'] ?? '');

// Get distinct services for the filter from reviews, services, and products table
$available_services = db_query("
    SELECT DISTINCT name FROM (
        SELECT service_type COLLATE utf8mb4_general_ci as name FROM reviews WHERE service_type != '' AND service_type IS NOT NULL
        UNION
        SELECT name COLLATE utf8mb4_general_ci FROM services
        UNION
        SELECT name COLLATE utf8mb4_general_ci FROM products
    ) as combined_services WHERE name IS NOT NULL AND name != '' ORDER BY name ASC
") ?: [];

// Pagination
$items_per_page = 15;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$sql_base = "
    FROM reviews r
    INNER JOIN customers c ON c.customer_id = r.user_id
    WHERE 1=1
";
$params = [];
$types = '';

if (!empty($search)) {
    $sql_base .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR r.order_id = ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = (int)str_replace(['#', 'ORD-'], '', $search);
    $types .= 'ssi';
}
if (!empty($review_type)) {
    $sql_base .= " AND r.review_type = ?";
    $params[] = $review_type;
    $types .= 's';
}
if ($rating > 0 && $rating <= 5) {
    $sql_base .= " AND r.rating = ?";
    $params[] = $rating;
    $types .= 'i';
}
if (!empty($service)) {
    // Robust filtering by name (legacy) or by ID mapping (modern)
    $sql_base .= " AND (
        r.service_type = ? 
        OR r.service_type LIKE ? 
        OR (r.review_type = 'custom' AND r.reference_id IN (SELECT service_id FROM services WHERE name = ? OR name LIKE ?))
        OR (r.review_type = 'product' AND r.reference_id IN (SELECT product_id FROM products WHERE name = ? OR name LIKE ?))
    )";
    $like = $service . '%';
    $params[] = $service;
    $params[] = $like;
    $params[] = $service;
    $params[] = $like;
    $params[] = $service;
    $params[] = $like;
    $types .= 'ssssss';
}

$count_sql = "SELECT COUNT(*) as total" . $sql_base;
$total_result = db_query($count_sql, $types ?: null, $params ?: null);
$total_items = (int)($total_result[0]['total'] ?? 0);
$total_pages = ceil($total_items / $items_per_page);

$query_sql = "
    SELECT
        r.id,
        r.order_id,
        r.reference_id,
        r.review_type,
        r.service_type as legacy_service_type,
        r.rating,
        r.comment,
        r.video_path,
        r.created_at,
        c.first_name,
        c.last_name,
        (CASE 
            WHEN r.review_type = 'product' THEN (SELECT name FROM products WHERE product_id = r.reference_id)
            WHEN r.review_type = 'custom' THEN (SELECT name FROM services WHERE service_id = r.reference_id)
            ELSE r.service_type
        END) as item_name
    " . $sql_base . "
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";

$fetch_params = array_merge($params, [$items_per_page, $offset]);
$fetch_types = $types . 'ii';
$reviews = db_query($query_sql, $fetch_types ?: null, $fetch_params ?: null) ?: [];

// Map services for reset/links
$page_query_params = ['search'=>$search, 'review_type'=>$review_type, 'rating'=>$rating, 'service'=>$service];

// Fetch images and replies for each review
$reviews = [];
foreach ($reviews_raw as $r) {
    $rid = (int)$r['id'];
    $images = db_query("SELECT image_path FROM review_images WHERE review_id = ?", 'i', [$rid]) ?: [];
    $replies = db_query("
        SELECT rr.id, rr.reply_message, rr.created_at, u.first_name, u.last_name
        FROM review_replies rr
        INNER JOIN users u ON u.user_id = rr.staff_id
        WHERE rr.review_id = ?
        ORDER BY rr.created_at ASC
    ", 'i', [$rid]) ?: [];
    
    $r['images'] = $images;
    $r['replies'] = $replies;
    $reviews[] = $r;
}

function stars_text($value) {
    $v = max(1, min(5, (int)$value));
    return str_repeat('★', $v) . str_repeat('☆', 5 - $v);
}

$csrf_token = generate_csrf_token();
$page_title = 'Review Management - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .rv-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .rv-toolbar { padding: 20px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; background: #fff; border-radius: 14px; margin-bottom: 24px; border: 1px solid #e5e7eb; }
        .rv-group { display: flex; flex-direction: column; gap: 6px; }
        .rv-label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 800; }
        .rv-input, .rv-select { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 13px; outline: none; transition: border-color 0.2s; }
        .rv-input:focus, .rv-select:focus { border-color: #0a2530; }
        .rv-btn { border: none; border-radius: 8px; padding: 10px 20px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; height: 41px; }
        .rv-btn.primary { background: #0a2530; color: #fff; }
        .rv-btn.light { background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; text-decoration: none; }
        
        .review-item { padding: 24px; border-bottom: 1px solid #f1f5f9; }
        .review-item:last-child { border-bottom: none; }
        .review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .review-user { font-weight: 800; font-size: 16px; color: #0a2530; margin-bottom: 4px; }
        .review-meta { display: flex; gap: 10px; align-items: center; font-size: 12px; color: #64748b; }
        .type-badge { padding: 3px 10px; border-radius: 999px; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.02em; }
        .type-product { background: rgba(59, 130, 246, 0.1); color: #1d4ed8; }
        .type-custom { background: rgba(16, 185, 129, 0.1); color: #047857; }
        
        .review-stars { color: #f59e0b; font-size: 16px; margin-bottom: 12px; }
        .review-msg { font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 16px; overflow-wrap: anywhere; word-break: break-word; }
        
        .review-media { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .media-thumb { width: 80px; height: 80px; border-radius: 10px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; transition: transform 0.2s; }
        .media-thumb:hover { transform: scale(1.04); }
        .video-thumb { background: #000; position: relative; display: flex; align-items: center; justify-content: center; }
        .video-thumb::after { content: '▶'; color: #fff; font-size: 14px; text-shadow: 0 0 10px rgba(0,0,0,0.5); }
        
        .replies-container { background: #f8fafc; border-radius: 12px; padding: 16px; margin-top: 20px; }
        .reply-item { margin-bottom: 16px; }
        .reply-item:last-child { margin-bottom: 0; }
        .reply-header { display: flex; justify-content: space-between; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
        .reply-msg { font-size: 13px; color: #475569; background: #fff; padding: 12px; border-radius: 8px; border: 1px solid #edf2f7; overflow-wrap: anywhere; word-break: break-word; }
        
        .reply-form { margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        .reply-input-wrap { display: flex; gap: 12px; margin-top: 12px; }
        .reply-textarea { flex: 1; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px; font-size: 13px; min-height: 50px; resize: none; }
        .reply-submit { background: #0a2530; color: #fff; border: none; border-radius: 10px; padding: 0 20px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .reply-submit:hover { background: #1a3a4a; }
        
        .rv-pager { padding: 20px; text-align: center; }
        .rv-empty { text-align: center; padding: 80px 20px; background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; color: #64748b; }
        
        .rv-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 40px; }
        .rv-modal.open { display: flex; }
        .rv-modal-content { max-width: 90%; max-height: 90%; position: relative; }
        .rv-close { position: absolute; top: -30px; right: -30px; color: #fff; font-size: 30px; cursor: pointer; }
        .main-content { padding-top: 10px !important; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php if (($_SESSION['user_type'] ?? '') === 'Admin'): ?>
        <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>
    <?php endif; ?>

    <div class="main-content">

        <main>
            <form method="GET" class="rv-toolbar">
                <div class="rv-group" style="flex: 1; min-width: 250px;">
                    <label class="rv-label">Search Customer or Order</label>
                    <input name="search" class="rv-input" type="text" value="<?php echo htmlspecialchars($search); ?>" placeholder="E.g. John Doe, 2261" onchange="this.form.submit()">
                </div>
                <div class="rv-group">
                    <label class="rv-label">Service</label>
                    <select name="service" class="rv-select" onchange="this.form.submit()">
                        <option value="">All Services</option>
                        <?php foreach($available_services as $as): ?>
                            <option value="<?php echo htmlspecialchars($as['name']); ?>" <?php echo $service === $as['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($as['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rv-group">
                    <label class="rv-label">Type</label>
                    <select name="review_type" class="rv-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="product" <?php echo $review_type === 'product' ? 'selected' : ''; ?>>Fixed Products</option>
                        <option value="custom" <?php echo $review_type === 'custom' ? 'selected' : ''; ?>>Custom Services</option>
                    </select>
                </div>
                <div class="rv-group">
                    <label class="rv-label">Rating</label>
                    <select name="rating" class="rv-select" onchange="this.form.submit()">
                        <option value="0">All Ratings</option>
                        <?php for($i=5; $i>=1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $rating === $i ? 'selected' : ''; ?>><?php echo $i; ?> Stars</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Filter button removed for automatic onchange updates -->
                <a href="reviews.php" class="rv-btn light">Reset</a>
            </form>

            <div id="reviewsList">
                <?php if (empty($reviews)): ?>
                    <div class="rv-empty">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">💬</div>
                        <p>No reviews found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="rv-card">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item" id="review-<?php echo $review['id']; ?>">
                            <div class="review-header">
                                <div>
                                    <div class="review-user"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></div>
                                    <div class="review-meta">
                                        <span class="type-badge <?php echo $review['review_type'] === 'product' ? 'type-product' : 'type-custom'; ?>">
                                            <?php echo $review['review_type'] === 'product' ? 'Fixed Product' : 'Custom Service'; ?>
                                        </span>
                                        <span style="font-weight: 700; color: #0a2530;">
                                            <?php echo htmlspecialchars($review['item_name'] ?: ($review['legacy_service_type'] ?: 'Unknown Item')); ?>
                                        </span>
                                        <span>&bull;</span>
                                        <a href="customizations.php?order_id=<?php echo $review['order_id']; ?>" style="color: #0c4a6e; font-weight: 700; text-decoration: none;">Order #<?php echo $review['order_id']; ?></a>
                                        <span>&bull;</span>
                                        <span><?php echo date('M d, Y h:i A', strtotime($review['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="review-stars"><?php echo stars_text($review['rating']); ?></div>
                            </div>
                            
                            <div class="review-msg"><?php echo nl2br(htmlspecialchars($review['comment'] ?: '')); ?></div>

                            <?php if (!empty($review['images']) || !empty($review['video_path'])): ?>
                                <div class="review-media">
                                    <?php if ($review['video_path']): 
                                        $vpath = $review['video_path'];
                                        if (strpos($vpath, 'http') === false && $vpath[0] !== '/') $vpath = '/printflow/'.$vpath;
                                    ?>
                                        <div class="media-thumb video-thumb" onclick="openMediaModal('<?php echo htmlspecialchars($vpath); ?>', 'video')"></div>
                                    <?php endif; ?>
                                    <?php foreach ($review['images'] as $img): 
                                        $ipath = $img['image_path'];
                                        if (strpos($ipath, 'http') === false && $ipath[0] !== '/') $ipath = '/printflow/'.$ipath;
                                    ?>
                                        <img src="<?php echo htmlspecialchars($ipath); ?>" class="media-thumb" onclick="openMediaModal('<?php echo htmlspecialchars($ipath); ?>', 'image')">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="replies-area">
                                <div class="replies-container" id="replies-<?php echo $review['id']; ?>" <?php echo empty($review['replies']) ? 'style="display:none"' : ''; ?>>
                                    <?php foreach ($review['replies'] as $reply): ?>
                                        <div class="reply-item">
                                            <div class="reply-header">
                                                <span>Staff Response &bull; <?php echo htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']); ?></span>
                                                <span><?php echo date('M d, Y', strtotime($reply['created_at'])); ?></span>
                                            </div>
                                            <div class="reply-msg"><?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="reply-form">
                                    <select class="rv-select" style="width: 100%; font-size: 12px;" onchange="applyQuickReply(this, <?php echo $review['id']; ?>)">
                                        <option value="">⚡ Quick Reply Suggestions...</option>
                                        <option value="Thank you! We're happy to hear you're satisfied with your order.">Positive Feedback</option>
                                        <option value="Thank you for your review! We look forward to serving you again.">Great experience</option>
                                        <option value="We apologize for the inconvenience. Please contact us so we can resolve this.">Negative Feedback</option>
                                    </select>
                                    <div class="reply-input-wrap">
                                        <textarea class="reply-textarea" placeholder="Type your response..." id="reply-text-<?php echo $review['id']; ?>"></textarea>
                                        <button class="reply-submit" onclick="submitReply(<?php echo $review['id']; ?>)">Reply</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <div class="rv-pager">
                        <?php echo get_pagination_links($current_page, $total_pages, $page_query_params); ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Media Modal -->
<div id="mediaModal" class="rv-modal" onclick="closeMediaModal()">
    <span class="rv-close">&times;</span>
    <div class="rv-modal-content" id="modalContent" onclick="event.stopPropagation()"></div>
</div>

<script>
function openMediaModal(src, type) {
    const modal = document.getElementById('mediaModal');
    const content = document.getElementById('modalContent');
    if (type === 'video') {
        content.innerHTML = `<video src="${src}" controls autoplay style="max-height: 80vh;"></video>`;
    } else {
        content.innerHTML = `<img src="${src}" style="max-height: 80vh;">`;
    }
    modal.classList.add('open');
}

function closeMediaModal() {
    document.getElementById('mediaModal').classList.remove('open');
    document.getElementById('modalContent').innerHTML = '';
}

function applyQuickReply(select, id) {
    if (select.value) {
        document.getElementById('reply-text-' + id).value = select.value;
        select.value = "";
    }
}

async function submitReply(reviewId) {
    const textarea = document.getElementById('reply-text-' + reviewId);
    const msg = textarea.value.trim();
    if (!msg) return;

    try {
        const response = await fetch('api_review_reply.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ review_id: reviewId, message: msg, csrf_token: '<?php echo $csrf_token; ?>' })
        });
        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to post reply');
        }
    } catch (e) {
        alert('An error occurred');
    }
}
</script>
</body>
</html>
