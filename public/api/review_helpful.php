<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$review_id = (int)($_POST['review_id'] ?? 0);
if ($review_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid review']);
    exit;
}

// Ensure table exists
global $conn;
$conn->query("CREATE TABLE IF NOT EXISTS review_helpful (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user (review_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$user_id = get_user_id();

// Check if already voted
$existing = db_query("SELECT id FROM review_helpful WHERE review_id = ? AND user_id = ?", 'ii', [$review_id, $user_id]);

if (!empty($existing)) {
    // Toggle off
    db_execute("DELETE FROM review_helpful WHERE review_id = ? AND user_id = ?", 'ii', [$review_id, $user_id]);
    $voted = false;
} else {
    // Toggle on
    db_execute("INSERT INTO review_helpful (review_id, user_id) VALUES (?, ?)", 'ii', [$review_id, $user_id]);
    $voted = true;
}

$count = db_query("SELECT COUNT(*) as cnt FROM review_helpful WHERE review_id = ?", 'i', [$review_id]);
$total = (int)($count[0]['cnt'] ?? 0);

echo json_encode(['success' => true, 'voted' => $voted, 'count' => $total]);
