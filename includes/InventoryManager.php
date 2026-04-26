<?php
/**
 * Inventory Manager (v2)
 * Unified service for all PrintFlow inventory types.
 */

require_once __DIR__ . '/db.php';

class InventoryManager {

    /**
     * Records a new inventory transaction and enforces idempotency.
     */
    public static function recordTransaction($itemId, $direction, $quantity, $uom, $refType, $refId, $rollId = null, $notes = '', $userId = null, $date = null) {
        global $conn;
        
        $date = $date ?: date('Y-m-d');
        $quantity = abs((float)$quantity);
        $userId = $userId ?: ($_SESSION['user_id'] ?? null);

        // Core fields
        $fields = [
            'item_id'          => ['type' => 'i', 'val' => $itemId],
            'direction'        => ['type' => 's', 'val' => $direction],
            'quantity'         => ['type' => 's', 'val' => (string)$quantity],
            'uom'              => ['type' => 's', 'val' => $uom],
            'ref_type'         => ['type' => 's', 'val' => $refType],
            'notes'            => ['type' => 's', 'val' => $notes],
            'transaction_date' => ['type' => 's', 'val' => $date]
        ];

        // Optional fields
        if ($rollId !== null) $fields['roll_id'] = ['type' => 'i', 'val' => $rollId];
        if ($refId !== null)  $fields['ref_id']  = ['type' => 'i', 'val' => $refId];
        if ($userId !== null) $fields['created_by'] = ['type' => 'i', 'val' => $userId];

        $cols = array_keys($fields);
        $placeholders = array_fill(0, count($fields), '?');
        $types = implode('', array_column($fields, 'type'));
        $values = array_column($fields, 'val');

        $sql = "INSERT INTO inventory_transactions (" . implode(', ', $cols) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        try {
            if ($stmt->execute()) {
                $id = $stmt->insert_id;
                $stmt->close();
                return $id;
            }
        } catch (Exception $e) {
            // Error 1062 is Duplicate Entry
            if (isset($conn->errno) && $conn->errno == 1062) {
                return true; 
            }
            throw new Exception("Ledger recording failed: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Receives new stock (IN).
     */
    public static function receiveStock($itemId, $quantity, $uom = null, $rollData = null, $refType = 'PURCHASE', $refId = null, $notes = '', $transactionDate = null) {
        global $conn;
        
        $item = self::getItem($itemId);
        if (!$item) throw new Exception("Item not found.");

        $conn->begin_transaction();
        try {
            $uom = $uom ?: $item['unit_of_measure'];
            $rollId = null;

            // Handle roll creation if it's a roll item
            if ($item['track_by_roll']) {
                require_once __DIR__ . '/RollService.php';
                $rollDataSafe = $rollData ?? [];
                $rollCode = $rollDataSafe['roll_code'] ?? '';
                if (empty($rollCode)) {
                    $rollCode = 'AUTO-' . strtoupper(substr($item['name'], 0, 3)) . '-' . date('YmdHis');
                }
                $rollId = RollService::createRoll(
                    $itemId, 
                    $quantity, // For new reception, total length = quantity received
                    $rollCode, 
                    $rollDataSafe['supplier'] ?? null,
                    $rollDataSafe['width_ft'] ?? 0
                );
            }

            // Record transaction
            $refType = strtoupper($refType ?: 'PURCHASE');
            self::recordTransaction($itemId, 'IN', $quantity, $uom, $refType, $refId, $rollId, $notes, null, $transactionDate);

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($conn->in_transaction) $conn->rollback();
            throw $e;
        }
    }

    /**
     * Issues stock (OUT).
     * For roll-tracked items, uses FIFO deduction across rolls automatically.
     * For non-roll items, records a simple OUT transaction.
     */
    public static function issueStock($itemId, $quantity, $uom = null, $refType = 'ADJUSTMENT', $refId = null, $notes = '', $ignoreRollCheck = false, $allowNegativeBypass = false) {
        $item = self::getItem($itemId);
        if (!$item) throw new Exception("Item not found.");
        
        // For roll-tracked items, route through FIFO deduction
        if ($item['track_by_roll'] && !$ignoreRollCheck) {
            require_once __DIR__ . '/RollService.php';
            return RollService::deductFIFO($itemId, $quantity, $refType, $refId, $notes);
        }

        $soh = self::getStockOnHand($itemId);
        // For roll-based items used with ignoreRollCheck, skip the SOH check since stock lives in inv_rolls
        $skipSohCheck = ($item['track_by_roll'] && $ignoreRollCheck) || $allowNegativeBypass;
        if (!$skipSohCheck && $soh < $quantity && !$item['allow_negative_stock']) {
            throw new Exception("Insufficient stock for '{$item['name']}'. Have: $soh, Need: $quantity");
        }

        $result = self::recordTransaction($itemId, 'OUT', $quantity, $uom ?: $item['unit_of_measure'], $refType, $refId, null, $notes);

        // Fire a low-stock push notification when SOH drops to or below the reorder level
        if ($result && !empty($item['reorder_level']) && (float)$item['reorder_level'] > 0) {
            $newSoh = self::getStockOnHand($itemId);
            if ($newSoh <= (float)$item['reorder_level']) {
                if (function_exists('create_notification')) {
                    $msg = "Low stock: {$item['name']} is at {$newSoh} {$item['unit_of_measure']} (reorder at {$item['reorder_level']})";
                    $admins = db_query("SELECT user_id FROM users WHERE role IN ('Admin','Manager') AND status = 'Activated'", '', []);
                    foreach ((array)$admins as $u) {
                        create_notification((int)$u['user_id'], 'User', $msg, 'Stock', false, false, $itemId);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Gets accurate Stock On Hand based on v2 rules.
     */
    public static function getStockOnHand($itemId) {
        $item = self::getItem($itemId);
        if (!$item) return 0;

        if ($item['track_by_roll']) {
            // Stock is the sum of remaining lengths of all OPEN rolls
            $sql = "SELECT SUM(remaining_length_ft) as soh FROM inv_rolls WHERE item_id = ? AND status = 'OPEN'";
            $res = db_query($sql, 'i', [$itemId]);
            return (float)($res[0]['soh'] ?? 0);
        } else {
            // Stock is the sum of IN - sum of OUT transactions
            $sql = "SELECT SUM(IF(direction='IN', quantity, -quantity)) as soh FROM inventory_transactions WHERE item_id = ?";
            $res = db_query($sql, 'i', [$itemId]);
            return (float)($res[0]['soh'] ?? 0);
        }
    }

    /**
     * Get item details.
     */
    public static function getItem($id) {
        $res = db_query("SELECT * FROM inv_items WHERE id = ?", 'i', [$id]);
        return $res[0] ?? null;
    }

    /**
     * Convenience method for roll deduction (used by TarpaulinService).
     */
    public static function deductRollMaterial($orderItemId, $rollId, $requiredLength) {
        require_once __DIR__ . '/RollService.php';
        // We use orderItemId as the jobOrderId for now, or we might need to look up the order_id
        $item = db_query("SELECT order_id FROM order_items WHERE order_item_id = ?", 'i', [$orderItemId]);
        $orderId = $item[0]['order_id'] ?? 0;
        
        return RollService::deductFromRoll($rollId, $requiredLength, $orderId, null);
    }
}
