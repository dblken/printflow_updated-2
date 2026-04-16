<?php
/**
 * Customer Notifications Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
    $back_filter = isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : '';
    redirect('/printflow/customer/notifications.php' . $back_filter);
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    db_execute("UPDATE notifications SET is_read = 1 WHERE customer_id = ? AND is_read = 0", 'i', [$customer_id]);
    redirect('/printflow/customer/notifications.php');
}

// Pagination settings
$items_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Get total count
$count_result = db_query(
    "SELECT COUNT(*) as total FROM notifications WHERE customer_id = ?",
    'i',
    [$customer_id]
);
$total_items = $count_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);
$notifications = get_customer_notifications_for_display($customer_id, $items_per_page, $offset);

// Categorize by read status for display
$grouped_notifications = [
    'New' => [],
    'Earlier' => []
];
foreach ($notifications as $notification) {
    if ((int)($notification['is_read'] ?? 0) === 0) {
        $grouped_notifications['New'][] = $notification;
    } else {
        $grouped_notifications['Earlier'][] = $notification;
    }
}
$grouped_notifications = array_filter($grouped_notifications);
$unread_total = get_unread_notification_count($customer_id, 'Customer');

$page_title = 'Notifications - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* Container */
    .notif-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Header Section */
    .notif-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        gap: 1rem;
    }
    .notif-page-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: #eaf6fb;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .notif-mark-all-btn {
        padding: 0.65rem 1.25rem;
        border-radius: 0;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        background: rgba(83, 197, 224, 0.1);
        border: none;
        color: #53c5e0;
        text-decoration: none;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .notif-mark-all-btn:hover {
        background: rgba(83, 197, 224, 0.2);
        transform: translateY(-1px);
    }

    /* Group Label */
    .notif-group-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: rgba(83, 197, 224, 0.6);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin: 2rem 0 0.75rem;
        padding-left: 0.25rem;
    }
    .notif-group-label:first-child {
        margin-top: 0;
    }

    /* Notification Card */
    .notif-card {
        background: #0a2530;
        border: none;
        border-radius: 0;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: all 0.2s;
        text-decoration: none;
        display: block;
    }
    .notif-card:hover {
        background: #0d3240;
        transform: translateX(4px);
    }
    .notif-card.unread {
        background: #0d3240;
        border-left: 3px solid #53c5e0;
    }

    /* Desktop Layout */
    .notif-card-inner {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    .notif-image-wrap {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 0;
        overflow: hidden;
        background: rgba(0, 0, 0, 0.2);
        border: none;
        flex-shrink: 0;
    }
    .notif-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .notif-content-wrap {
        flex: 1;
        min-width: 0;
    }
    .notif-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #eaf6fb;
        margin-bottom: 0.35rem;
    }
    .notif-card.unread .notif-title {
        color: #53c5e0;
    }
    .notif-description {
        font-size: 0.85rem;
        line-height: 1.5;
        color: #9fc4d4;
        margin-bottom: 0.5rem;
        word-wrap: break-word;
    }
    .notif-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .notif-time {
        font-size: 0.75rem;
        color: rgba(83, 197, 224, 0.6);
        font-weight: 600;
    }
    .notif-view-btn {
        padding: 0.4rem 0.9rem;
        border-radius: 0;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        background: rgba(83, 197, 224, 0.1);
        border: none;
        color: #53c5e0;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .notif-card:hover .notif-view-btn {
        background: #53c5e0;
        color: #030d11;
    }

    /* Empty State */
    .notif-empty {
        text-align: center;
        padding: 4rem 2rem;
        color: rgba(255, 255, 255, 0.4);
    }
    .notif-empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    /* Mobile Responsive */
    @media (max-width: 640px) {
        .notif-page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .notif-page-title {
            font-size: 1.5rem;
        }
        .notif-mark-all-btn {
            width: 100%;
            text-align: center;
        }
        .notif-card-inner {
            flex-direction: column;
        }
        .notif-image-wrap {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9;
            max-height: 180px;
        }
        .notif-meta {
            flex-direction: column;
            align-items: flex-start;
        }
        .notif-view-btn {
            width: 100%;
            text-align: center;
            padding: 0.6rem 1rem;
        }
    }
</style>

<div class="min-h-screen py-8">
    <div class="notif-container">
        <!-- Header -->
        <div class="notif-page-header">
            <h1 class="notif-page-title">
                Notifications
                <?php if ($unread_total > 0): ?>
                    <span style="background: #53c5e0; color: #030d11; padding: 4px 12px; border-radius: 0; font-size: 0.75rem; font-weight: 900; box-shadow: 0 0 15px rgba(83, 197, 224, 0.4);"><?php echo $unread_total; ?></span>
                <?php endif; ?>
            </h1>
            <?php if ($unread_total > 0): ?>
                <a href="?mark_all_read=1" class="notif-mark-all-btn">Mark all as read</a>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="notif-empty">
                <div class="notif-empty-icon">&#128276;</div>
                <p style="font-size: 1rem; font-weight: 600;">No notifications yet</p>
                <p style="font-size: 0.85rem; margin-top: 0.5rem;">We'll notify you when something important happens</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_notifications as $group => $notifs): ?>
                <?php if ($group === 'New'): ?>
                    <div class="notif-group-label"><?php echo htmlspecialchars($group); ?></div>
                <?php endif; ?>
                <?php foreach ($notifs as $notif): ?>
                    <?php
                    $is_rating_notif = (
                        (string)($notif['type'] ?? '') === 'Rating' ||
                        stripos((string)$notif['message'], 'rate your experience') !== false ||
                        stripos((string)$notif['message'], 'rate your order') !== false ||
                        stripos((string)$notif['message'], 'replied to your review') !== false
                    );
                    $msg = htmlspecialchars((string)$notif['message']);
                    $msg = preg_replace('/(Order #\d+)/', '<b>$1</b>', $msg);
                    ?>
                    <a href="<?php echo htmlspecialchars((string)$notif['link']); ?>" class="notif-card <?php echo !empty($notif['is_read']) ? '' : 'unread'; ?>">
                        <div class="notif-card-inner">
                            <div class="notif-image-wrap">
                                <img src="<?php echo htmlspecialchars((string)$notif['image']); ?>"
                                     alt="<?php echo htmlspecialchars((string)$notif['title']); ?>"
                                     class="notif-image"
                                     onerror="this.onerror=null;this.src='<?php echo htmlspecialchars((string)$notif['fallback'], ENT_QUOTES); ?>';">
                            </div>
                            <div class="notif-content-wrap">
                                <div class="notif-title"><?php echo htmlspecialchars((string)$notif['title']); ?></div>
                                <div class="notif-description"><?php echo $msg; ?></div>
                                <div class="notif-meta">
                                    <div class="notif-time"><?php echo htmlspecialchars((string)$notif['time_ago']); ?></div>
                                    <?php if (!empty($notif['data_id'])): ?>
                                        <span class="notif-view-btn"><?php echo $is_rating_notif ? 'Rate Now' : 'View'; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="margin-top: 2rem;">
                <?php echo render_pagination($current_page, $total_pages, [], 'page'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
