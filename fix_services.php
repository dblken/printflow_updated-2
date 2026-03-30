<?php
require_once __DIR__ . '/includes/db.php';

echo "Updating services table...\n";

// Rename Sintraboard to Sintraboard Standees
$update = db_execute("UPDATE services SET name = 'Sintraboard Standees' WHERE name = 'Sintraboard'");
if ($update) {
    echo "Renamed Sintraboard to Sintraboard Standees.\n";
} else {
    echo "Sintraboard not found or already renamed.\n";
}

// Remove the incorrect Standees service
$delete = db_execute("DELETE FROM services WHERE name = 'Standees'");
if ($delete) {
    echo "Removed redundant Standees service.\n";
} else {
    echo "Standees service not found.\n";
}

echo "Database cleanup complete.\n";
unlink(__FILE__);
