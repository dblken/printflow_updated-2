<?php
/**
 * process_register.php
 * Handles User (Admin/Staff) Registration
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any existing OTP session data to prevent "stickiness"
    unset($_SESSION['otp_pending_email']);
    unset($_SESSION['otp_user_type']);
    unset($_SESSION['otp_error']);
    unset($_SESSION['otp_success']);

    // 1. Validate form inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = sanitize($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role       = $_POST['role'] ?? 'Staff';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        redirect('register.php?error=All fields are required');
    }

    // Name validation
    if (!preg_match("/^[A-Za-z]+( [A-Za-z]+)*$/", $first_name) || !preg_match("/^[A-Za-z]+( [A-Za-z]+)*$/", $last_name)) {
        redirect('register.php?error=' . urlencode('Names must contain only letters.'));
    }
    if (strlen($first_name) < 2 || strlen($first_name) > 50 || strlen($last_name) < 2 || strlen($last_name) > 50) {
        redirect('register.php?error=' . urlencode('Names must be between 2 and 50 characters.'));
    }

    // Auto-capitalize
    $first_name = ucfirst($first_name);
    $last_name = ucfirst($last_name);

    // 2. Validate email
    if (strlen($email) > 254 || strpos($email, ' ') !== false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('register.php?error=' . urlencode('Invalid email address.'));
    }
    // Require at least 2 characters after the last dot in domain
    if (!preg_match('/^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/', $email)) {
        redirect('register.php?error=' . urlencode('Email domain extension must be at least 2 characters (e.g., .com, .org).'));
    }

    // 3. Server-side password complexity validation
    $pw_errors = [];
    if (strlen($password) < 8) $pw_errors[] = 'at least 8 characters';
    if (strlen($password) > 64) $pw_errors[] = 'at most 64 characters';
    if (!preg_match('/[A-Z]/', $password)) $pw_errors[] = 'an uppercase letter';
    if (!preg_match('/[a-z]/', $password)) $pw_errors[] = 'a lowercase letter';
    if (!preg_match('/[0-9]/', $password)) $pw_errors[] = 'a number';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $pw_errors[] = 'a special character';
    if (strpos($password, ' ') !== false) $pw_errors[] = 'no spaces';
    if (!empty($pw_errors)) {
        redirect('register.php?error=' . urlencode('Password must contain: ' . implode(', ', $pw_errors) . '.'));
    }

    // 4. Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Check if email exists in users (pending row can be replaced)
    $existing = db_query("SELECT user_id, email_verified FROM users WHERE email = ?", 's', [$email]);
    if (!empty($existing)) {
        if (isset($existing[0]['email_verified']) && $existing[0]['email_verified'] == 0) {
            // Delete incomplete registration to allow re-registration
            db_execute("DELETE FROM users WHERE user_id = ?", 'i', [$existing[0]['user_id']]);
        } else {
            redirect('register.php?error=Email already exists');
        }
    }

    // Same email cannot be a customer account
    if (email_in_use_across_accounts($email, null, null)) {
        redirect('register.php?error=' . urlencode('This email is already registered as a customer. Please sign in with that account or use a different email.'));
    }

    // 3. Insert user record with email_verified = 0
    $sql = "INSERT INTO users (first_name, last_name, email, password_hash, role, status, email_verified) VALUES (?, ?, ?, ?, ?, 'Pending', 0)";
    $user_id = db_execute($sql, 'sssss', [$first_name, $last_name, $email, $password_hash, $role]);

    if ($user_id) {
        // 4. Generate OTP
        $otp = (string)rand(100000, 999999);

        // 5. Set expiration time (5 minutes)
        $now    = date('Y-m-d H:i:s');
        $expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

        // 6. Save OTP and expiry in users table
        db_execute("UPDATE users SET otp_code = ?, otp_expiry = ?, otp_last_sent = ? WHERE user_id = ?", 'sssi', [$otp, $expiry, $now, $user_id]);

        // 7. Send OTP email
        $mail_result = send_otp_email($email, $otp);
        if (isset($mail_result['success']) && $mail_result['success'] === true) {
            $_SESSION['otp_pending_email'] = $email;
            $_SESSION['otp_user_type'] = 'User';
            // 8. Redirect to verify_email.php
            redirect('verify_email.php?success=' . urlencode('Verification code sent to your email'));
        } else {
            // Revert pending user if mail fails
            db_execute("DELETE FROM users WHERE user_id = ?", 'i', [$user_id]);
            redirect('register.php?error=' . urlencode('Failed to send verification email. ' . ($mail_result['message'] ?? 'Please try again.')));
        }
    } else {
        redirect('register.php?error=Registration failed');
    }
} else {
    redirect('register.php');
}
