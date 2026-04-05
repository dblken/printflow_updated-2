<?php
/**
 * Process Dynamic Service Order Form Submission
 * Safely handles form data from admin-configured dynamic forms
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/dynamic_form_helpers.php';

require_role('Customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: products.php");
    exit;
}

// CSRF Check
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = "Invalid session. Please refresh and try again.";
    header("Location: products.php");
    exit;
}

$product_id = (int)($_POST['product_id'] ?? 0);
$config_id = (int)($_POST['config_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$branch_id = (int)($_POST['branch_id'] ?? 1);
$action = $_POST['action'] ?? 'add_to_cart';

// Validate product
$product = db_query("SELECT * FROM products WHERE product_id = ? AND status = 'Activated'", 'i', [$product_id]);
if (empty($product)) {
    $_SESSION['error'] = "Product not found.";
    header("Location: products.php");
    exit;
}
$product = $product[0];

// Validate dynamic form config
$config = db_query("SELECT * FROM service_form_configs WHERE config_id = ? AND product_id = ? AND is_active = 1", 'ii', [$config_id, $product_id]);
if (empty($config)) {
    $_SESSION['error'] = "Form configuration not found.";
    header("Location: products.php");
    exit;
}
$config = $config[0];

// Check customer restrictions
$customer_id = get_user_id();
if (is_customer_restricted($customer_id)) {
    $_SESSION['error'] = "Account restricted. Cannot place order.";
    header("Location: order_dynamic.php?product_id=" . $product_id);
    exit;
}

// Collect all form data
$form_data = [];
$fields = get_form_fields($config_id);

foreach ($fields as $field) {
    $field_name = $field['field_name'];
    
    if ($field['field_type'] === 'file') {
        // Handle file uploads
        if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES[$field_name]['tmp_name'];
            $file_name = $_FILES[$field_name]['name'];
            $file_size = $_FILES[$field_name]['size'];
            
            // Validate file size (10MB max)
            if ($file_size > 10 * 1024 * 1024) {
                $_SESSION['error'] = "File too large. Maximum size is 10MB.";
                header("Location: order_dynamic.php?product_id=" . $product_id);
                exit;
            }
            
            // Store temp file
            $tmp_path = tempnam(sys_get_temp_dir(), 'pf_dynamic_');
            $data = file_get_contents($file_tmp);
            file_put_contents($tmp_path, $data);
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            $form_data[$field_name] = [
                'type' => 'file',
                'tmp_path' => $tmp_path,
                'mime' => $mime,
                'name' => $file_name
            ];
        } elseif ($field['is_required']) {
            $_SESSION['error'] = "Please upload required file: " . $field['field_label'];
            header("Location: order_dynamic.php?product_id=" . $product_id);
            exit;
        }
    } elseif ($field['field_type'] === 'checkbox') {
        // Handle checkbox arrays
        $form_data[$field_name] = $_POST[$field_name] ?? [];
    } else {
        // Handle regular fields
        $value = $_POST[$field_name] ?? '';
        if ($field['is_required'] && empty($value)) {
            $_SESSION['error'] = "Please fill required field: " . $field['field_label'];
            header("Location: order_dynamic.php?product_id=" . $product_id);
            exit;
        }
        $form_data[$field_name] = sanitize($value);
    }
}

// Initialize cart if needed
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Create unique cart item key
$item_key = $product_id . '_dynamic_' . time();

// Prepare cart item
$cart_item = [
    'product_id' => $product_id,
    'name' => $product['name'],
    'category' => $product['category'] ?? 'Service',
    'source_page' => 'dynamic_form',
    'branch_id' => $branch_id,
    'price' => $product['base_price'],
    'quantity' => $quantity,
    'image' => '📦',
    'customization' => [
        'Branch_ID' => $branch_id,
        'form_type' => 'dynamic',
        'config_id' => $config_id
    ],
    'dynamic_form_data' => $form_data
];

// Handle file uploads in form data
foreach ($form_data as $key => $value) {
    if (is_array($value) && isset($value['type']) && $value['type'] === 'file') {
        $cart_item['design_tmp_path'] = $value['tmp_path'];
        $cart_item['design_mime'] = $value['mime'];
        $cart_item['design_name'] = $value['name'];
    }
}

// Add to cart
$_SESSION['cart'][$item_key] = $cart_item;

// Redirect based on action
if ($action === 'buy_now') {
    header("Location: order_review.php?item=" . urlencode($item_key));
} else {
    header("Location: cart.php");
}
exit;
