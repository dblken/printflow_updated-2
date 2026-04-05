<?php
/**
 * Create Sample Job Orders Data for Customization Usage Chart
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin']);

try {
    // Check if job_orders table exists and has data
    $existingCount = db_query("SELECT COUNT(*) as cnt FROM job_orders")[0]['cnt'] ?? 0;
    
    if ($existingCount > 0) {
        echo "<h2>Job Orders Table already has {$existingCount} records</h2>";
        echo "<p><a href='dashboard.php'>Back to Dashboard</a></p>";
        exit;
    }
    
    // Create sample job orders data for the last 12 months
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
        
        // Random number of orders per month (5-25)
        $ordersThisMonth = rand(5, 25);
        
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
    
    echo "<h2>Successfully created {$insertedCount} sample job orders!</h2>";
    echo "<p>The customization usage chart should now display data.</p>";
    echo "<p><a href='dashboard.php'>View Dashboard</a></p>";
    echo "<p><a href='test_customization_chart.php'>Test Chart API</a></p>";
    
} catch (Exception $e) {
    echo "<h2>Error creating sample data:</h2>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>