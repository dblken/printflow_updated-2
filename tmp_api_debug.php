<?php
$ch = curl_init("http://localhost/printflow/public/api/chat/list_conversations.php?archived=0");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Need to handle session/auth for it to work though...
$res = curl_exec($ch);
echo "RESPONSE FROM API:\n";
echo $res;
?>
