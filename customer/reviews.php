<?php
/**
 * Customer Reviews Page
 * Display service reviews, images, videos, and staff replies.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// require_role('Customer'); // Publicly accessible
ensure_ratings_table_exists();

$service_id = (int)($_GET['service_id'] ?? 0);
$service_name_get = sanitize($_GET['service'] ?? ''); // Fallback for old links
$order_id = (int)($_GET['order_id'] ?? 0);

$display_service = '';
if ($service_id > 0) {
    $serv_res = db_query("SELECT name FROM services WHERE service_id = ?", 'i', [$service_id]);
    if (!empty($serv_res)) $display_service = $serv_res[0]['name'];
} elseif (!empty($service_name_get)) {
    $display_service = $service_name_get;
}

$sql = "
    SELECT r.id, r.order_id, r.rating, r.comment as message, r.video_path, r.created_at, r.service_type,
           COALESCE(c.first_name, u.first_name) as first_name,
           COALESCE(c.last_name, u.last_name) as last_name
    FROM reviews r
    LEFT JOIN customers c ON c.customer_id = r.user_id
    LEFT JOIN users u ON u.user_id = r.user_id
    WHERE 1=1
";
$params = [];
$types = '';

if ($service_id > 0) {
    $sql .= " AND r.reference_id = ? AND r.review_type = 'custom'";
    $params[] = $service_id;
    $types .= 'i';
} elseif (!empty($display_service)) {
    // Basic prefix matching for service name (legacy)
    $sql .= " AND (r.service_type LIKE ? OR r.service_type = ?)";
    $like = '%' . $display_service . '%';
    $params[] = $like;
    $params[] = $display_service;
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

.rv-feed { background: rgba(10, 37, 48, 0.95); border: 1px solid rgba(83, 197, 224, 0.15); border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; backdrop-filter: blur(10px); }
.rv-item { padding: 2.5rem; border-bottom: 1px solid rgba(83, 197, 224, 0.08); transition: background 0.2s; }
.rv-item:last-child { border-bottom: none; }
.rv-item:hover { background: rgba(83, 197, 224, 0.02); }

.rv-meta { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.rv-user { display: flex; align-items: center; gap: 16px; }
.rv-avatar { width: 48px; height: 48px; background: linear-gradient(135deg, #53c5e0, #32a1c4); border-radius: 1rem; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1.2rem; box-shadow: 0 8px 16px rgba(50, 161, 196, 0.2); }
.rv-name { font-weight: 800; font-size: 1.15rem; color: #fff; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
.rv-date { font-size: 0.85rem; color: #6e98aa; font-weight: 600; letter-spacing: 0.2px; }
.rv-service { display: inline-block; padding: 2px 10px; background: rgba(83, 197, 224, 0.08); color: #53c5e0; font-size: 10px; border-radius: 6px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; border: 1px solid rgba(83, 197, 224, 0.1); vertical-align: middle; }

.rv-stars { color: #fbbf24; font-size: 1.1rem; letter-spacing: 2px; }
.rv-message { font-size: 1.1rem; color: #eaf6fb; line-height: 1.8; white-space: pre-wrap; margin-bottom: 1.75rem; font-weight: 400; }

.rv-media-grid { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 1.75rem; }
.rv-media-item { position: relative; width: 120px; height: 120px; border-radius: 1rem; overflow: hidden; border: 1px solid rgba(83, 197, 224, 0.15); cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.rv-media-item:hover { border-color: #53c5e0; transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.4); }

.rv-reply { margin-top: 1.5rem; padding: 1.5rem; background: rgba(8, 30, 39, 0.6); border-radius: 1.25rem; border-left: 3px solid #53c5e0; }
.rv-reply-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.rv-reply-staff { display: flex; align-items: center; gap: 8px; font-weight: 900; font-size: 0.8rem; color: #53c5e0; text-transform: uppercase; letter-spacing: 1px; }
.rv-reply-date { font-size: 0.8rem; color: #648899; font-weight: 600; }
.rv-reply-msg { color: #cbd5e1; line-height: 1.7; font-size: 1rem; font-weight: 400; }

.rv-empty { text-align: center; padding: 8rem 2rem; color: #6e98aa; background: rgba(10, 37, 48, 0.4); border-radius: 2rem; border: 2px dashed rgba(83,197,224,0.1); }
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
        <div class="flex items-center gap-2 mb-4">
            <div class="rv-stars"><?php echo str_repeat('★', round($ravg)) . str_repeat('☆', 5 - round($ravg)); ?></div>
            <span style="font-size: 1.4rem; font-weight: 850; color: #fbbf24;"><?php echo number_format($ravg, 1); ?></span>
            <span style="color: #6e98aa; font-weight: 700; margin-left:8px; font-size:1.1rem;">&bull; <?php echo $rcount; ?> review<?php echo $rcount != 1 ? 's' : ''; ?></span>
        </div>
        <p class="rv-subtitle">Genuine feedback <?php echo !empty($display_service) ? 'for ' . htmlspecialchars($display_service) : ''; ?> from our verified customers.</p>
    </div>

    <?php if (empty($reviews_final)): ?>
        <div class="rv-empty">
            <svg width="60" height="60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5h2M11 19h2M7 9h2M7 15h2M15 9h2M15 15h2m-6-10v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h4a2 2 0 012 2zm10 0v14a2 2 0 01-2 2h-4a2 2 0 01-2-2V5a2 2 0 012-2h4a2 2 0 012 2z"></path></svg>
            <p style="font-size: 1.5rem; font-weight: 800; margin-bottom: 12px; color: #fff;">No reviews found</p>
            <p>We haven't received any feedback yet for this selection.</p>
            <a href="/printflow/customer/services.php" style="display:inline-block; margin-top:2.5rem; color:#53c5e0; font-weight:800; text-decoration:none">← Explore our services</a>
        </div>
    <?php else: ?>
        <div class="rv-feed">
            <?php foreach ($reviews_final as $review): 
                $initials = strtoupper(substr($review['first_name'], 0, 1) . substr($review['last_name'], 0, 1));
                $display_name = htmlspecialchars($review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.');
            ?>
            <div class="rv-item">
                <div class="rv-meta">
                    <div class="rv-user">
                        <div class="rv-avatar"><?php echo $initials; ?></div>
                        <div>
                            <div class="rv-name"><?php echo $display_name; ?> <span class="rv-service"><?php echo htmlspecialchars($review['service_type']); ?></span></div>
                            <div class="rv-date"><?php echo format_datetime($review['created_at']); ?></div>
                        </div>
                    </div>
                    <div class="rv-stars"><?php echo str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']); ?></div>
                </div>

                <p class="rv-message"><?php echo nl2br(htmlspecialchars($review['message'])); ?></p>
                
                <div class="rv-media-grid">
                    <?php if (!empty($review['video_path'])): 
                        $vpath = $review['video_path'];
                        if ($vpath && strpos($vpath, '/') !== 0 && strpos($vpath, 'http') !== 0) $vpath = $base_url . '/' . $vpath;
                    ?>
                        <div class="rv-media-item" onclick="openLightboxVideo('<?php echo htmlspecialchars($vpath); ?>')">
                            <video style="width:100%; height:100%; object-fit:cover;" muted playsinline>
                                <source src="<?php echo htmlspecialchars($vpath); ?>#t=0.1" type="video/mp4">
                            </video>
                            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.3); pointer-events:none;">
                                <svg width="24" height="24" fill="white" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"></path></svg>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($review['images'])): ?>
                        <?php foreach ($review['images'] as $img): 
                            $ipath = $img['image_path'];
                            if ($ipath && strpos($ipath, '/') !== 0 && strpos($ipath, 'http') !== 0) $ipath = $base_url . '/' . $ipath;
                        ?>
                            <div class="rv-media-item" onclick="openLightbox('<?php echo htmlspecialchars($ipath); ?>')">
                                <img src="<?php echo htmlspecialchars($ipath); ?>" alt="Review photo" style="width:100%; height:100%; object-fit:cover;">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php foreach ($review['replies'] as $reply): ?>
                    <div class="rv-reply">
                        <div class="rv-reply-header">
                            <div class="rv-reply-staff">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20" style="color: #fbbf24;"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                                Staff Response
                            </div>
                            <div class="rv-reply-date"><?php echo format_ago($reply['created_at']); ?></div>
                        </div>
                        <p class="rv-reply-msg"><?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
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
    
    // Clear previous state
    vid.pause();
    vid.currentTime = 0;
    
    vid.src = src;
    vid.style.display = 'block';
    img.style.display = 'none';
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
    
    // Ensure it loads and plays
    vid.load();
    vid.play().catch(e => {
        console.warn('Autoplay prevented, showing controls:', e);
        vid.muted = true; // Try muted autoplay as fallback
        vid.play().catch(err => console.error('Even muted autoplay failed', err));
    });
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
