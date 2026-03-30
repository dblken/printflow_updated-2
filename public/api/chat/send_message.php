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
    $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, read_receipt)
            VALUES (?, ?, ?, ?, 'text', 0)";
    if (db_execute($sql, 'isis', [$order_id, $db_sender, $user_id, $message])) {
        $messages_sent++;
    }
}

// 2. Handle multiple images
if (isset($_FILES['image'])) {
    $files = $_FILES['image'];
    $is_array = is_array($files['name']);
    $count = $is_array ? count($files['name']) : 1;

    for ($i = 0; $i < $count; $i++) {
        $error = $is_array ? $files['error'][$i] : $files['error'];
        if ($error !== UPLOAD_ERR_OK) {
            continue;
        }

        $single_file = [
            'name'     => $is_array ? $files['name'][$i] : $files['name'],
            'type'     => $is_array ? $files['type'][$i] : $files['type'],
            'tmp_name' => $is_array ? $files['tmp_name'][$i] : $files['tmp_name'],
            'error'    => $error,
            'size'     => $is_array ? $files['size'][$i] : $files['size'],
        ];

        $upload = upload_file($single_file, ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'chat');
        if (!($upload['success'] ?? false)) {
            continue;
        }

        $image_path = (string)$upload['file_path'];
        $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, image_path, read_receipt)
                VALUES (?, ?, ?, '', 'image', ?, 0)";
        if (db_execute($sql, 'isis', [$order_id, $db_sender, $user_id, $image_path])) {
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
