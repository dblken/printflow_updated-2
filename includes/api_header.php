<?php
/**
 * API Response Header
 * Ensures clean JSON output without PHP errors/warnings
 * Include this at the top of all API endpoints
 */

// Disable all error output to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Clear any output buffer that might contain errors/warnings
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Prevent any accidental output
ob_start();

// Register shutdown function to ensure clean JSON output
register_shutdown_function(function() {
    $output = ob_get_clean();
    
    // Check if output is valid JSON
    if ($output && json_decode($output) === null && json_last_error() !== JSON_ERROR_NONE) {
        // Output is not valid JSON, return error
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API response format'
        ]);
    } else {
        // Output is valid JSON or empty
        echo $output;
    }
});
