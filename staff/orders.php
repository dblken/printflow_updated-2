<?php
/**
 * Staff Orders Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$branch_ctx    = init_branch_context(false);
$staffBranchId = (int)$branch_ctx['selected_branch_id'];
$branchName    = $branch_ctx['branch_name'];

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['status'];
        $staff_id = get_user_id();

        // Fetch current status and customer ID (only if order is in this branch)
        $order_info = db_query(
            "SELECT customer_id, status FROM orders WHERE order_id = ? AND branch_id = ?",
            'ii',
            [$order_id, $staffBranchId]
        );
        
        if (!empty($order_info)) {
            $current_status = $order_info[0]['status'];
            $customer_id = $order_info[0]['customer_id'];

            // Only proceed if the status is actually changing
            if ($current_status !== $new_status) {
                // Use the centralized update_order_status logic
                $success = db_execute("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?", 'si', [$new_status, $order_id]);

                if ($success) {
                    // Log activity
                    log_activity($staff_id, 'Order Status Update', "Updated Order #{$order_id} to {$new_status}");

                    // Notify customer
                    if ($new_status === 'To Pay') {
                        $msg = "💳 Your order #{$order_id} has been approved! Please prepare your payment upon pickup.";
                    } else {
                        $msg = "Your order #{$order_id} status has been updated to: {$new_status}";
                    }
                    
                    // Pass order_id as data_id for shortcut linking
                    create_notification($customer_id, 'Customer', $msg, 'Order', false, false, $order_id);
                    add_order_system_message($order_id, $msg);
                }
            } else {
                // Status is already the same, consider it a "soft" success
                $success = true;
            }
        } else {
            $success = false;
        }

        if ($success) {
            // If AJAX, return JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'new_status' => $new_status]);
                exit;
            }

            redirect('/printflow/staff/orders.php?success=1');
        } else {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database update failed']);
                exit;
            }
            redirect('/printflow/staff/orders.php?error=1');
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from_filter = $_GET['date_from'] ?? '';
$date_to_filter        = $_GET['date_to']   ?? '';
$customer_filter       = $_GET['customer']  ?? '';
$product_type_filter   = $_GET['product_type']   ?? '';
$payment_status_filter = $_GET['payment_status'] ?? '';
$min_price_filter      = $_GET['min_price']      ?? '';
$max_price_filter      = $_GET['max_price']      ?? '';
$sort_by               = $_GET['sort']           ?? 'newest';

$active_filters = [];
if ($status_filter !== '')         $active_filters['status']         = $status_filter;
if ($date_from_filter !== '')      $active_filters['date_from']      = $date_from_filter;
if ($date_to_filter !== '')        $active_filters['date_to']        = $date_to_filter;
if ($customer_filter !== '')       $active_filters['customer']       = $customer_filter;
if ($product_type_filter !== '')   $active_filters['product_type']   = $product_type_filter;
if ($payment_status_filter !== '') $active_filters['payment_status'] = $payment_status_filter;
if ($min_price_filter !== '')      $active_filters['min_price']      = $min_price_filter;
if ($max_price_filter !== '')      $active_filters['max_price']      = $max_price_filter;
if ($sort_by !== 'newest')         $active_filters['sort']           = $sort_by;

$sql_conditions = " AND o.order_type = 'product'";
$params = [];
$types = '';

// Apply branch filtering
$sql_conditions .= branch_where('o', $staffBranchId, $types, $params);

if ($status_filter !== '') {
    if ($status_filter === 'Pending') {
        $sql_conditions .= " AND (o.status IN ('Pending', 'Pending Review', 'Pending Approval', 'To Pay', 'To Verify'))";
    } elseif ($status_filter === 'Ready for Pickup') {
        // Include legacy production statuses so they appear in TO PICK UP tab
        $sql_conditions .= " AND (o.status IN ('Ready for Pickup', 'Processing', 'In Production', 'Printing', 'Approved Design'))";
    } else {
        $sql_conditions .= " AND o.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
}
if ($date_from_filter !== '') {
    $sql_conditions .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from_filter;
    $types .= 's';
}
if ($date_to_filter !== '') {
    $sql_conditions .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to_filter;
    $types .= 's';
}
if ($min_price_filter !== '') {
    $sql_conditions .= " AND o.total_amount >= ?";
    $params[] = (float)$min_price_filter;
    $types .= 'd';
}
if ($max_price_filter !== '') {
    $sql_conditions .= " AND o.total_amount <= ?";
    $params[] = (float)$max_price_filter;
    $types .= 'd';
}
if ($customer_filter !== '') {
    $sql_conditions .= " AND (o.order_id LIKE ? OR CONCAT_WS(' ', c.first_name, c.last_name) LIKE ? OR (o.customer_id IS NULL AND 'Walk-in Customer (Guest)' LIKE ?))";
    $like = '%' . $customer_filter . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}
if ($payment_status_filter !== '') {
    $sql_conditions .= " AND o.payment_status = ?";
    $params[] = $payment_status_filter;
    $types .= 's';
}
if ($product_type_filter !== '') {
    $sql_conditions .= " AND EXISTS (SELECT 1 FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id AND (p.name LIKE ? OR oi.customization_data LIKE ?))";
    $like = '%' . $product_type_filter . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$sql = "SELECT o.*, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), 'Walk-in Customer (Guest)') as customer_name,
        (SELECT GROUP_CONCAT(COALESCE(p.name, 'Custom Product') SEPARATOR ', ') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as item_names,
        (SELECT oi.customization_data FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization
        FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id WHERE 1=1" . $sql_conditions;

$count_sql = "SELECT COUNT(*) as total FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id WHERE 1=1" . $sql_conditions;

// Pagination settings
$items_per_page = 15;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$total_result = db_query($count_sql, $types, $params);
$total_items = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

$sort_clause = match($sort_by) {
    'oldest' => " ORDER BY o.order_date ASC",
    'az'     => " ORDER BY customer_name ASC",
    'za'     => " ORDER BY customer_name DESC",
    default  => " ORDER BY o.order_date DESC"
};

$sql .= $sort_clause . " LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$orders = db_query($sql, $types, $params);

// Get KPI statistics (branch-specific)
// Note: o.order_type = 'product' filter from line 108 is preserved in $sql_conditions
$kpi_conditions = " AND o.order_type = 'product'";
$kpi_types = '';
$kpi_params = [];
$kpi_conditions .= branch_where('o', $staffBranchId, $kpi_types, $kpi_params);

$total_count      = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status NOT IN ('Processing', 'In Production', 'Printing', 'Approved Design') AND 1=1 {$kpi_conditions}", $kpi_types ?: null, $kpi_params ?: null)[0]['count'] ?? 0;
$pending_count    = db_query("SELECT COUNT(*) as count FROM orders o WHERE (o.status IN ('Pending', 'Pending Review', 'Pending Approval', 'To Pay', 'To Verify')) {$kpi_conditions}", $kpi_types ?: null, $kpi_params ?: null)[0]['count'] ?? 0;
$ready_count      = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status IN ('Ready for Pickup', 'Processing', 'In Production', 'Printing', 'Approved Design') {$kpi_conditions}", $kpi_types ?: null, $kpi_params ?: null)[0]['count'] ?? 0;
$completed_count  = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Completed' {$kpi_conditions}", $kpi_types ?: null, $kpi_params ?: null)[0]['count'] ?? 0;
$cancelled_count  = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Cancelled' {$kpi_conditions}", $kpi_types ?: null, $kpi_params ?: null)[0]['count'] ?? 0;
$approved_count   = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Approved' {$kpi_conditions}", $kpi_types ?: null, $kpi_params ?: null)[0]['count'] ?? 0;
$topay_count      = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'To Pay' {$kpi_conditions}", $kpi_types ?: null, $kpi_params ?: null)[0]['count'] ?? 0;

$all_counts = [
    'ALL'              => $total_count,
    'Pending'          => $pending_count,
    'Ready for Pickup' => $ready_count,
    'Completed'        => $completed_count,
    'Cancelled'        => $cancelled_count
];

// Handle specific AJAX request for drawing the table
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_start();
    if (empty($orders)) {
        echo '<tr><td colspan="6" style="text-align:center; padding: 48px; color:#64748b; font-size:14px; font-weight:600;"><div style="font-size:24px; margin-bottom:8px;">📁</div>No orders found matching your filters.</td></tr>';
    } else {
        foreach ($orders as $order) {
            ?>
            <tr class="staff-order-row" onclick="openOrderModal(<?php echo $order['order_id']; ?>)">
                <td>
                    <div class="order-info-cell">
                        <div class="order-id-wrap">
                            #<?php echo $order['order_id']; ?>
                            <?php if (($order['order_source'] ?? '') === 'pos'): ?>
                                <span style="display:inline-flex;align-items:center;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:800;background:#fef3c7;color:#92400e;margin-left:4px;border:1px solid #fde68a;">POS</span>
                            <?php endif; ?>
                            <?php 
                            $unread = get_unread_chat_count($order['order_id'], 'User');
                            if ($unread > 0): 
                            ?>
                                <span style="background: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800; animation: pulse 2s infinite;" title="<?php echo $unread; ?> new messages from customer">
                                    <?php echo $unread; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($order['item_names'])): ?>
                            <div class="order-items-sub">
                                <?php 
                                    $display_items = $order['item_names'];
                                    if ($display_items === 'Custom Product' || $display_items === 'Custom Order') {
                                        $display_items = get_service_name_from_customization($order['first_item_customization'] ?? '{}', $display_items);
                                        $c_json = json_decode($order['first_item_customization'] ?? '{}', true);
                                        if (!empty($c_json['product_type']) && $c_json['product_type'] !== $display_items) {
                                            $display_items .= " (" . $c_json['product_type'] . ")";
                                        }
                                    }
                                    echo htmlspecialchars(strlen($display_items) > 100 ? substr($display_items, 0, 100) . '...' : $display_items); 
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="color: #334155; font-weight: 500;"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                <td style="color: #64748b; font-size: 13px;"><?php echo format_date($order['order_date']); ?></td>
                <td style="font-weight: 700; color: #1e293b;"><?php echo format_currency($order['total_amount']); ?></td>
                <td>
                    <?php
                    // Normalize status for product orders — production statuses should display as 'Ready for Pickup'
                    $display_order_status = $order['status'];
                    $product_production_statuses = ['Processing', 'In Production', 'Printing', 'Approved Design'];
                    if (in_array($display_order_status, $product_production_statuses)) {
                        $display_order_status = 'Ready for Pickup';
                    }
                    echo status_badge($display_order_status, 'order'); ?>
                    <?php if (($order['design_status'] ?? '') === 'Revision Submitted'): ?>
                        <div style="margin-top: 6px;">
                            <?php echo status_badge('Revision Submitted', 'order'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($order['payment_status'])): ?>
                        <div style="margin-top:6px;"><?php echo status_badge($order['payment_status'], 'payment'); ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-cell">
                        <button
                            onclick="event.stopPropagation(); window.openStaffOrderManage(<?php echo $order['order_id']; ?>, '<?php echo addslashes($order['status']); ?>');"
                            class="btn-staff-action btn-staff-action-emerald"
                        >
                            Manage
                        </button>
                        <a href="/printflow/staff/chats.php?order_id=<?php echo $order['order_id']; ?>"
                            onclick="event.stopPropagation();"
                            class="btn-staff-action btn-staff-action-indigo"
                        >
                            Message
                        </a>
                    </div>
                </td>
            </tr>
            <?php
        }
    }
    $tbody = ob_get_clean();
    $pagination = get_pagination_links($current_page, $total_pages, $active_filters);
    
    header('Content-Type: application/json');
    echo json_encode([
        'tbody'      => $tbody, 
        'pagination' => $pagination, 
        'total'      => number_format($total_items),
        'counts'     => $all_counts,
        'badge'      => count(array_filter([$status_filter, $customer_filter, $date_from_filter, $date_to_filter, $product_type_filter, $payment_status_filter, $min_price_filter, $max_price_filter], function($v) { return $v !== null && $v !== ''; }))
    ]);
    exit;
}

$page_title = 'Orders - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="turbo-visit-control" content="reload">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <link rel="stylesheet" href="/printflow/public/assets/css/chat.css">
    <style>
        /* ── Tabs for Status Filtering ─── */
        .pf-custom-tabs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 16px;
        }
        .pill-tab { 
            position: relative;
            padding: 8px 16px; 
            font-weight: 700; 
            font-size: 11px; 
            font-family: inherit;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b; 
            border-radius: 9999px; 
            transition: all 0.2s; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
            user-select: none;
        }
        .pill-tab:hover { background: #f1f5f9; color: #1e293b; border-color: #cbd5e1; }
        .pill-tab.active { background: #06A1A1; color: white; border-color: #06A1A1; box-shadow: 0 4px 12px rgba(6,161,161,0.25); }
        .tab-count { 
            background: rgba(0,0,0,0.1); 
            color: inherit; 
            font-size: 10px; 
            padding: 2px 7px; 
            border-radius: 9999px; 
            font-weight: 700;
        }
        .pill-tab.active .tab-count { background: rgba(255,255,255,0.25); color: white; }

        .filter-date-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .filter-date-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
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
        .filter-select:focus { outline: none; border-color: #0d9488; }

        .filter-search-wrap { position: relative; }
        .filter-search-wrap svg {
            position: absolute;
            left: 9px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }
        .filter-search-input {
            width: 100%;
            height: 38px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            padding: 0 12px;
            color: #1f2937;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .filter-search-input:focus { outline: none; border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }
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
        }
        .filter-btn-reset:hover { background: #f9fafb; border-color: #d1d5db; }

        /* ── Sort Dropdown ─── */
        .sort-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            min-width: 200px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 200;
            padding: 6px 0;
            overflow: hidden;
        }
        .sort-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
            transition: background 0.1s;
        }
        .sort-option:hover { background: #f9fafb; }
        .sort-option.selected { color: #0d9488; font-weight: 600; background: #f0fdfa; }
        .sort-option .check { margin-left: auto; color: #0d9488; }

        /* ── Active filter badge ─── */
        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #0d9488;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
        }

        /* ── Table improvements ─── */
        .staff-orders-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .staff-orders-table th {
            padding: 12px 16px !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #6b7280;
            text-align: left;
            border-bottom: 2px solid #e5e7eb !important;
            background: #f8fafc !important;
            white-space: nowrap;
        }
        .staff-orders-table td {
            padding: 16px 12px !important;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .staff-orders-table tbody tr {
            cursor: pointer;
            transition: background 0.1s;
        }
        .staff-orders-table tbody tr:hover td { background: #f9fafb !important; }

        .action-cell { display: flex; justify-content: flex-end; gap: 4px; }
        .order-info-cell { display: flex; flex-direction: column; gap: 4px; }
        .order-id-wrap { font-weight: 700; color: #1e293b; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .order-items-sub { font-size: 12px; color: #64748b; font-weight: 600; }

        /* ── Order Detail Modal ─────────────────────────────────── */
        #orderModal {
            position: fixed; inset: 0; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            opacity: 0; pointer-events: none;
            transition: opacity 0.25s ease;
        }
        #orderModal.open { opacity: 1; pointer-events: all; }

        .om-backdrop {
            position: absolute; inset: 0;
            background: transparent;
            backdrop-filter: none;
            transition: opacity 0.25s ease;
        }

        .om-panel {
            position: relative; z-index: 1;
            background: #fff;
            border-radius: 20px;
            width: 100%; max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            transform: translateY(24px) scale(0.97);
            transition: transform 0.3s cubic-bezier(.34,1.56,.64,1), opacity 0.25s ease;
            opacity: 0;
        }
        #orderModal.open .om-panel {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .om-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 24px 28px 20px;
            border-bottom: 1px solid #f1f5f9;
            position: sticky; top: 0; background: #fff; border-radius: 20px 20px 0 0; z-index: 2;
        }
        .om-title { font-size: 1.35rem; font-weight: 800; color: #0f172a; }
        .om-subtitle { font-size: 0.78rem; color: #94a3b8; margin-top: 2px; }
        .om-close {
            width: 36px; height: 36px; border-radius: 50%;
            border: none; background: #f1f5f9; color: #64748b;
            cursor: pointer; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;
            transition: background 0.15s, color 0.15s;
        }
        .om-close:hover { background: #e2e8f0; color: #0f172a; }

        .om-body { padding: 24px 28px 28px; }
        .om-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 700px) { .om-grid { grid-template-columns: 1fr; } }

        .om-card {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 14px; padding: 20px;
        }
        .om-card-title {
            font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.07em; color: #94a3b8; margin-bottom: 14px;
        }
        .om-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13.5px;
        }
        .om-row:last-child { border-bottom: none; }
        .om-label { color: #6b7280; }
        .om-value { font-weight: 600; color: #1e293b; text-align: right; }

        .om-notes {
            margin-top: 14px; padding: 14px 16px;
            background: linear-gradient(135deg,#fffbeb,#fef3c7);
            border: 1px solid #fde68a; border-radius: 12px;
            max-height: 180px; overflow-y: auto;
        }
        .om-notes-title { font-size: 12px; font-weight: 800; color: #92400e; margin-bottom: 6px; }
        .om-notes-text { font-size: 13px; color: #b45309; line-height: 1.6; overflow-wrap: anywhere; word-break: break-word; }

        .om-cust-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .om-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg,#667eea,#764ba2);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 16px; flex-shrink: 0;
        }



        /* Items table */
        .om-items-section { margin-top: 20px; }
        .om-items-title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; margin-bottom: 12px; }
        .om-items-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .om-items-table th {
            text-align: left; padding: 8px 10px;
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: #94a3b8;
            border-bottom: 2px solid #e2e8f0;
        }
        .om-items-table td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .om-items-table tr:last-child td { border-bottom: none; }
        .om-items-total td { border-top: 2px solid #e2e8f0 !important; font-weight: 700; }

        /* Design image */
        .om-design-wrap { margin-top: 10px; }
        .om-design-img {
            max-width: 140px; border-radius: 8px; border: 2px solid #e2e8f0;
            cursor: zoom-in; transition: transform 0.2s, box-shadow 0.2s;
        }
        .om-design-img:hover { transform: scale(1.04); box-shadow: 0 8px 24px rgba(0,0,0,0.15); }

        /* Customs chips */
        .om-custom-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
        .om-chip {
            background: #e0e7ff; color: #4338ca;
            border-radius: 99px; padding: 2px 10px;
            font-size: 11px; font-weight: 600;
        }

        /* Loader */
        .om-loader { text-align: center; padding: 64px 0; }
        .om-spinner {
            width: 40px; height: 40px; border-radius: 50%;
            border: 3px solid #e2e8f0; border-top-color: #06A1A1;
            animation: om-spin 0.7s linear infinite; margin: 0 auto 12px;
        }
        @keyframes om-spin { to { transform: rotate(360deg); } }

        /* Alert flash inside modal */
        .om-alert { border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; }
        .om-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .om-alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        /* Customer orders list */
        .om-cust-orders { margin-top: 14px; }
        .om-co-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #f1f5f9; font-size: 12.5px; }
        .om-co-row:last-child { border-bottom: none; }

        /* Status badge replicated in JS */
        .badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .badge-purple { background: #ede9fe; color: #5b21b6; }

        /* Table hover + clickable rows */
        .staff-orders-table tbody tr { transition: background 0.1s; }
        .staff-orders-table tbody tr:hover td { background: #f9fafb; }

        /* ── Centered Status Overlay ───────────────────────── */
        .om-status-overlay {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            z-index: 100; pointer-events: none; opacity: 0;
            transition: opacity 0.3s ease;
        }
        .om-status-overlay.active { opacity: 1; pointer-events: all; }
        
        .om-status-toast {
            background: rgba(15, 23, 42, 0.9);
            color: #fff; padding: 16px 24px; border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            display: flex; flex-direction: column; align-items: center; gap: 12px;
            transform: scale(0.9); transition: transform 0.3s cubic-bezier(.34,1.56,.64,1);
            max-width: 280px; text-align: center;
        }
        .om-status-overlay.active .om-status-toast { transform: scale(1); }
        .om-status-toast-icon { font-size: 2rem; }
        .om-status-toast-msg { font-size: 14px; font-weight: 600; line-height: 1.4; }
    </style>
    <script>
    /* ═══════════════════════════════════════════════════════
       Staff Orders Page — All functions defined in <head>
       so they are available before any onclick fires,
       regardless of Turbo Drive full vs partial navigation.
    ═══════════════════════════════════════════════════════ */

    // ── Navigate without Turbo Drive interception ────────
    function openStaffOrderManage(orderId, status = '') {
        // Always open the modal for all product order statuses
        openOrderModal(orderId);
    }

    // ── Status badge helper ──────────────────────────────
    function statusBadge(val) {
        var map = {
            'Completed':             'background: #dcfce7; color: #166534;',
            'Pending':               'background: #fef3c7; color: #92400e;',
            'Pending Review':        'background: #fef3c7; color: #92400e;',
            'Approved':              'background: #dbeafe; color: #1e40af;',
            'To Pay':                'background: #dbeafe; color: #1e40af;',
            'To Verify':             'background: #fef9c3; color: #854d0e;',
            'Downpayment Submitted': 'background: #fce7f3; color: #be185d;',
            'Pending Verification':  'background: #fef9c3; color: #854d0e;',
            'Processing':            'background: #e0e7ff; color: #4338ca;',
            'In Production':         'background: #cffafe; color: #0891b2;',
            'Printing':              'background: #cffafe; color: #0891b2;',
            'For Revision':          'background: #ffe4e6; color: #b91c1c;',
            'Revision Submitted':    'background: #fef3c7; color: #92400e; border: 1px solid #ffe58f;',
            'Ready for Pickup':      'background: #dcfce7; color: #15803d;',
            'Cancelled':             'background: #fee2e2; color: #991b1b;',
            'Paid':                  'background: #dcfce7; color: #166534;',
            'Unpaid':                'background: #fee2e2; color: #991b1b;',
            'Partially Paid':        'background: #fef3c7; color: #92400e;',
            'Partial':               'background: #fef3c7; color: #92400e;',
            'To Rate':               'background: #f3e8ff; color: #6b21a8;',
            'Rated':                 'background: #f3e8ff; color: #6b21a8;'
        };
        var style = map[val] || 'background: #F3F4F6; color: #374151;';
        var display = val;
        if (['Pending', 'Pending Review', 'Pending Approval', 'To Pay', 'To Verify'].includes(val)) display = 'TO VERIFY';
        else if (val === 'Ready for Pickup') display = 'TO PICK UP';
        else if (val === 'Completed') display = 'COMPLETED';
        else if (val === 'Cancelled') display = 'CANCELLED';

        return '<span class="px-3 py-1 text-xs rounded-full" style="' + style + ' display: inline-block; white-space: nowrap; font-weight: 700; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">' + display + '</span>';
    }

    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;');
    }

    function formatCurrency(val) {
        return '₱' + parseFloat(val).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    function toggleAdvancedFilters() {
        var adv = document.getElementById('advancedFilters');
        if (!adv) return;
        if (adv.style.display === 'none') {
            adv.style.display = 'grid';
        } else {
            adv.style.display = 'none';
        }
    }

    // ── AJAX Table Updates ───────────────────────────────
    var searchDebounceTimer = null;

    function buildFilterURL(overrides = {}, isAjax = false) {
        const params = new URLSearchParams(window.location.search);
        const fields = {
            status:         () => document.getElementById('fp_status')?.value         || '',
            customer:       () => document.getElementById('fp_customer')?.value       || '',
            date_from:      () => document.getElementById('fp_date_from')?.value      || '',
            date_to:        () => document.getElementById('fp_date_to')?.value        || '',
            product_type:   () => document.getElementById('fp_product_type')?.value   || '',
            payment_status: () => document.getElementById('fp_payment_status')?.value || '',
            min_price:      () => document.getElementById('fp_min_price')?.value      || '',
            max_price:      () => document.getElementById('fp_max_price')?.value      || '',
        };
        for (const [key, getter] of Object.entries(fields)) {
            let val = (overrides[key] !== undefined) ? overrides[key] : getter();
            if (val) params.set(key, val);
            else params.delete(key);
        }
        if (overrides.sort !== undefined) {
            if (overrides.sort && overrides.sort !== 'newest') params.set('sort', overrides.sort);
            else params.delete('sort');
        }
        if (isAjax) params.set('ajax', '1');
        else params.delete('ajax');
        params.delete('page');
        return window.location.pathname + '?' + params.toString();
    }

    async function fetchUpdatedTable(overrides = {}) {
        const url = buildFilterURL(overrides, true);
        const container = document.querySelector('.staff-orders-table tbody');
        if (!container) return;

        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';

        try {
            const resp = await fetch(url);
            const data = await resp.json();
            
            container.innerHTML = data.tbody;
            
            const pag = document.querySelector('.pagination-container');
            if (pag && data.pagination) pag.outerHTML = data.pagination;
            
            const bc = document.getElementById('filterBadgeContainer');
            if (bc) bc.innerHTML = data.badge > 0 ? `<span class="filter-badge">${data.badge}</span>` : '';
            
            const countEl = document.getElementById('totalOrdersCount');
            if (countEl) countEl.textContent = data.total;

            // Update Alpine tab counts
            const dashboardEl = document.querySelector('[x-data^="ordersPage"]');
            if (dashboardEl && dashboardEl.__x && data.counts) {
                dashboardEl.__x.$data.updateCounts(data.counts);
            }

            window.dispatchEvent(new CustomEvent('filter-badge-update', { detail: { badge: data.badge } }));
            
            const displayUrl = buildFilterURL(overrides, false);
            window.history.replaceState({ path: displayUrl }, '', displayUrl);
        } catch (e) { console.error('Error updating table:', e); }
        
        container.style.opacity = '1';
        container.style.pointerEvents = 'all';
    }

    function applyFilters(resetAll = false) {
        if (resetAll) {
            window.location.href = window.location.pathname;
        } else { fetchUpdatedTable(); }
    }

    function applySortFilter(sortKey) {
        window.dispatchEvent(new CustomEvent('sort-changed', { detail: { sortKey } }));
        fetchUpdatedTable({ sort: sortKey });
    }

    function resetFilterField(fields) {
        fields.forEach(f => {
            const el = document.getElementById('fp_' + f);
            if (el) el.value = '';
        });
        fetchUpdatedTable();
    }

    function ordersPage() {
        return {
            filterOpen: false,
            sortOpen:   false,
            activeSort: '<?php echo $sort_by; ?>',
            hasActiveFilters: <?php echo count($active_filters) > 0 ? 'true' : 'false'; ?>,
            activeTab: '<?php echo $status_filter ?: 'ALL'; ?>',
            tabCounts: <?php echo json_encode($all_counts); ?>,
            statusTabs: {
                'ALL':              'All',
                'Pending':          'TO VERIFY',
                'Ready for Pickup': 'TO PICK UP',
                'Completed':        'COMPLETED'
            },
            getProfileImage(image) {
                if (!image || image === 'null' || image === 'undefined') {
                    return '/printflow/public/assets/uploads/profiles/default.png';
                }
                if (typeof image !== 'string') return '/printflow/public/assets/uploads/profiles/default.png';
                if (image.startsWith('/') || image.startsWith('http')) return image;
                return '/printflow/public/assets/uploads/profiles/' + image;
            },
            
            init() {
                window.addEventListener('filter-badge-update', e => { this.hasActiveFilters = (e.detail.badge > 0); });
                window.addEventListener('sort-changed', e => { this.activeSort = e.detail.sortKey; this.sortOpen = false; });
                
                // Watch for status select changes to sync tab
                const statusEl = document.getElementById('fp_status');
                if (statusEl) {
                    statusEl.addEventListener('change', (e) => {
                        this.activeTab = e.target.value || 'ALL';
                    });
                }

                // Add debounced search
                const searchEl = document.getElementById('fp_customer');
                if (searchEl) {
                    searchEl.addEventListener('input', () => {
                        clearTimeout(searchDebounceTimer);
                        searchDebounceTimer = setTimeout(() => { applyFilters(); }, 500);
                    });
                }
            },

            switchStatusTab(key) {
                this.activeTab = key;
                const statusEl = document.getElementById('fp_status');
                if (statusEl) {
                    statusEl.value = (key === 'ALL') ? '' : key;
                    applyFilters();
                }
            },

            updateCounts(counts) {
                this.tabCounts = counts;
            }
        };
    }

    // Inside fetchUpdatedTable, update Alpine counts
    const originalFetchUpdatedTable = fetchUpdatedTable;
    fetchUpdatedTable = async function(overrides = {}) {
        const url = buildFilterURL(overrides, true);
        const container = document.querySelector('.staff-orders-table tbody');
        if (!container) return;

        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';

        try {
            const resp = await fetch(url);
            const data = await resp.json();
            
            container.innerHTML = data.tbody;
            
            const pag = document.querySelector('.pagination-container');
            if (pag && data.pagination) pag.outerHTML = data.pagination;
            
            const bc = document.getElementById('filterBadgeContainer');
            if (bc) bc.innerHTML = data.badge > 0 ? `<span class="filter-badge">${data.badge}</span>` : '';
            
            const countEl = document.getElementById('totalOrdersCount');
            if (countEl) countEl.textContent = data.total;

            // Updated bit: update Alpine tab counts
            const dashboard = document.querySelector('[x-data^="ordersPage"]').__x.$data;
            if (dashboard && data.counts) {
                dashboard.updateCounts(data.counts);
            }

            window.dispatchEvent(new CustomEvent('filter-badge-update', { detail: { badge: data.badge } }));
            
            const displayUrl = buildFilterURL(overrides, false);
            window.history.replaceState({ path: displayUrl }, '', displayUrl);
        } catch (e) { console.error('Error updating table:', e); }
        
        container.style.opacity = '1';
        container.style.pointerEvents = 'all';
    }
    window.ordersPage = ordersPage;

    // ── Open / close order modal ─────────────────────────
    var currentOrderId = null;

    function openOrderModal(orderId) {
        currentOrderId = orderId;
        var modal = document.getElementById('orderModal');
        document.getElementById('omTitle').textContent = 'Order #' + orderId;
        document.getElementById('omSubtitle').textContent = 'Loading…';
        document.getElementById('omBody').innerHTML =
            '<div class="om-loader"><div class="om-spinner"></div>' +
            '<div style="color:#94a3b8;font-size:14px;">Fetching order details…</div></div>';
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';

        fetch('/printflow/staff/get_order_data.php?id=' + orderId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) {
            var ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                return r.text().then(function(txt) {
                    console.error('Non-JSON response:', txt);
                    document.getElementById('omBody').innerHTML =
                        '<div class="om-alert om-alert-error">Server returned unexpected response (HTTP ' + r.status + '). Check console.</div>';
                    return null;
                });
            }
            return r.json();
        })
        .then(function(data) {
            if (!data) return;
            if (data.error) {
                document.getElementById('omBody').innerHTML =
                    '<div class="om-alert om-alert-error">Error: ' + data.error + '</div>';
                return;
            }
            try { renderOrderModal(data); }
            catch (err) {
                console.error('Render Error:', err);
                document.getElementById('omBody').innerHTML =
                    '<div class="om-alert om-alert-error">Rendering Error: ' + err.message + '</div>';
            }
        })
        .catch(function(err) {
            console.error('Fetch Error:', err);
            document.getElementById('omBody').innerHTML =
                '<div class="om-alert om-alert-error">Network Error: ' + err.message + '</div>';
        });
    }
    window.openOrderModal = openOrderModal;

    function closeOrderModal() {
        var modal = document.getElementById('orderModal');
        if (modal) modal.classList.remove('open');
        document.body.style.overflow = '';
        currentOrderId = null;
    }
    window.closeOrderModal = closeOrderModal;

    function showStatusOverlay(icon, msg) {
        var ov = document.getElementById('omStatusOverlay');
        if (!ov) return;
        document.getElementById('omStatusIcon').textContent = icon;
        document.getElementById('omStatusMsg').textContent = msg;
        ov.classList.add('active');
        setTimeout(function() { ov.classList.remove('active'); }, 2200);
    }

    // ── Revision modal ───────────────────────────────────
    function openRevisionModal(orderId, csrfToken) {
        document.getElementById('revOrderId').value = orderId;
        document.getElementById('revCsrfToken').value = csrfToken;
        document.getElementById('revisionModal').classList.add('open');
    }
    function closeRevisionModal() {
        document.getElementById('revisionModal').classList.remove('open');
        document.getElementById('revForm').reset();
        document.getElementById('revOtherWrapper').style.display = 'none';
    }
    function handleReasonChange(select) {
        var wrap  = document.getElementById('revOtherWrapper');
        var input = document.getElementById('revOtherInput');
        if (select.value === 'Other') {
            wrap.style.display = 'block';
            input.required = true;
        } else {
            wrap.style.display = 'none';
            input.required = false;
        }
    }

    // ── Design review actions ────────────────────────────
    async function approveDesign(orderId, csrfToken) {
        const confirmed = await pfConfirm({
            title: 'Approve Design',
            text: 'Are you sure you want to approve this design? This will notify the customer.',
            icon: '📐',
            iconBg: '#eff6ff',
            iconColor: '#3b82f6',
            confirmText: 'Yes, Approve'
        });
        if (!confirmed) return;

        var fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('csrf_token', csrfToken);
        fetch('/printflow/staff/approve_design_process.php', {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showStatusOverlay('✅', res.message);
                setTimeout(function() { openOrderModal(orderId); fetchUpdatedTable(); }, 1200);
            } else {
                alert(res.error || 'Failed to approve design');
            }
        })
        .catch(function() { alert('Network error occurred'); });
    }

    async function markOrderCompleted(orderId, csrfToken) {
        const confirmed = await pfConfirm({
            title: 'Complete Order',
            text: 'Mark this order as COMPLETED? This will deduct items from stock and finalize the order.',
            icon: '📦',
            iconBg: '#ecfdf5',
            iconColor: '#10b981',
            confirmText: 'Yes, Complete',
            confirmColor: '#059669'
        });
        if (!confirmed) return;

        var fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('status', 'Completed');
        fd.append('csrf_token', csrfToken);
        
        fetch('/printflow/staff/update_order_status_process.php', {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showStatusOverlay('🎉', res.message);
                setTimeout(function() { 
                    closeOrderModal(); 
                    fetchUpdatedTable(); 
                }, 1500);
            } else {
                alert(res.error || 'Failed to complete order');
            }
        })
        .catch(function() { alert('Network error occurred'); });
    }

    async function verifyPaymentProof(orderId, action) {
        if (action === 'Reject') {
            openPaymentRejectionModal(orderId);
            return;
        }
        
        const confirmed = await pfConfirm({
            title: 'Verify Payment',
            text: 'Are you sure you want to approve this payment proof? The order will move to TO PICK UP.',
            icon: '📄',
            iconBg: '#f0fdf4',
            iconColor: '#16a34a',
            confirmText: 'Yes, Approve',
            confirmColor: '#06A1A1'
        });
        if (!confirmed) return;
        
        var fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('action', 'Approve');
        
        fetch('/printflow/staff/api_verify_payment.php', {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showStatusOverlay('✅', 'Payment Verified!');
                setTimeout(function() { openOrderModal(orderId); fetchUpdatedTable(); }, 1200);
            } else {
                alert(res.error || 'Failed to verify payment');
            }
        })
        .catch(function() { alert('Network error'); });
    }

    function openPaymentRejectionModal(orderId) {
        document.getElementById('payRejOrderId').value = orderId;
        document.getElementById('paymentRejectionModal').style.opacity = '1';
        document.getElementById('paymentRejectionModal').style.pointerEvents = 'all';
    }

    function closePaymentRejectionModal() {
        document.getElementById('paymentRejectionModal').style.opacity = '0';
        document.getElementById('paymentRejectionModal').style.pointerEvents = 'none';
    }

    function handlePayRejReasonChange(sel) {
        var wrap = document.getElementById('payRejOtherWrapper');
        wrap.style.display = (sel.value === 'Other') ? 'block' : 'none';
    }

    function submitPaymentRejection() {
        var orderId = document.getElementById('payRejOrderId').value;
        var sel = document.getElementById('payRejReasonSelect');
        var reason = sel.value;
        if (reason === 'Other') {
            reason = document.getElementById('payRejOtherInput').value;
        }

        if (!reason) {
            alert('Please select or enter a reason for rejection.');
            return;
        }

        var fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('action', 'Reject');
        fd.append('reason', reason);

        fetch('/printflow/staff/api_verify_payment.php', {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                closePaymentRejectionModal();
                showStatusOverlay('❌', 'Payment Rejected');
                setTimeout(function() { openOrderModal(orderId); fetchUpdatedTable(); }, 1200);
            } else {
                alert(res.error || 'Failed to reject payment');
            }
        })
        .catch(function() { alert('Network error'); });
    }

    async function setOrderPrice(orderId) {
        var price = parseFloat(document.getElementById('omPriceInput').value);
        if (isNaN(price) || price <= 0) {
            alert('Please enter a valid price.');
            return;
        }

        const confirmed = await pfConfirm({
            title: 'Set Order Price',
            text: 'Are you sure you want to set the price to ₱' + price.toLocaleString() + ' and approve this order?',
            icon: '💰',
            iconBg: '#f0fdf4',
            iconColor: '#16a34a',
            confirmText: 'Yes, Set Price'
        });
        if (!confirmed) return;

        var fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('price', price);
        fd.append('action', 'update_order_price');

        fetch('/printflow/admin/job_orders_api.php', {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                // After setting price, also move to "Ready for Pickup" or "To Pay"
                // For POS, let's just refresh the modal
                showStatusOverlay('✅', 'Price set successfully!');
                setTimeout(function() { openOrderModal(orderId); fetchUpdatedTable(); }, 1200);
            } else {
                alert(res.error || 'Failed to update price');
            }
        })
        .catch(function() { alert('Network error'); });
    }

    // renderOrderModal is defined after DOMContentLoaded since it
    // just builds HTML strings — safe to define here too:
    function renderOrderModal(d) {
        document.getElementById('omSubtitle').textContent = d.order_date;

        var isFixed = (d.items || []).some(function(item) { return item.product_type === 'fixed'; });

        var cancelBlock = '';
        if (d.status === 'Cancelled' && (d.cancelled_by || d.cancel_reason)) {
            cancelBlock = '<div style="margin-top:12px;padding:12px;background:#fef2f2;border:1px solid #fee2e2;border-radius:10px;">' +
                '<div style="font-weight:700;color:#ef4444;font-size:12px;margin-bottom:4px;">Cancellation Details</div>' +
                '<div style="font-size:12px;color:#b91c1c;"><b>By:</b> ' + esc(d.cancelled_by) +
                '<br><b>Reason:</b> ' + esc(d.cancel_reason) +
                (d.cancelled_at ? '<br><b>At:</b> ' + esc(d.cancelled_at) : '') + '</div></div>';
        }

        var notesBlock = d.notes ? '<div class="om-notes-section" style="margin-top:20px; padding:16px; border-radius:12px; border:1px solid #e5e7eb; background:#fff;">' +
            '<label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Order Notes</label>' +
            '<div style="font-size:13px;color:#1f2937;line-height:1.5;background:#fefce8;padding:12px;border:1px solid #fef3c7;border-radius:8px;">' + esc(d.notes).replace(/\n/g,'<br>') + '</div>' +
            '</div>' : '';

        var payBlock = '';
        if (d.payment_proof && d.payment_proof !== 'null' && d.payment_proof !== 'undefined') {
            payBlock = '<div style="margin-top:20px; padding:16px; border-radius:12px; border:1px solid #e2e8f0; background:#f0fdf4;">' +
                '<label style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;display:block;margin-bottom:12px;">📄 Payment Proof</label>' +
                '<a href="' + d.payment_proof + '" target="_blank" style="display:block;border-radius:10px;overflow:hidden;border:2px solid #bbf7d0;background:#fff;">' +
                '<img src="' + d.payment_proof + '" alt="Payment Proof" style="width:100%;height:auto;display:block;max-height:400px;object-fit:contain;"></a></div>';
        }

        // Use CSRF token from order data (already generated server-side)
        var csrf = d.csrf_token || '';

        var actionsHTML = '';
        var verificationStatuses = ['Pending', 'Pending Review', 'Pending Approval', 'To Pay', 'To Verify', 'TO VERIFY'];
        var completionStatuses   = ['Ready for Pickup', 'TO PICK UP'];

        if (verificationStatuses.includes(d.status)) {
            // Check if it's a POS order needing a price (total is 0 or very small)
            if (d.order_source === 'pos' && d.total_raw <= 0) {
                actionsHTML = '<div style="margin-top:20px; padding:16px; border-radius:12px; border:1px solid #e2e8f0; background:#f8fafc;">' +
                    '<label style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; display:block; margin-bottom:12px;">💰 Set Negotiated Price</label>' +
                    '<div style="position:relative; margin-bottom:16px;">' +
                        '<span style="position:absolute; left:12px; top:12px; font-weight:700; color:#94a3b8;">₱</span>' +
                        '<input type="number" id="omPriceInput" style="width:100%; padding:12px 12px 12px 28px; border:1px solid #cbd5e1; border-radius:10px; font-size:18px; font-weight:700; outline:none;" placeholder="0.00">' +
                    '</div>' +
                    '<button class="btn-primary" onclick="setOrderPrice(' + d.order_id + ')" style="width:100%; background:#06A1A1; color:white; border:none; padding:12px; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px;">Approve & Set Price</button>' +
                    '</div>';
            } else {
                // VERIFICATION STAGE: Always show Payment Approve/Reject as requested
                actionsHTML = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:28px;">' +
                    '<button class="btn-primary" onclick="verifyPaymentProof(' + d.order_id + ', \'Approve\')" style="background:#06A1A1; color:white; border:none; padding:12px; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px;">Approve Payment</button>' +
                    '<button class="btn-secondary" onclick="verifyPaymentProof(' + d.order_id + ', \'Reject\')" style="color:#ef4444; border:1px solid #fee2e2; background:transparent; padding:12px; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px;">Reject Payment</button>' +
                    '</div>';
            }
        } else if (completionStatuses.includes(d.status)) {
            // COMPLETION STAGE
            actionsHTML = '<div style="margin-top:28px;">' +
                '<button class="btn-primary" onclick="markOrderCompleted(' + d.order_id + ', \'' + csrf + '\')" style="width:100%; background:#06A1A1; color:white; border:none; padding:12px; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px;">Mark as Completed</button>' +
                '</div>';
        }

        // --- Start Building UI ---
        var contentHTML = '';

        // 1. Customer Profile Header (Avatar first style)
        var cType = d.cust_type || 'REGULAR';
        var cTypeColor = (cType === 'NEW') ? 'background:#d1fae5; color:#065f46;' : 'background:#dbeafe; color:#1e40af;';
        
        contentHTML += '<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid #f3f4f6;">' +
            '<div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#06A1A1,#047676);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:22px;flex-shrink:0;overflow:hidden;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.1);">' + 
              ((d.cust_profile_picture && d.cust_profile_picture !== "null" && d.cust_profile_picture !== "undefined") ? '<img src="' + getProfileImage(d.cust_profile_picture) + '" style="width:100%;height:100%;object-fit:cover;" onerror="this.src=\'/printflow/public/assets/uploads/profiles/default.png\'">' : esc(d.cust_initial || '?')) + 
            '</div>' +
            '<div>' +
                '<div style="font-size:16px;font-weight:700;color:#1f2937;">' + esc(d.cust_name) + '</div>' +
                '<div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">' +
                    '<span style="font-size:11px; font-weight:700; padding:2px 8px; border-radius:99px; ' + cTypeColor + '">' + cType + '</span>' +
                    '<span style="font-size:12px;color:#6b7280;">' + esc(d.cust_phone) + '</span>' +
                '</div>' +
                (d.cust_address ? '<div style="font-size:12px;color:#6b7280;margin-top:6px;max-width:100%;word-break:break-word;">' + esc(d.cust_address) + '</div>' : '') +
            '</div>' +
        '</div>';

        // 2. Order Info Row
        contentHTML += '<div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:16px; margin-bottom:20px;">' +
            '<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">' +
                '<div>' +
                    '<div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Status</div>' +
                    '<div>' + statusBadge(d.status) + '</div>' +
                '</div>' +
                '<div>' +
                    '<div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Payment</div>' +
                    '<div>' + statusBadge(d.payment_status || '-') + '</div>' +
                '</div>' +
                '<div>' +
                    '<div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Grand Total</div>' +
                    '<div style="font-size:15px;font-weight:800;color:#111827;">₱ ' + parseFloat(d.total_raw || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</div>' +
                '</div>' +
                (d.payment_reference ? '<div><div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Ref #</div><div style="font-size:13px;font-weight:700;color:#111827;">' + esc(d.payment_reference) + '</div></div>' : '') +
            '</div>' +
            cancelBlock +
        '</div>';

        // 3. Order Details Section
        var itemsHTML = '';
        (d.items || []).forEach(function(item) {
            var itemImg = item.product_image || item.design_url || '/printflow/public/assets/images/services/default.png';
            if (!itemImg || itemImg === 'null' || itemImg === 'undefined') {
                itemImg = '/printflow/public/assets/images/services/default.png';
            }
            if (!itemImg.startsWith('/printflow') && !itemImg.startsWith('http') && !itemImg.startsWith('data:')) {
                itemImg = '/printflow/' + itemImg.replace(/^\/+/, '');
            }

            var specHTML = '';
            if (item.customization && Object.keys(item.customization).length) {
                var grid = '';
                Object.entries(item.customization).forEach(function(e2) {
                    var k = e2[0], v = e2[1];
                    if (!v || v === 'No' || v === 'None' || v === 'none' || k === 'branch_id') return;
                    var label = k.replace(/_/g, ' ');
                    grid += '<div style="padding:6px; background:#f9fafb; border:1px solid #e2e8f0; border-radius:6px;">' +
                        '<div style="font-size:9px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:2px;">' + esc(label) + '</div>' +
                        '<div style="font-size:12px; font-weight:600; color:#1e293b;">' + esc(String(v)) + '</div>' +
                    '</div>';
                });
                if (grid) specHTML = '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:10px; margin-top:12px;">' + grid + '</div>';
            }

            itemsHTML += '<div style="padding:16px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; margin-bottom:12px;">' +
                '<div style="display:flex; gap:16px; align-items:flex-start;">' +
                    '<div style="border-radius:10px; overflow:hidden; border:1px solid #eef2f6; flex-shrink:0; background:#f8fafc;">' +
                        '<img src="' + itemImg + '" style="width:70px; height:70px; object-fit:cover; display:block;">' +
                    '</div>' +
                    '<div style="flex:1;">' +
                        '<div style="font-size:14px; font-weight:700; color:#111827; margin-bottom:4px;">' + esc(item.product_name) + ' × ' + item.quantity + '</div>' +
                        '<div style="font-size:12px; color:#64748b;">₱' + parseFloat(item.unit_price).toFixed(2) + ' each</div>' +
                    '</div>' +
                '</div>' +
                specHTML +
            '</div>';
        });

        var detailsHTML = '<div style="margin-bottom:20px; padding:16px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">' +
            '<label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:12px;">Order Details (Customer Specifications)</label>' +
            itemsHTML +
        '</div>';

        document.getElementById('omBody').innerHTML = contentHTML + detailsHTML + notesBlock + payBlock + actionsHTML;
    }

    // ── DOMContentLoaded: event listeners & auto-open ────
    document.addEventListener('DOMContentLoaded', function() {
        // Escape key closes modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeOrderModal();
        });

        // Revision form: combine reason fields
        var revForm = document.getElementById('revForm');
        if (revForm) {
            revForm.addEventListener('submit', function(e) {
                var submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    if (submitBtn.disabled) { e.preventDefault(); return false; }
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Sending...';
                    submitBtn.style.opacity = '0.7';
                }
                var sel = this.querySelector('select[name="revision_reason_select"]');
                var oth = this.querySelector('textarea[name="revision_reason_other"]');
                var finalReason = sel ? sel.value : '';
                if (finalReason === 'Other' && oth) finalReason = oth.value;
                var hidden = this.querySelector('input[name="revision_reason"]');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'revision_reason';
                    this.appendChild(hidden);
                }
                hidden.value = finalReason;
            });
        }

        // Auto-open modal if order_id is in URL
        var urlParams = new URLSearchParams(window.location.search);
        var orderId = urlParams.get('order_id');
        if (orderId) { openOrderModal(orderId); }
    });
    </script>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <div>
                <h1 class="page-title">Orders Management</h1>
                <p class="page-subtitle">Track and manage all customer orders and job statuses</p>
            </div>
        </header>

        <main x-data="ordersPage()" x-init="init()">
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4" style="background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px;">
                    Order status updated successfully!
                </div>
            <?php endif; ?>

            <!-- Standardized KPI Row -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <span class="kpi-card-inner">
                        <span class="kpi-label">Total Orders</span>
                        <span class="kpi-value" id="totalOrdersCount"><?php echo number_format($total_count); ?></span>
                        <span class="kpi-sub">Lifetime orders</span>
                    </span>
                </div>
                <div class="kpi-card amber">
                    <span class="kpi-card-inner">
                        <span class="kpi-label">TO VERIFY</span>
                        <span class="kpi-value"><?php echo $pending_count; ?></span>
                        <span class="kpi-sub">Awaiting action</span>
                    </span>
                </div>
                <div class="kpi-card emerald">
                    <span class="kpi-card-inner">
                        <span class="kpi-label">TO PICK UP</span>
                        <span class="kpi-value"><?php echo $ready_count; ?></span>
                        <span class="kpi-sub">Awaiting customer</span>
                    </span>
                </div>
                <div class="kpi-card blue">
                    <span class="kpi-card-inner">
                        <span class="kpi-label">COMPLETED</span>
                        <span class="kpi-value"><?php echo $completed_count; ?></span>
                        <span class="kpi-sub">Processed successfully</span>
                    </span>
                </div>
            </div>

            <!-- Orders List & Standardized Toolbar -->
            <div class="card overflow-visible">
                <div class="toolbar-container" style="display:block;">
                    <div class="pf-custom-tabs">
                        <template x-for="(label, key) in statusTabs" :key="key">
                            <button type="button" 
                                    class="pill-tab" 
                                    :class="{ 'active': activeTab === key }"
                                    @click="switchStatusTab(key)">
                                <span x-text="label"></span>
                                <span class="tab-count" x-text="tabCounts[key] || 0"></span>
                            </button>
                        </template>
                    </div>

                    <div style="display:flex; align-items:center; width:100%;">
                        <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">
                        Orders List
                    </h3>
                    <div class="toolbar-group" style="margin-left: auto;">


                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: sortOpen || (activeSort !== 'newest') }" @click="sortOpen = !sortOpen; filterOpen = false">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/></svg>
                                Sort by
                            </button>
                            <div class="dropdown-panel sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <?php
                                $sorts = [
                                    'newest' => 'Newest to Oldest',
                                    'oldest' => 'Oldest to Newest',
                                    'az'     => 'A → Z',
                                    'za'     => 'Z → A',
                                ];
                                foreach ($sorts as $key => $label): ?>
                                <div class="sort-option" 
                                     :class="{ 'active': activeSort === '<?php echo $key; ?>' }"
                                     @click="applySortFilter('<?php echo $key; ?>')">
                                    <?php echo htmlspecialchars($label); ?>
                                    <svg x-show="activeSort === '<?php echo $key; ?>'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: filterOpen || hasActiveFilters }" @click="filterOpen = !filterOpen; sortOpen = false">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                Filter
                                <template x-if="hasActiveFilters">
                                    <span class="filter-badge"><?php echo count($active_filters); ?></span>
                                </template>
                            </button>

                            <!-- Filter Panel -->
                            <div class="dropdown-panel filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-header">Filter</div>
                                
                                <!-- Date Range -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-label" style="margin:0;">Date range</span>
                                        <button @click="resetFilterField(['date_from','date_to'])" class="filter-reset-link">Reset</button>
                                    </div>
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                        <input type="date" id="fp_date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from_filter); ?>" @change="applyFilters()">
                                        <input type="date" id="fp_date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to_filter); ?>" @change="applyFilters()">
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-label" style="margin:0;">Status</span>
                                        <button @click="resetFilterField(['status'])" class="filter-reset-link">Reset</button>
                                    </div>
                                    <select id="fp_status" class="filter-select" @change="applyFilters()">
                                        <option value="">All statuses</option>
                                        <option value="Pending"               <?php echo $status_filter === 'Pending'               ? 'selected' : ''; ?>>TO VERIFY</option>
                                        <option value="Downpayment Submitted" <?php echo $status_filter === 'Downpayment Submitted' ? 'selected' : ''; ?>>ToCheck</option>
                                        <option value="Ready for Pickup"      <?php echo $status_filter === 'Ready for Pickup'      ? 'selected' : ''; ?>>TO PICK UP</option>
                                        <option value="Completed"             <?php echo $status_filter === 'Completed'             ? 'selected' : ''; ?>>COMPLETED</option>
                                        <option value="Cancelled"             <?php echo $status_filter === 'Cancelled'             ? 'selected' : ''; ?>>CANCELLED</option>
                                    </select>
                                </div>

                                <!-- Keyword Search -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-label" style="margin:0;">Keyword search</span>
                                        <button @click="resetFilterField(['customer'])" class="filter-reset-link">Reset</button>
                                    </div>
                                    <input type="text" id="fp_customer" class="filter-input" placeholder="Search..." value="<?php echo htmlspecialchars($customer_filter); ?>" @change="applyFilters()">
                                </div>

                                <div class="filter-footer">
                                    <button class="filter-btn-reset" style="width:100%;" @click="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="staff-orders-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="staff-order-row" onclick="openOrderModal(<?php echo $order['order_id']; ?>)" style="border-bottom: 1px solid #f1f5f9; cursor: pointer;">
                                    <td>
                                        <div class="order-info-cell">
                                            <div class="order-id-wrap">
                                                #<?php echo $order['order_id']; ?>
                                                <?php if (($order['order_source'] ?? '') === 'pos'): ?>
                                                    <span style="display:inline-flex;align-items:center;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:800;background:#fef3c7;color:#92400e;margin-left:4px;border:1px solid #fde68a;">POS</span>
                                                <?php endif; ?>
                                                <?php 
                                                $unread = get_unread_chat_count($order['order_id'], 'User');
                                                if ($unread > 0): 
                                                ?>
                                                    <span style="background: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800; animation: pulse 2s infinite;" title="<?php echo $unread; ?> new messages from customer">
                                                        <?php echo $unread; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($order['item_names'])): ?>
                                                <div class="order-items-sub">
                                                    <?php 
                                                        $display_items = $order['item_names'];
                                                        if ($display_items === 'Custom Product' || $display_items === 'Custom Order') {
                                                            $display_items = get_service_name_from_customization($order['first_item_customization'] ?? '{}', $display_items);
                                                            $c_json = json_decode($order['first_item_customization'] ?? '{}', true);
                                                            if (!empty($c_json['product_type']) && $c_json['product_type'] !== $display_items) {
                                                                $display_items .= " (" . $c_json['product_type'] . ")";
                                                            }
                                                        }
                                                        echo htmlspecialchars(strlen($display_items) > 100 ? substr($display_items, 0, 100) . '...' : $display_items); 
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="color: #334155; font-weight: 500;"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td style="color: #64748b; font-size: 13px;"><?php echo format_date($order['order_date']); ?></td>
                                    <td style="font-weight: 700; color: #1e293b;"><?php echo format_currency($order['total_amount']); ?></td>
                                    <td>
                                        <?php
                                        $display_order_status2 = $order['status'];
                                        if (in_array($display_order_status2, ['Processing', 'In Production', 'Printing', 'Approved Design'])) {
                                            $display_order_status2 = 'Ready for Pickup';
                                        }
                                        echo status_badge($display_order_status2, 'order'); ?>
                                        <?php if (($order['design_status'] ?? '') === 'Revision Submitted'): ?>
                                            <div style="margin-top: 6px;">
                                                <?php echo status_badge('Revision Submitted', 'order'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($order['payment_status'])): ?>
                                            <div style="margin-top:6px;"><?php echo status_badge($order['payment_status'], 'payment'); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-cell">
                                            <button onclick="event.stopPropagation(); openOrderModal(<?php echo $order['order_id']; ?>)" 
                                                    class="btn-staff-action btn-staff-action-emerald">
                                                Manage
                                            </button>
                                            <a href="/printflow/staff/chats.php?order_id=<?php echo $order['order_id']; ?>"
                                               onclick="event.stopPropagation();"
                                               class="btn-staff-action btn-staff-action-indigo">
                                                Message
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php echo get_pagination_links($current_page, $total_pages, $active_filters); ?>
        </main>
    </div>
</div>

<!-- ══════════════════════════════════════════
     ORDER DETAIL MODAL
═══════════════════════════════════════════ -->
<div id="orderModal" role="dialog" aria-modal="true" aria-labelledby="omTitle">
    <div class="om-backdrop" onclick="closeOrderModal()"></div>
    <div class="om-panel">
        <div class="om-header">
            <div>
                <div class="om-title" id="omTitle">Order Details</div>
                <div class="om-subtitle" id="omSubtitle">Loading…</div>
            </div>
            <button class="om-close" onclick="closeOrderModal()" aria-label="Close">✕</button>
        </div>
        <div class="om-body" id="omBody">
            <!-- Loader -->
            <div class="om-loader">
                <div class="om-spinner"></div>
                <div style="color:#94a3b8; font-size:14px;">Fetching order details…</div>
            </div>
        </div>

        <!-- Status Overlay (Centered Toast) -->
        <div id="omStatusOverlay" class="om-status-overlay">
            <div class="om-status-toast">
                <div id="omStatusIcon" class="om-status-toast-icon">✅</div>
                <div id="omStatusMsg" class="om-status-toast-msg">Status Updated!</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     REVISION MODAL
═══════════════════════════════════════════ -->
<style>
    #revisionModal {
        position: fixed; inset: 0; z-index: 10001;
        display: flex; align-items: center; justify-content: center;
        padding: 16px; opacity: 0; pointer-events: none;
        transition: opacity 0.2s ease;
    }
    #revisionModal.open { opacity: 1; pointer-events: all; }
    .rev-backdrop { position: absolute; inset: 0; background: transparent; backdrop-filter: none; }
    .rev-panel {
        position: relative; z-index: 1; background: #fff; border-radius: 20px;
        width: 100%; max-width: 450px; padding: 28px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        transform: scale(0.95); transition: transform 0.2s;
    }
    #revisionModal.open .rev-panel { transform: scale(1); }
    .rev-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
    .rev-sub { font-size: 0.9rem; color: #64748b; margin-bottom: 20px; }
</style>

<div id="revisionModal" role="dialog" aria-modal="true">
    <div class="rev-backdrop" onclick="closeRevisionModal()"></div>
    <div class="rev-panel">
        <div class="rev-title">Request Design Revision</div>
        <p class="rev-sub">Please select a reason for the revision request. This will be sent to the customer.</p>
        
        <form id="revForm" action="request_revision_process.php" method="POST">
            <input type="hidden" name="order_id" id="revOrderId">
            <input type="hidden" name="csrf_token" id="revCsrfToken">
            
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:8px;">Reason for Revision</label>
                <select name="revision_reason_select" class="input-field" required onchange="handleReasonChange(this)">
                    <option value="" disabled selected>Select a reason...</option>
                    <option value="Low image quality / Blurry file">Low image quality / Blurry file</option>
                    <option value="Incorrect dimensions / Size issue">Incorrect dimensions / Size issue</option>
                    <option value="Wrong file format">Wrong file format</option>
                    <option value="Design not print-ready">Design not print-ready</option>
                    <option value="Incomplete details">Incomplete details</option>
                    <option value="Copyright or restricted content">Copyright or restricted content</option>
                    <option value="Other">Other (Please specify)</option>
                </select>
            </div>

            <div id="revOtherWrapper" style="display:none; margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:8px;">Specify Other Reason</label>
                <textarea name="revision_reason_other" id="revOtherInput" class="input-field" style="height:80px;"></textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <button type="button" class="btn-secondary" onclick="closeRevisionModal()">Cancel</button>
                <button type="submit" class="btn-primary">Send Request</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     PAYMENT REJECTION MODAL
═══════════════════════════════════════════ -->
<div id="paymentRejectionModal" role="dialog" aria-modal="true" style="position: fixed; inset: 0; z-index: 10002; display: flex; align-items: center; justify-content: center; padding: 16px; opacity: 0; pointer-events: none; transition: opacity 0.2s ease;">
    <div class="rev-backdrop" onclick="closePaymentRejectionModal()" style="position: absolute; inset: 0; background: transparent; backdrop-filter: none;"></div>
    <div class="rev-panel" style="position: relative; z-index: 1; background: #fff; border-radius: 20px; width: 100%; max-width: 450px; padding: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); transform: scale(0.95); transition: transform 0.2s;">
        <div class="rev-title" style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Reject Payment Proof</div>
        <p class="rev-sub" style="font-size: 0.9rem; color: #64748b; margin-bottom: 20px;">Please select a reason for rejecting the payment proof. The customer will be notified.</p>
        
        <div id="payRejectionForm">
            <input type="hidden" id="payRejOrderId">
            
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:8px;">Reason for Rejection</label>
                <select id="payRejReasonSelect" class="input-field" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0;" onchange="handlePayRejReasonChange(this)">
                    <option value="" disabled selected>Select a reason...</option>
                    <option value="Invalid payment proof">Invalid payment proof</option>
                    <option value="Blurry image">Blurry image</option>
                    <option value="Wrong amount">Wrong amount</option>
                    <option value="Duplicate payment">Duplicate payment</option>
                    <option value="Other">Other (Please specify)</option>
                </select>
            </div>

            <div id="payRejOtherWrapper" style="display:none; margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:8px;">Specify Other Reason</label>
                <textarea id="payRejOtherInput" class="input-field" style="width:100%; height:80px; padding:10px; border-radius:8px; border:1px solid #e2e8f0;"></textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <button type="button" class="btn-secondary" onclick="closePaymentRejectionModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="submitPaymentRejection()" style="background:#ef4444; border-color:#ef4444;">Reject Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     CUSTOM CONFIRMATION MODAL
═══════════════════════════════════════════ -->
<style>
    #pfConfirmModal {
        position: fixed; inset: 0; z-index: 20000;
        display: none; align-items: center; justify-content: center;
        padding: 24px;
    }
    #pfConfirmModal.open { display: flex; }
    .pf-confirm-backdrop { position: absolute; inset: 0; background: transparent; backdrop-filter: none; }
    .pf-confirm-panel {
        position: relative; z-index: 1; background: #fff; border-radius: 24px;
        width: 100%; max-width: 400px; padding: 32px; text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        transform: scale(0.95); transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    #pfConfirmModal.open .pf-confirm-panel { transform: scale(1); }
    .pf-confirm-icon {
        width: 72px; height: 72px; background: #f0fdf4; color: #10b981;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 24px; font-size: 32px; border: 4px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .pf-confirm-title { font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 12px; }
    .pf-confirm-text { font-size: 15px; color: #64748b; line-height: 1.6; margin-bottom: 32px; }
</style>

<div id="pfConfirmModal" role="dialog" aria-modal="true">
    <div class="pf-confirm-backdrop"></div>
    <div class="pf-confirm-panel">
        <div id="pfConfirmIcon" class="pf-confirm-icon">✓</div>
        <div id="pfConfirmTitle" class="pf-confirm-title">Are you sure?</div>
        <p id="pfConfirmText" class="pf-confirm-text">Please confirm you want to proceed with this action.</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <button id="pfConfirmBtnCancel" class="btn-secondary" style="padding: 12px; border-radius: 12px; font-weight: 700;">Cancel</button>
            <button id="pfConfirmBtnConfirm" class="btn-primary" style="padding: 12px; border-radius: 12px; font-weight: 700; background: #06A1A1;">Proceed</button>
        </div>
    </div>
</div>

<script>
/**
 * Custom Promise-based Confirmation Modal
 */
function pfConfirm(options) {
    return new Promise((resolve) => {
        const modal = document.getElementById('pfConfirmModal');
        const title = document.getElementById('pfConfirmTitle');
        const text = document.getElementById('pfConfirmText');
        const icon = document.getElementById('pfConfirmIcon');
        const confirmBtn = document.getElementById('pfConfirmBtnConfirm');
        const cancelBtn = document.getElementById('pfConfirmBtnCancel');

        // Setup
        title.textContent = options.title || 'Are you sure?';
        text.textContent = options.text || 'Do you want to continue?';
        icon.textContent = options.icon || '✓';
        icon.style.background = options.iconBg || '#f0fdf4';
        icon.style.color = options.iconColor || '#10b981';
        
        confirmBtn.textContent = options.confirmText || 'Proceed';
        confirmBtn.style.background = options.confirmColor || '#06A1A1';
        confirmBtn.style.borderColor = options.confirmColor || '#06A1A1';
        
        cancelBtn.textContent = options.cancelText || 'Cancel';

        modal.classList.add('open');

        // Cleanup
        const done = (val) => {
            modal.classList.remove('open');
            confirmBtn.onclick = null;
            cancelBtn.onclick = null;
            resolve(val);
        };

        confirmBtn.onclick = () => done(true);
        cancelBtn.onclick = () => done(false);
    });
}
</script>

</body>
</html>
