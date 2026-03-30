<?php
/**
 * register.php
 * User (Admin/Staff) Registration Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect Admin, Manager, and Staff away from registration
redirect_admin_staff_from_public();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Clear old OTP data to prevent "sticky" email from previous attempts
        unset($_SESSION['otp_pending_email']);
        unset($_SESSION['otp_user_type']);
        
        $reg_type = $_POST['reg_type'] ?? 'user'; // 'direct' or 'legacy' are customers

        if ($reg_type === 'direct' || $reg_type === 'legacy') {
            // --- Customer Registration (Existing Flow) ---
            if ($reg_type === 'direct') {
                $identifier_type = sanitize($_POST['identifier_type'] ?? '');
                $identifier      = sanitize($_POST['identifier'] ?? '');
                $password        = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                if (empty($identifier_type) || empty($identifier) || empty($password)) {
                    $error = 'Please fill in all fields.';
                } elseif (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters.';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } else {
                    $result = register_customer_direct($identifier_type, $identifier, $password);
                    if ($result['success']) {
                        $_SESSION['otp_pending_email'] = ($identifier_type === 'email') ? $identifier : ($identifier . '@phone.local');
                        $_SESSION['otp_user_type'] = 'Customer';
                        redirect('/printflow/public/verify_email.php');
                    } else {
                        $error = $result['message'];
                    }
                }
            } else {
                // ... handle legacy customer reg if needed ...
                $error = "Registration type not supported here.";
            }
        } else {
            // --- User (Admin/Staff) Registration (New Flow) ---
            // Redirect to process_register.php or handle here. 
            // For simplicity and matching the prompt, let's let process_register.php handle it 
            // BUT if it's a GET we show the form.
        }
    }
    
    // If there was an error and it's from the modal, redirect back
    if ($error && !empty($_SERVER['HTTP_REFERER'])) {
        $return_path = '/printflow/';
        $sep = (strpos($return_path, '?') !== false) ? '&' : '?';
        redirect($return_path . $sep . 'auth_modal=register&error=' . urlencode($error));
    }
} else {
    // Clear OTP session data on fresh page load to prevent "sticky" email
    unset($_SESSION['otp_pending_email']);
    unset($_SESSION['otp_user_type']);
}

$page_title = "User Registration - PrintFlow";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        h1 { font-size: 1.5rem; margin-bottom: 1.5rem; text-align: center; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        input, select { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: #32a1c4; color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; margin-top: 1rem; }
        .alert { padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-error { background: #fee2e2; color: #b91c1c; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .footer { text-align: center; margin-top: 1rem; font-size: 0.875rem; }
        .footer a { color: #32a1c4; text-decoration: none; }

        /* Validation Styles */
        .form-group.is-invalid input {
            border-color: #ef4444 !important;
            background-color: #fef2f2;
        }
        .form-group.is-valid input {
            border-color: #10b981 !important;
            background-color: #f0fdf4;
        }
        .error-message {
            color: #ef4444;
            font-size: 11px;
            margin-top: 4px;
            display: none;
            font-weight: 500;
        }
        .form-group.is-invalid .error-message {
            display: block;
        }
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(1);
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Create Staff Account</h1>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        
        <form action="process_register.php" method="POST" id="registerForm" onsubmit="return validateRegisterForm(event)">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="reg_type" value="staff">
            
            <div class="form-group" id="group_first_name">
                <label>First Name *</label>
                <input type="text" name="first_name" id="first_name" required autocomplete="given-name" onkeydown="return blockNonLetters(event)" oninput="removeNonLetters(this)">
                <div class="error-message" id="error_first_name">First name is required.</div>
            </div>
            <div class="form-group" id="group_last_name">
                <label>Last Name *</label>
                <input type="text" name="last_name" id="last_name" required autocomplete="family-name" onkeydown="return blockNonLetters(event)" oninput="removeNonLetters(this)">
                <div class="error-message" id="error_last_name">Last name is required.</div>
            </div>
            <div class="form-group" id="group_email">
                <label>Email Address *</label>
                <input type="email" name="email" id="email" required autocomplete="email">
                <div class="error-message" id="error_email">Valid email is required.</div>
            </div>
            <div class="form-group" id="group_password">
                <label>Password *</label>
                <input type="password" name="password" id="password" required autocomplete="new-password">
                <p id="password_requirements" style="font-size:11px;color:#9ca3af;margin-top:4px;">Min. 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol</p>
                <div class="error-message" id="error_password">Invalid password format.</div>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="Staff">Staff</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <button type="submit" id="btn_register">Register</button>
        </form>
        <div class="footer">
            Already have an account? <a href="/printflow/">Sign in</a>
        </div>
    </div>
    <script>
    const validators = {
        first_name: (val) => {
            if (!val) return "First name is required.";
            if (!/^[A-Za-z]+( [A-Za-z]+)*$/.test(val)) return "First name must contain only letters.";
            if (val.length < 2 || val.length > 50) return "First name must be between 2 and 50 characters.";
            return null;
        },
        last_name: (val) => {
            if (!val) return "Last name is required.";
            if (!/^[A-Za-z]+( [A-Za-z]+)*$/.test(val)) return "Last name must contain only letters.";
            if (val.length < 2 || val.length > 50) return "Last name must be between 2 and 50 characters.";
            return null;
        },
        email: (val) => {
            if (!val) return "Email is required.";
            // Email validation: require at least 2 characters after the last dot
            if (!/^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/.test(val)) return "Invalid email address.";
            return null;
        },
        password: (val) => {
            if (!val) return "Password is required.";
            if (val.length < 8) return "Password must be at least 8 characters.";
            if (!/[A-Z]/.test(val)) return "Password must have an uppercase letter.";
            if (!/[a-z]/.test(val)) return "Password must have a lowercase letter.";
            if (!/[0-9]/.test(val)) return "Password must have a number.";
            if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) return "Password must have a special character.";
            return null;
        }
    };

    function validateField(id, validator) {
        const input = document.getElementById(id);
        if (!input) return true;
        const group = document.getElementById('group_' + id);
        const error = document.getElementById('error_' + id);
        let val = input.value;

        // Auto-formatting for names
        if (id === 'first_name' || id === 'last_name') {
            if (val.startsWith(' ')) val = val.trimStart();
            if (val.length > 0) val = val.charAt(0).toUpperCase() + val.slice(1);
            input.value = val;
        }

        // Block leading spaces for all
        if (val.startsWith(' ')) {
            input.value = val.trimStart();
            val = input.value;
        }

        const errorMessage = validator(val.trim());
        if (errorMessage) {
            group.classList.add('is-invalid');
            group.classList.remove('is-valid');
            error.textContent = errorMessage;
            return false;
        } else {
            group.classList.remove('is-invalid');
            group.classList.add('is-valid');
            return true;
        }
    }

    function checkForm() {
        const fValid = validateField('first_name', validators.first_name);
        const lValid = validateField('last_name', validators.last_name);
        const eValid = validateField('email', validators.email);
        const pValid = validateField('password', validators.password);
        document.getElementById('btn_register').disabled = !(fValid && lValid && eValid && pValid);
    }

    // Listeners
    ['first_name', 'last_name', 'email', 'password'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', checkForm);
            el.addEventListener('blur', checkForm);
        }
    });

    function validateRegisterForm(e) {
        checkForm();
        if (document.getElementById('btn_register').disabled) {
            e.preventDefault();
            return false;
        }
        return true;
    }

    // Initial check
    checkForm();

    // Block numbers and special characters in name fields
    function blockNonLetters(event) {
        // Allow: backspace, delete, tab, escape, enter, arrow keys
        if ([8, 9, 27, 13, 46, 37, 38, 39, 40].indexOf(event.keyCode) !== -1 ||
            // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            (event.keyCode === 65 && event.ctrlKey === true) ||
            (event.keyCode === 67 && event.ctrlKey === true) ||
            (event.keyCode === 86 && event.ctrlKey === true) ||
            (event.keyCode === 88 && event.ctrlKey === true) ||
            // Allow: home, end
            (event.keyCode >= 35 && event.keyCode <= 36)) {
            return true;
        }
        
        // Get the character that would be typed
        var char = event.key;
        
        // Block ALL special characters and numbers - only allow letters and space
        if (char && char.length === 1 && !/^[a-zA-Z ]$/.test(char)) {
            event.preventDefault();
            return false;
        }
        
        // Block consecutive spaces
        if (char === ' ') {
            var input = event.target;
            var value = input.value;
            var cursorPos = input.selectionStart;
            
            // Block space at the beginning
            if (cursorPos === 0) {
                event.preventDefault();
                return false;
            }
            
            // Block consecutive spaces
            if (value.charAt(cursorPos - 1) === ' ') {
                event.preventDefault();
                return false;
            }
        }
        
        return true;
    }

    // Remove any non-letter characters that get through (e.g., from paste)
    function removeNonLetters(input) {
        var value = input.value;
        // Remove ALL special characters and numbers - keep only letters and spaces
        var cleaned = value.replace(/[^a-zA-Z ]/g, '');
        // Remove consecutive spaces
        cleaned = cleaned.replace(/  +/g, ' ');
        // Remove leading spaces
        cleaned = cleaned.replace(/^ +/, '');
        if (value !== cleaned) {
            input.value = cleaned;
        }
    }
    </script>
</body>
</html>
