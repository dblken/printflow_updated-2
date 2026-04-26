<?php
/**
 * Send a call event system message to the chat.
 * Called after a call ends, is missed, declined, or fails due to busy state.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../../../includes/auth.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    ob_start();
    header('Content-Type: application/json');

    if (!is_logged_in()) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $order_id  = isset($_POST['order_id'])   ? (int)$_POST['order_id']   : 0;
    $event     = $_POST['event_type']        ?? '';  // missed|ended|declined|busy|no_answer
    $call_type = $_POST['call_type']         ?? 'voice'; // voice|video
    $duration  = isset($_POST['duration'])   ? (int)$_POST['duration']   : 0;

    $allowed_events = ['missed', 'ended', 'declined', 'busy', 'no_answer'];
    if (!$order_id || !in_array($event, $allowed_events)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $user_id = get_user_id();

    // Build message text
    $type_label = ($call_type === 'video') ? 'Video call' : 'Voice call';
    switch ($event) {
        case 'missed':
        case 'no_answer':
            $message = "Missed {$type_label}";
            break;
        case 'declined':
            $message = "{$type_label} was declined";
            break;
        case 'busy':
            $message = "User is currently on another call";
            break;
        case 'ended':
            if ($duration > 0) {
                $mins = floor($duration / 60);
                $secs = $duration % 60;
                $dur_str = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
                $message = "{$type_label} \xc2\xb7 {$dur_str}";
            } else {
                $message = "{$type_label}";
            }
            break;
        default:
            $message = "{$type_label} event";
    }

    $caller_id   = isset($_POST['caller_id'])   ? (int)$_POST['caller_id']   : 0;
    $caller_type = $_POST['caller_type']        ?? '';

    // If caller details are missing, attempt to resolve them from the order/auth context
    if (!$caller_id || !$caller_type) {
        $curr_user_id = get_user_id();
        $curr_user_type = get_user_type();
        
        // If the current user is reporting the event:
        // 1. For 'ended', the current user is a participant (could be caller or receiver).
        // 2. For 'no_answer', 'missed', the current user is usually the caller reporting a timeout.
        // 3. For 'declined', 'busy', the current user is the receiver reporting they can't take it.
        
        if (in_array($event, ['missed', 'no_answer'])) {
            // Re-attribute to the current user (caller)
            $sender = $curr_user_type;
            $sender_id = $curr_user_id;
        } else {
            // For declined/busy/ended, we need to find the "other" party.
            // If current is Customer, other is Staff. If current is Staff, other is Customer.
            if ($curr_user_type === 'Customer') {
                $sender = 'Staff';
                // Try to find the assigned staff for this order
                $staff_res = db_query("SELECT assigned_to FROM job_orders WHERE order_id = ? LIMIT 1", 'i', [$order_id]);
                $sender_id = ($staff_res && $staff_res[0]['assigned_to']) ? (int)$staff_res[0]['assigned_to'] : 0;
                if (!$sender_id) {
                    $staff_res = db_query("SELECT user_id FROM users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1");
                    $sender_id = $staff_res ? (int)$staff_res[0]['user_id'] : 0;
                }
            } else {
                $sender = 'Customer';
                // Find the customer for this order
                $cust_res = db_query("SELECT customer_id FROM orders WHERE order_id = ? LIMIT 1", 'i', [$order_id]);
                $sender_id = $cust_res ? (int)$cust_res[0]['customer_id'] : 0;
            }
        }
    } else {
        $sender      = ($caller_type === 'Staff') ? 'Staff' : 'Customer';
        $sender_id   = $caller_id;
    }

    // Final fallback if resolution failed: Use the current logged-in user's info
    if (!$sender_id) {
        $sender = get_user_type() === 'Customer' ? 'Customer' : 'Staff';
        $sender_id = get_user_id();
    }

    $sql    = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, created_at)
               VALUES (?, ?, ?, ?, 'call_log', NOW())";
    $result = db_execute($sql, 'isis', [$order_id, $sender, $sender_id, $message]);

    ob_end_clean();
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'error' => 'DB insert failed']);
    }

} catch (Throwable $t) {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => false, 'error' => $t->getMessage()]);
}
?>
