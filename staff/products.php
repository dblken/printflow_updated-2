<?php
/**
 * Staff Products (Inventory) Page
 * PrintFlow - Printing Shop PWA
 * Read-only view for staff
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM products WHERE status = 'Activated'";
$params = [];
$types = '';

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR sku LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Pagination settings
$items_per_page = 15;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Count total items for pagination
$count_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Activated'";
$count_params = [];
$count_types = '';

if (!empty($category)) {
    $count_sql .= " AND category = ?";
    $count_params[] = $category;
    $count_types .= 's';
}

if (!empty($search)) {
    $count_sql .= " AND (name LIKE ? OR sku LIKE ?)";
    $count_params[] = '%' . $search . '%';
    $count_params[] = '%' . $search . '%';
    $count_types .= 'ss';
}

$total_result = db_query($count_sql, $count_types, $count_params);
$total_items = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

$sort = $_GET['sort'] ?? 'az';
$sort_clause = match($sort) {
    'za'      => " ORDER BY name DESC",
    'price_high' => " ORDER BY price DESC",
    'price_low'  => " ORDER BY price ASC",
    'stock_low'  => " ORDER BY stock_quantity ASC",
    default   => " ORDER BY name ASC"
};

$sql .= $sort_clause . " LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$products = db_query($sql, $types, $params);
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC");

$page_title = 'Products & Inventory - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <div>
                <h1 class="page-title">Products & Inventory</h1>
                <p class="page-subtitle">View and monitor items and stock levels</p>
            </div>
        </header>

        <main x-data="{ filterOpen: false, sortOpen: false, hasActiveFilters: <?php echo (!empty($search) || !empty($category)) ? 'true' : 'false'; ?> }">
            <?php
            // Calculate KPIs for products
            $total_products = db_query("SELECT COUNT(*) as count FROM products WHERE status = 'Activated'")[0]['count'] ?? 0;
            $low_stock_count = db_query("SELECT COUNT(*) as count FROM products WHERE status = 'Activated' AND stock_quantity < ?", 'i', [10])[0]['count'] ?? 0;
            $fixed_count = db_query("SELECT COUNT(*) as count FROM products WHERE status = 'Activated' AND product_type = 'fixed'")[0]['count'] ?? 0;
            $variable_count = db_query("SELECT COUNT(*) as count FROM products WHERE status = 'Activated' AND product_type = 'variable'")[0]['count'] ?? 0;
            ?>

            <!-- Standardized KPI Row -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <span class="kpi-label">Total Products</span>
                    <span class="kpi-value"><?php echo number_format($total_products); ?></span>
                    <span class="kpi-sub"><?php echo $fixed_count; ?> fixed, <?php echo $variable_count; ?> variable</span>
                </div>
                <div class="kpi-card rose">
                    <span class="kpi-label">Low Stock</span>
                    <span class="kpi-value"><?php echo $low_stock_count; ?></span>
                    <span class="kpi-sub">Items below threshold (10)</span>
                </div>
                <div class="kpi-card emerald">
                    <span class="kpi-label">In Stock</span>
                    <span class="kpi-value"><?php echo number_format($total_products - $low_stock_count); ?></span>
                    <span class="kpi-sub">Sufficient quantity available</span>
                </div>
                <div class="kpi-card amber">
                    <span class="kpi-label">Inventory Status</span>
                    <span class="kpi-value" style="font-size:18px; line-height:36px;"><?php echo round((($total_products - $low_stock_count) / max(1, $total_products)) * 100); ?>%</span>
                    <span class="kpi-sub">Overall availability health</span>
                </div>
            </div>

            <!-- Inventory List Container -->
            <div class="card">
                <div class="toolbar-container">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">
                        Inventory List
                    </h3>
                    <div class="toolbar-group" style="margin-left: auto;">
    

                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: sortOpen || ('<?php echo $sort; ?>' !== 'az') }" @click="sortOpen = !sortOpen; filterOpen = false">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/></svg>
                                Sort by
                            </button>
                            <div class="dropdown-panel sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <?php
                                $sorts = [
                                    'az'         => 'A → Z',
                                    'za'         => 'Z → A',
                                    'price_high' => 'Price: High to Low',
                                    'price_low'  => 'Price: Low to High',
                                    'stock_low'  => 'Lowest Stock First',
                                ];
                                foreach ($sorts as $key => $label): ?>
                                <a href="products.php?sort=<?php echo urlencode($key); ?><?php echo !empty($category) ? '&category='.urlencode($category) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="sort-option <?php echo $sort === $key ? 'active' : ''; ?>" style="text-decoration:none;">
                                    <?php echo htmlspecialchars($label); ?>
                                    <svg x-show="'<?php echo $sort; ?>' === '<?php echo $key; ?>'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: filterOpen || hasActiveFilters }" @click="filterOpen = !filterOpen; sortOpen = false">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                Filter
                                <template x-if="hasActiveFilters">
                                    <span class="filter-badge"><?php echo (int)!empty($category) + (int)!empty($search); ?></span>
                                </template>
                            </button>

                            <!-- Filter Panel -->
                            <div class="dropdown-panel filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <form id="products-filter-form" method="GET" action="products.php">
                                    <?php if (!empty($sort) && $sort !== 'az'): ?>
                                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                                    <?php endif; ?>
                                    <div class="filter-header">Filter Products</div>
                                    
                                    <!-- Category -->
                                    <div class="filter-section">
                                        <div class="filter-section-head">
                                            <span class="filter-label" style="margin:0;">Category</span>
                                            <button type="button" onclick="document.forms['products-filter-form'].elements['category'].value=''; document.getElementById('products-filter-form').submit()" class="filter-reset-link">Reset</button>
                                        </div>
                                        <select name="category" class="filter-select" onchange="document.getElementById('products-filter-form').submit()">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['category']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Keyword search -->
                                    <div class="filter-section">
                                        <div class="filter-section-head">
                                            <span class="filter-label" style="margin:0;">Keyword search</span>
                                            <button type="button" onclick="document.getElementById('productSearchInput').value=''; document.getElementById('products-filter-form').submit()" class="filter-reset-link">Reset</button>
                                        </div>
                                        <input type="text" id="productSearchInput" name="search" class="filter-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" onchange="document.getElementById('products-filter-form').submit()">
                                    </div>

                                    <div class="filter-footer">
                                        <a href="products.php" class="filter-btn-reset" style="display:flex; align-items:center; justify-content:center; text-decoration:none; width: 100%;">Reset all filters</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Name</th>
                                 <th>Category</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <?php foreach ($products as $product): ?>
                                <tr data-name="<?php echo htmlspecialchars(strtolower($product['name'])); ?>"
                                    data-sku="<?php echo htmlspecialchars(strtolower($product['sku'])); ?>"
                                    data-category="<?php echo htmlspecialchars(strtolower($product['category'])); ?>">
                                    <td style="font-family:monospace; font-size:12px;"><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td style="font-weight:500;"><?php echo htmlspecialchars($product['name']); ?></td>
                                     <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><span class="badge <?php echo $product['product_type'] === 'fixed' ? 'badge-blue' : 'badge-purple'; ?>"><?php echo ucfirst($product['product_type']); ?></span></td>
                                    <td style="font-weight:600;"><?php echo format_currency($product['price']); ?></td>
                                    <td>
                                        <?php if ($product['stock_quantity'] < 10): ?>
                                            <span style="color:#dc2626; font-weight:700;"><?php echo $product['stock_quantity']; ?></span>
                                            <span style="font-size:11px; color:#dc2626; font-weight:600;">LOW</span>
                                        <?php else: ?>
                                            <span style="color:#16a34a;"><?php echo $product['stock_quantity']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo status_badge($product['status'], 'order'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <!-- Pagination -->
            <?php echo get_pagination_links($current_page, $total_pages, ['category' => $category, 'search' => $search]); ?>
        </main>
    </div>
</div>

<script>
const productSearch = document.getElementById('productSearchInput');
const productCategory = document.getElementById('productCategorySelect');
const productsTableBody = document.getElementById('productsTableBody');
const productRows = productsTableBody ? Array.from(productsTableBody.querySelectorAll('tr')) : [];

function filterProductsLocally() {
    const q = (productSearch?.value || '').trim().toLowerCase();
    const cat = (productCategory?.value || '').trim().toLowerCase();

    productRows.forEach((row) => {
        const name = row.getAttribute('data-name') || '';
        const sku = row.getAttribute('data-sku') || '';
        const category = row.getAttribute('data-category') || '';
        const matchesText = q === '' || name.includes(q) || sku.includes(q);
        const matchesCategory = cat === '' || category === cat;
        row.style.display = (matchesText && matchesCategory) ? '' : 'none';
    });
}

if (productSearch) {
    productSearch.addEventListener('input', filterProductsLocally);
}
if (productCategory) {
    productCategory.addEventListener('change', filterProductsLocally);
}
filterProductsLocally();
</script>

</body>
</html>
