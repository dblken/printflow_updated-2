<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/ensure_order_messages.php';

// Prevent accidental output (notices, etc.) from breaking JSON
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$reply_id = isset($_POST['reply_id']) && (int)$_POST['reply_id'] > 0 ? (int)$_POST['reply_id'] : null;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

// Map Admin/Manager/Staff to 'Staff'
$db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';
$messages_sent = 0;

// 1. Handle text message
if ($message !== '') {
    $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, read_receipt, reply_id)
            VALUES (?, ?, ?, ?, 'text', 0, ?)";
    if (db_execute($sql, 'isssi', [$order_id, $db_sender, $user_id, $message, $reply_id])) {
        $messages_sent++;
    }
}

// 2. Handle multiple files (images/videos)
if (isset($_FILES['image'])) {
    $files = $_FILES['image'];
    $is_array = is_array($files['name']);
    $count = $is_array ? count($files['name']) : 1;

    for ($i = 0; $i < $count; $i++) {
        $error = $is_array ? $files['error'][$i] : $files['error'];
        if ($error !== UPLOAD_ERR_OK) continue;

        $name = $is_array ? $files['name'][$i] : $files['name'];
        $single_file = [
            'name'     => $name,
            'type'     => $is_array ? $files['type'][$i] : $files['type'],
            'tmp_name' => $is_array ? $files['tmp_name'][$i] : $files['tmp_name'],
            'error'    => $error,
            'size'     => $is_array ? $files['size'][$i] : $files['size'],
        ];

        // Process file (up to 50MB) 
        // We use the 'chat' folder destination, let's keep allowed extensions explicit.
        $upload = upload_file($single_file, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'], 'chat_media', null, 50 * 1024 * 1024);
        if (!($upload['success'] ?? false)) continue;

        $image_path = (string)$upload['file_path'];
        $ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
        $file_type = in_array($ext, ['mp4', 'webm', 'mov']) ? 'video' : 'image';

        $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, image_path, file_type, file_path, message_file, file_name, file_size, read_receipt, reply_id)
                VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, 0, ?)";
        
        // For backwards compatibility we still update message_type and image_path. 
        // message_type = 'image', but we have file_type = 'video' natively.
        // Actually, let's use message_type = 'image', file_type = 'video'
        
        if (db_execute($sql, 'issssssssii', [
            $order_id, $db_sender, $user_id, 
            'image', // legacy message_type
            $image_path, // legacy image_path
            $file_type, 
            $image_path, 
            $image_path, // message_file
            $name, 
            $single_file['size'], 
            $reply_id
        ])) {
            $messages_sent++;
        }
    }
}

if ($messages_sent === 0) {
    echo json_encode(['success' => false, 'error' => 'No message or images were sent.']);
    exit();
}

// 3. Automated notifications for messages are disabled per user request
// (Reduces notification clutter as users prefer checking the chat sidebar directly)


// Clear accidental output before sending JSON
ob_end_clean();
echo json_encode(['success' => true, 'messages_sent' => $messages_sent]);
?>
