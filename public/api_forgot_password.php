<?php
/**
 * Forgot Password API
 * Sends a 6-digit reset code via email or SMS
 */

ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$type       = trim($_POST['type'] ?? '');
$identifier = trim($_POST['identifier'] ?? '');

if (empty($type) || empty($identifier)) {
    echo json_encode(['success' => false, 'message' => 'Please provide all required fields.']);
    exit;
}

if (!in_array($type, ['email', 'phone'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid reset type.']);
    exit;
}

if ($type === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

if ($type === 'phone') {
    $digitsOnly = preg_replace('/[\s\-\+\(\)]/', '', $identifier);
    if (!preg_match('/^[0-9]{10,15}$/', $digitsOnly)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid phone number (digits only, 10-15 digits).']);
        exit;
    }
    $identifier = $digitsOnly;
}

// IP-based rate limiting: max 10 reset requests per 10 minutes per IP (permissive for testing/shared networks)
require_once __DIR__ . '/../includes/rate_limiter.php';
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (RateLimiter::isBlocked('pwd_reset_ip', $client_ip, 10, 600)) {
    echo json_encode(['success' => false, 'message' => 'Too many requests from your network. Please try again after 10 minutes.']);
    exit;
}
RateLimiter::hit('pwd_reset_ip', $client_ip);

// Rate limiting: one request per identifier per 2 minutes
try {
    $rate = db_query(
        "SELECT COUNT(*) as cnt FROM password_resets WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
        's', [$identifier]
    );
    if (!empty($rate) && $rate[0]['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Please wait 2 minutes before requesting another reset code.']);
        exit;
    }
} catch (Exception $e) {
    // Table doesn't exist yet - continue to create it below
}

try {
    // Ensure table exists
    global $conn;
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM('User','Customer') NOT NULL,
        identifier VARCHAR(255) NOT NULL,
        reset_token VARCHAR(255) NOT NULL,
        used TINYINT(1) DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_token (reset_token),
        KEY idx_identifier (identifier),
        KEY idx_user (user_id, user_type)
    )");

    $user_found = null;
    $user_type  = null;

    if ($type === 'email') {
        // Users (Admin/Staff)
        $rows = db_query(
            "SELECT user_id, CONCAT(first_name,' ',last_name) AS full_name FROM users WHERE email = ? AND status IN ('Activated', 'Pending') LIMIT 1",
            's', [$identifier]
        );
        if (!empty($rows)) {
            $user_found = $rows[0];
            $user_type  = 'User';
        } else {
            // Customers - Note: customers table does NOT have a 'status' column
            $rows = db_query(
                "SELECT customer_id AS user_id, CONCAT(first_name,' ',last_name) AS full_name FROM customers WHERE email = ? LIMIT 1",
                's', [$identifier]
            );
            if (!empty($rows)) {
                $user_found = $rows[0];
                $user_type  = 'Customer';
            }
        }
    } else {
        // Phone-based reset (Customers only)
        $rows = db_query(
            "SELECT customer_id AS user_id, CONCAT(first_name,' ',last_name) AS full_name FROM customers WHERE contact_number = ? LIMIT 1",
            's', [$identifier]
        );
        if (!empty($rows)) {
            $user_found = $rows[0];
            $user_type  = 'Customer';
        }
    }

    // Explicit response if account doesn't exist (per user requirement)
    if (!$user_found) {
        $msg = ($type === 'email') ? 'Account not found. Please check your email address.' : 'Account not found. Please check your phone number.';
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // Generate secure reset token
    $reset_token = bin2hex(random_bytes(32)); // 64-character secure token
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes')); // Extended to 30 mins for link-based

    // Delete previous codes, insert fresh one
    db_execute("DELETE FROM password_resets WHERE user_id = ? AND user_type = ?", 'is', [$user_found['user_id'], $user_type]);
    db_execute(
        "INSERT INTO password_resets (user_id, user_type, identifier, reset_token, expires_at) VALUES (?,?,?,?,?)",
        'issss', [$user_found['user_id'], $user_type, $identifier, $reset_token, $expires_at]
    );

    // Send the code
    if ($type === 'email') {
        $name = htmlspecialchars($user_found['full_name']);
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        // Construct the reset link
        $reset_link = $protocol . $host . "/printflow/reset-password.php?token=" . $reset_token;

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
            body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;margin:0;padding:0;background:#f3f4f6}
            .wrap{max-width:520px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
            .hdr{background:linear-gradient(135deg,#32a1c4,#53C5E0);padding:28px 24px;text-align:center;color:#fff}
            .hdr h1{margin:0;font-size:22px;font-weight:700}
            .body{padding:28px 24px;color:#374151;line-height:1.6}
            .btn-wrap{text-align:center;margin:30px 0}
            .btn{background:#32a1c4;color:#ffffff !important;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;font-size:16px}
            .expiry{margin:15px 0 0;font-size:13px;color:#9ca3af;text-align:center}
            .warn{background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px;border-radius:6px;font-size:13px;color:#991b1b;margin-top:20px}
            .ftr{padding:20px;text-align:center;font-size:12px;color:#9ca3af;background:#f9fafb}
            .link-text{font-size:12px;word-break:break-all;color:#94a3b8;margin-top:20px;text-align:center}
        </style></head><body>
        <div class='wrap'>
            <div class='hdr'><h1>Reset Your Password</h1></div>
            <div class='body'>
                <p>Hello <strong>{$name}</strong>,</p>
                <p>We received a password reset request for your PrintFlow account. Click the button below to set a new password:</p>
                <div class='btn-wrap'>
                    <a href='{$reset_link}' class='btn'>Reset My Password</a>
                    <p class='expiry'>This link expires in 30 minutes &middot; One-time use ONLY</p>
                </div>
                <div class='link-text'>If the button doesn't work, copy and paste this link:<br>{$reset_link}</div>
                <div class='warn'><strong>Security notice:</strong> If you did not request this, please ignore this email. Your account remains secure.</div>
            </div>
            <div class='ftr'>&copy;" . date('Y') . " PrintFlow. All rights reserved.</div>
        </div></body></html>";

        $sent = send_email($identifier, 'PrintFlow - Reset Your Password', $html, true);
        if (!$sent) {
            db_execute("DELETE FROM password_resets WHERE user_id = ? AND user_type = ?", 'is', [$user_found['user_id'], $user_type]);
            echo json_encode(['success' => false, 'message' => 'Could not send the email. Please configure SMTP in includes/smtp_config.php with your Gmail App Password. See SMTP_SETUP_GUIDE.md for instructions.']);
            exit;
        }
    } else {
        // SMS still gets a link, but shortened or direct
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $reset_link = $protocol . $_SERVER['HTTP_HOST'] . "/printflow/reset-password.php?token=" . $reset_token;
        send_sms($identifier, "PrintFlow Reset: Click here to change your password: {$reset_link} (Expires in 30m)");
    }

    // Always log for development reference
    error_log("[PrintFlow] Password reset token for {$identifier}: {$reset_token}");

    $resp = ['success' => true, 'message' => 'A password reset link has been sent to your email.'];
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $resp['debug'] = ['reset_token' => $reset_token, 'expires_at' => $expires_at];
    }
    echo json_encode($resp);

} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
} catch (Error $e) {
    error_log("Forgot password fatal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
