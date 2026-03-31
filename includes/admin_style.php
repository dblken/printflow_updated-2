<?php require_once __DIR__ . '/favicon_links.php'; ?>
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
<?php
    unset($__pf_asset_js);
}
?>

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
        --sidebar-w-expanded: 240px;
        --sidebar-w-collapsed: 72px;
        --sidebar-dur: 0.28s;
        --sidebar-ease: cubic-bezier(0.4, 0, 0.2, 1);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body { 
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; 
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
    
    /* Layout */
    .dashboard-container {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: stretch;
        min-height: 100vh;
    }
    /* Turbo permanent wrapper: inner <aside> is position:fixed — no in-flow width.
       order:-1 keeps layout correct even if Turbo leaves this node after .main-content in the DOM. */
    #printflow-persistent-sidebar {
        order: -1;
        flex: 0 0 0;
        width: 0;
        min-width: 0;
        overflow: visible;
        position: relative;
        align-self: stretch;
    }
    /* z-index: keeps toolbar/buttons above the #printflow-persistent-sidebar flex shim (width:0; overflow:visible) in odd stacking cases */
    .main-content {
        flex: 1;
        margin-left: var(--sidebar-w-expanded);
        overflow-y: auto;
        position: relative;
        z-index: 1;
    }
    /* Keep main in sync with fixed sidebar width (same duration/easing = no “jump”) */
    @media (min-width: 769px) {
        .main-content {
            transition: margin-left var(--sidebar-dur) var(--sidebar-ease);
        }
        /* Avoid main column “sliding” on Turbo body swap (sidebar is fixed; only markup changes) */
        html.pf-turbo-nav .main-content {
            transition: none !important;
        }
        /* No animated nav/width churn on the persistent sidebar while Turbo swaps main */
        html.pf-turbo-nav #printflow-persistent-sidebar a.nav-item {
            transition: none !important;
        }
        html.pf-turbo-nav aside.sidebar {
            transition: none !important;
        }
    }

    /* Expanded (default): 240px sidebar — collapsed: 72px + main offset (before <aside> gets .collapsed) */
    html.sidebar-preload-collapsed aside.sidebar,
    body.sidebar-collapsed aside.sidebar {
        width: var(--sidebar-w-collapsed) !important;
    }
    html.sidebar-preload-collapsed .main-content,
    body.sidebar-collapsed .main-content {
        margin-left: var(--sidebar-w-collapsed) !important;
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
    
    .page-title, h1, h2 { font-size: 24px; font-weight: 600; color: var(--text-main); }
    
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

    /* Sidebar — full dark theme */
    .sidebar {
        width: var(--sidebar-w-expanded);
        background: linear-gradient(180deg, #000508 0%, #000d12 22%, #001018 55%, #001920 100%);
        border-right: 1px solid rgba(83, 197, 224, 0.12);
        display: flex;
        flex-direction: column;
        position: fixed;
        height: 100vh;
        height: 100dvh;
        top: 0;
        left: 0;
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

    /* Collapsible Sidebar Support */
    .sidebar.collapsed { width: var(--sidebar-w-collapsed); }
    /* Legacy: aside was direct sibling of .main-content */
    .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-w-collapsed); }
    /*
     * Turbo shell: sidebar lives inside #printflow-persistent-sidebar, so .main-content is a sibling
     * of the wrapper — use :has() so collapsed width still pulls main content left (desktop).
     */
    @media (min-width: 769px) {
        #printflow-persistent-sidebar:has(.sidebar.collapsed) ~ .main-content {
            margin-left: var(--sidebar-w-collapsed);
        }
    }
    
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

    /* ── Global summary / KPI card accent (all admin-style pages) ───────── */
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

