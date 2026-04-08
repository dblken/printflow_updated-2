<?php
/**
 * Fix Unit Prices in Order Items
 * This script corrects order_items where unit_price was incorrectly saved as total price
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$fixed_count = 0;
$errors = [];

// Get all order items where unit_price might be incorrect
// (unit_price equals subtotal, meaning it was saved as total instead of per-unit)
$suspicious_items = db_query("
    SELECT 
        oi.order_item_id,
        oi.order_id,
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.unit_price / oi.quantity as calculated_unit_price,
        p.name as product_name,
        p.price as product_base_price
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.quantity > 1
    AND oi.unit_price > 1000
    ORDER BY oi.order_id DESC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_prices'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        foreach ($suspicious_items as $item) {
            $correct_unit_price = $item['unit_price'] / $item['quantity'];
            
            // Update the unit_price
            $result = db_execute(
                "UPDATE order_items SET unit_price = ? WHERE order_item_id = ?",
                'di',
                [$correct_unit_price, $item['order_item_id']]
            );
            
            if ($result) {
                $fixed_count++;
            } else {
                $errors[] = "Failed to fix order item #{$item['order_item_id']}";
            }
        }
        
        $_SESSION['success'] = "Fixed {$fixed_count} order items with incorrect unit prices.";
        redirect('fix_unit_prices.php');
    }
}

$page_title = 'Fix Unit Prices - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Fix Unit Prices in Order Items</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Suspicious Order Items</h2>
            <p class="text-gray-600 mb-4">
                These order items have unit_price values that appear to be total prices (unit_price × quantity).
                The script will divide the unit_price by quantity to get the correct per-unit price.
            </p>
            
            <?php if (empty($suspicious_items)): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
                    No suspicious order items found. All unit prices appear to be correct.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Unit Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Correct Unit Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($suspicious_items as $item): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm">#<?php echo $item['order_id']; ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown'); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo $item['quantity']; ?></td>
                                    <td class="px-4 py-3 text-sm font-bold text-red-600">
                                        <?php echo format_currency($item['unit_price']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-green-600">
                                        <?php echo format_currency($item['calculated_unit_price']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php echo format_currency($item['unit_price'] * $item['quantity']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <form method="POST" class="mt-6">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="fix_prices" class="btn-primary">
                        Fix <?php echo count($suspicious_items); ?> Order Items
                    </button>
                    <a href="dashboard.php" class="btn-secondary ml-4">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h3 class="font-bold text-yellow-800 mb-2">⚠️ Important Notes:</h3>
            <ul class="list-disc list-inside text-yellow-700 text-sm space-y-1">
                <li>This will update the unit_price by dividing it by the quantity</li>
                <li>The total order amount will remain the same (unit_price × quantity)</li>
                <li>Make sure to backup your database before running this fix</li>
                <li>This only affects orders with quantity > 1 and unit_price > ₱1,000</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
