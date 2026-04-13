<?php
/**
 * Clean Signal Voice Upload
 */
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../includes/auth.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    if (!is_logged_in()) throw new Exception('Unauthorized access.');
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    if (!$order_id) throw new Exception('Missing Order ID.');
    if (!isset($_FILES['voice'])) throw new Exception('No voice data received.');

    $user_id = get_user_id();
    $user_type = get_user_type();
    $db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';

    $file = $_FILES['voice'];
    $upload_dir = __DIR__ . '/../../../uploads/chat_media/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $filename = uniqid() . ".webm";
    $relative_path = '/printflow/uploads/chat_media/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, message_file, file_type, read_receipt)
                VALUES (?, ?, ?, '', 'voice', ?, 'none', 0)";
        
        if (db_execute($sql, 'isss', [$order_id, $db_sender, $user_id, $relative_path])) {
            ob_end_clean();
            echo json_encode(['success' => true, 'file' => $relative_path]);
        } else {
            throw new Exception('Database insertion failed.');
        }
    } else {
        throw new Exception('Failed to save file on server.');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $t) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Critical Error: ' . $t->getMessage()]);
}
