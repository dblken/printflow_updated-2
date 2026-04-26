<?php require_once __DIR__ . '/favicon_links.php'; ?>
<?php if (function_exists('render_order_item_styles')) render_order_item_styles(); ?>
<?php
/**
 * Alpine.js Core Loading (admin / manager / staff shell).
 * Turbo Drive removed for stability - using standard page navigation.
 */
if (empty($GLOBALS['__printflow_shell_core_js'])) {
    $GLOBALS['__printflow_shell_core_js'] = true;
    $__pf_asset_js = '/printflow/public/assets/js';
    ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo $__pf_asset_js; ?>/alpine.min.js" defer></script>
<script>
    window.getProfileImage = function(image) {
        if (!image || image === 'null' || image === 'undefined') {
            return '/printflow/public/assets/uploads/profiles/default.png';
        }
        if (typeof image !== 'string') return '/printflow/public/assets/uploads/profiles/default.png';
        if (image.startsWith('/') || image.startsWith('http')) return image;
        // Check if it's already a public path but missing root
        if (image.includes('assets/uploads/profiles/')) {
            return (image.startsWith('/') ? '' : '/') + image;
        }
        return '/printflow/public/assets/uploads/profiles/' + image;
    };
</script>
<?php
    unset($__pf_asset_js);
}
?>
<!-- PrintFlow Call System (Global) -->
<link rel="stylesheet" href="/printflow/public/assets/css/printflow_call.css?v=1.0.7">
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script src="/printflow/public/assets/js/printflow_call.js?v=1.0.7"></script>
<script>
(function initGlobalCallStaff() {
    const init = () => {
        if (window.PFCall) {
            // PFCall.initialize(userId, userType, userName, userAvatar, basePath)
            // Note: These values are usually available in SESSION for staff
            const userId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
            if (!userId) return;
            const userType = '<?php echo addslashes($_SESSION['user_type'] ?? 'Staff'); ?>';
            const userName = '<?php echo addslashes($_SESSION['user_name'] ?? 'User'); ?>';
            const userAvatar = '<?php echo addslashes(get_profile_image($_SESSION['profile_picture'] ?? null)); ?>';
            const basePath = '/printflow';
            window.PFCall.initialize(userId, userType, userName, userAvatar, basePath);
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    document.addEventListener('turbo:load', init);
})();
</script>

<?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/staff/') !== false): ?>
<script>(function(){document.documentElement.classList.add('printflow-staff');})();</script>
<?php include __DIR__ . '/staff_theme.php'; ?>
<?php endif; ?>
<?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/manager/') !== false): ?>
<script>(function(){document.documentElement.classList.add('printflow-manager');})();</script>
<?php include __DIR__ . '/manager_theme.php'; ?>
<?php endif; ?>
<script>
(function () {
    var root = document.documentElement;
    /* Skip boot-pending when persistent sidebar already exists (Turbo head merge) — avoids full-page hide flash */
    var shell = document.getElementById('printflow-persistent-sidebar');
    try {
        var v = localStorage.getItem('sidebarCollapsed');
        var collapsed = v === 'true' || v === '1';
        if (collapsed) {
            root.classList.add('sidebar-preload-collapsed');
            if (!shell) {
                root.classList.add('sidebar-boot-pending');
            }
        }
    } catch (e) {}
    setTimeout(function () {
        if (root.classList.contains('sidebar-boot-pending')) {
            root.classList.remove('sidebar-boot-pending');
            root.classList.add('sidebar-layout-ready', 'ready');
        }
    }, 2500);
})();
</script>
<style>
    /* Admin White Theme - Consistent Clean Design */
    :root {
        --bg-color: #ffffff;
        --text-main: #1f2937;
        --text-muted: #6b7280;
        --border-color: #f3f4f6;
        --border-hover: #e5e7eb;
        --accent-color: #3b82f6;
        
        /* Layout Constants */
        --sidebar-w-expanded: 240px;
        --sidebar-w-collapsed: 72px;
        --sidebar-dur: 0.28s;
        --sidebar-ease: cubic-bezier(0.4, 0, 0.2, 1);

        /* Reactive layout variable - Locked to HTML root */
        --sidebar-w: var(--sidebar-w-expanded);
    }

    html.sidebar-collapsed,
    html.sidebar-preload-collapsed,
    body.sidebar-collapsed {
        --sidebar-w: var(--sidebar-w-collapsed) !important;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body { 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; 
        background: var(--bg-color); 
        color: var(--text-main);
    }

    /*
     * Sidebar anti-flicker (collapsed nav):
     * - Head script sets sidebar-boot-pending only when localStorage says collapsed.
     * - sidebar_layout_boot.php (first in .dashboard-container) sets body.sidebar-collapsed + removes pending.
     * - Failsafe timeout clears pending if boot script never runs.
     */
    html.sidebar-boot-pending {
        visibility: hidden;
    }
    html.sidebar-layout-ready,
    html.ready {
        visibility: visible;
    }

    /* No layout transition until first sync (sidebar + main must stay locked together) */
    html:not(.sidebar-transitions-enabled) aside.sidebar,
    html:not(.sidebar-transitions-enabled) .main-content {
        transition: none !important;
    }
    
    /* 
     * Core Portal Layout - Structural Stabilization
     * Using Block + Margin instead of Flex to prevent Hotwire Turbo "sibling jumps"
     */
    .dashboard-container {
        display: block;
        min-height: 100vh;
        width: 100%;
        position: relative;
    }

    /* Target persistent wrapper (survives Turbo navigation) */
    #printflow-persistent-sidebar {
        display: block;
        width: var(--sidebar-w);
        height: 100vh;
        height: 100dvh;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1000;
        transition: width var(--sidebar-dur) var(--sidebar-ease);
        background: #000; /* Prevent transparency flashes during body swap */
    }

    .main-content {
        display: block;
        width: auto;
        min-height: 100vh;
        position: relative;
        margin-left: var(--sidebar-w);
        overflow-x: hidden;
        z-index: 1; /* Stay above any background elements */
    }

    /* Desktop Sync Logic: Hard-bound to HTML root for absolute persistence */
    @media (min-width: 769px) {
        .main-content {
            transition: margin-left var(--sidebar-dur) var(--sidebar-ease);
        }
        
        /* Collapsed State margins handled by --sidebar-w variable above */

        /* Prevent manual shifts or transitions during Turbo page swaps */
        html.pf-turbo-nav .main-content {
            transition: none !important;
        }
    }

    
    /* Common Headers */
    .top-bar, header { 
        background: var(--bg-color); 
        padding: 24px 32px; /* Increased top/bottom padding to match dashboard look */
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        /* position: sticky;  <-- Removed sticky */
        /* top: 0; */
        /* z-index: 10; */
        margin-bottom: 8px;
    }
    
    .page-title, h1, h2 { font-size: 24px; font-weight: 700; color: var(--text-main); letter-spacing: -0.025em; }
    .page-subtitle { font-size: 14px; color: var(--text-muted); margin-top: 4px; }
    
    .content-area, main { padding: 0 32px 32px 32px; }
    
    /* Cards */
    .card, .stat-card, .chart-card { 
        background: white; 
        border-radius: 16px; 
        padding: 24px; 
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
        transition: border-color 0.2s ease, box-shadow 0.2s ease; 
        margin-bottom: 24px;
    }
    
    .card:hover, .stat-card:hover, .chart-card:hover { 
        border-color: var(--border-hover); 
        box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
    }
    
    /* Inputs & Forms */
    .input-field, select, input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="search"] {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
        transition: all 0.2s;
        color: var(--text-main);
    }
    
    .input-field:focus, select:focus, input:focus {
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    
    label { display: block; font-size: 13px; font-weight: 500; color: var(--text-main); margin-bottom: 6px; }
    
    /* Buttons */
    .btn-primary {
        background: #1f2937; 
        color: white;
        border-radius: 8px;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .btn-primary:hover { background: #111827; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    
    .btn-secondary {
        background: white;
        color: var(--text-main);
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }

    /* Tables */
    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th { text-align: left; padding: 12px 16px; font-size: 13px; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border-color); }
    td { padding: 16px; font-size: 14px; border-bottom: 1px solid var(--border-color); color: var(--text-main); text-transform: capitalize; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background-color: #fcfcfc; }
    
    /* Autocapslock for common labels */
    .stat-label, .kpi-label, .service-info, .chart-title, .tp-name, .om-value, .om-label { text-transform: capitalize; }

    .status-badge-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 120px;
        padding: 4px 12px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.2s ease;
        border: none;
    }

    /* Global Status Colors (Pale Backgrounds) */
    .badge-fulfilled  { background: #dcfce7; color: #166534 !important; }
    .badge-pending    { background: #fef3c7; color: #92400e !important; }
    .badge-approved   { background: #dbeafe; color: #1e40af !important; }
    .badge-topay      { background: #dbeafe; color: #1e40af !important; }
    .badge-verify     { background: #fef9c3; color: #854d0e !important; }
    .badge-production { background: #e0e7ff; color: #4338ca !important; }
    .badge-pickup     { background: #dcfce7; color: #15803d !important; }
    .badge-cancelled  { background: #fee2e2; color: #991b1b !important; }
    .badge-revision   { background: #ffe4e6; color: #b91c1c !important; }

    /* Utilities */
    .badge { display: inline-flex; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .text-sm { font-size: 13px; }
    .text-gray-500 { color: var(--text-muted); }
    .mb-6 { margin-bottom: 24px; }
    .mb-4 { margin-bottom: 16px; }
    .grid { display: grid; gap: 24px; }
    .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
    .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
    .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
    
    /* Stats Grid - Single row on most screens */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
    
    /* Dynamic column count based on number of children */
    .stats-grid:has(> :last-child:nth-child(3)) { grid-template-columns: repeat(3, 1fr); }
    
    @media (max-width: 1200px) { .stats-grid { gap: 16px; } }
    @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }
    
    @media (max-width: 1024px) {
        .grid-cols-4, .grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .grid-cols-2, .grid-cols-3, .grid-cols-4 { grid-template-columns: 1fr; }
        .main-content { margin-left: 0; padding-top: 0; }
        .sidebar { transform: translateX(-100%); }
        .sidebar.active { transform: translateX(0); box-shadow: 4px 0 15px rgba(0,0,0,0.2); }
        .sidebar.collapsed { width: 240px; }
        .sidebar.collapsed.active { transform: translateX(0); }
        html.sidebar-preload-collapsed aside.sidebar,
        body.sidebar-collapsed aside.sidebar {
            width: 240px !important;
        }
        html.sidebar-preload-collapsed .main-content,
        body.sidebar-collapsed .main-content {
            margin-left: 0 !important;
        }
        
        /* Show mobile burger menu */
        #mobileBurger { display: flex; }
        
        /* Hide collapse button on mobile */
        .sidebar-collapse-btn { display: none; }
        
        /* Ensure proper z-index stacking */
        .sidebar { z-index: 100; }
        #mobileBurger { z-index: 101; }
        #sidebarOverlay { z-index: 90; }
        
        /* Adjust content padding for mobile */
        .content-area, main { padding: 16px; }
        .top-bar, header { padding: 16px; margin-bottom: 8px; }
        
        /* Add top padding to headers to avoid burger overlap */
        .page-title, h1 { padding-left: 60px; }
        
        /* Make tables horizontally scrollable */
        .overflow-x-auto { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        
        /* Adjust KPI grid for mobile */
        .kpi-row { grid-template-columns: 1fr; gap: 12px; }
    }

    .sidebar {
        width: 100%;
        height: 100%;
        background: linear-gradient(180deg, #000508 0%, #000d12 22%, #001018 55%, #001920 100%);
        border-right: 1px solid rgba(83, 197, 224, 0.12);
        display: flex;
        flex-direction: column;
        position: relative; /* Relative to fixed wrapper */
        z-index: 50;
        overflow-x: hidden;
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        transform: translateZ(0);
        overscroll-behavior: contain;
        contain: layout style;
    }
    /* Desktop: animate width only (no transform) — avoids fighting mobile drawer + extra reflow */
    @media (min-width: 769px) {
        .sidebar {
            transition: width var(--sidebar-dur) var(--sidebar-ease);
        }
    }
    @media (max-width: 768px) {
        .sidebar {
            transition: transform 0.3s ease;
        }
    }
    .sidebar-header {
        padding: 24px 20px;
        border-bottom: 1px solid rgba(83, 197, 224, 0.12);
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        background: transparent;
        flex-shrink: 0;
    }
    .logo { display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 600; color: #e8f4f8; text-decoration: none; overflow: hidden; white-space: nowrap; flex: 1; }
    .sidebar-header .logo img {
        border-color: rgba(83, 197, 224, 0.35) !important;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.2);
    }
    .logo-icon { min-width: 32px; width: 32px; height: 32px; background: linear-gradient(135deg, #00232b, #124a58); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 1px solid rgba(83, 197, 224, 0.25); }
    
    /* Sidebar Collapse Button */
    .sidebar-collapse-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(83, 197, 224, 0.2);
        color: #9fd4e3;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .sidebar-collapse-btn:hover {
        background: rgba(255, 255, 255, 0.16);
        color: #ffffff;
        border-color: rgba(83, 197, 224, 0.35);
    }
    .sidebar-collapse-btn svg {
        width: 16px;
        height: 16px;
    }
    
    /* Mobile Burger Menu */
    #mobileBurger {
        display: none;
        position: fixed;
        top: 16px;
        left: 16px;
        z-index: 60;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #001018, #00232b);
        border: 1px solid rgba(83, 197, 224, 0.25);
        color: #e8f4f8;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        transition: all 0.2s;
    }
    #mobileBurger:hover {
        background: linear-gradient(135deg, #00232b, #0a3d4d);
        border-color: rgba(83, 197, 224, 0.4);
        color: #fff;
    }
    
    /* Mobile Sidebar Overlay */
    #sidebarOverlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 90;
        opacity: 0;
        transition: opacity 0.3s;
    }
    #sidebarOverlay.active {
        display: block;
        opacity: 1;
    }
    
    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding: 16px 0;
        overflow-anchor: none;
    }
    .nav-section { margin-bottom: 24px; }
    .nav-section-title {
        font-size: 11px;
        font-weight: 600;
        color: rgba(148, 200, 212, 0.55);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 0 20px;
        margin-bottom: 8px;
    }
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        margin: 0 10px;
        border-radius: 10px;
        color: rgba(200, 230, 238, 0.88);
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.18s, color 0.18s, box-shadow 0.18s;
        position: relative;
        border-right: none;
        min-height: 40px;
        box-sizing: border-box;
    }
    .nav-item:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #f0fafc;
    }
    /* Active: light pill (same font-weight as inactive — avoids reflow / “blink” when switching) */
    .nav-item.active {
        background: linear-gradient(135deg, #f0fdfa 0%, #e0f2f4 45%, #d8eef2 100%);
        color: #00232b;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.85);
        border-right: none;
    }
    .nav-item.active .nav-icon { color: #00232b; stroke: #00232b; }
    .nav-item.active:hover {
        filter: none;
        background: linear-gradient(135deg, #f8fffe 0%, #e8f6f8 50%, #dff0f4 100%);
        color: #00151a;
    }
    .nav-icon { width: 20px; height: 20px; }
    .nav-badge { 
        margin-left: auto; 
        background: #ef4444; 
        color: white; 
        font-size: 11px; 
        font-weight: 700; 
        padding: 2px 6px; 
        border-radius: 10px; 
        min-width: 20px; 
        text-align: center;
        line-height: 1.4;
    }
    /* Reserve badge space in sidebar so poll / Turbo doesn’t shift the row */
    #printflow-persistent-sidebar .nav-item .nav-badge[data-notif-badge] {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .sidebar-footer {
        padding: 16px;
        border-top: 1px solid rgba(83, 197, 224, 0.12);
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: linear-gradient(180deg, transparent, rgba(0, 0, 0, 0.2));
        flex-shrink: 0;
    }
    .user-profile { display: flex; align-items: center; gap: 12px; padding: 8px; border-radius: 10px; }
    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #124a58 0%, #53C5E0 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
        border: 1px solid rgba(83, 197, 224, 0.35);
    }
    .user-info { flex: 1; }
    .user-name-display { font-size: 14px; font-weight: 500; color: #e8f4f8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
    .user-role { font-size: 12px; color: rgba(148, 200, 212, 0.75); }
    .logout-btn-footer { 
        display: flex; 
        align-items: center; 
        justify-content: center;
        gap: 8px; 
        padding: 8px 12px; 
        color: rgba(200, 230, 238, 0.9); 
        font-size: 14px; 
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(83, 197, 224, 0.18);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }
    .logout-btn-footer:hover { 
        color: #fecaca; 
        background: rgba(239, 68, 68, 0.12); 
        border-color: rgba(248, 113, 113, 0.35);
    }
    .logout-btn { display: flex; align-items: center; gap: 8px; padding: 8px 12px; color: rgba(200, 230, 238, 0.85); font-size: 14px; text-decoration: none; margin-top: 8px; border-radius: 6px; }
    .logout-btn:hover { color: #f0fafc; background: rgba(255, 255, 255, 0.06); }
    a.user-profile:hover { background: rgba(255, 255, 255, 0.05); }

    /* Margins now globally synced via CSS variable --sidebar-w on html root */
    
    .sidebar.collapsed .sidebar-header { padding: 24px 12px; justify-content: center; flex-direction: column; gap: 12px; }
    .sidebar.collapsed .logo { flex-direction: column; gap: 4px; }
    .sidebar.collapsed .logo span { display: none; }
    .sidebar.collapsed .sidebar-collapse-btn { margin: 0; }
    .sidebar.collapsed .nav-section-title { text-align: center; font-size: 0; padding: 0; margin-bottom: 16px; }
    .sidebar.collapsed .nav-section-title::after { content: "•••"; font-size: 12px; letter-spacing: 2px; color: rgba(148, 200, 212, 0.45); }
    
    /* Collapsed: no gap between icon and hidden label — otherwise icon sits left of center */
    .sidebar.collapsed .nav-item {
        padding: 12px;
        justify-content: center;
        margin: 0 8px;
        border-radius: 10px;
        border-right: none;
        gap: 0;
        font-size: 0;
        min-height: 0;
    }
    .sidebar.collapsed .nav-item.active {
        border-right: none;
        background: linear-gradient(135deg, #f0fdfa 0%, #e0f2f4 50%, #d8eef2 100%);
        color: #00232b;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.18);
    }
    .sidebar.collapsed .nav-item.active .nav-icon { color: #00232b; stroke: #00232b; }
    .sidebar.collapsed .nav-item text,
    .sidebar.collapsed .nav-item tooltip,
    .sidebar-nav a { position: relative; }

    .sidebar.collapsed .nav-icon { margin: 0; width: 20px; height: 20px; flex-shrink: 0; }
    .sidebar.collapsed .nav-badge { 
        position: absolute; 
        top: 8px; 
        right: 8px; 
        min-width: 8px; 
        height: 8px; 
        padding: 0; 
        font-size: 0; 
        border-radius: 50%;
    }

    .sidebar.collapsed .sidebar-footer { padding: 16px 8px; display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .sidebar.collapsed .user-info { display: none; }
    .sidebar.collapsed .user-avatar { margin: 0; }
    /* Center profile block like the logo (link was flex-start + full width) */
    .sidebar.collapsed .sidebar-footer a.user-profile {
        justify-content: center;
        width: 100%;
        gap: 0;
        padding-left: 0;
        padding-right: 0;
    }
    .sidebar.collapsed .logout-btn-footer { 
        width: auto;
        padding: 10px;
        border-radius: 8px;
    }
    .sidebar.collapsed .logout-btn-footer span { display: none; }
    .sidebar.collapsed .logout-btn-footer svg { margin: 0; }
    /* Staff footer uses .logout-btn + text node — match icon-only centered layout */
    .sidebar.collapsed .logout-btn {
        width: auto;
        padding: 10px;
        justify-content: center;
        margin-top: 0;
        gap: 0;
        font-size: 0;
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
    
    /* Sidebar nav scrollbar — dark theme */
    .sidebar-nav { scrollbar-width: thin; scrollbar-color: rgba(83, 197, 224, 0.25) transparent; }
    .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(83, 197, 224, 0.2); border-radius: 4px; }
    .sidebar-nav:hover::-webkit-scrollbar-thumb { background: rgba(83, 197, 224, 0.35); }
    
    /* Strict Layout Enforcement */
    html, body { height: 100%; overflow: hidden; } /* Lock body scroll */
    .dashboard-container { height: 100%; overflow: hidden; }
    .main-content { height: 100%; overflow-y: scroll; scroll-behavior: smooth; } /* Always show scrollbar track */

    /* ── KPI figure figures — one canonical rule in this file ── */
    .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    @media (max-width: 1024px) { .kpi-row { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .kpi-row { grid-template-columns: 1fr; } }

    .kpi-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; position: relative; overflow: hidden; display: block; text-decoration: none; color: inherit; }
    .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #00232b, #53C5E0); }
    .kpi-card.indigo::before { background: linear-gradient(90deg, #00232b, #53C5E0); }
    .kpi-card.emerald::before { background: linear-gradient(90deg, #059669, #34d399); }
    .kpi-card.amber::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .kpi-card.rose::before { background: linear-gradient(90deg, #e11d48, #fb7185); }
    .kpi-card.blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
    
    .kpi-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 6px; display: block; background: none !important; -webkit-text-fill-color: initial !important; }
    .kpi-value { font-size: 24px; font-weight: 800; color: #1f2937; line-height: 1.2; display: block; }
    .kpi-sub { font-size: 12px; color: #6b7280; margin-top: 4px; display: block; }

    /* Clickable KPI cards */
    a.kpi-card.kpi-card--link {
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0,35,43,.06);
        transition: transform 0.25s ease, box-shadow 0.25s ease, filter 0.2s ease, opacity 0.2s ease;
    }
    a.kpi-card.kpi-card--link:hover {
        transform: scale(1.02);
        box-shadow: 0 10px 28px rgba(0,35,43,.12);
    }
    a.kpi-card.kpi-card--link:active { transform: scale(0.99); }
    
    .kpi-card-inner { position: relative; display: block; padding-bottom: 24px; }
    .kpi-card-cta { 
        position: absolute; 
        right: 0; 
        bottom: 0; 
        font-size: 11px; 
        font-weight: 600; 
        color: #6b7280; 
        opacity: 0.5; 
        transition: opacity 0.2s; 
    }
    a.kpi-card.kpi-card--link:hover .kpi-card-cta { opacity: 1; color: #00232b; }
    a.kpi-card.kpi-card--link:focus { outline: none; }
    a.kpi-card.kpi-card--link:focus-visible {
        outline: 2px solid #53C5E0;
        outline-offset: 3px;
    }
    @media (hover: hover) {
        a.kpi-card.kpi-card--link .kpi-card-cta { opacity: 0.4; }
        a.kpi-card.kpi-card--link:hover .kpi-card-cta,
        a.kpi-card.kpi-card--link:focus-visible .kpi-card-cta { opacity: 1; color: #00232b; }
    }
    @media (hover: none) {
        a.kpi-card.kpi-card--link .kpi-card-cta { opacity: 0.75; }
    }

    /* ── Filter & Sort Toolbars ── */
    .toolbar-container { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
    .toolbar-group { display: flex; align-items: center; gap: 10px; }
    
    .toolbar-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border: 1px solid #e5e7eb;
        background: #fff;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        height: 38px;
    }
    .toolbar-btn:hover { border-color: #9ca3af; background: #f9fafb; }
    .toolbar-btn.active { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
    .toolbar-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
    
    .filter-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        background: #0d9488;
        color: #fff;
        border-radius: 50%;
        font-size: 10px;
        font-weight: 700;
        margin-left: 6px;
        padding: 0 4px;
    }

    /* Standardized Filter Panel */
    .dropdown-panel {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        z-index: 200;
        overflow: hidden;
    }
    .filter-panel { width: 320px; }
    .sort-dropdown { min-width: 200px; padding: 6px 0; }
    
    .filter-header { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 700; color: #111827; }
    .filter-section { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; }
    .filter-section:last-child { border-bottom: none; }
    .filter-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .filter-label { font-size: 13px; font-weight: 600; color: #374151; display: block; margin-bottom: 8px; }
    .filter-reset-link { font-size: 12px; font-weight: 600; color: #0d9488; cursor: pointer; background: none; border: none; padding: 0; }
    .filter-reset-link:hover { text-decoration: underline; }
    
    .filter-input { width: 100%; height: 34px; border: 1px solid #e5e7eb; border-radius: 7px; font-size: 13px; padding: 0 10px; color: #1f2937; box-sizing: border-box; transition: border-color 0.15s; }
    .filter-input:focus { outline: none; border-color: #0d9488; }
    
    .filter-select { width: 100%; height: 34px; border: 1px solid #e5e7eb; border-radius: 7px; font-size: 13px; padding: 0 10px; color: #1f2937; background: #fff; box-sizing: border-box; cursor: pointer; }
    .filter-select:focus { outline: none; border-color: #0d9488; }
    
    .filter-footer { padding: 14px 18px; background: #f9fafb; display: flex; gap: 8px; border-top: 1px solid #f3f4f6; }
    .filter-btn-reset { flex: 1; height: 36px; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; font-size: 13px; font-weight: 600; color: #374151; cursor: pointer; transition: all 0.2s; }
    .filter-btn-reset:hover { background: #f3f4f6; }
    .filter-btn-apply { flex: 1; height: 36px; border: none; background: #0d9488; color: #fff; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .filter-btn-apply:hover { background: #0f766e; }
    
    .sort-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        font-size: 13px;
        color: #374151;
        cursor: pointer;
        transition: background 0.1s;
    }
    .sort-option:hover { background: #f9fafb; }
    .sort-option.active { color: #0d9488; font-weight: 600; background: #f0fdfa; }
    .sort-option .check { margin-left: auto; color: #0d9488; }

    /* ── Global summary / KPI card accent ── */
    .kpi-card::before,
    .kpi-card.indigo::before,
    .kpi-card.emerald::before,
    .kpi-card.amber::before,
    .kpi-card.rose::before,
    .kpi-card.blue::before,
    .kpi-ind::before,
    .kpi-em::before,
    .kpi-amb::before,
    .kpi-vio::before {
        background: linear-gradient(90deg, #00232b, #53C5E0) !important;
    }
    .kpi-label,
    .kpi-lbl {
        background: linear-gradient(90deg, #00232b, #53C5E0) !important;
        -webkit-background-clip: text !important;
        background-clip: text !important;
        color: transparent !important;
        -webkit-text-fill-color: transparent !important;
    }
    /* Stat grid cards (staff dashboard, etc.) — top bar + readable title */
    .stats-grid .stat-card,
    .stat-card {
        position: relative;
        overflow: hidden;
    }
    /* Clickable KPI Cards & Hover Effects */
    .kpi-card--link { cursor: pointer; text-decoration: none; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); background: #fff; overflow: hidden; position: relative; }
    .kpi-card--link:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -8px rgba(0,0,0,0.1); border-color: transparent; }
    .kpi-card--link .kpi-card-cta { display: block; margin-top: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8; transition: opacity 0.2s; }
    .kpi-card--link:hover .kpi-card-cta { opacity: 1; }
    
    .kpi-card-inner { display: flex; flex-direction: column; height: 100%; transition: transform 0.25s ease; }
    .kpi-card--link:hover .kpi-card-inner { transform: scale(1.02); }

    /* KPI Pulse Effect */
    @keyframes kpiPulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(0.98); opacity: 0.8; }
        100% { transform: scale(1); opacity: 1; }
    }
    .metric-pulse { animation: kpiPulse 0.4s ease-in-out; }

    /* Standardized Search Input */
    .toolbar-search { position: relative; width: 240px; }
    .toolbar-search input {
        width: 100%;
        height: 38px;
        padding: 0 12px 0 36px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 13px;
        outline: none;
        transition: all 0.2s;
        background: #fff;
        color: #1f2937;
    }
    .toolbar-search input:focus { border-color: #0d9488; box-shadow: 0 0 0 2px rgba(13,148,136,0.1); }
    .toolbar-search svg {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        pointer-events: none;
        width: 14px;
        height: 14px;
    }

    .stats-grid .stat-card::before,
    .stat-card:not(.no-stat-accent)::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #00232b, #53C5E0);
        pointer-events: none;
    }

    /*
     * KPI figures — one canonical rule in this file (included on every admin/staff shell page).
     * Turbo Drive can leave previous pages’ <style> blocks in <head>; !important keeps weight
     * stable on first paint and across navigations.
     */
    .kpi-value {
        font-size: clamp(20px, 3.5vw, 26px);
        font-weight: 800 !important;
        color: #1f2937;
        font-variant-numeric: tabular-nums;
        white-space: nowrap !important;
        display: block !important;
    }
    .stats-grid .stat-value,
    .stat-card > .stat-value {
        font-size: clamp(22px, 4vw, 32px);
        font-weight: 800 !important;
        color: #1f2937;
        font-variant-numeric: tabular-nums;
        margin-bottom: 4px;
        white-space: nowrap !important;
        display: block !important;
    }
    .report-summary .summary-box .value {
        font-size: clamp(18px, 3vw, 24px);
        font-weight: 800 !important;
        color: #1f2937;
        font-variant-numeric: tabular-nums;
        white-space: nowrap !important;
        display: block !important;
    }
    .inv-summary-card .value {
        font-weight: 800 !important;
        font-variant-numeric: tabular-nums;
        white-space: nowrap !important;
        display: block !important;
    }

    .stat-label {
        color: #00232b;
        font-weight: 600;
    }

    /* ── PrintFlow form guard: save overlay, unsaved modal, toast (portal lives in sidebar, turbo-permanent) ── */
    .pf-fg-portal {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 10030;
    }
    .pf-fg-save-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 19, 28, 0.45);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.28s ease, visibility 0.28s ease;
        pointer-events: none;
    }
    .pf-fg-save-overlay--visible {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .pf-fg-spinner {
        display: inline-block;
        width: 1em;
        height: 1em;
        border: 2px solid rgba(83, 197, 224, 0.25);
        border-top-color: #53C5E0;
        border-radius: 50%;
        animation: pf-fg-spin 0.65s linear infinite;
        vertical-align: -0.12em;
        margin-right: 8px;
    }
    @keyframes pf-fg-spin {
        to { transform: rotate(360deg); }
    }
    .pf-fg-save-highlight {
        box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.9) !important;
        transition: box-shadow 0.2s ease;
    }
    .pf-fg-dirty-hint {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #b45309;
        flex: 0 0 100%;
        width: 100%;
        max-width: 100%;
        margin-top: 8px;
        text-align: right;
        box-sizing: border-box;
    }
    .pf-fg-dirty-hint[hidden] {
        display: none !important;
    }
    .pf-fg-nav-modal {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .pf-fg-nav-modal--open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .pf-fg-nav-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 19, 28, 0.5);
    }
    .pf-fg-nav-modal__panel {
        position: relative;
        width: 100%;
        max-width: 460px;
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 24px 56px rgba(0, 35, 43, 0.22);
        padding: 22px 24px 20px;
        transform: scale(0.96) translateY(6px);
        opacity: 0;
        transition: transform 0.28s cubic-bezier(0.34, 1.2, 0.64, 1), opacity 0.22s ease;
    }
    .pf-fg-nav-modal--open .pf-fg-nav-modal__panel {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
    .pf-fg-nav-modal__title {
        font-size: 17px;
        font-weight: 700;
        color: #00232b;
        margin: 0 0 8px;
        letter-spacing: -0.02em;
    }
    .pf-fg-nav-modal__msg {
        font-size: 14px;
        color: #4b5563;
        margin: 0 0 12px;
        line-height: 1.45;
    }
    .pf-fg-nav-modal__sub {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #00232b;
        margin: 0 0 8px;
    }
    .pf-fg-nav-modal__list {
        list-style: none;
        margin: 0 0 16px;
        padding: 12px 14px;
        background: linear-gradient(135deg, rgba(83, 197, 224, 0.12), rgba(0, 35, 43, 0.06));
        border: 1px solid rgba(83, 197, 224, 0.5);
        border-radius: 10px;
        border-left: 4px solid #53C5E0;
    }
    .pf-fg-nav-modal__list li {
        font-size: 14px;
        font-weight: 600;
        color: #00232b;
        padding: 6px 0 6px 22px;
        position: relative;
        line-height: 1.35;
    }
    .pf-fg-nav-modal__list li + li {
        border-top: 1px solid rgba(0, 35, 43, 0.08);
    }
    .pf-fg-nav-modal__list li::before {
        content: '';
        position: absolute;
        left: 2px;
        top: 0.65em;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #53C5E0;
        box-shadow: 0 0 0 2px rgba(0, 35, 43, 0.15);
    }
    .pf-fg-nav-modal__err {
        font-size: 13px;
        color: #b91c1c;
        margin: 0 0 14px;
        padding: 10px 12px;
        background: #fef2f2;
        border-radius: 8px;
        border: 1px solid #fecaca;
    }
    .pf-fg-nav-modal__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 10px;
    }
    .pf-fg-btn {
        height: 40px;
        padding: 0 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: background 0.15s ease, transform 0.12s ease, box-shadow 0.15s ease;
    }
    .pf-fg-btn:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }
    .pf-fg-btn--accent {
        background: #53C5E0;
        color: #00232b;
        border: 2px solid #00232b;
        box-shadow: 0 2px 10px rgba(83, 197, 224, 0.4);
    }
    .pf-fg-btn--accent:hover:not(:disabled) {
        background: #6dceea;
        box-shadow: 0 4px 14px rgba(83, 197, 224, 0.45);
    }
    .pf-fg-btn--discard {
        background: #00232b;
        color: #53C5E0;
        border: 2px solid #00232b;
    }
    .pf-fg-btn--discard:hover:not(:disabled) {
        background: #003a47;
        color: #6dceea;
    }
    .pf-fg-btn--neutral {
        background: #fff;
        color: #00232b;
        border: 2px solid #53C5E0;
    }
    .pf-fg-btn--neutral:hover:not(:disabled) {
        background: rgba(83, 197, 224, 0.12);
    }
    .pf-fg-toast {
        position: fixed;
        bottom: 28px;
        right: 28px;
        z-index: 10060;
        padding: 14px 20px;
        background: linear-gradient(135deg, #00232b, #0a3d4d);
        color: #e8f4f8;
        font-size: 14px;
        font-weight: 600;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0, 35, 43, 0.35);
        border: 1px solid rgba(83, 197, 224, 0.35);
        opacity: 0;
        transform: translateY(12px);
        transition: opacity 0.28s ease, transform 0.28s ease;
        pointer-events: none;
        max-width: min(360px, calc(100vw - 40px));
    }
    .pf-fg-toast--visible {
        opacity: 1;
        transform: translateY(0);
    }
    .btn-staff-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 11px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none !important;
        line-height: 1.2;
        background: transparent;
        border: 1.5px solid transparent;
    }
    .btn-staff-action-emerald {
        border-color: #059669;
        color: #059669 !important;
    }
    .btn-staff-action-emerald:hover {
        background: #059669;
        color: white !important;
        transform: translateY(-1px);
    }
    .btn-staff-action-indigo {
        border-color: #4f46e5;
        color: #4f46e5 !important;
    }
    .btn-staff-action-indigo:hover {
        background: #4f46e5;
        color: white !important;
        transform: translateY(-1px);
    }
    .btn-staff-action-blue {
        border-color: #06A1A1;
        color: #06A1A1 !important;
    }
    .btn-staff-action-blue:hover {
        background: #06A1A1;
        color: white !important;
        transform: translateY(-1px);
    }
    .btn-staff-action-red {
        border-color: #ef4444;
        color: #ef4444 !important;
    }
    .btn-staff-action-red:hover {
        background: #ef4444;
        color: white !important;
        transform: translateY(-1px);
    }
</style>
<script>
(function () {
    function printflowBootCharts() {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                try {
                    if (document.getElementById('reportsFilterForm') && typeof window.printflowInitReportsCharts === 'function') {
                        window.printflowInitReportsCharts();
                    } else if (document.getElementById('salesChart') && typeof window.printflowInitDashboardCharts === 'function') {
                        window.printflowInitDashboardCharts();
                    }
                } catch (e) { }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', printflowBootCharts);
    } else {
        printflowBootCharts();
    }
})();
</script>

