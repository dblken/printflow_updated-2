<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Prevent accidental output
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$reaction_type = isset($_POST['reaction_type']) ? trim($_POST['reaction_type']) : '';
$user_id = get_user_id();
$user_type = get_user_type();

if (!$message_id || !$reaction_type) {
    echo json_encode(['success' => false, 'error' => 'Missing message ID or reaction']);
    exit();
}

$db_sender = ($user_type === 'Customer') ? 'Customer' : 'Staff';

// Check if reaction exists
$sql_check = "SELECT reaction_id, reaction_type FROM message_reactions WHERE message_id = ? AND sender = ? AND sender_id = ?";
$existing = db_query($sql_check, 'isi', [$message_id, $db_sender, $user_id]);

if (!empty($existing)) {
    if ($existing[0]['reaction_type'] === $reaction_type) {
        // Remove reaction if same
        db_execute("DELETE FROM message_reactions WHERE reaction_id = ?", 'i', [$existing[0]['reaction_id']]);
    } else {
        // Update reaction
        db_execute("UPDATE message_reactions SET reaction_type = ? WHERE reaction_id = ?", 'si', [$reaction_type, $existing[0]['reaction_id']]);
    }
} else {
    // Insert new reaction
    db_execute("INSERT INTO message_reactions (message_id, sender, sender_id, reaction_type) VALUES (?, ?, ?, ?)", 'isis', [$message_id, $db_sender, $user_id, $reaction_type]);
}

ob_end_clean();
echo json_encode(['success' => true]);
?>
