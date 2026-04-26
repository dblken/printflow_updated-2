<?php
/**
 * public/api/push/send_call_push.php
 * Triggers a push notification for an incoming call.
 * Internal API: Should be secured or only accessible from localhost if possible.
 */
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/push_helper.php';

header('Content-Type: application/json');

// Get POST data
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data['receiverId']) || empty($data['receiverType'])) {
    echo json_encode(['success' => false, 'error' => 'Missing target user info']);
    exit;
}

$receiverId = (int)$data['receiverId'];
$receiverType = $data['receiverType'];
$callerName = $data['callerName'] ?? 'Someone';
$callType = $data['callType'] ?? 'voice';
$orderId = $data['orderId'] ?? null;

// Determine URL
$url = '/printflow/';
if ($receiverType === 'Customer' && $orderId) {
    $url = "/printflow/customer/chat.php?order_id=$orderId";
} else if (($receiverType === 'Staff' || $receiverType === 'Manager') && $orderId) {
    $url = "/printflow/staff/chats.php?order_id=$orderId";
}

$payload = [
    'title' => "Incoming " . ucfirst($callType) . " Call",
    'body' => "$callerName is calling you on PrintFlow...",
    'url' => $url,
    'tag' => 'incoming-call',
    'type' => 'call'
];

$sentCount = push_notify_user($receiverId, $receiverType, $payload);

echo json_encode([
    'success' => true,
    'sent_to_devices' => $sentCount,
    'receiver' => "$receiverId ($receiverType)"
]);
