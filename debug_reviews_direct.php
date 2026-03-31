<?php
require_once __DIR__ . '/includes/db.php';
$res = db_query("SELECT id, order_id, user_id, reference_id, review_type, service_type, rating FROM reviews");
$out = "ALL REVIEWS:\n";
foreach($res ?: [] as $r) {
    $out .= "ID:{$r['id']}, REF_ID:{$r['reference_id']}, TYPE:{$r['review_type']}, LABEL:{$r['service_type']}, RATING:{$r['rating']}\n";
}
file_put_contents(__DIR__ . '/reviews_out_direct.txt', $out);
