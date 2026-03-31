<?php
/**
 * Admin User Details API
 * PrintFlow - Printing Shop PWA
 */
require_once __DIR__ . '/../includes/api_header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

$user = db_query("
    SELECT u.user_id, u.first_name, u.middle_name, u.last_name, u.birthday as dob, u.gender,
           u.email, u.contact_number, u.address, u.role, u.profile_picture, u.id_validation_image,
           u.status, u.branch_id, b.branch_name, u.created_at
    FROM users u 
    LEFT JOIN branches b ON u.branch_id = b.id 
    WHERE u.user_id = ?
", 'i', [$user_id]);

if (empty($user)) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

echo json_encode(['success' => true, 'user' => $user[0]]);
