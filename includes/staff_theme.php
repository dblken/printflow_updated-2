<?php
/**
 * Staff portal palette: primary #06A1A1, soft #9ED7C4.
 * Requires `html.printflow-staff` (script in admin_style.php or header.php for /staff/).
 */
?>
<style>
    html.printflow-staff {
        --accent-color: #06A1A1;
        --staff-primary: #06A1A1;
        --staff-soft: #9ED7C4;
    }

    /* Main area: focus rings & links */
    html.printflow-staff .input-field:focus,
    html.printflow-staff select:focus,
    html.printflow-staff input:focus {
        border-color: var(--staff-primary);
        box-shadow: 0 0 0 3px rgba(6, 161, 161, 0.18);
    }

    html.printflow-staff .btn-primary {
        background: #06A1A1;
        color: #fff;
    }
    html.printflow-staff .btn-primary:hover {
        background: #058f8f;
        box-shadow: 0 4px 14px rgba(6, 161, 161, 0.35);
    }

    /* Sidebar shell */
    html.printflow-staff .sidebar {
        background: linear-gradient(180deg, #011818 0%, #022a2a 24%, #033838 55%, #044040 100%);
        border-right: 1px solid rgba(6, 161, 161, 0.22);
        box-shadow: 4px 0 24px rgba(0, 48, 48, 0.14);
    }
    html.printflow-staff .sidebar-header {
        border-bottom: 1px solid rgba(158, 215, 196, 0.18);
    }
    html.printflow-staff .sidebar-header .logo img {
        border-color: rgba(158, 215, 196, 0.4) !important;
    }
    html.printflow-staff .logo-icon {
        background: linear-gradient(135deg, #035050, #06A1A1);
        border-color: rgba(158, 215, 196, 0.35);
    }
    html.printflow-staff .sidebar-collapse-btn {
        border-color: rgba(6, 161, 161, 0.28);
        color: #9ED7C4;
    }
    html.printflow-staff .sidebar-collapse-btn:hover {
        border-color: rgba(158, 215, 196, 0.45);
        color: #fff;
    }

    html.printflow-staff #mobileBurger {
        background: linear-gradient(135deg, #022e2e, #06A1A1);
        border-color: rgba(158, 215, 196, 0.35);
    }
    html.printflow-staff #mobileBurger:hover {
        background: linear-gradient(135deg, #035f5f, #09b5b5);
        border-color: rgba(158, 215, 196, 0.5);
    }

    html.printflow-staff .nav-section-title {
        color: rgba(158, 215, 196, 0.55);
    }
    html.printflow-staff .nav-item {
        color: rgba(220, 245, 238, 0.9);
    }
    html.printflow-staff .nav-item:hover {
        color: #f6fffc;
    }
    html.printflow-staff .nav-item.active {
        background: linear-gradient(135deg, #f7fefb 0%, #e5f9f2 42%, #d4f0e6 100%);
        color: #023d3d;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    }
    html.printflow-staff .nav-item.active .nav-icon {
        color: #023d3d;
        stroke: #023d3d;
    }
    html.printflow-staff .nav-item.active:hover {
        background: linear-gradient(135deg, #ffffff 0%, #eefaf5 50%, #dff5ec 100%);
        color: #012828;
    }

    html.printflow-staff .sidebar-footer {
        border-top: 1px solid rgba(6, 161, 161, 0.2);
    }
    html.printflow-staff .user-avatar {
        background: linear-gradient(135deg, #047676 0%, #06A1A1 55%, #9ED7C4 100%);
        border-color: rgba(158, 215, 196, 0.45);
    }

    html.printflow-staff .sidebar.collapsed .nav-item.active .nav-icon {
        color: #023d3d;
        stroke: #023d3d;
    }
    html.printflow-staff .sidebar.collapsed .nav-section-title::after {
        color: rgba(158, 215, 196, 0.5);
    }

    html.printflow-staff .sidebar-nav {
        scrollbar-color: rgba(6, 161, 161, 0.35) transparent;
    }
    html.printflow-staff .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(6, 161, 161, 0.28);
    }
    html.printflow-staff .sidebar-nav:hover::-webkit-scrollbar-thumb {
        background: rgba(6, 161, 161, 0.45);
    }

    /* KPI / stat accents */
    html.printflow-staff .kpi-card::before,
    html.printflow-staff .kpi-card.indigo::before,
    html.printflow-staff .kpi-card.emerald::before,
    html.printflow-staff .kpi-card.amber::before,
    html.printflow-staff .kpi-card.rose::before,
    html.printflow-staff .kpi-card.blue::before,
    html.printflow-staff .kpi-ind::before,
    html.printflow-staff .kpi-em::before,
    html.printflow-staff .kpi-amb::before,
    html.printflow-staff .kpi-vio::before {
        background: linear-gradient(90deg, #035f5f, #06A1A1, #9ED7C4) !important;
    }
    html.printflow-staff .kpi-label,
    html.printflow-staff .kpi-lbl {
        background: linear-gradient(90deg, #023d3d, #06A1A1) !important;
        -webkit-background-clip: text !important;
        background-clip: text !important;
        color: transparent !important;
        -webkit-text-fill-color: transparent !important;
    }

    html.printflow-staff .stats-grid .stat-card::before,
    html.printflow-staff .stat-card:not(.no-stat-accent)::before {
        background: linear-gradient(90deg, #035f5f, #06A1A1, #9ED7C4);
    }

    html.printflow-staff .stat-label {
        color: #047676;
    }

    /* Form guard (sidebar portal) */
    html.printflow-staff .pf-fg-spinner {
        border-color: rgba(6, 161, 161, 0.3);
        border-top-color: #06A1A1;
    }
    html.printflow-staff .pf-fg-save-highlight {
        box-shadow: 0 0 0 2px rgba(6, 161, 161, 0.85) !important;
    }
    html.printflow-staff .pf-fg-btn--accent {
        background: #06A1A1;
        color: #fff;
        border-color: #023d3d;
        box-shadow: 0 2px 10px rgba(6, 161, 161, 0.35);
    }
    html.printflow-staff .pf-fg-btn--accent:hover:not(:disabled) {
        background: #058f8f;
    }
    html.printflow-staff .pf-fg-btn--discard {
        background: #023d3d;
        color: #9ED7C4;
        border-color: #023d3d;
    }
    html.printflow-staff .pf-fg-btn--discard:hover:not(:disabled) {
        background: #035050;
        color: #c8efe0;
    }
    html.printflow-staff .pf-fg-btn--neutral {
        border-color: #06A1A1;
        color: #023d3d;
    }
    html.printflow-staff .pf-fg-btn--neutral:hover:not(:disabled) {
        background: rgba(158, 215, 196, 0.25);
    }
    html.printflow-staff .pf-fg-nav-modal__title,
    html.printflow-staff .pf-fg-nav-modal__sub {
        color: #023d3d;
    }
    html.printflow-staff .pf-fg-nav-modal__list {
        background: linear-gradient(135deg, rgba(158, 215, 196, 0.2), rgba(6, 161, 161, 0.08));
        border-color: rgba(6, 161, 161, 0.35);
        border-left-color: #06A1A1;
    }
    html.printflow-staff .pf-fg-nav-modal__list li::before {
        background: #06A1A1;
    }

    /* Unified Table Action Buttons */
    html.printflow-staff .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 12px;
        min-height: 28px;
        font-size: 12px;
        font-weight: 700;
        border-radius: 6px;
        transition: all 0.2s;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    html.printflow-staff .btn-action-primary {
        background: rgba(6, 161, 161, 0.12);
        color: #058f8f;
    }
    html.printflow-staff .btn-action-primary:hover {
        background: #06A1A1;
        color: #ffffff;
        transform: translateY(-1px);
    }
    html.printflow-staff .btn-action-secondary {
        background: rgba(124, 58, 237, 0.1);
        color: #7c3aed;
    }
    html.printflow-staff .btn-action-secondary:hover {
        background: #7c3aed;
        color: #ffffff;
        transform: translateY(-1px);
    }
    html.printflow-staff .btn-action-danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    html.printflow-staff .btn-action-danger:hover {
        background: #ef4444;
        color: #ffffff;
        transform: translateY(-1px);
    }
</style>
