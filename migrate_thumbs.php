<?php
require 'includes/db.php';
require 'includes/functions.php';

// Fix existing T-SHIRT PRINT order updates
$res = db_query("SELECT message_id, message FROM order_messages WHERE message_type = 'order_update'");
$count = 0;
foreach ($res as $row) {
    $payload = json_decode($row['message'], true);
    if (!$payload) continue;
    
    $p_name = $payload['product_name'] ?? '';
    if ($p_name === 'T-SHIRT PRINT' || $p_name === 'Eunsoyaa') {
        // If it's T-SHIRT PRINT but has the wrong image (or Eunsoyaa image), fix it
        // Or if it's Eunsoyaa but it's actually a tshirt order... wait.
        // Let's just fix T-SHIRT PRINT for now.
        if ($p_name === 'T-SHIRT PRINT') {
            $payload['product_image'] = '/printflow/public/assets/images/services/service_1775829792_69d90320bd272_0.jpg';
            $new_json = json_encode($payload);
            db_execute("UPDATE order_messages SET message = ? WHERE message_id = ?", 'si', [$new_json, $row['message_id']]);
            $count++;
        }
    }
}
echo "Fixed $count messages.\n";
