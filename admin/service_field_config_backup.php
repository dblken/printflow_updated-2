<?php
/**
 * Service Field Configuration
 * Admin interface to customize service form fields
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_field_config_helper.php';

require_role(['Admin', 'Manager']);

$service_id = (int)($_GET['service_id'] ?? $_GET['service-id'] ?? 0);
$error = '';
$success = '';

if ($service_id < 1) {
    header('Location: services_management.php');
    exit;
}

$service = db_query("SELECT * FROM services WHERE service_id = ?", 'i', [$service_id]);
if (empty($service)) {
    header('Location: services_management.php');
    exit;
}
$service = $service[0];

// Initialize config if not exists
if (!service_has_field_config($service_id)) {
    // Initialize with default fields or from customer_link if available
    $service_link = !empty($service['customer_link']) ? $service['customer_link'] : null;
    init_service_field_config($service_id, $service_link);
}

// Ensure branch field always exists
$branch_exists = db_query(
    "SELECT config_id FROM service_field_configs WHERE service_id = ? AND field_key = 'branch'",
    'i',
    [$service_id]
);

if (empty($branch_exists)) {
    // Add branch field as first field
    save_service_field_config($service_id, 'branch', [
        'label' => 'Branch',
        'type' => 'select',
        'options' => null,
        'visible' => true,
        'required' => true,
        'default' => null,
        'order' => 0
    ]);
    
    // Update order of other fields
    db_execute(
        "UPDATE service_field_configs SET display_order = display_order + 1 WHERE service_id = ? AND field_key != 'branch'",
        'i',
        [$service_id]
    );
}

// Ensure default bottom fields always exist (needed_date, quantity, notes)
$default_bottom_fields = [
    'needed_date' => ['label' => 'Needed Date', 'type' => 'date', 'required' => true],
    'quantity' => ['label' => 'Quantity', 'type' => 'quantity', 'required' => true],
    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'required' => false]
];

foreach ($default_bottom_fields as $field_key => $field_data) {
    $field_exists = db_query(
        "SELECT config_id FROM service_field_configs WHERE service_id = ? AND field_key = ?",
        'is',
        [$service_id, $field_key]
    );
    
    if (empty($field_exists)) {
        // Get max order to add at the bottom
        $max_order_result = db_query(
            "SELECT MAX(display_order) as max_order FROM service_field_configs WHERE service_id = ?",
            'i',
            [$service_id]
        );
        $max_order = ($max_order_result[0]['max_order'] ?? 0) + 1;
        
        // Add the default field
        save_service_field_config($service_id, $field_key, [
            'label' => $field_data['label'],
            'type' => $field_data['type'],
            'options' => null,
            'visible' => true,
            'required' => $field_data['required'],
            'default' => null,
            'order' => $max_order
        ]);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['save_config'])) {
        $configs = json_decode($_POST['field_configs'] ?? '[]', true);
        
        // Get existing field keys from database
        $existing_keys = [];
        $existing_fields = db_query("SELECT field_key FROM service_field_configs WHERE service_id = ?", 'i', [$service_id]);
        foreach ($existing_fields as $field) {
            $existing_keys[] = $field['field_key'];
        }
        
        // Get submitted field keys
        $submitted_keys = array_keys($configs);
        
        // Protect default fields from deletion
        $protected_keys = ['branch', 'needed_date', 'quantity', 'notes'];
        
        // Delete fields that are no longer in the submitted config (except protected fields)
        $keys_to_delete = array_diff($existing_keys, $submitted_keys);
        foreach ($keys_to_delete as $key_to_delete) {
            // Skip deletion of protected default fields
            if (in_array($key_to_delete, $protected_keys)) {
                continue;
            }
            db_execute(
                "DELETE FROM service_field_configs WHERE service_id = ? AND field_key = ?",
                'is',
                [$service_id, $key_to_delete]
            );
        }
        
        // Save or update remaining fields
        foreach ($configs as $field_key => $config) {
            save_service_field_config($service_id, $field_key, $config);
        }
        
        $success = 'Field configuration saved successfully!';
        $field_configs = get_service_field_config($service_id);
    }
}

$field_configs = get_service_field_config($service_id);

$page_title = 'Configure Input Fields - ' . $service['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        /* Match Staff Dashboard Design */
        .orders-table { width:100%; border-collapse:collapse; font-size:13px; }
        .orders-table th { padding:12px 16px; font-weight:600; color:#6b7280; text-align:left; border-bottom:1px solid #e5e7eb; background:#f9fafb; }
        .orders-table td { padding:12px 16px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
        .orders-table tbody tr { transition:background 0.1s; }
        .orders-table tbody tr:hover { background:#f9fafb; }
        .btn-action { display:inline-flex; align-items:center; justify-content:center; padding:5px 12px; min-width:60px; border:1px solid transparent; background:transparent; border-radius:6px; font-size:12px; font-weight:500; cursor:pointer; white-space:nowrap; transition:all 0.2s; }
        .btn-action.blue { color:#3b82f6; border-color:#3b82f6; } .btn-action.blue:hover { background:#3b82f6; color:#fff; }
        .btn-action.red { color:#ef4444; border-color:#ef4444; } .btn-action.red:hover { background:#ef4444; color:#fff; }
        .toolbar-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border:1px solid #e5e7eb; background:#fff; border-radius:8px; font-size:13px; font-weight:500; color:#374151; cursor:pointer; white-space:nowrap; transition:all 0.2s; }
        .toolbar-btn:hover { border-color:#9ca3af; background:#f9fafb; }
        .section-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:16px; overflow:hidden; }
        .section-header { display:flex; justify-content:space-between; align-items:center; padding:16px 20px; background:#f9fafb; border-bottom:1px solid #e5e7eb; cursor:pointer; user-select:none; }
        .section-header:hover { background:#f3f4f6; }
        .section-title { font-size:15px; font-weight:700; color:#1f2937; display:flex; align-items:center; gap:10px; }
        .section-type { display:inline-block; padding:3px 8px; background:#e0e7ff; color:#4338ca; border-radius:4px; font-size:10px; font-weight:700; text-transform:uppercase; }
        .section-body { padding:20px; display:none; }
        .section-body.active { display:block; }
        .field-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .field-group { margin-bottom:16px; }
        .field-label { display:block; font-size:11px; font-weight:700; color:#6b7280; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px; }
        .field-input { width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; background:#fff; transition:all 0.2s; }
        .field-input:focus { outline:none; border-color:#0d9488; box-shadow:0 0 0 3px rgba(13,148,136,0.1); }
        .toggle-switch { position:relative; display:inline-block; width:48px; height:26px; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider { position:absolute; cursor:pointer; inset:0; background:#cbd5e1; border-radius:26px; transition:0.3s; }
        .toggle-slider:before { position:absolute; content:""; height:20px; width:20px; left:3px; bottom:3px; background:white; border-radius:50%; transition:0.3s; box-shadow:0 2px 4px rgba(0,0,0,0.2); }
        .toggle-switch input:checked + .toggle-slider { background:#0d9488; }
        .toggle-switch input:checked + .toggle-slider:before { transform:translateX(22px); }
        .toggle-label { display:inline-flex; align-items:center; gap:10px; font-size:13px; color:#374151; font-weight:500; }
        .option-list { display:flex; flex-direction:column; gap:8px; }
        .option-item { display:flex; gap:8px; align-items:center; }
        .option-input { flex:1; padding:9px 12px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; transition:all 0.2s; }
        .option-input:focus { outline:none; border-color:#0d9488; box-shadow:0 0 0 2px rgba(13,148,136,0.1); }
        .btn-remove { padding:8px 12px; background:#fee2e2; color:#dc2626; border:none; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; transition:all 0.2s; }
        .btn-remove:hover { background:#fecaca; }
        .btn-add { padding:9px 16px; background:#f0fdfa; color:#0d9488; border:1px solid #0d9488; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; transition:all 0.2s; }
        .btn-add:hover { background:#ccfbf1; }
        .btn-save { padding:8px 16px; background:#0d9488; color:#fff; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; }
        .btn-save:hover { background:#0f766e; }
        .btn-cancel { padding:8px 16px; background:#f3f4f6; color:#374151; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; }
        .btn-cancel:hover { background:#e5e7eb; }
        .chevron { width:20px; height:20px; color:#9ca3af; transition:transform 0.2s; }
        .chevron.rotated { transform:rotate(180deg); }
        .info-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 16px; margin-bottom:20px; }
        .info-box p { margin:0; font-size:13px; color:#1e40af; line-height:1.5; }
        
        /* Modal Styles */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px; }
        .modal-overlay.active { display:flex; }
        .modal-content { background:#fff; border-radius:12px; max-width:500px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px rgba(0,0,0,0.25); }
        .modal-header { padding:18px 20px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; }
        .modal-header h3 { margin:0; font-size:18px; font-weight:700; color:#1f2937; }
        .modal-body { padding:20px; }
        .modal-footer { padding:16px 20px; border-top:1px solid #e5e7eb; display:flex; gap:12px; justify-content:flex-end; }
        .btn-modal-cancel { padding:10px 20px; background:#f3f4f6; color:#374151; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s; }
        .btn-modal-cancel:hover { background:#e5e7eb; }
        .btn-modal-save { padding:10px 20px; background:#0d9488; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s; }
        .btn-modal-save:hover { background:#0f766e; }
        .btn-close { background:none; border:none; cursor:pointer; color:#9ca3af; font-size:24px; line-height:1; transition:color 0.2s; }
        .btn-close:hover { color:#374151; }
        
        /* Card Styles - Match Staff Dashboard */
        .card { padding:20px; border-radius:16px; margin-bottom:16px; border:1px solid #f1f5f9; background:#fff; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <div class="main-content" style="padding:12px 12px 0;">
        <header>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                <button type="button" onclick="window.location.href='services_management.php'" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:none;background:transparent;cursor:pointer;padding:0;" onmouseover="this.querySelector('svg').style.color='#0d9488';" onmouseout="this.querySelector('svg').style.color='#6b7280';">
                    <svg style="width:24px;height:24px;color:#6b7280;transition:color 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                <h1 class="page-title" style="margin:0;">Configure Input Fields</h1>
            </div>
            <p style="color:#6b7280; font-size:14px; margin-top:8px;">
                Service: <strong><?php echo htmlspecialchars($service['name']); ?></strong>
            </p>
        </header>
        <main>
            <?php if ($success): ?>
                <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                    ✓ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                    ✗ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <p><strong>💡 Tip:</strong> Customize each input field below to control what customers see when ordering this service. Toggle visibility to show/hide fields, edit labels to change text, and modify options to update choices. You can also add new fields at the bottom.</p>
            </div>

            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">Input Fields</h3>
                    <button type="button" class="toolbar-btn" onclick="showAddFieldModal()" style="height:38px;border-color:#3b82f6;color:#3b82f6;">
                        + Add Field
                    </button>
                </div>
                
                <form method="POST" id="configForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="field_configs" id="fieldConfigsInput">
                    
                    <div id="sectionsContainer">
                        <?php if (empty($field_configs)): ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <svg style="width:80px;height:80px;color:#d1d5db;margin:0 auto 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 style="font-size:18px;font-weight:700;color:#374151;margin:0 0 8px;">No Fields Configured</h3>
                                <p style="color:#6b7280;margin:0;font-size:14px;">Use the "+ Add Field" button below to start building your custom service form.</p>
                            </div>
                        <?php else: ?>
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th style="width:25%;">Field Label</th>
                                        <th style="width:15%;">Type</th>
                                        <th style="width:15%;text-align:center;">Status</th>
                                        <th style="width:15%;text-align:center;">Required</th>
                                        <th style="width:15%;text-align:center;">Visibility</th>
                                        <th style="width:15%;text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                            <?php foreach ($field_configs as $key => $config): ?>
                                <tr onclick="toggleFieldRow('<?php echo htmlspecialchars($key); ?>')" style="cursor:pointer;">
                                    <td style="font-weight:500;color:#1f2937;"><?php echo htmlspecialchars($config['label']); ?></td>
                                    <td>
                                        <span style="display:inline-block;padding:3px 8px;background:#e0e7ff;color:#4338ca;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase;"><?php echo htmlspecialchars($config['type']); ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if (in_array($key, ['branch', 'needed_date', 'quantity', 'notes'])): ?>
                                            <span style="display:inline-block;padding:3px 8px;background:#dcfce7;color:#166534;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase;">DEFAULT</span>
                                        <?php else: ?>
                                            <span style="display:inline-block;padding:3px 8px;background:#f3f4f6;color:#6b7280;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase;">CUSTOM</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if (in_array($key, ['branch', 'needed_date', 'quantity'])): ?>
                                            <span style="color:#059669;font-weight:600;font-size:12px;">✓ Yes</span>
                                        <?php elseif ($key === 'notes'): ?>
                                            <span style="color:#6b7280;font-weight:500;font-size:12px;">Optional</span>
                                        <?php else: ?>
                                            <span style="color:<?php echo $config['required'] ? '#059669' : '#6b7280'; ?>;font-weight:<?php echo $config['required'] ? '600' : '500'; ?>;font-size:12px;"><?php echo $config['required'] ? '✓ Yes' : 'Optional'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="color:#059669;font-weight:600;font-size:12px;">✓ Visible</span>
                                    </td>
                                    <td style="text-align:right;white-space:nowrap;" onclick="event.stopPropagation();">
                                        <?php if (!in_array($key, ['branch', 'needed_date', 'quantity', 'notes'])): ?>
                                            <button type="button" class="btn-action blue" onclick="showEditFieldModal('<?php echo htmlspecialchars($key); ?>')">Edit</button>
                                            <button type="button" class="btn-action red" onclick="deleteField(this)" data-field-key="<?php echo htmlspecialchars($key); ?>">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr id="field-detail-<?php echo htmlspecialchars($key); ?>" style="display:none;">
                                    <td colspan="6" style="background:#f9fafb;padding:0;">
                                        <div class="section-card" data-field-key="<?php echo htmlspecialchars($key); ?>" <?php echo in_array($key, ['branch', 'needed_date', 'quantity', 'notes']) ? 'data-is-default="true"' : ''; ?> style="border:none;border-radius:0;margin:0;">
                                            <div class="section-body active" style="display:block;">
                                                <?php if (in_array($key, ['branch', 'needed_date', 'quantity', 'notes'])): ?>
                                                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                                                    <p style="margin:0;font-size:13px;color:#1e40af;line-height:1.5;"><strong>ℹ️ Default Field:</strong> This field is required for all services and cannot be deleted. <?php echo $key === 'branch' ? 'It automatically displays all active branches.' : 'It will always appear at the bottom of the customer form.'; ?></p>
                                                </div>
                                                <?php endif; ?>
                                        
                                        <div class="field-row">
                                            <div class="field-group">
                                                <label class="field-label">Section Label</label>
                                                <input type="text" class="field-input label-input" value="<?php echo htmlspecialchars($config['label']); ?>" placeholder="e.g., Size, Finish, Design" <?php echo in_array($key, ['branch', 'needed_date', 'quantity', 'notes']) ? 'readonly style="background:#f9fafb;cursor:not-allowed;"' : ''; ?>>
                                            </div>
                                            <div class="field-group">
                                                <label class="field-label">Required Field</label>
                                                <?php if (in_array($key, ['branch', 'needed_date', 'quantity'])): ?>
                                                <div style="padding:10px 0;color:#6b7280;font-size:14px;font-weight:600;">✓ Always Required</div>
                                                <?php elseif ($key === 'notes'): ?>
                                                <div style="padding:10px 0;color:#6b7280;font-size:14px;font-weight:600;">Optional</div>
                                                <?php else: ?>
                                                <label class="toggle-label">
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" class="required-toggle" <?php echo $config['required'] ? 'checked' : ''; ?>>
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                    <span>Customer must fill this</span>
                                                </label>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (in_array($config['type'], ['radio', 'select'])): ?>
                                            <div class="field-group">
                                                <label class="field-label">Options (Choices shown to customer)</label>
                                                <div class="option-list">
                                                    <?php foreach ($config['options'] ?? [] as $option): ?>
                                                        <div class="option-item">
                                                            <input type="text" class="option-input" value="<?php echo htmlspecialchars($option); ?>" placeholder="Enter option">
                                                            <button type="button" class="btn-remove" onclick="removeOption(this)">Remove</button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button type="button" class="btn-add" onclick="addOption(this)">+ Add Option</button>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($config['type'] === 'dimension'): ?>
                                            <div class="field-group">
                                                <label class="field-label">Dimension Options (Width × Height)</label>
                                                <div class="option-list dimension-options">
                                                    <?php 
                                                    $dimension_options = $config['options'] ?? [];
                                                    if (empty($dimension_options)) {
                                                        // Show one empty row if no options
                                                        echo '<div class="option-item" style="display: flex; gap: 8px; align-items: center;">';
                                                        echo '<input type="text" class="option-input dimension-w" value="" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,\'\'); checkDimensionDuplicates(this);">';
                                                        echo '<span style="color: #cbd5e1; font-weight: bold;">×</span>';
                                                        echo '<input type="text" class="option-input dimension-h" value="" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,\'\'); checkDimensionDuplicates(this);">';
                                                        echo '<button type="button" class="btn-remove" onclick="removeDimensionOption(this)">Remove</button>';
                                                        echo '</div>';
                                                    } else {
                                                        foreach ($dimension_options as $option): 
                                                            // Handle multiple separator types
                                                            $option_clean = trim($option);
                                                            $parts = preg_split('/[×xX*\-\s]+/', $option_clean, 2);
                                                            $w = isset($parts[0]) && $parts[0] !== '' ? trim($parts[0]) : '';
                                                            $h = isset($parts[1]) && $parts[1] !== '' ? trim($parts[1]) : '';
                                                    ?>
                                                        <div class="option-item" style="display: flex; gap: 8px; align-items: center;">
                                                            <input type="text" class="option-input dimension-w" value="<?php echo htmlspecialchars($w); ?>" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,''); checkDimensionDuplicates(this);">
                                                            <span style="color: #cbd5e1; font-weight: bold;">×</span>
                                                            <input type="text" class="option-input dimension-h" value="<?php echo htmlspecialchars($h); ?>" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,''); checkDimensionDuplicates(this);">
                                                            <button type="button" class="btn-remove" onclick="removeDimensionOption(this)">Remove</button>
                                                        </div>
                                                    <?php 
                                                        endforeach;
                                                    }
                                                    ?>
                                                </div>
                                                <button type="button" class="btn-add" onclick="addDimensionOption(this)">+ Add Dimension</button>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($config['type'] === 'dimension'): ?>
                                            <div class="field-group">
                                                <label class="field-label">Measurement Unit</label>
                                                <select class="field-input unit-select" onchange="updateUnitDisplay(this)">
                                                    <option value="ft" <?php echo ($config['unit'] ?? 'ft') === 'ft' ? 'selected' : ''; ?>>Feet (ft)</option>
                                                    <option value="in" <?php echo ($config['unit'] ?? 'ft') === 'in' ? 'selected' : ''; ?>>Inches (in)</option>
                                                </select>
                                            </div>
                                            <div class="field-group">
                                                <label class="toggle-label">
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" class="allow-others-toggle" <?php echo ($config['allow_others'] ?? true) ? 'checked' : ''; ?>>
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                    <span>Allow "Others" (Custom Size Input)</span>
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:24px; padding-top:24px; border-top:1px solid #e5e7eb;">
                        <button type="button" class="btn-cancel" onclick="window.location.href='services_management.php'">Cancel</button>
                        <button type="submit" name="save_config" class="btn-save">Save Configuration</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<!-- View Field Modal -->
<div id="viewFieldModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>View Field Details</h3>
            <button type="button" class="btn-close" onclick="closeViewFieldModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="field-group">
                <label class="field-label">Field Label</label>
                <input type="text" id="view-field-label" class="field-input" readonly style="background:#f9fafb;cursor:default;">
            </div>
            
            <div class="field-group">
                <label class="field-label">Field Type</label>
                <input type="text" id="view-field-type" class="field-input" readonly style="background:#f9fafb;cursor:default;">
            </div>
            
            <div id="view-field-options-section" style="display:none;">
                <div class="field-group">
                    <label class="field-label">Options</label>
                    <div id="view-field-options-list" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;">
                    </div>
                </div>
            </div>
            
            <div id="view-field-dimension-section" style="display:none;">
                <div class="field-group">
                    <label class="field-label">Measurement Unit</label>
                    <input type="text" id="view-field-unit" class="field-input" readonly style="background:#f9fafb;cursor:default;">
                </div>
                <div class="field-group">
                    <label class="field-label">Allow Custom Size</label>
                    <input type="text" id="view-field-allow-others" class="field-input" readonly style="background:#f9fafb;cursor:default;">
                </div>
                <div class="field-group">
                    <label class="field-label">Dimension Options</label>
                    <div id="view-field-dimension-list" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;">
                    </div>
                </div>
            </div>
            
            <div class="field-group">
                <label class="field-label">Required Field</label>
                <input type="text" id="view-field-required" class="field-input" readonly style="background:#f9fafb;cursor:default;">
            </div>
            
            <div class="field-group">
                <label class="field-label">Status</label>
                <input type="text" id="view-field-status" class="field-input" readonly style="background:#f9fafb;cursor:default;">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeViewFieldModal()">Close</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteFieldModal" class="modal-overlay">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button type="button" class="btn-close" onclick="closeDeleteFieldModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin:0;color:#374151;font-size:14px;line-height:1.6;">Are you sure you want to delete this field? This action cannot be undone.</p>
            <input type="hidden" id="delete-field-key">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeDeleteFieldModal()">Cancel</button>
            <button type="button" class="btn-modal-save" onclick="confirmDeleteField()" style="background:#ef4444;">Delete</button>
        </div>
    </div>
</div>

<!-- Edit Field Modal -->
<div id="editFieldModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Field</h3>
            <button type="button" class="btn-close" onclick="closeEditFieldModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-field-key">
            
            <div class="field-group">
                <label class="field-label">Field Label *</label>
                <input type="text" id="edit-field-label" class="field-input" placeholder="e.g., Special Instructions">
            </div>
            
            <div class="field-group">
                <label class="field-label">Field Type *</label>
                <input type="text" id="edit-field-type-display" class="field-input" readonly style="background:#f9fafb;cursor:not-allowed;">
                <input type="hidden" id="edit-field-type">
            </div>
            
            <div id="edit-field-options-section" style="display:none;">
                <div class="field-group">
                    <label class="field-label">Options (Choices shown to customer)</label>
                    <div id="edit-field-options-list" class="option-list"></div>
                    <button type="button" class="btn-add" onclick="addEditFieldOption()" style="margin-top:12px;">+ Add Option</button>
                </div>
            </div>
            
            <div id="edit-field-dimension-section" style="display:none;">
                <div class="field-group">
                    <label class="field-label">Measurement Unit</label>
                    <select id="edit-field-unit" class="field-input">
                        <option value="ft">Feet (ft)</option>
                        <option value="in">Inches (in)</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="toggle-label">
                        <label class="toggle-switch">
                            <input type="checkbox" id="edit-field-allow-others">
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Allow "Others" (Custom Size Input)</span>
                    </label>
                </div>
                <div class="field-group">
                    <label class="field-label">Dimension Options (Width × Height)</label>
                    <div id="edit-field-dimension-list" class="option-list"></div>
                    <button type="button" class="btn-add" onclick="addEditDimensionOption()" style="margin-top:12px;">+ Add Dimension</button>
                </div>
            </div>
            
            <div class="field-group">
                <label class="toggle-label">
                    <label class="toggle-switch">
                        <input type="checkbox" id="edit-field-required">
                        <span class="toggle-slider"></span>
                    </label>
                    <span>Required Field</span>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeEditFieldModal()">Cancel</button>
            <button type="button" class="btn-modal-save" onclick="saveEditField()">Save Changes</button>
        </div>
    </div>
</div>

<!-- Add Field Modal -->
<div id="addFieldModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Field</h3>
            <button type="button" class="btn-close" onclick="closeAddFieldModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="field-group">
                <label class="field-label">Field Label *</label>
                <input type="text" id="new-field-label" class="field-input" placeholder="e.g., Special Instructions">
            </div>
            
            <div class="field-group">
                <label class="field-label">Field Type *</label>
                <select id="new-field-type" class="field-input" onchange="toggleNewFieldOptions()">
                    <option value="">-- Select Type --</option>
                    <option value="select">Select (Dropdown)</option>
                    <option value="dimension">Dimension (Size)</option>
                    <option value="radio">Radio Buttons</option>
                    <option value="file">File Upload</option>
                    <option value="textarea">Textarea (Multi-line)</option>
                </select>
            </div>
            
            <div id="new-field-options-section" style="display:none;">
                <div class="field-group">
                    <label class="field-label">Options (Choices shown to customer)</label>
                    <div id="new-field-options-list" class="option-list">
                        <div class="option-item">
                            <input type="text" class="option-input" placeholder="e.g., Small, Red, Matte">
                            <button type="button" class="btn-remove" onclick="removeNewFieldOption(this)">Remove</button>
                        </div>
                        <div class="option-item">
                            <input type="text" class="option-input" placeholder="e.g., Medium, Blue, Glossy">
                            <button type="button" class="btn-remove" onclick="removeNewFieldOption(this)">Remove</button>
                        </div>
                        <div class="option-item">
                            <input type="text" class="option-input" placeholder="e.g., Large, Green, Vinyl">
                            <button type="button" class="btn-remove" onclick="removeNewFieldOption(this)">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add" onclick="addNewFieldOption()" style="margin-top:12px;">+ Add Option</button>
                </div>
            </div>
            
            <div id="new-field-dimension-section" style="display:none;">
                <div class="field-group">
                    <label class="field-label">Measurement Unit</label>
                    <select id="new-field-unit" class="field-input">
                        <option value="ft">Feet (ft)</option>
                        <option value="in">Inches (in)</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="toggle-label">
                        <label class="toggle-switch">
                            <input type="checkbox" id="new-field-allow-others" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Allow "Others" (Custom Size Input)</span>
                    </label>
                </div>
                <div class="field-group">
                    <label class="field-label">Dimension Options (Width × Height)</label>
                    <div id="new-field-dimension-list" class="option-list">
                        <div class="option-item" style="display: flex; gap: 8px; align-items: center;">
                            <input type="text" class="dimension-width" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            <span style="color: #cbd5e1; font-weight: bold;">×</span>
                            <input type="text" class="dimension-height" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            <button type="button" class="btn-remove" onclick="removeNewDimensionOption(this)">Remove</button>
                        </div>
                        <div class="option-item" style="display: flex; gap: 8px; align-items: center;">
                            <input type="text" class="dimension-width" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            <span style="color: #cbd5e1; font-weight: bold;">×</span>
                            <input type="text" class="dimension-height" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            <button type="button" class="btn-remove" onclick="removeNewDimensionOption(this)">Remove</button>
                        </div>
                        <div class="option-item" style="display: flex; gap: 8px; align-items: center;">
                            <input type="text" class="dimension-width" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            <span style="color: #cbd5e1; font-weight: bold;">×</span>
                            <input type="text" class="dimension-height" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            <button type="button" class="btn-remove" onclick="removeNewDimensionOption(this)">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add" onclick="addNewDimensionOption()" style="margin-top:12px;">+ Add Dimension</button>
                </div>
            </div>
            
            <div class="field-group">
                <label class="toggle-label">
                    <label class="toggle-switch">
                        <input type="checkbox" id="new-field-required" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span>Required Field</span>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeAddFieldModal()">Cancel</button>
            <button type="button" class="btn-modal-save" onclick="addNewField()">Add Field</button>
        </div>
    </div>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

<script>
window.fieldConfigurations = <?php echo json_encode($field_configs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || {};

window.showAddFieldModal = function() {
    document.getElementById('addFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
};

window.closeAddFieldModal = function() {
    document.getElementById('addFieldModal').classList.remove('active');
    document.body.style.overflow = '';
};

window.showEditFieldModal = function(key) {
    document.getElementById('editFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
};

window.closeEditFieldModal = function() {
    document.getElementById('editFieldModal').classList.remove('active');
    document.body.style.overflow = '';
};

window.deleteField = function(btn) {
    const key = btn.getAttribute('data-field-key');
    document.getElementById('delete-field-key').value = key;
    document.getElementById('deleteFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
};

window.closeDeleteFieldModal = function() {
    document.getElementById('deleteFieldModal').classList.remove('active');
    document.body.style.overflow = '';
};

window.confirmDeleteField = function() {
    const key = document.getElementById('delete-field-key').value;
    const row = document.querySelector('tr[data-key="' + key + '"]');
    if (row) row.remove();
    delete window.fieldConfigurations[key];
    closeDeleteFieldModal();
};

function toggleFieldRow(elem) {
    const key = elem.getAttribute('data-key');
    if (key) alert('Field: ' + key);
}

function toggleNewFieldOptions() {
    const type = document.getElementById('new-field-type')?.value;
    const optionsSection = document.getElementById('new-field-options-section');
    const dimensionSection = document.getElementById('new-field-dimension-section');
    if (optionsSection) optionsSection.style.display = (type === 'select' || type === 'radio') ? 'block' : 'none';
    if (dimensionSection) dimensionSection.style.display = (type === 'dimension') ? 'block' : 'none';
}

document.getElementById('configForm')?.addEventListener('submit', function(e) {
    const configs = {};
    let order = 0;
    for (const key in window.fieldConfigurations) {
        const config = window.fieldConfigurations[key];
        configs[key] = {
            label: config.label,
            type: config.type,
            options: config.options,
            visible: config.visible !== false,
            required: config.required || false,
            order: order++
        };
        if (config.type === 'dimension') {
            configs[key].unit = config.unit || 'ft';
            configs[key].allow_others = config.allow_others !== false;
        }
    }
    document.getElementById('fieldConfigsInput').value = JSON.stringify(configs);
});

console.log('All functions loaded. showAddFieldModal:', typeof window.showAddFieldModal);
</script>
