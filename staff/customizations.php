<?php
/**
 * Staff: Customizations Management
 * Production tracking & material assignment.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

if (!defined('BASE_URL')) define('BASE_URL', '/printflow');
require_role(['Admin', 'Staff', 'Manager']);
$page_title = 'Customizations - PrintFlow';

$branchFilter = printflow_branch_filter_for_user();
$joBranchSql = '';
$joBranchTypes = '';
$joBranchParams = [];
$ordBranchSql = '';
$ordBranchTypes = '';
$ordBranchParams = [];
if ($branchFilter !== null) {
    $b = (int) $branchFilter;
    $joBranchSql = ' AND COALESCE(jo.branch_id, (SELECT o2.branch_id FROM orders o2 WHERE o2.order_id = jo.order_id LIMIT 1)) = ?';
    $joBranchTypes = 'i';
    $joBranchParams = [$b];
    $ordBranchSql = ' AND branch_id = ?';
    $ordBranchTypes = 'i';
    $ordBranchParams = [$b];
}

// Get statistics for KPIs (include both job_orders and regular orders pending review)
$total_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE 1=1" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$total_orders_pending = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$total_jobs = $total_jobs_jobs + $total_orders_pending;

$pending_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'PENDING'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$pending_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$pending_jobs = $pending_jobs_jobs + $pending_orders;

$approval_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'APPROVED'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$in_production_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'IN_PRODUCTION'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$in_production_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status IN ('Processing', 'In Production', 'Printing', 'Paid – In Process', 'Paid - In Process')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$in_production = $in_production_jobs + $in_production_orders;

$completed_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'COMPLETED'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$completed_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status = 'Completed'" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$completed_jobs = $completed_jobs_jobs + $completed_orders;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="turbo-visit-control" content="reload">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>



        /* Multi-Row Toolbar: Separating Stages from Filters */
        .pf-custom-toolbar {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 24px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 20px;
        }
        
        .pf-custom-tabs-row {
            display: flex;
            align-items: center;
            width: 100%;
            border-bottom: 1px solid #f8fafc;
            padding-bottom: 12px;
        }

        .pf-custom-tabs {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping so all categories are visible */
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .pf-custom-filters-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            width: 100%;
        }

        .pf-custom-search {
            flex: 1;
            min-width: 200px;
        }

        .filter-select {
            height: 36px;
            padding: 0 32px 0 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            background-color: white;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 16px;
            appearance: none;
            cursor: pointer;
            outline: none;
            transition: all 0.2s;
        }
        .filter-select:focus { border-color: #06A1A1; ring: 2px; ring-color: #06A1A1; }

        .pill-tab { 
            position: relative;
            padding: 8px 14px; 
            font-weight: 600; 
            font-size: 11px; 
            font-family: inherit;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280; 
            border-radius: 9999px; 
            transition: all 0.2s; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px;
            background: transparent;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .pill-tab:hover { background: #f3f4f6; color: #111827; }
        .pill-tab.active { background: #eef2ff; color: #4f46e5; border: 1px solid #4f46e5; }
        .tab-count { 
            background: #4f46e5; 
            color: white; 
            font-size: 10px; 
            padding: 1px 6px; 
            border-radius: 9999px; 
            font-weight: 600;
        }
        .pill-tab:not(.active) .tab-count { background: #e5e7eb; color: #6b7280; }



        /* Unified Table Typography */
        .table-text-main { font-size: 13px; color: #111827; font-weight: 500; }
        .table-text-sub { font-size: 11px; color: #6b7280; font-weight: 400; }
        
        thead th { 
            font-size: 11px; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            color: #6b7280;
            background: #f9fafb;
            border-bottom: 2px solid #f3f4f6;
        }

        .row-indicator {
            position: absolute;
            left: 0;
            top: 2px;
            bottom: 2px;
            width: 3px;
            background: #4f46e5;
            border-radius: 0 4px 4px 0;
            opacity: 0;
            transition: opacity 0.2s;
        }
        tr:hover .row-indicator { opacity: 1; }

        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .modal-panel { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-width:560px; max-height:88vh; overflow-y:auto; margin:16px; position:relative; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pf-tab-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.45; } }
        [x-cloak] { display: none !important; }

    </style>
</head>
<body data-base-url="<?php echo htmlspecialchars(BASE_URL); ?>" data-csrf="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
<div class="dashboard-container">
    <?php 
    if (in_array($_SESSION['user_type'] ?? '', ['Staff', 'Manager'])) {
        include __DIR__ . '/../includes/staff_sidebar.php';
    } else {
        include __DIR__ . '/../includes/admin_sidebar.php';
    }
    ?>
    <div class="main-content">
        <div id="staffJoCustomizationsPage" x-data="joManager('ALL')" x-init="init()" class="pf-staff-customizations-root" @keydown.escape.window="onSvcEscape()">
        <header>
            <div>
                <h1 class="page-title">Customizations</h1>
                <p class="page-subtitle">Track and manage all custom jobs</p>
            </div>
        </header>

        <main>
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <span class="kpi-card-inner">
                        <span class="kpi-label">Total Customizations</span>
                        <span class="kpi-value"><?php echo number_format($total_jobs); ?></span>
                        <span class="kpi-sub"><?php echo number_format($completed_jobs); ?> items finished</span>
                    </span>
                </div>
                <div class="kpi-card amber">
                    <span class="kpi-card-inner">
                        <span class="kpi-label">Pending Approval</span>
                        <span class="kpi-value"><?php echo number_format($pending_jobs); ?></span>
                        <span class="kpi-sub">Awaiting review</span>
                    </span>
                </div>
                <div class="kpi-card blue">
                    <span class="kpi-card-inner">
                        <span class="kpi-label">Approved</span>
                        <span class="kpi-value"><?php echo number_format($approval_jobs); ?></span>
                        <span class="kpi-sub">Ready for production</span>
                    </span>
                </div>
                <div class="kpi-card emerald">
                    <span class="kpi-card-inner">
                        <span class="kpi-label">In Production</span>
                        <span class="kpi-value"><?php echo number_format($in_production); ?></span>
                        <span class="kpi-sub">Active task tracks</span>
                    </span>
                </div>
            </div>

            <!-- Jobs List & Filters (matching Enterprise reference) -->
            <div class="card overflow-visible">
                <div class="toolbar-container">
                    <div class="toolbar-group">
                        <div class="pf-custom-tabs">
                            <button type="button" @click="activeStatus = 'ALL'" :class="activeStatus === 'ALL' ? 'active' : ''" class="pill-tab">
                                <span>ALL</span>
                                <span class="tab-count" x-text="getStatusCount('ALL')"></span>
                            </button>
                            <button type="button" @click="activeStatus = 'PENDING'" :class="activeStatus === 'PENDING' ? 'active' : ''" class="pill-tab">
                                <span>PENDING</span>
                                <span class="tab-count" x-text="getStatusCount('PENDING')"></span>
                            </button>
                            <button type="button" @click="activeStatus = 'APPROVED'" :class="activeStatus === 'APPROVED' ? 'active' : ''" class="pill-tab">
                                <span>APPROVED</span>
                                <span class="tab-count" x-text="getStatusCount('APPROVED')"></span>
                            </button>
                            <button type="button" @click="activeStatus = 'TO_PAY'" :class="activeStatus === 'TO_PAY' ? 'active' : ''" class="pill-tab">
                                <span>TO PAY</span>
                                <span class="tab-count" x-text="getStatusCount('TO_PAY')"></span>
                            </button>
                            <button type="button" @click="activeStatus = 'TO_VERIFY'" :class="activeStatus === 'TO_VERIFY' ? 'active' : ''" class="pill-tab">
                                <span>TO VERIFY</span>
                                <span class="tab-count" x-text="getStatusCount('TO_VERIFY')"></span>
                                <span x-show="getStatusCount('TO_VERIFY') > 0" style="position:absolute;top:-4px;right:-4px;width:10px;height:10px;background:#ef4444;border-radius:9999px;border:2px solid #fff;animation:pf-tab-pulse 2s ease-in-out infinite;"></span>
                            </button>
                            <button type="button" @click="activeStatus = 'IN_PRODUCTION'" :class="activeStatus === 'IN_PRODUCTION' ? 'active' : ''" class="pill-tab">
                                <span>IN PRODUCTION</span>
                                <span class="tab-count" x-text="getStatusCount('IN_PRODUCTION')"></span>
                            </button>
                            <button type="button" @click="activeStatus = 'TO_RECEIVE'" :class="activeStatus === 'TO_RECEIVE' ? 'active' : ''" class="pill-tab">
                                <span>TO PICKUP</span>
                                <span class="tab-count" x-text="getStatusCount('TO_RECEIVE')"></span>
                            </button>
                            <button type="button" @click="activeStatus = 'COMPLETED'" :class="activeStatus === 'COMPLETED' ? 'active' : ''" class="pill-tab">
                                <span>COMPLETED</span>
                                <span class="tab-count" x-text="getStatusCount('COMPLETED')"></span>
                            </button>
                            <button type="button" @click="activeStatus = 'CANCELLED'" :class="activeStatus === 'CANCELLED' ? 'active' : ''" class="pill-tab">
                                <span>CANCELLED</span>
                                <span class="tab-count" x-text="getStatusCount('CANCELLED')"></span>
                            </button>
                        </div>
                    </div>

                    <div class="toolbar-group" style="margin-left: auto;">
    
                        <!-- Sort Menu -->
                        <div style="position: relative;">
                            <button @click="sortOpen = !sortOpen; filterOpen = false" class="toolbar-btn" :class="sortOrder !== 'newest' ? 'active' : ''">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                                <span>Sort by</span>
                            </button>
                            <div x-show="sortOpen" @click.away="sortOpen = false" x-cloak class="dropdown-panel sort-dropdown" style="right: 0;">
                                <div class="sort-option" :class="sortOrder === 'newest' ? 'active' : ''" @click="sortOrder = 'newest'; sortOpen = false">
                                    <span>Newest to Oldest</span>
                                    <svg x-show="sortOrder === 'newest'" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" style="margin-left: auto; color: #0d9488;"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="sort-option" :class="sortOrder === 'oldest' ? 'active' : ''" @click="sortOrder = 'oldest'; sortOpen = false">
                                    <span>Oldest to Newest</span>
                                    <svg x-show="sortOrder === 'oldest'" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" style="margin-left: auto; color: #0d9488;"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="sort-option" :class="sortOrder === 'az' ? 'active' : ''" @click="sortOrder = 'az'; sortOpen = false">
                                    <span>A → Z</span>
                                    <svg x-show="sortOrder === 'az'" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" style="margin-left: auto; color: #0d9488;"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="sort-option" :class="sortOrder === 'za' ? 'active' : ''" @click="sortOrder = 'za'; sortOpen = false">
                                    <span>Z → A</span>
                                    <svg x-show="sortOrder === 'za'" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" style="margin-left: auto; color: #0d9488;"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Menu -->
                        <div style="position: relative;">
                            <button @click="filterOpen = !filterOpen; sortOpen = false" class="toolbar-btn" :class="(serviceFilter !== 'ALL' || dateFilter !== 'ALL') ? 'active' : ''">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                <span>Filter</span>
                                <template x-if="serviceFilter !== 'ALL' || dateFilter !== 'ALL'">
                                    <span class="filter-badge" x-text="(serviceFilter !== 'ALL' ? 1 : 0) + (dateFilter !== 'ALL' ? 1 : 0)"></span>
                                </template>
                            </button>
                            <div x-show="filterOpen" @click.away="filterOpen = false" x-cloak class="dropdown-panel filter-panel" style="right: 0;">
                                <div class="filter-header">Filter</div>
                                
                                <div class="filter-section">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <span class="filter-label" style="margin:0;">Service Type</span>
                                        <button @click="serviceFilter = 'ALL'" class="filter-reset-link">Reset</button>
                                    </div>
                                    <select x-model="serviceFilter" class="filter-select">
                                        <option value="ALL">All Services</option>
                                        <option value="T-SHIRT PRINTING">T-Shirt Printing</option>
                                        <option value="TARPAULIN PRINTING">Tarpaulin</option>
                                        <option value="DECALS/STICKERS (PRINT/CUT)">Stickers/Decals</option>
                                        <option value="TRANSPARENT STICKER PRINTING">Transparent Stickers</option>
                                        <option value="SINTRA BOARD">Sintraboard</option>
                                        <option value="REFLECTORIZED SIGNAGE">Reflectorized</option>
                                        <option value="SOUVENIRS">Souvenirs</option>
                                    </select>
                                </div>

                                <div class="filter-section">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <span class="filter-label" style="margin:0;">Date range</span>
                                        <button @click="dateFilter = 'ALL'; customDateFrom = ''; customDateTo = ''" class="filter-reset-link">Reset</button>
                                    </div>
                                    <select x-model="dateFilter" class="filter-select" style="margin-bottom:8px;">
                                        <option value="ALL">All Dates</option>
                                        <option value="TODAY">Today</option>
                                        <option value="WEEK">This Week</option>
                                        <option value="MONTH">This Month</option>
                                        <option value="CUSTOM">Custom Range</option>
                                    </select>
                                    <div x-show="dateFilter === 'CUSTOM'" style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                                        <div>
                                            <div style="font-size:11px; color:#6b7280; margin-bottom:4px;">From:</div>
                                            <input type="date" x-model="customDateFrom" class="filter-input">
                                        </div>
                                        <div>
                                            <div style="font-size:11px; color:#6b7280; margin-bottom:4px;">To:</div>
                                            <input type="date" x-model="customDateTo" class="filter-input">
                                        </div>
                                    </div>
                                </div>

                                <div class="filter-section">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <span class="filter-label" style="margin:0;">Keyword search</span>
                                        <button @click="search = ''" class="filter-reset-link">Reset</button>
                                    </div>
                                    <input type="text" x-model="search" class="filter-input" placeholder="Search...">
                                </div>

                                <div class="filter-footer">
                                    <button @click="serviceFilter = 'ALL'; dateFilter = 'ALL'; customDateFrom = ''; customDateTo = ''; search = '';" class="filter-btn-reset" style="width:100%;">
                                        Reset all filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto -mx-6 px-6" style="clear:both;">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="pl-6 pr-4 py-4 w-[12%] border-b border-gray-100">Order #</th>
                                <th class="px-4 py-4 w-[30%] border-b border-gray-100">Customization Info</th>
                                <th class="px-4 py-4 w-[18%] border-b border-gray-100 text-center">Status</th>
                                <th class="px-4 py-4 w-[8%] border-b border-gray-100 text-center">Source</th>
                                <th class="px-4 py-4 w-[16%] border-b border-gray-100">Customer</th>
                                <th class="px-4 py-4 w-[12%] border-b border-gray-100 text-right">Created</th>
                                <th class="px-4 py-4 w-[10%] border-b border-gray-100 text-center uppercase tracking-widest text-[10px]">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="jo in paginatedOrders" :key="(jo.order_type || 'JOB') + '-' + jo.id">
                                <tr @click="viewDetails(jo.id, jo.order_type || 'JOB')" class="group transition-all hover:bg-gray-50/50 relative cursor-pointer">
                                    <td class="pl-6 pr-4 py-4 relative">
                                        <div class="row-indicator"></div>
                                        <span class="table-text-main" x-text="(jo.order_type === 'ORDER' ? '#ORD-' : (jo.order_type === 'SERVICE' ? '#SRV-' : (jo.order_type === 'CUSTOMIZATION' ? '#CUST-' : '#JO-'))) + jo.id.toString().padStart(5, '0')"></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex flex-col gap-0 min-w-0">
                                                <div class="table-text-main truncate" x-text="jo.job_title || jo.service_type"></div>
                                                <div class="table-text-sub uppercase tracking-wider" x-show="jo.order_type !== 'SERVICE'"><span x-text="jo.width_ft"></span>'×<span x-text="jo.height_ft"></span>' • <span x-text="jo.quantity"></span> pcs</div>
                                                <div class="table-text-sub uppercase tracking-wider" x-show="jo.order_type === 'SERVICE'">Service purchase</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div :class="{
                                            'badge-fulfilled':  jo.status === 'COMPLETED',
                                            'badge-approved':   jo.status === 'APPROVED',
                                            'badge-topay':      jo.status === 'TO_PAY',
                                            'badge-verify':     jo.status === 'VERIFY_PAY',
                                            'badge-production': jo.status === 'IN_PRODUCTION',
                                            'badge-pickup':     jo.status === 'TO_RECEIVE' || jo.status === 'READY_TO_COLLECT',
                                            'badge-pending':    jo.status === 'PENDING',
                                            'badge-cancelled':  jo.status === 'CANCELLED'
                                        }" class="status-badge-pill" x-text="jo.status === 'COMPLETED' ? 'Fulfilled' : 
                                           (jo.status === 'APPROVED' ? 'Approved' : 
                                           (jo.status === 'TO_PAY' ? 'To Pay' : 
                                           (jo.status === 'VERIFY_PAY' ? 'To Verify' : 
                                           (jo.status === 'IN_PRODUCTION' ? 'Processing' : 
                                           (jo.status === 'TO_RECEIVE' || jo.status === 'READY_TO_COLLECT' ? 'To Pickup' : jo.status)))))">
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <template x-if="['pos','walk-in'].includes((jo.order_source || '').toLowerCase())">
                                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;background:#fef3c7;color:#92400e;">🖥 POS</span>
                                        </template>
                                        <template x-if="!['pos','walk-in'].includes((jo.order_source || '').toLowerCase())">
                                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af;">🌐 Online</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="table-text-main" x-text="jo.first_name + ' ' + (jo.last_name || '')"></div>
                                        <div class="table-text-sub" style="margin-top:4px;max-width:220px;word-break:break-word;" x-show="jo.customer_contact" x-text="jo.customer_contact"></div>
                                        <div style="margin-top:4px;">
                                            <span style="font-size:10px; font-weight:700; width:100px; padding:2px 0;" class="status-badge-pill" :class="jo.customer_type === 'NEW' ? 'badge-approved' : 'badge-fulfilled'" x-text="jo.customer_type"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="table-text-main" x-text="jo.created_at ? new Date(jo.created_at).toLocaleDateString(undefined, {month:'long', day:'numeric', year:'numeric'}) : ''"></div>
                                        <div class="table-text-sub uppercase" x-text="jo.due_date ? 'Due ' + new Date(jo.due_date).toLocaleDateString() : ''"></div>
                                    </td>
                                    <td class="px-4 py-4 text-center space-x-1">
                                        <button @click.stop="viewDetails(jo.id, jo.order_type || 'JOB')" class="btn-staff-action btn-staff-action-blue">View</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredOrders.length === 0">
                                <td colspan="7" class="px-6 py-24 text-center">
                                    <span class="table-text-sub uppercase tracking-widest">No matching jobs in this stage</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div x-show="totalPages > 1" style="display:block; width:100%; text-align:center; margin-top:20px; padding-top:16px; border-top:1px solid #f3f4f6;">
                    <div style="display:inline-flex; align-items:center; justify-content:center; gap:4px;">
                        <button x-show="currentPage > 1" @click="currentPage--" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:500;transition:all 0.2s;" onmouseover="this.style.background='#f5f7fa'" onmouseout="this.style.background='white'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <template x-for="(p, i) in pageNumbers" :key="i">
                        <span style="display:inline-flex;">
                            <span x-show="p === '...'" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;font-size:13px;color:#9ca3af;letter-spacing:1px;">···</span>
                            <button x-show="p !== '...'" @click="currentPage = p" :style="currentPage === p ? 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #0d9488;background:#0d9488;color:white;text-decoration:none;font-size:13px;font-weight:600;' : 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:white;color:#374151;text-decoration:none;font-size:13px;font-weight:500;transition:all 0.2s;'" x-text="p"></button>
                        </span>
                    </template>
                    <button x-show="currentPage < totalPages" @click="currentPage++" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:500;transition:all 0.2s;" onmouseover="this.style.background='#f5f7fa'" onmouseout="this.style.background='white'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    </div>
                </div>
            </div>
        </main>

    <!-- No more materials modal - integrated into details -->

<!-- Image Preview Lightbox -->
<div x-show="previewFile" x-cloak @click.self="previewFile = null" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); z-index:10000; max-width:90vw; max-height:90vh;">
    <div style="position:relative; background:white; border-radius:16px; padding:20px; box-shadow:0 25px 50px rgba(0,0,0,0.3); border:1px solid #e5e7eb;">
        <button @click="previewFile = null" style="position:absolute; top:10px; right:10px; background:#f3f4f6; border:none; color:#374151; font-size:24px; width:36px; height:36px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s; z-index:10001; font-weight:300; line-height:1;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">&times;</button>
        <img :src="previewFile" @click.stop style="max-width:80vw; max-height:70vh; border-radius:8px; display:block;">
        <div style="margin-top:16px; text-align:center;">
            <a :href="previewFile" download @click.stop style="background:#06A1A1; color:white; padding:10px 24px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s;" onmouseover="this.style.background='#047676'" onmouseout="this.style.background='#06A1A1'">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download Artwork
            </a>
        </div>
    </div>
</div>

<!-- Customization Details Modal — matching customers_management.php style -->
<div x-show="showDetailsModal" x-cloak>
    <div class="modal-overlay" @click.self="showDetailsModal = false">
        <div class="modal-panel" @click.stop>

            <!-- Loading State -->
            <div x-show="loadingDetails" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#06A1A1;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading job details...</p>
            </div>

            <!-- Content -->
            <div x-show="!loadingDetails && currentJo.id">

                <!-- Modal Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;" x-text="'Customization #' + currentJo.id"></h3>
                        <p style="font-size:12px;color:#6b7280;margin:2px 0 0;" x-text="getCorrectServiceType(currentJo)"></p>
                    </div>
                    <button @click="showDetailsModal = false" style="background:transparent;border:none;cursor:pointer;color:#6b7280;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div style="padding:24px;">

                    <!-- Customer Row -->
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid #f3f4f6;">
                        <div x-show="!currentJo.customer_profile_picture || currentJo.customer_profile_picture === 'null' || currentJo.customer_profile_picture === 'undefined'" style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#06A1A1,#047676);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:22px;flex-shrink:0;" x-text="currentJo.customer_full_name ? currentJo.customer_full_name[0].toUpperCase() : '?'"></div>
                        <img x-show="currentJo.customer_profile_picture && currentJo.customer_profile_picture !== 'null' && currentJo.customer_profile_picture !== 'undefined'" :src="getProfileImage(currentJo.customer_profile_picture)" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid #06A1A1;background:#f3f4f6;flex-shrink:0;" onerror="this.src='/printflow/public/assets/uploads/profiles/default.png'">
                        <div>
                            <div style="font-size:16px;font-weight:700;color:#1f2937;" x-text="currentJo.customer_full_name"></div>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                                <span style="font-size:11px; font-weight:700; min-width:80px; padding:2px 8px;" class="status-badge-pill" :class="currentJo.customer_type === 'NEW' ? 'badge-approved' : 'badge-fulfilled'" x-text="currentJo.customer_type"></span>
                                <span style="font-size:12px;color:#6b7280;" x-text="currentJo.customer_contact"></span>
                            </div>
                            <div x-show="currentJo.customer_address" style="font-size:12px;color:#6b7280;margin-top:8px;max-width:100%;word-break:break-word;" x-text="currentJo.customer_address"></div>
                        </div>
                    </div>


                    <!-- Dynamic Order Details (service-specific fields from customization_data) -->
                    <template x-if="currentJo.items && currentJo.items.length > 0">
                        <div style="margin-bottom:20px; padding:16px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:12px;">Order Details (Customer Specifications)</label>
                            <template x-for="(item, idx) in currentJo.items" :key="item.order_item_id || idx">
                                <div style="margin-bottom:16px; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
                                    <div style="font-size:13px; font-weight:700; color:#1f2937; margin-bottom:10px;" x-text="getDynamicProductName(item) + ' × ' + item.quantity"></div>
                                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:10px;">
                                        <template x-for="([k, v]) in getDisplayableCustom(item.customization)" :key="k">
                                            <div style="padding:8px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; min-width:0; overflow-wrap:break-word;">
                                                <div style="font-size:10px; font-weight:600; color:#6b7280; text-transform:uppercase; margin-bottom:2px;" x-text="getCustomLabel(k)"></div>
                                                <div style="font-size:12px; font-weight:500; color:#1f2937; word-break:break-word; overflow-wrap:break-word;" x-text="formatCustomValuePlain(v)"></div>
                                                <a x-show="isDisplayableLink(v)" :href="sanitizeStaffLink(v)" target="_blank" rel="noopener noreferrer" style="font-size:11px;color:#4f46e5;font-weight:600;margin-top:4px;display:inline-block;">Open link →</a>
                                            </div>
                                        </template>
                                    </div>
                                    <template x-if="item.design_url">
                                        <div style="margin-top:12px;">
                                            <div style="font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:6px;">Design Preview</div>
                                            <div style="display:flex; align-items:flex-end; gap:12px;">
                                                <img :src="item.design_url" 
                                                     @click="previewFile = item.design_url"
                                                     style="width:140px; height:auto; border-radius:10px; border:1px solid #e2e8f0; cursor:zoom-in; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);" 
                                                     onerror="this.src='<?php echo htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/assets/images/services/default.png', ENT_QUOTES, 'UTF-8'); ?>'">
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="item.reference_url">
                                        <div style="margin-top:12px;">
                                            <div style="font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:6px;">Reference image</div>
                                            <div style="display:flex; align-items:flex-end; gap:12px;">
                                                <img :src="item.reference_url"
                                                     @click="previewFile = item.reference_url"
                                                     style="width:140px; height:auto; border-radius:10px; border:1px solid #e2e8f0; cursor:zoom-in; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);"
                                                     onerror="this.style.display='none'">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>


                    <!-- Notes -->
                    <div style="margin-bottom:20px;" x-show="combinedCustomerNotes().trim() !== '' && combinedCustomerNotes() !== 'No specific instructions.'">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:6px;">Order Notes</label>
                        <div style="font-size:13px;color:#6b7280;background:#fffbeb;border:1px solid #fef3c7;padding:10px 14px;border-radius:8px;word-break:break-word;overflow-wrap:break-word;white-space:pre-wrap;" x-text="combinedCustomerNotes()"></div>
                    </div>

                    <!-- 4. TO_VERIFY (Payment Verification) -->
                    <template x-if="isVerifyStageRow(currentJo)">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">
                            <label style="font-size:11px;font-weight:700;color:#374151;text-transform:uppercase;display:block;margin-bottom:16px;">Step 4: Verify Payment Proof</label>
                            
                            <div style="display:flex; gap:20px; align-items:flex-start;">
                                <div style="width:160px; flex-shrink:0;">
                                    <template x-if="currentJo.payment_proof_path">
                                        <a :href="'/printflow/api_view_proof.php?file=' + encodeURIComponent(currentJo.payment_proof_path)"
                                           target="_blank" rel="noopener noreferrer"
                                           style="display:block;line-height:0;">
                                            <img :src="'/printflow/api_view_proof.php?file=' + encodeURIComponent(currentJo.payment_proof_path)"
                                                 style="width:100%; height:auto; border-radius:8px; border:1px solid #d1d5db; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);"
                                                 alt="Proof — opens full size in new tab">
                                        </a>
                                    </template>
                                </div>
                                <div style="flex:1;">
                                    <div style="margin-bottom:16px;">
                                        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase;">Amount Submitted</div>
                                        <div style="font-size:22px; font-weight:800; color:#1f2937;" x-text="'₱' + Number(currentJo.payment_submitted_amount || 0).toLocaleString()"></div>
                                    </div>
                                    <div style="display:flex; gap:10px;">
                                        <button @click="verifyPayment()" class="btn-staff-action btn-staff-action-emerald" style="flex:1;">Approve Payment</button>
                                        <button @click="openRejectPaymentModal()" class="btn-staff-action btn-staff-action-red" style="flex:1;">Reject</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- 2. APPROVED (Set Price & Materials) -->
                    <template x-if="currentJo.status === 'APPROVED'">
                        <div style="margin-bottom:20px; display:flex; flex-direction:column; gap:20px;">
                            
                            <!-- Production Details Section -->
                            <div style="padding:20px; border-radius:16px; border:1px solid #e2e8f0; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                    <h3 style="font-size:14px; font-weight:700; color:#1f2937; text-transform:uppercase; letter-spacing:0.025em; margin:0;">Production Assignment</h3>
                                    <span style="font-size:11px; background:#f3f4f6; color:#6b7280; padding:4px 10px; border-radius:100px; font-weight:600;" x-text="getCorrectServiceType(currentJo)"></span>
                                </div>

                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
                                    
                                    <!-- A. Materials Selection -->
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <label style="font-size:12px; font-weight:700; color:#374151;">[1] Core Materials</label>
                                        
                                        <!-- Searchable Selection -->
                                        <div style="position:relative;">
                                            <input type="text" x-model="materialSearch" placeholder="Search materials (e.g. tarpaulin, vinyl...)" 
                                                   style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; margin-bottom:8px;">
                                            
                                            <select x-model="newMaterialId" @change="newMaterialId = $event.target.value; newMaterialRollId = ''; availableRollsList = []; if(isRollTracked(newMaterialId)) loadAvailableRolls(newMaterialId);" 
                                                    style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:white; cursor:pointer;">
                                                <option value="">-- Choose Material --</option>
                                                <template x-for="item in availableMaterialsForCurrentOrder" :key="item.id">
                                                    <option :value="item.id" x-text="`${item.name} (${item.current_stock} ${item.unit_of_measure})`"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <template x-if="newMaterialId">
                                            <div style="padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                                <div style="grid-column: span 2;">
                                                    <label style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;">Qty / Length</label>
                                                    <input type="number" x-model.number="newMaterialQty" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                                </div>
                                                <template x-if="isTarpaulin(newMaterialId)">
                                                    <div style="grid-column: span 2;">
                                                        <label style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;">Height (ft)</label>
                                                        <input type="number" x-model.number="newMaterialHeight" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                                    </div>
                                                </template>
                                                <button @click="addMaterialToQueue()" class="btn-staff-action btn-staff-action-indigo" style="grid-column: span 2; padding:8px; font-size:12px; font-weight:600;">Add to Order Content</button>
                                            </div>
                                        </template>

                                        <div x-show="pendingMaterials.length > 0" style="display:flex; flex-direction:column; gap:6px;">
                                            <template x-for="(pm, idx) in pendingMaterials" :key="idx">
                                                <div style="display:flex; align-items:center; justify-content:space-between; background:#f1f5f9; border-radius:8px; padding:8px 12px; font-size:12px; border:1px solid #e2e8f0;">
                                                    <div>
                                                        <span style="font-weight:600; color:#1e293b;" x-text="pm.name"></span>
                                                        <span x-show="pm.qty > 0" style="margin-left:4px; font-weight:800; color:#06A1A1;" x-text="'x' + pm.qty"></span>
                                                    </div>
                                                    <div style="display:flex; align-items:center; gap:12px;">
                                                        <span style="color:#64748b;" x-text="pm.qty + ' ' + pm.uom"></span>
                                                        <button @click="pendingMaterials.splice(idx,1)" style="color:#ef4444; border:none; background:none; cursor:pointer; font-weight:700;">✕</button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- B. Ink Options -->
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <div style="display:flex; align-items:center; justify-content:space-between;">
                                            <label style="font-size:12px; font-weight:700; color:#374151;">[2] Ink Options</label>
                                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                                                <input type="checkbox" x-model="useInk" style="width:16px; height:16px; cursor:pointer; accent-color:#06A1A1;">
                                                <span style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">Use Ink</span>
                                            </label>
                                        </div>

                                        <div x-show="useInk" x-transition style="padding:16px; border:1px solid #cbd5e1; border-radius:12px; background:#f9fafb;">
                                            <label style="font-size:11px; font-weight:700; color:#374151; text-transform:uppercase; margin-bottom:10px; display:block;">Select Ink Set</label>
                                            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px;">
                                                <template x-for="type in availableInkOptionsForService" :key="type">
                                                    <button type="button" @click="inkCategorySelected = type" 
                                                            :style="inkCategorySelected === type ? 'background:#06A1A1; color:white; border-color:#06A1A1;' : 'background:white; color:#64748b; border-color:#e2e8f0;'"
                                                            style="padding:8px 16px; border-radius:8px; border:2px solid; font-size:12px; font-weight:700; transition:all 0.2s; cursor:pointer;"
                                                            x-text="type"></button>
                                                </template>
                                            </div>

                                            <template x-if="inkCategorySelected">
                                                <div>
                                                    <div style="background:#fff; padding:16px; border-radius:10px; border:1px solid #e2e8f0;">
                                                        <div style="font-size:11px; font-weight:700; color:#374151; text-transform:uppercase; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                                                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            Ink Consumption (ml)
                                                        </div>
                                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                                            <div>
                                                                <label style="font-size:10px; font-weight:700; color:#ef4444; text-transform:uppercase; display:flex; align-items:center; gap:4px; margin-bottom:6px;">
                                                                    <span style="width:12px; height:12px; background:#ef4444; border-radius:50%; display:inline-block;"></span>
                                                                    RED
                                                                </label>
                                                                <div style="position:relative;">
                                                                    <input type="number" x-model.number="inkRed" step="0.1" min="0" placeholder="0.0" style="width:100%; padding:10px 32px 10px 12px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; font-weight:600; transition:border-color 0.2s;" onfocus="this.style.borderColor='#ef4444'" onblur="this.style.borderColor='#e5e7eb'">
                                                                    <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:11px; color:#9ca3af; font-weight:600;">ml</span>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label style="font-size:10px; font-weight:700; color:#3b82f6; text-transform:uppercase; display:flex; align-items:center; gap:4px; margin-bottom:6px;">
                                                                    <span style="width:12px; height:12px; background:#3b82f6; border-radius:50%; display:inline-block;"></span>
                                                                    BLUE
                                                                </label>
                                                                <div style="position:relative;">
                                                                    <input type="number" x-model.number="inkBlue" step="0.1" min="0" placeholder="0.0" style="width:100%; padding:10px 32px 10px 12px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; font-weight:600; transition:border-color 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                                                                    <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:11px; color:#9ca3af; font-weight:600;">ml</span>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label style="font-size:10px; font-weight:700; color:#1f2937; text-transform:uppercase; display:flex; align-items:center; gap:4px; margin-bottom:6px;">
                                                                    <span style="width:12px; height:12px; background:#1f2937; border-radius:50%; display:inline-block;"></span>
                                                                    BLACK
                                                                </label>
                                                                <div style="position:relative;">
                                                                    <input type="number" x-model.number="inkBlack" step="0.1" min="0" placeholder="0.0" style="width:100%; padding:10px 32px 10px 12px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; font-weight:600; transition:border-color 0.2s;" onfocus="this.style.borderColor='#1f2937'" onblur="this.style.borderColor='#e5e7eb'">
                                                                    <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:11px; color:#9ca3af; font-weight:600;">ml</span>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label style="font-size:10px; font-weight:700; color:#eab308; text-transform:uppercase; display:flex; align-items:center; gap:4px; margin-bottom:6px;">
                                                                    <span style="width:12px; height:12px; background:#eab308; border-radius:50%; display:inline-block;"></span>
                                                                    YELLOW
                                                                </label>
                                                                <div style="position:relative;">
                                                                    <input type="number" x-model.number="inkYellow" step="0.1" min="0" placeholder="0.0" style="width:100%; padding:10px 32px 10px 12px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; font-weight:600; transition:border-color 0.2s;" onfocus="this.style.borderColor='#eab308'" onblur="this.style.borderColor='#e5e7eb'">
                                                                    <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:11px; color:#9ca3af; font-weight:600;">ml</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        <div x-show="!useInk" style="font-size:12px; color:#94a3b8; font-style:italic; text-align:center; padding:16px; background:#f9fafb; border-radius:8px; border:1px dashed #e2e8f0;">
                                            No ink required for this job
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Final Step: Pricing and Submit -->
                            <div style="padding:24px; border-radius:16px; border:2px solid #06A1A1; background:linear-gradient(135deg, #f0fdfa 0%, #ecfeff 100%); box-shadow:0 4px 12px rgba(6, 161, 161, 0.15);">
                                <div style="margin-bottom:20px;">
                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                                        <svg width="20" height="20" fill="none" stroke="#0f766e" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <label style="font-size:13px; font-weight:800; color:#0f766e; text-transform:uppercase; letter-spacing:0.5px;">[3] Set Final Price</label>
                                    </div>
                                    <div style="position:relative;">
                                        <span style="position:absolute; left:16px; top:50%; transform:translateY(-50%); font-weight:800; color:#0f766e; font-size:20px;">₱</span>
                                        <input type="number" x-model.number="jobPriceInput" 
                                               min="0" step="0.01" placeholder="0.00"
                                               @input="jobPriceInput = parseFloat($event.target.value) || 0"
                                               style="width:100%; padding:16px 16px 16px 40px; border:2px solid #06A1A1; border-radius:12px; font-size:24px; font-weight:800; color:#0f766e; outline:none; background:#ffffff; transition:all 0.2s;"
                                               onfocus="this.style.borderColor='#0d9488'; this.style.boxShadow='0 0 0 3px rgba(6, 161, 161, 0.1)'"
                                               onblur="this.style.borderColor='#06A1A1'; this.style.boxShadow='none'">
                                    </div>
                                    <div style="display:flex; align-items:center; gap:6px; margin-top:10px; padding:10px 12px; background:#fff; border-radius:8px; border:1px solid #d1fae5;">
                                        <svg width="14" height="14" fill="none" stroke="#059669" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span style="font-size:11px; color:#059669; font-weight:600;">This is the total amount the customer will pay</span>
                                    </div>
                                </div>
                                <button @click="submitToPay()" class="btn-action" style="width:100%; padding:16px; height:auto; font-size:16px; background:#06A1A1; color:#fff; border:none; font-weight:800; border-radius:12px; display:flex; align-items:center; justify-content:center; gap:12px; box-shadow:0 10px 20px rgba(6, 161, 161, 0.3); transition:all 0.2s; cursor:pointer;"
                                        onmouseover="this.style.background='#0d9488'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 24px rgba(6, 161, 161, 0.4)'"
                                        onmouseout="this.style.background='#06A1A1'; this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 20px rgba(6, 161, 161, 0.3)'">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Confirm Approval & Send to Payment</span>
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                </button>
                                <p style="font-size:11px; color:#0d9488; font-weight:500; margin-top:12px; display:flex; align-items:flex-start; gap:6px; line-height:1.5;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0; margin-top:2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Approving will notify the customer, set the final price, and prepare materials for production.</span>
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- 3. TO_PAY (Waiting for Payment) -->
                    <template x-if="currentJo.status === 'TO_PAY'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #dbeafe; background:#f0f9ff;">
                            <label style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;display:block;margin-bottom:12px;">Step 3: Awaiting Payment</label>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                                <div style="font-size:14px; font-weight:500; color:#1e40af;">Total Outstanding:</div>
                                <div style="font-size:20px; font-weight:800; color:#1e40af;" x-text="'₱' + Number(currentJo.estimated_total || 0).toLocaleString()"></div>
                            </div>
                            <div style="font-size:13px; color:#1e40af; line-height:1.5;">Waiting for the customer to upload payment proof. Once uploaded, it will appear in the TO VERIFY section.</div>
                        </div>
                    </template>

                    <!-- 5. IN_PRODUCTION -->
                    <template x-if="currentJo.status === 'IN_PRODUCTION' || currentJo.status === 'Processing'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #06A1A1; background:#f0fbfb;">
                            <label style="font-size:11px;font-weight:700;color:#0f766e;text-transform:uppercase;display:block;margin-bottom:12px;">Step 5: Production In Progress</label>
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:16px;">
                                <div style="font-size:14px; color:#0f766e; font-weight:500;" x-text="materialsDeductedSummary"></div>
                                <button @click="markReadyForPickup()" class="btn-action" style="background:#06A1A1; color:#fff; border:none; font-weight:600; padding:6px 16px; border-radius:8px; white-space:nowrap;">Mark as Ready for Pickup</button>
                            </div>
                        </div>
                    </template>

                    <!-- 6. TO_RECEIVE -->
                    <template x-if="currentJo.status === 'TO_RECEIVE'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #06A1A1; background:#f0fbfb;">
                            <label style="font-size:11px;font-weight:700;color:#0f766e;text-transform:uppercase;display:block;margin-bottom:12px;">Step 6: Ready for Pickup</label>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:14px; color:#0f766e; font-weight:500;">Customer has been notified to pick up the order.</div>
                                <button @click="completeOrder()" class="btn-action" style="background:#06A1A1; color:#fff; border:none; font-weight:600; padding:6px 16px; border-radius:8px;">Mark Final Completed</button>
                            </div>
                        </div>
                    </template>

                    <!-- 7. COMPLETED -->
                    <template x-if="currentJo.status === 'COMPLETED'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #bbf7d0; background:#f0fdf4;">
                            <label style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;display:block;margin-bottom:4px;">Workflow Finished</label>
                            <div style="font-size:15px; font-weight:700; color:#15803d; display:flex; align-items:center; gap:8px;">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Order Successfully Completed
                            </div>
                        </div>
                    </template>

                    <!-- CANCELLED -->
                    <template x-if="currentJo.status === 'CANCELLED'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #fca5a5; background:#fef2f2;">
                            <label style="font-size:11px;font-weight:700;color:#dc2626;text-transform:uppercase;display:block;margin-bottom:4px;">Workflow Terminated</label>
                            <div style="font-size:15px; font-weight:700; color:#dc2626; display:flex; align-items:center; gap:8px;">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                Order Cancelled
                            </div>
                        </div>
                    </template>

                    <div x-show="currentJo.materials && currentJo.materials.length > 0" style="margin-top:20px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Assigned Production Materials</label>
                        <template x-for="m in groupedMaterials" :key="m.item_id">
                            <div style="background:white; border:1px solid #e5e7eb; border-radius:8px; padding:10px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-size:12px; font-weight:600; color:#1f2937;">
                                        <span x-text="m.item_name"></span>
                                        <span x-show="m.track_by_roll == 0 && m.quantity > 0" style="margin-left:4px; font-weight:800; color:#06A1A1;" x-text="'x' + Number(m.quantity)"></span>
                                        <template x-if="m.track_by_roll == 1">
                                            <span style="margin-left:4px; font-weight:800; color:#06A1A1;" x-text="'x' + Number(m.computed_required_length_ft)"></span>
                                        </template>
                                    </div>
                                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                                        <span x-show="m.track_by_roll == 1">
                                            Req: <span x-text="m.computed_required_length_ft"></span>'
                                            <span x-show="m.roll_code"> (Roll: <span x-text="m.roll_code"></span>)</span>
                                            <span x-show="!m.roll_code"> (Auto Pick Roll)</span>
                                        </span>
                                        <span x-show="m.track_by_roll == 0">Qty: <span x-text="m.quantity"></span></span>
                                        <template x-if="m.metadata && m.metadata.lamination_item_id">
                                            <div style="color:#059669; font-weight:600; margin-top:4px;">
                                                + Lamination (Auto Pick Roll) — <span x-text="m.metadata.lamination_length_ft"></span>'
                                            </div>
                                        </template>
                                        <template x-if="m.metadata && m.metadata.waste_length_ft !== undefined">
                                            <div style="color:#b45309; margin-top:2px;">
                                                Recorded Waste: <span x-text="m.metadata.waste_length_ft"></span>'
                                            </div>
                                        </template>
                                        <span style="color:#06A1A1; font-weight:600; margin-left:8px;" x-show="m.deducted_at">✓ Deducted</span>
                                    </div>
                                </div>
                                <template x-if="!m.deducted_at">
                                    <button type="button" @click="removeMaterial(m.id)" style="background:none; border:none; color:#ef4444; font-size:11px; font-weight:600; cursor:pointer; padding:4px 8px; border-radius:4px; transition:all 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">Remove</button>
                                </template>
                            </div>
                        </template>
                    </div>

                    <template x-if="currentJo.ink_usage && currentJo.ink_usage.length > 0">
                        <div style="margin-top:16px;">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Ink Consumption Recorded</label>
                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                <template x-for="ink in (currentJo.ink_usage || [])" :key="ink.id">
                                    <div style="background:#fdf4ff; border:1px solid #fbcfe8; border-radius:6px; padding:6px 10px; font-size:11px; font-weight:600; color:#9d174d;">
                                        <span x-text="ink.item_name + ' → '"></span>
                                        <span x-text="ink.quantity_used + ' bottle'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Design Status / Revision Alert -->
                    <template x-if="currentJo.design_status === 'Revision Submitted'">
                        <div style="margin-bottom:20px; padding:12px; background:#e0f2fe; border:1px solid #bae6fd; border-radius:10px; display:flex; align-items:center; gap:10px;">
                             <div style="background:#0284c7; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 4px 6px -1px rgba(2, 132, 199, 0.4);">
                                 <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 11l5 5m0 0l-5 5m5-5H6"/></svg>
                             </div>
                            <div>
                                <div style="font-size:13px; font-weight:700; color:#0369a1;">Revision Re-submitted</div>
                                <div style="font-size:12px; color:#075985;">The customer has uploaded a new design file. Please review and approve.</div>
                            </div>
                        </div>
                    </template>

                    <!-- Artwork Files -->
                    <div x-show="currentJo.files && currentJo.files.length > 0" style="margin-top:16px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Artwork Files</label>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <template x-for="file in (currentJo.files || [])" :key="file.id">
                                <a :href="'/printflow/' + file.file_path.replace(/^\/+/, '')" target="_blank" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#1f2937;transition:border-color 0.2s;" onmouseover="this.style.borderColor='#06A1A1'" onmouseout="this.style.borderColor='#e5e7eb'">
                                    <span style="font-size:12px;font-weight:500;" x-text="file.file_name"></span>
                                    <span style="font-size:11px;color:#06A1A1;font-weight:600;">View ↗</span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>


                <!-- Modal Footer -->
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <!-- Left: Status actions -->
                    <div style="display:flex;gap:8px; flex-wrap:wrap; align-items:center;">
                        <div x-show="isPendingReviewStatus(currentJo) && !isVerifyStageRow(currentJo)" style="display:flex; gap:8px;">
                            <button type="button" @click="jobAction('APPROVED')" class="btn-action" style="padding:8px 16px; font-weight:600; background:#86efac; color:#166534; border:1px solid #86efac; border-radius:8px; transition:all 0.2s;" onmouseover="this.style.background='#22c55e'; this.style.borderColor='#22c55e'; this.style.color='#ffffff';" onmouseout="this.style.background='#86efac'; this.style.borderColor='#86efac'; this.style.color='#166534';">Approve to Set Price</button>
                            <button type="button" @click="openRevisionModal()" class="btn-action" style="padding:8px 16px; font-weight:600; background:#fca5a5; color:#991b1b; border:1px solid #fca5a5; border-radius:8px; transition:all 0.2s;" onmouseover="this.style.background='#ef4444'; this.style.borderColor='#ef4444'; this.style.color='#ffffff';" onmouseout="this.style.background='#fca5a5'; this.style.borderColor='#fca5a5'; this.style.color='#991b1b';">Request Revision</button>
                        </div>
                    </div>
                    <!-- Right: Close -->
                    <button @click="showDetailsModal = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- REVISION MODAL -->
    <template x-if="showRevisionModal">
        <div>
            <!-- Backdrop -->
            <div x-show="showRevisionModal" x-cloak
                 style="position:fixed; inset:0; z-index:10001; background:transparent;"
                 @click="closeRevisionModal()"></div>
            <!-- Modal Panel — true viewport center via transform -->
            <div x-show="showRevisionModal" x-cloak
                 style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:10002;
                        width:calc(100% - 32px); max-width:420px;
                        background:white; border-radius:16px;
                        box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);
                        border:1px solid #fee2e2; overflow:hidden;">
                <!-- Header -->
                <div style="padding:16px 20px; border-bottom:1px solid #fee2e2; background:#fef2f2; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:16px; font-weight:700; color:#b91c1c;">Request Revision</h3>
                    <button @click="closeRevisionModal()" style="background:none; border:none; color:#f87171; cursor:pointer;" onmouseover="this.style.color='#b91c1c'" onmouseout="this.style.color='#f87171'">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Body -->
                <div style="padding:20px;">
                    <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Reason for Revision</label>
                    <select x-model="revisionReasonSelect" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; margin-bottom:16px; outline:none;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'">
                        <option value="">-- Select a reason --</option>
                        <option value="Low image quality">Low image quality</option>
                        <option value="Wrong design uploaded">Wrong design uploaded</option>
                        <option value="Incorrect details provided">Incorrect details provided</option>
                        <option value="Not printable / invalid format">Not printable / invalid format</option>
                        <option value="Others">Others</option>
                    </select>
                    <div x-show="revisionReasonSelect === 'Others'" style="transition:all 0.2s;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Please specify</label>
                        <textarea x-model="revisionReasonText" rows="3" placeholder="Enter custom reason..." style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical; outline:none; box-sizing:border-box;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'"></textarea>
                    </div>
                </div>
                <!-- Footer -->
                <div style="padding:16px 20px; border-top:1px solid #f3f4f6; background:#f9fafb; display:flex; justify-content:flex-end; gap:8px;">
                    <button @click="closeRevisionModal()" class="btn-secondary">Cancel</button>
                    <button @click="submitRevision()" class="btn-action red">Submit Revision</button>
                </div>
            </div>
        </div>
    </template>

    <!-- REJECT PAYMENT MODAL -->
    <template x-if="showRejectPaymentModal">
        <div>
            <!-- Backdrop -->
            <div x-show="showRejectPaymentModal" x-cloak
                 style="position:fixed; inset:0; z-index:10001; background:transparent;"
                 @click="closeRejectPaymentModal()"></div>
            <!-- Modal Panel -->
            <div x-show="showRejectPaymentModal" x-cloak
                 style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:10002;
                        width:calc(100% - 32px); max-width:420px;
                        background:white; border-radius:16px;
                        box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);
                        border:1px solid #fee2e2; overflow:hidden;">
                <!-- Header -->
                <div style="padding:16px 20px; border-bottom:1px solid #fee2e2; background:#fef2f2; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:16px; font-weight:700; color:#b91c1c;">Reject Payment Proof</h3>
                    <button @click="closeRejectPaymentModal()" style="background:none; border:none; color:#f87171; cursor:pointer;" onmouseover="this.style.color='#b91c1c'" onmouseout="this.style.color='#f87171'">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Body -->
                <div style="padding:20px;">
                    <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Reason for Rejection</label>
                    <select x-model="rejectPaymentReasonSelect" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; margin-bottom:16px; outline:none;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'">
                        <option value="">-- Select a reason --</option>
                        <option value="Unclear image / receipt">Unclear image / receipt</option>
                        <option value="Incorrect amount submitted">Incorrect amount submitted</option>
                        <option value="Payment not received">Payment not received</option>
                        <option value="Expired reference">Expired reference</option>
                        <option value="Others">Others</option>
                    </select>
                    <div x-show="rejectPaymentReasonSelect === 'Others'" style="transition:all 0.2s;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Please specify</label>
                        <textarea x-model="rejectPaymentReasonText" rows="3" placeholder="Enter custom reason..." style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical; outline:none; box-sizing:border-box;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'"></textarea>
                    </div>
                </div>
                <!-- Footer -->
                <div style="padding:16px 20px; border-top:1px solid #f3f4f6; background:#f9fafb; display:flex; justify-content:flex-end; gap:8px;">
                    <button @click="closeRejectPaymentModal()" class="btn-secondary">Cancel</button>
                    <button @click="submitRejectPayment()" class="btn-action red">Confirm Rejection</button>
                </div>
            </div>
        </div>
    </template>
        <!-- Custom Staff Alert Modal -->
        <div x-show="alertModal.show" x-cloak style="position:fixed; inset:0; z-index:90000; display:flex; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px);">
            <div @click.self="closeStaffAlert()" style="position:fixed; inset:0; background:rgba(17,24,39,0.7);"></div>
            <div style="background:white; border-radius:24px; width:100%; max-width:400px; position:relative; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.4); animation:modalIn 0.3s ease-out; margin:auto;">
                <div style="padding:32px 32px 24px; text-align:center;">
                    <div style="width:56px; height:56px; background:#f0f9ff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                        <svg width="28" height="28" fill="none" stroke="#00A1A1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 x-text="alertModal.title" style="font-size:20px; font-weight:800; color:#111827; margin:0 0 8px;"></h3>
                    <p x-text="alertModal.message" style="font-size:15px; color:#4b5563; line-height:1.6; margin:0;"></p>
                </div>
                <div style="padding:0 32px 32px;">
                    <button @click="closeStaffAlert()" style="width:100%; background:#111827; color:white; border:none; border-radius:14px; padding:14px; font-weight:700; font-size:15px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'">OK</button>
                </div>
            </div>
        </div>

        <!-- Custom Staff Confirm Modal -->
        <div x-show="confirmModal.show" x-cloak style="position:fixed; inset:0; z-index:90000; display:flex; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px);">
            <div @click.self="closeStaffConfirm(false)" style="position:fixed; inset:0; background:rgba(17,24,39,0.7);"></div>
            <div style="background:white; border-radius:24px; width:100%; max-width:420px; position:relative; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.4); animation:modalIn 0.3s ease-out; margin:auto;">
                <div style="padding:32px 32px 24px; text-align:center;">
                    <div style="width:56px; height:56px; background:#fff7ed; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                        <svg width="28" height="28" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 x-text="confirmModal.title" style="font-size:20px; font-weight:800; color:#111827; margin:0 0 8px;"></h3>
                    <p x-text="confirmModal.message" style="font-size:15px; color:#4b5563; line-height:1.6; margin:0;"></p>
                </div>
                <div style="padding:0 32px 32px; display:flex; gap:12px;">
                    <button @click="closeStaffConfirm(false)" x-text="confirmModal.cancelText" style="flex:1; background:#f3f4f6; color:#4b5563; border:none; border-radius:14px; padding:14px; font-weight:700; font-size:15px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'"></button>
                    <button @click="closeStaffConfirm(true)" x-text="confirmModal.confirmText" style="flex:1; background:#00A1A1; color:white; border:none; border-radius:14px; padding:14px; font-weight:700; font-size:15px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(6,161,161,0.25)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'"></button>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/partials/service_order_modal.php'; ?>
        </div><!-- /#staffJoCustomizationsPage -->
    </div><!-- /.main-content -->
</div><!-- /.dashboard-container -->

<script src="<?php echo htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/assets/js/staff_service_order_modal.js'); ?>"></script>
<script>
    document.addEventListener('alpine:init', function () {
        Alpine.data('joManager', function (defaultStatus) {
            defaultStatus = defaultStatus || 'ALL';
            return {
            ...printflowStaffServiceOrderModalMixin({
                async afterSvcMutation() { await this.loadOrders(); }
            }),
            statuses: ['ALL', 'PENDING', 'APPROVED', 'TO_PAY', 'TO_VERIFY', 'IN_PRODUCTION', 'TO_RECEIVE', 'COMPLETED', 'CANCELLED'],
            activeStatus: defaultStatus || 'ALL',
            currentPage: 1,
            itemsPerPage: 15,
            orders: [],
            sortOrder: 'newest',
            sortOpen: false,
            filterOpen: false,
            machines: [],
            showDetailsModal: false,
            loadingDetails: false,
            showRevisionModal: false,
            revisionReasonSelect: '',
            revisionReasonText: '',
            showRejectPaymentModal: false,
            rejectPaymentReasonSelect: '',
            rejectPaymentReasonText: '',
            previewFile: null,
            currentJo: {},
            availableRolls: {},
            allInventoryItems: [],
            inventoryPollMs: 20000,
            newMaterialId: '',
            newMaterialQty: 1,
            newMaterialHeight: 0,
            newMaterialRollId: '',
            newMaterialNotes: '',
            newMaterialMetadata: {lamination: '', lamination_roll_id: ''},
            pendingMaterials: [],
            availableRollsList: [],
            laminationItemsList: [],
            availableLamRollsList: [],
            impactPreview: null,
            search: '',
            jobPriceInput: 0,
            
            // ── Profile Image Fallback ───────────────────────────────────
            getProfileImage(image) {
                if (!image || image === 'null' || image === 'undefined') {
                    return '/printflow/public/assets/uploads/profiles/default.png';
                }
                if (typeof image !== 'string') return '/printflow/public/assets/uploads/profiles/default.png';
                if (image.startsWith('/') || image.startsWith('http')) {
                    return image;
                }
                return '/printflow/public/assets/uploads/profiles/' + image;
            },

            getItemCount(name, list) {
                if (!list || !Array.isArray(list)) return 0;
                return list.filter(m => (m.item_name || m.name) === name).length;
            },

            get groupedMaterials() {
                if (!this.currentJo || !this.currentJo.materials) return [];
                const grouped = [];
                this.currentJo.materials.forEach(m => {
                    const existing = grouped.find(g => g.item_id === m.item_id);
                    if (existing) {
                        existing.quantity = (parseFloat(existing.quantity) || 0) + (parseFloat(m.quantity) || 0);
                        existing.computed_required_length_ft = (parseFloat(existing.computed_required_length_ft) || 0) + (parseFloat(m.computed_required_length_ft) || 0);
                    } else {
                        grouped.push({ ...m });
                    }
                });
                return grouped;
            },

            get materialsDeductedSummary() {
                if (!this.currentJo || !this.currentJo.materials || this.currentJo.materials.length === 0) {
                    return "Materials have been deducted from inventory.";
                }
                const counts = {};
                this.currentJo.materials.forEach(m => {
                    const name = m.item_name;
                    const q = parseFloat(m.track_by_roll == 1 ? m.computed_required_length_ft : m.quantity) || 0;
                    counts[name] = (counts[name] || 0) + q;
                });
                const summary = Object.entries(counts).map(([name, count]) => {
                    const cleanCount = Number(Number(count).toFixed(2));
                    return `${cleanCount}x ${name}`;
                }).join(", ");
                return summary + " deducted from inventory.";
            },
            
            // Ink Settings
            inkCategorySelected: '',
            inkBlue: '',
            inkRed: '',
            inkBlack: '',
            inkYellow: '',
            useInk: false,
            materialSearch: '',
            dateFilter: 'ALL',
            serviceFilter: 'ALL',
            customDateFrom: '',
            customDateTo: '',
            alertModal: {
                show: false,
                title: 'System Message',
                message: '',
                onClose: null
            },
            confirmModal: {
                show: false,
                title: 'Confirm Action',
                message: '',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                onConfirm: null,
                onCancel: null
            },
            showStaffAlert(title, message, onClose = null) {
                this.alertModal.title = title;
                this.alertModal.message = message;
                this.alertModal.onClose = onClose;
                this.alertModal.show = true;
            },
            closeStaffAlert() {
                const cb = this.alertModal.onClose;
                this.alertModal.show = false;
                if (typeof cb === 'function') cb();
            },
            showStaffConfirm(title, message, onConfirm, onCancel = null, confirmText = 'Confirm', cancelText = 'Cancel') {
                this.confirmModal.title = title;
                this.confirmModal.message = message;
                this.confirmModal.confirmText = confirmText;
                this.confirmModal.cancelText = cancelText;
                this.confirmModal.onConfirm = onConfirm;
                this.confirmModal.onCancel = onCancel;
                this.confirmModal.show = true;
            },
            closeStaffConfirm(isConfirm) {
                this.confirmModal.show = false;
                if (isConfirm && typeof this.confirmModal.onConfirm === 'function') {
                    this.confirmModal.onConfirm();
                } else if (!isConfirm && typeof this.confirmModal.onCancel === 'function') {
                    this.confirmModal.onCancel();
                }
            },

            serviceMapping: {
                'TARPAULIN PRINTING': { categories: [2], ink: 'TARP' },
                'T-SHIRT PRINTING': { categories: [7], ink: ['L120', 'L130'] },
                'DECALS/STICKERS (PRINT/CUT)': { categories: [3, 8], ink: ['L120', 'L130'] },
                'GLASS/WALL STICKERS': { categories: [3, 8], ink: ['L120', 'L130'] },
                'TRANSPARENT STICKERS': { categories: [3], ink: ['L120', 'L130'] },
                'REFLECTORIZED': { categories: [3], ink: ['L120', 'L130'] },
                'SINTRA BOARD': { categories: [3], ink: ['L120', 'L130'] },
                'SOUVENIRS': { categories: [3, 1], ink: ['L120', 'L130'] }
            },

            inkTypes: {
                'TARP': { 'BLUE': 24, 'RED': 25, 'BLACK': 26, 'YELLOW': 27 },
                'L120': { 'BLUE': 28, 'RED': 29, 'BLACK': 30, 'YELLOW': 31 },
                'L130': { 'BLUE': 32, 'RED': 33, 'BLACK': 34, 'YELLOW': 35 }
            },

            getDynamicProductName(item) {
                const custom = item.customization || {};
                
                // Helper to safely find a key value case-insensitively
                const findKey = (searchKeys) => {
                    for (const [k, v] of Object.entries(custom)) {
                        const lowerK = k.toLowerCase().replace(/_/g, ' ');
                        const lowerS = searchKeys.map(s => s.toLowerCase().replace(/_/g, ' '));
                        if (lowerS.includes(lowerK) && v) return v;
                    }
                    return null;
                };

                const sintraVal = findKey(['sintra_type', 'sintra type', 'type']);
                if (sintraVal || findKey(['is_standee', 'thickness', 'sintraboard_thickness'])) {
                    return 'Sintra Board - ' + (sintraVal || 'Standee');
                }

                const tarpVal = findKey(['tarp_size', 'tarp size']);
                if (tarpVal) {
                    return 'Tarpaulin Printing - ' + tarpVal;
                }

                const width = findKey(['width']);
                const height = findKey(['height']);
                if (width && height && findKey(['finish', 'with_eyelets'])) {
                    return 'Tarpaulin Printing (' + width + 'x' + height + ' ft)';
                }

                if (findKey(['vinyl_type', 'print_placement', 'tshirt_color', 'shirt_color', 'tshirt_size', 'shirt_source'])) {
                    return 'T-Shirt Printing';
                }

                const stickerVal = findKey(['sticker_type', 'sticker type', 'shape', 'cut_type']);
                if (stickerVal) {
                    return 'Decals/Stickers (Print/Cut) - ' + stickerVal;
                }

                return item.product_name || 'Standard Product';
            },
            getCorrectServiceType(jo) {
                if (!jo) return '';
                if (jo.items && jo.items.length > 0) {
                    for (const item of jo.items) {
                        const custom = item.customization || {};
                        const findKey = (searchKeys) => {
                            for (const [k, v] of Object.entries(custom)) {
                                const lowerK = k.toLowerCase().replace(/_/g, ' ');
                                const lowerS = searchKeys.map(s => s.toLowerCase().replace(/_/g, ' '));
                                if (lowerS.includes(lowerK) && v) return true;
                            }
                            return false;
                        };

                        // User Priority Logic
                        if (findKey(['sintra_type', 'sintra type', 'is_standee', 'type', 'thickness', 'sintraboard_thickness'])) return 'SINTRA BOARD';
                        if (findKey(['tarp_size', 'tarp size', 'with_eyelets', 'finish'])) return 'TARPAULIN PRINTING';
                        if (findKey(['width']) && findKey(['height']) && findKey(['finish', 'with_eyelets'])) return 'TARPAULIN PRINTING';
                        if (findKey(['vinyl_type', 'print_placement', 'tshirt_color', 'shirt_color', 'shirt_source'])) return 'T-SHIRT PRINTING';
                        if (findKey(['sticker_type', 'sticker type', 'shape', 'cut_type', 'lamination'])) return 'DECALS/STICKERS (PRINT/CUT)';
                    }
                }
                const raw = String(jo.service_type || jo.job_title || 'Custom Service').toUpperCase();
                // Standardize common fallbacks
                if (raw.includes('SINTRA')) return 'SINTRA BOARD';
                if (raw.includes('TARP')) return 'TARPAULIN PRINTING';
                if (raw.includes('SHIRT')) return 'T-SHIRT PRINTING';
                if (raw.includes('STICKER') || raw.includes('DECAL')) return 'DECALS/STICKERS (PRINT/CUT)';
                return raw;
            },

            customFieldLabels: {
                size: 'Size', color: 'Color', shirt_color: 'Color', print_placement: 'Placement',
                design_type: 'Design Type', template: 'Template', width: 'Width (ft)', height: 'Height (ft)',
                finish: 'Finish', with_eyelets: 'Eyelets', shape: 'Shape', waterproof: 'Waterproof',
                lamination: 'Lamination', laminate_option: 'Lamination Option', layout: 'Layout',
                dimensions: 'Dimensions', needed_date: 'Needed Date', notes: 'Notes', additional_notes: 'Notes',
                tshirt_provider: 'T-Shirt Provider', shirt_source: 'Shirt Source', brand: 'Brand',
                material: 'Material', surface_application: 'Surface', surface_type: 'Surface Type',
                sintraboard_thickness: 'Thickness', is_standee: 'Standee', sticker_type: 'Sticker Type',
                sintra_type: 'Type',
                cut_type: 'Cut Type', thickness: 'Thickness', installation_fee: 'Installation Fee',
                design_upload: 'Design upload', reference_upload: 'Reference upload',
                design_upload_path: 'Design file path', reference_upload_path: 'Reference file path'
            },
            // Redundant / internal keys to skip in the customization grid.
            // notes and additional_notes are handled separately in the yellow 'Order Notes' box.
            customFieldSkip: ['Branch_ID', 'branch_id', 'service_type', 'product_type', 'notes', 'additional_notes', 'layout_file', 'reference_file'],
            getDisplayableCustom(custom) {
                if (!custom || typeof custom !== 'object') return [];
                const skip = this.customFieldSkip;
                return Object.entries(custom).filter(([k, v]) => {
                    if (v === '' || v == null) return false;
                    if (skip.includes(k)) return false;
                    if (typeof v === 'string' && v.length > 2000) return false;
                    return true;
                });
            },
            getCustomLabel(k) {
                return this.customFieldLabels[k] || k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            },
            formatCustomValuePlain(v) {
                if (v == null) return '';
                if (typeof v === 'object') return JSON.stringify(v);
                return String(v);
            },
            isDisplayableLink(v) {
                if (v == null || typeof v === 'object') return false;
                const s = String(v).trim();
                if (s.length < 2) return false;
                if (/^https?:\/\//i.test(s)) return true;
                return s.startsWith('/');
            },
            sanitizeStaffLink(v) {
                const s = String(v).trim();
                if (/^https?:\/\//i.test(s)) return s;
                if (s.startsWith('/')) return s;
                return '#';
            },
            combinedCustomerNotes() {
                const j = this.currentJo;
                if (!j) return '';
                
                // Priority 1: Store order notes (Checkout notes)
                let note = (j.store_order_notes || '').trim();
                
                // Priority 2: Item-specific customization notes
                if (!note && j.items && j.items.length) {
                    for (const it of j.items) {
                        const c = it.customization || {};
                        const n = (c.notes || c.additional_notes || '').trim();
                        if (n) { 
                            note = n; 
                            break; 
                        }
                    }
                }
                
                // Priority 3: Production/Job notes (filter out Revision history)
                if (!note && j.notes) {
                    const lines = j.notes.split('\n')
                        .filter(l => !l.includes('[REVISION REQUEST]'))
                        .map(l => l.trim())
                        .filter(l => l !== '');
                    note = lines.join('\n');
                }

                return note || '';
            },
            isPendingReviewStatus(jo) {
                if (!jo) return false;
                const s = String(jo.status || '');
                const u = s.toUpperCase().replace(/\s+/g, '_');
                if (['PENDING', 'PENDING_REVIEW', 'PENDING_APPROVAL', 'FOR_REVISION'].includes(u)) return true;
                return ['Pending Review', 'Pending Approval', 'For Revision'].includes(s);
            },

            /** Row is in payment-verification stage — strictly status-based only. */
            isVerifyStageRow(row) {
                if (!row) return false;
                const s = String(row.status || '').toUpperCase().replace(/\s+/g, '_');
                return s === 'VERIFY_PAY' || s === 'TO_VERIFY' || s === 'PENDING_VERIFICATION' || s === 'DOWNPAYMENT_SUBMITTED';
            },

            /** Store/job row is actively in production — strictly status-based only. */
            isInProductionRow(row) {
                if (!row) return false;
                const raw = String(row.status || '').trim();
                const t = raw.toUpperCase().replace(/\s+/g, '_');
                if (t === 'IN_PRODUCTION' || t === 'PROCESSING' || t === 'PRINTING') return true;
                if (/PAID[-–_\s]+IN[-–_\s]+PROCESS/i.test(raw)) return true;
                return false;
            },

            /** Waiting for customer payment — strictly status-based only. */
            isToPayRow(row) {
                if (!row) return false;
                const s = String(row.status || '').toUpperCase().replace(/\s+/g, '_');
                return s === 'TO_PAY';
            },

            get availableMaterialsForCurrentOrder() {
                if (!this.currentJo || !this.allInventoryItems) return [];
                
                const serviceRaw = String(this.currentJo.service_type || this.currentJo.job_title || '').toUpperCase();
                let allowedCats = [];

                // Detect matching service from mapping
                for (const [key, map] of Object.entries(this.serviceMapping)) {
                    if (serviceRaw.includes(key)) {
                        allowedCats = map.categories;
                        break;
                    }
                }

                return this.allInventoryItems.filter(item => {
                    // MUST HAVE STOCK > 0
                    const stock = parseFloat(item.current_stock || 0);
                    if (stock <= 0) return false;

                    // If we found specific categories for this service, filter by them
                    if (allowedCats.length > 0) {
                        if (!allowedCats.includes(Number(item.category_id))) return false;
                    }

                    // Search filter
                    if (this.materialSearch && !item.name.toUpperCase().includes(this.materialSearch.toUpperCase())) {
                        return false;
                    }

                    return true;
                });
            },

            get availableInkOptionsForService() {
                if (!this.currentJo) return [];
                const serviceRaw = String(this.currentJo.service_type || this.currentJo.job_title || '').toUpperCase();
                for (const [key, map] of Object.entries(this.serviceMapping)) {
                    if (serviceRaw.includes(key)) {
                        return Array.isArray(map.ink) ? map.ink : [map.ink];
                    }
                }
                return ['L120', 'L130']; // Default
            },

            async init() {
                this.$watch('search', () => { this.currentPage = 1; });
                this.$watch('activeStatus', () => { this.currentPage = 1; });
                await this.loadOrders();
                await this.loadMachines();
                await this.loadAllInventoryItems();

                // Keep stock values in sync with admin-side ledger deductions.
                // This page otherwise fetches `current_stock` only once on load and would show stale stock.
                if (!window.pfStaffCustomizationsInventoryPollListenerAttached) {
                    window.pfStaffCustomizationsInventoryPollListenerAttached = true;
                    document.addEventListener('turbo:before-cache', function () {
                        if (window.pfStaffCustomizationsInventoryPoll) {
                            clearInterval(window.pfStaffCustomizationsInventoryPoll);
                            window.pfStaffCustomizationsInventoryPoll = null;
                        }
                    });
                }
                if (window.pfStaffCustomizationsInventoryPoll) {
                    clearInterval(window.pfStaffCustomizationsInventoryPoll);
                }
                window.pfStaffCustomizationsInventoryPoll = setInterval(() => {
                    this.loadAllInventoryItems().catch(() => {});
                }, this.inventoryPollMs);
                
                // Auto-open modal if order_id is in URL
                const params = new URLSearchParams(window.location.search);
                const orderId = params.get('order_id');
                const initialStatus = params.get('status');

                if (initialStatus) {
                    // Map common statuses to tabs
                    const statusMap = {
                        'TO_VERIFY': 'TO_VERIFY',
                        'PENDING_VERIFICATION': 'TO_VERIFY',
                        'DOWNPAYMENT_SUBMITTED': 'TO_VERIFY',
                        'VERIFY_PAY': 'TO_VERIFY',
                        'TO_PAY': 'TO_PAY',
                        'PENDING': 'PENDING',
                        'PENDING_REVIEW': 'PENDING',
                        'APPROVED': 'APPROVED',
                        'PROCESSING': 'IN_PRODUCTION'
                    };
                    const mapped = statusMap[initialStatus.toUpperCase().replace(/\s+/g, '_')] || initialStatus;
                    if (this.statuses.includes(mapped)) {
                        this.activeStatus = mapped;
                    } else if (orderId) {
                        // If we have an order_id but the status doesn't match a tab, default to ALL to ensure it's found
                        this.activeStatus = 'ALL';
                    }
                }

                if (orderId) {
                    const jobType = params.get('job_type') || 'JOB';
                    await this.viewDetails(parseInt(orderId, 10), jobType);
                }
            },

            async loadOrders() {
                try {
                    const [joRes, ordersRes] = await Promise.all([
                        fetch('../admin/job_orders_api.php?action=list_orders&per_page=200').then(r => r.json()),
                        fetch('../admin/job_orders_api.php?action=list_pending_orders').then(r => r.json())
                    ]);

                    const jobOrders = joRes.success ? joRes.data : [];
                    if (!ordersRes.success) {
                        console.warn('list_pending_orders failed:', ordersRes.error || ordersRes);
                    }
                    const regularOrders = ordersRes.success ? ordersRes.data : [];
                    
                    // Merge then sort newest first
                    const combined = [...jobOrders, ...regularOrders];
                    const sorted = combined.sort((a, b) => {
                        const ta = new Date(a.created_at || a.order_date || 0).getTime();
                        const tb = new Date(b.created_at || b.order_date || 0).getTime();
                        return tb - ta;
                    });

                    // Set of order IDs that have at least one job_order
                    const storeIdsWithJob = new Set(
                        jobOrders
                            .filter(j => j.order_id != null && j.order_id !== '')
                            .map(j => String(j.order_id))
                    );

                    // Set of order IDs present in the regular orders list
                    const regularOrderIds = new Set(
                        regularOrders
                            .map(o => String(o.order_id ?? o.id))
                    );

                    this.orders = sorted
                        .filter(row => {
                            // Rule 1: Always keep ORDER rows if they are present.
                            // They serve as the "Bulk" entry for production management.
                            if (row.order_type === 'ORDER') return true;

                            // Rule 2: For JOB rows, check if they belong to a store order.
                            const oid = row.order_id != null && row.order_id !== '' ? String(row.order_id) : null;
                            
                            // If it's a standalone job (no store order), keep it.
                            if (!oid) return true;

                            // If it belongs to a store order, only keep it if the ORDER row is NOT present.
                            // This prevents fragmentation when we want to manage it as a "Bulk" order.
                            if (regularOrderIds.has(oid)) return false;

                            return true;
                        })
                        .map(o => ({
                            ...o,
                            _ts: new Date(o.created_at || o.order_date || 0).getTime()
                        }));
                } catch(err) {
                    console.error('Error loading orders:', err);
                    this.orders = [];
                }
            },

            async loadMachines() {
                const res = await (await fetch('../admin/job_orders_api.php?action=list_machines')).json();
                this.machines = res.success ? res.data : [];
            },

            sameId(a, b) {
                if (a == null || b == null) return false;
                return String(a) === String(b);
            },

            /** job_orders.id for API calls (handles ORDER rows where id is store order_id) */
            effectiveJobId() {
                const j = this.currentJo;
                if (!j) return null;
                if (j.order_type === 'ORDER') {
                    const jid = j.job_order_id;
                    return jid != null && jid !== '' ? Number(jid) : null;
                }
                return j.id != null && j.id !== '' ? Number(j.id) : null;
            },

            /** Resolves job_orders.id from store order_id when job_order_id was missing (API limit / older rows). */
            async resolveEffectiveJobId() {
                let jid = this.effectiveJobId();
                if (jid != null && !Number.isNaN(jid) && jid > 0) return jid;
                const j = this.currentJo;
                if (!j || j.order_type !== 'ORDER') return null;
                const oid = j.order_id ?? j.id;
                if (oid == null || oid === '') return null;
                try {
                    const res = await (await fetch(`../admin/job_orders_api.php?action=resolve_job_for_order&order_id=${encodeURIComponent(oid)}`)).json();
                    if (res.success && res.job_id) {
                        this.currentJo.job_order_id = res.job_id;
                        await this.loadOrders();
                        return Number(res.job_id);
                    }
                } catch (e) {
                    console.error('resolve_job_for_order', e);
                }
                return null;
            },

            findOrder(id, orderType = 'JOB') {
                return this.orders.find(o => this.sameId(o.id, id) && (o.order_type || 'JOB') === (orderType || 'JOB'));
            },

            getCorrectServiceType(jo) {
                const combined = ((jo.job_title || '') + ' ' + (jo.service_type || '')).toUpperCase();
                if (combined.includes('T-SHIRT') || combined.includes('TSHIRT') || combined.includes('T SHIRT')) return 'T-SHIRT PRINTING';
                if (combined.includes('TARPAULIN')) return 'TARPAULIN PRINTING';
                if (combined.includes('TRANSPARENT STICKER') || combined.includes('TRANSPARENT')) return 'TRANSPARENT STICKER PRINTING';
                if (combined.includes('STICKER') || combined.includes('DECAL')) return 'DECALS/STICKERS (PRINT/CUT)';
                if (combined.includes('SINTRA')) return 'SINTRA BOARD';
                if (combined.includes('SOUVENIR')) return 'SOUVENIRS';
                if (combined.includes('REFLECTORIZED')) return 'REFLECTORIZED SIGNAGE';
                return 'OTHER';
            },

            get filteredOrders() {
                const filtered = this.orders.filter(jo => {
                    // Status Filter
                    let matchStatus = false;
                    if (this.activeStatus === 'ALL') {
                        matchStatus = true;
                    } else if (this.activeStatus === 'APPROVED') {
                        matchStatus = jo.status === 'APPROVED';
                    } else if (this.activeStatus === 'TO_VERIFY') {
                        matchStatus = this.isVerifyStageRow(jo);
                    } else if (this.activeStatus === 'TO_PAY') {
                        matchStatus = this.isToPayRow(jo);
                    } else if (this.activeStatus === 'IN_PRODUCTION') {
                        matchStatus = this.isInProductionRow(jo);
                    } else if (this.activeStatus === 'TO_RECEIVE') {
                        matchStatus = jo.status === 'TO_RECEIVE' || jo.status === 'READY_TO_COLLECT';
                    } else {
                        matchStatus = jo.status === this.activeStatus;
                    }
                    if (!matchStatus) return false;

                    // Service Filter
                    if (this.serviceFilter !== 'ALL') {
                        const rowService = this.getCorrectServiceType(jo);
                        if (rowService !== this.serviceFilter) return false;
                    }

                    // Date Filter
                    if (this.dateFilter !== 'ALL') {
                        const orderDate = new Date(jo.created_at || jo.order_date);
                        const now = new Date();
                        
                        if (this.dateFilter === 'TODAY') {
                            if (orderDate.toDateString() !== now.toDateString()) return false;
                        } else if (this.dateFilter === 'WEEK') {
                            const lastWeek = new Date();
                            lastWeek.setDate(now.getDate() - 7);
                            if (orderDate < lastWeek) return false;
                        } else if (this.dateFilter === 'MONTH') {
                            if (orderDate.getMonth() !== now.getMonth() || orderDate.getFullYear() !== now.getFullYear()) return false;
                        } else if (this.dateFilter === 'CUSTOM') {
                            if (this.customDateFrom) {
                                const from = new Date(this.customDateFrom);
                                from.setHours(0,0,0,0);
                                if (orderDate < from) return false;
                            }
                            if (this.customDateTo) {
                                const to = new Date(this.customDateTo);
                                to.setHours(23,59,59,999);
                                if (orderDate > to) return false;
                            }
                        }
                    }
                    
                    // Search Bar
                    const searchLower = this.search.toLowerCase();
                    const matchSearch = !this.search || 
                        (jo.job_title && jo.job_title.toLowerCase().includes(searchLower)) ||
                        (jo.service_type && jo.service_type.toLowerCase().includes(searchLower)) ||
                        (((jo.first_name || '') + ' ' + (jo.last_name || '')).toLowerCase().includes(searchLower)) ||
                        (jo.id && jo.id.toString().includes(searchLower));
                    
                    return matchSearch;
                });

                // Sorting
                return filtered.sort((a, b) => {
                    if (this.sortOrder === 'oldest') {
                        return (a._ts || 0) - (b._ts || 0);
                    } else if (this.sortOrder === 'az') {
                        const nameA = ((a.first_name || '') + ' ' + (a.last_name || '')).toLowerCase();
                        const nameB = ((b.first_name || '') + ' ' + (b.last_name || '')).toLowerCase();
                        return nameA.localeCompare(nameB);
                    } else if (this.sortOrder === 'za') {
                        const nameA = ((a.first_name || '') + ' ' + (a.last_name || '')).toLowerCase();
                        const nameB = ((b.first_name || '') + ' ' + (b.last_name || '')).toLowerCase();
                        return nameB.localeCompare(nameA);
                    }
                    return (b._ts || 0) - (a._ts || 0); // newest (default)
                });
            },

            get paginatedOrders() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                return this.filteredOrders.slice(start, end);
            },

            get totalPages() {
                return Math.ceil(this.filteredOrders.length / this.itemsPerPage);
            },

            get pageNumbers() {
                const total = this.totalPages;
                if (total <= 1) return [];
                let pages = [1];
                const window = 2;
                for (let i = Math.max(2, this.currentPage - window); i <= Math.min(total - 1, this.currentPage + window); i++) {
                    pages.push(i);
                }
                if (total > 1 && !pages.includes(total)) pages.push(total);
                
                let uniquePages = [...new Set(pages)].sort((a,b) => a - b);
                let finalPages = [];
                let prev = null;
                for (let p of uniquePages) {
                    if (prev && p - prev > 1) finalPages.push('...');
                    finalPages.push(p);
                    prev = p;
                }
                return finalPages;
            },

            getStatusCount(status) {
                if (status === 'ALL') {
                    // Count each order exactly once based on which tab it belongs to
                    return this.orders.filter(o => {
                        const s = String(o.status || '').toUpperCase().replace(/\s+/g, '_');
                        return ['PENDING','APPROVED','TO_PAY','VERIFY_PAY','TO_VERIFY','PENDING_VERIFICATION',
                                'DOWNPAYMENT_SUBMITTED','IN_PRODUCTION','PROCESSING','PRINTING',
                                'TO_RECEIVE','COMPLETED','CANCELLED'].includes(s) ||
                               this.isInProductionRow(o);
                    }).length;
                }
                if (status === 'TO_VERIFY') {
                    return this.orders.filter(o => this.isVerifyStageRow(o)).length;
                }
                if (status === 'TO_PAY') {
                    return this.orders.filter(o => this.isToPayRow(o)).length;
                }
                if (status === 'IN_PRODUCTION') {
                    return this.orders.filter(o => this.isInProductionRow(o)).length;
                }
                if (status === 'TO_RECEIVE') {
                    return this.orders.filter(o => o.status === 'TO_RECEIVE' || o.status === 'READY_TO_COLLECT').length;
                }
                return this.orders.filter(o => o.status === status).length;
            },

            async viewDetails(id, orderType = 'JOB') {
                let order = this.findOrder(id, orderType);
                if (orderType === 'SERVICE' || order?.order_type === 'SERVICE') {
                    await this.openSvcModal(id);
                    return;
                }

                this.showDetailsModal = true;
                this.loadingDetails = true;
                this.currentJo = {};
                const base = document.body.getAttribute('data-base-url') || '/printflow';
                
                if (orderType === 'CUSTOMIZATION') {
                    // Fetch customization entry details
                    try {
                        const detailRes = await (await fetch(`${base}/admin/job_orders_api.php?action=get_customization&id=${id}`)).json();
                        if (detailRes.success) {
                            this.currentJo = { ...detailRes.data, order_type: 'CUSTOMIZATION' };
                            this.jobPriceInput = this.currentJo.estimated_total || this.currentJo.estimated_price || 0;
                        } else {
                            this.showStaffAlert('Error', 'Customization details could not be loaded.');
                            this.showDetailsModal = false;
                        }
                    } catch (e) {
                        console.error('Error fetching customization detail:', e);
                        this.showDetailsModal = false;
                    }
                    this.loadingDetails = false;
                    return;
                }
                
                if (orderType === 'ORDER') {
                    // Always fetch full order details to get `items` array and dynamic fields
                    try {
                        const detailRes = await (await fetch(`${base}/admin/job_orders_api.php?action=get_regular_order&id=${id}`)).json();
                        if (detailRes.success) {
                            order = detailRes.data;
                        }
                    } catch (e) { console.error('Error fetching order detail:', e); }
                    
                    if (!order || !order.items) {
                        this.loadingDetails = false;
                        this.showDetailsModal = false;
                        this.showStaffAlert('Not Found', 'Order not found or not accessible.');
                        return;
                    }
                    this.currentJo = { ...order, order_type: 'ORDER' };
                    this.jobPriceInput = this.currentJo.estimated_total || this.currentJo.estimated_price || this.currentJo.total_amount || 0;
                    if (!this.currentJo.job_order_id) {
                        await this.resolveEffectiveJobId();
                    }
                    this.loadingDetails = false;
                } else {
                    // JOB ORDER
                    const jid = id || (order ? order.id : null);
                    if (!jid) {
                        this.loadingDetails = false;
                        this.showDetailsModal = false;
                        return;
                    }

                    try {
                        const res = await (await fetch(`${base}/admin/job_orders_api.php?action=get_order&id=${jid}`)).json();
                        if (res.success) {
                            this.currentJo = { ...res.data, order_type: 'JOB' };
                            this.jobPriceInput = this.currentJo.estimated_total || this.currentJo.estimated_price || 0;
                            this.resetMaterialForm();
                            this.resetInkForm();
                            for (const m of this.currentJo.materials || []) {
                                if (m.track_by_roll == 1) this.loadAvailableRolls(m.item_id);
                            }
                        } else {
                            // Fallback: It might be a regular order ID passed with job_type=JOB
                            const fallbackRes = await (await fetch(`${base}/admin/job_orders_api.php?action=get_regular_order&id=${jid}`)).json();
                            if (fallbackRes.success) {
                                this.currentJo = { ...fallbackRes.data, order_type: 'ORDER' };
                                this.jobPriceInput = this.currentJo.estimated_total || this.currentJo.estimated_price || this.currentJo.total_amount || 0;
                                if (!this.currentJo.job_order_id) {
                                    await this.resolveEffectiveJobId();
                                }
                            } else {
                                this.showStaffAlert('Error', 'Order details could not be loaded.');
                                this.showDetailsModal = false;
                            }
                        }
                    } catch (e) {
                        console.error('Error loading job details', e);
                        this.showDetailsModal = false;
                    }
                    this.loadingDetails = false;
                }
            },

            isOverdue(date) {
                if(!date) return false;
                return new Date(date) < new Date() && this.activeStatus !== 'COMPLETED' && this.activeStatus !== 'CANCELLED';
            },

            async loadAvailableRolls(itemId) {
                if(this.availableRolls[itemId]) {
                    this.availableRollsList = this.availableRolls[itemId];
                    return;
                }
                const res = await (await fetch(`../admin/inventory_rolls_api.php?action=list_rolls&item_id=${itemId}`)).json();
                if(res.success) {
                    this.availableRolls[itemId] = res.data;
                    this.availableRollsList = res.data;
                }
            },

            isRollTracked(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.track_by_roll == 1;
            },

            async assignRoll(jomId, rollId) {
                const fd = new FormData();
                fd.append('action', 'assign_roll');
                fd.append('jom_id', jomId);
                fd.append('roll_id', rollId);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    await this.refreshMaterials();
                } else {
                    this.showStaffAlert('Error', res.error);
                }
            },

            async jobAction(status, machineId = null) {
                if (this.currentJo.order_type === 'CUSTOMIZATION') {
                    const fd = new FormData();
                    fd.append('action', 'update_customization');
                    fd.append('id', this.currentJo.id);
                    fd.append('status', status === 'APPROVED' ? 'Approved' : status);
                    const base = document.body.getAttribute('data-base-url') || '/printflow';
                    const res = await (await fetch(base + '/admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                    if (res.success) { await this.loadOrders(); this.showDetailsModal = false; }
                    else this.showStaffAlert('Error', res.error || 'Update failed.');
                    return;
                }
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    this.showStaffAlert('Production Job Error', 'Could not create or find a production job for this store order. Confirm the order has line items in Orders.');
                    return;
                }
                const ok = await this.updateStatus(jid, status, machineId);
                if (ok) this.showDetailsModal = false;
            },

            async updateStatus(id, status, machineId = null, reason = '') {
                if (id == null || id === '' || Number(id) <= 0) {
                    this.showStaffAlert('Error', 'Invalid job order id.');
                    return false;
                }

                const base = document.body.getAttribute('data-base-url') || '/printflow';
                const fd = new FormData();
                
                if (this.currentJo.order_type === 'CUSTOMIZATION') {
                    fd.append('action', 'update_customization');
                    fd.append('id', id);
                    fd.append('status', status);
                } else {
                    fd.append('action', 'update_status');
                    fd.append('id', id);
                    fd.append('status', status);
                    if(machineId) fd.append('machine_id', machineId);
                    if(reason) fd.append('reason', reason);
                }
                
                const res = await (await fetch(base + '/admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    if (this.showDetailsModal && (this.sameId(this.effectiveJobId(), id) || this.sameId(this.currentJo.id, id))) {
                        await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                    }
                    return true;
                }
                this.showStaffAlert('Update Failed', res.error);
                return false;
            },

            async parseJsonResponse(r) {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Non-JSON response', text.slice(0, 500));
                    return { success: false, error: 'Server returned an invalid response. Check console or PHP error log.' };
                }
            },

            async verifyPayment() {
                this.showStaffConfirm(
                    'Verify Payment & Start Production',
                    `Verify payment of ₱${this.currentJo.payment_submitted_amount}?\n\nThis will deduct materials from inventory and start production.`,
                    async () => {
                        const base = document.body.getAttribute('data-base-url') || '/printflow';
                        const ot = this.currentJo.order_type || 'JOB';
                        let res;

                        if (ot === 'ORDER') {
                            const oid = this.currentJo.order_id || this.currentJo.id;
                            const fd = new FormData();
                            fd.append('order_id', oid);
                            fd.append('action', 'Approve');
                            const r = await fetch(base + '/staff/api_verify_payment.php', { method: 'POST', body: fd });
                            res = await this.parseJsonResponse(r);
                        } else {
                            const jid = await this.resolveEffectiveJobId();
                            if (!jid) {
                                this.showStaffAlert('Error', 'No linked production job for payment verification.');
                                return;
                            }
                            const fd = new FormData();
                            fd.append('action', 'verify_payment');
                            fd.append('id', jid);
                            const r = await fetch(base + '/admin/api_verify_job_payment.php', { method: 'POST', body: fd });
                            res = await this.parseJsonResponse(r);
                        }

                        if(res.success) {
                            this.activeStatus = 'IN_PRODUCTION';
                            await this.loadOrders();
                            await this.loadAllInventoryItems();
                            await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                            this.showStaffAlert('Success', 'Payment verified. Materials deducted and production started.');
                        } else {
                            this.showStaffAlert('Verification Failed', res.error || 'Verification failed.');
                        }
                    }
                );
            },

            openRejectPaymentModal() {
                this.rejectPaymentReasonSelect = '';
                this.rejectPaymentReasonText = '';
                this.showRejectPaymentModal = true;
            },

            closeRejectPaymentModal() {
                this.showRejectPaymentModal = false;
            },

            async submitRejectPayment() {
                const finalReason = this.rejectPaymentReasonSelect === 'Others' ? this.rejectPaymentReasonText : this.rejectPaymentReasonSelect;
                if (!finalReason) {
                    this.showStaffAlert('Input Required', 'Please select or specify a reason.');
                    return;
                }
                await this.rejectPayment(finalReason);
                this.closeRejectPaymentModal();
            },

            async rejectPayment(reasonOverride = null) {
                let reason = reasonOverride;
                if (!reason) {
                    reason = prompt("Enter reason for rejection (e.g., Unclear image, Incorrect amount):");
                }
                if(!reason) return;

                const base = document.body.getAttribute('data-base-url') || '/printflow';
                const ot = this.currentJo.order_type || 'JOB';
                let res;

                if (ot === 'ORDER') {
                    const oid = this.currentJo.order_id || this.currentJo.id;
                    const fd = new FormData();
                    fd.append('order_id', oid);
                    fd.append('action', 'Reject');
                    fd.append('reason', reason);
                    const r = await fetch(base + '/staff/api_verify_payment.php', { method: 'POST', body: fd });
                    res = await this.parseJsonResponse(r);
                } else {
                    const jid = await this.resolveEffectiveJobId();
                    if (!jid) {
                        this.showStaffAlert('Error', 'No linked production job.');
                        return;
                    }
                    const fd = new FormData();
                    fd.append('action', 'reject_payment');
                    fd.append('id', jid);
                    fd.append('reason', reason);
                    const r = await fetch(base + '/admin/api_verify_job_payment.php', { method: 'POST', body: fd });
                    res = await this.parseJsonResponse(r);
                }

                if(res.success) {
                    await this.loadOrders();
                    await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                    this.showStaffAlert('Success', 'Payment proof rejected.');
                } else {
                    this.showStaffAlert('Rejection Failed', res.error || 'Rejection failed.');
                }
            },

            async setJobPrice(id) {
                if(this.jobPriceInput < 0) return;
                let jid = id != null ? id : await this.resolveEffectiveJobId();
                if (!jid) {
                    this.showStaffAlert('Error', 'No linked production job.');
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'set_price');
                fd.append('id', jid);
                fd.append('price', this.jobPriceInput);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(!res.success) {
                    this.showStaffAlert('Error', res.error);
                    throw new Error(res.error);
                }
            },

            addMaterialToQueue() {
                if (!this.newMaterialId) return;
                const item = this.allInventoryItems.find(i => i.id == this.newMaterialId);
                if (!item) return;

                // Check if already in pending queue
                const existing = this.pendingMaterials.find(m => m.item_id == this.newMaterialId);
                if (existing) {
                    existing.qty += this.newMaterialQty || 1;
                    // Reset input
                    this.newMaterialId = '';
                    this.newMaterialQty = 1;
                    this.newMaterialHeight = 0;
                    return;
                }

                let meta = {};
                if (this.isTarpaulin(this.newMaterialId)) {
                    meta.height_ft = this.newMaterialHeight;
                    meta.finishing = this.newMaterialMetadata.finishing || '';
                } else if (this.isSticker(this.newMaterialId)) {
                    meta.lamination = this.newMaterialMetadata.lamination || '';
                }
                this.pendingMaterials.push({
                    item_id: this.newMaterialId,
                    name: item.name,
                    qty: this.newMaterialQty || 1,
                    uom: this.isSticker(this.newMaterialId) ? 'pcs' : (item.unit_of_measure || 'pcs'),
                    roll_id: this.newMaterialRollId || '',
                    notes: this.newMaterialNotes,
                    metadata: meta
                });
                // Reset form
                this.newMaterialId = '';
                this.newMaterialQty = 1;
                this.newMaterialHeight = 0;
                this.newMaterialRollId = '';
                this.newMaterialNotes = '';
                this.newMaterialMetadata = {};
            },

            async submitToPay() {
                console.log('submitToPay called');
                console.log('jobPriceInput value:', this.jobPriceInput);
                console.log('jobPriceInput type:', typeof this.jobPriceInput);
                
                if (this.currentJo.order_type === 'CUSTOMIZATION') {
                    const priceValue = parseFloat(this.jobPriceInput);
                    console.log('Parsed price value:', priceValue);
                    console.log('Is NaN?', isNaN(priceValue));
                    
                    if (!priceValue || priceValue <= 0 || isNaN(priceValue)) {
                        this.showStaffAlert('Price Required', 'Please enter a valid price before approving.');
                        return;
                    }
                    const fd = new FormData();
                    fd.append('action', 'update_customization');
                    fd.append('id', this.currentJo.id);
                    fd.append('status', 'TO_PAY');
                    fd.append('price', this.jobPriceInput);
                    const base = document.body.getAttribute('data-base-url') || '/printflow';
                    const res = await (await fetch(base + '/admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                    if (res.success) {
                        const hasPaymentProof = this.currentJo.payment_proof_path || this.currentJo.payment_proof;
                        const paymentAmount = parseFloat(this.currentJo.payment_submitted_amount || 0);
                        const targetTab = (hasPaymentProof && paymentAmount > 0) ? 'TO_VERIFY' : 'TO_PAY';
                        const successMessage = targetTab === 'TO_VERIFY'
                            ? 'Price set! Payment proof detected — order moved to verification.'
                            : 'Price set and order moved to payment stage.';

                        this.showStaffAlert('Success', successMessage, async () => {
                            const details = this.currentJo.customization_details || {};
                            const urlParams = new URLSearchParams(window.location.search);
                            const returnToPOS = urlParams.get('return_to_pos') === '1';

                            if (details.source === 'POS' || returnToPOS) {
                                // Update the matching cart item price by product_id, then redirect
                                const savedState = sessionStorage.getItem('pos_cart_state');
                                if (savedState) {
                                    try {
                                        const state = JSON.parse(savedState);
                                        await fetch(base + '/staff/api/pos_cart_handler.php', {
                                            method: 'POST',
                                            headers: {'Content-Type': 'application/json'},
                                            body: JSON.stringify({
                                                action: 'update_price',
                                                index: state.item_index,
                                                price: priceValue
                                            })
                                        });
                                    } catch (e) {
                                        console.error('Error updating cart price:', e);
                                    }
                                    sessionStorage.removeItem('pos_cart_state');
                                }
                                window.location.href = base + '/staff/pos.php?from_customizations=1';
                            } else {
                                this.activeStatus = targetTab;
                                await this.loadOrders();
                                this.showDetailsModal = false;
                            }
                        });
                    } else {
                        this.showStaffAlert('Error', res.error || 'Failed.');
                    }
                    return;
                }
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    this.showStaffAlert('Error', 'No linked production job for materials and pricing.');
                    return;
                }
                
                // CRITICAL: Capture the price BEFORE any async operations that might reset it
                const userEnteredPrice = parseFloat(this.jobPriceInput);
                console.log('User entered price (captured early):', userEnteredPrice);
                
                // Check if this order came from POS BEFORE any operations
                const urlParams = new URLSearchParams(window.location.search);
                const returnToPOS = urlParams.get('return_to_pos') === '1';
                const fromPOS = returnToPOS || (this.currentJo.order_type === 'ORDER' && this.currentJo.order_source === 'pos') || (this.currentJo.order_type === 'CUSTOMIZATION' && this.currentJo.order_source === 'pos');
                
                // Save all pending materials from the queue
                for (const pm of this.pendingMaterials) {
                    const fd = new FormData();
                    fd.append('action', 'add_material');
                    fd.append('order_id', jid);
                    fd.append('item_id', pm.item_id);
                    fd.append('quantity', pm.qty);
                    fd.append('uom', pm.uom);
                    fd.append('roll_id', pm.roll_id);
                    fd.append('notes', pm.notes);
                    fd.append('metadata', JSON.stringify(pm.metadata));
                    const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                    if (!res.success) { this.showStaffAlert('Material Error', 'Failed to save material: ' + res.error); return; }
                }
                this.pendingMaterials = [];

                // Also save the current form if something is still selected
                if (this.newMaterialId) {
                    await this.addMaterial();
                }

                // Handle Ink Usage Check and Submission
                if (this.useInk && this.inkCategorySelected) {
                    const mappedInks = this.inkTypes[this.inkCategorySelected];
                    const inkPayload = [];
                    
                    if (this.inkBlue > 0) inkPayload.push({ item_id: mappedInks['BLUE'], color: 'BLUE', quantity: this.inkBlue });
                    if (this.inkRed > 0) inkPayload.push({ item_id: mappedInks['RED'], color: 'RED', quantity: this.inkRed });
                    if (this.inkBlack > 0) inkPayload.push({ item_id: mappedInks['BLACK'], color: 'BLACK', quantity: this.inkBlack });
                    if (this.inkYellow > 0) inkPayload.push({ item_id: mappedInks['YELLOW'], color: 'YELLOW', quantity: this.inkYellow });

                    if (inkPayload.length > 0) {
                        const fdInk = new FormData();
                        fdInk.append('action', 'save_ink_usage');
                        fdInk.append('order_id', jid);
                        fdInk.append('ink_data', JSON.stringify(inkPayload));

                        const resInk = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fdInk })).json();
                        if (!resInk.success) {
                            this.showStaffAlert('Ink Error', 'Failed to save ink usage: ' + resInk.error);
                            return;
                        }
                    }
                }

                // Re-fetch to get latest materials
                await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');

                // IMPORTANT: viewDetails resets jobPriceInput, so use the captured value
                console.log('Price before materials check:', userEnteredPrice);

                if ((!this.currentJo.materials || this.currentJo.materials.length === 0) && (!this.currentJo.ink_usage || this.currentJo.ink_usage.length === 0)) {
                    this.showStaffAlert('Production Required', 'Please add at least one production material or ink before submitting to pay.');
                    return;
                }

                // Validate price is set - use the captured value from the beginning
                if (!userEnteredPrice || userEnteredPrice <= 0 || isNaN(userEnteredPrice)) {
                    this.showStaffAlert('Price Required', 'Please enter a valid price before submitting to pay.');
                    return;
                }
                
                // Restore the price value that was reset by viewDetails
                this.jobPriceInput = userEnteredPrice;

                // Update price for both job_orders AND orders table
                const priceUpdated = await this.updatePrice();
                if (!priceUpdated) {
                    this.showStaffAlert('Error', 'Failed to update price. Please try again.');
                    return;
                }
                
                await this.updateStatus(jid, 'TO_PAY');
                // Refresh the modal to show updated price
                await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                
                // Close the modal after successful submission
                this.showDetailsModal = false;
                
                // Check if we need to redirect back to POS
                if (fromPOS) {
                    const base = document.body.getAttribute('data-base-url') || '/printflow';
                    const savedState = sessionStorage.getItem('pos_cart_state');
                    
                    if (savedState) {
                        try {
                            const state = JSON.parse(savedState);
                            const itemIndex = state.item_index;
                            
                            // Update cart via API
                            await fetch(base + '/staff/api/pos_cart_handler.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({
                                    action: 'update_price',
                                    index: itemIndex,
                                    price: userEnteredPrice
                                })
                            });
                            
                            // Redirect back to POS
                            window.location.href = base + '/staff/pos.php?from_customizations=1';
                            return; // Exit early to prevent showing alert
                        } catch (e) {
                            console.error('Error updating cart:', e);
                            window.location.href = base + '/staff/pos.php';
                            return;
                        }
                    } else {
                        // No saved state, just redirect
                        window.location.href = base + '/staff/pos.php';
                        return;
                    }
                }
                
                // Show success message (only if not redirecting to POS)
                this.showStaffAlert('Success', 'Order approved and moved to payment stage!');
            },

            async loadAllInventoryItems() {
                const res = await (await fetch('../admin/inventory_items_api.php?action=get_items&active_only=1')).json();
                if(res.success) {
                    // Drop roll cache so any newly issued/received roll deductions become visible.
                    this.availableRolls = {};
                    this.allInventoryItems = res.data;
                    this.laminationItemsList = this.allInventoryItems.filter(i => i.name.toUpperCase().includes('LAMINATE'));
                }
            },

            async loadAvailableLamRolls(itemId) {
                if(!itemId) return;
                const res = await (await fetch(`../admin/inventory_rolls_api.php?action=list&item_id=${itemId}&status=OPEN`)).json();
                if(res.success) {
                    this.availableLamRollsList = res.data;
                }
            },

            async approveOrder() {
                this.showStaffConfirm(
                    'Approve Order',
                    'Approve this order and request payment?',
                    async () => {
                        const id = this.currentJo.id;
                        const jid = this.effectiveJobId();
                        const oid = this.currentJo.order_id || this.currentJo.id;
                        
                        // Set price first
                        if (parseFloat(this.jobPriceInput) > 0) {
                            await this.updatePrice();
                        }

                        if (this.currentJo.order_type === 'ORDER') {
                            const fd = new FormData();
                            fd.append('order_id', oid);
                            fd.append('status', 'To Pay');
                            fd.append('update_status', '1');
                            fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
                            
                            const res = await (await fetch('orders.php', { 
                                method: 'POST', 
                                body: fd, 
                                headers: {'X-Requested-With': 'XMLHttpRequest'} 
                            })).json();

                            if (res.success) {
                                this.showStaffAlert('Success', 'Order approved and moved to To Pay!');
                                await this.loadOrders();
                                this.showDetailsModal = false;
                            } else {
                                this.showStaffAlert('Error', 'Error: ' + (res.error || 'Failed to update order status'));
                            }
                        } else {
                            // Custom Job Order
                            await this.updateStatus(id, 'TO_PAY');
                        }
                    }
                );
            },

            async updatePrice() {
                const jid = this.effectiveJobId();
                const oid = this.currentJo.order_id || this.currentJo.id;
                const price = parseFloat(this.jobPriceInput);
                
                if (!price || price <= 0) {
                    this.showStaffAlert('Invalid Price', 'Please enter a valid price greater than 0.');
                    return false;
                }
                
                if (this.currentJo.order_type === 'ORDER') {
                   const fd = new FormData();
                   fd.append('action', 'update_order_price');
                   fd.append('order_id', oid);
                   fd.append('price', price);
                   const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                   if (!res.success) {
                       this.showStaffAlert('Error', 'Failed to update price: ' + res.error);
                       return false;
                   }
                   this.currentJo.total_amount = price;
                   this.currentJo.estimated_total = price;
                   console.log('Price updated successfully to:', price);
                   return true;
                } else if (this.currentJo.order_type === 'CUSTOMIZATION') {
                   const fd = new FormData();
                   fd.append('action', 'update_customization');
                   fd.append('id', oid);
                   fd.append('status', 'APPROVED');
                   fd.append('price', price);
                   const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                   if (!res.success) {
                       this.showStaffAlert('Error', 'Failed to update customization price: ' + res.error);
                       return false;
                   }
                   this.currentJo.estimated_total = price;
                   console.log('Customization price updated successfully to:', price);
                   return true;
                } else {
                    const success = await this.setJobPrice(jid);
                    if (success !== false) {
                        this.currentJo.estimated_total = price;
                        console.log('Job price updated successfully to:', price);
                        return true;
                    }
                    return false;
                }
            },

            async setJobPrice(jid) {
                if (!jid) return false;
                const price = parseFloat(this.jobPriceInput);
                if (!price || price <= 0) return false;
                
                const fd = new FormData();
                fd.append('action', 'set_price');
                fd.append('id', jid);
                fd.append('price', price);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if (res.success) {
                    this.currentJo.estimated_total = price;
                    console.log('Job price set to:', price);
                    return true;
                } else {
                    this.showStaffAlert('Error', 'Error setting price: ' + res.error);
                    return false;
                }
            },

            openRevisionModal() {
                this.revisionReasonSelect = '';
                this.revisionReasonText = '';
                this.showRevisionModal = true;
            },

            closeRevisionModal() {
                this.showRevisionModal = false;
            },

            async submitRevision() {
                const oid = this.effectiveJobId();
                if (!oid) return;
                
                let finalReason = this.revisionReasonSelect;
                if (finalReason === 'Others' || !finalReason) {
                    finalReason = this.revisionReasonText.trim();
                }
                
                if (!finalReason) {
                    this.showStaffAlert('Input Required', 'Please select or specify a reason for the revision request.');
                    return;
                }

                this.showStaffConfirm(
                    'Request Revision',
                    `Submit revision request?\nReason: ${finalReason}`,
                    async () => {
                        const ok = await this.updateStatus(oid, 'For Revision', null, finalReason);
                        if (ok) {
                            this.showStaffAlert('Success', 'Revision requested successfully.');
                            this.showRevisionModal = false;
                            this.showDetailsModal = false; 
                        }
                    }
                );
            },

            async addMaterial() {
                if(!this.newMaterialId || !this.newMaterialQty) return;
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    this.showStaffAlert('Error', 'No linked production job.');
                    return;
                }
                const item = this.allInventoryItems.find(i => i.id == this.newMaterialId);
                const fd = new FormData();
                fd.append('action', 'add_material');
                fd.append('order_id', jid);
                fd.append('item_id', this.newMaterialId);
                fd.append('quantity', this.newMaterialQty);
                fd.append('uom', this.isSticker(this.newMaterialId) ? 'pcs' : (item.unit_of_measure || 'pcs'));
                fd.append('roll_id', this.newMaterialRollId);
                fd.append('notes', this.newMaterialNotes);
                
                // Construct metadata based on category
                let meta = {};
                if (this.isTarpaulin(this.newMaterialId)) {
                    meta.height_ft = this.newMaterialHeight;
                    meta.finishing = this.newMaterialMetadata.finishing || '';
                } else if (this.isSticker(this.newMaterialId)) {
                    // STICKER LOGIC
                    let orderedHeight = this.currentJo.height_ft > 0 ? this.currentJo.height_ft : 1;
                    meta.waste_length_ft = Math.max(0, this.newMaterialQty - orderedHeight);
                    if (this.newMaterialMetadata.lamination) {
                        meta.lamination_item_id = this.newMaterialMetadata.lamination;
                        meta.lamination_roll_id = this.newMaterialMetadata.lamination_roll_id || null;
                        meta.lamination_length_ft = this.newMaterialQty; // Lamination length matches consumed vinyl length
                    }
                }
                fd.append('metadata', JSON.stringify(meta));

                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    this.resetMaterialForm();
                    await this.refreshMaterials();
                } else {
                    this.showStaffAlert('Error', res.error);
                }
            },

            resetMaterialForm() {
                this.newMaterialId = '';
                this.newMaterialQty = 1;
                this.newMaterialHeight = 0;
                this.newMaterialRollId = '';
                this.newMaterialNotes = '';
                this.materialSearch = '';
                this.availableLamRollsList = [];
                this.newMaterialMetadata = {
                    lamination: '',
                    lamination_roll_id: ''
                };
            },

            resetInkForm() {
                this.inkCategorySelected = '';
                this.inkBlue = '';
                this.inkRed = '';
                this.inkBlack = '';
                this.inkYellow = '';
                this.useInk = false;
            },

            isTarpaulin(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 2; // confirmed from schema check
            },

            isSticker(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 3;
            },

            isPlate(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 1;
            },

            async removeMaterial(jomId) {
                this.showStaffConfirm(
                    'Remove Material',
                    'Remove this material?',
                    async () => {
                        const fd = new FormData();
                        fd.append('action', 'remove_material');
                        fd.append('id', jomId);
                        const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                        if(res.success) {
                            await this.refreshMaterials();
                        } else {
                            this.showStaffAlert('Error', res.error);
                        }
                    }
                );
            },

            async refreshMaterials() {
                const jid = await this.resolveEffectiveJobId();
                if (!jid) return;
                const res = await (await fetch(`../admin/job_orders_api.php?action=get_order&id=${jid}`)).json();
                if(res.success) {
                    this.currentJo = { ...res.data, order_type: 'JOB' };
                    for(const m of (this.currentJo.materials || [])) {
                        if(m.track_by_roll == 1) this.loadAvailableRolls(m.item_id);
                    }
                }
            },

            async markReadyForPickup() {
                this.showStaffConfirm(
                    'Mark Ready for Pickup',
                    'Mark this order as ready for customer pickup?',
                    async () => {
                        await this.jobAction('TO_RECEIVE');
                    }
                );
            },

            async completeOrder(machineId = null) {
                this.showStaffConfirm(
                    'Complete Order',
                    'Mark this order as completed and fulfilled?',
                    async () => {
                        const jid = await this.resolveEffectiveJobId();
                        if (!jid) {
                            this.showStaffAlert('Error', 'No linked production job for this entry.');
                            return;
                        }
                        const ok = await this.updateStatus(jid, 'COMPLETED', machineId);
                        if (ok) {
                            this.showDetailsModal = false;
                        }
                    }
                );
            }
        };
        });
    });
    /*
     * Do NOT call Alpine.initTree here when document.readyState !== 'loading' (Turbo body swap).
     * Inline scripts run before turbo:load's setTimeout; initTree(root) + initTree(.main-content) double-mounts x-for (tripled tabs, zero counts).
     * Full load: Alpine.start() (defer) inits the page. Turbo: public/assets/js/turbo-init.js initTree(.main-content) runs after swap.
     */
</script>
</body>
</html>
