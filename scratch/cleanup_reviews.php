<?php
require_once __DIR__ . '/../includes/db.php';

echo "Cleaning up reviews..." . PHP_EOL;

// Fix reviews for custom orders to point to the correct service page
$sql = "UPDATE reviews r
        JOIN orders o ON r.order_id = o.order_id
        SET r.reference_id = o.reference_id, 
            r.review_type = 'custom'
        WHERE o.order_type = 'custom' 
        AND o.reference_id IS NOT NULL 
        AND o.reference_id > 0";

$affected = db_execute($sql);

echo "Updated $affected reviews for custom orders." . PHP_EOL;

// Also fix standard orders that were incorrectly tagged as 'custom' or had wrong ref_id (unlikely but good for consistency)
// But wait, the priority is fixing the service redirects.

echo "Done." . PHP_EOL;
