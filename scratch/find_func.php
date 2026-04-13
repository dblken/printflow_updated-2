<?php
require_once __DIR__ . '/../includes/functions.php';

if (function_exists('update_order_status')) {
    $refl = new ReflectionFunction('update_order_status');
    echo "Found at: " . $refl->getFileName() . ":" . $refl->getStartLine() . "\n";
} else {
    echo "Function update_order_status NOT FOUND\n";
}
