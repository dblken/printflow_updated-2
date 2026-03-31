<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SELECT id, order_id, user_id, reference_id, review_type, service_type, rating FROM reviews");
echo "ALL REVIEWS:\n";
foreach($res ?: [] as $r) {
    echo "ID:{$r['id']}, REF_ID:{$r['reference_id']}, TYPE:{$r['review_type']}, LABEL:{$r['service_type']}, RATING:{$r['rating']}\n";
}
