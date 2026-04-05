<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$config_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$config_id) die('Invalid request');

// Get configuration
$config = db_query("SELECT sfc.*, p.name as service_name, p.base_price FROM service_form_configs sfc JOIN products p ON sfc.product_id = p.product_id WHERE sfc.config_id = ?", 'i', [$config_id]);
if (empty($config)) die('Configuration not found');
$config = $config[0];

// Get steps and fields
$steps = db_query("SELECT * FROM service_form_steps WHERE config_id = ? ORDER BY step_number", 'i', [$config_id]);
$fields = db_query("SELECT * FROM service_form_fields WHERE config_id = ? ORDER BY step_number, display_order", 'i', [$config_id]);

$page_title = 'Form Preview: ' . $config['service_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="../public/assets/css/output.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100">

<div class="container mx-auto px-4 py-8" x-data="formPreview()">
    <!-- Preview Header -->
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Preview Mode</strong> - This is how the form will appear to customers. No data will be saved.
                </p>
            </div>
        </div>
    </div>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($config['service_name']) ?></h1>
        <button onclick="window.close()" class="btn-secondary">Close Preview</button>
    </div>

    <!-- Multi-Step Form -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <!-- Progress Bar -->
        <?php if (count($steps) > 1): ?>
        <div class="mb-8">
            <div class="flex justify-between mb-2">
                <?php foreach ($steps as $index => $step): ?>
                <div class="flex-1 text-center">
                    <div class="relative">
                        <div class="flex items-center justify-center">
                            <div :class="currentStep >= <?= $index + 1 ?> ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600'" 
                                 class="w-10 h-10 rounded-full flex items-center justify-center font-semibold">
                                <?= $index + 1 ?>
                            </div>
                        </div>
                        <div class="text-xs mt-2" :class="currentStep === <?= $index + 1 ?> ? 'text-blue-600 font-semibold' : 'text-gray-500'">
                            <?= htmlspecialchars($step['step_title']) ?>
                        </div>
                    </div>
                </div>
                <?php if ($index < count($steps) - 1): ?>
                <div class="flex-1 flex items-center">
                    <div :class="currentStep > <?= $index + 1 ?> ? 'bg-blue-600' : 'bg-gray-300'" 
                         class="h-1 w-full"></div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form Steps -->
        <?php foreach ($steps as $index => $step): ?>
        <div x-show="currentStep === <?= $index + 1 ?>" x-transition>
            <h2 class="text-2xl font-semibold mb-2"><?= htmlspecialchars($step['step_title']) ?></h2>
            <?php if ($step['step_description']): ?>
            <p class="text-gray-600 mb-6"><?= htmlspecialchars($step['step_description']) ?></p>
            <?php endif; ?>

            <div class="space-y-6">
                <?php 
                $stepFields = array_filter($fields, function($f) use ($step) { 
                    return $f['step_number'] == $step['step_number']; 
                });
                foreach ($stepFields as $field): 
                    $options = $field['options_json'] ? json_decode($field['options_json'], true) : [];
                ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?= htmlspecialchars($field['field_label']) ?>
                        <?php if ($field['is_required']): ?>
                        <span class="text-red-500">*</span>
                        <?php endif; ?>
                    </label>

                    <?php if ($field['field_type'] === 'text'): ?>
                        <input type="text" class="form-input w-full" placeholder="Enter <?= htmlspecialchars($field['field_label']) ?>" <?= $field['is_required'] ? 'required' : '' ?>>
                    
                    <?php elseif ($field['field_type'] === 'number'): ?>
                        <input type="number" class="form-input w-full" placeholder="Enter <?= htmlspecialchars($field['field_label']) ?>" <?= $field['is_required'] ? 'required' : '' ?>>
                    
                    <?php elseif ($field['field_type'] === 'textarea'): ?>
                        <textarea class="form-input w-full" rows="4" placeholder="Enter <?= htmlspecialchars($field['field_label']) ?>" <?= $field['is_required'] ? 'required' : '' ?>></textarea>
                    
                    <?php elseif ($field['field_type'] === 'select'): ?>
                        <select class="form-input w-full" <?= $field['is_required'] ? 'required' : '' ?>>
                            <option value="">Select <?= htmlspecialchars($field['field_label']) ?></option>
                            <?php foreach ($options as $option): ?>
                            <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    
                    <?php elseif ($field['field_type'] === 'radio'): ?>
                        <div class="space-y-2">
                            <?php foreach ($options as $option): ?>
                            <label class="flex items-center">
                                <input type="radio" name="<?= htmlspecialchars($field['field_name']) ?>" value="<?= htmlspecialchars($option) ?>" class="mr-2" <?= $field['is_required'] ? 'required' : '' ?>>
                                <span><?= htmlspecialchars($option) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    
                    <?php elseif ($field['field_type'] === 'checkbox'): ?>
                        <div class="space-y-2">
                            <?php foreach ($options as $option): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="<?= htmlspecialchars($field['field_name']) ?>[]" value="<?= htmlspecialchars($option) ?>" class="mr-2">
                                <span><?= htmlspecialchars($option) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    
                    <?php elseif ($field['field_type'] === 'file'): ?>
                        <input type="file" class="form-input w-full" <?= $field['is_required'] ? 'required' : '' ?>>
                    
                    <?php endif; ?>

                    <?php if ($field['help_text']): ?>
                    <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($field['help_text']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Navigation Buttons -->
            <div class="flex justify-between mt-8">
                <button type="button" @click="prevStep" x-show="currentStep > 1" class="btn-secondary">
                    ← Previous
                </button>
                <div x-show="currentStep === 1"></div>
                
                <button type="button" @click="nextStep" x-show="currentStep < <?= count($steps) ?>" class="btn-primary">
                    Next →
                </button>
                <button type="button" x-show="currentStep === <?= count($steps) ?>" class="btn-primary" onclick="alert('Preview mode - form submission disabled')">
                    Submit Order
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($steps)): ?>
        <div class="text-center py-12 text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="mt-4">No steps configured yet. Add steps and fields in the form builder.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Configuration Info -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-semibold text-blue-900 mb-2">Configuration Details</h3>
        <ul class="text-sm text-blue-800 space-y-1">
            <li>• Status: <strong><?= $config['is_active'] ? 'Active' : 'Inactive' ?></strong></li>
            <li>• Custom Design Upload: <strong><?= $config['allow_custom_design'] ? 'Enabled' : 'Disabled' ?></strong></li>
            <li>• Total Steps: <strong><?= count($steps) ?></strong></li>
            <li>• Total Fields: <strong><?= count($fields) ?></strong></li>
            <li>• Base Price: <strong>₱<?= number_format($config['base_price'], 2) ?></strong></li>
        </ul>
    </div>
</div>

<script>
function formPreview() {
    return {
        currentStep: 1,
        totalSteps: <?= count($steps) ?>,
        
        nextStep() {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
            }
        },
        
        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
            }
        }
    }
}
</script>

</body>
</html>
