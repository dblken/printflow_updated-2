<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$service_id = (int)($_GET['service_id'] ?? 0);
if ($service_id <= 0) {
    echo json_encode(['reviews' => [], 'avg' => 0, 'count' => 0]);
    exit;
}

$rows = db_query("
    SELECT r.rating, r.message, r.created_at,
           COALESCE(c.first_name, u.first_name, 'Customer') as first_name,
           COALESCE(c.last_name,  u.last_name,  '')          as last_name
    FROM reviews r
    LEFT JOIN customers c ON c.customer_id = r.customer_id
    LEFT JOIN users u ON u.user_id = r.customer_id
    WHERE r.service_type COLLATE utf8mb4_unicode_ci IN (
        SELECT name COLLATE utf8mb4_unicode_ci FROM services WHERE service_id = ?
    )
    ORDER BY r.created_at DESC
    LIMIT 5
", 'i', [$service_id]) ?: [];

$all = db_query("
    SELECT AVG(rating) as avg, COUNT(*) as cnt
    FROM reviews r
    WHERE r.service_type COLLATE utf8mb4_unicode_ci IN (
        SELECT name COLLATE utf8mb4_unicode_ci FROM services WHERE service_id = ?
    )
", 'i', [$service_id]);

echo json_encode([
    'reviews' => $rows,
    'avg'     => round((float)($all[0]['avg'] ?? 0), 1),
    'count'   => (int)($all[0]['cnt'] ?? 0),
]);
