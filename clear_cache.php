<?php
// Clear PHP OpCache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OpCache cleared!<br>";
} else {
    echo "OpCache not enabled<br>";
}

// Clear any file-based cache
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "APCu cache cleared!<br>";
}

echo "<br>Cache clearing complete. <a href='/printflow/admin/orders_management.php'>Go to Orders</a>";
?>
