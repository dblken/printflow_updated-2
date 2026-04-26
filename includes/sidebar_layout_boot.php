<?php
/**
 * Sidebar layout boot — run as the first output inside .dashboard-container (before <aside>).
 * Syncs body.sidebar-collapsed with localStorage and reveals the page (pairs with admin_style.php).
 */
?>
<script>
(function () {
    try {
        var v = localStorage.getItem('sidebarCollapsed');
        var collapsed = v === 'true' || v === '1';
        if (document.body) {
            document.body.classList.toggle('sidebar-collapsed', collapsed);
        }
        if (document.documentElement) {
            document.documentElement.classList.toggle('sidebar-preload-collapsed', collapsed);
            document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
        }
    } catch (e) {}
    var root = document.documentElement;
    root.classList.add('sidebar-layout-ready', 'ready');
    root.classList.remove('sidebar-boot-pending');
})();
</script>

