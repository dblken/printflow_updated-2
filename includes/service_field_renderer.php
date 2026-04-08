<?php
/**
 * Dynamic Service Field Renderer
 * Renders form fields based on admin configuration
 */

require_once __DIR__ . '/service_field_config_helper.php';

/**
 * Render a single field based on configuration
 */
function render_service_field($field_key, $config, $branches = [], $existing_data = []) {
    if (!$config['visible']) {
        return '';
    }
    
    // Extract saved values from existing_data
    $saved_value = '';
    $saved_customization = $existing_data['customization'] ?? [];
    
    // Get the label to match against customization keys
    $field_label = $config['label'];
    
    // Try to find saved value
    if ($field_key === 'branch') {
        $saved_value = $existing_data['branch_id'] ?? '';
    } elseif ($field_key === 'quantity') {
        $saved_value = $existing_data['quantity'] ?? 1;
    } elseif ($field_key === 'needed_date') {
        $saved_value = $saved_customization['needed_date'] ?? '';
    } elseif ($field_key === 'notes') {
        $saved_value = $saved_customization['notes'] ?? '';
    } else {
        // For custom fields, check customization array by label
        $saved_value = $saved_customization[$field_label] ?? '';
    }
    
    $label = htmlspecialchars($config['label']);
    $required = $config['required'] ? ' *' : '';
    $required_attr = $config['required'] ? 'required' : '';
    
    // Add unit to label for dimension fields
    if ($config['type'] === 'dimension' && !empty($config['unit'])) {
        $label .= ' (' . htmlspecialchars($config['unit']) . ')';
    }
    
    $parent_field = $config['parent_field_key'] ?? '';
    $parent_value = $config['parent_value'] ?? '';
    
    $row_attrs = ' data-field-key="' . htmlspecialchars($field_key) . '"';
    if ($parent_field && $parent_value) {
        $row_attrs .= ' data-parent-field="' . htmlspecialchars($parent_field) . '"';
        $row_attrs .= ' data-parent-value="' . htmlspecialchars($parent_value) . '"';
        // Initial state: hidden if it has a parent (will be shown by JS if condition met)
        $row_attrs .= ' style="display: none; opacity: 0; transform: translateY(-10px); transition: all 0.3s ease;"';
    } else {
        $row_attrs .= ' style="transition: all 0.3s ease;"';
    }
    
    $html = '<div class="shopee-form-row" id="card-' . htmlspecialchars($field_key) . '"' . $row_attrs . '>';
    $html .= '<div class="shopee-form-label">' . $label . $required . '</div>';
    $html .= '<div class="shopee-form-field">';
    
    // Pre-scan for all values that appear inside nested fields to avoid duplication at the top level
    $nestedValuesSet = [];
    if (!empty($config['options']) && is_array($config['options'])) {
        foreach ($config['options'] as $option) {
            if (is_array($option) && !empty($option['nested_fields'])) {
                foreach ($option['nested_fields'] as $nestedField) {
                    if (!empty($nestedField['options']) && is_array($nestedField['options'])) {
                        foreach ($nestedField['options'] as $nOpt) {
                            $nOptVal = is_array($nOpt) ? ($nOpt['value'] ?? '') : $nOpt;
                            if ($nOptVal !== '') {
                                $nValStr = (string)$nOptVal;
                                $nestedValuesSet[strtolower(trim($nValStr))] = true;
                                
                                // Dimension-specific: hide parts (e.g. '2' from '2x2')
                                if (($nestedField['type'] ?? '') === 'dimension') {
                                    $normalized = str_replace(['x', 'X', '*', '-', '×'], '|', $nValStr);
                                    $parts = explode('|', $normalized);
                                    foreach ($parts as $p) {
                                        $pTrim = trim($p);
                                        if ($pTrim !== '') $nestedValuesSet[strtolower($pTrim)] = true;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    switch ($config['type']) {
        case 'select':
            if ($field_key === 'branch') {
                $selected_branch = $existing_data['branch_id'] ?? '';
                $html .= '<select name="branch_id" id="branch_id" class="shopee-opt-btn" ' . $required_attr . ' style="width: 175px; cursor: pointer;">';
                $html .= '<option value="">Select Branch</option>';
                foreach ($branches as $b) {
                    $selected = ($selected_branch == $b['id']) ? ' selected' : '';
                    $html .= '<option value="' . (int)$b['id'] . '"' . $selected . '>' . htmlspecialchars($b['branch_name']) . '</option>';
                }
                $html .= '</select>';
            } else {
                $html .= '<select name="' . htmlspecialchars($field_key) . '" class="shopee-opt-btn" ' . $required_attr . ' style="width: 175px; cursor: pointer;">';
                $html .= '<option value="">Select ' . $label . '</option>';
                foreach ($config['options'] ?? [] as $option) {
                    $optionValue = is_array($option) ? ($option['value'] ?? '') : $option;
                    if ($optionValue === '') continue;
                    
                    // Skip if this option is already defined in a nested field
                    if (isset($nestedValuesSet[strtolower(trim($optionValue))])) continue;
                    
                    $value = htmlspecialchars($optionValue);
                    $html .= '<option value="' . $value . '">' . $value . '</option>';
                }
                $html .= '</select>';
            }
            break;
            
        case 'radio':
            $html .= '<div class="shopee-opt-group">';
            foreach ($config['options'] ?? [] as $idx => $option) {
                $optionValue = is_array($option) ? ($option['value'] ?? '') : $option;
                $nestedFields = is_array($option) ? ($option['nested_fields'] ?? []) : [];
                
                if ($optionValue === '') continue;
                if (empty($nestedFields) && isset($nestedValuesSet[strtolower(trim($optionValue))])) continue;
                
                $value = htmlspecialchars($optionValue);
                $is_checked = ($saved_value == $optionValue) ? ' checked' : '';
                $html .= '<label class="shopee-opt-btn' . ($is_checked ? ' active' : '') . '">';
                $html .= '<input type="radio" name="' . htmlspecialchars($field_key) . '" value="' . $value . '"' . $is_checked . ' style="display:none;" ' . $required_attr . ' onchange="updateOptVisual(this); handleNestedFields(this, \'' . htmlspecialchars($field_key) . '\', ' . $idx . ')">';
                $html .= '<span>' . $value . '</span>';
                $html .= '</label>';
            }
            $html .= '</div>';
            
            // Render nested fields containers (initially hidden)
            foreach ($config['options'] ?? [] as $idx => $option) {
                $nestedFields = is_array($option) ? ($option['nested_fields'] ?? []) : [];
                if (!empty($nestedFields)) {
                    $html .= '<div id="nested-' . htmlspecialchars($field_key) . '-' . $idx . '" class="nested-fields-container" style="display:none; margin-top:16px; padding:16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">';
                    
                    foreach ($nestedFields as $nIdx => $nestedField) {
                        $nestedKey = $field_key . '_nested_' . $idx . '_' . $nIdx;
                        $nestedLabel = htmlspecialchars($nestedField['label'] ?? '');
                        $nestedType = $nestedField['type'] ?? 'text';
                        $nestedRequired = ($nestedField['required'] ?? false) ? 'required' : '';
                        $nestedRequiredMark = ($nestedField['required'] ?? false) ? ' *' : '';
                        
                        $html .= '<div class="shopee-form-row" style="margin-bottom:12px;">';
                        
                        // Check if nested label is redundant (same as parent option value)
                        $cleanNestedLabel = trim(str_replace('*', '', $nestedField['label'] ?? ''));
                        $isRedundant = (strtolower($cleanNestedLabel) === strtolower(trim($optionValue)));
                        
                        if ($isRedundant) {
                            $html .= '<div class="shopee-form-label" style="min-width:100px;font-size:13px;"></div>';
                        } else {
                            $html .= '<div class="shopee-form-label" style="min-width:100px;font-size:13px;">' . $nestedLabel . $nestedRequiredMark . '</div>';
                        }
                        
                        $html .= '<div class="shopee-form-field">';
                        
                        switch ($nestedType) {
                            case 'select':
                                $html .= '<select name="' . htmlspecialchars($nestedKey) . '" class="shopee-opt-btn" ' . $nestedRequired . ' style="width:175px;cursor:pointer;">';
                                $html .= '<option value="">Select ' . $nestedLabel . '</option>';
                                foreach ($nestedField['options'] ?? [] as $nOpt) {
                                    $nOptVal = is_array($nOpt) ? ($nOpt['value'] ?? '') : $nOpt;
                                    $nVal = htmlspecialchars($nOptVal);
                                    $html .= '<option value="' . $nVal . '">' . $nVal . '</option>';
                                }
                                $html .= '</select>';
                                break;
                                
                            case 'radio':
                                $html .= '<div class="shopee-opt-group">';
                                foreach ($nestedField['options'] ?? [] as $nOpt) {
                                    $nOptVal = is_array($nOpt) ? ($nOpt['value'] ?? '') : $nOpt;
                                    $nVal = htmlspecialchars($nOptVal);
                                    $html .= '<label class="shopee-opt-btn">';
                                    $html .= '<input type="radio" name="' . htmlspecialchars($nestedKey) . '" value="' . $nVal . '" style="display:none;" ' . $nestedRequired . ' onchange="updateOptVisual(this)">';
                                    $html .= '<span>' . $nVal . '</span>';
                                    $html .= '</label>';
                                }
                                $html .= '</div>';
                                break;
                                
                            case 'dimension':
                                $nUnit = $nestedField['unit'] ?? 'ft';
                                $nAllowOthers = $nestedField['allow_others'] ?? true;
                                
                                $html .= '<div class="shopee-opt-group mb-3">';
                                foreach ($nestedField['options'] ?? [] as $nOpt) {
                                    $parts = explode('×', $nOpt);
                                    if (count($parts) === 2) {
                                        $w = trim($parts[0]);
                                        $h = trim($parts[1]);
                                        $html .= '<button type="button" class="shopee-opt-btn" onclick="selectNestedDimension(\'' . $nestedKey . '\', ' . $w . ', ' . $h . ', event)">' . $w . '×' . $h . '</button>';
                                    }
                                }
                                if ($nAllowOthers) {
                                    $html .= '<button type="button" class="shopee-opt-btn" onclick="selectNestedDimensionOthers(\'' . $nestedKey . '\', event)">Others</button>';
                                }
                                $html .= '</div>';
                                
                                if ($nAllowOthers) {
                                    $html .= '<div id="nested-dim-others-' . $nestedKey . '" style="display:none;margin-top:12px;">';
                                    $html .= '<div style="display:flex;gap:12px;max-width:300px;">';
                                    $html .= '<input type="text" id="nested-w-' . $nestedKey . '" placeholder="Width" class="input-field" style="text-align:center;" oninput="syncNestedDimension(\'' . $nestedKey . '\')"> ';
                                    $html .= '<span style="padding-top:8px;">×</span>';
                                    $html .= '<input type="text" id="nested-h-' . $nestedKey . '" placeholder="Height" class="input-field" style="text-align:center;" oninput="syncNestedDimension(\'' . $nestedKey . '\')"> ';
                                    $html .= '</div></div>';
                                }
                                
                                $html .= '<input type="hidden" name="' . htmlspecialchars($nestedKey) . '" id="nested-hidden-' . $nestedKey . '" ' . $nestedRequired . '>';
                                break;
                                
                            case 'file':
                                $html .= '<input type="file" name="' . htmlspecialchars($nestedKey) . '" class="input-field" ' . $nestedRequired . ' style="max-width:400px;">';
                                break;
                                
                            case 'textarea':
                                $html .= '<textarea name="' . htmlspecialchars($nestedKey) . '" rows="3" class="shopee-opt-btn" ' . $nestedRequired . ' style="max-width:400px;resize:none;"></textarea>';
                                break;
                                
                            case 'date':
                                $html .= '<input type="date" name="' . htmlspecialchars($nestedKey) . '" class="input-field" ' . $nestedRequired . ' min="' . date('Y-m-d') . '" style="max-width:200px;">';
                                break;
                                
                            case 'number':
                                $html .= '<input type="number" name="' . htmlspecialchars($nestedKey) . '" class="input-field" ' . $nestedRequired . ' style="max-width:200px;">';
                                break;
                                
                            default:
                                $html .= '<input type="text" name="' . htmlspecialchars($nestedKey) . '" class="input-field" ' . $nestedRequired . ' style="max-width:400px;">';
                        }
                        
                        $html .= '</div></div>';
                    }
                    
                    $html .= '</div>';
                }
            }
            break;
            
        case 'dimension':
            $unit = $config['unit'] ?? 'ft';
            $allowOthers = $config['allow_others'] ?? true;
            
            // Parse saved dimension (e.g., "2×3 ft" or "2x3")
            $saved_width = '';
            $saved_height = '';
            $is_custom_dimension = false;
            
            if ($saved_value) {
                // Remove unit and normalize separators
                $dim_value = preg_replace('/\s*(ft|in|cm|m)\s*$/i', '', $saved_value);
                $dim_value = str_replace(['×', 'X', '*', '-'], 'x', $dim_value);
                $parts = explode('x', $dim_value);
                if (count($parts) === 2) {
                    $saved_width = trim($parts[0]);
                    $saved_height = trim($parts[1]);
                    
                    // Check if it's a preset or custom
                    $is_preset = false;
                    foreach ($config['options'] ?? [] as $option) {
                        $opt_normalized = str_replace(['×', 'X', '*', '-'], 'x', trim($option));
                        if (strtolower($opt_normalized) === strtolower($saved_width . 'x' . $saved_height)) {
                            $is_preset = true;
                            break;
                        }
                    }
                    $is_custom_dimension = !$is_preset;
                }
            }
            
            $html .= '<div class="shopee-opt-group mb-3">';
            
            if (!empty($config['options']) && is_array($config['options'])) {
                foreach ($config['options'] as $option) {
                    $option = trim($option);
                    $normalized = str_replace(['x', 'X', '*', '-', '×'], '|', $option);
                    $parts = explode('|', $normalized);
                    
                    if (count($parts) === 2) {
                        $w = trim($parts[0]);
                        $h = trim($parts[1]);
                        if ($w && $h) {
                            $displayLabel = $w . '×' . $h;
                            $is_active = (!$is_custom_dimension && $saved_width == $w && $saved_height == $h) ? ' active' : '';
                            $html .= '<button type="button" class="shopee-opt-btn' . $is_active . '" data-width="' . htmlspecialchars($w) . '" data-height="' . htmlspecialchars($h) . '" onclick="selectDimension(' . htmlspecialchars($w) . ', ' . htmlspecialchars($h) . ', event)">' . $displayLabel . '</button>';
                        }
                    }
                }
            }
            
            if ($allowOthers) {
                $others_active = $is_custom_dimension ? ' active' : '';
                $html .= '<button type="button" class="shopee-opt-btn' . $others_active . '" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>';
            }
            $html .= '</div>';
            
            if ($allowOthers) {
                $others_display = $is_custom_dimension ? 'block' : 'none';
                $html .= '<div id="dim-others-inputs" style="display: ' . $others_display . '; border-top: 1px dashed #eee; padding-top: 1rem; margin-top: 1rem;">';
                $html .= '<div style="display: flex; gap: 0.75rem; align-items: flex-start; max-width: 400px;">';
                $html .= '<div style="flex: 1;">';
                $html .= '<label class="dim-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">WIDTH</label>';
                $html .= '<input type="text" inputmode="numeric" id="custom_width" class="input-field" placeholder="' . htmlspecialchars($unit) . '" maxlength="2" pattern="[0-9]*" value="' . ($is_custom_dimension ? htmlspecialchars($saved_width) : '') . '" oninput="validateDimensionInput(this)" style="text-align: center;">';
                $html .= '</div>';
                $html .= '<div style="padding-top: 1.75rem; color: #cbd5e1; font-weight: bold; font-size: 1.25rem;">×</div>';
                $html .= '<div style="flex: 1;">';
                $html .= '<label class="dim-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">HEIGHT</label>';
                $html .= '<input type="text" inputmode="numeric" id="custom_height" class="input-field" placeholder="' . htmlspecialchars($unit) . '" maxlength="2" pattern="[0-9]*" value="' . ($is_custom_dimension ? htmlspecialchars($saved_height) : '') . '" oninput="validateDimensionInput(this)" style="text-align: center;">';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '<input type="hidden" name="width" id="width_hidden" value="' . htmlspecialchars($saved_width) . '" ' . $required_attr . '>';
            $html .= '<input type="hidden" name="height" id="height_hidden" value="' . htmlspecialchars($saved_height) . '" ' . $required_attr . '>';
            $html .= '<input type="hidden" name="unit" id="unit_hidden" value="' . htmlspecialchars($unit) . '">';
            break;
            
        case 'file':
            $html .= '<input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" ' . $required_attr . ' style="max-width: 400px;">';
            break;
            
        case 'date':
            $html .= '<div class="shopee-opt-group">';
            $html .= '<input type="date" name="' . htmlspecialchars($field_key) . '" id="' . htmlspecialchars($field_key) . '" class="shopee-opt-btn" ' . $required_attr . ' min="' . date('Y-m-d') . '" value="' . htmlspecialchars($saved_value) . '" style="cursor: pointer; width: 175px;">';
            $html .= '</div>';
            break;
            
        case 'quantity':
            $saved_qty = $existing_data['quantity'] ?? 1;
            $html .= '<div class="shopee-opt-group">';
            $html .= '<div class="quantity-container shopee-opt-btn" style="display: inline-flex; justify-content: space-between; gap: 1rem; width: 175px; cursor: default;">';
            $html .= '<button type="button" class="qty-btn-minus" style="background: none; border: none; color: #6b7280; font-size: 1.125rem; font-weight: 600; cursor: pointer; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;" onclick="decreaseQty()">&minus;</button>';
            $html .= '<input type="number" id="quantity-input" name="quantity" class="qty-input-field" style="border: none; text-align: center; width: 60px; font-size: 0.875rem; font-weight: 500; color: #374151; background: transparent; outline: none; -moz-appearance: textfield;" min="1" max="100" value="' . (int)$saved_qty . '" onwheel="return false;" oninput="validateQuantity(this)" onkeydown="return event.key === \'Backspace\' || event.key === \'Delete\' || event.key === \'ArrowLeft\' || event.key === \'ArrowRight\' || event.key === \'Tab\' || (event.key >= \'0\' && event.key <= \'9\');">';
            $html .= '<button type="button" class="qty-btn-plus" style="background: none; border: none; color: #6b7280; font-size: 1.125rem; font-weight: 600; cursor: pointer; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;" onclick="increaseQty()">+</button>';
            $html .= '</div>';
            $html .= '</div>';
            break;
            
        case 'textarea':
            $html .= '<textarea name="' . htmlspecialchars($field_key) . '" rows="4" class="shopee-opt-btn notes-textarea" placeholder="Any special instructions..." maxlength="500" ' . $required_attr . ' style="max-width: 600px; height: 100px; resize: none; align-items: flex-start; justify-content: flex-start; text-align: left; padding: 0.75rem;">' . htmlspecialchars($saved_value) . '</textarea>';
            break;
            
        case 'text':
        case 'number':
            $type = $config['type'] === 'number' ? 'number' : 'text';
            $html .= '<input type="' . $type . '" name="' . htmlspecialchars($field_key) . '" class="input-field" value="' . htmlspecialchars($saved_value) . '" ' . $required_attr . ' style="max-width: 400px;">';
            break;
    }
    
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

/**
 * Render all fields for a service
 */
function render_service_fields($service_id, $branches = [], $existing_data = []) {
    $configs = get_service_field_config($service_id);
    
    if (empty($configs)) {
        return '<p style="color:#ef4444; padding:20px; text-align:center;">No field configuration found. Please contact administrator.</p>';
    }
    
    // Separate fields into categories
    $branch_field = [];
    $custom_fields = [];
    $default_bottom_fields = [];
    
    foreach ($configs as $key => $config) {
        if ($key === 'branch') {
            $branch_field[$key] = $config;
        } elseif (in_array($key, ['needed_date', 'quantity', 'notes'])) {
            $default_bottom_fields[$key] = $config;
        } else {
            $custom_fields[$key] = $config;
        }
    }
    
    // Sort custom fields by display order
    uasort($custom_fields, function($a, $b) {
        return $a['order'] - $b['order'];
    });
    
    // Sort default bottom fields in specific order: needed_date, quantity, notes
    $bottom_order = ['needed_date' => 1, 'quantity' => 2, 'notes' => 3];
    uasort($default_bottom_fields, function($a, $b) use ($bottom_order, $default_bottom_fields) {
        $key_a = array_search($a, $default_bottom_fields);
        $key_b = array_search($b, $default_bottom_fields);
        return ($bottom_order[$key_a] ?? 999) - ($bottom_order[$key_b] ?? 999);
    });
    
    // Render in order: branch -> custom fields -> default bottom fields
    $html = '';
    
    // 1. Branch field first
    foreach ($branch_field as $key => $config) {
        $html .= render_service_field($key, $config, $branches, $existing_data);
    }
    
    // 2. Custom fields
    foreach ($custom_fields as $key => $config) {
        $html .= render_service_field($key, $config, $branches, $existing_data);
    }
    
    // 3. Default bottom fields last
    foreach ($default_bottom_fields as $key => $config) {
        $html .= render_service_field($key, $config, $branches, $existing_data);
    }
    
    return $html;
}

/**
 * Get JavaScript for dynamic field behavior
 */
function get_service_field_scripts() {
    return <<<'JSEND'
<script>
let dimensionMode = 'preset';

function updateOptVisual(input) {
    const name = input.name;
    document.querySelectorAll('input[name="' + name + '"]').forEach(r => {
        const wrap = r.closest('.shopee-opt-btn');
        if (wrap) wrap.classList.toggle('active', r.checked);
    });
}

function handleNestedFields(radio, fieldKey, optionIndex) {
    // Hide all nested field containers for this field first
    document.querySelectorAll('[id^="nested-' + fieldKey + '-"]').forEach(container => {
        container.style.display = 'none';
        // Clear nested field values when hiding
        container.querySelectorAll('input, select, textarea').forEach(input => {
            if (input.type === 'radio' || input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
            // Remove visual active states
            const wrap = input.closest('.shopee-opt-btn');
            if (wrap) wrap.classList.remove('active');
        });
    });
    
    // Only show the nested fields for the currently selected option
    if (radio.checked) {
        const nestedContainer = document.getElementById('nested-' + fieldKey + '-' + optionIndex);
        if (nestedContainer) {
            nestedContainer.style.display = 'block';
        }
    }
}

function selectNestedDimension(key, w, h, e) {
    e.preventDefault();
    const btnGroup = e.target.closest('.shopee-opt-group');
    if (btnGroup) {
        btnGroup.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    }
    e.target.classList.add('active');
    
    const hidden = document.getElementById('nested-hidden-' + key);
    if (hidden) hidden.value = w + 'x' + h;
    
    const othersDiv = document.getElementById('nested-dim-others-' + key);
    if (othersDiv) othersDiv.style.display = 'none';
}

function selectNestedDimensionOthers(key, e) {
    e.preventDefault();
    const btnGroup = e.target.closest('.shopee-opt-group');
    if (btnGroup) {
        btnGroup.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    }
    e.target.classList.add('active');
    
    const othersDiv = document.getElementById('nested-dim-others-' + key);
    if (othersDiv) othersDiv.style.display = 'block';
}

function syncNestedDimension(key) {
    const w = document.getElementById('nested-w-' + key)?.value || '';
    const h = document.getElementById('nested-h-' + key)?.value || '';
    const hidden = document.getElementById('nested-hidden-' + key);
    if (hidden && w && h) {
        hidden.value = w + 'x' + h;
    }
}

function validateDimensionInput(input) {
    input.value = input.value.replace(/[^0-9]/g, '').substring(0, 2);
    syncDimensionToHidden();
}

function updateDimensionUnit(unit) {
    const unitHidden = document.getElementById('unit_hidden');
    if (unitHidden) unitHidden.value = unit;
    
    const widthInput = document.getElementById('custom_width');
    const heightInput = document.getElementById('custom_height');
    if (widthInput) widthInput.placeholder = unit;
    if (heightInput) heightInput.placeholder = unit;
}

function syncDimensionToHidden() {
    const wh = document.getElementById('width_hidden');
    const hh = document.getElementById('height_hidden');
    if (!wh || !hh) return;
    
    if (dimensionMode === 'preset') {
        const btn = document.querySelector('.shopee-opt-btn.active[data-width]');
        if (btn && btn.dataset.width) {
            wh.value = btn.dataset.width;
            hh.value = btn.dataset.height;
        } else {
            wh.value = '';
            hh.value = '';
        }
    } else {
        wh.value = document.getElementById('custom_width')?.value || '';
        hh.value = document.getElementById('custom_height')?.value || '';
    }
}

function selectDimension(w, h, e) {
    e.preventDefault();
    dimensionMode = 'preset';
    const btnGroup = e.target.closest('.shopee-opt-group');
    if (btnGroup) {
        btnGroup.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    }
    e.target.closest('.shopee-opt-btn').classList.add('active');
    const othersInput = document.getElementById('dim-others-inputs');
    if (othersInput) othersInput.style.display = 'none';
    
    const widthInput = document.getElementById('custom_width');
    const heightInput = document.getElementById('custom_height');
    if (widthInput) widthInput.value = '';
    if (heightInput) heightInput.value = '';
    
    syncDimensionToHidden();
}

function selectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    const btnGroup = e.target.closest('.shopee-opt-group');
    if (btnGroup) {
        btnGroup.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    }
    document.getElementById('dim-others-btn')?.classList.add('active');
    const othersInput = document.getElementById('dim-others-inputs');
    if (othersInput) othersInput.style.display = 'block';
    syncDimensionToHidden();
}

function increaseQty() {
    const i = document.getElementById('quantity-input');
    if (i) i.value = Math.min(100, (parseInt(i.value) || 1) + 1);
}

function decreaseQty() {
    const i = document.getElementById('quantity-input');
    if (i && parseInt(i.value) > 1) i.value = parseInt(i.value) - 1;
}

function validateQuantity(input) {
    let val = parseInt(input.value);
    if (isNaN(val) || val < 1) {
        input.value = 1;
    } else if (val > 100) {
        input.value = 100;
    }
}

// --- Conditional Fields Logic ---

function updateConditionalFields() {
    const allRows = document.querySelectorAll('.shopee-form-row[data-parent-field]');
    
    // Create a map of current field values
    const fieldValues = {};
    
    // Get values from all potential parent fields
    // 1. Radios
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        fieldValues[radio.name] = radio.value;
    });
    
    // 2. Selects
    document.querySelectorAll('select').forEach(select => {
        fieldValues[select.name] = select.value;
    });
    
    allRows.forEach(row => {
        const parentField = row.getAttribute('data-parent-field');
        const triggerValue = row.getAttribute('data-parent-value');
        const currentValue = fieldValues[parentField];
        
        if (currentValue === triggerValue) {
            showFieldRow(row);
        } else {
            hideFieldRow(row);
        }
    });
}

function showFieldRow(row) {
    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'flex';
        // Force reflow for transition
        row.offsetHeight;
        row.style.opacity = '1';
        row.style.transform = 'translateY(0)';
    }
}

function hideFieldRow(row) {
    if (row.style.display !== 'none') {
        row.style.opacity = '0';
        row.style.transform = 'translateY(-10px)';
        
        // Wait for transition to finish before hiding
        setTimeout(() => {
            // Re-check condition before hiding (in case user toggled back quickly)
            const parentField = row.getAttribute('data-parent-field');
            const triggerValue = row.getAttribute('data-parent-value');
            
            // Get current value again
            let currentVal = '';
            const radio = document.querySelector('input[name="' + parentField + '"]:checked');
            if (radio) {
                currentVal = radio.value;
            } else {
                const select = document.querySelector('select[name="' + parentField + '"]');
                if (select) currentVal = select.value;
            }
            
            if (currentVal !== triggerValue) {
                row.style.display = 'none';
                clearFieldRowValues(row);
            }
        }, 300);
    }
}

function clearFieldRowValues(row) {
    // 1. Inputs (text, number, date)
    row.querySelectorAll('input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"])').forEach(input => {
        input.value = '';
    });
    
    // 2. Textarea
    row.querySelectorAll('textarea').forEach(textarea => {
        textarea.value = '';
    });
    
    // 3. Select
    row.querySelectorAll('select').forEach(select => {
        select.selectedIndex = 0;
    });
    
    // 4. Radios
    row.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.checked = false;
        const wrap = radio.closest('.shopee-opt-btn');
        if (wrap) wrap.classList.remove('active');
    });
    
    // 5. Files
    row.querySelectorAll('input[type="file"]').forEach(file => {
        file.value = '';
    });
    
    // 6. Dimensions (custom)
    const wh = row.querySelector('#width_hidden');
    const hh = row.querySelector('#height_hidden');
    if (wh) wh.value = '';
    if (hh) hh.value = '';
    
    row.querySelectorAll('.shopee-opt-btn').forEach(btn => btn.classList.remove('active'));
    const othersInput = row.querySelector('#dim-others-inputs');
    if (othersInput) othersInput.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    // Ensure all nested fields are hidden initially
    document.querySelectorAll('.nested-fields-container').forEach(container => {
        container.style.display = 'none';
    });
    
    // Initialize radio buttons visual state
    document.querySelectorAll('.shopee-opt-btn input[type="radio"]').forEach(radio => {
        if (radio.checked) {
            updateOptVisual(radio);
        }
        radio.addEventListener('change', function() {
            updateOptVisual(this);
            updateConditionalFields();
        });
    });
    
    // Initialize select listeners
    document.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', updateConditionalFields);
    });
    
    const widthInput = document.getElementById('custom_width');
    const heightInput = document.getElementById('custom_height');
    if (widthInput) widthInput.addEventListener('input', syncDimensionToHidden);
    if (heightInput) heightInput.addEventListener('input', syncDimensionToHidden);
    
    // Run once on load to show initial state
    updateConditionalFields();
});
</script>
JSEND;
}
