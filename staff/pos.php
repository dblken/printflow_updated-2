<?php
/**
 * Point of Sale (POS) - Staff Walk-in Interface
 * PrintFlow - Printing Shop PWA
 */
$GLOBALS['PRINTFLOW_DISABLE_TURBO'] = true;

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Require staff or admin role
require_role(['Admin', 'Staff']);

$page_title = "Point of Sale (POS)";
$current_page = "pos";
$user_name = $_SESSION['user_name'] ?? 'Staff';

// Fetch Categories
$categories = [];
try {
    $categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' AND category IS NOT NULL ORDER BY category ASC");
} catch (Exception $e) { }

// Fetch Customers
$customers = [];
try {
    $customers = db_query("SELECT customer_id, first_name, last_name, email, contact_number FROM customers ORDER BY first_name ASC, last_name ASC");
} catch (Exception $e) { }

// Fetch Branches (for service modals)
$branches = [];
try {
    $branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - PrintFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    
    <style>
        /* 
         * STABLE POS LAYOUT
         * We use absolute positioning inside a relative container to prevent ALL jumping/height shifts.
         */
        
        /* The container takes up exactly the available height minus the top bar */
        .pos-wrapper {
            position: relative;
            flex: 1;
            height: 100%;
            display: flex;
            background: #ffffff;
            border: none;
            overflow: hidden;
            margin: 0;
            min-height: 0; /* Critical for vertical scroll */
        }

        /* Left Side: Products */
        .pos-products-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
            min-width: 0;
            min-height: 0;
        }
        
        .pos-search-header {
            padding: 20px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .pos-search-box {
            position: relative;
            flex: 1;
        }
        
        .pos-search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .pos-search-input {
            width: 100%;
            padding: 12px 16px 12px 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .pos-search-input:focus {
            border-color: #06A1A1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .pos-category-select {
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            font-size: 14px;
            min-width: 180px;
            outline: none;
            cursor: pointer;
        }

        .pos-products-grid {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            align-content: start;
            background: #f1f5f9;
        }
        
        /* Product Card */
        .pos-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.6);
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        
        .pos-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: #06A1A1;
        }
        
        .pos-card.no-stock {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(80%);
        }
        .pos-card.no-stock:hover { transform: none; box-shadow: none; }
        
        .pos-card-img-container {
            width: 100%;
            height: 140px;
            position: relative;
            background: #f1f5f9;
        }
        
        .pos-card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .pos-card-price {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 700;
            color: #4f46e5;
            font-size: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        
        .pos-card-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .pos-card-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }
        
        .pos-card-stock {
            margin-top: auto;
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Right Side: Cart */
        .pos-cart-area {
            width: 420px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            border-left: 1px solid #e2e8f0;
            min-height: 0;
        }
        
        .pos-cart-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pos-cart-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .pos-btn-clear {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .pos-btn-clear:hover { background: #fca5a5; }

        .pos-customer-section {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .pos-customer-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }
        
        .pos-btn-link {
            background: none;
            border: none;
            color: #06A1A1;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
        }
        .pos-btn-link:hover { text-decoration: underline; }

        .pos-cart-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        
        .pos-empty-state {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            text-align: center;
        }
        
        .pos-empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #cbd5e1;
        }
        
        .pos-cart-item {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            border: 1px solid #f1f5f9;
            border-radius: 10px;
            margin-bottom: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            transition: all 0.2s;
        }
        .pos-cart-item:hover {
            border-color: #06A1A1;
            background: #f8fafc;
        }
        
        .pos-item-details { flex: 1; padding-right: 12px; min-width: 0; }
        .pos-item-name { font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 4px; line-height: 1.2; word-wrap: break-word; overflow-wrap: break-word; }
        .pos-item-price { font-size: 12px; color: #64748b; }
        
        .pos-item-controls {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .pos-qty-btn {
            background: none;
            border: none;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #475569;
        }
        .pos-qty-btn:hover { background: #e2e8f0; }
        
        .pos-qty-val {
            width: 30px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            border: none;
            background: transparent;
            pointer-events: none;
        }
        
        .pos-item-total {
            font-weight: 700;
            font-size: 14px;
            min-width: 60px;
            text-align: right;
            margin-left: 12px;
        }
        
        .pos-item-remove {
            color: #ef4444;
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 12px;
            padding: 4px;
            opacity: 0.6;
        }
        .pos-item-remove:hover { opacity: 1; }

        .pos-checkout-section {
            padding: 16px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        
        @media (max-height: 800px) {
            .pos-checkout-section { padding: 12px 20px; }
            .pos-payment-tabs { margin: 12px 0; }
            .pos-summary-total { margin-top: 12px; padding-top: 12px; font-size: 18px; }
            .service-btn { padding: 20px 16px; border-radius: 12px; font-size: 14px; gap: 8px; }
            .service-btn i { width: 48px; height: 48px; font-size: 24px; border-radius: 10px; }
            .pos-tender-group { margin-bottom: 12px; }
            .pos-btn-checkout { padding: 12px; }
        }
        
        .pos-summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            color: #475569;
        }
        
        .pos-summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
            font-size: 20px;
            font-weight: 800;
            color: #1e293b;
        }
        

        
        .pos-tender-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .pos-tender-input {
            width: 140px;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-align: right;
            font-weight: 700;
            font-size: 16px;
            outline: none;
        }
        .pos-tender-input:focus { border-color: #06A1A1; }


        
        .pos-btn-checkout {
            width: 100%;
            padding: 16px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .pos-btn-checkout:hover { background: #4338ca; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .pos-btn-checkout:disabled { background: #94a3b8; cursor: not-allowed; }

        /* Clean text-based service buttons - Shopee style */
        .service-btn {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
        }
        .service-btn:hover {
            transform: translateY(-4px) scale(1.02);
            border-color: #06A1A1;
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.15), 0 10px 10px -5px rgba(99, 102, 241, 0.1);
        }
        .service-btn.active,
        .service-btn:active {
            border-color: #4f46e5;
            background: #eef2ff;
            color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.3);
        }
        .service-btn.btn-other {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            color: #64748b;
        }
        .service-btn.btn-other:hover,
        .service-btn.btn-other.active {
            border-style: solid;
            border-color: #06A1A1;
        }

        .pos-services-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 24px;
            align-content: start;
            height: 100%;
        }

        /* Price Input Modal */
        #price-modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000; align-items:center; justify-content:center;
        }
        .price-modal {
            background: #fff; width: 320px; border-radius: 20px; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); color: #1e293b; border: 1px solid #e2e8f0;
        }

        /* Hide scrollbar for grid to look cleaner */
        .pos-products-grid::-webkit-scrollbar, .pos-cart-list::-webkit-scrollbar { width: 6px; }
        .pos-products-grid::-webkit-scrollbar-thumb, .pos-cart-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    </style>
</head>
<body data-turbo="false">

<div class="dashboard-container">
    <?php 
    if ($_SESSION['user_type'] === 'Staff') {
        include __DIR__ . '/../includes/staff_sidebar.php';
    } else {
        include __DIR__ . '/../includes/admin_sidebar.php';
    }
    ?>

    <div class="main-content" style="padding: 0; height: 100vh; overflow: hidden; display: flex; flex-direction: column; width: 100%; min-height: 0;">
        <main style="flex: 1; display: flex; flex-direction: column; width: 100%; min-height: 0;">
            <div class="pos-wrapper" style="width: 100%; flex: 1; min-height: 0;">
                
                <!-- LEFT: SERVICES -->
                <div class="pos-products-area" style="background:#fff;">
                    <div style="padding: 24px; border-bottom: 1px solid #e2e8f0; background: #fff;">
                        <h2 style="font-weight:700; font-size:18px; color:#1e293b; margin:0;">Available Services</h2>
                        <p style="font-size:13px; color:#64748b; margin-top:4px;">Quickly add a printing service to the order.</p>
                    </div>
                    
                    <div class="pos-services-grid" style="border-bottom:none; padding: 24px;">
                        <button type="button" class="service-btn" onclick="addQuickService('Tarpaulin'); setActiveService(this)" data-service="Tarpaulin">Tarpaulin</button>
                        <button type="button" class="service-btn" onclick="addQuickService('T-Shirt'); setActiveService(this)" data-service="T-Shirt">T-Shirt</button>
                        <button type="button" class="service-btn" onclick="addQuickService('Decals / Stickers'); setActiveService(this)" data-service="Decals / Stickers">Decals / Stickers</button>
                        <button type="button" class="service-btn" onclick="addQuickService('Reflectorized'); setActiveService(this)" data-service="Reflectorized">Reflectorized</button>
                        <button type="button" class="service-btn" onclick="addQuickService('Glass/Wall'); setActiveService(this)" data-service="Glass/Wall">Glass/Wall</button>
                        <button type="button" class="service-btn" onclick="addQuickService('Transparent Stickers'); setActiveService(this)" data-service="Transparent Stickers">Transparent</button>
                        <button type="button" class="service-btn" onclick="addQuickService('Sintraboard'); setActiveService(this)" data-service="Sintraboard">Sintraboard</button>
                        <button type="button" class="service-btn" onclick="addQuickService('Standees'); setActiveService(this)" data-service="Standees">Standees</button>
                        <button type="button" class="service-btn" onclick="addQuickService('Souvenirs'); setActiveService(this)" data-service="Souvenirs">Souvenirs</button>
                        <button type="button" class="service-btn btn-other" onclick="addOtherService(); setActiveService(this)" data-service="Other">+ Other</button>
                    </div>
                </div>
                
                <!-- RIGHT: CART -->
                <div class="pos-cart-area">
                    <div class="pos-cart-header">
                        <h2>Current Order</h2>
                        <button class="pos-btn-clear" onclick="clearCart()"><i class="fas fa-trash"></i> Clear</button>
                    </div>
                    
                    <div class="pos-customer-section">
                        <div class="pos-customer-label">
                            <span>Customer</span>
                            <button class="pos-btn-link" onclick="openNewCustomerModal()">+ New</button>
                        </div>
                        <select id="pos-customer" class="pos-category-select" style="width: 100%; min-width: unset;">
                            <option value="guest">Walk-in Customer (Guest)</option>
                            <?php foreach($customers as $c): ?>
                                <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="pos-cart-list" id="pos-cart-items">
                        <div class="pos-empty-state">
                            <i class="fas fa-shopping-basket"></i>
                            <p>Cart is empty</p>
                        </div>
                    </div>
                    
                    <div class="pos-checkout-section">
                        <div class="pos-summary-line">
                            <span>Subtotal</span>
                            <span id="pos-subtotal">₱0.00</span>
                        </div>
                        
                        <div class="pos-summary-total">
                            <span id="pos-total">₱0.00</span>
                        </div>
                        
                        <div class="pos-tender-group" style="margin-bottom: 12px;">
                            <span style="font-weight: 600; font-size: 14px; color: #475569;">Payment Method</span>
                            <select id="pos-payment-method" class="pos-category-select" style="min-width: 140px; text-align: right; padding: 10px;" onchange="toggleReferenceField()">
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                                <option value="Maya">Maya</option>
                            </select>
                        </div>
                        
                        <div class="pos-tender-group" id="reference-group" style="display: none; margin-bottom: 12px;">
                            <span style="font-weight: 600; font-size: 14px; color: #475569;">Reference No. *</span>
                            <input type="text" id="pos-reference" name="reference_number" class="pos-tender-input" placeholder="e.g. 100234" oninput="updateCheckoutState()">
                        </div>

                        <div class="pos-tender-group" id="tender-group">
                            <span style="font-weight: 600; font-size: 14px; color: #475569;">Tendered</span>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 12px; font-weight: 600; color: #94a3b8;">₱</span>
                                <input type="number" id="pos-tendered" name="amount_tendered" class="pos-tender-input" placeholder="0.00" oninput="calculateChange()" style="padding-left: 28px;">
                            </div>
                        </div>
                        
                        <div class="pos-summary-line" id="change-group" style="margin-bottom: 20px; align-items: center;">
                            <span style="font-weight: 600; color: #475569;">Change</span>
                            <span id="pos-change" style="font-size: 20px; font-weight: 800; color: #06A1A1;">₱0.00</span>
                        </div>
                        
                        <button class="pos-btn-checkout" id="pos-checkout-btn" disabled onclick="processCheckout()">
                            <i class="fas fa-lock" id="checkout-icon"></i> <span id="checkout-text">Select Items</span>
                        </button>
                    </div>
                </div>

            </div> <!-- END pos-wrapper -->
        </main>
    </div>
</div>

<!-- POS Customization Modal -->
<div id="custom-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; flex-direction:column;">
    <div style="background:#ffffff; width:450px; border-radius:20px; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.15); border:1px solid #e2e8f0; transform:translateY(0); transition:all 0.3s; margin:16px; color:#1e293b;">
        <h3 id="cm-title" style="margin:0 0 20px 0; font-size:20px; font-weight:800; color:#0f172a; letter-spacing:-0.02em;">Product Customization</h3>
        
        <div id="cm-dynamic-fields" style="display:flex; flex-direction:column; gap:16px; margin-bottom:24px; max-height: 450px; overflow-y:auto; padding-right:8px;">
            <!-- Fields generated dynamically via JS -->
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px; border-top:1px solid #f1f5f9; padding-top:20px;">
            <button onclick="closeCustomModal()" style="padding:12px 20px; border:1px solid #e2e8f0; background:#f8fafc; border-radius:12px; cursor:pointer; font-weight:600; font-size:14px; color:#64748b; transition:all 0.2s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#1e293b'" onmouseout="this.style.background='#f8fafc';this.style.color='#64748b'">Cancel</button>
            <button onclick="confirmCustomization()" style="padding:12px 28px; border:none; background:#4f46e5; color:white; border-radius:12px; cursor:pointer; font-weight:700; font-size:14px; box-shadow:0 10px 15px -3px rgba(79,70,229,0.3); transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)';this.style.background='#4338ca'" onmouseout="this.style.transform='translateY(0)';this.style.background='#4f46e5'">Add to Cart</button>
        </div>
    </div>
</div>

<!-- Modal for New Customer -->
<div id="customer-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:999; align-items:center; justify-content:center;">
    <div style="background:#ffffff; width:400px; border-radius:20px; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.15); color:#1e293b; border:1px solid #e2e8f0;">
        <div style="display:flex; justify-content:space-between; margin-bottom:24px;">
            <h3 style="margin:0; font-weight:800; color:#0f172a; font-size:20px; letter-spacing:-0.02em;">Add Customer</h3>
            <button onclick="closeCustomerModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#94a3b8; padding:4px;" onmouseover="this.style.color='#1e293b'" onmouseout="this.style.color='#94a3b8'">&times;</button>
        </div>
        <input type="text" id="nc-first" placeholder="First Name" style="width:100%; padding:14px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#1e293b; outline:none; transition:all 0.2s;" onfocus="this.style.borderColor='#06A1A1';this.style.background='#fff'">
        <input type="text" id="nc-last" placeholder="Last Name" style="width:100%; padding:14px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#1e293b; outline:none; transition:all 0.2s;" onfocus="this.style.borderColor='#06A1A1';this.style.background='#fff'">
        <input type="tel" id="nc-phone" placeholder="Phone Number" style="width:100%; padding:14px; margin-bottom:24px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#1e293b; outline:none; transition:all 0.2s;" onfocus="this.style.borderColor='#06A1A1';this.style.background='#fff'">
        <button onclick="saveCustomer()" id="nc-save-btn" style="width:100%; background:#4f46e5; color:white; padding:14px; border:none; border-radius:12px; font-weight:700; cursor:pointer; box-shadow:0 10px 15px -3px rgba(79,70,229,0.3); transition:all 0.2s;" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">Save Customer</button>
    </div>
</div>

<!-- Modal for Custom Price -->
<div id="price-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000; align-items:center; justify-content:center;">
    <div class="price-modal" style="border-radius:20px; border:1px solid #e2e8f0;">
        <h3 id="pm-title" style="margin:0 0 12px 0; font-size:20px; font-weight:800; color:#0f172a; letter-spacing:-0.02em;">Set Price</h3>
        <div id="pm-name-group" style="margin-bottom: 24px; display:none;">
            <label style="display:block; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; letter-spacing:0.05em;">Service Name</label>
            <input type="text" id="pm-name-input" name="custom_service_name" style="width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#1e293b; outline:none;" placeholder="e.g. Custom Frame">
        </div>
        <div style="margin-bottom:28px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; letter-spacing:0.05em;">Negotiated Price</label>
            <div style="position: relative;">
                <span style="position: absolute; left: 16px; top: 14px; font-weight: 700; color: #94a3b8;">₱</span>
                <input type="number" id="pm-price-input" name="custom_service_price" style="width:100%; padding:14px 14px 14px 32px; border:1px solid #e2e8f0; border-radius:12px; font-weight:800; font-size:24px; background:#f8fafc; color:#1e293b; outline:none;" placeholder="0.00" step="0.01">
            </div>
        </div>
        <div style="display:flex; gap:12px;">
            <button onclick="closePriceModal()" style="flex:1; padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#64748b; font-weight:700; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#1e293b'" onmouseout="this.style.background='#f8fafc';this.style.color='#64748b'">Cancel</button>
            <button onclick="confirmPrice()" style="flex:1; padding:14px; border:none; border-radius:12px; background:#4f46e5; color:white; font-weight:700; cursor:pointer; box-shadow:0 10px 15px -3px rgba(79,70,229,0.3); transition:all 0.2s;" onmouseover="this.style.background='#4338ca';this.style.transform='translateY(-1px)'" onmouseout="this.style.background='#4f46e5';this.style.transform='translateY(0)'">Add Item</button>
        </div>
    </div>
</div>

<script>
window.POS_BRANCHES = <?php echo json_encode(array_map(function($b){return ['id'=>(int)$b['id'],'name'=>$b['branch_name']];}, $branches ?: [])); ?>;

let products = [];
let cart = [];
let currentTotal = 0;

document.addEventListener('DOMContentLoaded', () => {
    fetchProducts();
    refreshCart(); // Initialize cart from session
    const searchEl = document.getElementById('pos-search');
    const catEl = document.getElementById('pos-category');
    if (searchEl) searchEl.addEventListener('input', renderProducts);
    if (catEl) catEl.addEventListener('change', renderProducts);
});

async function syncedCartAction(action, payload = {}) {
    console.log('syncedCartAction:', action, payload);
    try {
        const response = await fetch('/printflow/staff/api/pos_cart_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...payload })
        });
        const data = await response.json();
        console.log('syncedCartAction Response:', data);
        if (data.success) {
            cart = data.cart || [];
            console.log('Updated local cart:', cart);
            renderCart();
            return { success: true };
        } else {
            console.error('syncedCartAction Error:', data.message);
            alert(data.message || 'Action failed');
            return { success: false, message: data.message };
        }
    } catch (e) {
        console.error('Cart Action Error:', e);
        alert('Network error while updating cart.');
        return { success: false };
    }
}

async function refreshCart() {
    await syncedCartAction('get');
}

async function fetchProducts() {
    try {
        const res = await fetch('/printflow/staff/api/get_products.php');
        const data = await res.json();
        if(data.success) {
            products = data.products || [];
            const grid = document.getElementById('pos-products-grid');
            if (grid) renderProducts();
        } else {
            const grid = document.getElementById('pos-products-grid');
            if (grid) grid.innerHTML = '<p style="color:red; text-align:center; padding:20px;">Failed to load products.</p>';
        }
    } catch(e) {
        const grid = document.getElementById('pos-products-grid');
        if (grid) grid.innerHTML = '<p style="color:red; text-align:center; padding:20px;">Network error.</p>';
    }
}

// POS Dynamic Requirements Config - Synced with customer service forms (excluding Notes)
function getBranchField() {
    const branches = (window.POS_BRANCHES || []).map(b => ({ value: b.id, label: b.name }));
    const hasBranches = branches && branches.length > 0;
    return { label: 'Branch *', type: 'select', name: 'branch_id', options: branches, required: hasBranches };
}
const serviceRequirements = {
    'Tarpaulin': [
        getBranchField,
        { label: 'Dimensions (ft)', type: 'dimensions_ft', name: 'dimensions', subNames: ['width', 'height'], placeholders: ['Width', 'Height'], required: true },
        { label: 'Finish Type', type: 'select', name: 'finish', options: ['Matte', 'Glossy'] },
        { label: 'Lamination', type: 'select', name: 'lamination', options: ['With Laminate', 'Without Laminate'] },
        { label: 'Eyelets', type: 'select', name: 'with_eyelets', options: ['Yes', 'No'] },
        { label: 'Layout', type: 'select', name: 'layout', options: ['With Layout', 'Without Layout'] },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Needed Date', type: 'date', name: 'needed_date', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ],
    'T-Shirt': [
        getBranchField,
        { label: 'Shirt Source', type: 'select', name: 'shirt_source', options: ['Shop will provide the shirt', 'Customer will provide the shirt'], required: true },
        { label: 'Shirt Type', type: 'select', name: 'shirt_type', options: ['Crew Neck', 'V-Neck', 'Polo', 'Raglan', 'Long Sleeve', 'Others'] },
        { label: 'Shirt Type (if Others)', type: 'text', name: 'shirt_type_other', placeholder: 'Enter custom shirt type', conditionalOn: { field: 'shirt_type', value: 'Others' } },
        { label: 'Shirt Color', type: 'select', name: 'shirt_color', options: ['Black', 'White', 'Red', 'Blue', 'Navy', 'Grey', 'Other'] },
        { label: 'Color (if Other)', type: 'text', name: 'color_other', placeholder: 'Enter custom color', conditionalOn: { field: 'shirt_color', value: 'Other' } },
        { label: 'Sizes', type: 'select_other', name: 'sizes', options: ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'Others'], otherOption: 'Others', otherName: 'sizes_other', otherPlaceholder: 'Enter custom size', disabledWhen: { field: 'shirt_source', value: 'Customer will provide the shirt' } },
        { label: 'Print Placement', type: 'select', name: 'print_placement', options: ['Front Center Print', 'Back Upper Print', 'Left/Right Chest Print', 'Bottom Hem Print', 'Sleeve Print', 'Long Sleeve Arm Print'] },
        { label: 'Lamination', type: 'select', name: 'lamination', options: ['With Laminate', 'Without Laminate'] },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ],
    'Stickers': [
        getBranchField,
        { label: 'Dimensions (W × H, inches)', type: 'text', name: 'size', placeholder: 'e.g. 2x2', required: true },
        { label: 'Finish', type: 'select', name: 'finish', options: ['Glossy', 'Matte'] },
        { label: 'Laminate', type: 'select', name: 'laminate_option', options: ['With Laminate', 'Without Laminate'] },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Needed Date', type: 'date', name: 'needed_date', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ],
    'Decals / Stickers': [
        getBranchField,
        { label: 'Dimensions (W × H, inches)', type: 'text', name: 'size', placeholder: 'e.g. 2x2', required: true },
        { label: 'Finish', type: 'select', name: 'finish', options: ['Glossy', 'Matte'] },
        { label: 'Laminate', type: 'select', name: 'laminate_option', options: ['With Laminate', 'Without Laminate'] },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Needed Date', type: 'date', name: 'needed_date', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ],
    'Glass/Wall': [
        getBranchField,
        { label: 'Dimensions (ft)', type: 'dimensions_ft', name: 'dimensions', subNames: ['width', 'height'], placeholders: ['Width', 'Height'], required: true },
        { label: 'Surface Type', type: 'select', name: 'surface_type', options: ['Glass (Window/Door/Storefront)', 'Wall (Painted/Concrete)', 'Frosted Glass', 'Mirror', 'Acrylic/Panel', 'Others'] },
        { label: 'Surface Type (if Others)', type: 'text', name: 'surface_type_other', placeholder: 'Specify surface type', conditionalOn: { field: 'surface_type', value: 'Others' } },
        { label: 'Lamination', type: 'select', name: 'lamination', options: ['With Laminate', 'Without Laminate'] },
        { label: 'Installation', type: 'select', name: 'installation', options: ['Without Installation', 'With Installation'] },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Needed Date', type: 'date', name: 'needed_date', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ],
    'Transparent Stickers': [
        getBranchField,
        { label: 'Surface Application', type: 'select', name: 'surface_application', options: ['Glass (Window/Door/Storefront)', 'Plastic / Acrylic', 'Metal', 'Smooth Painted Wall', 'Mirror', 'Others'] },
        { label: 'Surface (if Others)', type: 'text', name: 'surface_other', placeholder: 'Specify surface', conditionalOn: { field: 'surface_application', value: 'Others' } },
        { label: 'Dimensions (e.g. 2x2, 3x4 ft)', type: 'text', name: 'dimensions', placeholder: 'e.g. 2x2', required: true },
        { label: 'Layout', type: 'select', name: 'layout', options: ['With Layout', 'Without Layout'] },
        { label: 'Lamination', type: 'select', name: 'lamination', options: ['With Laminate', 'Without Laminate'] },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Needed Date', type: 'date', name: 'needed_date', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ],
    'Reflectorized': {
        isDynamic: true,
        base: [
            getBranchField,
            { label: 'Product Type *', type: 'select', name: 'product_type', options: ['Subdivision / Gate Pass (Vehicle Sticker)', 'Plate Number / Temporary Plate', 'Custom Reflectorized Sign'], required: true, dynamicTrigger: true }
        ],
        'Subdivision / Gate Pass (Vehicle Sticker)': [
            { label: 'Subdivision / Company Name *', type: 'text', name: 'gate_pass_subdivision', placeholder: 'GREEN VALLEY SUBDIVISION', required: true },
            { label: 'Gate Pass Number *', type: 'text', name: 'gate_pass_number', placeholder: 'GP-0215', required: true },
            { label: 'Plate Number *', type: 'text', name: 'gate_pass_plate', placeholder: 'ABC 1234', required: true },
            { label: 'Year / Validity *', type: 'text', name: 'gate_pass_year', placeholder: 'VALID UNTIL: 2026', required: true },
            { label: 'Vehicle Type', type: 'select', name: 'gate_pass_vehicle_type', options: [{ value: '', label: 'Select' }, { value: 'Car', label: 'Car' }, { value: 'Motorcycle', label: 'Motorcycle' }] },
            { label: 'Exact Size (Width × Height)', type: 'text', name: 'dimensions', placeholder: 'e.g. 12 x 18' },
            { label: 'Unit', type: 'select', name: 'unit', options: ['in', 'ft'] },
            { label: 'Needed Date * (dd/mm/yyyy)', type: 'date', name: 'needed_date', required: true },
            { label: 'Upload Design * (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' },
            { label: 'Quantity Required *', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true }
        ],
        'Plate Number / Temporary Plate': [
            { label: 'Material Selection *', type: 'select', name: 'temp_plate_material', options: ['Acrylic', 'Aluminum Sheet', 'Aluminum Coated (Steel)'], required: true },
            { label: 'Plate Number * (must match OR/CR)', type: 'text', name: 'temp_plate_number', placeholder: 'Must match OR/CR', required: true },
            { label: 'TEMPORARY PLATE text', type: 'text', name: 'temp_plate_text', placeholder: 'Auto-displayed on design', defaultValue: 'TEMPORARY PLATE' },
            { label: 'MV File Number', type: 'text', name: 'mv_file_number', placeholder: 'Optional' },
            { label: 'Dealer Name', type: 'text', name: 'dealer_name', placeholder: 'Optional' },
            { label: 'Needed Date * (dd/mm/yyyy)', type: 'date', name: 'needed_date', required: true },
            { label: 'Quantity Required *', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true }
        ],
        'Custom Reflectorized Sign': [
            { label: 'Needed Date * (dd/mm/yyyy)', type: 'date', name: 'needed_date', required: true },
            { label: 'Dimensions *', type: 'select_other', name: 'dimensions', options: ['6 x 12 in', '9 x 12 in', '12 x 18 in', '18 x 24 in', '24 x 36 in'], otherOption: 'Others', otherName: 'dimensions_other', otherPlaceholder: 'e.g. 10 x 14 in', required: true },
            { label: 'Lamination', type: 'select', name: 'laminate_option', options: ['With Lamination', 'Without Lamination'] },
            { label: 'Layout', type: 'select', name: 'layout', options: ['With Layout', 'Without Layout'] },
            { label: 'Material Brand', type: 'select', name: 'material_type', options: ['Kiwalite (Japan Brand)', '3M Brand'] },
            { label: 'Upload Design * (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' },
            { label: 'Quantity Required *', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true }
        ]
    },
    'Sintraboard': [
        getBranchField,
        { label: 'Sintraboard Type', type: 'select', name: 'sintra_type', options: ['Flat Type', '2D Type (with Frame)', 'Standee (Back Stand Support)'], required: true },
        { label: 'Dimensions (e.g. 12 x 18)', type: 'text', name: 'dimensions', placeholder: 'e.g. 12 x 18', required: true },
        { label: 'Unit', type: 'select', name: 'unit', options: ['in', 'ft'] },
        { label: 'Thickness', type: 'select', name: 'thickness', options: ['3mm', '5mm'], required: true },
        { label: 'Lamination', type: 'select', name: 'lamination', options: ['With Lamination', 'Without Lamination'] },
        { label: 'Layout', type: 'select', name: 'layout', options: ['With Layout', 'Without Layout'] },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ],
    'Standees': [
        getBranchField,
        { label: 'Size', type: 'text', name: 'size', placeholder: 'e.g. 22x28 inches', required: true },
        { label: 'With Stand?', type: 'select', name: 'with_stand', options: ['No', 'Yes'] },
        { label: 'Needed Date', type: 'date', name: 'needed_date', required: true },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ],
    'Souvenirs': [
        getBranchField,
        { label: 'Type', type: 'select', name: 'souvenir_type', options: ['Mug', 'Keychain', 'Tote Bag', 'Pen', 'Tumbler', 'T-Shirt', 'Others'] },
        { label: 'Custom Print?', type: 'select', name: 'custom_print', options: ['No', 'Yes – I have a design'] },
        { label: 'Lamination', type: 'select', name: 'lamination', options: ['With Lamination', 'Without Lamination'] },
        { label: 'Needed Date', type: 'date', name: 'needed_date', required: true },
        { label: 'Quantity', type: 'number', name: 'quantity', placeholder: '1', step: '1', required: true },
        { label: 'Upload Design (JPG, PNG, PDF - max 5MB)', type: 'file', name: 'design_file', accept: '.jpg,.jpeg,.png,.pdf' }
    ]
};

function getRequirementsForProduct(productName, category) {
    const term = (productName + ' ' + (category || '')).toLowerCase();
    const svc = productName || category || '';
    if (term.includes('tarpaulin') || term.includes('tarp')) return expandRequirements('Tarpaulin');
    if (term.includes('t-shirt') || term.includes('tshirt')) return expandRequirements('T-Shirt');
    if (term.includes('sticker') || term.includes('decal') || svc === 'Decals / Stickers') return expandRequirements(svc === 'Decals / Stickers' ? 'Decals / Stickers' : 'Stickers');
    if (term.includes('glass') || term.includes('wall')) return expandRequirements('Glass/Wall');
    if (term.includes('transparent')) return expandRequirements('Transparent Stickers');
    if (term.includes('reflectorized') || term.includes('signage')) return expandRequirements('Reflectorized');
    if (term.includes('sintraboard') && !term.includes('standee')) return expandRequirements('Sintraboard');
    if (term.includes('standee')) return expandRequirements('Standees');
    if (term.includes('souvenir')) return expandRequirements('Souvenirs');
    return null;
}
function expandRequirements(key, productType) {
    const raw = serviceRequirements[key];
    if (!raw) return [];
    if (raw.isDynamic) {
        const base = (raw.base || []).map(r => typeof r === 'function' ? r() : r).filter(Boolean);
        if (productType && raw[productType]) {
            return base.concat(raw[productType]);
        }
        return base;
    }
    const arr = Array.isArray(raw) ? raw : [];
    return arr.map(r => typeof r === 'function' ? r() : r).filter(Boolean);
}

function renderProducts() {
    const grid = document.getElementById('pos-products-grid');
    if (!grid) return;
    const searchEl = document.getElementById('pos-search');
    const catEl = document.getElementById('pos-category');
    const search = (searchEl ? searchEl.value : '').toLowerCase();
    const cat = catEl ? catEl.value : '';
    
    grid.innerHTML = '';
    
    const filtered = products.filter(p => {
        const mSearch = p.product_name.toLowerCase().includes(search) || (p.sku && p.sku.toLowerCase().includes(search));
        const mCat = cat === '' || p.category === cat;
        return mSearch && mCat;
    });
    
    if(filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:#94a3b8;">No products found.</div>';
        return;
    }
    
    filtered.forEach(p => {
        const outOfStock = p.stock_quantity <= 0;
        const img = p.product_image ? '/printflow/' + p.product_image : '/printflow/public/assets/images/services/default.png';
        
        const card = document.createElement('div');
        card.className = `pos-card ${outOfStock ? 'no-stock' : ''}`;
        if(!outOfStock) card.onclick = () => addToCart(p);
        
        card.innerHTML = `
            <div class="pos-card-img-container">
                <img src="${img}" class="pos-card-img" onerror="this.onerror=null; this.src='/printflow/public/images/products/README.md'; this.outerHTML='<div style=\\'background:#f1f5f9; height:100%; display:flex; align-items:center; justify-content:center; font-size:32px;\\'>📦</div>';">
                <div class="pos-card-price">₱${parseFloat(p.price).toFixed(2)}</div>
            </div>
            <div class="pos-card-body">
                <div class="pos-card-title">${p.product_name}</div>
                <div class="pos-card-stock">
                    <i class="fas ${outOfStock ? 'fa-times-circle text-red' : 'fa-check-circle text-green'}" style="color:${outOfStock ? '#ef4444' : '#06A1A1'}"></i>
                    ${outOfStock ? 'Out of Stock' : p.stock_quantity + ' available'}
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

async function addToCart(p, overridePrice = null, overrideName = null) {
    const name = overrideName || p.product_name;
    const price = overridePrice !== null ? overridePrice : parseFloat(p.price);
    
    if(p.price == 0 && overridePrice === null) {
        openPriceModal(p);
        return;
    }
    
    await syncedCartAction('add', {
        product_id: p.product_id,
        name: name,
        price: price,
        qty: 1
    });
}

let pendingCustomProduct = null;
let currentCustomRequirements = null;
let posDynamicRequirements = null;
let posDynamicFieldStartIndex = 500;

function renderPosField(container, req, idx, baseStyle) {
    const div = document.createElement('div');
    div.style.display = 'flex';
    div.style.flexDirection = 'column';
    div.style.gap = '4px';
    const reqLabel = req.label || '';
    const reqName = req.name || ('field_' + idx);
    const isOpt = reqLabel.includes('(if ');
    let label = `<label style="font-size:12px; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">${reqLabel}</label>`;
    let inputHtml = '';
    if (req.type === 'dimensions_ft') {
        const subNames = req.subNames || ['width', 'height'];
        const placeholders = req.placeholders || ['Width', 'Height'];
        div.innerHTML = `<label style="font-size:12px; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">${reqLabel}</label>
            <div style="display:flex; align-items:center; gap:8px;">
                <input type="number" id="custom_field_${idx}_0" name="${subNames[0]}" placeholder="${placeholders[0]}" step="0.1" style="${baseStyle}; flex:1;" data-field-name="${subNames[0]}">
                <span style="flex-shrink:0; font-weight:700; color:#94a3b8;">×</span>
                <input type="number" id="custom_field_${idx}_1" name="${subNames[1]}" placeholder="${placeholders[1]}" step="0.1" style="${baseStyle}; flex:1;" data-field-name="${subNames[1]}">
            </div>`;
        div.dataset.fieldType = 'dimensions_ft';
        div.dataset.fieldIndex = String(idx);
    } else if (req.type === 'select_other') {
        const opts = req.options || [];
        const otherOpt = req.otherOption || 'Others';
        const otherName = req.otherName || (reqName + '_other');
        const otherPh = (req.otherPlaceholder || 'Enter custom').replace(/"/g, '&quot;');
        inputHtml = `<select id="custom_field_${idx}" name="${reqName}" style="${baseStyle}" data-field-name="${reqName}" data-other-option="${otherOpt}" data-other-name="${otherName}" onchange="togglePosOtherInput(${idx}, '${otherOpt}', '${otherName}')">`;
        inputHtml += `<option value="">Select...</option>`;
        opts.forEach(opt => {
            const val = (typeof opt === 'object' && opt !== null && 'value' in opt) ? opt.value : opt;
            const lab = (typeof opt === 'object' && opt !== null && 'label' in opt) ? opt.label : opt;
            inputHtml += `<option value="${String(val).replace(/"/g, '&quot;')}">${String(lab).replace(/</g, '&lt;')}</option>`;
        });
        inputHtml += `</select>`;
        inputHtml += `<div id="custom_other_${idx}" style="display:none; margin-top:6px;"><input type="text" id="custom_field_${idx}_other" name="${otherName}" placeholder="${otherPh}" style="${baseStyle}" data-field-name="${otherName}"></div>`;
        div.innerHTML = label + inputHtml;
        div.dataset.fieldType = 'select_other';
        if (req.disabledWhen && req.disabledWhen.field && req.disabledWhen.value) {
            const wrap = document.createElement('div');
            wrap.dataset.disabledWhenField = req.disabledWhen.field;
            wrap.dataset.disabledWhenValue = req.disabledWhen.value;
            wrap.dataset.disabledWhenDisplay = req.disabledWhen.display || 'Provided by Customer';
            const readonlyDiv = document.createElement('div');
            readonlyDiv.className = 'pos-disabled-when-display';
            readonlyDiv.style.cssText = 'display:none; padding:10px 12px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; color:#64748b;';
            readonlyDiv.innerHTML = reqLabel + ': <strong style="color:#475569;">' + (req.disabledWhen.display || 'Provided by Customer') + '</strong>';
            wrap.appendChild(div);
            wrap.appendChild(readonlyDiv);
            container.appendChild(wrap);
            return wrap;
        }
    } else if (req.type === 'select') {
        inputHtml = `<select id="custom_field_${idx}" name="${reqName}" style="${baseStyle}" data-field-name="${reqName}">`;
        inputHtml += `<option value="">Select...</option>`;
        const opts = req.options || [];
        opts.forEach(opt => {
            const val = (typeof opt === 'object' && opt !== null && 'value' in opt) ? opt.value : opt;
            const lab = (typeof opt === 'object' && opt !== null && 'label' in opt) ? opt.label : opt;
            inputHtml += `<option value="${String(val).replace(/"/g, '&quot;')}">${String(lab).replace(/</g, '&lt;')}</option>`;
        });
        inputHtml += `</select>`;
        div.innerHTML = label + inputHtml;
    } else if (req.type === 'file') {
        inputHtml = `<input type="file" id="custom_field_${idx}" name="${reqName}" accept="${(req.accept || '').replace(/"/g, '&quot;')}" style="${baseStyle}" data-field-name="${reqName}">`;
        div.innerHTML = label + inputHtml;
    } else if (req.type === 'date') {
        const minDate = new Date().toISOString().split('T')[0];
        inputHtml = `<input type="date" id="custom_field_${idx}" name="${reqName}" min="${minDate}" style="${baseStyle}" data-field-name="${reqName}">`;
        div.innerHTML = label + inputHtml;
    } else {
        const ph = (req.placeholder || '').replace(/"/g, '&quot;');
        const st = req.step ? ` step="${req.step}"` : '';
        const dv = req.defaultValue ? ` value="${String(req.defaultValue).replace(/"/g, '&quot;')}"` : '';
        inputHtml = `<input type="${req.type || 'text'}" id="custom_field_${idx}" name="${reqName}" placeholder="${ph}"${st}${dv} style="${baseStyle}" data-field-name="${reqName}">`;
        div.innerHTML = label + inputHtml;
    }
    div.dataset.fieldName = reqName;
    if (req.conditionalOn && req.conditionalOn.field && req.conditionalOn.value) {
        const wrap = document.createElement('div');
        wrap.style.display = 'none';
        wrap.dataset.conditionalField = req.conditionalOn.field;
        wrap.dataset.conditionalValue = req.conditionalOn.value;
        wrap.appendChild(div);
        container.appendChild(wrap);
        return wrap;
    }
    container.appendChild(div);
    return div;
}

function renderReflectorizedDynamicFields(productType) {
    const dynContainer = document.getElementById('cm-dynamic-product-fields');
    if (!dynContainer) return;
    dynContainer.innerHTML = '';
    posDynamicRequirements = null;
    if (!productType) return;
    const refl = serviceRequirements['Reflectorized'];
    if (!refl || !refl[productType]) return;
    posDynamicRequirements = refl[productType];
    const baseStyle = 'width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none;';
    posDynamicRequirements.forEach((req, i) => {
        const idx = posDynamicFieldStartIndex + i;
        renderPosField(dynContainer, req, idx, baseStyle);
    });
}

function openCustomModal(product, requirements) {
    pendingCustomProduct = product;
    currentCustomRequirements = requirements;
    posDynamicRequirements = null;
    
    const isReflectorized = (product.category === 'Reflectorized' || product.product_name === 'Reflectorized');
    
    document.getElementById('cm-title').textContent = (product.product_name || product.name) + ' Details';
    const container = document.getElementById('cm-dynamic-fields');
    container.innerHTML = '';
    
    const baseStyle = 'width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none;';
    
    requirements.forEach((req, idx) => {
        const r = typeof req === 'function' ? req() : req;
        if (r) renderPosField(container, r, idx, baseStyle);
    });
    wireUpConditionalFields(container);
    wireUpDisabledWhenFields(container);
    if (isReflectorized) {
        const dynWrap = document.createElement('div');
        dynWrap.id = 'cm-dynamic-product-fields';
        dynWrap.style.marginTop = '12px';
        container.appendChild(dynWrap);
        const ptSelect = container.querySelector('select[name="product_type"]');
        if (ptSelect) {
            ptSelect.addEventListener('change', function() {
                const val = this.value;
                renderReflectorizedDynamicFields(val);
            });
            if (ptSelect.value) renderReflectorizedDynamicFields(ptSelect.value);
        }
    }
    
    // Inject Price Input directly into the form if product price is 0
    const initialPrice = parseFloat(product.price) || 0;
    if (initialPrice === 0) {
        const priceHtml = `
            <div id="cm-price-section" style="margin-top:16px; padding-top:16px; border-top:1px dashed #cbd5e1;">
                <label style="display:block; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; letter-spacing:0.05em;">Negotiated Price *</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 16px; top: 14px; font-weight: 700; color: #94a3b8;">₱</span>
                    <input type="number" id="cm-price-input" style="width:100%; padding:14px 14px 14px 32px; border:1px solid #e2e8f0; border-radius:12px; font-weight:800; font-size:24px; background:#f8fafc; color:#1e293b; outline:none;" placeholder="0.00" step="0.01">
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', priceHtml);
        
        // Ensure scrolling works properly
        container.style.maxHeight = '55vh'; 
    }
    
    document.getElementById('custom-modal-overlay').style.display = 'flex';
}

function closeCustomModal() {
    document.getElementById('custom-modal-overlay').style.display = 'none';
    pendingCustomProduct = null;
    currentCustomRequirements = null;
    posDynamicRequirements = null;
}
function togglePosOtherInput(idx, otherOption, otherName) {
    const sel = document.getElementById('custom_field_' + idx);
    const wrap = document.getElementById('custom_other_' + idx);
    if (sel && wrap) {
        wrap.style.display = (sel.value === otherOption) ? 'block' : 'none';
        if (sel.value !== otherOption) {
            const inp = wrap.querySelector('input');
            if (inp) inp.value = '';
        }
    }
}

function wireUpConditionalFields(container) {
    if (!container) return;
    container.querySelectorAll('[data-conditional-field]').forEach(wrap => {
        const fieldName = wrap.dataset.conditionalField;
        const showValue = wrap.dataset.conditionalValue;
        const parentSelect = container.querySelector('select[name="' + fieldName + '"]');
        const input = wrap.querySelector('input');
        if (!parentSelect) return;
        function sync() {
            const show = parentSelect.value === showValue;
            wrap.style.display = show ? 'block' : 'none';
            if (!show && input) input.value = '';
        }
        parentSelect.addEventListener('change', sync);
        sync();
    });
}

function wireUpDisabledWhenFields(container) {
    if (!container) return;
    container.querySelectorAll('[data-disabled-when-field]').forEach(wrap => {
        const fieldName = wrap.dataset.disabledWhenField;
        const triggerValue = wrap.dataset.disabledWhenValue;
        const triggerSelect = container.querySelector('select[name="' + fieldName + '"]');
        const editableChild = wrap.firstElementChild;
        const readonlyChild = wrap.querySelector('.pos-disabled-when-display');
        if (!triggerSelect || !editableChild || !readonlyChild) return;
        function sync() {
            const disabled = triggerSelect.value === triggerValue;
            editableChild.style.display = disabled ? 'none' : 'flex';
            readonlyChild.style.display = disabled ? 'block' : 'none';
            const sel = editableChild.querySelector('select');
            const otherWrap = editableChild.querySelector('[id^="custom_other_"]');
            const otherInp = otherWrap ? otherWrap.querySelector('input') : null;
            if (sel) sel.disabled = disabled;
            if (disabled) {
                if (sel) sel.value = '';
                if (otherInp) otherInp.value = '';
                if (otherWrap) otherWrap.style.display = 'none';
            }
        }
        triggerSelect.addEventListener('change', sync);
        sync();
    });
}

function collectRequirementsToCustomization(requirements, startIdx, customization, validation) {
    if (!requirements) return;
    requirements.forEach((req, i) => {
        const resolvedReq = typeof req === 'function' ? req() : req;
        if (!resolvedReq) return;
        req = resolvedReq;
        const idx = startIdx + i;
        const name = req.name || ('field_' + idx);
        let val = null;
        
        if (req.type === 'dimensions_ft') {
            const w = document.getElementById(`custom_field_${idx}_0`);
            const h = document.getElementById(`custom_field_${idx}_1`);
            if (w && h) {
                const wv = w.value, hv = h.value;
                if (req.required && (!wv || !hv)) validation.valid = false;
                if (wv) customization['width'] = wv;
                if (hv) customization['height'] = hv;
            }
            return;
        }
        if (req.type === 'select_other') {
            const sel = document.getElementById(`custom_field_${idx}`);
            const other = document.getElementById(`custom_field_${idx}_other`);
            const otherOpt = req.otherOption || 'Others';
            if (req.disabledWhen && req.disabledWhen.field) {
                const overlay = document.getElementById('custom-modal-overlay');
                const trigger = overlay ? overlay.querySelector('select[name="' + req.disabledWhen.field + '"]') : null;
                if (trigger && trigger.value === req.disabledWhen.value) {
                    customization[name] = 'Provided by Customer';
                    return;
                }
            }
            if (sel) {
                val = sel.value;
                if (val === otherOpt && other && other.value) {
                    val = other.value;
                } else if (val === otherOpt && req.required) {
                    validation.valid = false;
                    return;
                }
                if (req.required && !val) validation.valid = false;
                if (val) customization[name] = val;
            }
            return;
        }
        
        const el = document.getElementById(`custom_field_${idx}`);
        if (!el) return;
        val = el.value;
        if (req.type === 'file' && el.files && el.files.length > 0) {
            val = el.files[0].name;
        }
        if (req.required && !val) validation.valid = false;
        if (val) customization[name] = val;
    });
}

async function confirmCustomization() {
    if (!pendingCustomProduct || !currentCustomRequirements) return;
    
    const customization = {};
    const validation = { valid: true };
    
    collectRequirementsToCustomization(currentCustomRequirements, 0, customization, validation);
    if (posDynamicRequirements) {
        collectRequirementsToCustomization(posDynamicRequirements, posDynamicFieldStartIndex, customization, validation);
    }
    
    if (pendingCustomProduct.category === 'Reflectorized' || pendingCustomProduct.product_name === 'Reflectorized') {
        customization.service_type = 'Reflectorized Signage';
        if (!customization.product_type) {
            validation.valid = false;
        }
    }
    
    if (!validation.valid) {
        alert('Please complete all required fields (marked *) before proceeding.');
        return;
    }
    
    let price = parseFloat(pendingCustomProduct.price) || 0;
    if (price === 0) {
        const pInput = document.getElementById('cm-price-input');
        if (pInput) {
            price = parseFloat(pInput.value);
            if (isNaN(price) || price <= 0) {
                alert('Please enter a valid negotiated price.');
                pInput.focus();
                return;
            }
        } else {
            // Fallback if input somehow didn't render
            const p = pendingCustomProduct;
            closeCustomModal();
            openPriceModal(p, false, customization);
            return;
        }
    }
    
    const result = await syncedCartAction('add', {
        product_id: pendingCustomProduct.product_id,
        name: pendingCustomProduct.product_name || pendingCustomProduct.name,
        price: price,
        qty: 1,
        customization: customization
    });

    if (result.success) {
        closeCustomModal();
    }
}

let pendingProduct = null;
let isOtherService = false;
let pendingCustomization = null;

function openPriceModal(p, isOther = false, customization = null) {
    pendingProduct = p;
    isOtherService = isOther;
    pendingCustomization = customization || null;
    
    document.getElementById('pm-title').textContent = isOther ? 'Custom Service' : 'Set Service Price';
    document.getElementById('pm-name-group').style.display = isOther ? 'block' : 'none';
    document.getElementById('pm-name-input').value = isOther ? '' : (p.product_name || p.name || '');
    document.getElementById('pm-price-input').value = p.price > 0 ? p.price : '';
    document.getElementById('price-modal-overlay').style.display = 'flex';
    
    const focusEl = isOther ? 'pm-name-input' : 'pm-price-input';
    setTimeout(() => document.getElementById(focusEl).focus(), 100);
}

function closePriceModal() {
    document.getElementById('price-modal-overlay').style.display = 'none';
    pendingProduct = null;
    isOtherService = false;
    pendingCustomization = null;
}

function confirmPrice() {
    const name = document.getElementById('pm-name-input').value.trim();
    const price = parseFloat(document.getElementById('pm-price-input').value);
    
    if (isOtherService && !name) return alert('Please enter a service name.');
    if (isNaN(price) || price < 0) return alert('Please enter a valid price.');
    
    addToCartWithCustomization(pendingProduct, price, name, pendingCustomization);
    closePriceModal();
}

async function addToCartWithCustomization(p, price, name, customization) {
    const itemName = name || p.product_name || p.name;
    await syncedCartAction('add', {
        product_id: p.product_id,
        name: itemName,
        price: price,
        qty: 1,
        customization: customization
    });
}

function setActiveService(btn) {
    document.querySelectorAll('.pos-services-grid .service-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    setTimeout(() => btn && btn.classList.remove('active'), 400);
}

function addQuickService(serviceName) {
    // Mapping for UI button names to DB category names
    const categoryMap = {
        'Decals / Stickers': 'Decals & Stickers',
        'T-Shirt': 'Apparel',
        'Glass/Wall': 'Glass/Wall',
        'Transparent': 'Transparent Stickers',
        'Sintraboard': 'Sintraboard',
        'Standees': 'Standees',
        'Souvenirs': 'Souvenirs',
        'Reflectorized': 'Reflectorized'
    };
    
    const dbCategory = categoryMap[serviceName] || serviceName;
    
    // Look for exact category match first, then partial product name match
    let p = products.find(prod => 
        prod.category === dbCategory || 
        prod.product_name.toLowerCase().includes(serviceName.toLowerCase()) ||
        prod.product_name.toLowerCase().includes(dbCategory.toLowerCase())
    );
    if(!p) p = products.find(prod => prod.category.includes(serviceName));

    if(p) {
        const reqs = getRequirementsForProduct(p.product_name, p.category);
        if (reqs) {
            openCustomModal(p, reqs);
        } else {
            addToCart(p);
        }
    } else {
        // Create a temporary product object if not found in catalog
        const fallback = { product_id: 21, product_name: serviceName, category: serviceName, price: 0, stock_quantity: null };
        const reqs = getRequirementsForProduct(serviceName, serviceName);
        if (reqs) {
             openCustomModal(fallback, reqs);
        } else {
             addToCart(fallback);
        }
    }
}

function addOtherService() {
    const otherBase = { product_id: 21, product_name: 'Other', category: 'Other', price: 0, stock_quantity: null };
    openPriceModal(otherBase, true);
}

async function updateQtyByCartIndex(index, delta) {
    const item = cart[index];
    if(!item) return;
    let newQty = parseInt(item.qty) + delta;
    if (newQty < 1) newQty = 1;
    if (newQty > 100) {
        alert("Maximum quantity per item is 100.");
        newQty = 100;
    }
    await syncedCartAction('update', { index, qty: newQty });
}

async function removeByCartIndex(index) {
    await syncedCartAction('remove', { index });
}

async function clearCart() {
    if(cart.length > 0 && confirm('Clear current order?')) {
        await syncedCartAction('clear');
        document.getElementById('pos-tendered').value = '';
    }
}

function renderCart() {
    const cont = document.getElementById('pos-cart-items');
    currentTotal = 0;
    
    if(cart.length === 0) {
        cont.innerHTML = `<div class="pos-empty-state"><i class="fas fa-shopping-basket"></i><p>Cart is empty</p></div>`;
    } else {
        cont.innerHTML = '';
        cart.forEach((item, index) => {
            const rowTotal = item.price * item.qty;
            currentTotal += rowTotal;
            const div = document.createElement('div');
            div.className = 'pos-cart-item';
            
            let customHtml = '';
            if (item.customization) {
                const parts = [];
                for (const [key, val] of Object.entries(item.customization)) {
                    if(val) parts.push(`${key}: ${val}`);
                }
                if (parts.length > 0) {
                    customHtml = `<div style="font-size:11px; color:#64748b; margin-top:2px; line-height:1.2; word-break:break-word; max-height: 48px; overflow-y: auto;">${parts.join(' | ')}</div>`;
                }
            }
            
            div.innerHTML = `
                <div class="pos-item-details" style="flex:1;">
                    <div class="pos-item-name">${item.name}</div>
                    <div class="pos-item-price" style="margin-top:2px;">₱${item.price.toFixed(2)}</div>
                </div>
                <div class="pos-item-controls">
                    <button class="pos-qty-btn" style="font-size:16px; line-height:1; font-weight:bold;" onclick="updateQtyByCartIndex(${index}, -1)">&minus;</button>
                    <input class="pos-qty-val" value="${item.qty}" readonly>
                    <button class="pos-qty-btn" style="font-size:16px; line-height:1; font-weight:bold;" onclick="updateQtyByCartIndex(${index}, 1)">&plus;</button>
                </div>
                <div class="pos-item-total" style="width:70px; text-align:right;">₱${rowTotal.toFixed(2)}</div>
                <button class="pos-item-remove" style="font-size:18px; line-height:1; font-weight:bold;" onclick="removeByCartIndex(${index})">&times;</button>
            `;
            cont.appendChild(div);
        });
    }
    
    const fTotal = '₱' + currentTotal.toFixed(2);
    document.getElementById('pos-subtotal').textContent = fTotal;
    document.getElementById('pos-total').textContent = fTotal;
    
    calculateChange();
    updateCheckoutState();
}

// Handlers are used above in renderCart via 'index' directly


// Handlers are used above in renderCart via 'index' directly


function toggleReferenceField() {
    const pm = document.getElementById('pos-payment-method').value;
    const refGroup = document.getElementById('reference-group');
    if (pm !== 'Cash') {
        refGroup.style.display = 'flex';
    } else {
        refGroup.style.display = 'none';
        document.getElementById('pos-reference').value = '';
    }
    updateCheckoutState();
}

function calculateChange() {
    if(currentTotal === 0) {
        document.getElementById('pos-change').textContent = '₱0.00';
        return;
    }
    const tenderedInput = document.getElementById('pos-tendered');
    let tendered = parseFloat(tenderedInput.value) || 0;
    
    if (tendered > 1000000) {
        tendered = 1000000;
        tenderedInput.value = tendered;
    }
    
    let change = tendered - currentTotal;
    if (change < 0) change = 0; // Must never be negative on display
    
    const changeEl = document.getElementById('pos-change');
    changeEl.textContent = `₱${change.toFixed(2)}`;
    changeEl.style.color = (tendered < currentTotal && tendered > 0) ? '#ef4444' : '#06A1A1';
    
    updateCheckoutState();
}

function updateCheckoutState() {
    const btn = document.getElementById('pos-checkout-btn');
    const icon = document.getElementById('checkout-icon');
    const text = document.getElementById('checkout-text');
    
    if(cart.length === 0) {
        btn.disabled = true;
        icon.className = 'fas fa-lock';
        text.textContent = 'Select Items';
        return;
    }
    
    let canCheckout = true;
    let message = 'Complete Sale';
    
    const pm = document.getElementById('pos-payment-method').value;
    const ref = document.getElementById('pos-reference').value.trim();
    
    if (pm !== 'Cash' && !ref) {
        canCheckout = false;
        message = 'Enter Ref Number';
    }
    
    const tendered = parseFloat(document.getElementById('pos-tendered').value) || 0;
    if (tendered < currentTotal || tendered > 1000000) {
        canCheckout = false;
        if (message === 'Complete Sale') message = 'Enter Valid Amount';
    }
    
    btn.disabled = !canCheckout;
    icon.className = canCheckout ? 'fas fa-check-circle' : 'fas fa-lock';
    text.textContent = message;
}

async function processCheckout() {
    if(cart.length === 0) return;
    
    const pm = document.getElementById('pos-payment-method').value;
    const ref = document.getElementById('pos-reference').value.trim();
    if (pm !== 'Cash' && !ref) {
        alert("Reference number is required for " + pm);
        return;
    }
    
    const tendered = parseFloat(document.getElementById('pos-tendered').value) || 0;
    if (tendered < currentTotal || tendered > 1000000) {
        alert("Amount tendered must be at least ₱" + currentTotal.toFixed(2) + " and not exceed ₱1,000,000.");
        return;
    }
    
    const changeAmount = (tendered - currentTotal).toFixed(2);
    if(!confirm(`Confirm payment of ₱${currentTotal.toFixed(2)} using ${pm}?\nChange due: ₱${changeAmount}\n\nProceed to finish the transaction?`)) {
        return;
    }
    
    const btn = document.getElementById('pos-checkout-btn');
    btn.disabled = true;
    document.getElementById('checkout-icon').className = 'fas fa-spinner fa-spin';
    document.getElementById('checkout-text').textContent = 'Processing...';
    
    const payload = {
        action: 'walkin_checkout',
        customer_id: document.getElementById('pos-customer').value,
        payment_method: pm,
        reference_number: ref,
        amount_tendered: tendered,
        items: cart.map(i => ({ id: i.product_id, qty: i.qty, price: i.price, name: i.name || null, customization: i.customization || null }))
    };
    
    try {
        const res = await fetch('/printflow/staff/api/pos_checkout.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if(data.success) {
            alert('Sale Completed! Order ID: ' + data.order_id);
            await syncedCartAction('clear');
            document.getElementById('pos-customer').value = 'guest';
            document.getElementById('pos-tendered').value = '';
            document.getElementById('pos-reference').value = '';
            document.getElementById('pos-payment-method').value = 'Cash';
            toggleReferenceField();
            fetchProducts();
        } else {
            alert('Checkout failed: ' + (data.message || 'Error'));
        }
    } catch(e) {
        alert('Network error.');
    } finally {
        updateCheckoutState();
    }
}

function openNewCustomerModal() {
    document.getElementById('customer-modal').style.display = 'flex';
}
function closeCustomerModal() {
    document.getElementById('customer-modal').style.display = 'none';
}
async function saveCustomer() {
    const first = document.getElementById('nc-first').value.trim();
    const last = document.getElementById('nc-last').value.trim();
    if(!first || !last) return alert('First and Last name required.');
    
    document.getElementById('nc-save-btn').textContent = 'Saving...';
    try {
        const res = await fetch('/printflow/staff/api/pos_add_customer.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                first_name: first, last_name: last,
                contact_number: document.getElementById('nc-phone').value.trim()
            })
        });
        const data = await res.json();
        if(data.success) {
            const sel = document.getElementById('pos-customer');
            const opt = document.createElement('option');
            opt.value = data.customer_id;
            opt.textContent = `${first} ${last}`;
            sel.appendChild(opt);
            sel.value = data.customer_id;
            closeCustomerModal();
            document.getElementById('nc-first').value = '';
            document.getElementById('nc-last').value = '';
            document.getElementById('nc-phone').value = '';
        } else {
            alert('Failed: ' + data.message);
        }
    } catch(e) {
        alert('Error.');
    } finally {
        document.getElementById('nc-save-btn').textContent = 'Save Customer';
    }
}

// Expose key handlers globally for reliability (Turbo/SPA compatibility)
window.confirmCustomization = confirmCustomization;
window.closeCustomModal = closeCustomModal;
window.confirmPrice = confirmPrice;
window.closePriceModal = closePriceModal;
window.processCheckout = processCheckout;
window.addQuickService = addQuickService;
window.addToCart = addToCart;
window.togglePosOtherInput = togglePosOtherInput;
window.updateQtyByCartIndex = updateQtyByCartIndex;
window.removeByCartIndex = removeByCartIndex;
window.clearCart = clearCart;
</script>

</body>
</html>
