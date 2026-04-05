<?php
/**
 * Customer Profile Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Handle address API requests inline (same as admin profile)
if (isset($_GET['address_action'])) {
    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=86400'); // Cache for 24 hours

    $fetchJson = static function (string $url): array {
        $cacheDir = sys_get_temp_dir() . '/psgc_cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        
        $cachePath = $cacheDir . '/' . md5($url) . '.json';
        
        // Check cache first (24 hour cache)
        if (file_exists($cachePath) && (time() - filemtime($cachePath) < 86400)) {
            $cached = file_get_contents($cachePath);
            if ($cached) return json_decode($cached, true);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Accept: application/json']
            ]);
            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($body === false || $httpCode >= 400) {
                throw new RuntimeException($err ?: ('Address data request failed (' . $httpCode . ')'));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Accept: application/json\r\n",
                    'timeout' => 10,
                ]
            ]);
            $body = @file_get_contents($url, false, $context);
            if ($body === false) {
                throw new RuntimeException('Unable to fetch address data.');
            }
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid address dataset response.');
        }
        
        @file_put_contents($cachePath, $body);
        return $decoded;
    };

    try {
        $base = 'https://psgc.gitlab.io/api';
        $action = $_GET['address_action'] ?? '';

        if ($action === 'provinces') {
            $rows = $fetchJson($base . '/provinces/');
            $data = array_map(static fn($r) => [
                'code' => (string)($r['code'] ?? ''),
                'name' => (string)($r['name'] ?? ''),
            ], $rows);
            usort($data, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        if ($action === 'cities') {
            $provinceCode = preg_replace('/[^0-9]/', '', (string)($_GET['province_code'] ?? ''));
            if ($provinceCode === '') {
                throw new RuntimeException('Province code is required.');
            }
            $rows = $fetchJson($base . '/provinces/' . rawurlencode($provinceCode) . '/cities-municipalities/');
            $data = array_map(static fn($r) => [
                'code' => (string)($r['code'] ?? ''),
                'name' => (string)($r['name'] ?? ''),
            ], $rows);
            usort($data, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        if ($action === 'barangays') {
            $cityCode = preg_replace('/[^0-9]/', '', (string)($_GET['city_code'] ?? ''));
            if ($cityCode === '') {
                throw new RuntimeException('City/Municipality code is required.');
            }
            $rows = $fetchJson($base . '/cities-municipalities/' . rawurlencode($cityCode) . '/barangays/');
            $data = array_map(static fn($r) => [
                'code' => (string)($r['code'] ?? ''),
                'name' => (string)($r['name'] ?? ''),
            ], $rows);
            usort($data, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        throw new RuntimeException('Invalid address action.');
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

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

// Ensure ID verification columns exist
global $conn;
if (empty(db_query("SHOW COLUMNS FROM customers LIKE 'id_status'"))) {
    $conn->query("ALTER TABLE customers ADD COLUMN id_image VARCHAR(255) DEFAULT NULL, ADD COLUMN id_type VARCHAR(100) DEFAULT NULL, ADD COLUMN id_status ENUM('None','Pending','Verified','Rejected') DEFAULT 'None', ADD COLUMN id_reject_reason VARCHAR(255) DEFAULT NULL");
}

// Handle ID upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (empty($_FILES['id_image']['tmp_name']) || $_FILES['id_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select an ID image to upload.';
    } else {
        $finfo2 = new finfo(FILEINFO_MIME_TYPE);
        $mime2 = $finfo2->file($_FILES['id_image']['tmp_name']);
        if (!in_array($mime2, ['image/jpeg','image/png','image/webp'])) {
            $error = 'ID image must be JPG, PNG, or WEBP.';
        } elseif ($_FILES['id_image']['size'] > 5 * 1024 * 1024) {
            $error = 'ID image must be under 5MB.';
        } else {
            $id_type = sanitize($_POST['id_type'] ?? '');
            if (empty($id_type)) {
                $error = 'Please select an ID type.';
            } else {
                $ext2 = pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION);
                $fname2 = 'id_customer_' . $customer_id . '_' . time() . '.' . $ext2;
                $id_dir = __DIR__ . '/../uploads/ids/';
                if (!is_dir($id_dir)) mkdir($id_dir, 0755, true);
                if (move_uploaded_file($_FILES['id_image']['tmp_name'], $id_dir . $fname2)) {
                    db_execute("UPDATE customers SET id_image=?, id_type=?, id_status='Pending', id_reject_reason=NULL WHERE customer_id=?", 'ssi', [$fname2, $id_type, $customer_id]);
                    $success = 'ID submitted for verification. We will review it shortly.';
                    $customer = db_query("SELECT * FROM customers WHERE customer_id=?", 'i', [$customer_id])[0];
                } else {
                    $error = 'Failed to upload ID image.';
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
    max-width: 1100px;
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
    word-wrap: break-word; overflow-wrap: break-word; word-break: break-word;
}

.profile-user-email {
    font-size: 0.875rem; color: #64748b; margin-bottom: 1rem; 
    word-wrap: break-word; overflow-wrap: break-word; word-break: break-all;
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
    padding: 7px 24px;
    border-radius: 3px;
    border: none;
    background: #0a2530;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-transform: uppercase;
    font-size: 0.8rem;
    text-decoration: none;
}

.pf-btn-primary:hover {
    opacity: 0.9;
}

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
                            <?php
                            $id_st = $customer['id_status'] ?? 'None';
                            $st_color = $id_st==='Verified' ? '#16a34a' : ($id_st==='Pending' ? '#b45309' : ($id_st==='Rejected' ? '#b91c1c' : '#64748b'));
                            $st_label = $id_st==='Verified' ? 'ID Verified' : ($id_st==='Pending' ? 'Pending Review' : ($id_st==='Rejected' ? 'ID Rejected' : 'Unverified'));
                            ?>
                            <span style="font-weight:700;color:<?php echo $st_color;?>"><?php echo $st_label; ?></span>
                        </div>
                    </div>
                </div>


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
                        
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
                            <div class="pf-field-group">
                                <label class="pf-label" for="addr_province">Province *</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_province" name="province" class="pf-input addr-select" data-level="province">
                                        <option value="">— Select Province —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_province"></span>
                                </div>
                            </div>
                            <div class="pf-field-group">
                                <label class="pf-label" for="addr_city">City / Municipality *</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_city" name="city" class="pf-input addr-select" data-level="city" disabled>
                                        <option value="">— Select City / Municipality —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_city"></span>
                                </div>
                            </div>
                            <div class="pf-field-group">
                                <label class="pf-label" for="addr_barangay">Barangay *</label>
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
                    
                    <form method="POST" action="" novalidate>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_password" value="1">

                        <div class="form-grid">
                            <div class="pf-field-group" id="group_current_password">
                                <label for="current_password" class="pf-label">Current Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="current_password" name="current_password" class="pf-input" placeholder="Enter current password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </div>
                                <div class="error-message" id="error_current_password">Current password is required.</div>
                            </div>
                            <div class="pf-field-group">
                                <!-- empty column for alignment or extra info -->
                                <div style="font-size:0.813rem; color:#64748b; padding-top:2rem;">
                                    Confirm your identity to make security changes.
                                </div>
                            </div>
                            <div class="pf-field-group" id="group_new_password">
                                <label for="new_password" class="pf-label">New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="new_password" name="new_password" class="pf-input" placeholder="8+ characters" minlength="8">
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </div>
                                <div class="error-message" id="error_new_password">Invalid password format.</div>
                            </div>
                            <div class="pf-field-group" id="group_confirm_password">
                                <label for="confirm_password" class="pf-label">Confirm New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" class="pf-input" placeholder="Repeat new password" minlength="8">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </div>
                                <div class="error-message" id="error_confirm_password">Passwords do not match.</div>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                            <button type="submit" class="pf-btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>

                <!-- ID Verification Section -->
                <?php
                $id_status = $customer['id_status'] ?? 'None';
                $id_type   = $customer['id_type'] ?? '';
                $id_image  = $customer['id_image'] ?? '';
                $id_reject = $customer['id_reject_reason'] ?? '';
                $status_colors = [
                    'None'     => ['#64748b','#f1f5f9','Not Submitted'],
                    'Pending'  => ['#b45309','#fffbeb','Under Review'],
                    'Verified' => ['#15803d','#f0fdf4','Verified ✓'],
                    'Rejected' => ['#b91c1c','#fef2f2','Rejected'],
                ];
                [$sc,$sbg,$slabel] = $status_colors[$id_status] ?? $status_colors['None'];
                ?>
                <div class="profile-card" id="section-id" style="padding-top:2rem;border-top:1px solid #e2e8f0;">
                    <h3 class="profile-card-title">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                        ID Verification
                        <span style="font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:99px;background:<?php echo $sbg;?>;color:<?php echo $sc;?>;margin-left:8px;"><?php echo $slabel; ?></span>
                    </h3>

                    <?php if ($id_status === 'Rejected'): ?>
                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;margin-bottom:1.25rem;font-size:0.875rem;color:#b91c1c;">
                        <strong>Rejected:</strong> <?php echo htmlspecialchars($id_reject ?: 'Your ID was rejected. Please resubmit a clearer photo.'); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($id_status === 'Verified'): ?>
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;font-size:0.9rem;color:#15803d;">
                        <strong>✓ Your identity has been verified.</strong> You can now place orders.
                    </div>
                    <?php else: ?>
                    <p style="font-size:0.875rem;color:#64748b;margin-bottom:1.25rem;">Upload a valid government-issued ID to verify your identity before placing orders.</p>

                    <?php if (!empty($id_image) && $id_status === 'Pending'): ?>
                    <div style="margin-bottom:1.25rem;padding:12px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:0.875rem;color:#92400e;">
                        ⏳ Your ID is currently under review. We'll notify you once it's approved.
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="upload_id" value="1">
                        <div class="form-grid">
                            <div class="pf-field-group">
                                <label class="pf-label">ID Type *</label>
                                <select name="id_type" class="pf-input" required>
                                    <option value="">— Select ID Type —</option>
                                    <?php foreach (['Philippine Passport','Driver\'s License','SSS ID','PhilHealth ID','Postal ID','Voter\'s ID','PRC ID','National ID (PhilSys)','UMID','Senior Citizen ID','PWD ID','Barangay ID'] as $idt): ?>
                                    <option value="<?php echo htmlspecialchars($idt); ?>" <?php echo $id_type===$idt?'selected':''; ?>><?php echo htmlspecialchars($idt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="pf-field-group">
                                <label class="pf-label">ID Photo * <span style="font-weight:400;color:#94a3b8;">(JPG/PNG, max 5MB)</span></label>
                                <input type="file" name="id_image" accept="image/jpeg,image/png,image/webp" class="pf-input" style="padding:8px;" required onchange="previewIdImage(this)">
                            </div>
                        </div>
                        <div id="id-preview-wrap" style="display:none;margin-top:1rem;">
                            <img id="id-preview-img" src="" style="max-height:180px;border-radius:8px;border:1px solid #e2e8f0;">
                        </div>
                        <?php if (!empty($id_image)): ?>
                        <div style="margin-top:1rem;">
                            <p style="font-size:0.75rem;color:#64748b;margin-bottom:6px;">Previously submitted:</p>
                            <img src="/printflow/uploads/ids/<?php echo htmlspecialchars($id_image); ?>" style="max-height:140px;border-radius:8px;border:1px solid #e2e8f0;">
                        </div>
                        <?php endif; ?>
                        <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;">
                            <button type="submit" class="pf-btn-primary"><?php echo $id_status==='Rejected'?'Resubmit ID':'Submit ID for Verification'; ?></button>
                        </div>
                    </form>
                    <?php endif; ?>
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

/* Password validation */
.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.password-wrapper input {
    padding-right: 45px !important;
    width: 100%;
}
/* Hide browser default password toggle */
.password-wrapper input::-ms-reveal,
.password-wrapper input::-ms-clear {
    display: none;
}
.password-wrapper input::-webkit-credentials-auto-fill-button,
.password-wrapper input::-webkit-contacts-auto-fill-button {
    visibility: hidden;
    pointer-events: none;
    position: absolute;
    right: 0;
}
.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #9ca3af;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    transition: color 0.2s;
    z-index: 10;
    outline: none;
}
.password-toggle:focus {
    outline: none;
    border: none;
}
.password-toggle:hover {
    color: #53C5E0;
}
.pf-field-group.is-invalid .pf-input {
    border-color: #ef4444 !important;
    background-color: #fef2f2;
}
.error-message {
    color: #ef4444;
    font-size: 11px;
    margin-top: 4px;
    display: none;
    font-weight: 500;
}
.pf-field-group.is-invalid .error-message {
    display: block;
}
</style>

<script>
(function () {
    // ── Saved values from PHP (for pre-selection) ──
    const SAVED = {
        province:      <?php echo json_encode($customer['province']   ?? null); ?>,
        city:          <?php echo json_encode($customer['city']     ?? null); ?>,
        barangay:      <?php echo json_encode($customer['barangay'] ?? null); ?>,
    };

    const API = 'profile.php';

    const selProvince = document.getElementById('addr_province');
    const selCity     = document.getElementById('addr_city');
    const selBarangay = document.getElementById('addr_barangay');

    const spinOf = {
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
        const addressLineInput = document.getElementById('addr_street');
        const barangaySelect = selBarangay;
        const citySelect = selCity;
        const provinceSelect = selProvince;
        const addressPreview = preview;
        
        if (!addressLineInput || !barangaySelect || !citySelect || !provinceSelect || !addressPreview) return;
        var parts = [];
        var line = addressLineInput.value.trim();
        var barangay = barangaySelect.value.trim();
        var city = citySelect.value.trim();
        var province = provinceSelect.value.trim();

        if (line) parts.push(line);
        if (barangay) parts.push('Brgy. ' + barangay);
        if (city) parts.push(city);
        if (province) parts.push(province);
        parts.push('Philippines');

        if (parts.length > 1) {
            previewText.textContent = parts.join(', ');
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }

    // ── FETCH provinces ──
    async function loadProvinces() {
        spin('province', true);
        selProvince.disabled = true;
        selProvince.innerHTML = '<option value="">Loading provinces...</option>';
        try {
            const res  = await fetch(`${API}?address_action=provinces`);
            const data = await res.json();
            if (data.success) {
                populate(selProvince, data.data, '— Select Province —', SAVED.province, 'province');
                selProvince.disabled = false;
                if (SAVED.province && selectedCode(selProvince)) {
                    await loadCities(selectedCode(selProvince), true);
                }
            }
        } catch(e) { 
            console.error('Addr: provinces', e); 
            selProvince.innerHTML = '<option value="">Failed to load provinces</option>';
        }
        spin('province', false);
    }

    // ── FETCH cities ──
    async function loadCities(provinceCode, auto) {
        spin('city', true);
        selCity.innerHTML = '<option value="">Loading cities...</option>';
        selCity.disabled = true;
        reset(selBarangay, '— Select Barangay —');
        try {
            const res  = await fetch(`${API}?address_action=cities&province_code=${provinceCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selCity, data.data, '— Select City / Municipality —', auto ? SAVED.city : null, 'city');
                selCity.disabled = false;
                if (auto && SAVED.city && selectedCode(selCity)) {
                    await loadBarangays(selectedCode(selCity), true);
                }
            }
        } catch(e) { 
            console.error('Addr: cities', e); 
            selCity.innerHTML = '<option value="">Failed to load cities</option>';
        }
        spin('city', false);
        updatePreview();
    }

    // ── FETCH barangays ──
    async function loadBarangays(cityCode, auto) {
        spin('barangay', true);
        selBarangay.innerHTML = '<option value="">Loading barangays...</option>';
        selBarangay.disabled = true;
        try {
            const res  = await fetch(`${API}?address_action=barangays&city_code=${cityCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selBarangay, data.data, '— Select Barangay —', auto ? SAVED.barangay : null, 'barangay');
                selBarangay.disabled = false;
            }
        } catch(e) { 
            console.error('Addr: barangays', e); 
            selBarangay.innerHTML = '<option value="">Failed to load barangays</option>';
        }
        spin('barangay', false);
        updatePreview();
    }

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

    // ── Boot: load all provinces on page load ──
    loadProvinces();
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

    // Password validation
    const passwordForm = document.querySelector('form[action=""]');
    const passwordFieldIds = ['current_password', 'new_password', 'confirm_password'];
    const passwordTouched = { current_password: false, new_password: false, confirm_password: false };

    const passwordValidators = {
        new_password: (val) => {
            if (!val) return "New password is required.";
            if (val.length < 8) return "Password must be at least 8 characters.";
            if (val.length > 100) return "Password must be at most 100 characters.";
            if (!/[A-Z]/.test(val)) return "Password must have an uppercase letter.";
            if (!/[a-z]/.test(val)) return "Password must have a lowercase letter.";
            if (!/[0-9]/.test(val)) return "Password must have a number.";
            if (!/[^A-Za-z0-9]/.test(val)) return "Password must have a special character.";
            if (/\s/.test(val)) return "Password must not contain spaces.";
            return null;
        }
    };

    function clearPasswordValidationState(id) {
        const group = document.getElementById('group_' + id);
        if (!group) return;
        group.classList.remove('is-invalid');
    }

    function validatePasswordField(id, validator, options = {}) {
        const input = document.getElementById(id);
        const group = document.getElementById('group_' + id);
        const error = document.getElementById('error_' + id);
        if (!input || !group || !error) return true;

        const val = input.value || '';
        const trimmed = val.trim();

        if (options.skipEmpty && trimmed === '') {
            clearPasswordValidationState(id);
            return true;
        }
        if (options.onlyWhenTouched && !options.isTouched) {
            clearPasswordValidationState(id);
            return false;
        }

        const errorMessage = validator(trimmed);
        if (errorMessage) {
            group.classList.add('is-invalid');
            error.textContent = errorMessage;
            return false;
        }

        group.classList.remove('is-invalid');
        return true;
    }

    function checkPassword(force = false) {
        const current = document.getElementById('current_password');
        const newPass = document.getElementById('new_password');
        const confirm = document.getElementById('confirm_password');
        if (!current || !newPass || !confirm) return;

        const hasAnyInput = (current.value + newPass.value + confirm.value).trim().length > 0;
        const mustValidate = force || hasAnyInput || Object.values(passwordTouched).some(Boolean);

        if (!mustValidate) {
            passwordFieldIds.forEach(clearPasswordValidationState);
            return;
        }

        const currentValid = validatePasswordField('current_password', (val) => !val ? 'Current password is required.' : null, {
            onlyWhenTouched: !force,
            isTouched: passwordTouched.current_password || current.value.length > 0
        });

        const nValid = validatePasswordField('new_password', passwordValidators.new_password, {
            onlyWhenTouched: !force,
            isTouched: passwordTouched.new_password || newPass.value.length > 0
        });

        const cGroup = document.getElementById('group_confirm_password');
        const cError = document.getElementById('error_confirm_password');
        if (!cGroup || !cError) return;

        let confirmValid = false;
        if (!force && !passwordTouched.confirm_password && confirm.value === '') {
            clearPasswordValidationState('confirm_password');
        } else if (!confirm.value) {
            cGroup.classList.add('is-invalid');
            cError.textContent = "Confirm new password is required.";
        } else if (confirm.value !== newPass.value) {
            cGroup.classList.add('is-invalid');
            cError.textContent = "Passwords do not match.";
        } else {
            cGroup.classList.remove('is-invalid');
            confirmValid = true;
        }
    }

    // Attach password field listeners
    passwordFieldIds.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', () => { passwordTouched[id] = true; checkPassword(); });
        el.addEventListener('blur', () => { passwordTouched[id] = true; checkPassword(); });
    });

    // Password form submit validation
    const passwordFormEl = document.querySelector('form input[name="change_password"]')?.closest('form');
    if (passwordFormEl) {
        passwordFormEl.addEventListener('submit', function(e) {
            checkPassword(true);
            const hasErrors = passwordFieldIds.some(id => {
                const group = document.getElementById('group_' + id);
                return group && group.classList.contains('is-invalid');
            });
            if (hasErrors) {
                e.preventDefault();
            }
        });
    }

    checkPassword(false);

    // Password toggle function
    window.togglePassword = function(fieldId, button) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        if (field.type === 'password') {
            field.type = 'text';
            button.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>';
        } else {
            field.type = 'password';
            button.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
        }
    };
})();
function previewIdImage(input) {
    const wrap = document.getElementById('id-preview-wrap');
    const img  = document.getElementById('id-preview-img');
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => { img.src = e.target.result; wrap.style.display = 'block'; };
        r.readAsDataURL(input.files[0]);
    }
}
</script>

