<?php
/**
 * Migration: Add unit column to service_field_configs table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $result = db_query("SHOW COLUMNS FROM service_field_configs LIKE 'unit'");
    
    if (empty($result)) {
        echo "Adding 'unit' column to service_field_configs table...\n";
        
        db_execute("
            ALTER TABLE service_field_configs 
            ADD COLUMN unit VARCHAR(10) DEFAULT 'ft' AFTER default_value
        ");
        
        echo "✓ Successfully added 'unit' column!\n";
    } else {
        echo "✓ Column 'unit' already exists.\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
