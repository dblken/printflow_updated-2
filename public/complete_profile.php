<?php
/**
 * Complete Profile - New staff completes their profile via email link
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';
$token = trim($_GET['token'] ?? '');

// Preserve form data
$form_data = [
    'contact_number' => $_POST['contact_number'] ?? '',
    'address_province' => $_POST['address_province'] ?? '',
    'address_city' => $_POST['address_city'] ?? '',
    'address_barangay' => $_POST['address_barangay'] ?? '',
    'address_line' => $_POST['address_line'] ?? '',
    'gender' => $_POST['gender'] ?? '',
    'id_filename' => ''
];

// Preserve uploaded file name across form submissions
if (!empty($_FILES['id_image']['name'])) {
    $form_data['id_filename'] = $_FILES['id_image']['name'];
    // Store in session for persistence
    $_SESSION['temp_id_filename'] = $_FILES['id_image']['name'];
} elseif (!empty($_SESSION['temp_id_filename'])) {
    $form_data['id_filename'] = $_SESSION['temp_id_filename'];
}

if (empty($token)) {
    $error = 'Invalid or missing link. Please use the link from your email.';
    $user = null;
} else {
    // First, ensure the column exists
    try {
        $columns = db_query("SHOW COLUMNS FROM users LIKE 'profile_completion_fields_to_clear'");
        if (empty($columns)) {
            db_execute("ALTER TABLE users ADD COLUMN profile_completion_fields_to_clear TEXT NULL AFTER profile_completion_expires");
        }
    } catch (Exception $e) {
        // Column might already exist or error adding it
    }
    
    $user = db_query("SELECT user_id, first_name, middle_name, last_name, email, contact_number, address, gender, id_validation_image, profile_completion_token, profile_completion_expires, profile_completion_fields_to_clear, status FROM users WHERE profile_completion_token = ?", 's', [$token]);
    $user = $user[0] ?? null;

    if (!$user) {
        $error = 'Invalid or expired link. Please contact your administrator.';
    } elseif (strtotime($user['profile_completion_expires'] ?? '') < time()) {
        $error = 'This link has expired. Please contact your administrator for a new link.';
    } else {
        // Load existing data and clear only specified fields
        $fields_to_clear = [];
        if (!empty($user['profile_completion_fields_to_clear'])) {
            $fields_to_clear = json_decode($user['profile_completion_fields_to_clear'], true) ?: [];
        }
        
        // Pre-fill form data from database, clearing only checked fields
        if (empty($_POST)) {
            // Parse address
            $existingAddr = trim($user['address'] ?? '');
            if (!in_array('address', $fields_to_clear) && $existingAddr) {
                $parts = array_values(array_filter(array_map('trim', explode(',', $existingAddr)), static fn($p) => $p !== ''));
                if (count($parts) >= 4 && strcasecmp(end($parts), 'Philippines') === 0) {
                    $form_data['address_province'] = $parts[count($parts) - 2] ?? '';
                    $form_data['address_city'] = $parts[count($parts) - 3] ?? '';
                    $form_data['address_barangay'] = preg_replace('/^Brgy\.?\s*/i', '', (string)($parts[count($parts) - 4] ?? ''));
                    $form_data['address_line'] = implode(', ', array_slice($parts, 0, -4));
                }
            }
            
            // Pre-fill contact number if not marked for clearing
            if (!in_array('contact', $fields_to_clear) && !empty($user['contact_number'])) {
                $form_data['contact_number'] = $user['contact_number'];
            }
            
            // Pre-fill gender
            if (!empty($user['gender'])) {
                $form_data['gender'] = $user['gender'];
            }
            
            // Keep ID image if not marked for clearing
            if (!in_array('id_image', $fields_to_clear) && !empty($user['id_validation_image'])) {
                $form_data['id_filename'] = $user['id_validation_image'];
                $_SESSION['temp_id_filename'] = $user['id_validation_image'];
            }
        }
    }
}

$max_birthday = date('Y-m-d', strtotime('-18 years'));

// Parse existing address for edit
$addressProvince = $addressCity = $addressBarangay = $addressLine = '';
if ($user && !empty($_POST)) {
    $existingAddr = trim($_POST['address'] ?? '');
    if ($existingAddr) {
        $parts = array_values(array_filter(array_map('trim', explode(',', $existingAddr)), static fn($p) => $p !== ''));
        if (count($parts) >= 4 && strcasecmp(end($parts), 'Philippines') === 0) {
            $addressProvince = $parts[count($parts) - 2] ?? '';
            $addressCity = $parts[count($parts) - 3] ?? '';
            $addressBarangay = preg_replace('/^Brgy\.?\s*/i', '', (string)($parts[count($parts) - 4] ?? ''));
            $addressLine = implode(', ', array_slice($parts, 0, -4));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    // Ensure column exists before POST processing
    try {
        $columns = db_query("SHOW COLUMNS FROM users LIKE 'profile_completion_fields_to_clear'");
        if (empty($columns)) {
            db_execute("ALTER TABLE users ADD COLUMN profile_completion_fields_to_clear TEXT NULL AFTER profile_completion_expires");
        }
    } catch (Exception $e) {
        // Column might already exist
    }
    
    $submit_token = trim($_POST['token'] ?? $_GET['token'] ?? '');
    if (empty($submit_token)) {
        $error = 'Invalid or expired link. Please use the link from your email.';
        $user = null;
    } else {
        $user = db_query("SELECT user_id, first_name, middle_name, last_name, email, profile_completion_token, profile_completion_expires FROM users WHERE profile_completion_token = ?", 's', [$submit_token]);
        $user = $user[0] ?? null;
        if (!$user) {
            $error = 'Invalid or expired link. Please contact your administrator for a new link.';
        } elseif (strtotime($user['profile_completion_expires'] ?? '') < time()) {
            $error = 'This link has expired. Please contact your administrator for a new link.';
            $user = null;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $contact_number = preg_replace('/[^0-9]/', '', trim($_POST['contact_number'] ?? ''));
    $address_province = trim($_POST['address_province'] ?? '');
    $address_city = trim($_POST['address_city'] ?? '');
    $address_barangay = trim($_POST['address_barangay'] ?? '');
    $address_line = trim($_POST['address_line'] ?? '');
    $gender = trim($_POST['gender'] ?? '');

    $addressParts = [];
    if ($address_line !== '') $addressParts[] = $address_line;
    if ($address_barangay !== '') $addressParts[] = 'Brgy. ' . $address_barangay;
    if ($address_city !== '') $addressParts[] = $address_city;
    if ($address_province !== '') $addressParts[] = $address_province;
    $addressParts[] = 'Philippines';
    $address = implode(', ', $addressParts);

    if (empty($contact_number) || !preg_match('/^09\d{9}$/', $contact_number)) {
        $error = 'Valid contact number required (09XXXXXXXXX).';
    } elseif (strlen($address) < 10) {
        $error = 'Please complete the address (province, city, barangay).';
    } else {
        // Check if ID image is uploaded (either new upload or previously uploaded in session)
        $hasIdImage = !empty($_FILES['id_image']['tmp_name']) && $_FILES['id_image']['error'] === UPLOAD_ERR_OK;
        $hasPreviousId = !empty($_SESSION['temp_id_filename']);
        
        if (!$hasIdImage && !$hasPreviousId) {
            $error = 'Please upload your ID image.';
        } else {
            // Use newly uploaded file or skip validation if already uploaded
            if ($hasIdImage) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['id_image']['tmp_name']);
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mime, $allowed)) {
                    $error = 'ID image must be JPG, PNG, GIF, or WEBP.';
                } elseif ($_FILES['id_image']['size'] > 5 * 1024 * 1024) {
                    $error = 'ID image must be under 5MB.';
                }
            }
            
            if (!$error && contact_phone_in_use_across_accounts($contact_number, null, (int)$user['user_id'])) {
                $error = 'This phone number is already used by another account.';
            }
            
            if (!$error) {
                // Process file upload only if new file is provided
                if ($hasIdImage) {
                    $ext = pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
                    $filename = 'id_user_' . $user['user_id'] . '_' . time() . '.' . $ext;
                    $upload_dir = __DIR__ . '/../uploads/ids/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filepath = $upload_dir . $filename;

                    if (!move_uploaded_file($_FILES['id_image']['tmp_name'], $filepath)) {
                        $error = 'Failed to save ID image. Please try again.';
                    }
                } else {
                    // Use placeholder for previously uploaded file (will be handled by admin)
                    $filename = 'pending_' . $user['user_id'] . '.jpg';
                }
                
                if (!$error) {
                    db_execute(
                        "UPDATE users SET contact_number=?, address=?, gender=?, id_validation_image=?, profile_completion_token=NULL, profile_completion_expires=NULL, status='Pending', updated_at=NOW() WHERE user_id=?",
                        'ssssi',
                        [$contact_number, $address, $gender, $filename, $user['user_id']]
                    );

                    $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    $msg = $full_name . ' (' . $user['email'] . ') has completed their profile and is ready for admin review.';
                    $admins = db_query("SELECT user_id, role FROM users WHERE role = 'Admin' AND status = 'Activated'");
                    foreach ($admins as $a) {
                        $recipType = $a['role'] ?? 'Admin';
                        create_notification((int)$a['user_id'], $recipType, $msg, 'System', true, false, (int)$user['user_id']);
                    }

                    $success = 'Profile submitted successfully! An admin will review your information and activate your account. You will be notified once your account is activated.';
                    $user = null;
                    // Clear session temp data on success
                    unset($_SESSION['temp_id_filename']);
                }
            }
        }
    }
}

$page_title = 'Complete Your Profile - PrintFlow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include __DIR__ . '/../includes/favicon_links.php'; ?>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="/printflow/public/assets/js/alpine.min.js" defer></script>
    <style>
        body { font-family: system-ui, sans-serif; background: #f9fafb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 560px; width: 100%; overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #0d9488, #0f766e); color: #fff; padding: 24px; text-align: center; }
        .card-header h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px 0; }
        .card-header p { font-size: 14px; opacity: 0.9; margin: 0; }
        .card-body { padding: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #0d9488; }
        .form-group.is-invalid input, .form-group.is-invalid select { border-color: #ef4444; }
        .error-msg { font-size: 12px; color: #ef4444; margin-top: 4px; display: none; }
        .form-group.is-invalid .error-msg { display: block; }
        .btn { display: block; width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; transition: all 0.2s; position: relative; }
        .btn-primary { background: #0d9488; color: #fff; }
        .btn-primary:hover:not(:disabled) { background: #0f766e; }
        .btn-primary:disabled { background: #9ca3af; cursor: not-allowed; opacity: 0.7; }
        .btn-loading { pointer-events: none; }
        .btn-loading .btn-text { opacity: 0; }
        .btn-loading .spinner { display: inline-block; }
        .spinner { display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: translate(-50%, -50%) rotate(360deg); } }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .id-reference { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .id-reference h3 { font-size: 13px; font-weight: 600; color: #374151; margin: 0 0 8px 0; }
        .id-reference img { max-width: 100%; height: auto; border-radius: 6px; }
        .id-upload { border: 2px dashed #e5e7eb; border-radius: 8px; padding: 24px; text-align: center; cursor: pointer; transition: border-color 0.2s; }
        .id-upload:hover { border-color: #0d9488; }
        .id-upload input { display: none; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>Complete Your Profile</h1>
        <p><?php echo $user ? 'Welcome, ' . htmlspecialchars($user['first_name']) . '!' : 'PrintFlow Staff'; ?></p>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <p style="text-align:center; margin-top:16px; color:#6b7280; font-size:14px;">You can close this page. We will notify you when your account is activated.</p>
        <?php elseif ($user): ?>
        <form method="POST" enctype="multipart/form-data" id="completeForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($user['profile_completion_token'] ?? $token); ?>">

            <div class="form-group">
                <label>Contact Number *</label>
                <input type="text" name="contact_number" id="contact_number" placeholder="e.g. 09171234567" required maxlength="11" value="<?php echo htmlspecialchars($form_data['contact_number'] ?: '09'); ?>">
                <div class="error-msg" id="err_contact">Valid 11-digit number starting with 09.</div>
            </div>

            <div class="form-group">
                <label>Province *</label>
                <select name="address_province" id="address_province" required data-selected="<?php echo htmlspecialchars($form_data['address_province']); ?>">
                    <option value="">Select province</option>
                </select>
            </div>
            <div class="form-group">
                <label>City / Municipality *</label>
                <select name="address_city" id="address_city" required disabled data-selected="<?php echo htmlspecialchars($form_data['address_city']); ?>">
                    <option value="">Select city/municipality</option>
                </select>
            </div>
            <div class="form-group">
                <label>Barangay *</label>
                <select name="address_barangay" id="address_barangay" required disabled data-selected="<?php echo htmlspecialchars($form_data['address_barangay']); ?>">
                    <option value="">Select barangay</option>
                </select>
            </div>
            <div class="form-group">
                <label>Street / House No. (Optional)</label>
                <input type="text" name="address_line" id="address_line" maxlength="120" placeholder="e.g. 123 Rizal St." value="<?php echo htmlspecialchars($form_data['address_line']); ?>">
            </div>
            <input type="hidden" name="address" id="address" value="">

            <div class="form-group">
                <label>Gender</label>
                <select name="gender">
                    <option value="">-- Select --</option>
                    <option value="Male" <?php echo $form_data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $form_data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>

            <div class="id-reference">
                <h3>ID Photo Reference – Upload a clear, valid ID (not blurred)</h3>
                <img src="/printflow/uploads/id_validation.png" alt="Valid vs Invalid ID" style="max-width:100%;">
            </div>

            <div class="form-group">
                <label>Upload Valid ID *</label>
                <label class="id-upload" for="id_image">
                    <input type="file" name="id_image" id="id_image" accept="image/*" required>
                    <span id="id_label"><?php echo !empty($form_data['id_filename']) ? htmlspecialchars($form_data['id_filename']) : 'Click to select ID image (JPG, PNG, max 5MB)'; ?></span>
                </label>
                <?php if (!empty($form_data['id_filename'])): ?>
                <input type="hidden" id="previous_filename" value="<?php echo htmlspecialchars($form_data['id_filename']); ?>">
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn" style="margin-top:20px;">
                <span class="btn-text">Complete Profile</span>
                <span class="spinner"></span>
            </button>
        </form>
        <?php elseif (!$success): ?>
        <p style="text-align:center; color:#6b7280; margin-bottom:16px;">Please use the link from your email to complete your profile.</p>
        <p style="text-align:center; font-size:14px; color:#374151; margin-bottom:12px;">If your link has expired, you can log in with your email and default password (email + birthday MMDDYYYY) to complete your profile from the staff portal.</p>
        <p style="text-align:center;">
            <a href="/printflow/?auth_modal=login" style="display:inline-block; padding:12px 24px; background:#0d9488; color:#fff; text-decoration:none; border-radius:8px; font-weight:600;">Go to Login</a>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($user): ?>
<script>
(function() {
    const addrApi = '/printflow/public/api_address_public.php';
    const prov = document.getElementById('address_province');
    const city = document.getElementById('address_city');
    const brgy = document.getElementById('address_barangay');
    const line = document.getElementById('address_line');
    const addrHidden = document.getElementById('address');
    let provincesData = [];

    function buildAddress() {
        const p = [line.value.trim(), brgy.value ? 'Brgy. ' + brgy.value : '', city.value, prov.value].filter(Boolean);
        addrHidden.value = p.length ? p.join(', ') + ', Philippines' : '';
    }

    async function loadProvinces() {
        const r = await fetch(addrApi + '?address_action=provinces');
        const d = await r.json();
        if (d.success && d.data) {
            provincesData = d.data;
            prov.innerHTML = '<option value="">Select province</option>' + d.data.map(x => '<option value="' + x.name + '" data-code="' + x.code + '">' + x.name + '</option>').join('');
            
            // Restore selected province
            const selectedProv = prov.getAttribute('data-selected');
            if (selectedProv) {
                prov.value = selectedProv;
                const opt = prov.options[prov.selectedIndex];
                const code = opt && opt.value ? opt.getAttribute('data-code') : '';
                if (code) await loadCities(code);
            }
        }
    }
    async function loadCities(provinceCode) {
        if (!provinceCode) { city.innerHTML = '<option value="">Select city/municipality</option>'; city.disabled = true; brgy.innerHTML = '<option value="">Select barangay</option>'; brgy.disabled = true; buildAddress(); return; }
        const r = await fetch(addrApi + '?address_action=cities&province_code=' + encodeURIComponent(provinceCode));
        const d = await r.json();
        if (d.success && d.data) {
            city.innerHTML = '<option value="">Select city/municipality</option>' + d.data.map(x => '<option value="' + x.name + '" data-code="' + x.code + '">' + x.name + '</option>').join('');
            city.disabled = false;
            brgy.innerHTML = '<option value="">Select barangay</option>';
            brgy.disabled = true;
            
            // Restore selected city
            const selectedCity = city.getAttribute('data-selected');
            if (selectedCity) {
                city.value = selectedCity;
                const opt = city.options[city.selectedIndex];
                const code = opt && opt.value ? opt.getAttribute('data-code') : '';
                if (code) await loadBarangays(code);
            }
        }
        buildAddress();
    }
    async function loadBarangays(cityCode) {
        if (!cityCode) { brgy.innerHTML = '<option value="">Select barangay</option>'; brgy.disabled = true; buildAddress(); return; }
        const r = await fetch(addrApi + '?address_action=barangays&city_code=' + encodeURIComponent(cityCode));
        const d = await r.json();
        if (d.success && d.data) {
            brgy.innerHTML = '<option value="">Select barangay</option>' + d.data.map(x => '<option value="' + x.name + '">' + x.name + '</option>').join('');
            brgy.disabled = false;
            
            // Restore selected barangay
            const selectedBrgy = brgy.getAttribute('data-selected');
            if (selectedBrgy) {
                brgy.value = selectedBrgy;
            }
        }
        buildAddress();
    }

    loadProvinces();

    prov.addEventListener('change', function() {
        const opt = prov.options[prov.selectedIndex];
        const code = opt && opt.value ? opt.getAttribute('data-code') : '';
        loadCities(code);
    });
    city.addEventListener('change', function() {
        const opt = city.options[city.selectedIndex];
        const code = opt && opt.value ? opt.getAttribute('data-code') : '';
        loadBarangays(code);
    });
    brgy.addEventListener('change', buildAddress);
    line.addEventListener('input', buildAddress);

    const contactInput = document.getElementById('contact_number');
    contactInput.addEventListener('input', function() {
        let v = this.value.replace(/\D/g, '');
        if (v.length > 0 && !v.startsWith('09')) v = '09' + v.replace(/^0+/, '');
        this.value = v.slice(0, 11);
    });

    document.getElementById('id_image').addEventListener('change', function() {
        document.getElementById('id_label').textContent = this.files[0] ? this.files[0].name : 'Click to select ID image (JPG, PNG, max 5MB)';
    });

    // Show previously selected filename if exists
    const prevFilename = document.getElementById('previous_filename');
    if (prevFilename && prevFilename.value) {
        document.getElementById('id_label').textContent = prevFilename.value;
        // Make the file input not required if there was a previous upload
        document.getElementById('id_image').removeAttribute('required');
    }

    document.getElementById('completeForm').addEventListener('submit', function(e) {
        let ok = true;
        const c = contactInput.value.trim();
        if (!/^09\d{9}$/.test(c)) {
            document.getElementById('err_contact').parentElement.classList.add('is-invalid');
            ok = false;
        } else {
            document.getElementById('err_contact').parentElement.classList.remove('is-invalid');
        }
        buildAddress();
        if (!ok) {
            e.preventDefault();
        } else {
            // Show loading state
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.classList.add('btn-loading');
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
