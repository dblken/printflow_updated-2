<?php
/**
 * Staff Notifications Page — matches admin notifications UI (filters, pagination, actions).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$staff_id = get_user_id();

// Handle actions (same pattern as admin/notifications.php)
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'mark_read' && isset($_GET['id'])) {
        $notification_id = (int)$_GET['id'];
        db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?", 'ii', [$notification_id, $staff_id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_unread_count') {
        $r = db_query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$staff_id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => (int)($r[0]['count'] ?? 0)]);
        exit;
    }

    if ($action === 'mark_all_read') {
        db_execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 'i', [$staff_id]);
        redirect('/printflow/staff/notifications.php?success=All notifications marked as read');
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        $notification_id = (int)$_GET['id'];
        db_execute("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?", 'ii', [$notification_id, $staff_id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "user_id = ? AND type != 'Message'";
$params = [$staff_id];
$types = 'i';

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif (in_array($filter, ['Order', 'Stock', 'System'], true)) {
    $where .= " AND type = ?";
    $params[] = $filter;
    $types .= 's';
}

if ($search !== '') {
    $where .= " AND message LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

$per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$count_row = db_query("SELECT COUNT(*) as cnt FROM notifications WHERE $where", $types, $params);
$total_count = (int)($count_row[0]['cnt'] ?? 0);
$total_pages = max(1, (int)ceil(max(1, $total_count) / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$notifications = db_query(
    "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$per_page, $offset])
) ?: [];

$unread_result = db_query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$staff_id]);
$unread_count = (int)($unread_result[0]['count'] ?? 0);

$notif_filter_badge = ($filter !== 'all' ? 1 : 0) + ($search !== '' ? 1 : 0);
$notif_pagination_params = ['filter' => $filter];
if ($search !== '') {
    $notif_pagination_params['search'] = $search;
}

$page_title = 'Notifications - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="turbo-visit-control" content="reload">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        [x-cloak] { display: none !important; }
        .notif-card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 18px 20px 14px;
            border-bottom: 1px solid #f3f4f6;
        }
        .notif-card-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
        .notif-card-sub { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
            height: 38px;
        }
        .toolbar-btn:hover { border-color: #9ca3af; background: #f9fafb; }
        .toolbar-btn.active { border-color: #047676; color: #047676; background: #e6f7f5; }
        .filter-panel {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 320px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 200;
            overflow: hidden;
        }
        .filter-panel-header {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .filter-section { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; }
        .filter-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .filter-section-label { font-size: 13px; font-weight: 600; color: #374151; }
        .filter-reset-link {
            font-size: 12px;
            font-weight: 600;
            color: #06A1A1;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-select {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            background: #fff;
            box-sizing: border-box;
            cursor: pointer;
        }
        .filter-select:focus { outline: none; border-color: #06A1A1; }
        .filter-search-input {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 12px;
            color: #1f2937;
            box-sizing: border-box;
        }
        .filter-search-input:focus { outline: none; border-color: #06A1A1; }
        .filter-actions { display: flex; gap: 8px; padding: 14px 18px; border-top: 1px solid #f3f4f6; }
        .filter-btn-reset {
            flex: 1;
            height: 36px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }
        .filter-btn-reset:hover { background: #f9fafb; }
        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #06A1A1;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
        }
        .notif-header-primary {
            height: 38px;
            padding: 0 16px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            background: #06A1A1;
            color: #fff !important;
            border: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .notif-header-primary:hover { background: #058f8f; color: #fff !important; }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.1s;
            cursor: pointer;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #fafafa; margin: 0 -20px; padding: 16px 20px; border-radius: 8px; }
        .notif-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #06A1A1; flex-shrink: 0; margin-top: 6px;
        }
        .notif-dot.read { background: transparent; border: 2px solid #e5e7eb; }
        .notif-icon-wrap {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .notif-icon-wrap.order { background: #dbeafe; color: #1e40af; }
        .notif-icon-wrap.stock { background: #fef3c7; color: #b45309; }
        .notif-icon-wrap.system { background: #f3f4f6; color: #374151; }
        .notif-body { flex: 1; min-width: 0; }
        .notif-msg {
            font-size: 13px; font-weight: 500; color: #111827;
            line-height: 1.5; margin-bottom: 4px;
        }
        .notif-item.read .notif-msg { color: #6b7280; font-weight: 400; }
        .notif-time {
            font-size: 12px; color: #9ca3af; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        }
        .type-pill {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            font-size: 11px; font-weight: 600;
        }
        .type-pill.order { background: #dbeafe; color: #1e40af; }
        .type-pill.stock { background: #fef3c7; color: #b45309; }
        .type-pill.system { background: #f3f4f6; color: #374151; }
        .notif-actions-wrap {
            display: flex; gap: 6px; flex-shrink: 0; align-items: flex-start; padding-top: 2px;
        }
        .notif-action-btn {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;
            border: 1px solid #e5e7eb; background: #fff; color: #374151; cursor: pointer; transition: all 0.15s;
        }
        .notif-action-btn:hover { background: #f3f4f6; }
        .notif-action-btn.danger { color: #dc2626; border-color: #fecaca; background: #fff5f5; }
        .notif-action-btn.danger:hover { background: #fee2e2; }
        .notif-group-label {
            font-size: 11px; font-weight: 700; color: #9ca3af;
            text-transform: uppercase; letter-spacing: 0.06em;
            padding: 16px 0 8px;
        }
        .empty-notif { text-align: center; padding: 60px 20px; }
        .empty-notif-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: #f3f4f6; margin: 0 auto 16px;
            display: flex; align-items: center; justify-content: center;
        }
        .empty-notif-title { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 6px; }
        .empty-notif-text { font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div>
                <h1 class="page-title" style="margin-bottom:4px;">Notifications</h1>
                <p style="font-size:14px;color:#6b7280;"><?php echo (int)$unread_count; ?> unread · <?php echo number_format($total_count); ?> matching this view</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <button type="button" onclick="refreshNotifications()" class="btn-secondary" style="height:38px;padding:0 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh
                </button>
                <?php if ($unread_count > 0): ?>
                <a href="?action=mark_all_read" class="notif-header-primary">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Mark All Read
                </a>
                <?php endif; ?>
            </div>
        </header>

        <main>        <?php if (empty($notifications)): ?>
            <div class="card" style="text-align:center; padding:48px 24px;">
                <div style="font-size:48px; margin-bottom:12px;">🔔</div>
                <p style="color:#6b7280; font-size:14px;">No notifications yet</p>
            </div>
        <?php else: ?>
            <div class="card" style="padding:0;overflow:hidden;">
                <div class="notif-card-head" x-data="notifFilterPanel()">
                    <div>
                        <h3>Notifications</h3>
                        <div class="notif-card-sub">Page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?> · <?php echo number_format($total_count); ?> total</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <div style="position:relative;">
                            <button type="button" class="toolbar-btn" :class="{active: filterOpen || hasActiveFilters}" @click="filterOpen = !filterOpen" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                </svg>
                                Filter
                                <?php if ($notif_filter_badge > 0): ?>
                                <span class="filter-badge"><?php echo (int)$notif_filter_badge; ?></span>
                                <?php endif; ?>
                            </button>
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-panel-header">Filter</div>
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Type</span>
                                        <button type="button" class="filter-reset-link" onclick="notifResetField('filter')">Reset</button>
                                    </div>
                                    <select id="nt_fp_filter" class="filter-select">
                                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All notifications</option>
                                        <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread only</option>
                                        <option value="Order" <?php echo $filter === 'Order' ? 'selected' : ''; ?>>Orders</option>
                                        <option value="Stock" <?php echo $filter === 'Stock' ? 'selected' : ''; ?>>Inventory</option>
                                        <option value="System" <?php echo $filter === 'System' ? 'selected' : ''; ?>>System</option>
                                    </select>
                                </div>
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Keyword search</span>
                                        <button type="button" class="filter-reset-link" onclick="notifResetField('search')">Reset</button>
                                    </div>
                                    <input type="text" id="nt_fp_search" class="filter-search-input" placeholder="Search message..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="filter-actions">
                                    <button type="button" class="filter-btn-reset" style="width:100%;" onclick="notifResetAllFilters()">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="padding:0 20px 12px;" id="notifications-container">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-notif">
                            <div class="empty-notif-icon">
                                <svg width="28" height="28" fill="none" stroke="#9ca3af" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <div class="empty-notif-title">No notifications</div>
                            <p class="empty-notif-text">
                                <?php if ($filter === 'unread'): ?>You're all caught up! No unread notifications.
                                <?php elseif ($search !== ''): ?>No results for "<?php echo htmlspecialchars($search); ?>"
                                <?php else: ?>You don't have any notifications yet.<?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php
                        $grouped = ['New' => [], 'Earlier' => []];
                        foreach ($notifications as $n) {
                            $grouped[$n['is_read'] == 0 ? 'New' : 'Earlier'][] = $n;
                        }
                        $grouped = array_filter($grouped);

                        foreach ($grouped as $group => $notifs): ?>
                            <div class="notif-group-label"><?php echo htmlspecialchars($group); ?></div>
                            <?php foreach ($notifs as $notif):
                                $t = strtolower((string)$notif['type']);
                                $type_slug = 'system';
                                if (strpos($t, 'stock') !== false) {
                                    $type_slug = 'stock';
                                } elseif (strpos($t, 'order') !== false || strpos($t, 'job') !== false || strpos($t, 'payment') !== false || strpos($t, 'design') !== false) {
                                    $type_slug = 'order';
                                }

                                $is_unread = !(int)$notif['is_read'];
                                $target_url = staff_notification_target_url($notif);

                                $iconSvg = match ($type_slug) {
                                    'order' => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>',
                                    'stock' => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
                                    default => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                                };
                                ?>
                            <div class="notif-item <?php echo $is_unread ? '' : 'read'; ?>" data-id="<?php echo (int)$notif['notification_id']; ?>">
                                <div class="notif-dot <?php echo $is_unread ? '' : 'read'; ?>"></div>
                                <div class="notif-body" style="padding-left: 12px; border-left: 2px solid #eef2f3;">
                                    <a href="<?php echo htmlspecialchars($target_url); ?>" class="notif-msg" style="text-decoration:none;display:block;" data-turbo="false" onclick="handleNotifClick(event, <?php echo (int)$notif['notification_id']; ?>, <?php echo json_encode($target_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>, <?php echo $is_unread ? 'true' : 'false'; ?>)">
                                        <?php 
                                        // Remove common emojis to keep look professional
                                        $clean_msg = (string)$notif['message'];
                                        $clean_msg = preg_replace('/[\x{1F300}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $clean_msg);
                                        echo htmlspecialchars(trim($clean_msg)); 
                                        ?>
                                    </a>
                                    <div class="notif-time">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?php echo htmlspecialchars(time_ago($notif['created_at'])); ?>
                                        <span class="type-pill <?php echo htmlspecialchars($type_slug); ?>"><?php echo htmlspecialchars($notif['type']); ?></span>
                                    </div>
                                </div>
                                <div class="notif-actions-wrap">
                                    <?php if ($is_unread): ?>
                                    <button type="button" onclick="markAsRead(<?php echo (int)$notif['notification_id']; ?>)" class="btn-action btn-action-primary">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Read
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" onclick="deleteNotification(<?php echo (int)$notif['notification_id']; ?>)" class="btn-action btn-action-danger">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="nt-pagination" style="padding: 0 20px 20px;">
                    <?php echo render_pagination($page, $total_pages, $notif_pagination_params); ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
function notifFilterPanel() {
    return {
        filterOpen: false,
        get hasActiveFilters() {
            var f = document.getElementById('nt_fp_filter');
            var s = document.getElementById('nt_fp_search');
            var fv = f ? f.value : 'all';
            var sv = s ? (s.value || '').trim() : '';
            return fv !== 'all' || sv.length > 0;
        }
    };
}
function notifNavigate(page) {
    var p = new URLSearchParams();
    var ff = document.getElementById('nt_fp_filter');
    p.set('filter', ff ? ff.value : 'all');
    var si = document.getElementById('nt_fp_search');
    var q = si ? (si.value || '').trim() : '';
    if (q) p.set('search', q);
    if (page && page > 1) p.set('page', String(page));
    window.location.href = (window.location.pathname || '') + '?' + p.toString();
}
function notifResetAllFilters() {
    window.location.href = window.location.pathname || 'notifications.php';
}
function notifResetField(which) {
    if (which === 'filter') {
        var el = document.getElementById('nt_fp_filter');
        if (el) el.value = 'all';
    }
    if (which === 'search') {
        var el2 = document.getElementById('nt_fp_search');
        if (el2) el2.value = '';
    }
    notifNavigate(1);
}
var ntSearchTimer = null;
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('nt_fp_filter');
    if (sel) sel.addEventListener('change', function () { notifNavigate(1); });
    var inp = document.getElementById('nt_fp_search');
    if (inp) {
        inp.addEventListener('input', function () {
            clearTimeout(ntSearchTimer);
            ntSearchTimer = setTimeout(function () { notifNavigate(1); }, 500);
        });
    }
});

let autoRefreshInterval;
function startAutoRefresh() {
    autoRefreshInterval = setInterval(checkForNewNotifications, 30000);
}
function stopAutoRefresh() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
}
function checkForNewNotifications() {
    // Silently fetch unread count and update badge — do NOT reload the full page
    fetch('?action=get_unread_count', { credentials: 'include' })
        .then(function(r) { return r.ok ? r.json() : null; })
        .then(function(data) {
            if (data && typeof data.count !== 'undefined') {
                // Update badge if PFNotifications is available
                if (window.PFNotifications && window.PFNotifications.updateBadge) {
                    window.PFNotifications.updateBadge(data.count);
                }
            }
        })
        .catch(function() { /* silently ignore network errors */ });
}
function refreshNotifications() {
    window.location.reload();
}
function handleNotifClick(e, notifId, url, isUnread) {
    if (isUnread) {
        e.preventDefault();
        markAsRead(notifId, url);
    } else if (url && url !== '#') {
        // Already read — navigate without Turbo to avoid Alpine double-init
        e.preventDefault();
        pfNavigate(url);
    } else {
        e.preventDefault();
    }
}
/**
 * Navigate to a URL without Turbo Drive interception.
 * Turbo Drive intercepts window.location.href assignments on same-origin URLs,
 * causing a partial body swap that leaves Alpine's old instance active and
 * results in duplicate x-for rendered elements (doubled tabs, doubled lists).
 * By using a temporary anchor with data-turbo="false" we force a full page load.
 */
function pfNavigate(url) {
    if (!url || url === '#') return;
    // If Turbo is not present, just navigate normally
    if (typeof window.Turbo === 'undefined' && typeof window.Turbo === 'undefined') {
        window.location.href = url;
        return;
    }
    // Create a temporary link with data-turbo="false" to bypass Turbo Drive
    var a = document.createElement('a');
    a.href = url;
    a.setAttribute('data-turbo', 'false');
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(function() { if (a.parentNode) a.parentNode.removeChild(a); }, 100);
}
function markAsRead(notifId, redirectUrl) {
    fetch('?action=mark_read&id=' + encodeURIComponent(notifId))
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                if (redirectUrl && redirectUrl !== '#') {
                    // Use pfNavigate to avoid Turbo Drive partial swap (which doubles Alpine x-for elements)
                    pfNavigate(redirectUrl);
                    return;
                }
                var item = document.querySelector('[data-id="' + notifId + '"]');
                if (item) {
                    item.classList.add('read');
                    var dot = item.querySelector('.notif-dot');
                    if (dot) dot.classList.add('read');
                    var readBtn = item.querySelector('.notif-action-btn:not(.danger)');
                    if (readBtn) readBtn.remove();
                }
                setTimeout(function () { window.location.reload(); }, 400);
            }
        })
        .catch(function (err) { console.error(err); });
}
function deleteNotification(notifId) {
    if (!confirm('Delete this notification?')) return;
    fetch('?action=delete&id=' + encodeURIComponent(notifId))
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                var item = document.querySelector('[data-id="' + notifId + '"]');
                if (item) {
                    item.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(16px)';
                    setTimeout(function () {
                        item.remove();
                        var container = document.getElementById('notifications-container');
                        if (container && container.querySelectorAll('.notif-item').length === 0) {
                            window.location.reload();
                        }
                    }, 250);
                }
            }
        })
        .catch(function (err) { console.error(err); });
}
startAutoRefresh();
document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopAutoRefresh();
    else startAutoRefresh();
});
</script>

</body>
</html>
