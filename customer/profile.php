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
/* ── Profile Page Brand-Aligned Refinement ─── */
:root {
    --pf-bg-primary: #030d11;
    --pf-bg-secondary: #0a1f26;
    --pf-accent: #53c5e0;
    --pf-accent-hover: #32a1c4;
    --pf-text-main: #eaf6fb;
    --pf-text-muted: #9fc4d4;
    --pf-border: rgba(83, 197, 224, 0.15);
}

.profile-page-wrap {
    max-width: 1000px;
    margin: 0 auto;
    padding: 3rem 1.5rem 5rem;
}
.profile-page-title {
    font-size: 2.25rem;
    font-weight: 800;
    color: var(--pf-text-main);
    margin-bottom: 2.5rem;
    letter-spacing: -0.03em;
}
.profile-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 3rem;
    align-items: start;
}
@media (max-width: 1024px) {
    .profile-layout { grid-template-columns: 1fr; gap: 2rem; }
}

/* ─ Sidebar ─ */
.profile-sidebar {
    position: sticky;
    top: 100px;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.profile-sidebar-card {
    background: var(--pf-bg-secondary);
    border-radius: 20px;
    padding: 2.5rem 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    border: 1px solid var(--pf-border);
    text-align: center;
}
.profile-avatar-wrap {
    position: relative;
    display: inline-block;
    margin-bottom: 1.5rem;
}
.profile-avatar-ring {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 1px solid var(--pf-border);
    background: rgba(255,255,255,0.03);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto;
    transition: all 0.3s ease;
}
.profile-avatar-ring img {
    width: 100%; height: 100%; object-fit: cover;
}
.profile-avatar-edit-btn {
    position: absolute;
    bottom: 2px; right: 2px;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--pf-accent);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: all 0.2s;
    border: 2px solid var(--pf-bg-secondary);
}
.profile-avatar-edit-btn:hover { background: var(--pf-accent-hover); transform: scale(1.05); }

.profile-user-name {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--pf-text-main);
    margin-bottom: 6px;
}
.profile-user-email {
    font-size: 0.9rem;
    color: var(--pf-text-muted);
    word-break: break-all;
}

.profile-nav-card {
    background: var(--pf-bg-secondary);
    border-radius: 20px;
    padding: 0.75rem;
    border: 1px solid var(--pf-border);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.profile-nav-list {
    list-style: none;
    padding: 0; margin: 0;
}
.profile-nav-item a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--pf-text-muted);
    text-decoration: none;
    transition: all 0.2s;
}
.profile-nav-item a:hover {
    background: rgba(255,255,255,0.03);
    color: var(--pf-text-main);
}
.profile-nav-item a.active {
    background: rgba(83, 197, 224, 0.1);
    color: var(--pf-accent);
}

/* ─ Cards ─ */
.pf-card {
    background: var(--pf-bg-secondary);
    border-radius: 20px;
    padding: 3rem;
    box-shadow: 0 4px 30px rgba(0,0,0,0.2);
    border: 1px solid var(--pf-border);
    margin-bottom: 2.5rem;
}
@media (max-width: 640px) {
    .pf-card { padding: 1.5rem; }
}
.pf-card-header {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding-bottom: 1.5rem;
    margin-bottom: 2.5rem;
    border-bottom: 1px solid var(--pf-border);
}
.pf-card-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: rgba(255,255,255,0.03);
    display: flex; align-items: center; justify-content: center;
    color: var(--pf-accent);
    border: 1px solid var(--pf-border);
}
.pf-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--pf-text-main);
}
.pf-card-subtitle {
    font-size: 0.9rem;
    color: var(--pf-text-muted);
    margin-top: 4px;
}

/* ─ Form elements ─ */
.pf-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--pf-text-muted);
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.pf-input {
    width: 100%;
    padding: 14px 18px;
    border: 1px solid var(--pf-border);
    border-radius: 12px;
    font-size: 1rem;
    color: var(--pf-text-main);
    background: rgba(0, 0, 0, 0.15);
    transition: all 0.2s;
}
.pf-input:focus {
    outline: none;
    border-color: var(--pf-accent);
    background: rgba(0,0,0,0.25);
    box-shadow: 0 0 0 4px rgba(83, 197, 224, 0.15);
}
.pf-input:disabled { opacity: 0.5; cursor: not-allowed; }

.pf-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 36px;
    background: var(--pf-accent);
    color: #030d11;
    font-size: 1rem;
    font-weight: 800;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}
.pf-btn-primary:hover { background: var(--pf-accent-hover); transform: translateY(-1px); }
.pf-btn-primary:active { transform: translateY(0); }

.pf-alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 2rem;
    font-weight: 500;
}
.pf-alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
.pf-alert-success { background: rgba(83, 197, 224, 0.1); border: 1px solid rgba(83, 197, 224, 0.2); color: #53c5e0; }

.live-indicator { font-size: 0.75rem; margin-top: 6px; min-height: 1.2rem; }
.live-indicator.error { color: #f87171; font-weight: 600; }
.live-indicator .hint { color: var(--pf-text-muted); opacity: 0.7; }
#pw-checklist li { color: var(--pf-text-muted); font-size: 0.75rem; }
#pw-checklist li.ok { color: #4ade80; }
#pw-checklist li.fail { opacity: 0.6; }

/* ── Overriding some legacy stuff ── */
.req { color: #f87171; margin-left: 2px; }
.addr-select-wrap select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1.25rem; }
</style>

<div class="min-h-screen py-8">
  <div class="profile-page-wrap">
    <h1 class="profile-page-title">My Profile</h1>

    <?php if ($error): ?>
    <div class="pf-alert pf-alert-error" style="margin-bottom:1.5rem;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round"/></svg>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="pf-alert pf-alert-success" style="margin-bottom:1.5rem;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <div class="profile-layout">

      <!-- ── SIDEBAR ── -->
      <aside class="profile-sidebar">
        <!-- Avatar card -->
        <div class="profile-sidebar-card">
          <div class="profile-avatar-wrap">
            <div class="profile-avatar-ring">
              <?php if (!empty($customer['profile_picture'])): ?>
                <img src="/printflow/public/assets/uploads/profiles/<?php echo htmlspecialchars($customer['profile_picture']); ?>?t=<?php echo time(); ?>" alt="Avatar" id="profile-preview">
              <?php else: ?>
                <svg width="46" height="46" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <img src="" alt="Profile" style="display:none;width:100%;height:100%;object-fit:cover;" id="profile-preview">
              <?php endif; ?>
            </div>
            <label for="profile_picture" class="profile-avatar-edit-btn" title="Change photo">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </label>
          </div>
          <div class="profile-user-name"><?php echo htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name'])); ?></div>
          <div class="profile-user-email"><?php echo htmlspecialchars($customer['email']); ?></div>
          <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--pf-border);font-size:0.85rem;color:var(--pf-text-muted);">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span>Joined</span><span style="color:var(--pf-text-main);font-weight:600;"><?php echo isset($customer['created_at']) ? date('M Y', strtotime($customer['created_at'])) : 'Account member'; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span>Account</span><span style="color:#4ade80;font-weight:700;">Verified</span></div>
          </div>
        </div>
        <!-- Nav -->
        <div class="profile-nav-card">
          <ul class="profile-nav-list">
            <li class="profile-nav-item"><a href="#section-profile" class="active">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              Profile Info
            </a></li>
            <li class="profile-nav-item"><a href="#section-address">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              Address
            </a></li>
            <li class="profile-nav-item"><a href="#section-password">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              Security
            </a></li>
          </ul>
        </div>
      </aside>

      <!-- ── MAIN CONTENT ── -->
      <div>

        <!-- Profile Information card -->
        <div class="pf-card" id="section-profile">
          <div class="pf-card-header">
            <div class="pf-card-icon">
              <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
              <div class="pf-card-title">Personal Details</div>
              <div class="pf-card-subtitle">Manage your identity and contact info</div>
            </div>
          </div>

          <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="update_profile" value="1">
            <input type="file" id="profile_picture" name="profile_picture" class="hidden" accept="image/*" style="display:none;"
                   onchange="const f=this.files[0];if(f){const r=new FileReader();r.onload=e=>{const p=document.getElementById('profile-preview');p.src=e.target.result;p.style.display='block';const ph=document.getElementById('profile-placeholder');if(ph)ph.style.display='none';};r.readAsDataURL(f);}">
            <div style="display:none;" id="profile-placeholder"></div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:1.5rem;">
              <div>
                <label for="first_name" class="pf-label">First Name<span class="req">*</span></label>
                <input type="text" id="first_name" name="first_name" class="pf-input input-field validate-advanced-name" placeholder="First Name" required value="<?php echo htmlspecialchars($customer['first_name']); ?>" maxlength="50">
                <div class="live-indicator" data-for="first_name"></div>
              </div>
              <div>
                <label for="middle_name" class="pf-label">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name" class="pf-input input-field validate-advanced-name" placeholder="Middle Name" value="<?php echo htmlspecialchars($customer['middle_name'] ?? ''); ?>" maxlength="50">
                <div class="live-indicator" data-for="middle_name"></div>
              </div>
              <div>
                <label for="last_name" class="pf-label">Last Name<span class="req">*</span></label>
                <input type="text" id="last_name" name="last_name" class="pf-input input-field validate-advanced-name" placeholder="Last Name" required value="<?php echo htmlspecialchars($customer['last_name']); ?>" maxlength="50">
                <div class="live-indicator" data-for="last_name"></div>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.25rem;margin-bottom:1.75rem;">
              <div>
                <label for="email" class="pf-label">Email Address</label>
                <input type="email" id="email" class="pf-input input-field" placeholder="Email" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled>
                <div style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">Email cannot be changed</div>
              </div>
              <div>
                <label for="contact_number" class="pf-label">Contact Number<span class="req">*</span></label>
                <input type="tel" id="contact_number" name="contact_number" class="pf-input input-field validate-advanced-contact" placeholder="+639XXXXXXXXX" value="<?php echo htmlspecialchars($customer['contact_number'] ?? ''); ?>" maxlength="13" required>
                <div class="live-indicator" data-for="contact_number"></div>
              </div>
              <div>
                <label for="dob" class="pf-label">Date of Birth</label>
                <input type="date" id="dob" name="dob" class="pf-input input-field validate-advanced-dob" value="<?php echo htmlspecialchars($customer['dob'] ?? ''); ?>" max="<?php echo $max_birthday; ?>">
                <div class="live-indicator" data-for="dob"></div>
              </div>
              <div>
                <label for="gender" class="pf-label">Gender</label>
                <select id="gender" name="gender" class="pf-input input-field">
                  <option value="">Select Gender</option>
                  <option value="Male" <?php echo ($customer['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                  <option value="Female" <?php echo ($customer['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                  <option value="Other" <?php echo ($customer['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
              </div>
            </div>

            <div style="display:flex;justify-content:flex-end;">
              <button type="submit" id="btn-update-profile" class="pf-btn-primary">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save Profile
              </button>
            </div>
          </form>
        </div>

        <!-- Address card -->
        <div class="pf-card" id="section-address">
          <div class="pf-card-header">
            <div class="pf-card-icon">
              <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
              <div class="pf-card-title">Default Address</div>
              <div class="pf-card-subtitle">Used for delivery estimations</div>
            </div>
          </div>

          <form method="POST" action="" id="address-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="update_address" value="1">
            <div id="addr-alert" style="display:none;padding:12px 16px;border-radius:10px;margin-bottom:1.25rem;font-size:0.875rem;font-weight:500;"></div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
              <div>
                <label class="pf-label" for="addr_region">Region</label>
                <div class="addr-select-wrap">
                  <select id="addr_region" name="region" class="pf-input input-field addr-select" data-level="region">
                    <option value="">— Select Region —</option>
                  </select>
                  <span class="addr-spinner" id="spin_region"></span>
                </div>
              </div>
              <div>
                <label class="pf-label" for="addr_province">Province</label>
                <div class="addr-select-wrap">
                  <select id="addr_province" name="province" class="pf-input input-field addr-select" data-level="province" disabled>
                    <option value="">— Select Province —</option>
                  </select>
                  <span class="addr-spinner" id="spin_province"></span>
                </div>
              </div>
              <div>
                <label class="pf-label" for="addr_city">City / Municipality</label>
                <div class="addr-select-wrap">
                  <select id="addr_city" name="city" class="pf-input input-field addr-select" data-level="city" disabled>
                    <option value="">— Select City / Municipality —</option>
                  </select>
                  <span class="addr-spinner" id="spin_city"></span>
                </div>
              </div>
              <div>
                <label class="pf-label" for="addr_barangay">Barangay</label>
                <div class="addr-select-wrap">
                  <select id="addr_barangay" name="barangay" class="pf-input input-field addr-select" data-level="barangay" disabled>
                    <option value="">— Select Barangay —</option>
                  </select>
                  <span class="addr-spinner" id="spin_barangay"></span>
                </div>
              </div>
            </div>

            <div style="margin-bottom:1.25rem;">
              <label class="pf-label" for="addr_street">House No. / Lot / Block / Street</label>
              <input type="text" id="addr_street" name="street_address" class="pf-input input-field"
                     placeholder="e.g. 123 Sampaguita St., Brgy. Poblacion"
                     value="<?php echo htmlspecialchars($customer['street_address'] ?? ''); ?>">
            </div>

            <div id="addr-preview" style="display:none;background:rgba(83,197,224,0.05);border:1px solid rgba(83,197,224,0.2);border-radius:12px;padding:16px;margin-bottom:1.5rem;font-size:0.9rem;color:var(--pf-text-main);">
              <strong>Delivery Destination:</strong> <span id="addr-preview-text" style="color:var(--pf-accent);"></span>
            </div>

            <div style="display:flex;justify-content:flex-end;">
              <button type="submit" class="pf-btn-primary">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save Address
              </button>
            </div>
          </form>
        </div>

        <!-- Change Password card -->
        <div class="pf-card" id="section-password">
          <div class="pf-card-header">
            <div class="pf-card-icon">
              <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <div>
              <div class="pf-card-title">Security Settings</div>
              <div class="pf-card-subtitle">Keep your account guarded</div>
            </div>
          </div>

          <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="change_password" value="1">

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:1.75rem;">
              <div>
                <label for="current_password" class="pf-label">Current Password<span class="req">*</span></label>
                <input type="password" id="current_password" name="current_password" class="pf-input input-field" placeholder="••••••••" required>
              </div>
              <div>
                <label for="new_password" class="pf-label">New Password<span class="req">*</span></label>
                <input type="password" id="new_password" name="new_password" class="pf-input input-field" placeholder="••••••••" required minlength="8">
                <ul id="pw-checklist" style="display:none;">
                  <li id="pw-rule-len" class="fail"><span class="ck">✗</span> 8–64 chars</li>
                  <li id="pw-rule-upper" class="fail"><span class="ck">✗</span> Uppercase</li>
                  <li id="pw-rule-lower" class="fail"><span class="ck">✗</span> Lowercase</li>
                  <li id="pw-rule-num" class="fail"><span class="ck">✗</span> Number</li>
                  <li id="pw-rule-spec" class="fail"><span class="ck">✗</span> Special char</li>
                </ul>
              </div>
              <div>
                <label for="confirm_password" class="pf-label">Confirm Password<span class="req">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="pf-input input-field" placeholder="••••••••" required minlength="8">
                <p class="text-[11px] font-bold mt-2" id="pw-match-indicator" style="font-size:0.72rem;margin-top:4px;"></p>
              </div>
            </div>

            <div style="display:flex;justify-content:flex-end;">
              <button type="submit" class="pf-btn-primary">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Change Password
              </button>
            </div>
          </form>
        </div>

      </div><!-- /main -->
    </div><!-- /layout -->
  </div><!-- /wrap -->
</div>

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

