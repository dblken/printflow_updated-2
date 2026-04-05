<?php
/**
 * Migration: Add allow_others column to service_field_configs table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $result = db_query("SHOW COLUMNS FROM service_field_configs LIKE 'allow_others'");
    
    if (empty($result)) {
        echo "Adding 'allow_others' column to service_field_configs table...\n";
        
        db_execute("
            ALTER TABLE service_field_configs 
            ADD COLUMN allow_others TINYINT(1) DEFAULT 1 AFTER unit
        ");
        
        echo "✓ Successfully added 'allow_others' column!\n";
    } else {
        echo "✓ Column 'allow_others' already exists.\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
