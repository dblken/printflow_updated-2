<?php
/**
 * AJAX API to Add Souvenirs Order to Cart/Session for Review
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

header('Content-Type: application/json');

$requiredMsg = 'This field is required';

$service_price = 0;
$_s_row = db_query("SELECT price FROM services WHERE customer_link LIKE '%order_souvenirs%' LIMIT 1");
if(!empty($_s_row)) { 
    $service_price = (float)$_s_row[0]['price'];
}

if (!is_logged_in() || !is_customer()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$fields = $_POST;
$branch_id_raw = trim($fields['branch_id'] ?? '');
$branch_id = $branch_id_raw === '' ? 0 : (int)$branch_id_raw;
$souvenir_type = trim($fields['souvenir_type'] ?? '');
$souvenir_type_other = trim($fields['souvenir_type_other'] ?? '');
$allowed_souvenir_types = ['Mug', 'Keychain', 'Tote Bag', 'Pen', 'Tumbler', 'T-Shirt', 'Others'];
$needed_date = trim($fields['needed_date'] ?? '');
$lamination = trim($fields['lamination'] ?? '');
$quantity = (int)($fields['quantity'] ?? 1);
$custom_print = trim($fields['custom_print'] ?? '');
$notes = trim($fields['notes'] ?? '');

if ($branch_id < 1) {
    echo json_encode(['success' => false, 'message' => $requiredMsg]);
    exit;
}

if (!in_array($custom_print, ['Yes', 'No'], true)) {
    echo json_encode(['success' => false, 'message' => $requiredMsg]);
    exit;
}

if (!in_array($lamination, ['With Lamination', 'Without Lamination'], true)) {
    echo json_encode(['success' => false, 'message' => $requiredMsg]);
    exit;
}

if (!in_array($souvenir_type, $allowed_souvenir_types, true) || empty($needed_date) || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => $requiredMsg]);
    exit;
}

if ($souvenir_type === 'Others') {
    $otherLen = function_exists('mb_strlen') ? mb_strlen($souvenir_type_other) : strlen($souvenir_type_other);
    if ($otherLen < 1 || $otherLen > 50) {
        echo json_encode(['success' => false, 'message' => $requiredMsg]);
        exit;
    }
}

$souvenir_type_display = ($souvenir_type === 'Others') ? $souvenir_type_other : $souvenir_type;

$design_tmp_path = null;
$design_name = null;
$design_mime = null;

if ($custom_print === 'Yes') {
    if (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => $requiredMsg]);
        exit;
    }
    
    $valid = service_order_validate_file($_FILES['design_file']);
    if (!$valid['ok']) {
        echo json_encode(['success' => false, 'message' => 'Design upload error: ' . $valid['error']]);
        exit;
    }
    
    $tmp_dir = service_order_temp_dir();
    $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
    $tmp_filename = uniqid('souv_tmp_') . '.' . $ext;
    $design_tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
    
    if (move_uploaded_file($_FILES['design_file']['tmp_name'], $design_tmp_path)) {
        $design_name = $_FILES['design_file']['name'];
        $design_mime = $valid['mime'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to transform uploaded file.']);
        exit;
    }
}

$item_key = uniqid('item_');
$product_name = 'Souvenir: ' . $souvenir_type_display;

$cart_item = [
    'product_id' => 0,
    'source_page' => 'services',
    'branch_id'  => $branch_id,
    'name' => $product_name,
    'category' => 'Souvenirs',
    'price' => $service_price, // Fetched dynamically from database
    'quantity' => $quantity,
    'customization' => [
        'service_type' => 'Souvenirs',
        'souvenir_type' => $souvenir_type_display,
        'needed_date' => $needed_date,
        'lamination' => $lamination,
        'custom_print' => $custom_print,
        'notes' => $notes
    ],
    'design_tmp_path' => $design_tmp_path,
    'design_name' => $design_name,
    'design_mime' => $design_mime,
    'width' => '',
    'height' => '',
    'thickness' => '',
    'stand_type' => '',
    'cut_type' => '',
    'design_notes' => $notes
];

$_SESSION['cart'][$item_key] = $cart_item;

echo json_encode(['success' => true, 'item_key' => $item_key]);

