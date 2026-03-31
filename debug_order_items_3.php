<?php
require_once __DIR__ . '/includes/db.php';
try {
    $res = db_query("SELECT o.*, b.branch_name FROM orders o LEFT JOIN branches b ON o.branch_id = b.id LIMIT 1");
    if ($res !== false) {
        echo "SUCCESS! " . print_r($res[0], true);
    } else {
        echo "FAILED!";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
