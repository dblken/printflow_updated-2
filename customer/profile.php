<?php
/**
 * Customer Profile Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();
$error = '';
$success = '';

// Ensure address columns exist (one-time migration - add only missing columns)
$existing_cols = [];
foreach (db_query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'") ?: [] as $r) {
    $existing_cols[$r['COLUMN_NAME']] = true;
}
$add_cols = [
    ['region', 100, 'contact_number'],
    ['province', 100, 'region'],
    ['city', 100, 'province'],
    ['barangay', 100, 'city'],
    ['street_address', 255, 'barangay']
];
foreach ($add_cols as list($col, $len, $after)) {
    if (empty($existing_cols[$col])) {
        db_execute("ALTER TABLE customers ADD COLUMN `$col` varchar($len) DEFAULT NULL AFTER `$after`");
    }
}

// Get customer data
$customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $profile_picture = $customer['profile_picture'];
        
        // Handle profile picture upload
        if (!empty($_FILES['profile_picture']['tmp_name']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['profile_picture']['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (!in_array($mime, $allowed)) {
                $error = 'Profile picture must be JPG, PNG, or WEBP.';
            } elseif ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) {
                $error = 'Profile picture must be under 2MB.';
            } else {
                $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $new_filename = 'customer_' . $customer_id . '_' . time() . '.' . $ext;
                $upload_dir = __DIR__ . '/../public/assets/uploads/profiles/';
                
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                    // Delete old picture if exists
                    if (!empty($customer['profile_picture']) && file_exists($upload_dir . $customer['profile_picture'])) {
                        unlink($upload_dir . $customer['profile_picture']);
                    }
                    $profile_picture = $new_filename;
                    // Update DB immediately or wait
                } else {
                    $error = 'Failed to upload profile picture.';
                }
            }
        }
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $dob = sanitize($_POST['dob'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required';
        } elseif (!empty($dob)) {
            try {
                $bday_date = new DateTime($dob);
                $today = new DateTime();
                $age = $today->diff($bday_date)->y;
                if ($bday_date > $today) {
                    $error = 'Birthday cannot be a future date';
                } elseif ($age < 13) {
                    $error = 'You must be at least 13 years old';
                }
            } catch (Exception $e) {
                $error = 'Invalid birthday format';
            }
        }
        
        if (!$error) {
            $dob_val = trim($dob) !== '' ? $dob : null;
            $gender_val = in_array(trim($gender), ['Male', 'Female', 'Other'], true) ? $gender : null;
            $result = db_execute("UPDATE customers SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, dob = ?, gender = ? WHERE customer_id = ?",
                'ssssssi', [$first_name, $middle_name, $last_name, $contact_number, $dob_val, $gender_val, $customer_id]);
            
            $first_name = ucwords(strtolower(trim($first_name)));
            $middle_name = ucwords(strtolower(trim($middle_name)));
            $last_name = ucwords(strtolower(trim($last_name)));
            
            // Philippine Name Regex: letters and single space only, max 3 words
            $nameRegex = '/^[A-Za-z]+( [A-Za-z]+){0,2}$/';
            $contactRegex = '/^\+639\d{9}$/';
            $emailRegex = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';

            // Backend strong validation
            if (empty($first_name) || !preg_match($nameRegex, $first_name)) {
                $error = 'First name must contain only letters and at most 3 words.';
            } elseif (!empty($middle_name) && !preg_match($nameRegex, $middle_name)) {
                $error = 'Middle name must contain only letters and at most 3 words.';
            } elseif (empty($last_name) || !preg_match($nameRegex, $last_name)) {
                $error = 'Last name must contain only letters and at most 3 words.';
            } elseif (empty($contact_number) || !preg_match($contactRegex, $contact_number)) {
                $error = 'Contact number must follow format +639XXXXXXXXX.';
            } elseif (empty($dob) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || (strtotime($dob) > strtotime('-13 years'))) {
                $error = 'Date of birth must be a valid date and you must be at least 13 years old.';
            } else {
                // Ensure no script tags or malicious input
                if (strip_tags($first_name) !== $first_name || strip_tags($last_name) !== $last_name || strip_tags($middle_name) !== $middle_name) {
                    $error = 'Invalid characters detected in name fields.';
                } else {
                    $first_name = sanitize($first_name);
                    $middle_name = sanitize($middle_name);
                    $last_name = sanitize($last_name);
                    $contact_number = sanitize($contact_number);
                    $dob = sanitize($dob);
                    $gender = sanitize($gender);
                    $gender_val = in_array(trim($gender), ['Male', 'Female', 'Other'], true) ? $gender : null;
                    
                    $result = db_execute("UPDATE customers SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, dob = ?, gender = ?, profile_picture = ? WHERE customer_id = ?",
                        'sssssssi', [$first_name, $middle_name, $last_name, $contact_number, $dob, $gender_val, $profile_picture, $customer_id]);
                    
                    if ($result) {
                        $success = 'Profile updated successfully!';
                        $_SESSION['profile_update_count'] = ($_SESSION['profile_update_count'] ?? 0) + 1;
                        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                        // Refresh customer data
                        $customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];
                    } else {
                        $error = 'Failed to update profile';
                    }
                }
            }
        }
    }
}

// Handle address update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_address'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $region        = sanitize($_POST['region']         ?? '');
        $province      = sanitize($_POST['province']       ?? '');
        $city          = sanitize($_POST['city']           ?? '');
        $barangay      = sanitize($_POST['barangay']       ?? '');
        $street_address = sanitize($_POST['street_address'] ?? '');

        $result = db_execute(
            "UPDATE customers SET region=?, province=?, city=?, barangay=?, street_address=? WHERE customer_id=?",
            'sssssi',
            [$region, $province, $city, $barangay, $street_address, $customer_id]
        );

        if ($result) {
            $success = 'Address updated successfully!';
            $customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];
        } else {
            $error = 'Failed to update address';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $pw_errors = [];
        if (empty($current_password) || empty($new_password)) {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current_password, $customer['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            if (strlen($new_password) < 8 || strlen($new_password) > 64) $pw_errors[] = '8-64 characters';
            if (!preg_match('/[A-Z]/', $new_password)) $pw_errors[] = 'uppercase letter';
            if (!preg_match('/[a-z]/', $new_password)) $pw_errors[] = 'lowercase letter';
            if (!preg_match('/[0-9]/', $new_password)) $pw_errors[] = 'number';
            if (!preg_match('/[^A-Za-z0-9]/', $new_password)) $pw_errors[] = 'special character';

            if (!empty($pw_errors)) {
                $error = 'Password must contain: ' . implode(', ', $pw_errors) . '.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $result = db_execute("UPDATE customers SET password_hash = ? WHERE customer_id = ?", 'si', [$password_hash, $customer_id]);
                
                if ($result) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password.';
                }
            }
        }
    }
}

$max_birthday = date('Y-m-d', strtotime('-13 years'));

$page_title = 'My Profile - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Modern Profile Page (Refactored to Project Specs) ─── */
:root {
    --pf-primary: #030d11;
    --pf-secondary: #0a1f26;
    --pf-accent: #53c5e0;
    --pf-accent-hover: #32a1c4;
    --pf-text-main: #030d11; /* Changed to dark for the white container as requested */
    --pf-text-muted: #64748b;
    --pf-border: #e2e8f0;
    --pf-card-bg: #ffffff;
}

/* 1. SINGLE MAIN CONTAINER */
.profile-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 2.5rem;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    overflow: hidden; /* Prevent overflow */
    box-sizing: border-box;
}

/* 2. LAYOUT STRUCTURE (GRID / FLEX) */
.profile-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 2.5rem;
    align-items: start;
}

/* 📱 Responsiveness (Tablet: 992px) */
@media (max-width: 992px) {
    .profile-grid { grid-template-columns: 1fr; gap: 2rem; }
    .profile-container { margin: 20px; padding: 1.5rem; }
}

/* ─ SIDEBAR (LEFT SIDE) ─ */
.profile-sidebar {
    position: sticky;
    top: 20px;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.sidebar-content {
    text-align: center;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid var(--pf-border);
}

.profile-avatar-wrap {
    position: relative;
    display: inline-block;
    margin-bottom: 1.25rem;
}

.profile-avatar-ring {
    width: 130px; height: 130px;
    border-radius: 50%;
    overflow: hidden;
    background: #fff;
    border: 3px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin: 0 auto;
}

.profile-avatar-ring img { width: 100%; height: 100%; object-fit: cover; }

.profile-avatar-edit-btn {
    position: absolute;
    bottom: 5px; right: 5px;
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--pf-accent);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    border: 2px solid #fff;
}

.profile-user-name {
    font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;
}

.profile-user-email {
    font-size: 0.875rem; color: #64748b; margin-bottom: 1rem; word-break: break-all;
}

.profile-info-pill {
    display: flex; justify-content: space-between; padding: 0.75rem 0;
    border-top: 1px solid #e2e8f0; font-size: 0.813rem;
}

/* ─ MAIN CONTENT (RIGHT SIDE) ─ */
.profile-main-content {
    display: flex; flex-direction: column; gap: 2rem;
}

.profile-card {
    background: #fff;
    padding: 0; /* padding handled in card inner */
    transition: all 0.3s ease;
}

.profile-card:hover {
    transform: translateY(-2px);
}

.profile-card-title {
    font-size: 1.125rem; font-weight: 700; color: #0f172a; 
    margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;
}

/* ─ FORM LAYOUT & ELEMENTS ─ */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
    box-sizing: border-box;
}

/* 📱 Mobile: 576px */
@media (max-width: 576px) {
    .form-grid { grid-template-columns: 1fr; }
}

.pf-field-group {
    width: 100%;
}

.pf-label {
    display: block; font-size: 0.75rem; font-weight: 700; 
    color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.025em;
}

.pf-input {
    width: 100%; padding: 12px 14px;
    border: 1px solid #cbd5e1; border-radius: 8px;
    font-size: 0.95rem; color: #1e293b; background: #fff;
    box-sizing: border-box; transition: 0.2s;
}

.pf-input:focus {
    outline: none; border-color: var(--pf-accent); box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.1);
}

.pf-btn-primary {
    padding: 12px 24px; border-radius: 8px; border: none;
    background: var(--pf-accent); color: #fff; font-weight: 700;
    cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px;
}

.pf-btn-primary:hover { background: var(--pf-accent-hover); transform: translateY(-1px); }

/* Quick Actions Nav */
.profile-nav-card {
    background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 0.5rem;
}
.profile-nav-list { list-style: none; padding: 0; margin: 0; }
.profile-nav-item a {
    display: flex; align-items: center; gap: 10px; padding: 10px 14px;
    border-radius: 8px; font-weight: 600; color: #64748b; text-decoration: none; transition: 0.2s;
}
.profile-nav-item a:hover { background: #fff; color: var(--pf-accent); }
.profile-nav-item a.active { background: #fff; color: var(--pf-accent); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

/* Alerts */
.pf-alert { padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid transparent; }
.pf-alert-error { background: #fef2f2; color: #b91c1c; border-left-color: #ef4444; }
.pf-alert-success { background: #f0fdf4; color: #15803d; border-left-color: #22c55e; }

.live-indicator { font-size: 0.75rem; margin-top: 4px; min-height: 1.25rem; transition: 0.2s; }
.live-indicator.error { color: #dc2626; font-weight: 600; }
</style>

<div class="min-h-screen py-10" style="background: #f1f5f9;">
    <div class="profile-container">

        <?php if ($error): ?>
        <div class="pf-alert pf-alert-error">
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="pf-alert pf-alert-success">
            <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- ── SIDEBAR (LEFT SIDE) ── -->
            <aside class="profile-sidebar">
                <div class="sidebar-content">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar-ring">
                            <?php if (!empty($customer['profile_picture'])): ?>
                                <img src="/printflow/public/assets/uploads/profiles/<?php echo htmlspecialchars($customer['profile_picture']); ?>?t=<?php echo time(); ?>" alt="Avatar" id="profile-preview">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f1f5f9;">
                                    <svg width="48" height="48" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <img src="" alt="Profile" style="display:none;width:100%;height:100%;object-fit:cover;" id="profile-preview">
                            <?php endif; ?>
                        </div>
                        <label for="profile_picture" class="profile-avatar-edit-btn" title="Change photo">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </label>
                    </div>
                    <div class="profile-user-name"><?php echo htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name'])); ?></div>
                    <div class="profile-user-email"><?php echo htmlspecialchars($customer['email']); ?></div>
                    
                    <div style="margin-top: 1rem;">
                        <div class="profile-info-pill">
                            <span>Since</span>
                            <span style="font-weight:700;color:#0f172a;"><?php echo isset($customer['created_at']) ? date('M Y', strtotime($customer['created_at'])) : '2026'; ?></span>
                        </div>
                        <div class="profile-info-pill" style="border-bottom: none;">
                            <span>Status</span>
                            <span style="font-weight:700;color:#16a34a;">Verified</span>
                        </div>
                    </div>
                </div>

                <nav class="profile-nav-card">
                    <ul class="profile-nav-list">
                        <li class="profile-nav-item"><a href="#section-profile" class="active">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Profile Info
                        </a></li>
                        <li class="profile-nav-item"><a href="#section-address">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Address Book
                        </a></li>
                        <li class="profile-nav-item"><a href="#section-password">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Security
                        </a></li>
                    </ul>
                </nav>
            </aside>

            <!-- ── MAIN CONTENT (RIGHT SIDE) ── -->
            <div class="profile-main-content">

                <!-- Personal Information -->
                <div class="profile-card" id="section-profile">
                    <h3 class="profile-card-title">Personal Information</h3>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="update_profile" value="1">
                        <input type="file" id="profile_picture" name="profile_picture" class="hidden" accept="image/*" style="display:none;"
                               onchange="const f=this.files[0];if(f){const r=new FileReader();r.onload=e=>{const p=document.getElementById('profile-preview');p.src=e.target.result;p.style.display='block';};r.readAsDataURL(f);}">

                        <div class="form-grid">
                            <div class="pf-field-group">
                                <label for="first_name" class="pf-label">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="pf-input validate-advanced-name" required value="<?php echo htmlspecialchars($customer['first_name']); ?>" maxlength="50">
                                <div class="live-indicator" data-for="first_name"></div>
                            </div>
                            <div class="pf-field-group">
                                <label for="middle_name" class="pf-label">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" class="pf-input validate-advanced-name" value="<?php echo htmlspecialchars($customer['middle_name'] ?? ''); ?>" maxlength="50">
                                <div class="live-indicator" data-for="middle_name"></div>
                            </div>
                            <div class="pf-field-group">
                                <label for="last_name" class="pf-label">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="pf-input validate-advanced-name" required value="<?php echo htmlspecialchars($customer['last_name']); ?>" maxlength="50">
                                <div class="live-indicator" data-for="last_name"></div>
                            </div>
                            <div class="pf-field-group">
                                <label class="pf-label">Email Address (Locked)</label>
                                <input type="email" class="pf-input" style="background:#f1f5f9; cursor:not-allowed;" value="<?php echo htmlspecialchars($customer['email']); ?>" readonly>
                            </div>
                            <div class="pf-field-group">
                                <label for="contact_number" class="pf-label">Contact Number</label>
                                <input type="tel" id="contact_number" name="contact_number" class="pf-input validate-advanced-contact" placeholder="+639XXXXXXXXX" value="<?php echo htmlspecialchars($customer['contact_number'] ?? ''); ?>" maxlength="13" required>
                                <div class="live-indicator" data-for="contact_number"></div>
                            </div>
                            <div class="pf-field-group">
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <label for="dob" class="pf-label">Birthday</label>
                                        <input type="date" id="dob" name="dob" class="pf-input validate-advanced-dob" value="<?php echo htmlspecialchars($customer['dob'] ?? ''); ?>" max="<?php echo $max_birthday; ?>">
                                        <div class="live-indicator" data-for="dob"></div>
                                    </div>
                                    <div>
                                        <label for="gender" class="pf-label">Gender</label>
                                        <select id="gender" name="gender" class="pf-input">
                                            <option value="">Select</option>
                                            <option value="Male" <?php echo ($customer['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($customer['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($customer['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                            <button type="submit" id="btn-update-profile" class="pf-btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- Address Section -->
                <div class="profile-card" id="section-address" style="padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                    <h3 class="profile-card-title">Address & Delivery</h3>
                    
                    <form method="POST" action="" id="address-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="update_address" value="1">
                        
                        <div class="form-grid">
                            <div class="pf-field-group">
                                <label class="pf-label" for="addr_region">Region</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_region" name="region" class="pf-input addr-select" data-level="region">
                                        <option value="">— Select Region —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_region"></span>
                                </div>
                            </div>
                            <div class="pf-field-group">
                                <label class="pf-label" for="addr_province">Province</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_province" name="province" class="pf-input addr-select" data-level="province" disabled>
                                        <option value="">— Select Province —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_province"></span>
                                </div>
                            </div>
                            <div class="pf-field-group">
                                <label class="pf-label" for="addr_city">City / Municipality</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_city" name="city" class="pf-input addr-select" data-level="city" disabled>
                                        <option value="">— Select City / Municipality —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_city"></span>
                                </div>
                            </div>
                            <div class="pf-field-group">
                                <label class="pf-label" for="addr_barangay">Barangay</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_barangay" name="barangay" class="pf-input addr-select" data-level="barangay" disabled>
                                        <option value="">— Select Barangay —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_barangay"></span>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 1.25rem;">
                            <label class="pf-label" for="addr_street">Street Name, House No., Building Info</label>
                            <input type="text" id="addr_street" name="street_address" class="pf-input" placeholder="e.g. #123 Sampaguita st., Phase 2" value="<?php echo htmlspecialchars($customer['street_address'] ?? ''); ?>">
                        </div>

                        <div id="addr-preview" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1rem; margin-top:1.5rem; font-size:0.875rem;">
                            <span style="color:#64748b; font-weight:600; display:block; margin-bottom:4px;">Delivery Summary</span>
                            <div id="addr-preview-text" style="color:#0f172a; line-height:1.4;"></div>
                        </div>

                        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                            <button type="submit" class="pf-btn-primary">Update Address</button>
                        </div>
                    </form>
                </div>

                <!-- Security Section -->
                <div class="profile-card" id="section-password" style="padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                    <h3 class="profile-card-title">Security & Password</h3>
                    
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_password" value="1">

                        <div class="form-grid">
                            <div class="pf-field-group">
                                <label for="current_password" class="pf-label">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="pf-input" placeholder="Enter current password" required>
                            </div>
                            <div class="pf-field-group">
                                <!-- empty column for alignment or extra info -->
                                <div style="font-size:0.813rem; color:#64748b; padding-top:2rem;">
                                    Confirm your identity to make security changes.
                                </div>
                            </div>
                            <div class="pf-field-group">
                                <label for="new_password" class="pf-label">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="pf-input" placeholder="8+ characters" required minlength="8">
                                <ul id="pw-checklist" style="list-style:none; padding:10px 0 0; margin:0; display:flex; flex-wrap:wrap; gap:6px;">
                                    <li id="pw-rule-len" style="font-size:0.625rem; padding:3px 8px; background:#f1f5f9; border-radius:4px; color:#94a3b8;">8+ chars</li>
                                    <li id="pw-rule-upper" style="font-size:0.625rem; padding:3px 8px; background:#f1f5f9; border-radius:4px; color:#94a3b8;">Uppercase</li>
                                    <li id="pw-rule-num" style="font-size:0.625rem; padding:3px 8px; background:#f1f5f9; border-radius:4px; color:#94a3b8;">Number</li>
                                </ul>
                            </div>
                            <div class="pf-field-group">
                                <label for="confirm_password" class="pf-label">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="pf-input" placeholder="Repeat new password" required minlength="8">
                                <p id="pw-match-indicator" style="font-size:0.75rem; margin-top:6px; font-weight:600;"></p>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                            <button type="submit" class="pf-btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>

            </div><!-- /main -->
        </div><!-- /profile-grid -->
    </div><!-- /profile-container -->
</div><!-- /min-h-screen -->

<style>
/* ── password checklist styles already in page head ── */
.pw-checklist-hidden { display:none; }
/* ── Cascading Address Selector ── */
.addr-select-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.addr-select-wrap select.input-field {
    padding-right: 2.8rem;
}
.addr-select-wrap select:disabled {
    background: #f3f7f9;
    color: #9ca3af;
    cursor: not-allowed;
    border-color: #e5e7eb;
}
.addr-spinner {
    display: none;
    position: absolute;
    right: 2.2rem;
    width: 14px;
    height: 14px;
    border: 2px solid #d1d5db;
    border-top-color: #0a2530;
    border-radius: 50%;
    animation: addr-spin 0.7s linear infinite;
    pointer-events: none;
}
.addr-spinner.spinning { display: block; }
@keyframes addr-spin { to { transform: rotate(360deg); } }

.addr-select-wrap select:disabled + .addr-spinner { display: none !important; }

/* Mobile responsive: stack to 1 column */
@media (max-width: 640px) {
    #address-card .grid-cols-2 {
        grid-template-columns: 1fr !important;
    }
}
/* Custom grid for 4 columns profile row responsive */
.custom-grid-4 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}
@media (min-width: 768px) {
    .custom-grid-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
.required-asterisk {
    color: #dc2626;
    font-weight: 800;
}
/* Live validation indicators */
.live-indicator { font-size: 0.75rem; min-height: 1.25rem; transition: color 0.2s, opacity 0.2s; }
.live-indicator.valid { color: #16a34a; }
.live-indicator.error { color: #dc2626; font-weight: 600; }
.live-indicator .ind-icon { display: inline-block; margin-right: 0.25rem; font-weight: bold; }
.live-indicator .hint { color: #6b7280; font-weight: 400; }
.input-field.input-valid { border-color: #16a34a; box-shadow: 0 0 0 1px rgba(22,163,74,0.3); }
.input-field.input-error { border-color: #dc2626; box-shadow: 0 0 0 1px rgba(220,38,38,0.3); }
</style>

<script>
(function () {
    // ── Saved values from PHP (for pre-selection) ──
    const SAVED = {
        region:        <?php echo json_encode($customer['region']   ?? null); ?>,
        province:      <?php echo json_encode($customer['province'] ?? null); ?>,
        city:          <?php echo json_encode($customer['city']     ?? null); ?>,
        barangay:      <?php echo json_encode($customer['barangay'] ?? null); ?>,
    };

    const API = '/printflow/customer/api_address.php';

    const selRegion   = document.getElementById('addr_region');
    const selProvince = document.getElementById('addr_province');
    const selCity     = document.getElementById('addr_city');
    const selBarangay = document.getElementById('addr_barangay');

    const spinOf = {
        region:   document.getElementById('spin_region'),
        province: document.getElementById('spin_province'),
        city:     document.getElementById('spin_city'),
        barangay: document.getElementById('spin_barangay'),
    };

    const preview     = document.getElementById('addr-preview');
    const previewText = document.getElementById('addr-preview-text');

    // ── Utility: show/hide spinner ──
    function spin(level, on) {
        spinOf[level].classList.toggle('spinning', on);
    }

    // ── Utility: populate a <select> with [{code, name}] or [string] ──
    function populate(sel, items, placeholder, savedCode, levelName) {
        sel.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);

        items.forEach(item => {
            const opt = document.createElement('option');
            const code = typeof item === 'string' ? item : item.code;
            const name = typeof item === 'string' ? item : item.name;
            opt.value = name;         // we store the human-readable name in DB
            opt.dataset.code = code;  // keep PSGC code for subsequent API calls
            opt.textContent = name;
            if (savedCode && (name === savedCode || code === savedCode)) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });

        sel.disabled = (items.length === 0);
    }

    // ── Utility: reset a select back to disabled/empty ──
    function reset(sel, placeholder) {
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        sel.disabled = true;
    }

    // ── Get the PSGC code from the currently selected option ──
    function selectedCode(sel) {
        const opt = sel.options[sel.selectedIndex];
        return opt ? opt.dataset.code || '' : '';
    }

    // ── Update address preview box ──
    function updatePreview() {
        const parts = [
            selBarangay.value, selCity.value,
            selProvince.value, selRegion.value
        ].filter(Boolean);
        const street = document.getElementById('addr_street').value.trim();
        if (street) parts.unshift(street);

        if (parts.length > 1) {
            previewText.textContent = parts.join(', ');
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }

    // ── FETCH regions ──
    async function loadRegions() {
        spin('region', true);
        selRegion.disabled = true;
        try {
            const res  = await fetch(`${API}?action=regions`);
            const data = await res.json();
            if (data.success) {
                populate(selRegion, data.data, '— Select Region —', SAVED.region, 'region');
                selRegion.disabled = false;
                // Auto-cascade if a saved region exists
                if (SAVED.region && selectedCode(selRegion)) {
                    await loadProvinces(selectedCode(selRegion), true);
                }
            }
        } catch(e) { console.error('Addr: regions', e); }
        spin('region', false);
    }

    // ── FETCH provinces ──
    async function loadProvinces(regionCode, auto) {
        spin('province', true);
        reset(selCity,     '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        try {
            const res  = await fetch(`${API}?action=provinces&region=${regionCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selProvince, data.data, '— Select Province —', auto ? SAVED.province : null, 'province');
                if (auto && SAVED.province && selectedCode(selProvince)) {
                    await loadCities(selectedCode(selProvince), true);
                }
            }
        } catch(e) { console.error('Addr: provinces', e); }
        spin('province', false);
        updatePreview();
    }

    // ── FETCH cities ──
    async function loadCities(provinceCode, auto) {
        spin('city', true);
        reset(selBarangay, '— Select Barangay —');
        try {
            const res  = await fetch(`${API}?action=cities&province=${provinceCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selCity, data.data, '— Select City / Municipality —', auto ? SAVED.city : null, 'city');
                if (auto && SAVED.city && selectedCode(selCity)) {
                    await loadBarangays(selectedCode(selCity), true);
                }
            }
        } catch(e) { console.error('Addr: cities', e); }
        spin('city', false);
        updatePreview();
    }

    // ── FETCH barangays ──
    async function loadBarangays(cityCode, auto) {
        spin('barangay', true);
        try {
            const res  = await fetch(`${API}?action=barangays&city=${cityCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selBarangay, data.data, '— Select Barangay —', auto ? SAVED.barangay : null, 'barangay');
            }
        } catch(e) { console.error('Addr: barangays', e); }
        spin('barangay', false);
        updatePreview();
    }

    // ── Event: Region changed ──
    selRegion.addEventListener('change', function () {
        reset(selProvince, '— Select Province —');
        reset(selCity,     '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        updatePreview();
        const code = selectedCode(this);
        if (code) loadProvinces(code, false);
    });

    // ── Event: Province changed ──
    selProvince.addEventListener('change', function () {
        reset(selCity,     '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        updatePreview();
        const code = selectedCode(this);
        if (code) loadCities(code, false);
    });

    // ── Event: City changed ──
    selCity.addEventListener('change', function () {
        reset(selBarangay, '— Select Barangay —');
        updatePreview();
        const code = selectedCode(this);
        if (code) loadBarangays(code, false);
    });

    // ── Event: Barangay changed ──
    selBarangay.addEventListener('change', updatePreview);

    // ── Event: Street changed ──
    document.getElementById('addr_street').addEventListener('input', updatePreview);

    // ── Boot: load all regions on page load ──
    loadRegions();
})();
</script>

<script>
// Advanced Validations Logic
(function() {
    const indicators = {};
    document.querySelectorAll('.live-indicator').forEach(el => {
        indicators[el.dataset.for] = el;
    });

    const fProfile = document.getElementById('btn-update-profile')?.closest('form');
    const btnSubmit = document.getElementById('btn-update-profile');

    const REGEX = {
        name: /^[A-Za-z]+( [A-Za-z]+){0,2}$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    };

    const HINTS = {
        first_name: 'Letters only, max 3 words',
        middle_name: 'Letters only, max 3 words (optional)',
        last_name: 'Letters only, max 3 words',
        contact_number: 'Format: +639XXXXXXXXX',
        dob: 'You must be at least 13 years old'
    };

    function updateIndicator(fieldId, isValid, message) {
        const el = indicators[fieldId];
        const input = document.getElementById(fieldId);
        if (!el || !input) return;
        
        el.classList.remove('valid', 'error');
        input.classList.remove('input-valid', 'input-error');
        
        if (isValid) {
            el.innerHTML = '';
            el.dataset.valid = '1';
        } else {
            el.innerHTML = '<span class="ind-icon">!</span> ' + message;
            el.dataset.valid = '0';
            el.classList.add('error');
            input.classList.add('input-error');
        }
        checkFormValidity();
    }

    function showHint(fieldId) {
        const el = indicators[fieldId];
        if (!el || el.dataset.valid === '1' || el.dataset.valid === '0') return;
        const hint = HINTS[fieldId];
        if (hint) el.innerHTML = '<span class="hint">' + hint + '</span>';
    }

    function clearHint(fieldId) {
        const el = indicators[fieldId];
        if (el && !el.dataset.valid) el.innerHTML = '';
    }

    function checkFormValidity() {
        if (!fProfile || !btnSubmit) return;
        const inputs = fProfile.querySelectorAll('.validate-advanced-name, .validate-advanced-dob, .validate-advanced-contact');
        let allValid = true;
        
        inputs.forEach(input => {
            const el = indicators[input.id];
            if (!el || el.dataset.valid !== '1') {
                if (input.required || (input.value.trim().length > 0)) {
                    // For non-required fields like middle_name or contact_number, only mark as invalid if they have value but failed validation
                    if ((input.id === 'middle_name' || input.id === 'contact_number') && input.value.trim().length === 0) {
                        // skip
                    } else {
                        allValid = false;
                    }
                }
            }
        });

        btnSubmit.disabled = !allValid;
        btnSubmit.style.opacity = allValid ? '1' : '0.5';
        btnSubmit.style.cursor = allValid ? 'pointer' : 'not-allowed';
    }

    function normalizeSpaces(val) {
        return val.replace(/\s+/g, ' ');
    }

    function toTitleCase(str) {
        return str.toLowerCase().replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }

    // Name Validation
    document.querySelectorAll('.validate-advanced-name').forEach(input => {
        input.addEventListener('input', function() {
            // Prevent numbers and special characters immediately
            let val = this.value.replace(/[^A-Za-z ]/g, '');
            // Prevent multiple consecutive spaces
            val = val.replace(/ +(?= )/g, '');
            // Auto capitalize first letter of each word
            val = toTitleCase(val);
            this.value = val;

            const trimmed = val.trim();
            if (trimmed.length === 0) {
                if (this.required) {
                    updateIndicator(this.id, false, 'This field is required');
                } else {
                    const ind = indicators[this.id];
                    if (ind) {
                        ind.innerHTML = '<span class="hint">' + (HINTS[this.id] || '') + '</span>';
                        ind.dataset.valid = '1';
                    }
                    this.classList.remove('input-valid', 'input-error');
                    checkFormValidity();
                }
                return;
            }

            if (!REGEX.name.test(trimmed)) {
                let msg = 'Use letters only, max 3 words (e.g. Juan Carlos)';
                const words = trimmed.split(/\s+/).filter(Boolean).length;
                if (words > 3) msg = 'Maximum 3 words allowed';
                else if (/[0-9]/.test(trimmed)) msg = 'Numbers not allowed';
                else if (/[^A-Za-z\s]/.test(trimmed)) msg = 'Letters and spaces only';
                updateIndicator(this.id, false, msg);
            } else {
                updateIndicator(this.id, true);
            }
        });

        input.addEventListener('blur', function() {
            this.value = normalizeSpaces(this.value).trim();
            this.dispatchEvent(new Event('input'));
        });
        input.addEventListener('focus', function() {
            if (this.value.trim() === '' && indicators[this.id]) {
                const hint = HINTS[this.id] || (this.required ? 'This field is required' : '');
                if (hint) {
                    indicators[this.id].innerHTML = '<span class="hint">' + hint + '</span>';
                    indicators[this.id].dataset.valid = '';
                    this.classList.remove('input-valid', 'input-error');
                }
            }
        });
    });

    // Contact Number Validation
    document.querySelectorAll('.validate-advanced-contact').forEach(input => {
        const regexContact = /^\+639\d{9}$/;

        input.addEventListener('input', function() {
            let val = this.value;
            
            // Re-enforce +63 if deleted, but allow empty while typing if necessary
            // However, the rule says "Must start with +63"
            if (val.length > 0 && !val.startsWith('+')) {
                val = '+' + val.replace(/\+/g, '');
            }
            if (val.length > 1 && val !== '+' && val.charAt(1) !== '6') {
                val = '+6' + val.substring(1).replace(/6/g, '');
            }
            // Strict digit restriction after +
            val = val.substring(0, 1) + val.substring(1).replace(/[^0-9]/g, '');
            
            // Limit to 13 characters
            if (val.length > 13) val = val.substring(0, 13);
            
            this.value = val;

            const trimmed = val.trim();
            if (trimmed.length === 0) {
                updateIndicator(this.id, false, 'This field is required');
                return;
            }

            if (!regexContact.test(trimmed)) {
                updateIndicator(this.id, false, 'Use format +639XXXXXXXXX (11 digits after +63)');
            } else {
                updateIndicator(this.id, true);
            }
        });

        input.addEventListener('focus', function() {
            if (this.value === '') {
                this.value = '+639';
                this.dispatchEvent(new Event('input'));
            } else if (!regexContact.test(this.value.trim()) && indicators[this.id]) {
                indicators[this.id].innerHTML = '<span class="hint">' + HINTS.contact_number + '</span>';
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            let paste = (e.clipboardData || window.clipboardData).getData('text');
            // Basic normalization: remove spaces, handle 09 -> +639
            paste = paste.replace(/\s/g, '');
            if (paste.startsWith('09')) paste = '+63' + paste.substring(1);
            if (paste.startsWith('9')) paste = '+63' + paste;
            
            this.value = paste;
            this.dispatchEvent(new Event('input'));
        });
    });

    // DOB Validation
    const dobInput = document.querySelector('.validate-advanced-dob');
    if (dobInput) {
        dobInput.addEventListener('focus', function() {
            if (!this.value && indicators[this.id]) {
                indicators[this.id].innerHTML = '<span class="hint">' + HINTS.dob + '</span>';
                indicators[this.id].dataset.valid = '';
            }
        });
        dobInput.addEventListener('input', function() {
            if (!this.value) {
                updateIndicator(this.id, false, 'Select your date of birth');
                return;
            }
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;

            if (dob > today) {
                updateIndicator(this.id, false, 'Date cannot be in the future');
            } else if (age < 13) {
                updateIndicator(this.id, false, 'You must be at least 13 years old');
            } else {
                updateIndicator(this.id, true);
            }
        });
    }

    // Initial check on load - validate filled fields, show hints for empty
    document.querySelectorAll('.validate-advanced-name, .validate-advanced-dob, .validate-advanced-contact').forEach(input => {
        if (input.value.trim()) {
            input.dispatchEvent(new Event('input'));
        } else if (indicators[input.id] && HINTS[input.id]) {
            indicators[input.id].innerHTML = '<span class="hint">' + HINTS[input.id] + '</span>';
        }
    });

    if (fProfile) {
        fProfile.addEventListener('submit', function(e) {
            checkFormValidity();
            if (btnSubmit.disabled) {
                e.preventDefault();
                alert('Please correct all invalid fields before updating.');
            }
        });
    }

    // Password fields (keeping existing logic but integrating with indicators if needed)
    // [Existing password logic continues below...]
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

