<?php
/**
 * Login Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    $user_type = get_user_type();
    if ($user_type === 'Admin') {
        redirect(AUTH_REDIRECT_BASE . '/admin/dashboard.php');
    } elseif ($user_type === 'Manager') {
        redirect(AUTH_REDIRECT_BASE . '/manager/dashboard.php');
    } elseif ($user_type === 'Staff') {
        redirect(AUTH_REDIRECT_BASE . '/staff/dashboard.php');
    } else {
        redirect(AUTH_REDIRECT_BASE . '/customer/services.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
    $field_errors = ['email' => '', 'password' => ''];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
        $field_errors['password'] = $error;
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

        if (empty($email)) {
            $field_errors['email'] = 'Email is required.';
            $error = 'Please enter your email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = 'Please enter a valid email address.';
            $error = 'Invalid email format.';
        }

        if (empty($password)) {
            $field_errors['password'] = 'Password is required.';
            if (empty($error)) {
                $error = 'Please enter your password.';
            }
        }

        if (!$error) {
            $result = login($email, $password, $remember_me);

            if ($result['success']) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'redirect' => $result['redirect']
                    ]);
                    exit;
                }
                redirect($result['redirect']);
            } else {
                $error = $result['message'];
                $field_errors['password'] = $result['message'];
            }
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $error ?: 'Login failed.',
            'field_errors' => $field_errors
        ]);
        exit;
    }

    // Redirect back with modal params so modal can open with error (for modal flow)
    if ($error) {
        $return_path = '/printflow/';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $ref = $_SERVER['HTTP_REFERER'];
            if (strpos($ref, '/printflow/') !== false) {
                $parsed = parse_url($ref);
                $return_path = isset($parsed['path']) ? $parsed['path'] : $return_path;
            }
        }
        $sep = (strpos($return_path, '?') !== false) ? '&' : '?';
        redirect($return_path . $sep . 'auth_modal=login&error=' . urlencode($error));
    }
}

$page_title = 'Login - PrintFlow';
$base_url = get_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include __DIR__ . '/../includes/favicon_links.php'; ?>
    <!-- Updated: <?php echo date('Y-m-d H:i:s'); ?> -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #00151b;
            padding: 1rem;
        }
        .auth-card {
            background: white;
            border-radius: 16px;
            padding: 40px 32px 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
        }
        .auth-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .auth-icon svg { width: 22px; height: 22px; color: white; }
        .auth-title {
            font-size: 22px; font-weight: 700; color: #111827;
            text-align: center; margin-bottom: 4px;
        }
        .auth-subtitle {
            font-size: 14px; color: #9ca3af;
            text-align: center; margin-bottom: 28px;
        }
        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
            padding: 10px 14px; border-radius: 10px; font-size: 13px;
            margin-bottom: 20px; font-weight: 500;
        }
        .alert-success {
            background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d;
            padding: 10px 14px; border-radius: 10px; font-size: 13px;
            margin-bottom: 20px; font-weight: 500;
        }
        .form-group { margin-bottom: 16px; position: relative; }
        .form-input {
            width: 100%; padding: 12px 14px; font-size: 14px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            outline: none; transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit; color: #111827; background: #fff;
        }
        .form-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .form-input.is-invalid { border-color: #f87171 !important; box-shadow: 0 0 0 3px rgba(248,113,113,0.1) !important; }
        .form-input.is-valid { border-color: #4ade80 !important; box-shadow: 0 0 0 3px rgba(74,222,128,0.1) !important; }
        .field-error-login { margin: 4px 0 0; font-size: 12px; color: #ef4444; min-height: 16px; }
        .form-input::placeholder { color: #b0b5bf; }
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9ca3af;
            display: flex; align-items: center; padding: 2px;
        }
        .password-toggle:hover { color: #6b7280; }
        .remember-row {
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 24px;
        }
        .remember-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: #6b7280; cursor: pointer;
        }
        .remember-label input[type="checkbox"] {
            width: 16px; height: 16px; accent-color: #6366f1;
            border-radius: 4px; cursor: pointer;
        }
        .forgot-link {
            font-size: 13px; color: #6366f1; text-decoration: none; font-weight: 500;
        }
        .forgot-link:hover { color: #4f46e5; }
        .btn-submit {
            width: 100%; padding: 12px; font-size: 15px; font-weight: 600;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: white; border: none; border-radius: 10px; cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            font-family: inherit;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99,102,241,0.35);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-inline-loader { display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-inline-loader .spinner {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #53C5E0;
            animation: pfBtnSpin .8s linear infinite;
        }
        @keyframes pfBtnSpin { to { transform: rotate(360deg); } }
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #e5e7eb;
        }
        .divider span {
            font-size: 11px; color: #9ca3af; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;
        }
        .social-btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 11px; font-size: 14px; font-weight: 500;
            border: 1.5px solid #e5e7eb; border-radius: 10px; background: white;
            cursor: pointer; transition: all 0.2s; font-family: inherit; color: #374151;
        }
        .social-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .social-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
        .footer-text {
            text-align: center; margin-top: 24px; font-size: 13px; color: #9ca3af;
        }
        .footer-text a {
            color: #6366f1; font-weight: 600; text-decoration: none;
        }
        .footer-text a:hover { color: #4f46e5; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
    </div>
    <h1 class="auth-title">Sign in to PrintFlow</h1>
    <p class="auth-subtitle">Welcome back! Please sign in to continue</p>
    
    <!-- DEBUG: If you see this, the page is updated! -->

    <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="login-form" novalidate>
        <?php echo csrf_field(); ?>

        <div class="form-group">
            <input type="email" id="email" name="email" class="form-input" placeholder="Email address" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="email" maxlength="100">
            <p class="field-error-login" id="login-email-error"></p>
        </div>

        <div class="form-group">
            <div class="password-wrapper">
                <input type="password" id="password" name="password" class="form-input" placeholder="Password" required autocomplete="current-password" maxlength="100">
                <button type="button" class="password-toggle" onclick="togglePassword()">
                    <svg id="eye-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            <p class="field-error-login" id="login-pw-error"></p>
        </div>

        <div class="form-group row" style="display:flex; justify-content:space-between; align-items:center;">
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                <input type="checkbox" name="remember_me" value="1" style="width:1rem; height:1rem;">
                <span style="font-weight:400;">Remember me</span>
            </label>
            <a href="<?php echo htmlspecialchars($base_url); ?>/login/?action=forgot" class="forgot-link">Forgot Password?</a>
        </div>
        
        <button type="submit" class="btn-submit">Sign In</button>
    </form>

    <div class="divider"><span>or continue with</span></div>

    <button type="button" class="social-btn" onclick="signInWithGoogle()">
        <svg viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Google
    </button>

    <p class="footer-text">
        Don't have an account? <a href="<?php echo $url_register ?? '/printflow/register/'; ?>">Start your free trial</a>
    </p>
</div>

<!-- Forgot Password Modal -->
<style>
/* ── Backdrop & Modal Shell ─────────────────────────────── */
.forgot-modal-backdrop {
    position: fixed; inset: 0; z-index: 99998;
    background: rgba(0,0,0,0.55);
    opacity: 0; visibility: hidden;
    transition: opacity 0.2s, visibility 0.2s;
}
.forgot-modal-backdrop.is-open { opacity: 1; visibility: visible; }

.forgot-modal {
    position: fixed; left: 50%; top: 50%;
    transform: translate(-50%, -50%);
    z-index: 99999; width: 100%; max-width: 460px;
    max-height: 92vh; overflow-y: auto;
    background: #fff; border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    opacity: 0; visibility: hidden;
    transition: opacity 0.2s, visibility 0.2s;
}
.forgot-modal.is-open { opacity: 1; visibility: visible; }

.forgot-modal-close {
    position: absolute; right: 1rem; top: 1rem;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    color: #9ca3af; background: none; border: none;
    border-radius: 8px; cursor: pointer;
    font-size: 24px; line-height: 1;
    transition: color 0.15s, background 0.15s;
}
.forgot-modal-close:hover { color: #111827; background: #f3f4f6; }

.forgot-modal-inner { padding: 2.5rem 2rem 2rem; }

/* ── Typography ─────────────────────────────────────────── */
.forgot-modal h2 {
    margin: 0 0 0.4rem; font-size: 22px; font-weight: 700;
    color: #111827; text-align: center;
}
.forgot-modal-sub {
    margin: 0 0 1.5rem; font-size: 14px; color: #6b7280;
    text-align: center; line-height: 1.5;
}

/* ── Tabs ───────────────────────────────────────────────── */
.forgot-tabs {
    display: flex; gap: 0; margin-bottom: 1.25rem;
    border-radius: 10px; overflow: hidden;
    border: 1.5px solid #e5e7eb;
}
.forgot-tab {
    flex: 1; padding: 10px 16px; text-align: center;
    font-size: 14px; font-weight: 600;
    background: #fff; color: #6b7280;
    border: none; cursor: pointer; transition: all 0.18s;
    font-family: inherit;
}
.forgot-tab.active {
    background: linear-gradient(135deg, #6366f1, #7c3aed); color: #fff;
}
.forgot-tab:not(.active):hover { background: #f9fafb; color: #111827; }

/* ── Form Elements ──────────────────────────────────────── */
.forgot-form-group { margin-bottom: 1.1rem; }
.forgot-form-group label {
    display: block; font-size: 13px; font-weight: 600;
    color: #374151; margin-bottom: 6px;
}
.forgot-form-input {
    width: 100%; padding: 11px 14px; font-size: 14px;
    border: 1.5px solid #e5e7eb; border-radius: 10px;
    outline: none; transition: border-color 0.18s, box-shadow 0.18s;
    font-family: inherit; color: #111827; background: #fff;
    box-sizing: border-box;
}
.forgot-form-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.forgot-form-input:disabled { background: #f9fafb; color: #9ca3af; cursor: not-allowed; }
.forgot-form-input::placeholder { color: #b0b5bf; }

.forgot-btn-submit {
    width: 100%; padding: 12px; font-size: 15px; font-weight: 600;
    background: linear-gradient(135deg, #6366f1, #7c3aed);
    color: #fff; border: none; border-radius: 10px; cursor: pointer;
    transition: opacity 0.15s, transform 0.1s; font-family: inherit;
    margin-top: 0.25rem;
}
.forgot-btn-submit:hover { opacity: 0.92; transform: translateY(-1px); }
.forgot-btn-submit:active { transform: translateY(0); opacity: 1; }
.forgot-btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* ── Alerts ─────────────────────────────────────────────── */
.forgot-alert {
    padding: 11px 14px; border-radius: 10px;
    font-size: 13px; font-weight: 500; margin-bottom: 1rem;
}
.forgot-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.forgot-alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
.forgot-alert-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

/* ── Field Error Messages ───────────────────────────────── */
.fm-field-error {
    font-size: 12px; color: #ef4444;
    margin-top: 4px; min-height: 16px;
}

/* ── Countdown ──────────────────────────────────────────── */
.fm-countdown {
    display: flex; align-items: center; gap: 6px;
    padding: 8px 12px; background: #f0fdf4;
    border: 1px solid #bbf7d0; border-radius: 8px;
    font-size: 13px; color: #15803d; margin-bottom: 1.1rem;
    transition: color 0.3s, background 0.3s, border-color 0.3s;
}
.fm-countdown.expired {
    background: #fef2f2; border-color: #fecaca; color: #b91c1c;
}
.fm-timer-value { font-weight: 700; font-variant-numeric: tabular-nums; }

/* ── Locked Identifier Row ──────────────────────────────── */
.fm-locked-field {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; background: #f9fafb;
    border: 1.5px solid #e5e7eb; border-radius: 10px; gap: 8px;
}
.fm-locked-text {
    font-size: 14px; color: #374151; font-weight: 500;
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fm-change-btn {
    background: none; border: none; color: #6366f1;
    font-size: 13px; font-weight: 600; cursor: pointer;
    padding: 0; flex-shrink: 0; font-family: inherit;
}
.fm-change-btn:hover { color: #4f46e5; }

/* ── Code Input ─────────────────────────────────────────── */
.fm-code-input {
    text-align: center; font-size: 26px; font-weight: 700;
    letter-spacing: 14px; font-family: 'Courier New', monospace;
    padding-left: 20px;
}

/* ── Password Field with Toggle ─────────────────────────── */
.fm-pw-wrapper { position: relative; }
.fm-pw-wrapper .forgot-form-input { padding-right: 44px; }
.fm-pw-toggle {
    position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: #9ca3af; display: flex; align-items: center;
    padding: 2px; line-height: 0;
}
.fm-pw-toggle:hover { color: #6b7280; }

/* ── Strength Bars ──────────────────────────────────────── */
.fm-strength-wrap {
    display: flex; align-items: center; gap: 8px; margin-top: 7px;
}
.fm-strength-bars { display: flex; gap: 4px; flex: 1; }
.fm-bar {
    height: 4px; flex: 1; border-radius: 2px;
    background: #e5e7eb; transition: background-color 0.2s;
}
.fm-strength-label {
    font-size: 12px; font-weight: 600;
    min-width: 38px; text-align: right;
}

/* ── Password Requirements ──────────────────────────────── */
.fm-reqs { display: flex; flex-direction: column; gap: 4px; margin-top: 7px; }
.fm-req {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px; color: #9ca3af; transition: color 0.15s;
}
.fm-req svg { flex-shrink: 0; }

/* ── Match Indicator ────────────────────────────────────── */
.fm-match-ok { font-size: 12px; color: #10b981; font-weight: 600; margin-top: 4px; }

/* ── Success Step ───────────────────────────────────────── */
.fm-success-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #6366f1, #7c3aed);
    border-radius: 50%; display: flex;
    align-items: center; justify-content: center;
    margin: 0 auto 1.25rem; color: #fff;
}

/* ── Back Link ──────────────────────────────────────────── */
.forgot-back {
    text-align: center; margin-top: 1.1rem;
    font-size: 13px; color: #6b7280;
}
.forgot-back a { color: #6366f1; text-decoration: none; font-weight: 600; }
.forgot-back a:hover { color: #4f46e5; }
</style>

<!-- Backdrop -->
<div class="forgot-modal-backdrop" id="forgot-modal-backdrop"></div>

<!-- Modal -->
<div class="forgot-modal" id="forgot-modal" role="dialog" aria-labelledby="forgot-modal-title" aria-modal="true">
    <button type="button" class="forgot-modal-close" onclick="closeForgotModal()" aria-label="Close">&times;</button>
    <div class="forgot-modal-inner">

        <!-- ── Step 1: Enter email / phone ─────────────────── -->
        <div id="fm-step1">
            <h2 id="forgot-modal-title">Reset Password</h2>
            <p class="forgot-modal-sub">Enter your registered email address or phone number</p>

            <div id="fm-msg1"></div>

            <div class="forgot-tabs">
                <button type="button" class="forgot-tab active" id="fm-tab-email" onclick="fmSwitchTab('email')">Email</button>
                <button type="button" class="forgot-tab"        id="fm-tab-phone" onclick="fmSwitchTab('phone')">Phone</button>
            </div>

            <form id="fm-form1" onsubmit="fmSendCode(event)" novalidate>
                <input type="hidden" id="fm-type" value="email">
                <div class="forgot-form-group">
                    <label id="fm-id-label" for="fm-identifier">Email Address</label>
                    <input type="email" id="fm-identifier" class="forgot-form-input"
                           placeholder="you@example.com" autocomplete="email">
                    <p class="fm-field-error" id="fm-id-error"></p>
                </div>
                <button type="submit" class="forgot-btn-submit" id="fm-send-btn">
                    Send Reset Code
                </button>
            </form>

            <p class="forgot-back"><a href="#" onclick="closeForgotModal(); return false;">← Back to login</a></p>
        </div>

        <!-- ── Step 2: Enter code + new password ───────────── -->
        <div id="fm-step2" style="display:none">
            <h2>Enter Reset Code</h2>
            <p class="forgot-modal-sub" id="fm-step2-sub">Check your inbox for a 6-digit code.</p>

            <div id="fm-msg2"></div>

            <!-- Countdown -->
            <div class="fm-countdown" id="fm-countdown">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Code expires in <span id="fm-timer" class="fm-timer-value">15:00</span>
            </div>

            <form id="fm-form2" onsubmit="fmResetPassword(event)" novalidate>

                <!-- Locked identifier -->
                <div class="forgot-form-group">
                    <label>Sent to</label>
                    <div class="fm-locked-field">
                        <span id="fm-locked-id" class="fm-locked-text"></span>
                        <button type="button" class="fm-change-btn" onclick="fmGoStep1()">Change</button>
                    </div>
                </div>

                <!-- Code input -->
                <div class="forgot-form-group">
                    <label for="fm-code">6-Digit Reset Code</label>
                    <input type="text" id="fm-code" class="forgot-form-input fm-code-input"
                           placeholder="000000" inputmode="numeric" pattern="[0-9]{6}"
                           maxlength="6" autocomplete="one-time-code"
                           oninput="this.value=this.value.replace(/[^0-9]/g,''); fmClearError('fm-code-error')">
                    <p class="fm-field-error" id="fm-code-error"></p>
                </div>

                <!-- New password -->
                <div class="forgot-form-group">
                    <label for="fm-pw">New Password</label>
                    <div class="fm-pw-wrapper">
                        <input type="password" id="fm-pw" class="forgot-form-input"
                               placeholder="At least 8 characters" autocomplete="new-password"
                               oninput="fmCheckStrength()">
                        <button type="button" class="fm-pw-toggle"
                                onclick="fmTogglePw('fm-pw','fm-eye-pw')"
                                aria-label="Show/hide password">
                            <svg id="fm-eye-pw" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <!-- Strength bars -->
                    <div class="fm-strength-wrap">
                        <div class="fm-strength-bars">
                            <div class="fm-bar" id="fm-bar1"></div>
                            <div class="fm-bar" id="fm-bar2"></div>
                            <div class="fm-bar" id="fm-bar3"></div>
                            <div class="fm-bar" id="fm-bar4"></div>
                        </div>
                        <span class="fm-strength-label" id="fm-strength-lbl"></span>
                    </div>
                    <!-- Requirements -->
                    <div class="fm-reqs">
                        <div class="fm-req" id="req-len">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>At least 8 characters
                        </div>
                        <div class="fm-req" id="req-upper">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>One uppercase letter
                        </div>
                        <div class="fm-req" id="req-num">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>One number
                        </div>
                    </div>
                    <p class="fm-field-error" id="fm-pw-error"></p>
                </div>

                <!-- Confirm password -->
                <div class="forgot-form-group">
                    <label for="fm-confirm">Confirm New Password</label>
                    <div class="fm-pw-wrapper">
                        <input type="password" id="fm-confirm" class="forgot-form-input"
                               placeholder="Repeat new password" autocomplete="new-password"
                               oninput="fmCheckMatch()">
                        <button type="button" class="fm-pw-toggle"
                                onclick="fmTogglePw('fm-confirm','fm-eye-confirm')"
                                aria-label="Show/hide password">
                            <svg id="fm-eye-confirm" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <p class="fm-field-error" id="fm-confirm-error"></p>
                    <p class="fm-match-ok"   id="fm-match-ok" style="display:none">✓ Passwords match</p>
                </div>

                <button type="submit" class="forgot-btn-submit" id="fm-reset-btn">
                    Reset Password
                </button>
            </form>

            <p class="forgot-back" style="margin-top:1rem">
                <span id="fm-resend-wrap">
                    Didn't receive it?
                    <a href="#" id="fm-resend-link" onclick="fmResendCode(); return false;">Resend code</a>
                </span>
                <span id="fm-resend-cd" style="display:none; color:#9ca3af">
                    Resend available in <span id="fm-resend-secs">120</span>s
                </span>
            </p>
        </div>

        <!-- ── Step 3: Success ──────────────────────────────── -->
        <div id="fm-step3" style="display:none; text-align:center; padding: 0.5rem 0 1rem">
            <div class="fm-success-icon">
                <svg width="30" height="30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 style="margin-bottom:0.5rem">Password Reset!</h2>
            <p class="forgot-modal-sub" style="margin-bottom:1.5rem">
                Your password has been updated successfully.<br>You can now sign in with your new password.
            </p>
            <button type="button" class="forgot-btn-submit" onclick="closeForgotModal()">
                Back to Login
            </button>
        </div>

    </div><!-- /.forgot-modal-inner -->
</div><!-- /.forgot-modal -->

<script>
// ── Login page helpers ───────────────────────────────────────
function togglePassword() {
    const pw = document.getElementById('password');
    const icon = document.getElementById('eye-icon');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    } else {
        pw.type = 'password';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    }
}

function signInWithGoogle() {
    window.location.href = '/printflow/google-auth/';
}

// Login validation and async submit (no full page reload for field errors)
(function () {
    var form = document.getElementById('login-form');
    if (!form) return;

    var emailEl = document.getElementById('email');
    var pwEl = document.getElementById('password');
    var emailErrEl = document.getElementById('login-email-error');
    var pwErrEl = document.getElementById('login-pw-error');
    var submitBtn = form.querySelector('button[type="submit"]');
    var submitOriginalHtml = submitBtn ? submitBtn.innerHTML : '';

    function setSubmitLoading(loading, text) {
        if (!submitBtn) return;
        if (loading) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-inline-loader"><span class="spinner"></span><span>' + (text || 'Loading...') + '</span></span>';
            return;
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitOriginalHtml || 'Sign In';
    }

    function setFieldState(inputEl, errEl, msg) {
        if (!inputEl || !errEl) return;
        errEl.textContent = msg || '';
        inputEl.classList.toggle('is-invalid', Boolean(msg));
        if (!msg && inputEl.value.trim()) {
            inputEl.classList.add('is-valid');
        } else if (msg) {
            inputEl.classList.remove('is-valid');
        } else {
            inputEl.classList.remove('is-valid');
        }
    }

    function validateEmail() {
        var value = (emailEl.value || '').trim();
        emailEl.value = value;
        if (!value) {
            setFieldState(emailEl, emailErrEl, 'Email is required.');
            return false;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            setFieldState(emailEl, emailErrEl, 'Please enter a valid email address.');
            return false;
        }
        setFieldState(emailEl, emailErrEl, '');
        return true;
    }

    function validatePassword() {
        var value = pwEl.value || '';
        if (!value.trim()) {
            setFieldState(pwEl, pwErrEl, 'Password is required.');
            return false;
        }
        setFieldState(pwEl, pwErrEl, '');
        return true;
    }

    if (emailEl) {
        emailEl.addEventListener('blur', validateEmail);
        emailEl.addEventListener('input', function () {
            if (emailErrEl.textContent) validateEmail();
        });
    }
    if (pwEl) {
        pwEl.addEventListener('input', function () {
            if (pwErrEl.textContent) validatePassword();
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var emailOk = validateEmail();
        var pwOk = validatePassword();
        if (!emailOk || !pwOk) return;

        setSubmitLoading(true, 'Signing in...');

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    window.location.href = data.redirect || '/printflow/';
                    return;
                }
                var fieldErrors = (data && data.field_errors) ? data.field_errors : {};
                setFieldState(emailEl, emailErrEl, fieldErrors.email || '');
                setFieldState(pwEl, pwErrEl, fieldErrors.password || (data && data.message ? data.message : 'Unable to sign in.'));
            })
            .catch(function () {
                setFieldState(pwEl, pwErrEl, 'Network error. Please try again.');
            })
            .finally(function () {
                setSubmitLoading(false);
            });
    });
})();

// ── Forgot Password Modal State ──────────────────────────────
var fmIdentifier  = '';
var fmType        = 'email';
var fmTimerSecs   = 900; // 15 min
var fmTimerInt    = null;
var fmResendInt   = null;

// ── Open / Close ─────────────────────────────────────────────
function openForgotModal() {
    document.getElementById('forgot-modal-backdrop').classList.add('is-open');
    document.getElementById('forgot-modal').classList.add('is-open');
    document.getElementById('forgot-modal').removeAttribute('aria-hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(function() {
        var el = document.getElementById('fm-identifier');
        if (el) el.focus();
    }, 100);
}

function closeForgotModal() {
    document.getElementById('forgot-modal-backdrop').classList.remove('is-open');
    document.getElementById('forgot-modal').classList.remove('is-open');
    document.getElementById('forgot-modal').setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    fmReset();
}

function fmReset() {
    if (fmTimerInt)  { clearInterval(fmTimerInt);  fmTimerInt  = null; }
    if (fmResendInt) { clearInterval(fmResendInt); fmResendInt = null; }
    fmIdentifier = ''; fmType = 'email'; fmTimerSecs = 900;

    document.getElementById('fm-form1').reset();
    document.getElementById('fm-form2').reset();
    document.getElementById('fm-msg1').innerHTML = '';
    document.getElementById('fm-msg2').innerHTML = '';

    document.getElementById('fm-step1').style.display = '';
    document.getElementById('fm-step2').style.display = 'none';
    document.getElementById('fm-step3').style.display = 'none';

    fmSwitchTab('email');

    // Reset strength/match UI
    ['fm-bar1','fm-bar2','fm-bar3','fm-bar4'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.style.backgroundColor = '';
    });
    var lbl = document.getElementById('fm-strength-lbl');
    if (lbl) { lbl.textContent = ''; lbl.style.color = ''; }
    var mo = document.getElementById('fm-match-ok');
    if (mo) mo.style.display = 'none';

    fmSetReq('req-len', false);
    fmSetReq('req-upper', false);
    fmSetReq('req-num', false);

    ['fm-id-error','fm-code-error','fm-pw-error','fm-confirm-error'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.textContent = '';
    });

    // Reset countdown display
    var timerEl = document.getElementById('fm-timer');
    if (timerEl) { timerEl.textContent = '15:00'; timerEl.style.color = ''; }
    var cd = document.getElementById('fm-countdown');
    if (cd) cd.classList.remove('expired');

    // Reset resend
    var rw = document.getElementById('fm-resend-wrap');
    var rc = document.getElementById('fm-resend-cd');
    if (rw) rw.style.display = '';
    if (rc) rc.style.display = 'none';
}

// ── Tab Switch ───────────────────────────────────────────────
function fmSwitchTab(type) {
    fmType = type;
    document.getElementById('fm-type').value = type;
    document.getElementById('fm-tab-email').classList.toggle('active', type === 'email');
    document.getElementById('fm-tab-phone').classList.toggle('active', type === 'phone');

    var input = document.getElementById('fm-identifier');
    var label = document.getElementById('fm-id-label');
    if (type === 'email') {
        input.type = 'email'; input.placeholder = 'you@example.com';
        input.autocomplete = 'email'; label.textContent = 'Email Address';
    } else {
        input.type = 'tel'; input.placeholder = '09171234567';
        input.autocomplete = 'tel'; label.textContent = 'Phone Number';
    }
    input.value = '';
    document.getElementById('fm-id-error').textContent = '';
    document.getElementById('fm-msg1').innerHTML = '';
}

// ── Helper: show error under a field ────────────────────────
function fmSetError(id, msg) {
    var el = document.getElementById(id);
    if (el) el.textContent = msg;
}
function fmClearError(id) { fmSetError(id, ''); }

function fmShowAlert(containerId, type, html) {
    var el = document.getElementById(containerId);
    if (el) el.innerHTML = '<div class="forgot-alert forgot-alert-' + type + '">' + html + '</div>';
}

function fmEscape(t) {
    var d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

// ── Step navigation ──────────────────────────────────────────
function fmGoStep1() {
    if (fmTimerInt)  { clearInterval(fmTimerInt);  fmTimerInt  = null; }
    if (fmResendInt) { clearInterval(fmResendInt); fmResendInt = null; }
    document.getElementById('fm-step2').style.display = 'none';
    document.getElementById('fm-step1').style.display = '';
    document.getElementById('fm-msg1').innerHTML = '';
}

// ── Step 1: Send Code ────────────────────────────────────────
function fmSendCode(e) {
    e.preventDefault();
    var identifier = document.getElementById('fm-identifier').value.trim();
    var type       = document.getElementById('fm-type').value;

    fmClearError('fm-id-error');
    document.getElementById('fm-msg1').innerHTML = '';

    // Client-side validation
    if (!identifier) {
        fmSetError('fm-id-error', type === 'email'
            ? 'Please enter your email address.'
            : 'Please enter your phone number.');
        return;
    }
    if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(identifier)) {
        fmSetError('fm-id-error', 'Please enter a valid email address.');
        return;
    }
    if (type === 'phone' && !/^[0-9]{10,15}$/.test(identifier.replace(/[\s\-\+\(\)]/g, ''))) {
        fmSetError('fm-id-error', 'Please enter a valid phone number (digits only).');
        return;
    }

    var btn = document.getElementById('fm-send-btn');
    btn.textContent = 'Sending…';
    btn.disabled = true;

    var fd = new FormData();
    fd.append('type', type);
    fd.append('identifier', identifier);

    fetch('api_forgot_password.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                fmIdentifier = identifier;
                fmType = type;
                if (data.debug && data.debug.reset_code) {
                    console.log('[DEV] Reset code:', data.debug.reset_code);
                }
                fmShowStep2();
            } else {
                fmShowAlert('fm-msg1', 'error', fmEscape(data.message || 'Failed to send. Please try again.'));
            }
        })
        .catch(function() {
            fmShowAlert('fm-msg1', 'error', 'Network error. Please check your connection.');
        })
        .finally(function() {
            btn.textContent = 'Send Reset Code';
            btn.disabled = false;
        });
}

function fmShowStep2() {
    var masked = fmMask(fmIdentifier, fmType);
    document.getElementById('fm-step2-sub').textContent =
        'A 6-digit code was sent to ' + masked + '.';
    document.getElementById('fm-locked-id').textContent = masked;

    document.getElementById('fm-step1').style.display = 'none';
    document.getElementById('fm-step2').style.display = '';
    document.getElementById('fm-msg2').innerHTML = '';

    fmTimerSecs = 900;
    fmStartTimer();
    fmStartResendCd();

    setTimeout(function() {
        var c = document.getElementById('fm-code');
        if (c) c.focus();
    }, 50);
}

function fmMask(identifier, type) {
    if (type === 'email') {
        var parts = identifier.split('@');
        var user  = parts[0];
        var shown = user.substring(0, Math.min(3, user.length));
        return shown + '***@' + (parts[1] || '');
    }
    return identifier.substring(0, 3) + '***' + identifier.slice(-3);
}

// ── Countdown Timer ──────────────────────────────────────────
function fmStartTimer() {
    if (fmTimerInt) clearInterval(fmTimerInt);
    fmUpdateTimer();
    fmTimerInt = setInterval(function() {
        fmTimerSecs--;
        if (fmTimerSecs <= 0) {
            clearInterval(fmTimerInt); fmTimerInt = null;
            var t = document.getElementById('fm-timer');
            var cd = document.getElementById('fm-countdown');
            if (t) { t.textContent = 'Expired'; t.style.color = '#b91c1c'; }
            if (cd) cd.classList.add('expired');
            fmShowAlert('fm-msg2', 'error',
                'Your reset code has expired. <a href="#" onclick="fmGoStep1();return false;" style="color:#b91c1c;font-weight:700">Request a new one</a>.');
        } else {
            fmUpdateTimer();
        }
    }, 1000);
}

function fmUpdateTimer() {
    var m = Math.floor(fmTimerSecs / 60).toString().padStart(2, '0');
    var s = (fmTimerSecs % 60).toString().padStart(2, '0');
    var el = document.getElementById('fm-timer');
    if (el) {
        el.textContent = m + ':' + s;
        el.style.color = fmTimerSecs <= 60 ? '#b91c1c' : '';
    }
}

// ── Resend Cooldown ──────────────────────────────────────────
function fmStartResendCd() {
    var secs = 120;
    document.getElementById('fm-resend-wrap').style.display = 'none';
    document.getElementById('fm-resend-cd').style.display   = '';
    document.getElementById('fm-resend-secs').textContent   = secs;

    if (fmResendInt) clearInterval(fmResendInt);
    fmResendInt = setInterval(function() {
        secs--;
        var el = document.getElementById('fm-resend-secs');
        if (el) el.textContent = secs;
        if (secs <= 0) {
            clearInterval(fmResendInt); fmResendInt = null;
            document.getElementById('fm-resend-cd').style.display   = 'none';
            document.getElementById('fm-resend-wrap').style.display = '';
        }
    }, 1000);
}

function fmResendCode() {
    if (!fmIdentifier) return;

    document.getElementById('fm-resend-wrap').style.display = 'none';
    document.getElementById('fm-msg2').innerHTML =
        '<div style="text-align:center;color:#6b7280;font-size:13px;padding:8px 0">Sending new code…</div>';

    var fd = new FormData();
    fd.append('type', fmType);
    fd.append('identifier', fmIdentifier);

    fetch('api_forgot_password.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                fmShowAlert('fm-msg2', 'success', 'A new reset code has been sent.');
                if (data.debug && data.debug.reset_code) {
                    console.log('[DEV] New reset code:', data.debug.reset_code);
                }
                var t = document.getElementById('fm-timer');
                var cd = document.getElementById('fm-countdown');
                if (t) { t.style.color = ''; }
                if (cd) cd.classList.remove('expired');
                fmTimerSecs = 900;
                fmStartTimer();
                fmStartResendCd();
            } else {
                fmShowAlert('fm-msg2', 'error', fmEscape(data.message || 'Failed to resend. Try again.'));
                document.getElementById('fm-resend-wrap').style.display = '';
            }
        })
        .catch(function() {
            fmShowAlert('fm-msg2', 'error', 'Network error. Please try again.');
            document.getElementById('fm-resend-wrap').style.display = '';
        });
}

// ── Step 2: Reset Password ───────────────────────────────────
function fmResetPassword(e) {
    e.preventDefault();

    var code    = document.getElementById('fm-code').value.trim();
    var pw      = document.getElementById('fm-pw').value;
    var confirm = document.getElementById('fm-confirm').value;

    fmClearError('fm-code-error');
    fmClearError('fm-pw-error');
    fmClearError('fm-confirm-error');
    document.getElementById('fm-msg2').innerHTML = '';

    var ok = true;
    if (!code || code.length !== 6 || !/^\d{6}$/.test(code)) {
        fmSetError('fm-code-error', 'Please enter the 6-digit code.');
        ok = false;
    }
    if (!pw || pw.length < 8) {
        fmSetError('fm-pw-error', 'Password must be at least 8 characters.');
        ok = false;
    }
    if (!confirm) {
        fmSetError('fm-confirm-error', 'Please confirm your new password.');
        ok = false;
    } else if (pw !== confirm) {
        fmSetError('fm-confirm-error', 'Passwords do not match.');
        ok = false;
    }
    if (!ok) return;

    var btn = document.getElementById('fm-reset-btn');
    btn.textContent = 'Resetting…';
    btn.disabled = true;

    var fd = new FormData();
    fd.append('identifier', fmIdentifier);
    fd.append('reset_code', code);
    fd.append('password', pw);
    fd.append('confirm_password', confirm);

    fetch('api_reset_password.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (fmTimerInt)  clearInterval(fmTimerInt);
                if (fmResendInt) clearInterval(fmResendInt);
                document.getElementById('fm-step2').style.display = 'none';
                document.getElementById('fm-step3').style.display = '';
            } else {
                fmShowAlert('fm-msg2', 'error', fmEscape(data.message || 'Reset failed. Please try again.'));
                if (data.expired) {
                    document.getElementById('fm-msg2').innerHTML +=
                        '<p style="text-align:center;margin-top:0.5rem"><a href="#" onclick="fmGoStep1();return false;" style="color:#6366f1;font-size:13px;font-weight:600">Request a new code</a></p>';
                }
            }
        })
        .catch(function() {
            fmShowAlert('fm-msg2', 'error', 'Network error. Please try again.');
        })
        .finally(function() {
            btn.textContent = 'Reset Password';
            btn.disabled = false;
        });
}

// ── Password Strength ────────────────────────────────────────
function fmSetReq(id, met) {
    var el = document.getElementById(id);
    if (!el) return;
    var svg = el.querySelector('svg');
    el.style.color = met ? '#10b981' : '#9ca3af';
    if (svg) svg.innerHTML = met
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>';
}

function fmCheckStrength() {
    var pw       = document.getElementById('fm-pw').value;
    var hasLen   = pw.length >= 8;
    var hasUpper = /[A-Z]/.test(pw);
    var hasLower = /[a-z]/.test(pw);
    var hasNum   = /[0-9]/.test(pw);
    var hasSpc   = /[^A-Za-z0-9]/.test(pw);

    fmSetReq('req-len',   hasLen);
    fmSetReq('req-upper', hasUpper);
    fmSetReq('req-num',   hasNum);

    var score = (hasLen ? 1 : 0) + (hasUpper && hasLower ? 1 : 0) + (hasNum ? 1 : 0) + (hasSpc ? 1 : 0);
    var colors = ['', '#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
    var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

    ['fm-bar1','fm-bar2','fm-bar3','fm-bar4'].forEach(function(id, i) {
        var el = document.getElementById(id);
        if (el) el.style.backgroundColor = i < score ? colors[score] : '#e5e7eb';
    });
    var lbl = document.getElementById('fm-strength-lbl');
    if (lbl) {
        lbl.textContent  = score > 0 ? labels[score] : '';
        lbl.style.color  = score > 0 ? colors[score] : '';
    }
    fmClearError('fm-pw-error');
    fmCheckMatch();
}

function fmCheckMatch() {
    var pw      = document.getElementById('fm-pw').value;
    var confirm = document.getElementById('fm-confirm').value;
    var ok      = document.getElementById('fm-match-ok');

    if (!confirm) { if (ok) ok.style.display = 'none'; fmClearError('fm-confirm-error'); return; }

    if (pw === confirm) {
        fmClearError('fm-confirm-error');
        if (ok) ok.style.display = '';
    } else {
        if (ok) ok.style.display = 'none';
        fmSetError('fm-confirm-error', 'Passwords do not match.');
    }
}

// ── Password Visibility Toggle ───────────────────────────────
function fmTogglePw(fieldId, iconId) {
    var f = document.getElementById(fieldId);
    var i = document.getElementById(iconId);
    if (!f || !i) return;
    if (f.type === 'password') {
        f.type = 'text';
        i.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    } else {
        f.type = 'password';
        i.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    }
}

// ── Global Event Listeners ───────────────────────────────────
document.addEventListener('click', function(e) {
    if (e.target.matches('[data-forgot-modal]')) {
        e.preventDefault();
        openForgotModal();
    }
    if (e.target === document.getElementById('forgot-modal-backdrop')) {
        closeForgotModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modal = document.getElementById('forgot-modal');
        if (modal && modal.classList.contains('is-open')) closeForgotModal();
    }
});
</script>
</body>
</html>

