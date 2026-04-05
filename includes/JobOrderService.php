<?php
/**
 * Job Order Service
 * Handles life cycle of job orders (create, assign, deduct).
 * PrintFlow v2
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/InventoryManager.php';
require_once __DIR__ . '/RollService.php';
require_once __DIR__ . '/NotificationService.php';

class JobOrderService {

    /**
     * Create a new job order with materials.
     */
    public static function createOrder($orderData, $materials = []) {
        global $conn;
        
        $conn->begin_transaction();
        try {
            // 1. Insert Job Order (with explicit PENDING status)
            $requiredPayment = self::calculateRequiredPayment($orderData['customer_id'], $orderData['estimated_total']);
            $sql = "INSERT INTO job_orders (order_id, customer_id, job_title, service_type, width_ft, height_ft, quantity, total_sqft, price_per_sqft, price_per_piece, estimated_total, required_payment, notes, due_date, priority, artwork_path, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisssddiddddssssi", 
                $orderData['order_id'],
                $orderData['customer_id'], 
                $orderData['job_title'],
                $orderData['service_type'], 
                $orderData['width_ft'], 
                $orderData['height_ft'], 
                $orderData['quantity'], 
                $orderData['total_sqft'], 
                $orderData['price_per_sqft'], 
                $orderData['price_per_piece'], 
                $orderData['estimated_total'], 
                $requiredPayment,
                $orderData['notes'], 
                $orderData['due_date'],
                $orderData['priority'],
                $orderData['artwork_path'], 
                $orderData['created_by']
            );
            
            if (!$stmt->execute()) throw new Exception("Failed to create job order.");
            $orderId = $stmt->insert_id;
            $stmt->close();

            // 2. Insert Required Materials (placeholders + capture unit cost)
            if (!empty($materials)) {
                $materialSql = "INSERT INTO job_order_materials (job_order_id, item_id, quantity, uom, computed_required_length_ft, unit_cost_at_assignment) VALUES (?, ?, ?, ?, ?, ?)";
                $mStmt = $conn->prepare($materialSql);
                foreach ($materials as $m) {
                    $item = InventoryManager::getItem($m['item_id']);
                    $cost = $item['unit_cost'] ?? 0;
                    $mStmt->bind_param("iidsdd", $orderId, $m['item_id'], $m['quantity'], $m['uom'], $m['computed_len'], $cost);
                    $mStmt->execute();
                }
                $mStmt->close();
            }

            $conn->commit();
            return $orderId;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * Same service_type mapping as customer/checkout.php when creating jobs from cart line items.
     */
    public static function inferServiceTypeFromProduct(string $category, string $name): string {
        $cat_lower = strtolower($category . ' ' . $name);
        if (strpos($cat_lower, 'tarpaulin') !== false) {
            return 'Tarpaulin Printing';
        }
        if (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'shirt') !== false) {
            return 'T-shirt Printing';
        }
        if (strpos($cat_lower, 'reflectorized') !== false) {
            return 'Reflectorized (Subdivision Stickers/Signages)';
        }
        if (strpos($cat_lower, 'transparent') !== false) {
            return 'Transparent Stickers';
        }
        if (strpos($cat_lower, 'glass') !== false || strpos($cat_lower, 'wall') !== false || strpos($cat_lower, 'frosted') !== false) {
            return 'Glass Stickers / Wall / Frosted Stickers';
        }
        if (strpos($cat_lower, 'sintraboard') !== false && (strpos($cat_lower, 'standee') !== false || strpos($cat_lower, 'stand') !== false)) {
            return 'Sintraboard Standees';
        }
        if (strpos($cat_lower, 'sintraboard') !== false) {
            return 'Stickers on Sintraboard';
        }
        if (strpos($cat_lower, 'sticker') !== false || strpos($cat_lower, 'decal') !== false) {
            return 'Decals/Stickers (Print/Cut)';
        }
        if (strpos($cat_lower, 'souvenir') !== false) {
            return 'Souvenirs';
        }
        if (strpos($cat_lower, 'layout') !== false) {
            return 'Layouts';
        }
        return 'Tarpaulin Printing';
    }

    /**
     * If a store order has line items but no job_orders (e.g. checkout job step failed), create jobs like checkout does.
     * Returns first job_orders.id or null if order/items missing.
     */
    public static function ensureJobsForStoreOrder(int $orderId): ?int {
        $existing = db_query('SELECT id FROM job_orders WHERE order_id = ? ORDER BY id ASC LIMIT 1', 'i', [$orderId]);
        if (!empty($existing)) {
            return (int)$existing[0]['id'];
        }
        $order = db_query('SELECT * FROM orders WHERE order_id = ?', 'i', [$orderId]);
        if (empty($order)) {
            return null;
        }
        $order = $order[0];
        $customerId = (int)$order['customer_id'];
        $notes = $order['notes'] ?? '';

        $items = db_query(
            "SELECT oi.*, p.name AS product_name, p.category AS product_category
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.product_id
             WHERE oi.order_id = ?",
            'i',
            [$orderId]
        ) ?: [];

        if (empty($items)) {
            return null;
        }

        $firstJobId = null;
        foreach ($items as $item) {
            $custom = [];
            if (!empty($item['customization_data'])) {
                $decoded = json_decode($item['customization_data'], true);
                if (is_array($decoded)) {
                    $custom = $decoded;
                }
            }
            $pname = (string)($item['product_name'] ?? '');
            $pcat = (string)($item['product_category'] ?? '');
            $service_type = self::inferServiceTypeFromProduct($pcat, $pname);

            $dimensions = $custom['dimensions'] ?? $custom['Size'] ?? '';
            $width_ft = 0.0;
            $height_ft = 0.0;
            if ($dimensions && (strpos($dimensions, 'x') !== false || strpos($dimensions, '×') !== false)) {
                $d_parts = preg_split('/[x×]/u', strtolower($dimensions));
                $width_ft = (float)(trim($d_parts[0] ?? 0));
                $height_ft = (float)(trim($d_parts[1] ?? 0));
            }

            $job_title = get_service_name_from_customization($custom, $pname !== '' ? $pname : $service_type);
            $job_qty = (int)($item['quantity'] ?? 1);
            $unit_price = (float)($item['unit_price'] ?? 0);

            try {
                $jid = self::createOrder([
                    'order_id'        => $orderId,
                    'customer_id'     => $customerId,
                    'job_title'       => $job_title,
                    'service_type'    => $service_type,
                    'width_ft'        => $width_ft,
                    'height_ft'       => $height_ft,
                    'quantity'        => $job_qty,
                    'total_sqft'      => $width_ft * $height_ft * $job_qty,
                    'price_per_sqft'  => null,
                    'price_per_piece' => null,
                    'estimated_total' => $unit_price * $job_qty,
                    'notes'           => $notes,
                    'due_date'        => null,
                    'priority'        => 'NORMAL',
                    'artwork_path'    => null,
                    'created_by'      => null,
                ]);
                if ($firstJobId === null) {
                    $firstJobId = (int)$jid;
                }
            } catch (Throwable $e) {
                error_log('PrintFlow ensureJobsForStoreOrder: order ' . $orderId . ' — ' . $e->getMessage());
            }
        }

        return $firstJobId;
    }

    /**
     * Assign a specific roll to a job order material item.
     */
    public static function assignRoll($jomId, $rollId) {
        $sql = "UPDATE job_order_materials SET roll_id = ? WHERE id = ? AND deducted_at IS NULL";
        return db_execute($sql, 'ii', [$rollId, $jomId]);
    }

    /**
     * Add a material to a job order with advanced metadata.
     */
    public static function addMaterial($orderId, $itemId, $qty, $uom, $rollId = null, $notes = '', $metadata = null, $orderType = null) {
        if ($orderType === null) {
            // Auto-detect based on existence in job_orders table
            $isJob = db_query("SELECT id FROM job_orders WHERE id = ?", 'i', [$orderId]);
            $orderType = (!empty($isJob)) ? 'JOB' : 'ORDER';
        }
        
        $item = InventoryManager::getItem($itemId);
        if (!$item) throw new Exception("Item not found.");
        
        $cost = $item['unit_cost'] ?? 0;
        $track_by_roll = $item['track_by_roll'];
        
        // Calculate computed length if track_by_roll
        $computed_len = 0;
        if ($track_by_roll) {
            // For roll-based, if uom is ft, quantity is often the length
            // But we might have separate height/qty in metadata
            if (isset($metadata['height_ft'])) {
                $computed_len = $metadata['height_ft'] * $qty;
            } else {
                $computed_len = ($uom === 'ft') ? $qty : 0;
            }
        }

        $metaJson = $metadata ? json_encode($metadata) : null;

        $colId = ($orderType === 'ORDER') ? 'std_order_id' : 'job_order_id';

        // Check for duplicates
        if ($rollId) {
            $exists = db_query("SELECT id FROM job_order_materials WHERE $colId = ? AND item_id = ? AND roll_id = ?", 'iii', [$orderId, $itemId, $rollId]);
        } else {
            $exists = db_query("SELECT id FROM job_order_materials WHERE $colId = ? AND item_id = ? AND roll_id IS NULL", 'ii', [$orderId, $itemId]);
        }
        
        if (!empty($exists)) {
            return $exists[0]['id']; // Return existing ID instead of creating duplicate
        }

        $sql = "INSERT INTO job_order_materials ($colId, item_id, roll_id, quantity, uom, computed_required_length_ft, unit_cost_at_assignment, notes, metadata) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        return db_execute($sql, 'iiidsddss', [$orderId, $itemId, $rollId, $qty, $uom, $computed_len, $cost, $notes, $metaJson]);
    }

    /**
     * Preview the impact on a specific roll or item before assignment.
     */
    public static function previewImpact($itemId, $rollId = null, $qty = 0, $height = 0) {
        $item = InventoryManager::getItem($itemId);
        if (!$item) return null;

        $totalStock = InventoryManager::getStockOnHand($itemId);
        $required = 0;

        if ($item['track_by_roll']) {
            $required = $height > 0 ? ($height * $qty) : $qty;
            if ($rollId) {
                $roll = RollService::getRoll($rollId);
                return [
                    'item_name' => $item['name'],
                    'roll_code' => $roll['roll_code'] ?? "#$rollId",
                    'before' => $roll['remaining_length_ft'],
                    'required' => $required,
                    'after' => $roll['remaining_length_ft'] - $required,
                    'is_sufficient' => ($roll['remaining_length_ft'] >= $required)
                ];
            }
        } else {
            $required = $qty;
        }

        return [
            'item_name' => $item['name'],
            'before' => $totalStock,
            'required' => $required,
            'after' => $totalStock - $required,
            'is_sufficient' => ($totalStock >= $required)
        ];
    }

    /**
     * Remove a material from a job order.
     */
    public static function removeMaterial($jomId) {
        // Can only remove if not yet deducted
        $sql = "DELETE FROM job_order_materials WHERE id = ? AND deducted_at IS NULL";
        return db_execute($sql, 'i', [$jomId]);
    }

    /**
     * Get material readiness status for an order.
     */
    public static function getMaterialReadiness($orderId) {
        $order = self::getOrder($orderId);
        if (!$order) return 'MISSING';

        $status = 'READY';
        foreach ($order['materials'] as $m) {
            $stock = InventoryManager::getStockOnHand($m['item_id']);
            $required = $m['track_by_roll'] ? $m['computed_required_length_ft'] : $m['quantity'];
            
            if ($stock <= 0) {
                return 'MISSING';
            } elseif ($stock < $required) {
                $status = 'LOW';
            }

            // Check lamination stock readiness if printing sticker
            if (isset($m['metadata']['lamination_item_id']) && $m['metadata']['lamination_length_ft'] > 0) {
                $lamStock = InventoryManager::getStockOnHand($m['metadata']['lamination_item_id']);
                if ($lamStock <= 0) {
                    return 'MISSING';
                } elseif ($lamStock < $m['metadata']['lamination_length_ft']) {
                    $status = 'LOW';
                }
            }
        }

        if (isset($order['ink_usage']) && !empty($order['ink_usage'])) {
            // Check ink stock readiness
            foreach ($order['ink_usage'] as $ink) {
                $stock = InventoryManager::getStockOnHand($ink['item_id']);
                if ($stock < $ink['quantity_used']) {
                    return 'MISSING'; // Missing ink should block completion
                }
            }
        }

        return $status;
    }

    /**
     * Calculate internal material cost for a job.
     */
    public static function calculateJobCost($orderId) {
        $sql = "SELECT SUM(
                    (CASE WHEN computed_required_length_ft > 0 THEN computed_required_length_ft ELSE quantity END) 
                    * unit_cost_at_assignment
                ) as total_cost 
                FROM job_order_materials WHERE job_order_id = ?";
        $res = db_query($sql, 'i', [$orderId]);
        return $res[0]['total_cost'] ?? 0;
    }

    /**
     * Set job order status and trigger logic.
     */
    public static function updateStatus($orderId, $newStatus, $machineId = null, $reason = '') {
        global $conn;
        
        $order = db_query("SELECT * FROM job_orders WHERE id = ?", 'i', [$orderId]);
        if (!$order) throw new Exception("Order not found.");
        $order = $order[0];

        $conn->begin_transaction();
        try {
            // Materials are now handled at TO_PAY stage, so we don't block APPROVED.
            if ($newStatus === 'IN_PRODUCTION') {
                // Payment check removed per user request
            }

            if ($newStatus === 'COMPLETED') {
                // Only PAID orders can be marked COMPLETED
                $payment = strtoupper((string)($order['payment_status'] ?? ''));
                if ($payment !== 'PAID') {
                    throw new Exception('Cannot mark as Completed: payment must be Paid. Current payment status: ' . ($order['payment_status'] ?? 'Unpaid'));
                }
                self::processDeductions($orderId);
                if ($order['customer_id']) {
                    self::updateCustomerStatus($order['customer_id']);
                }
            }

            $sql = "UPDATE job_orders SET status = ?, machine_id = ? WHERE id = ?";
            db_execute($sql, 'sii', [$newStatus, $machineId ?: $order['machine_id'], $orderId]);

            // Sync status back to standard orders table
            if (!empty($order['order_id'])) {
                $order_status_map = [
                    'PENDING'       => 'Pending Approval',
                    'APPROVED'      => 'Approved',
                    'TO_PAY'        => 'To Pay',
                    'VERIFY_PAY'    => 'To Verify',
                    'IN_PRODUCTION' => 'In Production',
                    'TO_RECEIVE'    => 'Ready for Pickup',
                    'COMPLETED'     => 'Completed',
                    'CANCELLED'     => 'Cancelled'
                ];
                $storeStatus = $order_status_map[strtoupper($newStatus)] ?? $newStatus;
                
                $sql_parts = ["status = ?", "updated_at = NOW()"];
                $params = [$storeStatus];
                $types = "s";
                
                if (strtoupper($newStatus) === 'APPROVED') {
                    $sql_parts[] = "design_status = 'Approved'";
                    $sql_parts[] = "revision_reason = ''";
                }
                
                $params[] = $order['order_id'];
                $types .= "i";
                
                db_execute("UPDATE orders SET " . implode(', ', $sql_parts) . " WHERE order_id = ?", $types, $params);
            }

            // Send real-time notification to customer on every status change
            if (!empty($order['customer_id'])) {
                NotificationService::sendJobOrderNotification(
                    (int)$order['customer_id'],
                    $orderId,
                    $newStatus,
                    null,
                    $reason
                );
            }

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * Idempotent deduction for all materials in an order.
     */
    private static function processDeductions($orderId) {
        $materials = db_query("SELECT * FROM job_order_materials WHERE job_order_id = ? AND deducted_at IS NULL", 'i', [$orderId]);
        if ($materials) {
            foreach ($materials as $m) {
                $item = InventoryManager::getItem($m['item_id']);
                if (!$item) continue;

                if ($item['track_by_roll']) {
                    $lengthNeeded = (float)($m['computed_required_length_ft'] ?: $m['quantity']);

                    if ($lengthNeeded <= 0) {
                        // Nothing to deduct — mark as processed and continue
                        db_execute("UPDATE job_order_materials SET deducted_at = NOW() WHERE id = ?", 'i', [$m['id']]);
                        continue;
                    }

                    try {
                        // Use unified FIFO deduction logic
                        RollService::deductFIFO(
                            $m['item_id'],
                            $lengthNeeded,
                            'JOB_ORDER',
                            $orderId,
                            "Deducted for Job #{$orderId}"
                        );
                    } catch (Exception $e) {
                        // FIFO failed (e.g. insufficient rolls) — propagate error to prevent
                        // silent inventory corruption. Staff must add roll stock first.
                        throw new Exception(
                            "Cannot complete Job #{$orderId}: Roll stock depleted for '{$item['name']}'. " .
                            "Please receive new stock before marking complete. (" . $e->getMessage() . ")"
                        );
                    }
                    
                    // --- PRINTED STICKER: LAMINATION DEDUCTION ---
                    if (isset($m['metadata']['lamination_item_id']) && $m['metadata']['lamination_length_ft'] > 0) {
                        $lamItem = InventoryManager::getItem($m['metadata']['lamination_item_id']);
                        if ($lamItem) {
                            try {
                                if ($lamItem['track_by_roll']) {
                                    RollService::deductFIFO(
                                        $lamItem['id'],
                                        $m['metadata']['lamination_length_ft'],
                                        'JOB_ORDER',
                                        $orderId,
                                        "Lamination deducted for Job #{$orderId}"
                                    );
                                } else {
                                    InventoryManager::issueStock(
                                        $lamItem['id'], 
                                        $m['metadata']['lamination_length_ft'], 
                                        $lamItem['unit_of_measure'], 
                                        'JOB_ORDER', 
                                        $orderId, 
                                        "Lamination deducted for Job #{$orderId}"
                                    );
                                }
                            } catch (Exception $e) {
                                throw new Exception(
                                    "Cannot complete Job #{$orderId}: Lamination stock depleted for '{$lamItem['name']}'. " .
                                    "Please receive new stock before marking complete. (" . $e->getMessage() . ")"
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
                        'JOB_ORDER', 
                        $orderId, 
                        "Deducted for Job #{$orderId}"
                    );
                    // Mark as deducted
                    db_execute("UPDATE job_order_materials SET deducted_at = NOW() WHERE id = ?", 'i', [$m['id']]);
                }
            }
        }

        // Process Ink Deductions
        $inks = db_query("SELECT * FROM job_order_ink_usage WHERE job_order_id = ?", 'i', [$orderId]);
        if ($inks) {
            // Delete previous internal state notes for ink to avoid duplicates if completed twice somehow, although shouldn't happen
            foreach ($inks as $ink) {
                // Determine item from inventory
                $inkItem = InventoryManager::getItem($ink['item_id']);
                if (!$inkItem) continue;

                InventoryManager::issueStock(
                    $ink['item_id'],
                    $ink['quantity_used'],
                    $inkItem['unit_of_measure'] ?? 'bottle',
                    'JOB_ORDER',
                    $orderId,
                    "{$ink['ink_color']} ink used for Job #{$orderId}"
                );
            }
        }
    }

    /**
     * Store order line items + design URLs for staff modal (same shape as job_orders_api get_regular_order).
     */
    public static function getStoreOrderItemsPayload(int $storeOrderId): array {
        if ($storeOrderId <= 0) {
            return ['items' => [], 'width_ft' => '1', 'height_ft' => '1', 'service_type' => ''];
        }
        $items = db_query(
            "SELECT oi.*, p.name as product_name, p.category
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.product_id
             WHERE oi.order_id = ?",
            'i',
            [$storeOrderId]
        ) ?: [];
        require_once __DIR__ . '/order_ui_helper.php';
        $items_out = [];
        $first_custom = [];
        $total_qty = 0;
        $width_ft = '1';
        $height_ft = '1';
        foreach ($items as $item) {
            $custom = json_decode($item['customization_data'] ?? '{}', true) ?: [];
            if (empty($first_custom)) {
                $first_custom = $custom;
            }
            $total_qty += (int)$item['quantity'];
            if (!empty($custom['width']) && !empty($custom['height'])) {
                $width_ft = (string)$custom['width'];
                $height_ft = (string)$custom['height'];
            } elseif (!empty($custom['dimensions'])) {
                $d = $custom['dimensions'];
                if (is_string($d) && preg_match('/^(\d+)\s*[x×]\s*(\d+)$/i', $d, $m)) {
                    $width_ft = $m[1];
                    $height_ft = $m[2];
                } else {
                    $width_ft = (string)$d;
                    $height_ft = '';
                }
            }
            $name = $item['product_name'] ?: 'Custom Order';
            if (!empty($custom['sintra_type'])) {
                $name = 'Sintra Board - ' . $custom['sintra_type'];
            } elseif (!empty($custom['Sintra Type'])) {
                $name = 'Sintra Board - ' . $custom['Sintra Type'];
            } elseif (!empty($custom['tarp_size'])) {
                $name = 'Tarpaulin Printing - ' . $custom['tarp_size'];
            } elseif (!empty($custom['Tarp Size'])) {
                $name = 'Tarpaulin Printing - ' . $custom['Tarp Size'];
            } elseif (isset($custom['width']) && isset($custom['height']) && (isset($custom['finish']) || isset($custom['with_eyelets']))) {
                $name = 'Tarpaulin Printing (' . $custom['width'] . 'x' . $custom['height'] . ' ft)';
            } elseif (isset($custom['vinyl_type']) || isset($custom['print_placement'])) {
                $name = 'T-Shirt Printing';
            } elseif (isset($custom['sticker_type']) || isset($custom['Sticker Type'])) {
                $name = 'Decals/Stickers (Print/Cut)';
            } else if (empty($item['product_name']) || in_array(strtolower(trim((string)$name)), ['custom order', 'customer order', 'service order', 'order item', 'sticker pack'])) {
                $name = get_service_name_from_customization($custom, $item['product_name'] ?: 'Custom Order');
            }
            $items_out[] = [
                'order_item_id'   => $item['order_item_id'],
                'product_name'    => $name,
                'quantity'        => (int)$item['quantity'],
                'customization'   => $custom,
                'design_url'      => (!empty($item['design_image']) || !empty($item['design_file']))
                    ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] : null,
                'reference_url'   => !empty($item['reference_image_file'])
                    ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] . '&field=reference' : null,
            ];
        }
        $service_name = get_service_name_from_customization($first_custom, $items_out[0]['product_name'] ?? 'Custom Order');
        return [
            'items'        => $items_out,
            'width_ft'     => $width_ft,
            'height_ft'    => $height_ft,
            'service_type' => $service_name,
            'line_qty'     => $total_qty,
        ];
    }

    public static function getOrder($id) {
        $sql = "SELECT jo.*, 
                       c.customer_type, c.transaction_count,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_full_name,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       c.email as customer_email,
                       c.profile_picture,
                       COALESCE(NULLIF(TRIM(c.contact_number), ''), NULLIF(TRIM(c.email), '')) AS customer_contact,
                       TRIM(CONCAT_WS(', ', NULLIF(TRIM(c.street_address), ''), NULLIF(TRIM(c.barangay), ''), NULLIF(TRIM(c.city), ''))) AS customer_address,
                       COALESCE(jo.branch_id, ord.branch_id) AS branch_display_id,
                       b.branch_name AS branch_name
                FROM job_orders jo
                LEFT JOIN customers c ON jo.customer_id = c.customer_id
                LEFT JOIN orders ord ON ord.order_id = jo.order_id
                LEFT JOIN branches b ON b.id = COALESCE(jo.branch_id, ord.branch_id)
                WHERE jo.id = ?";
        
        $order = db_query($sql, 'i', [$id]);
        if (!$order) return null;
        $order = $order[0];
        
        // Format customer picture with full path
        if (!empty($order['profile_picture'])) {
            $order['customer_picture'] = '/printflow/public/assets/uploads/profiles/' . $order['profile_picture'];
        } else {
            $order['customer_picture'] = '';
        }

        $storeOid = (int)($order['order_id'] ?? 0);
        if ($storeOid > 0) {
            $payload = self::getStoreOrderItemsPayload($storeOid);
            $order['items'] = $payload['items'];
            $w = (string)($order['width_ft'] ?? '');
            $h = (string)($order['height_ft'] ?? '');
            if (($w === '' || $w === '0' || $w === '1') && ($h === '' || $h === '0' || $h === '1')) {
                $order['width_ft'] = $payload['width_ft'];
                $order['height_ft'] = $payload['height_ft'];
            }
            if (!empty($payload['service_type']) && (empty($order['service_type']) || $order['service_type'] === 'Custom Order')) {
                $order['service_type'] = $payload['service_type'];
            }
            $st = db_query('SELECT * FROM orders WHERE order_id = ? LIMIT 1', 'i', [$storeOid]);
            if (!empty($st)) {
                $row = $st[0];
                $proof = $row['payment_proof_path'] ?? $row['payment_proof'] ?? '';
                if ($proof !== '' && $proof !== null) {
                    $order['payment_proof_path'] = $proof;
                }
                $order['payment_submitted_amount'] = (float)($row['downpayment_amount'] ?? 0);
                $order['payment_proof_uploaded_at'] = $row['payment_submitted_at'] ?? null;
                $order['store_order_notes'] = (string)($row['notes'] ?? '');
                $order['revision_reason'] = (string)($row['revision_reason'] ?? '');
                $order['design_status'] = (string)($row['design_status'] ?? '');
                if (empty($order['estimated_total']) || (float)$order['estimated_total'] <= 0) {
                    $order['estimated_total'] = (float)($row['total_amount'] ?? 0);
                }
                $order['amount_paid'] = (float)($row['amount_paid'] ?? 0);
                if (strtoupper((string)($row['payment_status'] ?? '')) === 'PAID') {
                    $order['amount_paid'] = (float)($row['total_amount'] ?? $order['amount_paid']);
                }
            }
        } else {
            $order['items'] = [];
        }

        $order['materials'] = db_query(
            "SELECT m.*, i.name as item_name, i.track_by_roll, i.category_id, r.roll_code,
                    (SELECT SUM(IF(direction='IN', quantity, -quantity)) FROM inventory_transactions WHERE item_id = m.item_id) as total_stock
             FROM job_order_materials m 
             JOIN inv_items i ON m.item_id = i.id 
             LEFT JOIN inv_rolls r ON m.roll_id = r.id 
             WHERE m.job_order_id = ?", 
            'i', [$id]
        ) ?: [];

        // Parse JSON metadata for each material
        foreach ($order['materials'] as &$m) {
            $m['metadata'] = $m['metadata'] ? json_decode($m['metadata'], true) : null;
        }

        $order['files'] = db_query(
            "SELECT id, file_path, file_name, file_type, uploaded_at FROM job_order_files WHERE job_order_id = ?",
            'i', [$id]
        ) ?: [];

        $order['ink_usage'] = db_query(
            "SELECT u.*, i.name as item_name
             FROM job_order_ink_usage u
             JOIN inv_items i ON u.item_id = i.id
             WHERE job_order_id = ?",
             'i', [$id]
        ) ?: [];

        return $order;
    }

    /**
     * Calculate required payment based on customer classification.
     */
    public static function calculateRequiredPayment($customerId, $totalAmount) {
        if (!$customerId) return $totalAmount; // Walk-in is 100%

        $res = db_query("SELECT customer_type FROM customers WHERE customer_id = ?", 'i', [$customerId]);
        if (!$res) return $totalAmount;

        $type = $res[0]['customer_type'];
        if ($type === 'REGULAR') {
            return $totalAmount * 0.5; // 50% for regulars
        }
        return $totalAmount; // 100% for new
    }

    /**
     * Update customer stats and classification.
     */
    public static function updateCustomerStatus($customerId) {
        // Increment count
        db_execute("UPDATE customers SET transaction_count = transaction_count + 1 WHERE customer_id = ?", 'i', [$customerId]);
        
        // Check for upgrade
        $res = db_query("SELECT transaction_count FROM customers WHERE customer_id = ?", 'i', [$customerId]);
        if ($res && $res[0]['transaction_count'] >= 5) {
            db_execute("UPDATE customers SET customer_type = 'REGULAR' WHERE customer_id = ?", 'i', [$customerId]);
        }
    }

    /**
     * Pause production for a job order.
     */
    public static function pauseProduction($orderId, $notes = '') {
        $sql = "UPDATE job_orders SET status = 'PENDING', notes = CONCAT(IFNULL(notes, ''), '\n[PAUSED] ', ?) WHERE id = ?";
        return db_execute($sql, 'si', [$notes, $orderId]);
    }

    /**
     * Cancel a job order.
     */
    public static function cancelOrder($orderId, $reason = '') {
        $sql = "UPDATE job_orders SET status = 'CANCELLED', notes = CONCAT(IFNULL(notes, ''), '\n[CANCELLED] ', ?) WHERE id = ?";
        return db_execute($sql, 'si', [$reason, $orderId]);
    }

    /**
     * Save Ink Usage for an Order
     */
    public static function saveInkUsage($orderId, $inkData, $orderType = null) {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn) return false;

        if ($orderType === null) {
            // Auto-detect based on existence in job_orders table
            $isJob = db_query("SELECT id FROM job_orders WHERE id = ?", 'i', [$orderId]);
            $orderType = (!empty($isJob)) ? 'JOB' : 'ORDER';
        }

        $colId = ($orderType === 'ORDER') ? 'std_order_id' : 'job_order_id';

        $conn->begin_transaction();
        try {
            // Remove existing ink records for easy replace strategy
            db_execute("DELETE FROM job_order_ink_usage WHERE $colId = ?", 'i', [$orderId]);

            if (!empty($inkData) && is_array($inkData)) {
                $sql = "INSERT INTO job_order_ink_usage ($colId, item_id, ink_color, quantity_used) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    foreach ($inkData as $ink) {
                        $itemId = (int)($ink['item_id'] ?? 0);
                        $color = sanitize($ink['color'] ?? '');
                        $qty = (float)($ink['quantity'] ?? 0);

                        if ($itemId > 0 && $qty > 0 && !empty($color)) {
                            $stmt->bind_param('iisd', $orderId, $itemId, $color, $qty);
                            $stmt->execute();
                        }
                    }
                    $stmt->close();
                }
            }
            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
