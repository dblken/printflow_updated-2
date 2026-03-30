<?php
/**
 * Version Control for Cache Busting
 * Update this timestamp whenever you make changes to force browser cache refresh
 */

// Use file modification time as version to automatically bust cache
define('PRINTFLOW_ASSET_VERSION', filemtime(__FILE__));

// Alternative: Manual version (increment this when you make changes)
// define('PRINTFLOW_ASSET_VERSION', '1.0.1');

/**
 * Get versioned URL for cache busting
 * @param string $url The URL to add version to
 * @return string URL with version parameter
 */
function versioned_url($url) {
    $separator = strpos($url, '?') !== false ? '&' : '?';
    return $url . $separator . 'v=' . PRINTFLOW_ASSET_VERSION;
}
?>
