<?php
/**
 * Staff Reviews Page
 * Enhanced with Filtering by Type and improved data mapping.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Staff', 'Admin']);
require_once __DIR__ . '/../includes/staff_pending_check.php';

require_once __DIR__ . '/../includes/branch_context.php';
$branch_ctx = init_branch_context(false);
$branchName = $branch_ctx['branch_name'] ?? 'Main Branch';

// Filters
$search = sanitize($_GET['search'] ?? '');
$review_type = sanitize($_GET['review_type'] ?? '');
$rating = (int) ($_GET['rating'] ?? 0);
$service = sanitize($_GET['service'] ?? '');
$sort_by = sanitize($_GET['sort_by'] ?? 'newest');

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
$current_page = max(1, (int) ($_GET['page'] ?? 1));
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
    $params[] = (int) str_replace(['#', 'ORD-'], '', $search);
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
$total_items = (int) ($total_result[0]['total'] ?? 0);
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
        END) as item_name,
        (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id) as helpful_count
    " . $sql_base . "
    ";

$order_sql = " ORDER BY r.created_at DESC ";
if ($sort_by === 'oldest')
    $order_sql = " ORDER BY r.created_at ASC ";
if ($sort_by === 'rating_high')
    $order_sql = " ORDER BY r.rating DESC, r.created_at DESC ";
if ($sort_by === 'rating_low')
    $order_sql = " ORDER BY r.rating ASC, r.created_at DESC ";

$query_sql .= $order_sql . " LIMIT ? OFFSET ? ";

$fetch_params = array_merge($params, [$items_per_page, $offset]);
$fetch_types = $types . 'ii';
$reviews = db_query($query_sql, $fetch_types ?: null, $fetch_params ?: null) ?: [];

// Map services for reset/links
$page_query_params = ['search' => $search, 'review_type' => $review_type, 'rating' => $rating, 'service' => $service];

// Fetch images and replies for each review
$reviews_raw = $reviews;
$reviews = [];
foreach ($reviews_raw as $r) {
    $rid = (int) $r['id'];
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

function stars_text($value)
{
    $v = max(1, min(5, (int) $value));
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
        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
        }

        .status-badge-pill {
            font-size: 10px;
            padding: 4px 10px;
            font-weight: 700;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table-text-main {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
        }

        .table-text-sub {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }

        .filter-search-input {
            width: 100%;
            height: 38px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            padding: 0 12px 0 32px;
            color: #1f2937;
            box-sizing: border-box;
            transition: all 0.2s;
        }

        .filter-search-input:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            padding: 14px 18px;
            border-top: 1px solid #f3f4f6;
        }

        .filter-btn-reset {
            flex: 1;
            height: 40px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 400;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .filter-btn-reset:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        [x-cloak] {
            display: none !important;
        }

        .rv-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .rv-toolbar {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
            background: #fff;
            border-radius: 14px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
        }

        .rv-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .rv-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            font-weight: 800;
        }

        .rv-input,
        .rv-select {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }

        .rv-input:focus,
        .rv-select:focus {
            border-color: #0a2530;
        }

        .rv-btn {
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 41px;
        }

        .rv-btn.primary {
            background: #0a2530;
            color: #fff;
        }

        .rv-btn.light {
            background: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
            text-decoration: none;
        }

        .review-item {
            padding: 24px;
            border-bottom: 1px solid #f1f5f9;
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .review-user {
            font-weight: 800;
            font-size: 16px;
            color: #0a2530;
            margin-bottom: 4px;
        }

        .review-meta {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 12px;
            color: #64748b;
        }

        .type-badge {
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.02em;
        }

        .type-product {
            background: rgba(59, 130, 246, 0.1);
            color: #1d4ed8;
        }

        .type-custom {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
        }

        .review-stars {
            color: #f59e0b;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .review-msg {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 16px;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .review-media {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .media-thumb {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .media-thumb:hover {
            transform: scale(1.04);
        }

        .video-thumb {
            background: #0f172a;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #3b82f6 !important;
        }

        .video-thumb::after {
            content: '▶';
            color: #fff;
            font-size: 24px;
            filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.5));
        }

        .video-thumb::before {
            content: 'VIDEO';
            position: absolute;
            bottom: 4px;
            font-size: 8px;
            font-weight: 800;
            color: #3b82f6;
            letter-spacing: 0.1em;
        }

        .replies-container {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
        }

        .reply-item {
            margin-bottom: 16px;
        }

        .reply-item:last-child {
            margin-bottom: 0;
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .reply-msg {
            font-size: 13px;
            color: #475569;
            background: #fff;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #edf2f7;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .reply-form {
            margin-top: 20px;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }

        .reply-input-wrap {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }

        .reply-textarea {
            flex: 1;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px;
            font-size: 13px;
            min-height: 50px;
            resize: none;
        }

        .reply-submit {
            background: #0a2530;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0 20px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .reply-submit:hover {
            background: #1a3a4a;
        }

        .rv-pager {
            padding: 20px;
            text-align: center;
        }

        .rv-empty {
            text-align: center;
            padding: 80px 20px;
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            color: #64748b;
        }

        .rv-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .rv-modal.open {
            display: flex;
        }

        .rv-modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
        }

        .rv-close {
            position: absolute;
            top: -30px;
            right: -30px;
            color: #fff;
            font-size: 30px;
            cursor: pointer;
        }

        .main-content {
            padding-top: 10px !important;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php if (($_SESSION['user_type'] ?? '') === 'Admin'): ?>
            <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>
        <?php endif; ?>

        <div class="main-content" x-data="reviewManager()" x-init="init()">
            <header>
                <div>
                    <h1 class="page-title">Review Management</h1>
                    <p class="page-subtitle">Track and respond to customer feedback and service ratings</p>
                </div>
            </header>

            <main>
                <!-- KPI Summary Row -->
                <div class="kpi-row">
                    <div class="kpi-card indigo">
                        <span class="kpi-label">Total Reviews</span>
                        <span class="kpi-value"><?php echo number_format($total_items); ?></span>
                        <span class="kpi-sub">Lifetime customer feedback</span>
                    </div>
                    <div class="kpi-card amber">
                        <span class="kpi-label">Average Rating</span>
                        <span class="kpi-value">
                            <?php
                            $avg = db_query("SELECT AVG(rating) as avg FROM reviews");
                            echo number_format($avg[0]['avg'] ?? 0, 1);
                            ?>
                            <span style="font-size: 18px; color: #f59e0b;">★</span>
                        </span>
                        <span class="kpi-sub">System-wide performance quality</span>
                    </div>
                    <div class="kpi-card blue">
                        <span class="kpi-label">Service Focus</span>
                        <span class="kpi-value" style="font-size: 18px; line-height:36px;">
                            <?php
                            $top = db_query("SELECT service_type, COUNT(*) as c FROM reviews GROUP BY service_type ORDER BY c DESC LIMIT 1");
                            echo htmlspecialchars(ucfirst($top[0]['service_type'] ?? 'None'));
                            ?>
                        </span>
                        <span class="kpi-sub">Most frequently reviewed type</span>
                    </div>
                    <div class="kpi-card emerald">
                        <span class="kpi-label">Pending Replies</span>
                        <span class="kpi-value">
                            <?php
                            $pending = db_query("SELECT COUNT(*) as c FROM reviews r WHERE (SELECT COUNT(*) FROM review_replies rr WHERE rr.review_id = r.id) = 0");
                            echo $pending[0]['c'] ?? 0;
                            ?>
                        </span>
                        <span class="kpi-sub">Customer responses awaiting action</span>
                    </div>
                </div>

                <!-- Standardized Toolbar -->
                <div class="card overflow-visible" style="margin-bottom: 24px;">
                    <div class="toolbar-container">
                        <h3 style="font-size:16px; font-weight:700; color:#1f2937; margin:0;">Reviews Feed</h3>
                        <div class="toolbar-group" style="margin-left: auto;">


                            <!-- Sort Button -->
                            <div style="position:relative;">
                                <button class="toolbar-btn" :class="{ active: sortOpen || (activeSort !== 'newest') }"
                                    @click="sortOpen = !sortOpen; filterOpen = false">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                    </svg>
                                    Sort by
                                </button>
                                <div class="dropdown-panel sort-dropdown" x-show="sortOpen" x-cloak
                                    @click.outside="sortOpen = false">
                                    <template x-for="s in [
                                    {id:'newest', label:'Newest first'},
                                    {id:'oldest', label:'Oldest first'},
                                    {id:'rating_high', label:'Rating: High to Low'},
                                    {id:'rating_low', label:'Rating: Low to High'}
                                ]" :key="s.id">
                                        <div class="sort-option" :class="{ 'active': activeSort === s.id }"
                                            @click="applySort(s.id)">
                                            <span x-text="s.label"></span>
                                            <svg x-show="activeSort === s.id" class="check" width="14" height="14"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Filter Button -->
                            <div style="position:relative;">
                                <button class="toolbar-btn" :class="{ active: filterOpen || filterActiveCount > 0 }"
                                    @click="filterOpen = !filterOpen; sortOpen = false">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    Filters
                                    <template x-if="filterActiveCount > 0">
                                        <span class="filter-badge" x-text="filterActiveCount"></span>
                                    </template>
                                </button>

                                <!-- Filter Panel -->
                                <div class="dropdown-panel filter-panel" x-show="filterOpen" x-cloak
                                    @click.outside="filterOpen = false">
                                    <div class="filter-header">Refine Reviews</div>

                                    <div class="filter-section">
                                        <div class="filter-section-head">
                                            <span class="filter-label" style="margin:0;">Service</span>
                                            <button @click="service = ''; applyFilters()"
                                                class="filter-reset-link">Reset</button>
                                        </div>
                                        <select class="filter-select" x-model="service" @change="applyFilters()">
                                            <option value="">All Services</option>
                                            <?php foreach ($available_services as $as): ?>
                                                <option value="<?php echo htmlspecialchars($as['name']); ?>">
                                                    <?php echo htmlspecialchars($as['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="filter-section">
                                        <div class="filter-section-head">
                                            <span class="filter-label" style="margin:0;">Type</span>
                                            <button @click="reviewType = ''; applyFilters()"
                                                class="filter-reset-link">Reset</button>
                                        </div>
                                        <select class="filter-select" x-model="reviewType" @change="applyFilters()">
                                            <option value="">All Types</option>
                                            <option value="product">Fixed Products</option>
                                            <option value="custom">Custom Services</option>
                                        </select>
                                    </div>

                                    <div class="filter-section">
                                        <div class="filter-section-head">
                                            <span class="filter-label" style="margin:0;">Rating</span>
                                            <button @click="rating = 0; applyFilters()"
                                                class="filter-reset-link">Reset</button>
                                        </div>
                                        <select class="filter-select" x-model="rating" @change="applyFilters()">
                                            <option value="0">All Ratings</option>
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <option value="<?php echo $i; ?>"><?php echo $i; ?> Stars</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="filter-section">
                                        <div class="filter-section-head">
                                            <span class="filter-label" style="margin:0;">Keyword search</span>
                                            <button @click="search = ''; applyFilters()"
                                                class="filter-reset-link">Reset</button>
                                        </div>
                                        <input type="text" class="filter-input" placeholder="Search..." x-model="search"
                                            @change="applyFilters()">
                                    </div>

                                    <div class="filter-footer">
                                        <button class="filter-btn-reset" style="width:100%;"
                                            @click="resetFilters()">Reset all filters</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
                                            <div class="review-user">
                                                <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                            </div>
                                            <div class="review-meta">
                                                <span
                                                    class="type-badge <?php echo $review['review_type'] === 'product' ? 'type-product' : 'type-custom'; ?>">
                                                    <?php echo $review['review_type'] === 'product' ? 'Fixed Product' : 'Custom Service'; ?>
                                                </span>
                                                <span style="font-weight: 700; color: #0a2530;">
                                                    <?php echo htmlspecialchars($review['item_name'] ?: ($review['legacy_service_type'] ?: 'Unknown Item')); ?>
                                                </span>

                                                <span>&bull;</span>
                                                <span><?php echo date('M d, Y h:i A', strtotime($review['created_at'])); ?></span>
                                                <?php if (($review['helpful_count'] ?? 0) > 0): ?>
                                                    <span>&bull;</span>
                                                    <span
                                                        style="color: #059669; font-weight: 700; background: rgba(5, 150, 105, 0.1); padding: 2px 6px; border-radius: 4px; font-size: 11px;">
                                                        <svg style="width: 12px; height: 12px; margin-right: 2px; vertical-align: middle;"
                                                            viewBox="0 0 20 20" fill="currentColor">
                                                            <path
                                                                d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z" />
                                                        </svg>
                                                        <?php echo $review['helpful_count']; ?> Helpful
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="review-stars"><?php echo stars_text($review['rating']); ?></div>
                                    </div>

                                    <div class="review-msg"><?php echo nl2br(htmlspecialchars($review['comment'] ?: '')); ?>
                                    </div>

                                    <?php
                                    $has_imgs = !empty($review['images']);
                                    $has_vid = !empty($review['video_path']);
                                    if ($has_imgs || $has_vid): ?>
                                        <div class="review-media"
                                            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; max-width: 600px;">
                                            <?php if ($has_vid):
                                                $vpath = $review['video_path'];
                                                if (strpos($vpath, 'http') === false && (!isset($vpath[0]) || $vpath[0] !== '/'))
                                                    $vpath = '/printflow/' . $vpath;
                                                ?>
                                                <div class="media-thumb video-thumb" style="width: 100%; aspect-ratio: 1;"
                                                    onclick="openMediaModal('<?php echo htmlspecialchars($vpath); ?>', 'video')"></div>
                                            <?php endif; ?>

                                            <?php foreach ($review['images'] as $img):
                                                $ipath = $img['image_path'];
                                                if (strpos($ipath, 'http') === false && (!isset($ipath[0]) || $ipath[0] !== '/'))
                                                    $ipath = '/printflow/' . $ipath;
                                                ?>
                                                <img src="<?php echo htmlspecialchars($ipath); ?>" class="media-thumb"
                                                    style="width: 100%; aspect-ratio: 1;"
                                                    onclick="openMediaModal('<?php echo htmlspecialchars($ipath); ?>', 'image')">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="replies-area">
                                        <div class="replies-container" id="replies-<?php echo $review['id']; ?>" <?php echo empty($review['replies']) ? 'style="display:none"' : ''; ?>>
                                            <?php foreach ($review['replies'] as $reply): ?>
                                                <div class="reply-item">
                                                    <div class="reply-header">
                                                        <span>Staff Response &bull;
                                                            <?php echo htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']); ?></span>
                                                        <span><?php echo date('M d, Y', strtotime($reply['created_at'])); ?></span>
                                                    </div>
                                                    <div class="reply-msg">
                                                        <?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="reply-form">
                                            <select class="rv-select" style="width: 100%; font-size: 12px;"
                                                onchange="applyQuickReply(this, <?php echo $review['id']; ?>)">
                                                <option value="">⚡ Quick Reply Suggestions...</option>
                                                <option
                                                    value="Thank you! We're happy to hear you're satisfied with your order.">
                                                    Positive Feedback</option>
                                                <option
                                                    value="We apologize for the inconvenience. Please contact us so we can resolve this.">
                                                    Negative Feedback</option>
                                            </select>
                                            <div class="reply-input-wrap">
                                                <textarea class="reply-textarea" placeholder="Type your response..."
                                                    id="reply-text-<?php echo $review['id']; ?>"></textarea>
                                                <button class="reply-submit"
                                                    onclick="submitReply(<?php echo $review['id']; ?>, this)">Reply</button>
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
        function reviewManager() {
            return {
                search: '<?php echo addslashes($search); ?>',
                service: '<?php echo addslashes($service); ?>',
                reviewType: '<?php echo addslashes($review_type); ?>',
                rating: <?php echo $rating; ?>,
                activeSort: '<?php echo addslashes($_GET['sort_by'] ?? 'newest'); ?>',
                filterOpen: false,
                sortOpen: false,
                getProfileImage(image) {
                    if (!image || image === 'null' || image === 'undefined') {
                        return '/printflow/public/assets/uploads/profiles/default.png';
                    }
                    if (typeof image !== 'string') return '/printflow/public/assets/uploads/profiles/default.png';
                    if (image.startsWith('/') || image.startsWith('http')) return image;
                    return '/printflow/public/assets/uploads/profiles/' + image;
                },

                init() {
                    // Keep state
                },

                get filterActiveCount() {
                    let count = 0;
                    if (this.service) count++;
                    if (this.reviewType) count++;
                    if (this.rating > 0) count++;
                    return count;
                },

                applyFilters() {
                    const params = new URLSearchParams();
                    if (this.search) params.set('search', this.search);
                    if (this.service) params.set('service', this.service);
                    if (this.reviewType) params.set('review_type', this.reviewType);
                    if (this.rating > 0) params.set('rating', this.rating);
                    if (this.activeSort !== 'newest') params.set('sort_by', this.activeSort);
                    window.location.search = params.toString();
                },

                applySort(id) {
                    this.activeSort = id;
                    this.sortOpen = false;
                    this.applyFilters();
                },

                resetFilters() {
                    window.location.href = 'reviews.php';
                }
            };
        }

        function openMediaModal(src, type) {
            const modal = document.getElementById('mediaModal');
            const content = document.getElementById('modalContent');
            if (type === 'video') {
                content.innerHTML = `<video src="${src}" controls autoplay style="max-height: 80vh; max-width: 100%; border-radius: 12px;"></video>`;
            } else {
                content.innerHTML = `<img src="${src}" style="max-height: 80vh; max-width: 100%; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);">`;
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

        async function submitReply(reviewId, btnElement) {
            const textarea = document.getElementById('reply-text-' + reviewId);
            const msg = textarea.value.trim();
            if (!msg) return;

            const originalText = btnElement.innerText;
            btnElement.innerText = "Sending...";
            btnElement.disabled = true;

            try {
                const response = await fetch('api_review_reply.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ review_id: reviewId, message: msg, csrf_token: '<?php echo $csrf_token; ?>' })
                });
                const data = await response.json();
                if (data.success) {
                    btnElement.style.backgroundColor = '#16a34a';
                    btnElement.style.color = '#fff';
                    btnElement.innerText = "✓ Reply Sent";
                    setTimeout(() => { location.reload(); }, 1500);
                } else {
                    alert(data.error || 'Failed to post reply');
                    btnElement.innerText = originalText;
                    btnElement.disabled = false;
                }
            } catch (e) {
                alert('An error occurred');
                btnElement.innerText = originalText;
                btnElement.disabled = false;
            }
        }
    </script>
</body>

</html>