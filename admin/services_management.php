<?php
/**
 * Admin Services Management — service catalog (no inventory).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer_service_catalog.php';

require_role(['Admin', 'Manager']);

$current_user = get_logged_in_user();
$error = '';
$success = '';

/** Duplicate name check (case-insensitive, trimmed). */
function service_name_exists(string $name, int $excludeId = 0): bool {
    $name = trim($name);
    $rows = db_query(
        "SELECT service_id FROM services WHERE LOWER(TRIM(name)) = LOWER(?) AND service_id != ?",
        'si',
        [$name, $excludeId]
    );
    return !empty($rows);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['create_service'])) {
        $name = preg_replace('/\s+/', ' ', trim($_POST['name'] ?? ''));
        $category = sanitize($_POST['category'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = 1.0;
        $statusRaw = trim((string) ($_POST['status'] ?? ''));
        $status = ($statusRaw === 'Deactivated') ? 'Deactivated' : 'Activated';
        $customer_link = sanitize(trim((string) ($_POST['customer_link'] ?? '')));
        $hero_image = sanitize(trim((string) ($_POST['hero_image'] ?? '')));
        $customer_modal_text = trim(sanitize((string) ($_POST['customer_modal_text'] ?? '')));

        if ($name === '') {
            $error = 'Service name is required.';
        } elseif (strlen($name) > 150) {
            $error = 'Service name must not exceed 150 characters.';
        } elseif (empty($category) || $category === '-- Select Category --') {
            $error = 'Please select a category.';
        } elseif (strlen($description) > 2000) {
            $error = 'Description must not exceed 2000 characters.';
        } elseif (strlen($customer_modal_text) > 2000) {
            $error = 'Customer modal message must not exceed 2000 characters.';
        } elseif (service_name_exists($name, 0)) {
            $error = 'A service with this name already exists.';
        } else {
            $result = db_execute(
                'INSERT INTO services (name, category, description, price, duration, status, visible_to_customer, customer_link, hero_image, customer_modal_text, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, ?, 1, ?, ?, ?, NOW(), NOW())',
                'sss' . 'd' . 's' . 'sss',
                [$name, $category, $description, $price, $status, $customer_link, $hero_image, $customer_modal_text]
            );
            if ($result) {
                $success = 'Service created successfully';
            } else {
                global $conn;
                $error = 'Failed to create service. ' . ($conn->error ?? '');
            }
        }
    } elseif (isset($_POST['update_service'])) {
        $service_id = (int)($_POST['service_id'] ?? 0);
        $name = preg_replace('/\s+/', ' ', trim($_POST['name'] ?? ''));
        $category = sanitize($_POST['category'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = 1.0;
        $statusRaw = trim((string) ($_POST['status'] ?? ''));
        $status = ($statusRaw === 'Deactivated') ? 'Deactivated' : 'Activated';
        $customer_link = sanitize(trim((string) ($_POST['customer_link'] ?? '')));
        $hero_image = sanitize(trim((string) ($_POST['hero_image'] ?? '')));
        $customer_modal_text = trim(sanitize((string) ($_POST['customer_modal_text'] ?? '')));

        if ($service_id < 1) {
            $error = 'Invalid service.';
        } elseif ($name === '') {
            $error = 'Service name is required.';
        } elseif (strlen($name) > 150) {
            $error = 'Service name must not exceed 150 characters.';
        } elseif (empty($category) || $category === '-- Select Category --') {
            $error = 'Please select a category.';
        } elseif (strlen($description) > 2000) {
            $error = 'Description must not exceed 2000 characters.';
        } elseif (strlen($customer_modal_text) > 2000) {
            $error = 'Customer modal message must not exceed 2000 characters.';
        } elseif (service_name_exists($name, $service_id)) {
            $error = 'A service with this name already exists.';
        } else {
            $result = db_execute(
                'UPDATE services SET name = ?, category = ?, description = ?, price = ?, duration = NULL, status = ?, customer_link = ?, hero_image = ?, customer_modal_text = ?, updated_at = NOW() WHERE service_id = ?',
                'sss' . 'd' . 's' . 'ss' . 's' . 'i',
                [$name, $category, $description, $price, $status, $customer_link, $hero_image, $customer_modal_text, $service_id]
            );
            if ($result) {
                $success = 'Service updated successfully';
            } else {
                global $conn;
                $error = 'Failed to update service. ' . ($conn->error ?? '');
            }
        }
    } elseif (isset($_POST['archive_service'])) {
        $service_id = (int)$_POST['service_id'];
        db_execute("UPDATE services SET status = 'Archived', updated_at = NOW() WHERE service_id = ?", 'i', [$service_id]);
        $success = 'Service archived successfully!';
    } elseif (isset($_POST['restore_service'])) {
        $service_id = (int)$_POST['service_id'];
        db_execute("UPDATE services SET status = 'Activated', updated_at = NOW() WHERE service_id = ?", 'i', [$service_id]);
        $success = 'Service restored successfully!';
    } elseif (isset($_POST['delete_service'])) {
        $service_id = (int)$_POST['service_id'];
        $current = db_query("SELECT status FROM services WHERE service_id = ?", 'i', [$service_id]);
        $status = $current[0]['status'] ?? '';

        if ($status === 'Archived') {
            db_execute("DELETE FROM services WHERE service_id = ?", 'i', [$service_id]);
            $success = 'Service deleted permanently!';
        } else {
            $new_status = ($status === 'Activated') ? 'Deactivated' : 'Activated';
            db_execute("UPDATE services SET status = ?, updated_at = NOW() WHERE service_id = ?", 'si', [$new_status, $service_id]);
            $success = 'Service ' . strtolower($new_status) . ' successfully!';
        }
    }
}

// Archived list (modal)
if (isset($_GET['get_archived'])) {
    header('Content-Type: application/json');
    $archived = db_query("SELECT * FROM services WHERE status = 'Archived' ORDER BY updated_at DESC") ?: [];

    $html = '<table class="orders-table" style="width:100%;">';
    $html .= '<thead><tr><th>Name</th><th>Category</th><th style="text-align:right;">Actions</th></tr></thead>';
    $html .= '<tbody>';

    if (empty($archived)) {
        $html .= '<tr><td colspan="3" style="padding:40px;text-align:center;color:#9ca3af;">No archived services found.</td></tr>';
    } else {
        foreach ($archived as $s) {
            $html .= '<tr>';
            $html .= '<td style="font-weight:500; max-width:300px; word-break: break-word;">' . htmlspecialchars($s['name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($s['category'] ?? '—') . '</td>';
            $html .= '<td style="text-align:right;white-space:nowrap;">';
            $html .= '<form method="POST" class="inline service-status-form" data-pf-skip-guard style="display:inline-block;margin-right:4px;" data-action="Restore" data-service-name="' . htmlspecialchars($s['name'], ENT_QUOTES) . '" onsubmit="showServiceStatusModal(event, this);return false;">';
            $html .= csrf_field();
            $html .= '<input type="hidden" name="service_id" value="' . (int)$s['service_id'] . '">';
            $html .= '<button type="submit" name="restore_service" class="btn-action teal">Restore</button></form>';
            $html .= '<form method="POST" class="inline service-status-form" data-pf-skip-guard style="display:inline-block;" data-action="Delete Permanently" data-service-name="' . htmlspecialchars($s['name'], ENT_QUOTES) . '" onsubmit="showServiceStatusModal(event, this);return false;">';
            $html .= csrf_field();
            $html .= '<input type="hidden" name="service_id" value="' . (int)$s['service_id'] . '">';
            $html .= '<button type="submit" name="delete_service" class="btn-action red">Delete</button></form>';
            $html .= '</td></tr>';
        }
    }
    $html .= '</tbody></table>';

    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$search = trim($_GET['search'] ?? '');
$cat_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'newest';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT * FROM services WHERE status != 'Archived'";
$params = [];
$types = '';

if ($search !== '') {
    $like = '%' . $search . '%';
    $sql .= " AND (name LIKE ? OR category LIKE ?)";
    $params = array_merge($params, [$like, $like]);
    $types .= 'ss';
}
if ($cat_filter !== '') {
    $sql .= " AND category = ?";
    $params[] = $cat_filter;
    $types .= 's';
}
if ($status_filter !== '') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($date_from)) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if (!empty($date_to)) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$count_sql = str_replace('SELECT *', 'SELECT COUNT(*) as total', $sql);
$total_row = db_query($count_sql, $types ?: null, $params ?: null);
$total_services = $total_row[0]['total'] ?? 0;
$total_pages = max(1, ceil($total_services / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$order_clause = match ($sort_by) {
    'oldest' => 'created_at ASC',
    'az' => 'name ASC',
    'za' => 'name DESC',
    default => 'created_at DESC',
};
$sql .= " ORDER BY $order_clause LIMIT $per_page OFFSET $offset";
$services = db_query($sql, $types ?: null, $params ?: null) ?: [];

$page_title = 'Services Management - Admin';

$stat_total = db_query("SELECT COUNT(*) as c FROM services WHERE status != 'Archived'")[0]['c'] ?? 0;
$stat_active = db_query("SELECT COUNT(*) as c FROM services WHERE status='Activated'")[0]['c'] ?? 0;
$stat_inactive = db_query("SELECT COUNT(*) as c FROM services WHERE status='Deactivated'")[0]['c'] ?? 0;
$stat_archived = db_query("SELECT COUNT(*) as c FROM services WHERE status='Archived'")[0]['c'] ?? 0;

$categories = db_query("SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category != '' AND status != 'Archived' ORDER BY category ASC") ?: [];

function render_services_table_rows(array $services): void {
    ?>
    <table class="orders-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Service Name</th>
                <th>Category</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody id="servicesTableBody">
            <?php if (empty($services)): ?>
                <tr><td colspan="5" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">No services found.</td></tr>
            <?php else: ?>
                <?php foreach ($services as $svc): ?>
                    <tr onclick="openViewModal(<?php echo htmlspecialchars(json_encode($svc), ENT_QUOTES); ?>)">
                        <td style="color:#1f2937;"><?php echo (int)$svc['service_id']; ?></td>
                        <td style="font-weight:500;color:#1f2937;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($svc['name']); ?></td>
                        <td><?php echo htmlspecialchars($svc['category'] ?? '—'); ?></td>
                        <td>
                            <?php
                            $sc = match ($svc['status']) {
                                'Activated' => 'background:#dcfce7;color:#166534;',
                                'Deactivated' => 'background:#fee2e2;color:#991b1b;',
                                'Archived' => 'background:#f3f4f6;color:#374151;',
                                default => 'background:#fef9c3;color:#854d0e;',
                            };
                            ?>
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?php echo $sc; ?>"><?php echo htmlspecialchars($svc['status']); ?></span>
                        </td>
                        <td style="text-align:right;white-space:nowrap;" onclick="event.stopPropagation();">
                            <button type="button" class="btn-action blue" onclick='openModal("edit", <?php echo htmlspecialchars(json_encode($svc), ENT_QUOTES); ?>)'>Edit</button>
                            <?php if ($svc['status'] !== 'Archived'): ?>
                                <form method="POST" class="inline service-status-form" data-pf-skip-guard data-action="<?php echo $svc['status'] === 'Activated' ? 'Deactivate' : 'Activate'; ?>" data-service-name="<?php echo htmlspecialchars($svc['name'], ENT_QUOTES); ?>" onsubmit="showServiceStatusModal(event, this);return false;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="service_id" value="<?php echo (int)$svc['service_id']; ?>">
                                    <button type="submit" name="delete_service" class="btn-action <?php echo $svc['status'] === 'Activated' ? 'red' : 'teal'; ?>">
                                        <?php echo $svc['status'] === 'Activated' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <?php if ($svc['status'] === 'Deactivated'): ?>
                                    <form method="POST" class="inline service-status-form" data-pf-skip-guard data-action="Archive" data-service-name="<?php echo htmlspecialchars($svc['name'], ENT_QUOTES); ?>" onsubmit="showServiceStatusModal(event, this);return false;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="service_id" value="<?php echo (int)$svc['service_id']; ?>">
                                        <button type="submit" name="archive_service" class="btn-action gray">Archive</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST" class="inline service-status-form" data-pf-skip-guard data-action="Restore" data-service-name="<?php echo htmlspecialchars($svc['name'], ENT_QUOTES); ?>" onsubmit="showServiceStatusModal(event, this);return false;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="service_id" value="<?php echo (int)$svc['service_id']; ?>">
                                    <button type="submit" name="restore_service" class="btn-action teal">Restore</button>
                                </form>
                                <form method="POST" class="inline service-status-form" data-pf-skip-guard data-action="Delete Permanently" data-service-name="<?php echo htmlspecialchars($svc['name'], ENT_QUOTES); ?>" onsubmit="showServiceStatusModal(event, this);return false;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="service_id" value="<?php echo (int)$svc['service_id']; ?>">
                                    <button type="submit" name="delete_service" class="btn-action red">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

if (isset($_GET['ajax'])) {
    ob_start();
    render_services_table_rows($services);
    $table_html = ob_get_clean();
    ob_start();
    $pp = array_filter(['search' => $search, 'category' => $cat_filter, 'status' => $status_filter, 'sort' => $sort_by, 'date_from' => $date_from, 'date_to' => $date_to], function ($v) {
        return $v !== null && $v !== '';
    });
    echo render_pagination($page, $total_pages, $pp);
    $pagination_html = ob_get_clean();
    echo json_encode([
        'success' => true,
        'table' => '<div class="overflow-x-auto">' . $table_html . '</div>',
        'pagination' => $pagination_html,
        'count' => number_format($total_services),
        'badge' => count(array_filter([$search, $cat_filter, $status_filter, $date_from, $date_to], function ($v) {
            return $v !== null && $v !== '';
        })),
    ]);
    exit;
}

$category_options = ['Tarpaulin', 'T-Shirt', 'Stickers', 'Sintraboard Standees', 'Apparel', 'Signage', 'Merchandise', 'Print', 'Service', 'Consulting', 'Design'];
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
        .btn-action { display:inline-flex; align-items:center; justify-content:center; padding:5px 12px; min-width:72px; border:1px solid transparent; background:transparent; border-radius:6px; font-size:12px; font-weight:500; cursor:pointer; white-space:nowrap; }
        .btn-action.teal { color:#14b8a6; border-color:#14b8a6; } .btn-action.teal:hover { background:#14b8a6; color:#fff; }
        .btn-action.blue { color:#3b82f6; border-color:#3b82f6; } .btn-action.blue:hover { background:#3b82f6; color:#fff; }
        .btn-action.red { color:#ef4444; border-color:#ef4444; } .btn-action.red:hover { background:#ef4444; color:#fff; }
        .btn-action.gray { color:#6b7280; border-color:#d1d5db; } .btn-action.gray:hover { background:#6b7280; color:#fff; }
        .kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        @media(max-width:900px) { .kpi-row { grid-template-columns:repeat(2,1fr); } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.rose::before { background:linear-gradient(90deg,#e11d48,#fb7185); }
        .kpi-card.slate::before { background:linear-gradient(90deg,#64748b,#94a3b8); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }
        [x-cloak] { display:none !important; }
        .toolbar-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border:1px solid #e5e7eb; background:#fff; border-radius:8px; font-size:13px; font-weight:500; color:#374151; cursor:pointer; white-space:nowrap; }
        .toolbar-btn:hover { border-color:#9ca3af; background:#f9fafb; }
        .toolbar-btn.active { border-color:#0d9488; color:#0d9488; background:#f0fdfa; }
        .sort-dropdown { position:absolute; top:calc(100% + 6px); right:0; width:180px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); z-index:200; padding:6px; }
        .sort-option { padding:9px 12px; font-size:13px; color:#4b5563; border-radius:6px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; }
        .sort-option:hover { background:#f9fafb; }
        .sort-option.selected { background:#f0fdfa; color:#0d9488; font-weight:600; }
        .filter-panel { position:absolute; top:calc(100% + 6px); right:0; width:320px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.12); z-index:200; overflow:hidden; }
        .filter-panel-header { padding:14px 18px; border-bottom:1px solid #f3f4f6; font-size:14px; font-weight:700; }
        .filter-section { padding:14px 18px; border-bottom:1px solid #f3f4f6; }
        .filter-section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .filter-section-label { font-size:13px; font-weight:600; color:#374151; }
        .filter-reset-link { font-size:12px; font-weight:600; color:#0d9488; cursor:pointer; background:none; border:none; padding:0; }
        .filter-input, .filter-select, .filter-search-input { width:100%; height:34px; border:1px solid #e5e7eb; border-radius:7px; font-size:13px; padding:0 10px; box-sizing:border-box; }
        .filter-date-row { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .filter-date-label { font-size:11px; color:#6b7280; margin-bottom:4px; }
        .filter-actions { padding:14px 18px; border-top:1px solid #f3f4f6; }
        .filter-btn-reset { width:100%; height:36px; border:1px solid #e5e7eb; background:#fff; border-radius:8px; font-size:13px; cursor:pointer; }
        .filter-badge { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; background:#0d9488; color:#fff; border-radius:50%; font-size:10px; font-weight:700; }
        #service-modal-overlay, #view-service-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:16px; }
        #service-modal-overlay.active, #view-service-modal-overlay.active { display:flex; }
        #service-modal { max-width:560px; }
        #view-service-modal { max-width:640px; }
        #service-modal, #view-service-modal { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-height:90vh; overflow-y:auto; display:flex; flex-direction:column; }
        #service-modal .modal-header, #view-service-modal .modal-header { padding:18px 20px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
        #service-modal .modal-body, #view-service-modal .modal-body { padding:18px 20px 20px; overflow-y:auto; flex:1; }
        #service-modal .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:12px; }
        #service-modal .form-group { margin-bottom:12px; }
        #service-modal .form-group label { display:block; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:6px; }
        #service-modal .form-group input, #service-modal .form-group select, #service-modal .form-group textarea { width:100%; padding:10px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; font-family:inherit; color:#1f2937; box-sizing:border-box; background:#f9fafb; resize:vertical; max-width:100%; transition:border-color 0.2s; }
        #service-modal .form-group input:focus, #service-modal .form-group select:focus, #service-modal .form-group textarea:focus { outline:none; border-color:#0d9488; box-shadow:0 0 0 2px rgba(13,148,136,0.15); }
        #service-modal .form-group.has-error input, #service-modal .form-group.has-error select, #service-modal .form-group.has-error textarea { border-color:#ef4444 !important; box-shadow:0 0 0 2px rgba(239,68,68,0.15); }
        #service-modal .form-group.has-success input, #service-modal .form-group.has-success select, #service-modal .form-group.has-success textarea { border-color:#22c55e !important; }
        #service-modal .field-error { display:block; color:#dc2626; font-size:12px; margin-top:4px; }
        #service-modal .btn-save:disabled { opacity:0.6; cursor:not-allowed; }
        #service-modal .modal-footer { display:flex; gap:10px; margin-top:18px; padding-top:18px; border-top:1px solid #e5e7eb; }
        #service-modal .btn-cancel { flex:1; padding:10px 16px; border-radius:8px; background:#f3f4f6; border:none; font-weight:600; cursor:pointer; }
        #service-modal .btn-save { flex:1; padding:10px 16px; border-radius:8px; background:#0d9488; color:#fff; border:none; font-weight:600; cursor:pointer; }
        #service-modal .btn-save:disabled { opacity:0.65; cursor:not-allowed; }
        .view-label { display:block; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:6px; }
        .view-value-box { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:10px 14px; font-size:14px; word-break:break-word; }
        .orders-table { width:100%; border-collapse:collapse; font-size:13px; }
        .orders-table th { padding:12px 16px; font-weight:600; color:#6b7280; text-align:left; border-bottom:1px solid #e5e7eb; }
        .orders-table td { padding:12px 16px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
        .orders-table tbody tr { cursor:pointer; transition:background 0.1s; }
        .orders-table tbody tr:hover { background:#f9fafb; }
        @media (max-width:600px) { #service-modal .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <div class="main-content">
        <header><h1 class="page-title">Services Management</h1></header>
        <main>
            <?php if ($success): ?>
                <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:8px;margin-bottom:16px;">✓ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;">✗ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Services</div>
                    <div class="kpi-value"><?php echo (int)$stat_total; ?></div>
                    <div class="kpi-sub">Active list (non-archived)</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Active</div>
                    <div class="kpi-value"><?php echo (int)$stat_active; ?></div>
                    <div class="kpi-sub">Available for booking / quoting</div>
                </div>
                <div class="kpi-card rose">
                    <div class="kpi-label">Inactive</div>
                    <div class="kpi-value"><?php echo (int)$stat_inactive; ?></div>
                    <div class="kpi-sub">Hidden from default flows</div>
                </div>
                <div class="kpi-card slate">
                    <div class="kpi-label">Archived</div>
                    <div class="kpi-value"><?php echo (int)$stat_archived; ?></div>
                    <div class="kpi-sub">In archive storage</div>
                </div>
            </div>

            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;" x-data="filterPanel()">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;" id="servicesListHeader">Service List</h3>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button class="toolbar-btn" type="button" onclick="openModal('create')" style="height:38px;border-color:#3b82f6;color:#3b82f6;">Add Service</button>
                        <button class="toolbar-btn" type="button" onclick="window.openArchiveModal()" style="height:38px;border-color:#6b7280;color:#6b7280;display:flex;align-items:center;gap:6px;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                            Archived
                        </button>
                        <div style="position:relative;">
                            <button type="button" class="toolbar-btn" :class="{active: sortOpen || (activeSort !== 'newest')}" @click="sortOpen = !sortOpen; filterOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/></svg>
                                Sort by
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <?php $sorts = ['newest' => 'Newest to Oldest', 'oldest' => 'Oldest to Newest', 'az' => 'A → Z', 'za' => 'Z → A'];
                                foreach ($sorts as $key => $label): ?>
                                <div class="sort-option" :class="{ 'selected': activeSort === '<?php echo $key; ?>' }" onclick="applySortFilter('<?php echo $key; ?>')">
                                    <?php echo htmlspecialchars($label); ?>
                                    <svg x-show="activeSort === '<?php echo $key; ?>'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div style="position:relative;">
                            <button type="button" class="toolbar-btn" :class="{active: filterOpen || hasActiveFilters}" @click="filterOpen = !filterOpen; sortOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                Filter
                                <span id="filterBadgeContainer">
                                    <?php $afc = count(array_filter([$search, $cat_filter, $status_filter, $date_from, $date_to], function ($v) { return $v !== null && $v !== ''; }));
                                    if ($afc > 0): ?><span class="filter-badge"><?php echo $afc; ?></span><?php endif; ?>
                                </span>
                            </button>
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-panel-header">Filter</div>
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Date range</span>
                                        <button class="filter-reset-link" type="button" onclick="resetFilterField(['date_from','date_to'])">Reset</button>
                                    </div>
                                    <div class="filter-date-row">
                                        <div><div class="filter-date-label">From</div><input type="date" id="fp_date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>"></div>
                                        <div><div class="filter-date-label">To</div><input type="date" id="fp_date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>"></div>
                                    </div>
                                </div>
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Category</span>
                                        <button class="filter-reset-link" type="button" onclick="resetFilterField(['category'])">Reset</button>
                                    </div>
                                    <select id="fp_category" class="filter-select">
                                        <option value="">All categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $cat_filter === $cat['category'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Status</span>
                                        <button class="filter-reset-link" type="button" onclick="resetFilterField(['status'])">Reset</button>
                                    </div>
                                    <select id="fp_status" class="filter-select">
                                        <option value="">All statuses</option>
                                        <option value="Activated" <?php echo $status_filter === 'Activated' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Deactivated" <?php echo $status_filter === 'Deactivated' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Search</span>
                                        <button class="filter-reset-link" type="button" onclick="resetFilterField(['search'])">Reset</button>
                                    </div>
                                    <input type="text" id="fp_search" class="filter-search-input" placeholder="Service name or category..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="filter-actions">
                                    <button type="button" class="filter-btn-reset" onclick="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="servicesTableContainer">
                    <div class="overflow-x-auto">
                        <?php render_services_table_rows($services); ?>
                    </div>
                    <div id="servicesPagination">
                        <?php
                        $pagination_params = array_filter(['search' => $search, 'category' => $cat_filter, 'status' => $status_filter, 'sort' => $sort_by, 'date_from' => $date_from, 'date_to' => $date_to], function ($v) {
                            return $v !== null && $v !== '';
                        });
                        echo render_pagination($page, $total_pages, $pagination_params);
                        ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Confirm modal (z-index above printflow_form_guard overlays at 10030+) -->
<div id="serviceStatusConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10100;align-items:center;justify-content:center;padding:16px;flex-wrap:wrap;">
    <div style="background:white;border-radius:16px;padding:26px;max-width:420px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.25);text-align:center;position:relative;z-index:1;" role="dialog" aria-modal="true" aria-labelledby="serviceStatusConfirmTitle" onclick="event.stopPropagation();">
        <h3 id="serviceStatusConfirmTitle" style="font-size:18px;font-weight:700;margin:0 0 8px;">Confirm</h3>
        <p id="serviceStatusConfirmText" style="font-size:14px;color:#4b5563;margin:0 0 16px;line-height:1.5;"></p>
        <div id="serviceStatusInfoBox" style="font-size:12px;color:#6b7280;background:#f9fafb;padding:12px;border-radius:10px;margin-bottom:20px;text-align:left;border:1px solid #e5e7eb;">
            <div id="serviceStatusInfoText"></div>
        </div>
        <div style="display:flex;gap:12px;">
            <button type="button" id="serviceStatusConfirmCancel" style="flex:1;padding:12px;border:1px solid #e5e7eb;background:white;border-radius:10px;font-weight:600;cursor:pointer;">Cancel</button>
            <button type="button" id="serviceStatusConfirmOk" style="flex:1;padding:12px;border:none;background:#3b82f6;border-radius:10px;font-weight:600;color:white;cursor:pointer;">Confirm</button>
        </div>
    </div>
</div>

<!-- Add/Edit -->
<div id="service-modal-overlay" onclick="handleOverlayClick(event)">
    <div id="service-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
            <h3 id="modal-title" style="font-size:18px;font-weight:700;margin:0;">Add Service</h3>
            <button type="button" id="close-modal-btn" onclick="closeServiceModal()" style="background:none;border:none;cursor:pointer;color:#9ca3af;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" id="service-form" data-pf-skip-guard novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" id="modal-mode-input" name="create_service" value="1">
                <input type="hidden" id="modal-service-id" name="service_id" value="">

                <div class="form-group">
                    <label for="modal-name">Service Name <span style="color:red">*</span></label>
                    <input type="text" id="modal-name" name="name" maxlength="150" required placeholder="e.g. Large format printing">
                    <span id="err-name" class="field-error"></span>
                </div>

                <div class="form-group">
                    <label for="modal-category">Category <span style="color:red">*</span></label>
                    <select id="modal-category" name="category" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($category_options as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="err-category" class="field-error"></span>
                </div>

                <div class="form-group">
                    <label for="modal-description">Description <span style="color:red">*</span></label>
                    <textarea id="modal-description" name="description" rows="3" maxlength="2000" required placeholder="What this service includes..."></textarea>
                    <span id="err-description" class="field-error"></span>
                </div>

                <div class="form-group">
                    <label for="modal-customer-modal-text">Customer modal message <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                    <small style="display:block;color:#6b7280;font-size:12px;margin:-2px 0 6px;">Text shown on the customer Services page when they open a service (below the title). Leave blank to use the default wording.</small>
                    <textarea id="modal-customer-modal-text" name="customer_modal_text" rows="4" maxlength="2000" placeholder="<?php echo htmlspecialchars(printflow_default_customer_service_modal_text()); ?>"></textarea>
                    <span id="err-customer-modal-text" class="field-error"></span>
                </div>

                <div class="form-group">
                    <label for="modal-status">Status <span style="color:red">*</span></label>
                    <select id="modal-status" name="status" required>
                        <option value="Activated">Active</option>
                        <option value="Deactivated">Inactive</option>
                    </select>
                    <span id="err-status" class="field-error"></span>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeServiceModal()">Cancel</button>
                    <button type="submit" id="modal-submit-btn" class="btn-save">Save Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View -->
<div id="view-service-modal-overlay" onclick="handleViewOverlayClick(event)">
    <div id="view-service-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
            <h3 style="font-size:18px;font-weight:700;margin:0;">Service Details</h3>
            <button type="button" onclick="closeViewModal()" style="background:none;border:none;cursor:pointer;color:#9ca3af;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div style="display:grid;gap:14px;">
                <div><span class="view-label">Service Name</span><div id="view-name" class="view-value-box" style="font-weight:700;">—</div></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div><span class="view-label">Category</span><div id="view-category" class="view-value-box">—</div></div>
                    <div><span class="view-label">Status</span><div id="view-status" class="view-value-box">—</div></div>
                </div>
                <div><span class="view-label">Description</span><div id="view-description" class="view-value-box" style="white-space:pre-wrap;min-height:60px;">—</div></div>
                <div><span class="view-label">Customer modal message</span><div id="view-customer-modal-text" class="view-value-box" style="white-space:pre-wrap;min-height:48px;">—</div></div>
            </div>
            <div style="padding:16px 0 0;margin-top:24px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;">
                <button type="button" class="btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Archive modal (z-index above form-guard / sidebar layers) -->
<div id="archive-storage-overlay" role="dialog" aria-modal="true" aria-labelledby="archive-services-title" onclick="if (event.target === this) window.closeArchiveModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10090;align-items:center;justify-content:center;padding:16px;pointer-events:auto;">
    <div onclick="event.stopPropagation()" style="background:white;border-radius:16px;width:100%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 25px 50px rgba(0,0,0,0.25);pointer-events:auto;">
        <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
            <h3 id="archive-services-title" style="font-size:18px;font-weight:700;margin:0;">Archived Services</h3>
            <button type="button" onclick="window.closeArchiveModal()" style="background:none;border:none;cursor:pointer;color:#9ca3af;">✕</button>
        </div>
        <div style="padding:0;overflow-y:auto;flex:1;">
            <div id="archived-services-container" style="min-height:160px;padding:16px;">
                <p style="text-align:center;color:#9ca3af;">Loading…</p>
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid #e5e7eb;text-align:right;">
            <button type="button" class="btn-secondary" onclick="window.closeArchiveModal()">Close</button>
        </div>
    </div>
</div>

<script>
window.PF_DEFAULT_SERVICE_MODAL_TEXT = <?php echo json_encode(printflow_default_customer_service_modal_text(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
/* var: Turbo re-runs inline scripts; let/const would throw "already been declared". */
var activeSort = '<?php echo htmlspecialchars($sort_by); ?>';
var searchDebounceTimer = null;
var _serviceStatusForm = null;
var _serviceStatusButtonName = null;

function filterPanel() {
    return {
        sortOpen: false,
        filterOpen: false,
        activeSort: activeSort,
        get hasActiveFilters() {
            return document.getElementById('fp_date_from')?.value ||
                document.getElementById('fp_date_to')?.value ||
                document.getElementById('fp_category')?.value ||
                document.getElementById('fp_status')?.value ||
                document.getElementById('fp_search')?.value;
        }
    };
}

function buildFilterURL(page = 1) {
    const params = new URLSearchParams();
    params.set('page', page);
    const df = document.getElementById('fp_date_from')?.value; if (df) params.set('date_from', df);
    const dt = document.getElementById('fp_date_to')?.value; if (dt) params.set('date_to', dt);
    const cat = document.getElementById('fp_category')?.value; if (cat) params.set('category', cat);
    const st = document.getElementById('fp_status')?.value; if (st) params.set('status', st);
    const s = document.getElementById('fp_search')?.value; if (s) params.set('search', s);
    if (activeSort !== 'newest') params.set('sort', activeSort);
    return '?' + params.toString();
}

function fetchUpdatedTable(page = 1) {
    fetch(buildFilterURL(page) + '&ajax=1')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const wrap = document.getElementById('servicesTableContainer');
            if (wrap) {
                wrap.innerHTML = data.table + '<div id="servicesPagination">' + data.pagination + '</div>';
                if (typeof Alpine !== 'undefined' && typeof Alpine.initTree === 'function') {
                    try {
                        Alpine.initTree(wrap);
                    } catch (e) {
                        console.error(e);
                    }
                }
            }
            const cont = document.getElementById('filterBadgeContainer');
            if (cont) cont.innerHTML = data.badge > 0 ? '<span class="filter-badge">' + data.badge + '</span>' : '';
            history.replaceState(null, '', buildFilterURL(page));
        })
        .catch(console.error);
}

function applyFilters(reset = false) {
    if (reset) {
        ['fp_date_from', 'fp_date_to', 'fp_category', 'fp_status', 'fp_search'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        activeSort = 'newest';
    }
    fetchUpdatedTable(1);
}

function resetFilterField(fields) {
    const map = { date_from: 'fp_date_from', date_to: 'fp_date_to', category: 'fp_category', status: 'fp_status', search: 'fp_search' };
    fields.forEach(f => {
        const el = document.getElementById(map[f] || f);
        if (el) el.value = '';
    });
    fetchUpdatedTable(1);
}

function applySortFilter(sortKey) {
    activeSort = sortKey;
    fetchUpdatedTable(1);
    const alpineEl = document.querySelector('[x-data="filterPanel()"]');
    if (alpineEl && alpineEl._x_dataStack) {
        alpineEl._x_dataStack[0].activeSort = sortKey;
        alpineEl._x_dataStack[0].sortOpen = false;
    }
}

function printflowInitServicesPage() {
    if (!document.getElementById('servicesListHeader')) return;

    // Filter and Search
    ['fp_date_from', 'fp_date_to', 'fp_category', 'fp_status'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => fetchUpdatedTable());
    });
    const searchInput = document.getElementById('fp_search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => fetchUpdatedTable(), 450);
        });
    }

    // Form submit guard
    document.getElementById('service-form')?.addEventListener('submit', function () {
        const btn = document.getElementById('modal-submit-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    });

    /* #servicesTableContainer has no x-data; turbo-init initTree(.main-content) already walked it. */
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', printflowInitServicesPage);
} else {
    printflowInitServicesPage();
}
document.addEventListener('printflow:page-init', printflowInitServicesPage);

function openModal(mode, svc) {
    const overlay = document.getElementById('service-modal-overlay');
    const title = document.getElementById('modal-title');
    const modeInput = document.getElementById('modal-mode-input');
    const submitBtn = document.getElementById('modal-submit-btn');
    const form = document.getElementById('service-form');
    if (!overlay || !title || !modeInput || !submitBtn || !form) {
        console.warn('openModal: service modal markup not in DOM yet.');
        return;
    }
    form.reset();

    if (mode === 'edit' && svc) {
        title.textContent = 'Edit Service';
        modeInput.name = 'update_service';
        submitBtn.textContent = 'Save Changes';
        document.getElementById('modal-service-id').value = svc.service_id || '';
        document.getElementById('modal-name').value = svc.name || '';
        document.getElementById('modal-category').value = svc.category || '';
        document.getElementById('modal-description').value = svc.description || '';
        const cm = svc.customer_modal_text;
        document.getElementById('modal-customer-modal-text').value = (cm !== undefined && cm !== null && String(cm).trim() !== '') ? String(cm) : (window.PF_DEFAULT_SERVICE_MODAL_TEXT || '');
        document.getElementById('modal-status').value = (svc.status === 'Deactivated') ? 'Deactivated' : 'Activated';
    } else {
        title.textContent = 'Add Service';
        modeInput.name = 'create_service';
        submitBtn.textContent = 'Save Service';
        document.getElementById('modal-service-id').value = '';
        document.getElementById('modal-status').value = 'Activated';
        document.getElementById('modal-customer-modal-text').value = window.PF_DEFAULT_SERVICE_MODAL_TEXT || '';
    }
    submitBtn.disabled = false;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    document.getElementById('modal-name').focus();
    if (typeof window.printflowServiceFormValidationRun === 'function') {
        window.printflowServiceFormValidationRun();
    }
    try {
        document.dispatchEvent(new CustomEvent('pf-service-modal-shown'));
    } catch (e) { /* ignore */ }
}

function closeServiceModal() {
    document.getElementById('service-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
    const btn = document.getElementById('modal-submit-btn');
    if (btn) { btn.disabled = false; btn.textContent = document.getElementById('modal-mode-input').name === 'update_service' ? 'Save Changes' : 'Save Service'; }
}

function handleOverlayClick(e) {
    if (e.target.id === 'service-modal-overlay') closeServiceModal();
}

function openViewModal(svc) {
    document.getElementById('view-name').textContent = svc.name || '—';
    document.getElementById('view-category').textContent = svc.category || '—';
    const st = svc.status || '';
    document.getElementById('view-status').textContent = st === 'Activated' ? 'Active' : (st === 'Deactivated' ? 'Inactive' : st);
    document.getElementById('view-description').textContent = svc.description || '—';
    const cm = svc.customer_modal_text;
    document.getElementById('view-customer-modal-text').textContent =
        (cm !== undefined && cm !== null && String(cm).trim() !== '') ? String(cm) : (window.PF_DEFAULT_SERVICE_MODAL_TEXT || '—');
    document.getElementById('view-service-modal-overlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('view-service-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
}

function handleViewOverlayClick(e) {
    if (e.target.id === 'view-service-modal-overlay') closeViewModal();
}

function showServiceStatusModal(event, form) {
    if (event) event.preventDefault();
    const action = form.getAttribute('data-action') || 'proceed';
    const svcName = form.getAttribute('data-service-name') || 'this service';
    _serviceStatusForm = form;
    const btn = form.querySelector('button[type="submit"]');
    _serviceStatusButtonName = btn ? btn.getAttribute('name') : null;

    if (typeof window.closeArchiveModal === 'function') window.closeArchiveModal();
    closeServiceModal();
    closeViewModal();

    document.getElementById('serviceStatusConfirmTitle').textContent = 'Confirm ' + action;
    document.getElementById('serviceStatusConfirmText').innerHTML = 'Are you sure you want to <strong>' + action.toLowerCase() + '</strong> <strong style="color:#111827;">' + svcName.replace(/</g, '&lt;') + '</strong>?';

    let msg = '';
    if (action === 'Deactivate') msg = 'This service will be marked inactive and hidden from default customer flows.';
    else if (action === 'Activate') msg = 'This service will be active again.';
    else if (action === 'Archive') msg = 'The service moves to archive storage; you can restore it later.';
    else if (action === 'Restore') msg = 'The service returns to the main list as Active.';
    else if (action === 'Delete Permanently') msg = 'This cannot be undone.';
    document.getElementById('serviceStatusInfoText').textContent = msg;

    const m = document.getElementById('serviceStatusConfirmModal');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeServiceStatusModal() {
    document.getElementById('serviceStatusConfirmModal').style.display = 'none';
    document.body.style.overflow = '';
    _serviceStatusForm = null;
    _serviceStatusButtonName = null;
}

document.getElementById('serviceStatusConfirmModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeServiceStatusModal();
});

document.getElementById('serviceStatusConfirmCancel')?.addEventListener('click', closeServiceStatusModal);
document.getElementById('serviceStatusConfirmOk')?.addEventListener('click', function () {
    if (_serviceStatusForm) {
        if (_serviceStatusButtonName) {
            const hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = _serviceStatusButtonName;
            hid.value = '1';
            _serviceStatusForm.appendChild(hid);
        }
        _serviceStatusForm.submit();
    }
    closeServiceStatusModal();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        var archiveOv = document.getElementById('archive-storage-overlay');
        if (archiveOv && archiveOv.style.display === 'flex') {
            if (typeof window.closeArchiveModal === 'function') window.closeArchiveModal();
            return;
        }
        if (document.getElementById('serviceStatusConfirmModal').style.display === 'flex') closeServiceStatusModal();
        else { closeServiceModal(); closeViewModal(); }
    }
});

window.openArchiveModal = function openArchiveModal() {
    var el = document.getElementById('archive-storage-overlay');
    if (!el) return;
    el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    const c = document.getElementById('archived-services-container');
    c.innerHTML = '<p style="text-align:center;color:#9ca3af;">Loading…</p>';
    fetch('?get_archived=1').then(r => r.json()).then(data => {
        if (data.success) c.innerHTML = data.html;
        else c.innerHTML = '<p style="color:#ef4444;text-align:center;">Failed to load.</p>';
    }).catch(() => { c.innerHTML = '<p style="color:#ef4444;text-align:center;">Error loading archive.</p>'; });
};

window.closeArchiveModal = function closeArchiveModal() {
    var el = document.getElementById('archive-storage-overlay');
    if (el) el.style.display = 'none';
    document.body.style.overflow = '';
};

// Page-specific initialization is now handled above via printflowInitServicesPage.
</script>
<script src="/printflow/public/assets/js/service-form-validation.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
