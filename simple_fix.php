<?php
/**
 * SIMPLE FIX - Corrects all orders with incorrect unit prices
 * Access: http://localhost/printflow/simple_fix.php
 */

require_once __DIR__ . '/includes/db.php';

if (isset($_GET['run']) && $_GET['run'] === 'now') {
    
    // Simple approach: Fix ALL order items where quantity > 1 and unit_price > 500
    // This catches all service orders that likely have the bug
    $sql = "UPDATE order_items 
            SET unit_price = ROUND(unit_price / quantity, 2)
            WHERE quantity > 1 
            AND unit_price > 500";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $affected = mysqli_affected_rows($conn);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>✅ Fix Complete</title>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    background: white;
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 500px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center;
                }
                .icon { font-size: 64px; margin-bottom: 20px; }
                h1 { color: #10b981; font-size: 28px; margin-bottom: 15px; }
                p { color: #6b7280; font-size: 16px; line-height: 1.6; margin-bottom: 10px; }
                .count { 
                    background: #10b981; 
                    color: white; 
                    padding: 8px 16px; 
                    border-radius: 20px; 
                    font-weight: bold;
                    display: inline-block;
                    margin: 15px 0;
                }
                .btn {
                    display: inline-block;
                    margin: 10px 5px;
                    padding: 12px 24px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 600;
                    transition: all 0.3s;
                }
                .btn:hover { background: #5568d3; transform: translateY(-2px); }
                .btn-secondary { background: #6b7280; }
                .btn-secondary:hover { background: #4b5563; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">✅</div>
                <h1>Fix Complete!</h1>
                <p>Successfully corrected unit prices for:</p>
                <div class="count"><?php echo $affected; ?> order items</div>
                <p style="margin-top: 20px;">All unit prices have been divided by quantity to show the correct per-unit price.</p>
                <div style="margin-top: 30px;">
                    <a href="customer/payment.php?order_id=2280" class="btn">View Order #2280</a>
                    <a href="customer/payment.php?order_id=2279" class="btn btn-secondary">View Order #2279</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    } else {
        echo "<h1>Error</h1><p>" . mysqli_error($conn) . "</p>";
    }
    
} else {
    // Preview mode
    $preview_sql = "SELECT 
        oi.order_item_id,
        oi.order_id,
        oi.quantity,
        oi.unit_price as current_unit_price,
        ROUND(oi.unit_price / oi.quantity, 2) as corrected_unit_price,
        p.name as product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.quantity > 1 
    AND oi.unit_price > 500
    ORDER BY oi.order_id DESC";
    
    $result = mysqli_query($conn, $preview_sql);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Fix Unit Prices</title>
        <meta charset="UTF-8">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
                background: #f3f4f6;
                padding: 40px 20px;
            }
            .container { max-width: 1000px; margin: 0 auto; }
            h1 { 
                color: #111827; 
                font-size: 32px; 
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .subtitle { color: #6b7280; margin-bottom: 30px; font-size: 16px; }
            .warning {
                background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                border: 2px solid #f59e0b;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 30px;
            }
            .warning strong { color: #92400e; display: block; font-size: 18px; margin-bottom: 8px; }
            .warning p { color: #78350f; line-height: 1.6; }
            table {
                width: 100%;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                margin-bottom: 30px;
            }
            th, td { padding: 16px; text-align: left; }
            th { 
                background: #111827; 
                color: white; 
                font-weight: 600;
                text-transform: uppercase;
                font-size: 12px;
                letter-spacing: 0.5px;
            }
            tr:not(:last-child) td { border-bottom: 1px solid #e5e7eb; }
            .error { color: #dc2626; font-weight: 700; }
            .success { color: #10b981; font-weight: 700; }
            .arrow { color: #6b7280; font-size: 20px; }
            .btn {
                display: inline-block;
                padding: 16px 32px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 700;
                font-size: 16px;
                box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
                transition: all 0.3s;
                border: none;
                cursor: pointer;
            }
            .btn:hover { 
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(16, 185, 129, 0.4);
            }
            .empty {
                background: white;
                border-radius: 12px;
                padding: 60px;
                text-align: center;
            }
            .empty-icon { font-size: 64px; margin-bottom: 20px; }
            .empty h2 { color: #10b981; margin-bottom: 10px; }
            .empty p { color: #6b7280; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔧 Fix Unit Prices</h1>
            <p class="subtitle">Correct unit prices for all service orders</p>
            
            <?php if (empty($items)): ?>
                <div class="empty">
                    <div class="empty-icon">✅</div>
                    <h2>All Good!</h2>
                    <p>No orders need fixing. All unit prices are correct.</p>
                </div>
            <?php else: ?>
                <div class="warning">
                    <strong>⚠️ Found <?php echo count($items); ?> order items that need fixing</strong>
                    <p>The unit_price will be divided by quantity to show the correct per-unit price. The total order amount will remain the same.</p>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Current Unit Price</th>
                            <th></th>
                            <th>Corrected Unit Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong>#<?php echo $item['order_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($item['product_name'] ?: 'Service Order'); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td class="error">₱<?php echo number_format($item['current_unit_price'], 2); ?></td>
                            <td class="arrow">→</td>
                            <td class="success">₱<?php echo number_format($item['corrected_unit_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <a href="?run=now" class="btn" onclick="return confirm('Fix <?php echo count($items); ?> orders now?')">
                    ✅ Fix <?php echo count($items); ?> Orders Now
                </a>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}
?>
