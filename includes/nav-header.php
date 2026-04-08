<?php
/**
 * Shared nav header markup. Set $nav_header_class before including.
 * Used by header.php (non-landing) and index.php (landing, inside hero).
 */
$nav_header_class = $nav_header_class ?? 'bg-[#0a2530] backdrop-blur-md shadow-lg sticky top-0 z-50 border-b border-white/5';
require_once __DIR__ . '/shop_config.php';
$show_header_search = stripos((string)$nav_header_class, 'lp-hero-nav') === false;
$search_query = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));
$search_action = $url_products;
$search_placeholder = 'Search products...';
$search_param = 'search';
if ($is_logged_in) {
    if (is_customer()) {
        $search_action = $base_url . '/customer/products.php';
        $search_placeholder = 'Search products...';
    } elseif (is_admin()) {
        $search_action = $base_url . '/admin/products_management.php';
        $search_placeholder = 'Search products or SKU...';
    } elseif (is_staff()) {
        $search_action = $base_url . '/staff/products.php';
        $search_placeholder = 'Search products...';
    } elseif (function_exists('is_manager') && is_manager()) {
        $search_action = $base_url . '/manager/dashboard.php';
        $search_placeholder = 'Search...';
        $search_param = 'q';
    }
}

$first = trim((string)($current_user['first_name'] ?? ''));
$last = trim((string)($current_user['last_name'] ?? ''));
$initials = '';
if ($first !== '') {
    $initials .= mb_strtoupper(mb_substr($first, 0, 1));
}
if ($last !== '') {
    $initials .= mb_strtoupper(mb_substr($last, 0, 1));
}
if ($initials === '') {
    $emailInitial = trim((string)($current_user['email'] ?? 'U'));
    $initials = mb_strtoupper(mb_substr($emailInitial, 0, 1));
}
?>
<header class="<?php echo htmlspecialchars($nav_header_class); ?>" id="main-header">
    <style>
        #main-header .pf-header-shell { display: flex; align-items: center; gap: 1rem; }
        #main-header .pf-header-left { flex: 0 0 auto; min-width: 0; }
        #main-header .pf-header-mid { flex: 1 1 auto; min-width: 0; display: none; align-items: center; justify-content: center; gap: 1.25rem; }
        #main-header .pf-nav-links { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        #main-header .pf-search-wrap { width: min(460px, 100%); }
        #main-header .pf-search-form { display: flex; align-items: center; gap: .55rem; height: 2.6rem; border-radius: 9999px; border: 1px solid rgba(83,197,224,.28); background: rgba(255,255,255,.06); padding: 0 .9rem; box-shadow: inset 0 1px 0 rgba(255,255,255,.08); transition: border-color .2s, box-shadow .2s, background .2s; }
        #main-header .pf-search-form:focus-within { border-color: rgba(83,197,224,.7); box-shadow: 0 0 0 4px rgba(83,197,224,.12); background: rgba(255,255,255,.09); }
        #main-header .pf-search-icon { width: 1rem; height: 1rem; color: rgba(255,255,255,.72); flex-shrink: 0; }
        #main-header .pf-search-input { width: 100%; background: transparent; border: none; outline: none; color: #fff; font-size: .88rem; }
        #main-header .pf-search-input::placeholder { color: rgba(255,255,255,.58); }
        #main-header .pf-header-right { flex: 0 0 auto; margin-left: auto; display: flex; align-items: center; gap: .6rem; }
        #main-header .pf-icon-btn { position: relative; width: 2.55rem; height: 2.55rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; color: rgba(255,255,255,.86); background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.11); transition: all .2s ease; overflow: visible; }
        #main-header .pf-icon-btn:hover { color: #53C5E0; border-color: rgba(83,197,224,.5); background: rgba(83,197,224,.12); transform: translateY(-1px); }
        #main-header .pf-icon-btn svg { width: 1.2rem; height: 1.2rem; stroke-width: 1.9; }
        #main-header .pf-cart-icon,
        #main-header .pf-notif-icon { width: 1.2rem; height: 1.2rem; stroke-width: 1.9; }
        #main-header .pf-notif-icon { width: 1.35rem; height: 1.35rem; }
        #main-header .pf-badge { position: absolute; top: -6px; right: -6px; background: #53C5E0; color: #0a2530; font-size: .65rem; font-weight: 900; border-radius: 9999px; min-width: 18px; height: 18px; padding: 0 4px; display: flex !important; align-items: center; justify-content: center; box-shadow: 0 0 10px rgba(83,197,224,.4); line-height: 1; border: 1.5px solid #0a2530; z-index: 10; pointer-events: none; }
        #main-header .pf-notif-dropdown { position: absolute; top: calc(100% + 10px); right: 0; width: 320px; max-height: 480px; background: #0a2530; border: 1px solid rgba(83,197,224,0.3); border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); display: none; flex-direction: column; overflow: hidden; z-index: 100; }
        #main-header .pf-notif-dropdown.open { display: flex; }
        #main-header .pf-notif-header { padding: 12px 16px; border-bottom: 1px solid rgba(83,197,224,0.1); display: flex; align-items: center; justify-content: space-between; background: rgba(83,197,224,0.05); }
        #main-header .pf-notif-header span { font-size: 0.7rem; font-weight: 800; color: #53c5e0; text-transform: uppercase; letter-spacing: 0.05em; }
        #main-header .pf-notif-header a { font-size: 0.7rem !important; color: #53c5e0; text-decoration: none; font-weight: 800 !important; text-transform: uppercase; letter-spacing: 0.05em; }
        #main-header .pf-notif-list { overflow-y: auto; flex: 1; }
        #main-header .pf-notif-list::-webkit-scrollbar { width: 6px; }
        #main-header .pf-notif-list::-webkit-scrollbar-track { background: rgba(83,197,224,0.05); border-radius: 10px; }
        #main-header .pf-notif-list::-webkit-scrollbar-thumb { background: rgba(83,197,224,0.3); border-radius: 10px; }
        #main-header .pf-notif-list::-webkit-scrollbar-thumb:hover { background: rgba(83,197,224,0.5); }
        #main-header .pf-notif-item { display: flex; gap: 12px; padding: 12px 16px; border-bottom: 1px solid rgba(83,197,224,0.05); transition: background 0.2s; text-decoration: none; align-items: flex-start; }
        #main-header .pf-notif-item:hover { background: rgba(83,197,224,0.08); }
        #main-header .pf-notif-item.unread { background: rgba(83,197,224,0.15); border-left: 3px solid #53c5e0; }
        #main-header .pf-notif-item-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(83,197,224,0.1); display: flex; align-items: center; justify-content: center; color: #53c5e0; flex-shrink: 0; }
        #main-header .pf-notif-item-content { flex: 1; min-width: 0; }
        #main-header .pf-notif-item-text { font-size: 0.8rem; color: #eaf6fb; line-height: 1.4; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        #main-header .pf-notif-item-time { font-size: 0.7rem; color: rgba(83,197,224,0.6); font-weight: 600; }
        #main-header .pf-notif-footer { padding: 8px; border-top: 1px solid rgba(83,197,224,0.1); text-align: center; }
        #main-header .pf-notif-footer a { font-size: 0.7rem !important; color: #53c5e0; font-weight: 800 !important; text-decoration: none; text-transform: uppercase; letter-spacing: 0.05em; }
        #main-header .pf-notif-empty { padding: 32px 16px; text-align: center; color: rgba(255,255,255,0.4); font-size: 0.85rem; }
        #main-header .pf-avatar { width: 2.55rem; height: 2.55rem; border-radius: 9999px; overflow: hidden; border: 1px solid rgba(83,197,224,.45); background: linear-gradient(135deg, rgba(83,197,224,.24), rgba(50,161,196,.4)); display: inline-flex; align-items: center; justify-content: center; color: #e6f7fc; font-size: .78rem; font-weight: 700; letter-spacing: .02em; }
        #main-header .pf-avatar img { width: 100%; height: 100%; object-fit: cover; }
        #main-header .pf-dropdown-menu { display: none; }
        #main-header .pf-dropdown-menu.open { display: block; }
        #main-header .pf-dropdown-link,
        #main-header .pf-dropdown-btn {
            display: flex;
            align-items: center;
            gap: .55rem;
            min-height: 2.2rem;
            padding: .5rem .85rem;
            font-size: .88rem;
            line-height: 1;
            white-space: nowrap;
            text-decoration: none;
            transition: background .15s, color .15s;
        }
        #main-header .pf-dropdown-link { color: rgba(255,255,255,.82); }
        #main-header .pf-dropdown-link:hover { background: rgba(83,197,224,.1); color: #53c5e0; }
        #main-header .pf-dropdown-btn {
            width: 100%;
            border: none;
            background: transparent;
            text-align: left;
            color: rgba(239,68,68,.9);
            cursor: pointer;
        }
        #main-header .pf-dropdown-btn:hover { background: rgba(239,68,68,.1); color: #ef4444; }
        #main-header .pf-dropdown-icon {
            width: 14px;
            height: 14px;
            flex: 0 0 14px;
            opacity: .72;
        }
        #main-header .pf-burger-btn {
            width: 2.55rem;
            height: 2.55rem;
            border-radius: 9999px;
            display: none;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,.13);
            background: rgba(255,255,255,.06);
            color: #e5f6fb;
            cursor: pointer;
        }
        #main-header .pf-burger-btn svg { width: 1.25rem; height: 1.25rem; }
        #main-header .pf-mobile-panel {
            display: none;
            border-top: 1px solid rgba(83,197,224,.16);
            background: rgba(8, 26, 35, .97);
            backdrop-filter: blur(8px);
            padding: .75rem 1rem 1rem;
        }
        #main-header .pf-mobile-panel.open { display: block; }
        #main-header .pf-mobile-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem;
            margin-bottom: 0.75rem;
            background: rgba(83,197,224,0.08);
            border: 1px solid rgba(83,197,224,0.2);
            border-radius: 0.75rem;
        }
        #main-header .pf-mobile-profile-avatar {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid rgba(83,197,224,.45);
            background: linear-gradient(135deg, rgba(83,197,224,.24), rgba(50,161,196,.4));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e6f7fc;
            font-size: .85rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        #main-header .pf-mobile-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        #main-header .pf-mobile-profile-info {
            flex: 1;
            min-width: 0;
        }
        #main-header .pf-mobile-profile-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #eaf6fb;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #main-header .pf-mobile-profile-email {
            font-size: 0.75rem;
            color: rgba(83,197,224,0.7);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #main-header .pf-mobile-search {
            display: flex;
            align-items: center;
            gap: .45rem;
            height: 2.45rem;
            border: 1px solid rgba(83,197,224,.28);
            border-radius: .7rem;
            background: rgba(255,255,255,.06);
            padding: 0 .75rem;
            margin-bottom: .75rem;
        }
        #main-header .pf-mobile-search input {
            width: 100%;
            border: none;
            background: transparent;
            color: #fff;
            outline: none;
            font-size: .88rem;
        }
        #main-header .pf-mobile-search input::placeholder { color: rgba(255,255,255,.58); }
        #main-header .pf-mobile-links {
            display: grid;
            grid-template-columns: 1fr;
            gap: .4rem;
        }
        #main-header .pf-mobile-link {
            display: flex;
            align-items: center;
            min-height: 2.45rem;
            border-radius: .65rem;
            padding: 0 .8rem;
            color: rgba(255,255,255,.88);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
        }
        #main-header .pf-mobile-link:hover { color: #53c5e0; background: rgba(83,197,224,.12); }
        #main-header .pf-mobile-icon-row { display: contents; }
        @media (min-width: 1024px) {
            #main-header .pf-header-mid { display: flex; }
        }
        @media (max-width: 1023px) {
            #main-header .pf-burger-btn { display: inline-flex; }
            #main-header .pf-header-shell {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                max-width: 100%;
            }
            #main-header .pf-header-left {
                display: flex;
                align-items: center;
                flex: 0 1 auto;
                min-width: 0;
            }
            #main-header .pf-header-left a {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                white-space: nowrap;
            }
            #main-header .pf-header-left img,
            #main-header .pf-header-left svg {
                width: 32px;
                height: 32px;
                flex-shrink: 0;
            }
            #main-header .pf-header-left .text-xl,
            #main-header .pf-header-left .text-2xl {
                font-size: 1.1rem !important;
                white-space: nowrap;
            }
            #main-header .pf-header-right {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex: 0 0 auto;
                margin-left: auto;
            }
            #main-header .pf-header-right [data-pf-profile-wrap] {
                display: none !important;
            }
            #main-header .pf-mobile-icon-row {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            #main-header .pf-burger-btn {
                order: 999;
            }
            #main-header .pf-dropdown-menu {
                right: 0;
                left: auto;
            }
            #main-header .pf-icon-btn {
                width: 2.4rem;
                height: 2.4rem;
            }
            #main-header .pf-burger-btn {
                width: 2.4rem;
                height: 2.4rem;
            }
        }
        @media (max-width: 380px) {
            #main-header .pf-header-shell {
                gap: 0.4rem;
            }
            #main-header .pf-header-left img,
            #main-header .pf-header-left svg {
                width: 28px;
                height: 28px;
            }
            #main-header .pf-header-left .text-xl,
            #main-header .pf-header-left .text-2xl {
                font-size: 1rem !important;
            }
            #main-header .pf-header-right {
                gap: 0.4rem;
            }
            #main-header .pf-mobile-icon-row {
                gap: 0.4rem;
            }
            #main-header .pf-icon-btn {
                width: 2.2rem;
                height: 2.2rem;
            }
            #main-header .pf-burger-btn {
                width: 2.2rem;
                height: 2.2rem;
            }
        }
    </style>
    <nav class="container mx-auto px-4 py-3">
        <div class="pf-header-shell">
            <!-- Logo -->
            <div class="pf-header-left flex items-center space-x-3">
                <a href="<?php echo $url_index; ?>" class="flex items-center space-x-2 group">
                    <?php if (!empty($shop_logo_url)): ?>
                        <img src="<?php echo htmlspecialchars($shop_logo_url); ?>?t=<?php echo time(); ?>"
                             alt="<?php echo $shop_name; ?>"
                             style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;transition:transform 0.3s;flex-shrink:0;"
                             class="group-hover:scale-105">
                        <span class="text-xl font-bold bg-gradient-to-r from-primary-600 to-accent-purple bg-clip-text text-transparent"><?php echo $shop_name; ?></span>
                    <?php else: ?>
                        <div class="relative">
                            <svg class="w-10 h-10 text-primary-600 transform group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                        </div>
                        <span class="text-2xl font-bold bg-gradient-to-r from-primary-600 to-accent-purple bg-clip-text text-transparent"><?php echo $shop_name; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Center: Desktop Navigation + Search -->
            <div class="pf-header-mid">
                <div class="pf-nav-links">
                <?php if ($is_logged_in): ?>
                    <?php if (is_admin()): ?>
                        <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Dashboard
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/admin/products_management.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/admin/customers_management.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Customers
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php elseif (is_staff()): ?>
                        <a href="<?php echo $base_url; ?>/staff/dashboard.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Dashboard
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/staff/products.php" class="nav-link text-white/80 hover:text-white font-medium transition-colors duration-200 relative group">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php elseif (is_customer()): ?>
                        <a href="<?php echo $base_url; ?>/customer/services.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Services
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/customer/products.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Products
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
<a href="<?php echo $url_index; ?>" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Home
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $base_url; ?>/public/about.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            About
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $base_url; ?>/public/services.php" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Services
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>
<a href="<?php echo $url_products; ?>" class="nav-link font-medium transition-colors duration-200 relative group" style="color:inherit;">
                            Products
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-primary-600 to-accent-purple group-hover:w-full transition-all duration-300"></span>
                    </a>

                <?php endif; ?>
                </div>
                <?php if ($show_header_search): ?>
                <div class="pf-search-wrap" style="position:relative;">
                    <form class="pf-search-form" action="<?php echo htmlspecialchars($search_action); ?>" method="get" autocomplete="off" onsubmit="document.getElementById('pf-search-dropdown').style.display='none'">
                        <svg class="pf-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"></path>
                        </svg>
                        <input
                            type="search"
                            id="pf-search-input"
                            name="<?php echo htmlspecialchars($search_param); ?>"
                            value="<?php echo htmlspecialchars($search_query); ?>"
                            class="pf-search-input"
                            placeholder="<?php echo htmlspecialchars(is_customer() ? 'Search services, products...' : $search_placeholder); ?>"
                            aria-label="Search"
                            autocomplete="off">
                    </form>
                    <?php if (is_customer()): ?>
                    <div id="pf-search-dropdown" style="display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;background:#0a2530;border:1px solid rgba(83,197,224,0.3);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.5);z-index:9999;overflow:hidden;"></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Side Icons -->
            <div class="pf-header-right">
                <?php if ($is_logged_in): ?>
                    <?php if (is_customer()): ?>
                    <button type="button" class="pf-burger-btn" data-pf-mobile-toggle aria-label="Open navigation menu">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"></path>
                        </svg>
                    </button>
                    <div class="pf-mobile-icon-row">
                    <?php endif; ?>
                    <!-- Cart icon (customer only) -->
                    <?php if (is_customer()): ?>
                    <?php
                    $cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
                    $cart_display = $cart_count > 99 ? '99+' : $cart_count;
                    ?>
                    <div class="relative" style="display:inline-flex;">
                        <a href="<?php echo $base_url; ?>/customer/cart.php" title="My Cart" class="pf-icon-btn nav-link" style="color:white;">
                            <svg class="pf-cart-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 3 1.5 1.5m0 0 2.07 10.358A2.25 2.25 0 0 0 8.027 16.5h8.946a2.25 2.25 0 0 0 2.206-1.642L21 8.25H6.375m-2.625-3.75H21M9 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm8.25 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                            </svg>
                        </a>
                        <?php if ($cart_count > 0): ?>
                        <span id="cart-count-badge" class="pf-badge"><?php echo $cart_display; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <!-- Notifications -->
                    <div class="relative" data-pf-notif-wrap>
                        <button type="button" 
                           title="Notifications"
                           data-pf-notif-toggle
                           class="pf-icon-btn nav-link">
                            <svg class="pf-notif-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <?php 
                            $notif_display = $unread_count > 99 ? '99+' : $unread_count; 
                            ?>
                            <span id="nav-notif-badge" data-notif-badge class="pf-badge" style="display:<?php echo ($unread_count > 0 ? 'flex' : 'none'); ?>;"><?php echo $notif_display; ?></span>
                        </button>

                        <!-- Notification Dropdown -->
                        <div data-pf-notif-menu class="pf-notif-dropdown">
                            <div class="pf-notif-header">
                                <span>Notifications</span>
                                <a href="?mark_all_read=1" style="font-size:0.7rem !important; color:#53c5e0; text-decoration:none; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">Mark all read</a>
                            </div>
                            <div class="pf-notif-list" data-pf-notif-list>
                                <div class="pf-notif-empty">Loading notifications...</div>
                            </div>
                            <div class="pf-notif-footer">
                                <a href="<?php echo $base_url; ?>/<?php echo strtolower($user_type); ?>/notifications.php" style="font-size:0.7rem !important; color:#53c5e0; font-weight:800 !important; text-decoration:none; text-transform:uppercase; letter-spacing:0.05em;">View All Notifications</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Dropdown -->
                    <div class="relative" data-pf-profile-wrap>
                        <button type="button" data-pf-profile-toggle class="pf-icon-btn nav-link flex items-center gap-3" style="width:auto;padding:0 0.5rem;" title="My Account">
                            <div class="pf-avatar transition-all duration-300"
                                 style="width:1.85rem;height:1.85rem;font-size:0.7rem;<?php echo (stripos($_SERVER['REQUEST_URI'] ?? '', '/profile.php') !== false) ? 'color:#53C5E0;' : ''; ?>">
                                <?php if (!empty($current_user['profile_picture'])): ?>
                                    <img src="<?php echo $asset_base; ?>/assets/uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>?t=<?php echo time(); ?>" 
                                         alt="Profile" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars($initials); ?></span>
                                <?php endif; ?>
                            </div>
                            <svg class="w-3.5 h-3.5 transition-transform duration-200" 
                                 style="color:inherit;flex-shrink:0;opacity:0.8;" 
                                 fill="currentColor" 
                                 viewBox="0 0 20 20"
                                 data-dropdown-arrow>
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div data-pf-profile-menu
                             class="pf-dropdown-menu absolute right-0 mt-2 w-52 rounded-xl py-1 z-50"
                             style="background:#0a2530;border:1px solid rgba(83,197,224,0.2);box-shadow:0 8px 32px rgba(0,0,0,0.4);">
                            <?php if (is_customer()): ?>
                            <a href="<?php echo $base_url; ?>/customer/orders.php"
                               class="pf-dropdown-link">
                                <svg class="pf-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18v4H3V3zm2 6h14v10H5V9zm2 2v6h10v-6H7z"></path>
                                </svg>
                                Orders
                            </a>
                            <a href="<?php echo $base_url; ?>/customer/messages.php"
                               class="pf-dropdown-link">
                                <svg class="pf-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8m-8 4h5m-7 6h12a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"></path>
                                </svg>
                                Messages
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo $base_url; ?>/<?php echo strtolower($user_type); ?>/profile.php"
                               class="pf-dropdown-link">
                                <svg class="pf-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Profile
                            </a>
                            <div style="height:1px;background:rgba(83,197,224,0.1);margin:0.25rem 0;"></div>
                            <button onclick="document.getElementById('logout-confirm-modal').style.display='flex'" type="button"
                               class="pf-dropdown-btn">
                                <svg class="pf-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                Logout
                            </button>
                        </div>
                    </div>
                    <?php if (is_customer()): ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="#" data-auth-modal="login" class="font-medium transition-colors duration-200" style="color:inherit;">Login</a>
                    <a href="#" data-auth-modal="register" class="btn-gradient-primary pf-auth-cta pf-register-cta">Register</a>
                    <button type="button" id="pwa-install-btn" aria-label="Install PrintFlow app" class="pf-auth-cta" style="display:inline-flex;align-items:center;gap:0.45rem;white-space:nowrap;">
                        <svg style="width:15px;height:15px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Install App
                    </button>
                <?php endif; ?>

            </div>
        </div>
    </nav>
    <?php if ($is_logged_in && is_customer()): ?>
    <div class="pf-mobile-panel" data-pf-mobile-panel>
        <!-- Profile Section -->
        <div class="pf-mobile-profile">
            <div class="pf-mobile-profile-avatar">
                <?php if (!empty($current_user['profile_picture'])): ?>
                    <img src="<?php echo $asset_base; ?>/assets/uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>?t=<?php echo time(); ?>" 
                         alt="Profile">
                <?php else: ?>
                    <span><?php echo htmlspecialchars($initials); ?></span>
                <?php endif; ?>
            </div>
            <div class="pf-mobile-profile-info">
                <div class="pf-mobile-profile-name"><?php echo htmlspecialchars($first . ' ' . $last); ?></div>
                <div class="pf-mobile-profile-email"><?php echo htmlspecialchars($current_user['email'] ?? ''); ?></div>
            </div>
        </div>

        <!-- Search -->
        <form class="pf-mobile-search" action="<?php echo htmlspecialchars($search_action); ?>" method="get" autocomplete="off">
            <svg style="width:1rem;height:1rem;color:rgba(255,255,255,.72);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"></path>
            </svg>
            <input type="search" name="<?php echo htmlspecialchars($search_param); ?>" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="<?php echo htmlspecialchars($search_placeholder); ?>" aria-label="Search">
        </form>

        <!-- Navigation Links -->
        <div class="pf-mobile-links">
            <a class="pf-mobile-link" href="<?php echo $base_url; ?>/customer/services.php">Services</a>
            <a class="pf-mobile-link" href="<?php echo $base_url; ?>/customer/products.php">Products</a>
            <a class="pf-mobile-link" href="<?php echo $base_url; ?>/customer/orders.php">Orders</a>
            <a class="pf-mobile-link" href="<?php echo $base_url; ?>/customer/messages.php">Messages</a>
            <a class="pf-mobile-link" href="<?php echo $base_url; ?>/customer/profile.php">Profile</a>
            <div style="height:1px;background:rgba(83,197,224,0.15);margin:0.5rem 0;"></div>
            <button onclick="document.getElementById('logout-confirm-modal').style.display='flex'" type="button"
                    class="pf-mobile-link" 
                    style="width:100%;border:none;cursor:pointer;color:rgba(239,68,68,.9);background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);">
                Logout
            </button>
        </div>
    </div>
    <?php endif; ?>
</header>

<?php if ($is_logged_in): ?>
<!-- Logout Confirmation Modal -->
<div id="logout-confirm-modal"
     style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;"
     onclick="if(event.target===this)this.style.display='none'">
    <!-- Backdrop -->
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.75);"></div>
    <!-- Card -->
    <div style="position:relative;background:#0a2530;border:1.5px solid #53C5E0;border-radius:20px;padding:2.5rem 2rem 2rem;max-width:380px;width:100%;box-shadow:0 25px 50px -12px rgba(83,197,224,0.2);text-align:center;">
        <!-- Icon -->
        <div style="width:64px;height:64px;background:rgba(83,197,224,0.1);border:1.5px solid rgba(83,197,224,0.3);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;transform:rotate(-5deg);">
            <svg style="width:30px;height:30px;color:#53C5E0;" fill="none" stroke="#53C5E0" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
        </div>
        <h3 style="font-size:1.25rem;font-weight:800;color:#ffffff !important;margin:0 0 0.75rem;letter-spacing:-0.02em;">Sign Out</h3>
        <p style="font-size:0.95rem;color:#cbd5e1;margin:0 0 2rem;line-height:1.6;">Are you sure you want to sign out of your account?</p>
        <!-- Buttons -->
        <div style="display:flex;gap:1rem;">
            <button onclick="document.getElementById('logout-confirm-modal').style.display='none'" type="button"
                    style="flex:1;padding:0.75rem 1rem;border:1px solid #1e293b;border-radius:12px;background:#1e293b;color:#cbd5e1;font-size:0.875rem;font-weight:700;cursor:pointer;transition:all 0.2s;"
                    onmouseover="this.style.background='#334155';this.style.borderColor='#53C5E0';this.style.color='#f8fafc'"
                    onmouseout="this.style.background='#1e293b';this.style.borderColor='#1e293b';this.style.color='#cbd5e1'">
                Cancel
            </button>
            <a href="<?php echo $url_logout; ?>"
               style="flex:1;padding:0.75rem 1rem;border-radius:12px;background:#EF4444;color:#fff !important;font-size:0.875rem;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;transition:all 0.2s;box-shadow:0 8px 15px -3px rgba(239,68,68,0.3);"
               onmouseover="this.style.background='#DC2626';this.style.transform='translateY(-1px)';this.style.boxShadow='0 10px 20px -2px rgba(239,68,68,0.4)'"
               onmouseout="this.style.background='#EF4444';this.style.transform='translateY(0)';this.style.boxShadow='0 8px 15px -3px rgba(239,68,68,0.3)'">
                Sign Out
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function(){
    // ── Active nav link ──────────────────────────────────────────
    var p = window.location.pathname.toLowerCase().replace(/\/$/, '');
    var navLinks = document.querySelectorAll('a.nav-link');
    for (var i = 0; i < navLinks.length; i++) {
        var a = navLinks[i];
        var h = (a.getAttribute('href') || '').toLowerCase().replace(/\/$/, '');
        var isHomeLink = (h === '/printflow/public' || h === '/printflow' || h === '' || h.endsWith('/index.php'));
        var isHomePage = (p === '/printflow/public' || p === '/printflow' || p === '' || p.endsWith('/index.php'));
        if (isHomeLink && isHomePage) {
            a.classList.add('nav-active');
        } else if (!isHomeLink) {
            var hFile = h.split('/').pop().replace('.php', '');
            var pFile = p.split('/').pop().replace('.php', '');
            if (hFile && pFile && hFile === pFile) a.classList.add('nav-active');
        }
    }

    // ── Profile dropdown ─────────────────────────────────────────
    var profileWraps = document.querySelectorAll('[data-pf-profile-wrap]');
    for (var j = 0; j < profileWraps.length; j++) {
        (function(wrap){
            var btn = wrap.querySelector('[data-pf-profile-toggle]');
            var menu = wrap.querySelector('[data-pf-profile-menu]');
            var arrow = wrap.querySelector('[data-dropdown-arrow]');
            if (!btn || !menu) return;
            btn.addEventListener('click', function(ev){ ev.preventDefault(); ev.stopPropagation(); var isOpen = menu.classList.toggle('open'); if (arrow) arrow.style.transform = isOpen ? 'rotate(180deg)' : 'rotate(0deg)'; });
            menu.addEventListener('click', function(ev){ ev.stopPropagation(); });
            document.addEventListener('click', function(ev){ if (!wrap.contains(ev.target)) { menu.classList.remove('open'); if (arrow) arrow.style.transform = 'rotate(0deg)'; } });
        })(profileWraps[j]);
    }

    // ── Notifications dropdown ───────────────────────────────────
    var notifWraps = document.querySelectorAll('[data-pf-notif-wrap]');
    for (var k = 0; k < notifWraps.length; k++) {
        (function(wrap){
            var btn = wrap.querySelector('[data-pf-notif-toggle]');
            var menu = wrap.querySelector('[data-pf-notif-menu]');
            if (!btn || !menu) return;
            btn.addEventListener('click', function(ev){
                ev.preventDefault(); ev.stopPropagation();
                var isOpen = menu.classList.toggle('open');
                if (isOpen) {
                    if (window.PFNotifications && typeof window.PFNotifications.loadDropdown === 'function') {
                        window.PFNotifications.loadDropdown();
                    } else {
                        var list = wrap.querySelector('[data-pf-notif-list]');
                        if (list) list.innerHTML = '<div class="pf-notif-empty">System initializing...</div>';
                        setTimeout(function(){
                            if (window.PFNotifications && typeof window.PFNotifications.loadDropdown === 'function') window.PFNotifications.loadDropdown();
                            else if (list) list.innerHTML = '<div class="pf-notif-empty">Failed to initialize notifications.</div>';
                        }, 1000);
                    }
                }
            });
            document.addEventListener('click', function(ev){ if (!wrap.contains(ev.target)) menu.classList.remove('open'); });
        })(notifWraps[k]);
    }

    // ── Mobile panel ─────────────────────────────────────────────
    var mobileToggle = document.querySelector('[data-pf-mobile-toggle]');
    var mobilePanel = document.querySelector('[data-pf-mobile-panel]');
    if (mobileToggle && mobilePanel) {
        mobileToggle.addEventListener('click', function(ev){ ev.preventDefault(); ev.stopPropagation(); mobilePanel.classList.toggle('open'); });
        document.addEventListener('click', function(ev){ if (!mobilePanel.contains(ev.target) && !mobileToggle.contains(ev.target)) mobilePanel.classList.remove('open'); });
    }

    // ── Cart count badge ─────────────────────────────────────────
    window.updateCartBadge = function(count) {
        var badge = document.getElementById('cart-count-badge');
        count = parseInt(count) || 0;
        
        if (count > 0) {
            // Create badge if it doesn't exist
            if (!badge) {
                var cartLink = document.querySelector('a[href*="cart.php"]');
                if (cartLink) {
                    var container = cartLink.parentElement;
                    badge = document.createElement('span');
                    badge.id = 'cart-count-badge';
                    badge.className = 'pf-badge';
                    container.appendChild(badge);
                }
            }
            if (badge) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            }
        } else {
            // Remove badge if count is 0
            if (badge) {
                badge.remove();
            }
        }
    };

    // Poll cart count on load and every 5s
    <?php if ($is_logged_in && is_customer()): ?>
    (function pollCart() {
        fetch('/printflow/customer/api_cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_count', csrf_token: '<?php echo generate_csrf_token(); ?>'})
        })
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d.success) window.updateCartBadge(d.cart_count); })
        .catch(function(){});
        setTimeout(pollCart, 5000);
    })();
    <?php endif; ?>

    // ── Realtime search ──────────────────────────────────────────
    <?php if ($show_header_search): ?>
    (function(){
        console.log('Search script initializing...');
        var input = document.getElementById('pf-search-input');
        console.log('Search input element:', input);
        var dropdown = document.getElementById('pf-search-dropdown');
        console.log('Search dropdown element:', dropdown);
        if (!input) {
            console.error('Search input not found!');
            return;
        }
        if (!dropdown) {
            console.log('Dropdown not found, creating it...');
            dropdown = document.createElement('div');
            dropdown.id = 'pf-search-dropdown';
            dropdown.style.cssText = 'display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;background:#0a2530;border:1px solid rgba(83,197,224,0.3);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.5);z-index:9999;overflow:hidden;';
            var wrap = input.closest('.pf-search-wrap');
            if (wrap) {
                wrap.appendChild(dropdown);
                console.log('Dropdown created and appended');
            } else {
                console.error('Could not find .pf-search-wrap parent');
            }
        }

        var timer = null;
        var icons = {
            service: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>',
            product: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
            order:   '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>'
        };
        var colors = { service: '#53c5e0', product: '#a78bfa', order: '#34d399' };

        function doSearch(q) {
            console.log('Search triggered for:', q);
            if (!q.trim()) { dropdown.style.display = 'none'; return; }
            console.log('Fetching:', '/printflow/public/api/search.php?q=' + encodeURIComponent(q));
            fetch('/printflow/public/api/search.php?q=' + encodeURIComponent(q))
            .then(function(r){ 
                console.log('Response status:', r.status);
                return r.json(); 
            })
            .then(function(data) {
                console.log('Search results:', data);
                var results = data.results || [];
                if (!results.length) { 
                    console.log('No results, hiding dropdown');
                    dropdown.style.display = 'none'; 
                    return; 
                }
                var html = '';
                results.forEach(function(r) {
                    var color = colors[r.type] || '#fff';
                    html += '<a href="' + r.url + '" style="display:flex;align-items:center;gap:10px;padding:10px 14px;text-decoration:none;border-bottom:1px solid rgba(83,197,224,0.08);transition:background 0.15s;" '
                          + 'onmouseover="this.style.background=\'rgba(83,197,224,0.1)\'" onmouseout="this.style.background=\'\'">'
                          + '<span style="color:' + color + ';flex-shrink:0;">' + (icons[r.type] || '') + '</span>'
                          + '<span style="flex:1;min-width:0;">'
                          + '<span style="display:block;font-size:0.82rem;font-weight:600;color:#eaf6fb;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + r.label.replace(/</g,'&lt;') + '</span>'
                          + '<span style="display:block;font-size:0.72rem;color:rgba(255,255,255,0.45);">' + r.sub.replace(/</g,'&lt;') + '</span>'
                          + '</span>'
                          + '<span style="font-size:0.65rem;font-weight:700;text-transform:uppercase;color:' + color + ';opacity:0.7;flex-shrink:0;">' + r.type + '</span>'
                          + '</a>';
                });
                console.log('Showing dropdown with', results.length, 'results');
                dropdown.innerHTML = html;
                dropdown.style.display = 'block';
            })
            .catch(function(err){ 
                console.error('Search error:', err);
                dropdown.style.display = 'none'; 
            });
        }

        input.addEventListener('input', function() {
            console.log('Input event fired, value:', input.value);
            clearTimeout(timer);
            timer = setTimeout(function(){ doSearch(input.value); }, 220);
        });

        input.addEventListener('focus', function() {
            if (input.value.trim()) doSearch(input.value);
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') dropdown.style.display = 'none';
        });
    })();
    <?php endif; ?>
}());
</script>
