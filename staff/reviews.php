<?php
/**
 * Staff Reviews Page
 * Enhanced with inline reply system and multi-media support.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Staff', 'Admin']);
require_once __DIR__ . '/../includes/staff_pending_check.php';
ensure_ratings_table_exists();

// Filters
$search = sanitize($_GET['search'] ?? '');
$service_type = sanitize($_GET['service_type'] ?? '');
$rating = (int)($_GET['rating'] ?? 0);

// Pagination
$items_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$sql_base = "
    FROM reviews r
    INNER JOIN orders o ON o.order_id = r.order_id
    INNER JOIN customers c ON c.customer_id = r.customer_id
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
if (!empty($service_type)) {
    $sql_base .= " AND r.service_type = ?";
    $params[] = $service_type;
    $types .= 's';
}
if ($rating > 0 && $rating <= 5) {
    $sql_base .= " AND r.rating = ?";
    $params[] = $rating;
    $types .= 'i';
}

$count_sql = "SELECT COUNT(*) as total" . $sql_base;
$total_result = db_query($count_sql, $types ?: null, $params ?: null);
$total_items = (int)($total_result[0]['total'] ?? 0);
$total_pages = ceil($total_items / $items_per_page);

$query_sql = "
    SELECT
        r.id,
        r.order_id,
        r.service_type,
        r.rating,
        r.message,
        r.video_path,
        r.created_at,
        c.first_name,
        c.last_name
    " . $sql_base . "
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";

$fetch_params = array_merge($params, [$items_per_page, $offset]);
$fetch_types = $types . 'ii';
$reviews_raw = db_query($query_sql, $fetch_types ?: null, $fetch_params ?: null) ?: [];

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

$service_rows = db_query("SELECT DISTINCT service_type FROM reviews WHERE service_type IS NOT NULL AND service_type != '' ORDER BY service_type ASC") ?: [];
$service_options = array_map(static fn($r) => (string)$r['service_type'], $service_rows);

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
        .rv-toolbar { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; background: #fff; border-radius: 14px 14px 0 0; }
        .rv-group { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .rv-label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 800; }
        .rv-input, .rv-select { border: 1px solid #cbd5e1; border-radius: 10px; padding: 9px 12px; font-size: 13px; min-height: 40px; }
        .rv-btn { border: none; border-radius: 10px; padding: 10px 16px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; height: 40px; }
        .rv-btn.primary { background: #0a2530; color: #fff; }
        .rv-btn.primary:hover { background: #153e4f; }
        .rv-btn.light { background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; text-decoration: none; display: inline-flex; align-items: center; }
        .rv-btn.light:hover { background: #f1f5f9; }

        .review-item { padding: 24px; border-bottom: 1px solid #f1f5f9; }
        .review-item:last-child { border-bottom: none; }
        .review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .review-user { font-weight: 800; font-size: 15px; color: #0a2530; margin-bottom: 4px; }
        .review-meta { display: flex; gap: 10px; align-items: center; font-size: 12px; color: #64748b; }
        .review-chip { padding: 2px 8px; background: #f1f5f9; border-radius: 6px; font-weight: 700; text-transform: uppercase; font-size: 10px; color: #475569; }
        .review-stars { color: #f59e0b; font-size: 16px; letter-spacing: 2px; }
        .review-msg { font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 16px; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word; }

        .review-media { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .media-thumb { width: 70px; height: 70px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; transition: transform 0.2s; }
        .media-thumb:hover { transform: scale(1.05); }
        .video-thumb { background: #000; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 10px; font-weight: 800; position: relative; }
        .video-thumb::after { content: '▶'; position: absolute; font-size: 14px; }

        .replies-container { background: #f8fafc; border-radius: 12px; padding: 16px; margin-top: 16px; border: 1px solid #f1f5f9; }
        .reply-item { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #e2e8f0; }
        .reply-item:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .reply-header { display: flex; justify-content: space-between; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .reply-staff { color: #0a2530; display: flex; align-items: center; gap: 4px; }
        .reply-msg { font-size: 13px; color: #334155; line-height: 1.5; overflow-wrap: break-word; word-break: break-word; }

        .reply-form { margin-top: 16px; border-top: 1px solid #f1f5f9; padding-top: 16px; }
        .reply-input-wrap { display: flex; gap: 10px; }
        .reply-textarea { flex: 1; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 14px; font-size: 13px; min-height: 48px; resize: none; transition: all 0.2s; }
        .reply-textarea:focus { border-color: #0a2530; box-shadow: 0 0 0 3px rgba(10, 37, 48, 0.05); outline: none; }
        .reply-submit { background: #0a2530; color: #fff; border: none; border-radius: 10px; padding: 0 16px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s; }
        .reply-submit:hover { background: #153e4f; }
        .reply-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .rv-pager { padding: 14px 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 0 0 14px 14px; margin-top: -1px; }
        .rv-empty { text-align: center; color: #94a3b8; font-size: 15px; padding: 60px 20px; background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; }

        /* Media Modal */
        .rv-modal { position: fixed; inset: 0; background: rgba(2, 6, 23, 0.75); z-index: 200000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        .rv-modal.open { display: flex; }
        .rv-modal img, .rv-modal video { max-width: 90vw; max-height: 80vh; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 2px solid #fff; }
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
        <header>
            <h1 class="page-title">Review Management</h1>
        </header>
        <main>
            <form method="GET" class="rv-toolbar" style="margin-bottom: 20px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                <div class="rv-group" style="min-width: 240px;">
                    <label class="rv-label" for="search">Customer or Order #</label>
                    <input id="search" name="search" class="rv-input" type="text" value="<?php echo htmlspecialchars($search); ?>" placeholder="E.g. John Doe, ORD-00123" oninput="handleSearchInput()">
                </div>
                <div class="rv-group">
                    <label class="rv-label" for="service_type">Service</label>
                    <select id="service_type" name="service_type" class="rv-select" onchange="this.form.submit()">
                        <option value="">All Services</option>
                        <?php foreach ($service_options as $service): ?>
                            <option value="<?php echo htmlspecialchars($service); ?>" <?php echo $service_type === $service ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rv-group" style="min-width: 130px;">
                    <label class="rv-label" for="rating">Star Rating</label>
                    <select id="rating" name="rating" class="rv-select" onchange="this.form.submit()">
                        <option value="0">All Stars</option>
                        <?php for ($r = 5; $r >= 1; $r--): ?>
                            <option value="<?php echo $r; ?>" <?php echo $rating === $r ? 'selected' : ''; ?>><?php echo $r; ?> Stars</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <a href="/printflow/staff/reviews.php" class="rv-btn light" style="margin-bottom: 1px;">Reset</a>
            </form>

            <div id="reviewsList">
                <?php if (empty($reviews)): ?>
                    <div class="rv-empty">No reviews found matching your filters.</div>
                <?php else: ?>
                    <div class="rv-card" style="padding:0;">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item" id="review-<?php echo $review['id']; ?>">
                            <div class="review-header">
                                <div>
                                    <div class="review-user"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></div>
                                    <div class="review-meta">
                                        <span class="review-chip"><?php echo htmlspecialchars($review['service_type'] ?: 'General Service'); ?></span>
                                        <span>&bull;</span>
                                        <a href="/printflow/staff/customizations.php?order_id=<?php echo $review['order_id']; ?>" style="font-weight:700; color:#0a2530; text-decoration:none">#ORD-<?php echo str_pad((string)$review['order_id'], 5, '0', STR_PAD_LEFT); ?></a>
                                        <span>&bull;</span>
                                        <span><?php echo format_datetime($review['created_at']); ?></span>
                                    </div>
                                </div>
                                <div class="review-stars"><?php echo stars_text($review['rating']); ?></div>
                            </div>
                            
                            <div class="review-msg"><?php echo nl2br(htmlspecialchars($review['message'])); ?></div>

                            <?php if (!empty($review['images']) || !empty($review['video_path'])): ?>
                                <div class="review-media">
                                    <?php if (!empty($review['video_path'])): 
                                        $vpath = $review['video_path'];
                                        if ($vpath && strpos($vpath, '/') !== 0 && strpos($vpath, 'http') !== 0) {
                                            $vpath = '/printflow/' . $vpath;
                                        }
                                    ?>
                                        <div class="media-thumb video-thumb" onclick="openMediaModal('<?php echo htmlspecialchars($vpath, ENT_QUOTES); ?>', 'video')"></div>
                                    <?php endif; ?>
                                    <?php foreach ($review['images'] as $img): 
                                        $ipath = $img['image_path'];
                                        if ($ipath && strpos($ipath, '/') !== 0 && strpos($ipath, 'http') !== 0) {
                                            $ipath = '/printflow/' . $ipath;
                                        }
                                    ?>
                                        <img src="<?php echo htmlspecialchars($ipath); ?>" class="media-thumb" alt="Review Image" onclick="openMediaModal('<?php echo htmlspecialchars($ipath, ENT_QUOTES); ?>', 'img')">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="replies-area">
                                <div class="replies-container" <?php echo empty($review['replies']) ? 'style="display:none"' : ''; ?>>
                                    <?php foreach ($review['replies'] as $reply): ?>
                                        <div class="reply-item">
                                            <div class="reply-header">
                                                <div class="reply-staff">
                                                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                                                    <?php echo htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']); ?> (Staff)
                                                </div>
                                                <div><?php echo format_ago($reply['created_at']); ?></div>
                                            </div>
                                            <div class="reply-msg"><?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="reply-form">
                                    <div style="margin-bottom: 10px;">
                                        <select class="rv-select" style="width: 100%; font-size: 12px; height: 36px; padding: 0 10px;" onchange="applyQuickReply(this, <?php echo $review['id']; ?>)">
                                            <option value="">⚡ Quick Reply Suggestions...</option>
                                            <optgroup label="Positive Feedback">
                                                <option value="Thank you for your feedback! We're glad you had a great experience.">Great experience</option>
                                                <option value="Thank you for your support! We hope to serve you again soon.">Hope to serve again</option>
                                                <option value="Thank you for your feedback! If you have any concerns, feel free to contact us.">Contact us if needed</option>
                                            </optgroup>
                                            <optgroup label="Issue/Negative Feedback">
                                                <option value="We sincerely apologize for your experience. We will review this issue and improve our service.">We'll review/improve</option>
                                                <option value="We’re sorry for the issue you experienced. Please contact us so we can assist you better.">Sorry, contact us</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div class="reply-input-wrap">
                                        <textarea class="reply-textarea" placeholder="Type a professional reply..." id="reply-text-<?php echo $review['id']; ?>"></textarea>
                                        <button class="reply-submit" id="submit-btn-<?php echo $review['id']; ?>" onclick="submitReply(<?php echo $review['id']; ?>)">Post Reply</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <div class="rv-pager">
                        <?php echo get_pagination_links($current_page, $total_pages, ['search'=>$search, 'service_type'=>$service_type, 'rating'=>$rating]); ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div id="mediaModal" class="rv-modal" onclick="closeMediaModal()">
    <div id="mediaContent"></div>
</div>

<script>
let searchTimeout;
function handleSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const input = document.getElementById('search');
        if (input && input.form) input.form.submit();
    }, 700);
}

function openMediaModal(src, type) {
    const modal = document.getElementById('mediaModal');
    const content = document.getElementById('mediaContent');
    if (type === 'video') {
        content.innerHTML = `<video src="${src}" controls autoplay style="width:100%; height:auto"></video>`;
    } else {
        content.innerHTML = `<img src="${src}" alt="Full preview">`;
    }
    modal.classList.add('open');
}

function closeMediaModal() {
    const modal = document.getElementById('mediaModal');
    modal.classList.remove('open');
    setTimeout(() => { document.getElementById('mediaContent').innerHTML = ''; }, 300);
}

function applyQuickReply(select, reviewId) {
    if (!select.value) return;
    const textarea = document.getElementById(`reply-text-${reviewId}`);
    textarea.value = select.value;
    textarea.focus();
}

async function submitReply(reviewId) {
    const textarea = document.getElementById(`reply-text-${reviewId}`);
    const btn = document.getElementById(`submit-btn-${reviewId}`);
    const msg = textarea.value.trim();
    
    if (!msg) return;
    
    btn.disabled = true;
    btn.textContent = '...';
    
    try {
        const response = await fetch('/printflow/staff/api_review_reply.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                review_id: reviewId,
                message: msg,
                csrf_token: '<?php echo $csrf_token; ?>'
            })
        });
        
        const data = await response.json();
        if (data.success) {
            textarea.value = '';
            // Add reply to UI dynamically
            const container = document.querySelector(`#review-${reviewId} .replies-container`);
            container.style.display = 'block';
            
            const replyHtml = `
                <div class="reply-item" style="animation: fadeIn 0.4s ease forwards">
                    <div class="reply-header">
                        <div class="reply-staff">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                            You (Just now)
                        </div>
                    </div>
                    <div class="reply-msg">${msg.replace(/\n/g, '<br>')}</div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', replyHtml);
            showStaffAlert('Success', 'Your reply has been posted successfully.');
        } else {
            showStaffAlert('Request Failed', data.error || 'Failed to post reply.');
        }
    } catch (err) {
        console.error(err);
        showStaffAlert('Error', 'An unexpected error occurred. Please check your connection.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Post Reply';
    }
}

function showStaffAlert(title, message) {
    const modal = document.getElementById('staffAlertModal');
    document.getElementById('staffAlertTitle').textContent = title;
    document.getElementById('staffAlertMessage').textContent = message;
    modal.classList.add('open');
}

function closeStaffAlert() {
    document.getElementById('staffAlertModal').classList.remove('open');
}

document.addEventListener('keydown', (e) => { 
    if (e.key === 'Escape') {
        closeMediaModal();
        closeStaffAlert();
    }
});
</script>

<!-- Custom Alert Modal -->
<div id="staffAlertModal" class="rv-modal" style="z-index: 300000;" onclick="if(event.target === this) closeStaffAlert()">
    <div style="background: #fff; border-radius: 20px; width: 400px; max-width: 95vw; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: modalIn 0.3s ease;">
        <div style="padding: 24px 24px 16px; text-align: center;">
            <div id="staffAlertTitle" style="font-size: 18px; font-weight: 800; color: #0a2530; margin-bottom: 8px;">Alert</div>
            <div id="staffAlertMessage" style="font-size: 14px; color: #64748b; line-height: 1.6;">Message goes here.</div>
        </div>
        <div style="padding: 16px 24px 24px; display: flex; justify-content: center;">
            <button onclick="closeStaffAlert()" style="background: #0a2530; color: #fff; border: none; border-radius: 12px; padding: 12px 40px; font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.2s;">OK</button>
        </div>
    </div>
</div>

<style>
@keyframes modalIn { from { opacity: 0; transform: scale(0.9) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

</body>
</html>
