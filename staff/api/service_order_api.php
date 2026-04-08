<?php
/**
 * Staff API: service order detail (JSON) + approve / reject / update status.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/service_order_helper.php';
require_once __DIR__ . '/../../includes/service_order_staff_modal_data.php';

if (!is_logged_in() || (!is_staff() && !is_admin())) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

service_order_ensure_tables();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && ($_GET['action'] ?? '') === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    $data = service_order_staff_modal_data($id);
    if ($data === null) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($method !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($input)) {
    $input = $_POST;
}

if (!verify_csrf_token($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$order_id = (int)($input['order_id'] ?? $input['id'] ?? 0);
if ($order_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid order']);
    exit;
}

$order_row = db_query(
    'SELECT * FROM service_orders WHERE id = ?',
    'i',
    [$order_id]
);
if (empty($order_row)) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
$order_row = $order_row[0];

$op = $input['op'] ?? '';

if ($op === 'approve') {
    // When approving, move to Processing and trigger inventory deduction
    db_execute("UPDATE service_orders SET status = 'Processing' WHERE id = ?", 'i', [$order_id]);
    
    // Deduct inventory for service orders (similar to job orders moving to IN_PRODUCTION)
    require_once __DIR__ . '/../../includes/InventoryManager.php';
    require_once __DIR__ . '/../../includes/RollService.php';
    
    try {
        // Get materials assigned to this service order
        $materials = db_query(
            "SELECT * FROM job_order_materials WHERE std_order_id = ? AND deducted_at IS NULL",
            'i',
            [$order_id]
        );
        
        if ($materials) {
            foreach ($materials as $m) {
                $item = InventoryManager::getItem($m['item_id']);
                if (!$item) continue;

                if ($item['track_by_roll']) {
                    $lengthNeeded = (float)($m['computed_required_length_ft'] ?: $m['quantity']);

                    if ($lengthNeeded <= 0) {
                        db_execute("UPDATE job_order_materials SET deducted_at = NOW() WHERE id = ?", 'i', [$m['id']]);
                        continue;
                    }

                    try {
                        RollService::deductFIFO(
                            $m['item_id'],
                            $lengthNeeded,
                            'SERVICE_ORDER',
                            $order_id,
                            "Deducted for Service Order #{$order_id}"
                        );
                    } catch (Exception $e) {
                        throw new Exception(
                            "Cannot process Service Order #{$order_id}: Roll stock depleted for '{$item['name']}'. " .
                            "Please receive new stock before approving. (" . $e->getMessage() . ")"
                        );
                    }
                    
                    // Handle lamination if present
                    $metadata = is_string($m['metadata']) ? json_decode($m['metadata'], true) : $m['metadata'];
                    if (is_array($metadata) && isset($metadata['lamination_item_id']) && !empty($metadata['lamination_length_ft'])) {
                        $lamItem = InventoryManager::getItem($metadata['lamination_item_id']);
                        if ($lamItem) {
                            try {
                                if ($lamItem['track_by_roll']) {
                                    RollService::deductFIFO(
                                        $lamItem['id'],
                                        $metadata['lamination_length_ft'],
                                        'SERVICE_ORDER',
                                        $order_id,
                                        "Lamination deducted for Service Order #{$order_id}"
                                    );
                                } else {
                                    InventoryManager::issueStock(
                                        $lamItem['id'], 
                                        $metadata['lamination_length_ft'], 
                                        $lamItem['unit_of_measure'], 
                                        'SERVICE_ORDER', 
                                        $order_id, 
                                        "Lamination deducted for Service Order #{$order_id}"
                                    );
                                }
                            } catch (Exception $e) {
                                throw new Exception(
                                    "Cannot process Service Order #{$order_id}: Lamination stock depleted for '{$lamItem['name']}'. " .
                                    "Please receive new stock before approving. (" . $e->getMessage() . ")"
                                );
                            }
                        }
                    }

                    db_execute("UPDATE job_order_materials SET deducted_at = NOW() WHERE id = ?", 'i', [$m['id']]);
                } else {
                    // Non-roll deduction
                    InventoryManager::issueStock(
                        $m['item_id'], 
                        $m['quantity'], 
                        $m['uom'], 
                        'SERVICE_ORDER', 
                        $order_id, 
                        "Deducted for Service Order #{$order_id}"
                    );
                    db_execute("UPDATE job_order_materials SET deducted_at = NOW() WHERE id = ?", 'i', [$m['id']]);
                }
            }
        }

        // Process Ink Deductions
        $inks = db_query("SELECT * FROM job_order_ink_usage WHERE std_order_id = ?", 'i', [$order_id]);
        if ($inks) {
            foreach ($inks as $ink) {
                $inkItem = InventoryManager::getItem($ink['item_id']);
                if (!$inkItem) continue;

                InventoryManager::issueStock(
                    $ink['item_id'],
                    $ink['quantity_used'],
                    $inkItem['unit_of_measure'] ?? 'bottle',
                    'SERVICE_ORDER',
                    $order_id,
                    "{$ink['ink_color']} ink used for Service Order #{$order_id}"
                );
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    
    if (function_exists('create_notification')) {
        create_notification(
            (int)$order_row['customer_id'],
            'Customer',
            "Your service order #{$order_id} has been approved and is now in production. Materials have been allocated.",
            'Order',
            true,
            false
        );
    }
} elseif ($op === 'reject') {
    db_execute("UPDATE service_orders SET status = 'Rejected' WHERE id = ?", 'i', [$order_id]);
    if (function_exists('create_notification')) {
        create_notification(
            (int)$order_row['customer_id'],
            'Customer',
            "Your service order #{$order_id} has been rejected.",
            'Order',
            true,
            false
        );
    }
} elseif ($op === 'update_status') {
    $new_status = (string)($input['status'] ?? '');
    if (!in_array($new_status, ['Pending', 'Pending Review', 'Approved', 'Processing', 'Completed', 'Rejected'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }
    db_execute('UPDATE service_orders SET status = ? WHERE id = ?', 'si', [$new_status, $order_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown operation']);
    exit;
}

$data = service_order_staff_modal_data($order_id);
echo json_encode(['success' => true, 'data' => $data]);
exit;
