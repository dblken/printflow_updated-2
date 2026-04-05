<?php
/**
 * Add video_url column to services table
 */
require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $check = $conn->query("SHOW COLUMNS FROM services LIKE 'video_url'");
    
    if ($check->num_rows === 0) {
        $sql = "ALTER TABLE services ADD COLUMN video_url VARCHAR(500) NULL AFTER hero_image";
        
        if ($conn->query($sql)) {
            echo "✓ Successfully added video_url column to services table\n";
        } else {
            echo "✗ Error: " . $conn->error . "\n";
        }
    } else {
        echo "ℹ video_url column already exists\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
