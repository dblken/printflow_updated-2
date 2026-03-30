<?php
/**
 * No-Cache Headers
 * Include this at the top of every admin page to prevent aggressive browser caching
 */

// Prevent all caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 01 Jan 1990 00:00:00 GMT");

// Add version to force cache invalidation
if (!defined('PRINTFLOW_VERSION')) {
    define('PRINTFLOW_VERSION', time()); // Use timestamp as version
}
?>
