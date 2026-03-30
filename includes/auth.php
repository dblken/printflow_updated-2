<?php
/**
 * Authentication System
 * PrintFlow - Printing Shop PWA
 *
 * Role redirects: change REDIRECT_BASE if the app is not at /printflow (e.g. on production).
 */

// Base path for redirects (no trailing slash). Change this if app lives at a different path.
if (!defined('AUTH_REDIRECT_BASE')) {
    define('AUTH_REDIRECT_BASE', '/printflow');
}

require_once __DIR__ . '/session_manager.php';
require_once __DIR__ . '/rate_limiter.php';

// Start session with security hardening (fingerprint, timeout, secure cookies)
SessionManager::start();

require_once __DIR__ . '/db.php';

// Try to include functions.php
$functions_path = __DIR__ . '/functions.php';
if (file_exists($functions_path)) {
    require_once $functions_path;
}

// Fallback: Define log_activity if it still doesn't exist to prevent fatal error
if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $details = '') {
        // Silently fail if function is missing, but don't crash the app
        error_log("Warning: log_activity function missing. Action: $action");
        return false;
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * Check if user is Admin
 * @return bool
 */
function is_admin() {
    return is_logged_in() && $_SESSION['user_type'] === 'Admin';
}

/**
 * Check if user is Staff
 * @return bool
 */
function is_staff() {
    return is_logged_in() && $_SESSION['user_type'] === 'Staff';
}

/**
 * Check if user is Manager
 * @return bool
 */
function is_manager() {
    return is_logged_in() && $_SESSION['user_type'] === 'Manager';
}

/**
 * Check if user is Admin or Manager
 * @return bool
 */
function is_admin_or_manager() {
    return is_logged_in() && in_array($_SESSION['user_type'], ['Admin', 'Manager']);
}

/**
 * Check if user is Customer
 * @return bool
 */
function is_customer() {
    return is_logged_in() && $_SESSION['user_type'] === 'Customer';
}

/**
 * Check if the current user has one of the specified roles
 * @param string|array $roles The role(s) to check
 * @return bool
 */
function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    $user_type = get_user_type();
    return in_array($user_type, $roles);
}

/**
 * Get current user ID
 * @return int|null
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user type
 * @return string|null
 */
function get_user_type() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Get current logged in user data
 * @return array|null
 */
function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $user_id = get_user_id();
    $user_type = get_user_type();
    
    if ($user_type === 'Customer') {
        $result = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$user_id]);
    } else {
        $result = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$user_id]);
    }
    
    return $result[0] ?? null;
}

/**
 * Login user (Admin/Staff)
 * @param string $email
 * @param string $password
 * @param bool $remember_me Whether to extend session cookie lifetime
 * @return array ['success' => bool, 'message' => string, 'redirect' => string]
 */
function login_user($email, $password, $remember_me = false) {
    // First check if email exists at all (regardless of status)
    $result = db_query("SELECT * FROM users WHERE email = ?", 's', [$email]);

    if (empty($result)) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    $user = $result[0];

    // Account status check — give specific error before password check
    if ($user['status'] === 'Disabled') {
        return ['success' => false, 'message' => 'Your account has been disabled. Please contact support.'];
    }
    if ($user['status'] === 'Suspended') {
        return ['success' => false, 'message' => 'Your account has been suspended. Please contact support.'];
    }
    // Only allow Activated or Pending status
    if (!in_array($user['status'], ['Activated', 'Pending'])) {
        return ['success' => false, 'message' => 'Your account is not active. Please contact support.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Set session variables
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['user_type'] = $user['role']; // 'Admin', 'Manager', or 'Staff'
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_status'] = $user['status'];
    $_SESSION['branch_id']   = $user['branch_id'] ?? null;

    // Force Manager (and Staff) to their assigned branch immediately so the
    // branch selector never shows "All Branches" for restricted accounts.
    if ($user['role'] === 'Manager' || $user['role'] === 'Staff') {
        $_SESSION['selected_branch_id'] = $user['branch_id'] ?? null;
    } else {
        // Admin: leave selected_branch_id alone (keep previous or default 'all')
        if (!isset($_SESSION['selected_branch_id'])) {
            $_SESSION['selected_branch_id'] = 'all';
        }
    }

    // Determine redirect based on role and status
    if ($user['role'] === 'Admin') {
        $redirect = AUTH_REDIRECT_BASE . '/admin/dashboard.php';
    } elseif ($user['role'] === 'Manager') {
        $redirect = AUTH_REDIRECT_BASE . '/manager/dashboard.php';
    } elseif ($user['status'] === 'Pending') {
        // Pending staff can only see profile to complete their information
        $redirect = AUTH_REDIRECT_BASE . '/staff/profile.php';
    } else {
        $redirect = AUTH_REDIRECT_BASE . '/staff/dashboard.php';
    }

    SessionManager::regenerate();
    if ($remember_me) {
        SessionManager::applyRememberMe(REMEMBER_ME_STAFF_DAYS);
    }
    return [
        'success' => true,
        'message' => 'Login successful',
        'redirect' => $redirect
    ];
}

/**
 * Login customer
 * @param string $email
 * @param string $password
 * @param bool $remember_me Whether to extend session cookie lifetime
 * @return array ['success' => bool, 'message' => string, 'redirect' => string]
 */
function login_customer($email, $password, $remember_me = false) {
    $result = db_query("SELECT * FROM customers WHERE email = ?", 's', [$email]);

    // Also try phone-based accounts (contact_number match or phone@phone.local email)
    if (empty($result)) {
        $phone_clean = preg_replace('/[\s\-\(\)]/', '', $email);
        if (preg_match('/^(\+63|0)9\d{9}$/', $phone_clean)) {
            // Try by contact_number
            $result = db_query("SELECT * FROM customers WHERE contact_number = ?", 's', [$phone_clean]);
            if (empty($result)) {
                // Try by generated email placeholder
                $result = db_query("SELECT * FROM customers WHERE email = ?", 's', [$phone_clean . '@phone.local']);
            }
        }
    }

    if (empty($result)) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    $customer = $result[0];

    // Account status check (if the customers table has a status column)
    if (isset($customer['status'])) {
        if ($customer['status'] === 'Disabled') {
            return ['success' => false, 'message' => 'Your account has been disabled. Please contact support.'];
        }
        if ($customer['status'] === 'Suspended') {
            return ['success' => false, 'message' => 'Your account has been suspended. Please contact support.'];
        }
    }

    if (!password_verify($password, $customer['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $customer['customer_id'];
    $_SESSION['user_type'] = 'Customer';
    $_SESSION['user_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
    $_SESSION['user_email'] = $customer['email'];

    SessionManager::regenerate();
    if ($remember_me) {
        SessionManager::applyRememberMe(REMEMBER_ME_CUSTOMER_DAYS);
    }
    // Load persisted cart from database
    if (function_exists('load_customer_cart_into_session')) {
        load_customer_cart_into_session($customer['customer_id']);
    }
    return [
        'success' => true,
        'message' => 'Login successful',
        'redirect' => AUTH_REDIRECT_BASE . '/customer/services.php'
    ];
}

/**
 * Login or register customer using Google profile (no password). Finds by email or creates new.
 * @param string $email
 * @param string $first_name
 * @param string $last_name
 * @return array ['success' => bool, 'message' => string, 'redirect' => string]
 */
function login_customer_by_google($email, $first_name, $last_name) {
    $email = trim($email);
    $first_name = trim($first_name) ?: 'User';
    $last_name = trim($last_name) ?: '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email from Google'];
    }
    $existing = db_query("SELECT * FROM customers WHERE email = ?", 's', [$email]);
    if (!empty($existing)) {
        $customer = $existing[0];
        $_SESSION['user_id'] = $customer['customer_id'];
        $_SESSION['user_type'] = 'Customer';
        $_SESSION['user_name'] = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
        $_SESSION['user_email'] = $customer['email'];
        SessionManager::regenerate();
        if (function_exists('load_customer_cart_into_session')) {
            load_customer_cart_into_session($customer['customer_id']);
        }
        return ['success' => true, 'message' => 'Login successful', 'redirect' => AUTH_REDIRECT_BASE . '/customer/services.php'];
    }
    if (email_in_use_across_accounts($email)) {
        return [
            'success' => false,
            'message' => 'This email is already used for a staff or admin account. Sign in with your work credentials instead of Google.',
        ];
    }
    $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $sql = "INSERT INTO customers (first_name, middle_name, last_name, dob, gender, email, contact_number, password_hash) VALUES (?, '', ?, NULL, NULL, ?, NULL, ?)";
    $cid = db_execute($sql, 'ssss', [$first_name, $last_name, $email, $password_hash]);
    if (!$cid) {
        return ['success' => false, 'message' => 'Could not create account. Please try again.'];
    }
    $_SESSION['user_id'] = $cid;
    $_SESSION['user_type'] = 'Customer';
    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
    $_SESSION['user_email'] = $email;
    SessionManager::regenerate();
    if (function_exists('load_customer_cart_into_session')) {
        load_customer_cart_into_session($cid);
    }
    return ['success' => true, 'message' => 'Account created', 'redirect' => AUTH_REDIRECT_BASE . '/customer/services.php'];
}

/**
 * Unified login function (detects user type automatically)
 * @param string $email
 * @param string $password
 * @param bool $remember_me
 * @return array
 */
function login($email, $password, $remember_me = false) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Rate limit: block IP after 5 failed attempts within 15 minutes (900 seconds)
    if (RateLimiter::isBlocked('login', $ip, 5, 900)) {
        return ['success' => false, 'message' => 'Too many login attempts. Your access has been temporarily locked for 15 minutes. Please try again later.'];
    }

    // Try customer login first
    $customer_result = login_customer($email, $password, $remember_me);
    if ($customer_result['success']) {
        RateLimiter::clear('login', $ip);
        return $customer_result;
    }

    // Try user (Admin/Staff) login
    $user_result = login_user($email, $password, $remember_me);
    if ($user_result['success']) {
        RateLimiter::clear('login', $ip);
        return $user_result;
    }

    // Record failed attempt for rate limiting
    RateLimiter::hit('login', $ip);
    return ['success' => false, 'message' => 'Invalid email or password'];
}


/**
 * Register a new customer
 * @param array $data
 * @return array ['success' => bool, 'message' => string]
 */
function register_customer($data) {
    if (email_in_use_across_accounts($data['email'] ?? '')) {
        return ['success' => false, 'message' => 'This email is already in use. Please sign in.'];
    }
    $cn = $data['contact_number'] ?? '';
    if ($cn !== '' && $cn !== null && contact_phone_in_use_across_accounts($cn)) {
        return ['success' => false, 'message' => 'This phone number is already in use. Please sign in or use a different number.'];
    }

    // Hash password
    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Insert customer
    $sql = "INSERT INTO customers (first_name, middle_name, last_name, dob, gender, email, contact_number, password_hash) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $result = db_execute($sql, 'ssssssss', [
        $data['first_name'],
        $data['middle_name'] ?? null,
        $data['last_name'],
        $data['dob'] ?? null,
        $data['gender'] ?? null,
        $data['email'],
        $data['contact_number'] ?? null,
        $password_hash
    ]);
    
    if ($result) {
        // Auto-login after registration
        $_SESSION['user_id'] = $result;
        $_SESSION['user_type'] = 'Customer';
        $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
        $_SESSION['user_email'] = $data['email'];
        SessionManager::regenerate();
        return ['success' => true, 'message' => 'Registration successful'];
    }

    return ['success' => false, 'message' => 'Registration failed. Please try again.'];
}

/**
 * Register customer directly via email or phone (no validation)
 * @param string $type 'email' or 'phone'
 * @param string $identifier The email or phone number
 * @param string $password The password
 * @return array ['success' => bool, 'message' => string]
 */
function register_customer_direct($type, $identifier, $password) {
    // Determine email and contact_number
    if ($type === 'email') {
        $email = $identifier;
        $contact_number = null;
    } else {
        $email = $identifier . '@phone.local'; // placeholder for NOT NULL constraint
        $contact_number = $identifier;
    }

    // Check if already exists
    $existing = db_query("SELECT customer_id, email_verified FROM customers WHERE email = ?", 's', [$email]);
    if (!empty($existing)) {
        if ($existing[0]['email_verified'] == 0) {
            // Delete unverified account to allow retry
            db_execute("DELETE FROM customers WHERE customer_id = ?", 'i', [$existing[0]['customer_id']]);
        } else {
            return ['success' => false, 'message' => 'This email is already in use. Please sign in.'];
        }
    }

    if ($contact_number) {
        $existing2 = db_query("SELECT customer_id, email_verified FROM customers WHERE contact_number = ?", 's', [$contact_number]);
        if (!empty($existing2)) {
            if ($existing2[0]['email_verified'] == 0) {
                db_execute("DELETE FROM customers WHERE customer_id = ?", 'i', [$existing2[0]['customer_id']]);
            } else {
                return ['success' => false, 'message' => 'Phone number already registered. Please login.'];
            }
        }
    }

    if (email_in_use_across_accounts($email)) {
        return ['success' => false, 'message' => 'This email is already in use (customer or staff account). Please sign in or use a different email.'];
    }
    if ($contact_number && contact_phone_in_use_across_accounts($contact_number)) {
        return ['success' => false, 'message' => 'This phone number is already in use on another account. Please sign in or use a different number.'];
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO customers (first_name, middle_name, last_name, dob, gender, email, contact_number, password_hash, is_profile_complete, email_verified) 
            VALUES (?, '', ?, NULL, NULL, ?, ?, ?, 0, 0)";

    $result = db_execute($sql, 'sssss', [
        'Customer',   // placeholder first_name
        '',           // placeholder last_name
        $email,
        $contact_number,
        $password_hash
    ]);

    if ($result) {
        // Generate OTP
        $otp = (string)rand(100000, 999999);
        $now = date('Y-m-d H:i:s');
        $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

        // Save OTP to database
        db_execute("UPDATE customers SET otp_code = ?, otp_expiry = ?, otp_last_sent = ? WHERE customer_id = ?", 'sssi', [$otp, $expiry, $now, $result]);

        $otp_sent = false;
        $mail_res = null;
        if ($contact_number) {
            // Phone registration: send OTP via SMS (Philippines)
            require_once __DIR__ . '/email_sms_config.php';
            if (defined('SMS_ENABLED') && SMS_ENABLED && function_exists('send_sms')) {
                $phone_e164 = preg_replace('/\D/', '', $contact_number);
                if (preg_match('/^0(\d{10})$/', $phone_e164, $m)) $phone_e164 = '63' . $m[1];
                elseif (strlen($phone_e164) === 10 && $phone_e164[0] === '9') $phone_e164 = '63' . $phone_e164;
                $otp_sent = send_sms('+' . $phone_e164, "PrintFlow: Your verification code is {$otp}. Valid for 10 minutes.");
            }
            if (!$otp_sent) {
                // Fallback: email to placeholder (won't work, but keeps flow; or log)
                require_once __DIR__ . '/otp_mailer.php';
                $mail_res = send_otp_email($email, $otp);
                $otp_sent = isset($mail_res['success']) && $mail_res['success'] === true;
            }
        } else {
            // Email registration: send OTP via email
            require_once __DIR__ . '/otp_mailer.php';
            $mail_res = send_otp_email($email, $otp);
            $otp_sent = isset($mail_res['success']) && $mail_res['success'] === true;
        }

        if ($otp_sent) {
            // Auto-login after registration (Optional - we can keep it or remove it)
            // But if we want them to verify first, maybe don't set user_id yet?
            // Existing flow expects them to be "half-logged in" or just have session markers.
            
            $_SESSION['otp_pending_email'] = $email;
            $_SESSION['otp_user_type'] = 'Customer';
            $_SESSION['otp_resend_attempts'] = 0;

            return ['success' => true, 'message' => 'Registration successful! Verification code sent.'];
        } else {
            // ROLLBACK: Delete the customer record if OTP delivery failed
            db_execute("DELETE FROM customers WHERE customer_id = ?", 'i', [$result]);
            $msg = $contact_number
                ? 'Failed to send SMS. Ensure SMS is configured (Semaphore for PH).'
                : ('Failed to send verification email: ' . ($mail_res['message'] ?? 'Unknown error'));
            return ['success' => false, 'message' => $msg];
        }
    }

    return ['success' => false, 'message' => 'Registration failed. Please try again.'];
}

/**
 * Check if customer profile is complete (has real name, etc.)
 * @param int|null $customer_id
 * @return bool
 */
function is_profile_complete($customer_id = null) {
    if ($customer_id === null) $customer_id = get_user_id();
    if (!$customer_id || get_user_type() !== 'Customer') return true;
    
    $result = db_query("SELECT is_profile_complete FROM customers WHERE customer_id = ?", 'i', [$customer_id]);
    if (empty($result)) return true;
    return (bool)$result[0]['is_profile_complete'];
}

/**
 * Require authentication (redirect to login if not logged in).
 * Sets no-cache headers and handles session timeout redirect.
 */
function require_auth() {
    SessionManager::setNoCacheHeaders();
    if (!is_logged_in()) {
        if (SessionManager::wasTimedOut()) {
            header('Location: ' . AUTH_REDIRECT_BASE . '/public/login.php?timeout=1');
        } else {
            header('Location: ' . AUTH_REDIRECT_BASE . '/');
        }
        exit();
    }
}

/**
 * Require specific role (redirect if user doesn't have the role)
 * @param string|array $roles Allowed roles (e.g., 'Admin' or ['Admin', 'Staff'])
 */
function require_role($roles) {
    require_auth();
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    $user_type = get_user_type();
    
    if (!in_array($user_type, $roles)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>');
    }
}

/**
 * Redirect Admin/Manager/Staff to their respective dashboards if they hit a public page.
 */
function redirect_admin_staff_from_public() {
    if (is_logged_in()) {
        $user_type = get_user_type();
        if ($user_type === 'Admin') {
            header('Location: ' . AUTH_REDIRECT_BASE . '/admin/dashboard.php');
            exit();
        }
        if ($user_type === 'Manager') {
            header('Location: ' . AUTH_REDIRECT_BASE . '/manager/dashboard.php');
            exit();
        }
        if ($user_type === 'Staff') {
            header('Location: ' . AUTH_REDIRECT_BASE . '/staff/dashboard.php');
            exit();
        }
    }
}

/**
 * Redirect any logged-in user away from the public home (/printflow/).
 * Use only on the landing page so customers can still use products, FAQ, etc.
 */
function redirect_logged_in_from_landing_page(): void {
    if (!is_logged_in()) {
        return;
    }
    SessionManager::setNoCacheHeaders();
    $user_type = get_user_type();
    if ($user_type === 'Admin') {
        header('Location: ' . AUTH_REDIRECT_BASE . '/admin/dashboard.php', true, 302);
        exit();
    }
    if ($user_type === 'Manager') {
        header('Location: ' . AUTH_REDIRECT_BASE . '/manager/dashboard.php', true, 302);
        exit();
    }
    if ($user_type === 'Staff') {
        header('Location: ' . AUTH_REDIRECT_BASE . '/staff/dashboard.php', true, 302);
        exit();
    }
    if ($user_type === 'Customer') {
        header('Location: ' . AUTH_REDIRECT_BASE . '/customer/services.php', true, 302);
        exit();
    }
}

/**
 * Generate CSRF token
 * @return string
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token HTML input
 * @return string
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

