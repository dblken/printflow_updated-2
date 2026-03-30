<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No customer ID provided']);
    exit;
}

$id = intval($_GET['id']);

try {
    $customer = db_query("SELECT * FROM customers WHERE customer_id = ?", "i", [$id]);

    if (empty($customer)) {
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }
    
    $c = $customer[0];
    
    // Format profile picture path
    $profile_picture = null;
    if (!empty($c['profile_picture'])) {
        $profile_picture = '/printflow/public/assets/uploads/profiles/' . $c['profile_picture'];
    }
    
    // Format Data
    $data = [
        'customer_id' => $c['customer_id'],
        'first_name' => $c['first_name'],
        'middle_name' => $c['middle_name'] ?? '',
        'last_name' => $c['last_name'],
        'email' => $c['email'],
        'contact_number' => $c['contact_number'] ?? '',
        'address' => $c['address'] ?? '',
        'dob' => $c['dob'] ? date('m/d/Y', strtotime($c['dob'])) : '',
        'gender' => $c['gender'] ?? '',
        'created_at' => date('M j, Y', strtotime($c['created_at'])),
        'profile_picture' => $profile_picture,
        'initial' => strtoupper(substr($c['first_name'], 0, 1))
    ];

    echo json_encode(['success' => true, 'customer' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
