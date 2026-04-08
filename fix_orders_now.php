<?php
/**
 * IMMEDIATE FIX - Run this once to fix all orders with incorrect unit prices
 * Access: http://localhost/printflow/fix_orders_now.php
 */

require_once __DIR__ . '/includes/db.php';

// Check if already run
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    
    // Fix all order items where unit_price is actually the total
    $sql = "UPDATE order_items 
            SET unit_price = ROUND(unit_price / quantity, 2)
            WHERE quantity > 1 
            AND unit_price > 500
            AND ABS(MOD(unit_price, quantity)) < 1";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $affected = mysqli_affected_rows($conn);
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Fix Complete</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
                .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='success'>
                <h2>✅ Fix Complete!</h2>
                <p><strong>{$affected} order items</strong> have been corrected.</p>
                <p>Unit prices have been divided by quantity to show the correct per-unit price.</p>
            </div>
            <a href='customer/payment.php?order_id=2279' class='btn'>View Order #2279</a>
            <a href='admin/dashboard.php' class='btn'>Go to Dashboard</a>
        </body>
        </html>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    
} else {
    // Show confirmation page
    $preview_sql = "SELECT 
        order_item_id,
        order_id,
        quantity,
        unit_price as current_unit_price,
        ROUND(unit_price / quantity, 2) as corrected_unit_price,
        ROUND(unit_price, 2) as current_total
    FROM order_items 
    WHERE quantity > 1 
    AND unit_price > 500
    AND ABS(MOD(unit_price, quantity)) < 1
    ORDER BY order_id DESC
    LIMIT 50";
    
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
        <style>
            body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
            h1 { color: #333; }
            .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #f8f9fa; font-weight: bold; }
            .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .btn:hover { background: #218838; }
            .error { color: #dc3545; font-weight: bold; }
            .success { color: #28a745; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>🔧 Fix Unit Prices in Orders</h1>
        
        <div class="warning">
            <strong>⚠️ This will fix <?php echo count($items); ?> order items</strong><br>
            The unit_price will be divided by quantity to show the correct per-unit price.<br>
            The total order amount will remain the same.
        </div>
        
        <?php if (empty($items)): ?>
            <p style="color: #28a745; font-weight: bold;">✅ No orders need fixing! All unit prices are correct.</p>
        <?php else: ?>
            <h3>Preview of Changes:</h3>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Quantity</th>
                        <th>Current Unit Price</th>
                        <th>→</th>
                        <th>Corrected Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>#<?php echo $item['order_id']; ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td class="error">₱<?php echo number_format($item['current_unit_price'], 2); ?></td>
                        <td>→</td>
                        <td class="success">₱<?php echo number_format($item['corrected_unit_price'], 2); ?></td>
                        <td>₱<?php echo number_format($item['current_total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <a href="?confirm=yes" class="btn" onclick="return confirm('Are you sure you want to fix these orders?')">
                ✅ Fix <?php echo count($items); ?> Orders Now
            </a>
        <?php endif; ?>
        
    </body>
    </html>
    <?php
}
?>
