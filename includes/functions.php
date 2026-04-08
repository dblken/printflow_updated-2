<?php
/**
 * Helper Functions
 * PrintFlow - Printing Shop PWA
 */

// Set Timezone – adjust this based on your location
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_sms_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ensure_order_source_column.php'; // Ensure order_source column exists

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Check if request is AJAX/XHR
 * @return bool
 */
function is_xhr() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send email notification using PHPMailer
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param bool $is_html Whether message is HTML (default: true)
 * @return bool
 */
function send_email($to, $subject, $message, $is_html = true) {
    // Check if email is enabled
    if (!EMAIL_ENABLED) {
        error_log("Email sending disabled. Would send to: {$to}");
        return false;
    }
    
    try {
        $mail = new PHPMailer(true);

        $smtpFile = __DIR__ . '/smtp_config.php';
        $smtpCfg  = (is_file($smtpFile)) ? require $smtpFile : null;

        // Prefer includes/smtp_config.php (same as OTP / profile mailers) over email_sms_config placeholders
        if (is_array($smtpCfg) && !empty($smtpCfg['smtp_host']) && !empty($smtpCfg['smtp_user']) && ($smtpCfg['smtp_pass'] ?? '') !== '') {
            $mail->isSMTP();
            $mail->Host       = $smtpCfg['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpCfg['smtp_user'];
            $mail->Password   = $smtpCfg['smtp_pass'];
            $mail->SMTPSecure = ($smtpCfg['smtp_secure'] ?? 'tls') === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) ($smtpCfg['smtp_port'] ?? 587);
            $fromEmail        = $smtpCfg['from_email'] ?? $smtpCfg['smtp_user'];
            $fromName         = $smtpCfg['from_name'] ?? 'PrintFlow';
        } elseif (EMAIL_SERVICE === 'smtp') {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $fromEmail        = EMAIL_FROM_ADDRESS;
            $fromName         = EMAIL_FROM_NAME;
        } elseif (EMAIL_SERVICE === 'sendmail') {
            $mail->isSendmail();
            $fromEmail = EMAIL_FROM_ADDRESS;
            $fromName  = EMAIL_FROM_NAME;
        } else {
            $mail->isMail();
            $fromEmail = EMAIL_FROM_ADDRESS;
            $fromName  = EMAIL_FROM_NAME;
        }
        
        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($fromEmail, $fromName);
        
        // Content
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        
        if ($is_html) {
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
        } else {
            $mail->Body = $message;
        }
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send email to {$to}: " . $mail->ErrorInfo);
        error_log("\n" . str_repeat('=', 70));
        error_log("PRINTFLOW EMAIL ERROR - Quick Fix Guide:");
        error_log("1. Open: includes/smtp_config.php");
        error_log("2. Replace 'your-email@gmail.com' with your actual Gmail");
        error_log("3. Get App Password: https://myaccount.google.com/apppasswords");
        error_log("4. Replace 'your-app-password' with the 16-char password");
        error_log("5. See SMTP_SETUP_GUIDE.md for detailed instructions");
        error_log(str_repeat('=', 70) . "\n");
        return false;
    }
}

/**
 * Send SMS notification
 * @param string $phone Phone number
 * @param string $message SMS message
 * @return bool
 */
function send_sms($phone, $message) {
    // Check if SMS is enabled
    if (!SMS_ENABLED) {
        error_log("SMS sending disabled. Would send to: {$phone} - Message: {$message}");
        return false;
    }
    
    try {
        if (SMS_SERVICE === 'semaphore') {
            // Semaphore SMS API (Philippines)
            $url = 'https://api.semaphore.co/api/v4/messages';
            $data = [
                'apikey' => SEMAPHORE_API_KEY,
                'number' => $phone,
                'message' => $message,
                'sendername' => SEMAPHORE_SENDER_NAME
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                return true;
            } else {
                error_log("Semaphore SMS failed: " . $response);
                return false;
            }
            
        } elseif (SMS_SERVICE === 'twilio') {
            // Twilio SMS API
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $twilio = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
            $twilio->messages->create($phone, [
                'from' => TWILIO_PHONE_NUMBER,
                'body' => $message
            ]);
            
            return true;
            
        } else {
            error_log("No SMS service configured. Message to {$phone}: {$message}");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Failed to send SMS to {$phone}: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification
 * @param int $user_id User or Customer ID
 * @param string $user_type 'Customer' or 'User'
 * @param string $message Notification message
 * @param string $type Notification type ('Order', 'Stock', 'System', 'Message')
 * @param bool $send_email Whether to send email
 * @param bool $send_sms Whether to send SMS
 * @return bool|int
 */
function create_notification($user_id, $user_type, $message, $type = 'System', $send_email = false, $send_sms = false, $data_id = null) {
    // ── Pre-check ENUM ───────────────────────────────────────────────────────
    static $enums_checked = false;
    if (!$enums_checked) {
        try {
            $col = db_query("SHOW COLUMNS FROM notifications LIKE 'type'");
            if (!empty($col[0]['Type'])) {
                $t = (string)$col[0]['Type'];
                if (stripos($t, "'Rating'") === false || stripos($t, "'Review'") === false) {
                    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $t, $m);
                    $vals = $m[1] ?? [];
                    if (!in_array('Rating', $vals)) $vals[] = 'Rating';
                    if (!in_array('Review', $vals)) $vals[] = 'Review';
                    $escaped = array_map(fn($v) => "'" . str_replace("'", "\\'", $v) . "'", $vals);
                    db_execute("ALTER TABLE notifications MODIFY COLUMN type ENUM(" . implode(",", $escaped) . ") DEFAULT 'System'");
                }
            }
        } catch (Throwable $e) { error_log("Failed to ensure notification enum: " . $e->getMessage()); }
        $enums_checked = true;
    }

    $customer_id = $user_type === 'Customer' ? $user_id : null;
    $staff_user_id = $user_type !== 'Customer' ? $user_id : null;
    
    $sql = "INSERT INTO notifications (user_id, customer_id, message, type, data_id, is_read, send_email, send_sms) 
            VALUES (?, ?, ?, ?, ?, 0, ?, ?)";
    
    $result = db_execute($sql, 'iissiii', [
        $staff_user_id,
        $customer_id,
        $message,
        $type,
        $data_id,
        $send_email ? 1 : 0,
        $send_sms ? 1 : 0
    ]);
    
    if ($result && $send_email) {
        // Get user email
        if ($user_type === 'Customer') {
            $user = db_query("SELECT email FROM customers WHERE customer_id = ?", 'i', [$user_id]);
        } else {
            $user = db_query("SELECT email FROM users WHERE user_id = ?", 'i', [$user_id]);
        }
        
        if (!empty($user)) {
            send_email($user[0]['email'], "PrintFlow Notification", $message);
        }
    }

    // ── Web Push dispatch ────────────────────────────────────────────────────
    if ($result) {
        $push_helper = __DIR__ . '/push_helper.php';
        if (file_exists($push_helper)) {
            require_once $push_helper;
            if (function_exists('push_notify_user') && function_exists('push_url_for_type')) {
                $push_url = push_url_for_type($type, $data_id, $user_type);
                if ($type === 'System' && $data_id !== null && $data_id !== '' && (int)$data_id > 0) {
                    $ml = strtolower((string)$message);
                    if (strpos($ml, 'ready for admin review') !== false || strpos($ml, 'completed their profile') !== false) {
                        $push_url = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/admin/user_staff_management.php?open_user=' . (int)$data_id;
                    }
                }
                push_notify_user((int)$user_id, $user_type, [
                    'body' => $message,
                    'tag'  => 'pf-' . strtolower($type) . '-' . ($data_id ?? $result),
                    'url'  => $push_url,
                ]);
            }
        }
    }

    return $result;
}

/**
 * Notify all activated shop users (Staff, Admin, Manager) about a new customer order.
 * Uses each user's role for web push subscription matching.
 */
function notify_staff_new_order(int $order_id, string $customer_first_name): void {
    // Get service name and order type from context
    $first_item = db_query("SELECT customization_data FROM order_items WHERE order_id = ? LIMIT 1", 'i', [$order_id]);
    $service_name = 'Product Order';
    $is_service_order = false;
    
    if (!empty($first_item)) {
        $custom_data = !empty($first_item[0]['customization_data']) ? json_decode($first_item[0]['customization_data'], true) : [];
        $service_name = get_service_name_from_customization($custom_data, 'Product Order');
        
        // Check if this is a service order (has customization data)
        $is_service_order = !empty($custom_data) && is_array($custom_data) && count($custom_data) > 0;
    }
    
    // Also check order_type field
    $order_data = db_query("SELECT order_type FROM orders WHERE order_id = ? LIMIT 1", 'i', [$order_id]);
    if (!empty($order_data) && $order_data[0]['order_type'] === 'custom') {
        $is_service_order = true;
    }

    $users = db_query(
        "SELECT user_id, role FROM users WHERE role IN ('Staff', 'Admin', 'Manager') AND status = 'Activated'"
    );
    if (empty($users)) {
        return;
    }
    
    $name = trim($customer_first_name) !== '' ? trim($customer_first_name) : 'A customer';
    // Format: "Customer Name placed an order for Service Name"
    $msg = "{$name} placed an order for {$service_name}";
    
    foreach ($users as $u) {
        $role = $u['role'] ?? 'Staff';
        if (!in_array($role, ['Staff', 'Admin', 'Manager'], true)) {
            $role = 'Staff';
        }
        create_notification((int)$u['user_id'], $role, $msg, 'Order', false, false, $order_id);
    }
}

/**
 * Target URL when a staff user opens a notification (dashboard, list, etc.).
 */
function staff_notification_target_url(array $n): string {
    $base = defined('BASE_URL') ? BASE_URL : '/printflow';
    $msg = isset($n['message']) ? (string)$n['message'] : '';
    $msg_lower = strtolower($msg);
    
    $is_rating = (
        (isset($n['type']) && (string)$n['type'] === 'Rating') ||
        ((stripos($msg, 'rating') !== false || stripos($msg, 'review') !== false) && stripos($msg, 'design') === false)
    );
    if ($is_rating) {
        return $base . '/staff/reviews.php';
    }

    // Stock / Inventory notification — stay on notifications page
    if (isset($n['type']) && (string)$n['type'] === 'Stock') {
        return $base . '/staff/notifications.php';
    }

    // Order / Job / Payment / Design notifications with a data_id
    if (!empty($n['data_id']) && isset($n['type']) && (string)$n['type'] === 'Order') {
        $data_id = (int)$n['data_id'];
        
        // Re-uploaded design or design re-upload
        if (stripos($msg, 're-uploaded design') !== false || stripos($msg, 'design re-upload') !== false) {
             return $base . '/staff/customizations.php?order_id=' . $data_id . '&job_type=ORDER&status=PENDING';
        }

        // Check if the data_id belongs to job_orders table (custom/specialty jobs)
        $job_row = db_query(
            "SELECT id FROM job_orders WHERE id = ? LIMIT 1",
            'i',
            [$data_id]
        );
        if (!empty($job_row)) {
            return $base . '/staff/customizations.php?order_id=' . $data_id . '&job_type=JOB';
        }

        // Check if the data_id belongs to store orders table and get order_type + source
        $ord_row = db_query(
            "SELECT order_id, order_type, order_source FROM orders WHERE order_id = ? LIMIT 1",
            'i',
            [$data_id]
        );
        if (!empty($ord_row)) {
            $order_type = $ord_row[0]['order_type'] ?? 'product';
            $order_source = $ord_row[0]['order_source'] ?? 'customer';
            
            // Route based on order type: custom -> customizations.php, product -> orders.php
            if ($order_type === 'custom') {
                // Check if this is a new order notification
                if (stripos($msg, 'placed an order') !== false) {
                    // Customer orders (from order_service_dynamic.php) -> PENDING tab
                    // POS orders (from staff/pos.php) -> All tabs (no status filter)
                    if ($order_source === 'pos' || $order_source === 'walk-in') {
                        return $base . '/staff/customizations.php?order_id=' . $data_id . '&job_type=ORDER';
                    } else {
                        return $base . '/staff/customizations.php?order_id=' . $data_id . '&job_type=ORDER&status=PENDING';
                    }
                }
                return $base . '/staff/customizations.php?order_id=' . $data_id . '&job_type=ORDER';
            }
            return $base . '/staff/orders.php?order_id=' . $data_id;
        }

        // Fallback: treat as a job order
        return $base . '/staff/customizations.php?order_id=' . $data_id . '&job_type=JOB';
    }

    return $base . '/staff/notifications.php';
}

/**
 * Link for a staff notification row (marks read then redirects when unread).
 */
function staff_notification_item_href(array $n): string {
    $target = staff_notification_target_url($n);
    $base = defined('BASE_URL') ? BASE_URL : '/printflow';
    if (isset($n['is_read']) && (int)$n['is_read'] === 0) {
        return $base . '/staff/notifications.php?mark_read=' . (int)($n['notification_id'] ?? 0) . '&next=' . urlencode($target);
    }
    return $target;
}

/**
 * Target URL when an admin/manager opens a notification.
 */
function admin_notification_target_url(array $n): string {
    $base = defined('BASE_URL') ? BASE_URL : '/printflow';
    $admin = $base . '/admin';
    $type = isset($n['type']) ? (string)$n['type'] : '';
    $msg = isset($n['message']) ? strtolower((string)$n['message']) : '';
    $dataId = isset($n['data_id']) && $n['data_id'] !== null && $n['data_id'] !== ''
        ? (int)$n['data_id'] : 0;

    if ($type === 'System' && (
        strpos($msg, 'chatbot') !== false ||
        strpos($msg, 'support chat') !== false
    )) {
        return $admin . '/faq_chatbot_management.php?tab=inquiries';
    }

    // Staff submitted profile for activation (data_id = users.user_id)
    if ($dataId > 0 && $type === 'System' && (
        strpos($msg, 'ready for admin review') !== false ||
        strpos($msg, 'completed their profile') !== false
    )) {
        return $admin . '/user_staff_management.php?open_user=' . $dataId;
    }

    if ($dataId > 0) {
        if (in_array($type, ['Order', 'Design', 'Message'], true)) {
            return $admin . '/orders_management.php?open_order=' . $dataId;
        }
        if (in_array($type, ['Job Order', 'Payment Issue'], true)) {
            return $admin . '/job_orders.php?open_job=' . $dataId;
        }
        if ($type === 'Stock') {
            return $admin . '/inv_transactions_ledger.php?item_id=' . $dataId;
        }
        if ($type === 'Payment') {
            return $admin . '/orders_management.php?open_order=' . $dataId;
        }
    }

    if ($type === 'Payment') {
        return $admin . '/orders_management.php';
    }

    return $admin . '/dashboard.php';
}

/**
 * Log user activity
 * @param int $user_id
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool|int
 */
function log_activity($user_id, $action, $details = '') {
    // activity_logs.user_id has FK to users.user_id only.
    // Customer IDs are from customers.customer_id and can violate FK.
    // Logging must never break request flow.
    try {
        $resolved_user_id = 0;

        if (is_numeric($user_id)) {
            $candidate = (int)$user_id;
            if ($candidate > 0) {
                $exists = db_query("SELECT user_id FROM users WHERE user_id = ? LIMIT 1", 'i', [$candidate]);
                if (!empty($exists)) {
                    $resolved_user_id = $candidate;
                }
            }
        }

        // If the provided ID is not a valid staff/admin user, skip insert safely.
        if ($resolved_user_id <= 0) {
            return true;
        }

        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
        $result = db_execute($sql, 'iss', [$resolved_user_id, (string)$action, (string)$details]);
        return $result !== false;
    } catch (Throwable $e) {
        error_log("Activity log failed: " . $e->getMessage());
        return true; // Never block main feature when activity log fails
    }
}

/**
 * Get customer ID from session
 * @return int|null
 */
function get_customer_id() {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Customer') {
        return $_SESSION['user_id'] ?? null;
    }
    return null;
}

/**
 * Load customer cart from database into session.
 * Call after customer login or when session cart is empty.
 * @param int $customer_id
 * @return void
 */
function load_customer_cart_into_session($customer_id) {
    if (!$customer_id) return;
    $rows = db_query("SELECT product_id, variant_id, quantity FROM customer_cart WHERE customer_id = ?", 'i', [$customer_id]);
    if (empty($rows)) return;
    $_SESSION['cart'] = [];
    foreach ($rows as $r) {
        $pid = (int)$r['product_id'];
        $vid = isset($r['variant_id']) && $r['variant_id'] !== '' && $r['variant_id'] !== null ? (int)$r['variant_id'] : null;
        $qty = max(0, (int)$r['quantity']);
        if ($qty <= 0 || $pid <= 0) continue;
        $product = db_query("SELECT name, price, category FROM products WHERE product_id = ? AND status = 'Activated'", 'i', [$pid]);
        if (empty($product)) continue;
        $product = $product[0];
        $price = (float)$product['price'];
        $variant_name = '';
        if ($vid) {
            $v = db_query("SELECT variant_name, price FROM product_variants WHERE variant_id = ? AND product_id = ? AND status = 'Active'", 'ii', [$vid, $pid]);
            if (!empty($v)) {
                $variant_name = $v[0]['variant_name'] ?? '';
                $price = (float)$v[0]['price'];
            }
        }
        $key = $pid . '_' . ($vid ?? '0');
        $_SESSION['cart'][$key] = [
            'product_id' => $pid,
            'variant_id' => $vid,
            'name' => $product['name'],
            'category' => $product['category'] ?? '',
            'source_page' => 'products',
            'variant_name' => $variant_name,
            'quantity' => $qty,
            'price' => $price,
        ];
    }
}

/**
 * Sync session cart to customer_cart table.
 * @param int $customer_id
 * @return void
 */
function sync_cart_to_db($customer_id) {
    if (!$customer_id) return;
    db_execute("DELETE FROM customer_cart WHERE customer_id = ?", 'i', [$customer_id]);
    if (empty($_SESSION['cart'])) return;
    foreach ($_SESSION['cart'] as $key => $item) {
        $qty = (int)($item['quantity'] ?? 0);
        if ($qty <= 0) continue;
        // Persist only true catalog products in customer_cart.
        // Service/custom entries may carry non-catalog IDs and would violate FK.
        $source_page = strtolower(trim((string)($item['source_page'] ?? '')));
        if ($source_page === 'services') continue;
        $pid = (int)($item['product_id'] ?? 0);
        $vid = isset($item['variant_id']) && $item['variant_id'] !== null ? (int)$item['variant_id'] : 0;
        if ($pid <= 0) continue;
        $exists = db_query("SELECT product_id FROM products WHERE product_id = ? LIMIT 1", 'i', [$pid]);
        if (empty($exists)) continue;
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE to avoid duplicate key errors
        db_execute(
            "INSERT INTO customer_cart (customer_id, product_id, variant_id, quantity, updated_at) 
             VALUES (?, ?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = NOW()",
            'iiii',
            [$customer_id, $pid, $vid, $qty]
        );
    }
}

/**
 * Get customer cancellation count (last 30 days)
 * @param int $customer_id
 * @return int
 */
function get_customer_cancel_count($customer_id) {
    if (!$customer_id) return 0;
    $sql = "SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'Cancelled' AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = db_query($sql, 'i', [$customer_id]);
    return (int)($result[0]['count'] ?? 0);
}

/**
 * Check if customer is restricted due to cancellations
 * @param int $customer_id
 * @return bool
 */
function is_customer_restricted($customer_id) {
    if (!$customer_id) return false;
    // Check for hard restriction in DB first
    $customer = db_query("SELECT is_restricted FROM customers WHERE customer_id = ?", 'i', [$customer_id]);
    if (!empty($customer) && $customer[0]['is_restricted']) return true;

    // Automatic restriction based on cancellation count (7+)
    return get_customer_cancel_count($customer_id) >= 7;
}



/**
 * Validate file upload
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Max file size in bytes
 * @return array ['valid' => bool, 'message' => string, 'file_info' => array]
 */
function validate_file_upload($file, $allowed_types = [], $max_size = 10485760) {
    // Default allowed types for design files
    if (empty($allowed_types)) {
        $allowed_types = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'application/pdf',
            'image/svg+xml'
        ];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $max_mb = $max_size / 1048576;
        return ['valid' => false, 'message' => "File too large. Maximum size is {$max_mb}MB"];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['valid' => false, 'message' => 'Invalid file type'];
    }
    
    return [
        'valid' => true,
        'message' => 'File is valid',
        'file_info' => [
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $mime_type,
            'tmp_name' => $file['tmp_name']
        ]
    ];
}

/**
 * Upload file to server
 * @param array $file $_FILES array element
 * @param array $allowed_extensions Array of allowed extensions (e.g., ['jpg', 'png', 'pdf'])
 * @param string $destination Directory name under uploads/ (e.g., 'designs', 'payments')
 * @param string|null $new_name Optional new filename
 * @return array ['success' => bool, 'message' => string, 'error' => string, 'file_path' => string]
 */
function upload_file($file, $allowed_extensions = [], $destination = 'uploads', $new_name = null, $max_bytes = 5242880) {
    // Check for upload errors  
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_bytes) {
        $mb = round($max_bytes / 1048576);
        return ['success' => false, 'error' => "File too large. Maximum size is {$mb}MB"];
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowed_extensions) && !in_array($ext, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Create destination directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/' . $destination;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate filename
    if ($new_name === null) {
        $new_name = uniqid() . '_' . time() . '.' . $ext;
    }
    
    $target_path = $upload_dir . '/' . $new_name;
    $relative_path = '/printflow/uploads/' . $destination . '/' . $new_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_path' => $relative_path,
            'file_name' => $new_name
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file'];
}

/**
 * Ensure review and ratings tables exist.
 * One review entry per order. Multi-image, video, and staff replies supported.
 * @return void
 */
function ensure_ratings_table_exists() {
    static $ensured = false;
    if ($ensured) return;

    // 1. Core reviews table
    db_execute("
        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            reference_id INT DEFAULT NULL,
            review_type ENUM('product', 'custom') DEFAULT 'custom',
            service_type VARCHAR(150) DEFAULT NULL,
            rating TINYINT NOT NULL,
            comment TEXT DEFAULT NULL,
            video_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_review_order (order_id),
            KEY idx_review_user (user_id),
            KEY idx_review_rating (rating),
            CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
            CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // 2. Review multiple images
    db_execute("
        CREATE TABLE IF NOT EXISTS review_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            CONSTRAINT fk_review_images_review FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // 3. Staff replies to reviews
    db_execute("
        CREATE TABLE IF NOT EXISTS review_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            staff_id INT NOT NULL,
            reply_message TEXT NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_review_replies_review FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
            CONSTRAINT fk_review_replies_staff FOREIGN KEY (staff_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $ensured = true;
}

/**
 * Format a timestamp into a relative "X ago" string.
 * @param string|int $timestamp
 * @return string
 */
function format_ago($timestamp) {
    if (!$timestamp) return 'n/a';
    $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    if (!$time) return 'n/a';
    
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    if ($diff < 31536000) return floor($diff / 2592000) . 'mo ago';
    return floor($diff / 31536000) . 'y ago';
}

/**
 * Ensure `orders.status` enum contains required values.
 * Safe no-op if column is not enum or values already exist.
 * @param array $values
 * @return bool
 */
function ensure_order_status_values(array $values) {
    static $already_checked = [];
    $missing = array_values(array_filter(array_map('strval', $values), fn($v) => $v !== ''));
    if (empty($missing)) return true;
    sort($missing);
    $cache_key = implode('|', $missing);
    if (isset($already_checked[$cache_key])) return $already_checked[$cache_key];

    try {
        $col = db_query("SHOW COLUMNS FROM orders LIKE 'status'");
        if (empty($col[0]['Type'])) {
            return $already_checked[$cache_key] = false;
        }
        $type = (string)$col[0]['Type'];
        if (stripos($type, 'enum(') !== 0) {
            return $already_checked[$cache_key] = false;
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $m);
        $current = array_map(static function ($v) {
            return str_replace("\\'", "'", (string)$v);
        }, $m[1] ?? []);
        $all = $current;
        foreach ($missing as $v) {
            if (!in_array($v, $all, true)) $all[] = $v;
        }
        if (count($all) === count($current)) {
            return $already_checked[$cache_key] = true;
        }

        $escaped = array_map(static function ($v) {
            return "'" . str_replace("'", "\\'", (string)$v) . "'";
        }, $all);
        $default = in_array('Pending', $all, true) ? 'Pending' : $all[0];
        $sql = "ALTER TABLE orders MODIFY COLUMN status ENUM(" . implode(',', $escaped) . ") DEFAULT '" . str_replace("'", "\\'", $default) . "'";
        db_execute($sql);

        return $already_checked[$cache_key] = true;
    } catch (Throwable $e) {
        error_log('ensure_order_status_values failed: ' . $e->getMessage());
        return $already_checked[$cache_key] = false;
    }
}

/**
 * Friendly customer-facing status notification message.
 * @param int $order_id
 * @param string $status
 * @return array{type:string,message:string}
 */
function get_order_status_notification_payload($order_id, $status) {
    $order_id = (int)$order_id;
    $status = (string)$status;
    // Keep notification type enum-compatible across deployments.
    $type = 'Order';
    $base_url = defined('BASE_URL') ? BASE_URL : '/printflow';

    $map = [
        'Pending' => "Your order has been received and is pending confirmation.",
        'Pending Review' => "Your order has been received and is pending confirmation.",
        'Pending Approval' => "Your order has been received and is pending confirmation.",
        'For Revision' => "Your order needs revision. Please review the request details.",
        'Approved' => "Your order has been approved and will proceed to payment.",
        'To Pay' => "Your order is now ready for payment.",
        'To Verify' => "Your payment is currently being verified.",
        'Downpayment Submitted' => "Your payment is currently being verified.",
        'Pending Verification' => "Your payment is currently being verified.",
        'Processing' => "Your order is now being processed.",
        'In Production' => "Your order is now being processed.",
        'Printing' => "Your order is now being processed.",
        'Ready for Pickup' => "Your order is ready for pickup.",
        'Completed' => "Your order has been completed. You may now rate your experience.",
        'To Rate' => "Your order has been completed. You may now rate your experience.",
        'Rated' => "Thank you for rating your completed order.",
        'Cancelled' => "Your order has been cancelled."
    ];

    $message = $map[$status] ?? "Your order #{$order_id} status has been updated to: {$status}";
    if ($status === 'Completed' || $status === 'To Rate') {
        $message .= " Rate here: " . $base_url . "/customer/rate_order.php?order_id={$order_id}";
    }

    return ['type' => $type, 'message' => $message];
}

/**
 * Add a system message to an order's chat thread.
 * Call this when order status changes, payment verified, etc.
 *
 * @param int    $order_id
 * @param string $message
 * @return bool
 */
function add_order_system_message($order_id, $message) {
    $order_id = (int)$order_id;
    $message = trim($message);
    if (!$order_id || $message === '') return false;

    $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, read_receipt)
            VALUES (?, 'System', 0, ?, 'text', 1)";
    return (bool) db_execute($sql, 'is', [$order_id, $message]);
}

/**
 * Format currency
 * @param float $amount
 * @param string $currency
 * @return string
 */
function format_currency($amount, $currency = '₱') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Format date
 * @param string $date
 * @param string $format
 * @return string
 */
function format_date($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format datetime
 * @param string $datetime
 * @param string $format
 * @return string
 */
function format_datetime($datetime, $format = 'F j, Y g:i A') {
    return date($format, strtotime($datetime));
}

/**
 * Get time ago
 * @param string $datetime
 * @return string
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    $periods = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    ];
    
    foreach ($periods as $key => $value) {
        $result = floor($difference / $value);
        
        if ($result >= 1) {
            return $result . ' ' . $key . ($result > 1 ? 's' : '') . ' ago';
        }
    }
    
    return 'Just now';
}

/**
 * Generate status badge HTML
 * @param string $status
 * @param string $type 'order', 'payment', 'design'
 * @return string
 */
function status_badge($status, $type = 'order') {
    // Map job order statuses to display-friendly format
    $job_order_status_map = [
        'PENDING' => 'Pending',
        'APPROVED' => 'Approved',
        'TO_PAY' => 'To Pay',
        'VERIFY_PAY' => 'To Verify',
        'IN_PRODUCTION' => 'Processing',
        'TO_RECEIVE' => 'Ready for Pickup',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled'
    ];
    
    // Map job order payment statuses
    $job_order_payment_status_map = [
        'UNPAID' => 'Unpaid',
        'PENDING_VERIFICATION' => 'Pending Verification',
        'PARTIAL' => 'Partially Paid',
        'PAID' => 'Paid'
    ];
    
    // Convert job order status if needed
    if (isset($job_order_status_map[$status])) {
        $status = $job_order_status_map[$status];
    }
    
    // Convert job order payment status if needed
    if ($type === 'payment' && isset($job_order_payment_status_map[$status])) {
        $status = $job_order_payment_status_map[$status];
    }
    
    $colors = [
        'order' => [
            'Pending' => 'background: #fef3c7; color: #92400e; border: none;',
            'Pending Review' => 'background: #fef3c7; color: #92400e; border: none;',
            'Approved' => 'background: #dbeafe; color: #1e40af; border: none;',
            'To Pay' => 'background: #dbeafe; color: #1e40af; border: none;',
            'To Verify' => 'background: #fef9c3; color: #854d0e; border: none;',
            'Downpayment Submitted' => 'background: #fce7f3; color: #be185d; border: none;',
            'Pending Verification' => 'background: #fef9c3; color: #854d0e; border: none;',
            'Processing' => 'background: #e0e7ff; color: #4338ca; border: none;',
            'In Production' => 'background: #cffafe; color: #0891b2; border: none;',
            'Printing' => 'background: #cffafe; color: #0891b2; border: none;',
            'For Revision' => 'background: #ffe4e6; color: #b91c1c; border: none;',
            'Revision Submitted' => 'background: #fef3c7; color: #92400e; border: none;',
            'Ready for Pickup' => 'background: #dcfce7; color: #15803d; border: none;',
            'Completed' => 'background: #dcfce7; color: #166534; border: none;',
            'To Rate' => 'background: #f3e8ff; color: #6b21a8; border: none;',
            'Rated' => 'background: #f3e8ff; color: #6b21a8; border: none;',
            'Cancelled' => 'background: #fee2e2; color: #991b1b; border: none;'
        ],
        'payment' => [
            'Unpaid' => 'background: #fee2e2; color: #991b1b; border: none;',
            'Partially Paid' => 'background: #fef3c7; color: #92400e; border: none;',
            'Paid' => 'background: #dcfce7; color: #166534; border: none;',
            'Refunded' => 'background: #f3f4f6; color: #374151; border: none;',
            'Pending Verification' => 'background: #fef3c7; color: #92400e; border: none;'
        ],
        'design' => [
            'Pending' => 'background: #fffbeb; color: #92400e; border: 1px solid #fef3c7;',
            'Approved' => 'background: #f0fdf4; color: #166534; border: 1px solid #dcfce7;',
            'Rejected' => 'background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2;'
        ]
    ];
    
    $style = $colors[$type][$status] ?? 'background: #f9fafb; color: #374151; border: 1px solid #f3f4f6;';
    // Display "Pending" instead of "Pending Review" and "To Verify" for consistency
    if ($status === 'Pending Review' || $status === 'To Verify') {
        $display = 'Pending';
    } else {
        $display = $status;
    }
    
    return "<span class='status-badge-pill' style='{$style}'>" . htmlspecialchars($display) . "</span>";
}


/**
 * Sanitize input
 * @param string $input
 * @return string
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Normalize branch name for Add/Edit: trim, strip trailing "Branch", title-case
 * System auto-appends " Branch" — user should not type it.
 */
function normalize_branch_name($name) {
    $name = trim($name);
    $name = preg_replace('/\s+Branch\s*$/i', '', $name);
    return ucwords(strtolower($name));
}

/**
 * Redirect to URL
 * @param string $url
 */
function redirect($url) {
    header("Location: {$url}");
    exit();
}

/**
 * Get unread notification count
 * @param int $user_id
 * @param string $user_type
 * @return int
 */
function get_unread_notification_count($user_id, $user_type) {
    if ($user_type === 'Customer') {
        $result = db_query("SELECT COUNT(*) as count FROM notifications WHERE customer_id = ? AND is_read = 0", 'i', [$user_id]);
    } else {
        $result = db_query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$user_id]);
    }
    
    return (!empty($result) && isset($result[0]['count'])) ? (int)$result[0]['count'] : 0;
}

/**
 * Get count of unread chat messages for an order
 * @param int $order_id
 * @param string $viewer_role 'Customer' or 'Staff'
 * @return int
 */
function get_unread_chat_count($order_id, $viewer_role) {
    // If viewer is Customer, they haven't read messages from Staff
    // If viewer is Staff, they haven't read messages from Customer
    $sender_role = ($viewer_role === 'Customer') ? 'Staff' : 'Customer';
    
    $sql = "SELECT COUNT(*) as count FROM order_messages 
            WHERE order_id = ? AND sender = ? AND read_receipt = 0";
    $result = db_query($sql, 'is', [$order_id, $sender_role]);
    
    return $result[0]['count'] ?? 0;
}

/**
 * Generate random order number
 * @return string
 */
function generate_order_number() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Check if product is low stock
 * @param int $product_id
 * @param int $threshold Default threshold
 * @return bool
 */
function is_low_stock($product_id, $threshold = 10) {
    $result = db_query("SELECT stock_quantity FROM products WHERE product_id = ?", 'i', [$product_id]);
    
    if (empty($result)) {
        return false;
    }
    
    return $result[0]['stock_quantity'] <= $threshold;
}

/**
 * Compute stock status from quantity and low_stock_level (not stored in DB)
 * @param int $stock_quantity
 * @param int $low_stock_level
 * @return string "Out of Stock"|"Low Stock"|"In Stock"
 */
function get_stock_status($stock_quantity, $low_stock_level = 10) {
    $qty = (int) $stock_quantity;
    $low = (int) ($low_stock_level ?? 10);
    if ($qty <= 0) return 'Out of Stock';
    if ($qty <= $low) return 'Low Stock';
    return 'In Stock';
}

/**
 * Get app setting
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_setting($key, $default = null) {
    $result = db_query("SELECT value FROM settings WHERE key_name = ?", 's', [$key]);
    
    if (empty($result)) {
        return $default;
    }
    
    return $result[0]['value'];
}

/**
 * Set app setting
 * @param string $key
 * @param mixed $value
 * @return bool
 */
function set_setting($key, $value) {
    $existing = db_query("SELECT setting_id FROM settings WHERE key_name = ?", 's', [$key]);
    
    if (empty($existing)) {
        return db_execute("INSERT INTO settings (key_name, value) VALUES (?, ?)", 'ss', [$key, $value]);
    } else {
        return db_execute("UPDATE settings SET value = ? WHERE key_name = ?", 'ss', [$value, $key]);
    }
}

/**
 * Render pagination UI
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param array $extra_params Extra query parameters to preserve (e.g. search, filters)
 * @param string $page_param Query param name for page number (default: 'page')
 * @return string HTML string
 */
function render_pagination($current_page, $total_pages, $extra_params = [], $page_param = 'page') {
    if ($total_pages <= 1) return '';

    $current_page = (int)$current_page;
    $window = 2; // Show 2 pages before and after current
    $pages = [];
    
    // Always include first page
    $pages[] = 1;
    
    $range_start = max(2, $current_page - $window);
    $range_end   = min($total_pages - 1, $current_page + $window);
    for ($i = $range_start; $i <= $range_end; $i++) {
        $pages[] = $i;
    }
    
    // Always include last page
    if ($total_pages > 1) {
        $pages[] = $total_pages;
    }
    
    $pages = array_unique($pages);
    sort($pages);

    // Shared button styles — 'all:unset' defeats Tailwind/output.css resets on <a> tags
    // Using PrintFlow Primary #06A1A1
    $base_btn  = 'all:unset;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid #e5e7eb;background:#ffffff;color:#374151 !important;text-decoration:none !important;font-size:13px;font-weight:500;cursor:pointer;transition:all 0.2s;';
    $active_btn = 'all:unset;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid #06A1A1;background:#06A1A1;color:#ffffff !important;text-decoration:none !important;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 2px 4px rgba(6,161,161,0.2);';
    $hover = ' onmouseover="this.style.borderColor=\'#06A1A1\';this.style.color=\'#06A1A1\'" onmouseout="this.style.borderColor=\'#e5e7eb\';this.style.color=\'#374151\'"';
    $ellipsis = '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;font-size:13px;color:#9ca3af;letter-spacing:1px;">···</span>';

    $params = $extra_params;
    unset($params[$page_param]);
    
    $html = '<div style="display:flex; align-items:center; justify-content:center; gap:6px; margin-top:24px; padding:16px 0; border-top:1px solid #f1f5f9; width:100%;">';

    // Previous button
    if ($current_page > 1) {
        $params[$page_param] = $current_page - 1;
        $url = '?' . http_build_query($params);
        $html .= '<a href="' . htmlspecialchars($url) . '" style="' . $base_btn . '"' . $hover . '>
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>';
    }

    $prev_page = null;
    foreach ($pages as $p) {
        if ($prev_page !== null && $p - $prev_page > 1) {
            $html .= $ellipsis;
        }
        
        $params[$page_param] = $p;
        $url = '?' . http_build_query($params);
        
        if ((int)$p === (int)$current_page) {
            $html .= '<a href="' . htmlspecialchars($url) . '" style="' . $active_btn . '">' . $p . '</a>';
        } else {
            $html .= '<a href="' . htmlspecialchars($url) . '" style="' . $base_btn . '"' . $hover . '>' . $p . '</a>';
        }
        $prev_page = $p;
    }

    // Next button
    if ($current_page < $total_pages) {
        $params[$page_param] = $current_page + 1;
        $url = '?' . http_build_query($params);
        $html .= '<a href="' . htmlspecialchars($url) . '" style="' . $base_btn . '"' . $hover . '>
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Alias for render_pagination (backward compatibility)
 */
function get_pagination_links($current_page, $total_pages, $extra_params = [], $page_param = 'page') {
    return render_pagination($current_page, $total_pages, $extra_params, $page_param);
}

/**
 * Check if a customer's ID is verified.
 */
function is_customer_id_verified($customer_id = null) {
    if ($customer_id === null) $customer_id = get_user_id();
    if (!$customer_id) return false;
    static $cache = [];
    if (isset($cache[$customer_id])) return $cache[$customer_id];
    // Ensure columns exist
    global $conn;
    $cols = db_query("SHOW COLUMNS FROM customers LIKE 'id_status'");
    if (empty($cols)) {
        $conn->query("ALTER TABLE customers ADD COLUMN id_image VARCHAR(255) DEFAULT NULL, ADD COLUMN id_type VARCHAR(100) DEFAULT NULL, ADD COLUMN id_status ENUM('None','Pending','Verified','Rejected') DEFAULT 'None', ADD COLUMN id_reject_reason VARCHAR(255) DEFAULT NULL");
    }
    $r = db_query("SELECT id_status FROM customers WHERE customer_id = ?", 'i', [$customer_id]);
    return $cache[$customer_id] = (!empty($r) && $r[0]['id_status'] === 'Verified');
}

/**
 * Determine if a customer can cancel an order based on its status.
 */
function can_customer_cancel_order($order) {
    if (!$order) return false;
    $status = $order['status'] ?? '';
    // Customers can cancel unless production has started or payment is being verified
    $allowed_statuses = ['Pending', 'To Pay', 'For Revision', 'Pending Verification'];
    return in_array($status, $allowed_statuses);
}

/**
 * Get base URL for the application
 * @return string
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = rtrim($path, '/');
    return $protocol . '://' . $host . $path;
}

/**
 * Detects the service name based on customization keys if not explicitly provided.
 */
function normalize_service_name($name, $fallback = 'Custom Order') {
    $clean = trim((string)$name);
    if ($clean === '') return $fallback;

    $normalized = strtolower(preg_replace('/\s+/', ' ', $clean));
    
    // Exact mapping for core services to ensure consistency across the system
    $map = [
        'tarpaulin' => 'Tarpaulin Printing',
        'tarpaulin printing' => 'Tarpaulin Printing',
        'tarp' => 'Tarpaulin Printing',
        't-shirt' => 'T-Shirt Printing',
        'tshirt' => 'T-Shirt Printing',
        't-shirt printing' => 'T-Shirt Printing',
        'tshirt printing' => 'T-Shirt Printing',
        'stickers' => 'Decals/Stickers (Print/Cut)',
        'sticker' => 'Decals/Stickers (Print/Cut)',
        'decal' => 'Decals/Stickers (Print/Cut)',
        'decals' => 'Decals/Stickers (Print/Cut)',
        'decals/stickers (print/cut)' => 'Decals/Stickers (Print/Cut)',
        'decals / stickers (print/cut)' => 'Decals/Stickers (Print/Cut)',
        'decals / stickers (print & cut)' => 'Decals/Stickers (Print/Cut)',
        'sintraboard' => 'Sintraboard Standees',
        'sintra board' => 'Sintraboard Standees',
        'standee' => 'Sintraboard Standees',
        'standees' => 'Sintraboard Standees',
        'glass sticker' => 'Glass/Wall Stickers',
        'frosted sticker' => 'Glass/Wall Stickers',
        'wall sticker' => 'Glass/Wall Stickers',
        'transparent sticker' => 'Transparent Stickers',
        'reflectorized' => 'Reflectorized',
        'souvenir' => 'Souvenirs',
        'souvenirs' => 'Souvenirs'
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return ucwords($clean);
}

function get_service_name_from_customization($custom, $fallback = 'Custom Order') {
    if (!$custom) return $fallback;
    $custom = is_string($custom) ? json_decode($custom, true) : $custom;
    
    // User Requested Priority Logic
    // 1. Sintra Board
    if (!empty($custom['sintra_type']) || !empty($custom['Sintra Type']) || !empty($custom['is_standee'])) {
        return 'Sintraboard Standees';
    }
    // 2. Tarpaulin Printing
    if (!empty($custom['tarp_size']) || !empty($custom['Tarp Size']) || (!empty($custom['width']) && !empty($custom['height']) && (!empty($custom['finish']) || !empty($custom['with_eyelets'])))) {
        return 'Tarpaulin Printing';
    }
    // 3. T-Shirt Printing
    if (!empty($custom['vinyl_type']) || !empty($custom['print_placement']) || !empty($custom['tshirt_color']) || !empty($custom['shirt_source'])) {
        return 'T-Shirt Printing';
    }
    // 4. Decals/Stickers
    if (!empty($custom['sticker_type']) || !empty($custom['Sticker Type']) || !empty($custom['shape']) || !empty($custom['Cut_Type'])) {
        return 'Decals/Stickers (Print/Cut)';
    }

    // Secondary explicitly provided fields
    if (!empty($custom['service_type'])) {
        return normalize_service_name($custom['service_type'], $fallback);
    }
    if (!empty($custom['product_type'])) {
        return normalize_service_name($custom['product_type'], $fallback);
    }
    
    return normalize_service_name($fallback, $fallback);
}

/**
 * Service image mapping - SAME as Services page ($core_services).
 * Source of truth: /customer/services.php
 */
function get_services_image_map() {
    $base = '/printflow/public';
    return [
        'tarpaulin'   => $base . '/images/products/product_42.jpg',
        't-shirt'     => $base . '/images/products/product_31.jpg',
        'shirt'       => $base . '/images/products/product_31.jpg',
        'stickers'    => $base . '/images/products/product_21.jpg',
        'sticker'     => $base . '/images/products/product_21.jpg',
        'decal'       => $base . '/images/products/product_21.jpg',
        'glass'       => $base . '/images/products/Glass Stickers  Wall  Frosted Stickers.png',
        'frosted'     => $base . '/images/products/Glass Stickers  Wall  Frosted Stickers.png',
        'wall'        => $base . '/images/products/Glass Stickers  Wall  Frosted Stickers.png',
        'transparent' => $base . '/images/products/product_26.jpg',
        'reflectorized' => $base . '/images/products/signage.jpg',
        'signage'     => $base . '/images/products/signage.jpg',
        'sintraboard' => $base . '/images/products/standeeflat.jpg',
        'standee'     => $base . '/images/products/standeeflat.jpg',
        'souvenir'    => $base . '/assets/images/services/default.png',
    ];
}

/**
 * Get service image URL for Orders/Notifications - exact same images as Services page.
 * @param string $service_type_or_name e.g. "T-Shirt Printing", "Tarpaulin", "Custom T-Shirt"
 * @return string URL path to image (same file as Services page)
 */
function get_service_image_url($service_type_or_name) {
    $cat = strtolower(trim(preg_replace('/\s+/', ' ', (string)$service_type_or_name)));
    if ($cat === '') return '/printflow/public/assets/images/services/default.png';

    $map = get_services_image_map();
    foreach ($map as $keyword => $img) {
        if (strpos($cat, $keyword) !== false) {
            return $img;
        }
    }

    return '/printflow/public/assets/images/services/default.png';
}

/**
 * Normalize PH-style phone to digits for comparison (63 + national mobile).
 */
function normalize_contact_phone_digits($raw) {
    $d = preg_replace('/\D/', '', (string)$raw);
    if ($d === '') {
        return '';
    }
    if (strlen($d) >= 11 && $d[0] === '0' && ($d[1] ?? '') === '9') {
        $d = '63' . substr($d, 1);
    } elseif (strlen($d) === 10 && $d[0] === '9') {
        $d = '63' . $d;
    }
    return $d;
}

/**
 * Whether email is already used on `users` or `customers` (case-insensitive).
 * Pass exclusions when updating the same account row.
 */
function email_in_use_across_accounts($email, $exclude_customer_id = null, $exclude_user_id = null) {
    $email = trim((string)$email);
    if ($email === '') {
        return false;
    }
    $u = db_query('SELECT user_id FROM users WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1', 's', [$email]);
    if (!empty($u)) {
        $uid = (int)$u[0]['user_id'];
        if ($exclude_user_id === null || $uid !== (int)$exclude_user_id) {
            return true;
        }
    }
    $c = db_query('SELECT customer_id FROM customers WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1', 's', [$email]);
    if (!empty($c)) {
        $cid = (int)$c[0]['customer_id'];
        if ($exclude_customer_id === null || $cid !== (int)$exclude_customer_id) {
            return true;
        }
    }
    return false;
}

/**
 * Whether normalized phone matches another row's `contact_number` on users or customers.
 */
function contact_phone_in_use_across_accounts($raw, $exclude_customer_id = null, $exclude_user_id = null) {
    $norm = normalize_contact_phone_digits($raw);
    if ($norm === '' || strlen($norm) < 10) {
        return false;
    }
    $users = db_query("SELECT user_id, contact_number FROM users WHERE contact_number IS NOT NULL AND TRIM(contact_number) <> ''", '', []);
    foreach ($users ?: [] as $row) {
        if ($exclude_user_id !== null && (int)$row['user_id'] === (int)$exclude_user_id) {
            continue;
        }
        if (normalize_contact_phone_digits($row['contact_number']) === $norm) {
            return true;
        }
    }
    $custs = db_query("SELECT customer_id, contact_number FROM customers WHERE contact_number IS NOT NULL AND TRIM(contact_number) <> ''", '', []);
    foreach ($custs ?: [] as $row) {
        if ($exclude_customer_id !== null && (int)$row['customer_id'] === (int)$exclude_customer_id) {
            continue;
        }
        if (normalize_contact_phone_digits($row['contact_number']) === $norm) {
            return true;
        }
    }
    return false;
}

/**
 * Application base path (e.g. /printflow). Uses AUTH_REDIRECT_BASE when defined.
 */
function pf_app_base_path(): string {
    return rtrim(defined('AUTH_REDIRECT_BASE') ? AUTH_REDIRECT_BASE : '/printflow', '/');
}

/**
 * Build an absolute app URL for a script under admin/ with optional query and fragment.
 *
 * @param string $script File name (e.g. orders_management.php) or path starting with admin/
 * @param array  $query  Query parameters
 * @param string|null $fragment Hash without leading #
 */
function pf_admin_url(string $script, array $query = [], ?string $fragment = null): string {
    $script = ltrim($script, '/');
    $path = (strpos($script, 'admin/') === 0) ? $script : ('admin/' . $script);
    $url = pf_app_base_path() . '/' . $path;
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }
    if ($fragment !== null && $fragment !== '') {
        $url .= '#' . ltrim($fragment, '#');
    }
    return $url;
}
