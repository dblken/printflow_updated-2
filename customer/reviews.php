<?php
/**
 * Customer Reviews Page
 * Display service reviews, images, videos, and staff replies.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// require_role('Customer'); // Publicly accessible
ensure_ratings_table_exists();

$service_name = sanitize($_GET['service'] ?? '');
$order_id = (int)($_GET['order_id'] ?? 0);

$sql = "
    SELECT r.id, r.order_id, r.rating, r.message, r.video_path, r.created_at, r.service_type,
           c.first_name, c.last_name
    FROM reviews r
    INNER JOIN customers c ON c.customer_id = r.customer_id
    WHERE 1=1
";
$params = [];
$types = '';

if (!empty($service_name)) {
    // Basic prefix matching for service name
    $sql .= " AND (r.service_type LIKE ? OR r.service_type = ?)";
    $like = '%' . $service_name . '%';
    $params[] = $like;
    $params[] = $service_name;
    $types .= 'ss';
}

if ($order_id > 0) {
    $sql .= " AND r.order_id = ?";
    $params[] = $order_id;
    $types .= 'i';
}

$sql .= " ORDER BY r.created_at DESC LIMIT 50";

$reviews_list = db_query($sql, $types ?: null, $params ?: null) ?: [];

$ravg = 0; $rcount = 0;
if (!empty($reviews_list)) {
    $ravg = array_sum(array_column($reviews_list, 'rating')) / count($reviews_list);
    $rcount = count($reviews_list);
}

// Fetch images and replies for all shown reviews
$reviews_final = [];
foreach ($reviews_list as $r) {
    $rid = (int)$r['id'];
    $images = db_query("SELECT image_path FROM review_images WHERE review_id = ?", 'i', [$rid]) ?: [];
    $replies = db_query("
        SELECT rr.reply_message, rr.created_at, u.first_name as staff_fname, u.last_name as staff_lname
        FROM review_replies rr
        INNER JOIN users u ON u.user_id = rr.staff_id
        WHERE rr.review_id = ?
        ORDER BY rr.created_at ASC
    ", 'i', [$rid]) ?: [];
    
    $r['images'] = $images;
    $r['replies'] = $replies;
    $reviews_final[] = $r;
}

$page_title = 'Customer Reviews - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.rv-wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.25rem; min-height: 80vh; }
.rv-header { margin-bottom: 2.5rem; }
.rv-title { font-size: 2.2rem; font-weight: 850; color: #fff; line-height: 1.1; margin-bottom: 0.75rem; }
.rv-subtitle { font-size: 1.05rem; font-weight: 500; color: #a4d8eb; max-width: 600px; line-height: 1.6; }

.rv-card { background: rgba(10, 37, 48, 0.9); border: 1px solid rgba(83, 197, 224, 0.22); border-radius: 1.5rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 15px 35px rgba(0,0,0,0.3); transition: transform 0.2s, border-color 0.2s; }
.rv-card:hover { border-color: rgba(83, 197, 224, 0.4); }

.rv-meta { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; }
.rv-user { display: flex; align-items: center; gap: 14px; }
.rv-avatar { width: 44px; height: 44px; background: linear-gradient(135deg, #53c5e0, #32a1c4); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1.1rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.rv-name { font-weight: 700; font-size: 1.1rem; color: #fff; margin-bottom: 2px; }
.rv-date { font-size: 0.85rem; color: #6e98aa; font-weight: 500; }
.rv-service { display: inline-block; padding: 3px 10px; background: rgba(83, 197, 224, 0.12); color: #53c5e0; font-size: 0.75rem; border-radius: 999px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; border: 1px solid rgba(83, 197, 224, 0.15); }

.rv-stars { color: #f59e0b; font-size: 1.25rem; letter-spacing: 2px; margin-bottom: 1rem; }
.rv-message { font-size: 1.05rem; color: #eaf6fb; line-height: 1.7; white-space: pre-wrap; margin-bottom: 1.5rem; overflow-wrap: break-word; word-break: break-word; }

.rv-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-bottom: 1.5rem; }
.rv-media-item { aspect-ratio: 1; border-radius: 12px; overflow: hidden; border: 1px solid rgba(83, 197, 224, 0.2); cursor: pointer; position: relative; transition: all 0.2s; }
.rv-media-item:hover { border-color: #53c5e0; transform: scale(1.02); box-shadow: 0 6px 15px rgba(0,0,0,0.3); }
.rv-media-item img { width: 100%; height: 100%; object-fit: cover; }
.rv-video-tag { position: absolute; top: 8px; left: 8px; background: rgba(0,0,0,0.6); color: white; padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; }

.rv-video-player { width: 100%; border-radius: 1rem; overflow: hidden; border: 1px solid rgba(83, 197, 224, 0.3); background: #000; margin-bottom: 1.5rem; }

.rv-reply { margin-top: 1rem; padding: 1.25rem 1.5rem; background: rgba(8, 30, 39, 0.7); border-radius: 1.25rem; border-left: 4px solid #53c5e0; position: relative; }
.rv-reply::before { content: ''; position: absolute; left: 24px; top: -10px; width: 20px; height: 10px; background: rgba(8, 30, 39, 0.7); clip-path: polygon(50% 0%, 0% 100%, 100% 100%); }
.rv-reply-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.rv-reply-staff { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 0.9rem; color: #53c5e0; text-transform: uppercase; letter-spacing: 0.5px; }
.rv-reply-staff img { width: 20px; height: 20px; border-radius: 50%; border: 1px solid rgba(83, 197, 224, 0.3); }
.rv-reply-date { font-size: 0.8rem; color: #648899; }
.rv-reply-msg { color: #d1e9f1; line-height: 1.6; font-size: 0.95rem; overflow-wrap: break-word; word-break: break-word; }

.rv-empty { text-align: center; padding: 5rem 2rem; color: #6e98aa; background: rgba(10, 37, 48, 0.4); border-radius: 1.5rem; border: 2px dashed rgba(83,197,224,0.15); }
.rv-empty svg { margin-bottom: 1.5rem; opacity: 0.4; }

/* Lightbox Modal */
.rv-modal { position: fixed; inset: 0; background: rgba(0, 7, 10, 0.92); z-index: 1000000; display: none; align-items: center; justify-content: center; padding: 10px; backdrop-filter: blur(8px); }
.rv-modal.open { display: flex; }
.rv-modal img { max-width: 95vw; max-height: 85vh; border-radius: 12px; box-shadow: 0 30px 60px rgba(0,0,0,0.6); transform: scale(0.9); transition: transform 0.3s ease; }
.rv-modal.open img { transform: scale(1); }
.rv-modal-close { position: absolute; top: 20px; right: 20px; color: #fff; background: rgba(0,0,0,0.6); border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; font-size: 24px; display: flex; align-items: center; justify-content: center; }
</style>

<div class="rv-wrap">
    <div class="rv-header">
        <h1 class="rv-title">Customer Reviews</h1>
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 0.75rem;">
            <div class="rv-stars" style="margin-bottom: 0;"><?php echo str_repeat('★', floor($ravg)) . str_repeat('☆', 5 - floor($ravg)); ?></div>
            <span style="font-weight: 800; color: #53c5e0; font-size: 1.25rem;"><?php echo number_format($ravg, 1); ?></span>
            <span style="color: #6e98aa; font-weight: 500;">&bull; <?php echo $rcount; ?> review<?php echo $rcount != 1 ? 's' : ''; ?></span>
        </div>
        <p class="rv-subtitle">
            <?php if ($order_id > 0): ?>
                Showing review for Order #<?php echo str_pad((string)$order_id, 5, '0', STR_PAD_LEFT); ?>.
            <?php elseif (!empty($service_name)): ?>
                Genuine feedback for <strong><?php echo htmlspecialchars($service_name); ?></strong> from our verified customers.
            <?php else: ?>
                Check out what our customers have to say about our printing services.
            <?php endif; ?>
        </p>
    </div>

    <?php if (empty($reviews_final)): ?>
        <div class="rv-empty">
            <svg width="60" height="60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5h2M11 19h2M7 9h2M7 15h2M15 9h2M15 15h2m-6-10v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h4a2 2 0 012 2zm10 0v14a2 2 0 01-2 2h-4a2 2 0 01-2-2V5a2 2 0 012-2h4a2 2 0 012 2z"></path></svg>
            <p style="font-size: 1.25rem; font-weight: 700; margin-bottom: 8px;">No reviews found</p>
            <p>We haven't received any reviews yet for this filter.</p>
            <a href="/printflow/customer/services.php" style="display:inline-block; margin-top:2rem; color:#53c5e0; font-weight:700; text-decoration:none">← Shop our services</a>
        </div>
    <?php else: ?>
        <?php foreach ($reviews_final as $review): 
            $initials = strtoupper(substr($review['first_name'], 0, 1) . substr($review['last_name'], 0, 1));
            // Anonymize last name for privacy if requested, but I'll use full name + initials avatar
            $display_name = htmlspecialchars($review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.');
        ?>
        <div class="rv-card">
            <div class="rv-meta">
                <div class="rv-user">
                    <div class="rv-avatar"><?php echo $initials; ?></div>
                    <div>
                        <div class="rv-name"><?php echo $display_name; ?></div>
                        <div class="rv-date"><?php echo format_datetime($review['created_at']); ?> &bull; <span class="rv-service"><?php echo htmlspecialchars($review['service_type']); ?></span></div>
                    </div>
                </div>
                <div class="rv-stars"><?php echo str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']); ?></div>
            </div>

            <p class="rv-message"><?php echo nl2br(htmlspecialchars($review['message'])); ?></p>
            
            <div class="rv-media-grid">
                <?php if (!empty($review['video_path'])): 
                    // Ensure path logic doesn't double-up on base_url (fix for /printflow/printflow/... 404s)
                    $vpath = $review['video_path'];
                    if ($vpath && strpos($vpath, '/') !== 0 && strpos($vpath, 'http') !== 0) {
                        $vpath = $base_url . '/' . $vpath;
                    }
                ?>
                    <div class="rv-media-item" onclick="openLightboxVideo('<?php echo htmlspecialchars($vpath); ?>')">
                        <video style="width:100%; height:100%; object-fit:cover;" muted playsinline>
                            <source src="<?php echo htmlspecialchars($vpath); ?>#t=0.5" type="video/mp4">
                        </video>
                        <div class="rv-video-tag">Video</div>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.2);">
                            <svg width="32" height="32" fill="white" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"></path></svg>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($review['images'])): ?>
                    <?php foreach ($review['images'] as $img): 
                        $ipath = $img['image_path'];
                        if ($ipath && strpos($ipath, '/') !== 0 && strpos($ipath, 'http') !== 0) {
                            $ipath = $base_url . '/' . $ipath;
                        }
                    ?>
                        <div class="rv-media-item" onclick="openLightbox('<?php echo htmlspecialchars($ipath); ?>')">
                            <img src="<?php echo htmlspecialchars($ipath); ?>" alt="Review photo">
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php foreach ($review['replies'] as $reply): ?>
                <div class="rv-reply">
                    <div class="rv-reply-header">
                        <div class="rv-reply-staff">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="color: #ffcc00;"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                            Staff Response
                        </div>
                        <div class="rv-reply-date"><?php echo format_ago($reply['created_at']); ?></div>
                    </div>
                    <p class="rv-reply-msg"><?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="lightbox" class="rv-modal" onclick="closeLightbox(event)">
    <button class="rv-modal-close">×</button>
    <div style="max-width: 95vw; max-height: 85vh; position: relative;">
        <img id="lightboxImg" src="" style="display:none; max-width: 100%; max-height: 85vh;">
        <video id="lightboxVid" controls playsinline preload="auto" style="display:none; max-width: 100%; max-height: 85vh; border-radius: 8px; box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
            Your browser does not support the video tag.
        </video>
    </div>
</div>

<script>
function openLightbox(src) {
    const lb = document.getElementById('lightbox');
    const img = document.getElementById('lightboxImg');
    const vid = document.getElementById('lightboxVid');
    img.src = src;
    img.style.display = 'block';
    vid.style.display = 'none';
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function openLightboxVideo(src) {
    const lb = document.getElementById('lightbox');
    const img = document.getElementById('lightboxImg');
    const vid = document.getElementById('lightboxVid');
    vid.src = src;
    vid.load();
    vid.style.display = 'block';
    img.style.display = 'none';
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
    
    // Attempt to auto-play
    vid.play().catch(e => console.warn('Autoplay prevented:', e));
}
function closeLightbox(e) {
    const ev = e || window.event;
    if (ev && ev.target.tagName === 'VIDEO') return;
    
    const lb = document.getElementById('lightbox');
    const img = document.getElementById('lightboxImg');
    const vid = document.getElementById('lightboxVid');
    
    lb.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(() => { 
        img.src = ''; 
        vid.pause();
        vid.src = ''; 
        vid.load(); // fully clear the video buffer
    }, 300);
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeLightbox(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
