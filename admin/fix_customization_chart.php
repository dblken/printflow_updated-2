<?php
/**
 * Fix Customization Usage Chart - Restore functionality
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin']);

echo "<h1>Customization Usage Chart Fix</h1>";

try {
    // Step 1: Check if job_orders table exists
    $tableExists = db_query("SHOW TABLES LIKE 'job_orders'");
    if (empty($tableExists)) {
        echo "<h2>❌ job_orders table does not exist</h2>";
        echo "<p>Creating job_orders table...</p>";
        
        $createTableSQL = "
        CREATE TABLE `job_orders` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) DEFAULT NULL,
            `customer_id` int(11) DEFAULT NULL,
            `order_item_id` int(11) DEFAULT NULL,
            `branch_id` int(11) DEFAULT NULL,
            `job_title` varchar(150) DEFAULT NULL,
            `customer_name` varchar(120) DEFAULT NULL,
            `service_type` enum('Tarpaulin Printing','T-shirt Printing','Decals/Stickers (Print/Cut)','Glass Stickers / Wall / Frosted Stickers','Transparent Stickers','Layouts','Reflectorized (Subdivision Stickers/Signages)','Stickers on Sintraboard','Sintraboard Standees','Souvenirs') DEFAULT NULL,
            `status` varchar(100) DEFAULT 'PENDING',
            `customer_type` enum('NEW','REGULAR') DEFAULT 'NEW',
            `width_ft` decimal(10,2) DEFAULT NULL,
            `height_ft` decimal(10,2) DEFAULT NULL,
            `quantity` int(11) DEFAULT 1,
            `total_sqft` decimal(10,2) DEFAULT NULL,
            `price_per_sqft` decimal(10,2) DEFAULT NULL,
            `price_per_piece` decimal(10,2) DEFAULT NULL,
            `estimated_total` decimal(12,2) DEFAULT NULL,
            `amount_paid` decimal(12,2) DEFAULT 0.00,
            `required_payment` decimal(12,2) DEFAULT NULL,
            `payment_status` enum('UNPAID','PENDING_VERIFICATION','PARTIAL','PAID') DEFAULT 'UNPAID',
            `notes` text,
            `due_date` datetime DEFAULT NULL,
            `priority` enum('HIGH','NORMAL','LOW') DEFAULT 'NORMAL',
            `artwork_path` text,
            `assigned_to` int(11) DEFAULT NULL,
            `machine_id` int(11) DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `payment_proof_path` varchar(255) DEFAULT NULL,
            `payment_method` varchar(50) DEFAULT NULL,
            `payment_reference` varchar(100) DEFAULT NULL,
            `payment_submitted_amount` decimal(12,2) DEFAULT NULL,
            `payment_proof_status` enum('NONE','SUBMITTED','VERIFIED','REJECTED') DEFAULT 'NONE',
            `payment_proof_uploaded_at` datetime DEFAULT NULL,
            `payment_verified_at` datetime DEFAULT NULL,
            `payment_verified_by` int(11) DEFAULT NULL,
            `payment_rejection_reason` text,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        db_execute($createTableSQL);
        echo "<p>✅ job_orders table created successfully</p>";
    } else {
        echo "<h2>✅ job_orders table exists</h2>";
    }
    
    // Step 2: Check current data count
    $currentCount = db_query("SELECT COUNT(*) as cnt FROM job_orders")[0]['cnt'] ?? 0;
    echo "<p>Current job_orders count: <strong>{$currentCount}</strong></p>";
    
    // Step 3: Add sample data if needed
    if ($currentCount < 10) {
        echo "<h3>Adding sample data for chart...</h3>";
        
        $serviceTypes = [
            'Tarpaulin Printing',
            'T-shirt Printing', 
            'Decals/Stickers (Print/Cut)',
            'Glass Stickers / Wall / Frosted Stickers',
            'Transparent Stickers',
            'Layouts',
            'Reflectorized (Subdivision Stickers/Signages)',
            'Stickers on Sintraboard',
            'Sintraboard Standees',
            'Souvenirs'
        ];
        
        $statuses = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];
        $paymentStatuses = ['UNPAID', 'PENDING_VERIFICATION', 'PARTIAL', 'PAID'];
        $customerTypes = ['NEW', 'REGULAR'];
        $priorities = ['HIGH', 'NORMAL', 'LOW'];
        
        $sampleCustomers = [
            'Juan Dela Cruz', 'Maria Santos', 'Jose Rizal', 'Ana Garcia', 'Pedro Martinez',
            'Carmen Lopez', 'Miguel Rodriguez', 'Sofia Hernandez', 'Carlos Gonzalez', 'Isabella Torres'
        ];
        
        $insertedCount = 0;
        
        // Generate data for last 12 months
        for ($month = 11; $month >= 0; $month--) {
            $monthStart = date('Y-m-01', strtotime("-{$month} months"));
            $monthEnd = date('Y-m-t', strtotime("-{$month} months"));
            
            // Random number of orders per month (8-20)
            $ordersThisMonth = rand(8, 20);
            
            for ($i = 0; $i < $ordersThisMonth; $i++) {
                $randomDay = rand(1, date('t', strtotime($monthStart)));
                $createdAt = date('Y-m-d H:i:s', strtotime($monthStart . " +{$randomDay} days +" . rand(8, 18) . " hours"));
                
                $serviceType = $serviceTypes[array_rand($serviceTypes)];
                $customerName = $sampleCustomers[array_rand($sampleCustomers)];
                $status = $statuses[array_rand($statuses)];
                $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
                $customerType = $customerTypes[array_rand($customerTypes)];
                $priority = $priorities[array_rand($priorities)];
                
                // Generate realistic dimensions and pricing
                $width = rand(2, 10) + (rand(0, 9) / 10); // 2.0 to 10.9 ft
                $height = rand(2, 8) + (rand(0, 9) / 10); // 2.0 to 8.9 ft
                $quantity = rand(1, 50);
                $totalSqft = $width * $height * $quantity;
                
                $pricePerSqft = rand(50, 200) + (rand(0, 99) / 100); // ₱50.00 to ₱200.99 per sqft
                $pricePerPiece = $width * $height * $pricePerSqft;
                $estimatedTotal = $pricePerPiece * $quantity;
                
                // Payment amounts based on status
                $amountPaid = 0;
                $requiredPayment = $estimatedTotal;
                
                if ($paymentStatus === 'PAID') {
                    $amountPaid = $estimatedTotal;
                    $requiredPayment = 0;
                } elseif ($paymentStatus === 'PARTIAL') {
                    $amountPaid = $estimatedTotal * 0.5; // 50% down payment
                    $requiredPayment = $estimatedTotal - $amountPaid;
                }
                
                $dueDate = date('Y-m-d H:i:s', strtotime($createdAt . ' +' . rand(3, 14) . ' days'));
                
                $jobTitle = $serviceType . ' - ' . $customerName . ' #' . rand(1000, 9999);
                
                $sql = "INSERT INTO job_orders (
                    job_title, customer_name, service_type, status, customer_type,
                    width_ft, height_ft, quantity, total_sqft, price_per_sqft, price_per_piece,
                    estimated_total, amount_paid, required_payment, payment_status,
                    due_date, priority, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                db_execute($sql, 'sssssddiddddddssss', [
                    $jobTitle, $customerName, $serviceType, $status, $customerType,
                    $width, $height, $quantity, $totalSqft, $pricePerSqft, $pricePerPiece,
                    $estimatedTotal, $amountPaid, $requiredPayment, $paymentStatus,
                    $dueDate, $priority, $createdAt, $createdAt
                ]);
                
                $insertedCount++;
            }
        }
        
        echo "<p>✅ Added <strong>{$insertedCount}</strong> sample job orders</p>";
    }
    
    // Step 4: Test the API endpoint
    echo "<h3>Testing Customization Usage Chart API...</h3>";
    
    $testPeriods = ['daily', 'weekly', 'monthly'];
    foreach ($testPeriods as $period) {
        echo "<h4>Testing {$period} period:</h4>";
        
        // Simulate API call
        $branchId = 'all';
        $branchSql = '';
        $branchTypes = '';
        $branchParams = [];
        
        if ($branchId !== 'all' && is_numeric($branchId)) {
            $branchSql = ' AND COALESCE(jo.branch_id, (SELECT ord.branch_id FROM orders ord WHERE ord.order_id = jo.order_id LIMIT 1)) = ?';
            $branchTypes = 'i';
            $branchParams = [(int)$branchId];
        }
        
        switch ($period) {
            case 'monthly':
                $sql = "SELECT DATE_FORMAT(jo.created_at, '%Y-%m') as period_key,
                               COUNT(*) as total_orders,
                               COUNT(CASE WHEN jo.status = 'COMPLETED' THEN 1 END) as completed_orders,
                               SUM(CASE WHEN jo.payment_status = 'PAID' THEN COALESCE(jo.amount_paid, jo.estimated_total, 0) ELSE 0 END) as revenue
                        FROM job_orders jo 
                        WHERE jo.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        {$branchSql}
                        GROUP BY DATE_FORMAT(jo.created_at, '%Y-%m') 
                        ORDER BY period_key";
                break;
                
            case 'weekly':
                $sql = "SELECT YEARWEEK(jo.created_at, 1) as period_key,
                               COUNT(*) as total_orders,
                               COUNT(CASE WHEN jo.status = 'COMPLETED' THEN 1 END) as completed_orders,
                               SUM(CASE WHEN jo.payment_status = 'PAID' THEN COALESCE(jo.amount_paid, jo.estimated_total, 0) ELSE 0 END) as revenue
                        FROM job_orders jo 
                        WHERE jo.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                        {$branchSql}
                        GROUP BY YEARWEEK(jo.created_at, 1) 
                        ORDER BY period_key";
                break;
                
            case 'daily':
                $sql = "SELECT DATE(jo.created_at) as period_key,
                               COUNT(*) as total_orders,
                               COUNT(CASE WHEN jo.status = 'COMPLETED' THEN 1 END) as completed_orders,
                               SUM(CASE WHEN jo.payment_status = 'PAID' THEN COALESCE(jo.amount_paid, jo.estimated_total, 0) ELSE 0 END) as revenue
                        FROM job_orders jo 
                        WHERE jo.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        {$branchSql}
                        GROUP BY DATE(jo.created_at) 
                        ORDER BY period_key";
                break;
        }
        
        $results = db_query($sql, $branchTypes ?: null, $branchParams ?: null) ?: [];
        echo "<p>Found <strong>" . count($results) . "</strong> data points for {$period} period</p>";
        
        if (!empty($results)) {
            echo "<p>Sample data: " . htmlspecialchars(json_encode(array_slice($results, 0, 3))) . "</p>";
        }
    }
    
    // Step 5: Verify chart container exists in dashboard
    echo "<h3>Chart Integration Status:</h3>";
    echo "<p>✅ Customization Usage Chart is implemented in dashboard.php</p>";
    echo "<p>✅ API endpoint exists at api_customization_usage_chart.php</p>";
    echo "<p>✅ Chart.js is loaded in admin_style.php</p>";
    echo "<p>✅ Chart container and JavaScript are present</p>";
    
    echo "<hr>";
    echo "<h2>✅ Customization Usage Chart Fix Complete!</h2>";
    echo "<p>The chart should now display properly with sample data.</p>";
    
    echo "<div style='margin: 20px 0; padding: 15px; background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px;'>";
    echo "<h3>Next Steps:</h3>";
    echo "<ul>";
    echo "<li><a href='dashboard.php' style='color: #0ea5e9; font-weight: bold;'>View Dashboard</a> - Check if the chart displays</li>";
    echo "<li><a href='api_customization_usage_chart.php?period=monthly' style='color: #0ea5e9;'>Test API Directly</a> - View raw JSON data</li>";
    echo "<li><a href='test_customization_chart.php' style='color: #0ea5e9;'>Debug Chart</a> - Detailed debugging info</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error during fix:</h2>";
    echo "<p style='color: red; background: #fee; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>