<?php
/**
 * Inventory Rolls API
 * Roll-specific management.
 */

// Set JSON header first to ensure all responses are JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/RollService.php';

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Check role authorization
$user_type = $_SESSION['user_type'] ?? '';
if (!in_array($user_type, ['Admin', 'Manager', 'Staff'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions. Required: Admin, Manager, or Staff']);
    exit;
}

try {

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No action specified']);
        exit;
    }

    switch ($action) {
        case 'list_rolls':
            $itemId = (int)($_GET['item_id'] ?? 0);
            $status = sanitize($_GET['status'] ?? '');

            $where = ['1=1'];
            $types = '';
            $params = [];

            if ($itemId) {
                $where[] = 'r.item_id = ?';
                $types .= 'i';
                $params[] = $itemId;
            }
            if ($status !== '') {
                $where[] = 'r.status = ?';
                $types .= 's';
                $params[] = $status;
            }

            $sql = "SELECT r.*, i.name AS item_name
                    FROM inv_rolls r
                    JOIN inv_items i ON i.id = r.item_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY r.status ASC, r.received_at ASC";

            $rolls = $types ? db_query($sql, $types, $params) : db_query($sql);
            echo json_encode(['success' => true, 'data' => $rolls ?: []]);
            break;

        case 'add_roll':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $len = (float)($_POST['total_length'] ?? 0);
            $code = sanitize($_POST['roll_code'] ?? '');
            $supplier = sanitize($_POST['supplier'] ?? '');
            
            if (!$itemId || $len <= 0) throw new Exception("Invalid item or length.");
            
            $rollId = RollService::createRoll($itemId, $len, $code, $supplier);
            echo json_encode(['success' => true, 'roll_id' => $rollId]);
            break;

        case 'void_roll':
            $rollId = (int)($_POST['roll_id'] ?? 0);
            $notes = sanitize($_POST['notes'] ?? '');
            if (!$rollId) throw new Exception("Roll ID required.");
            
            $res = RollService::voidRoll($rollId, $notes);
            echo json_encode(['success' => $res]);
            break;

        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
