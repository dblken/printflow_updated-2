<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_FILES['voice'])) {
    echo json_encode(['success' => false, 'error' => 'No voice data received']);
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

$file = $_FILES['voice'];
$user_id = get_user_id();
$user_type = get_user_type();
$db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';

// Validate size (max ~10MB)
if ($file['size'] > 10000000) {
    echo json_encode(['success' => false, 'error' => 'File too large (Max 10MB)']);
    exit();
}

// Ensure directory exists - use chat_media which is known to work
$upload_dir = __DIR__ . '/../../../uploads/chat_media/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Could not create upload directory']);
        exit();
    }
}

$filename = uniqid() . ".webm";
$target_path = $upload_dir . $filename;
$relative_path = '/printflow/uploads/chat_media/' . $filename;

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Save to DB
    // Use message_type = 'voice' (just updated enum)
    $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, message_file, file_type, read_receipt)
            VALUES (?, ?, ?, '', 'voice', ?, 'none', 0)";
    
    // Fallback if voice type is rejected (though ALTER should have worked)
    // Actually we keep it as 'voice' since the ALTER worked.
    
    if (db_execute($sql, 'isss', [$order_id, $db_sender, $user_id, $relative_path])) {
        echo json_encode(['success' => true, 'file' => $relative_path]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database insertion failed: ' . ($conn->error ?? 'unknown')]);
    }
} else {
    $error_msg = error_get_last()['message'] ?? 'Check PHP upload limits or folder permissions';
    echo json_encode(['success' => false, 'error' => 'Failed to save recording: ' . $error_msg]);
}
