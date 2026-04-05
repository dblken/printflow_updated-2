
// Define global functions first to ensure they are available regardless of execution order
function handleEditClick(key) {
    console.log('handleEditClick called with key:', key);
    showEditFieldModal(key);
}

function handleDeleteClick(btn) {
    console.log('handleDeleteClick called');
    deleteField(btn);
}

// Store field configurations in JavaScript
const rawConfigs = <?php echo json_encode($field_configs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const fieldConfigurations = (rawConfigs && typeof rawConfigs === 'object') ? rawConfigs : {};

console.log('=== DEBUG START ===');
console.log('fieldConfigurations loaded:', Object.keys(fieldConfigurations).length, 'fields');
console.log('Wrapper functions defined');
console.log('=== DEBUG END ===');

function showViewFieldModal(keyOrElem) {
    const key = (typeof keyOrElem === 'string') ? keyOrElem : keyOrElem.dataset.key;
    const config = fieldConfigurations[key];
    if (!config) {
        alert('Field configuration not found');
        return;
    }
    
    const isDefault = ['branch', 'needed_date', 'quantity', 'notes'].includes(key);
    
    document.getElementById('view-field-label').value = config.label;
    document.getElementById('view-field-type').value = config.type.charAt(0).toUpperCase() + config.type.slice(1);
    document.getElementById('view-field-required').value = config.required ? 'Yes' : 'No';
    document.getElementById('view-field-status').value = isDefault ? 'Default Field' : 'Custom Field';
    
    // Show conditional info if exists
    const conditionalInfo = document.getElementById('view-conditional-info');
    if (config.parent_field_key && config.parent_value) {
        conditionalInfo.style.display = 'block';
        document.getElementById('view-field-parent').value = fieldConfigurations[config.parent_field_key] ? fieldConfigurations[config.parent_field_key].label : config.parent_field_key;
        document.getElementById('view-field-trigger').value = config.parent_value;
    } else {
        conditionalInfo.style.display = 'none';
    }
    
    const optionsSection = document.getElementById('view-field-options-section');
    const dimensionSection = document.getElementById('view-field-dimension-section');
    
    optionsSection.style.display = 'none';
    dimensionSection.style.display = 'none';
    
    if (config.type === 'select' || config.type === 'radio') {
        optionsSection.style.display = 'block';
        const optionsList = document.getElementById('view-field-options-list');
        const options = config.options || [];
        if (options.length > 0) {
            optionsList.innerHTML = options.map(opt => `<div style="padding:4px 0;color:#374151;font-size:13px;">â€¢ ${escapeHtml(opt)}</div>`).join('');
        } else {
            optionsList.innerHTML = '<div style="color:#9ca3af;font-size:13px;">No options configured</div>';
        }
    } else if (config.type === 'dimension') {
        dimensionSection.style.display = 'block';
        document.getElementById('view-field-unit').value = (config.unit || 'ft') === 'ft' ? 'Feet (ft)' : 'Inches (in)';
        document.getElementById('view-field-allow-others').value = (config.allow_others !== false) ? 'Yes' : 'No';
        
        const dimensionList = document.getElementById('view-field-dimension-list');
        const options = config.options || [];
        if (options.length > 0) {
            dimensionList.innerHTML = options.map(opt => `<div style="padding:4px 0;color:#374151;font-size:13px;">â€¢ ${escapeHtml(opt)}</div>`).join('');
        } else {
            dimensionList.innerHTML = '<div style="color:#9ca3af;font-size:13px;">No dimensions configured</div>';
        }
    }
    
    document.getElementById('viewFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewFieldModal() {
    document.getElementById('viewFieldModal').classList.remove('active');
    document.body.style.overflow = '';
}

function showEditFieldModal(keyOrElem) {
    const key = (typeof keyOrElem === 'string') ? keyOrElem : (keyOrElem.dataset ? keyOrElem.dataset.key : keyOrElem.getAttribute('data-key'));
    const config = fieldConfigurations[key];
    if (!config) {
        alert('Field configuration not found');
        return;
    }
    
    // Populate form
    document.getElementById('edit-field-key').value = key;
    document.getElementById('edit-field-label').value = config.label;
    document.getElementById('edit-field-type').value = config.type;
    document.getElementById('edit-field-required').checked = config.required;
    
    // Conditional Logic
    populateParentDropdown('edit-field-parent-key', config.parent_field_key || '', key);
    updateEditTriggerValues(config.parent_value || '');
    
    // Options section
    const optionsSection = document.getElementById('edit-field-options-section');
    const dimensionSection = document.getElementById('edit-field-dimension-section');
    
    if (config.type === 'select' || config.type === 'radio') {
        optionsSection.style.display = 'block';
        dimensionSection.style.display = 'none';
        
        const list = document.getElementById('edit-field-options-list');
        list.innerHTML = '';
        
        if (config.options && Array.isArray(config.options)) {
            config.options.forEach(opt => {
                const item = document.createElement('div');
                item.className = 'option-item';
                item.innerHTML = `
                    <input type="text" class="option-input" value="${escapeHtml(opt)}" placeholder="Enter option value">
                    <button type="button" class="btn-remove" onclick="removeEditFieldOption(this)">Remove</button>
                `;
                list.appendChild(item);
            });
        }
        
        if (list.children.length === 0) {
            addEditFieldOption();
        }
        
    } else if (config.type === 'dimension') {
        optionsSection.style.display = 'none';
        dimensionSection.style.display = 'block';
        
        document.getElementById('edit-field-unit').value = config.unit || 'ft';
        document.getElementById('edit-field-allow-others').checked = config.allow_others !== false;
        
        const list = document.getElementById('edit-field-dimension-list');
        list.innerHTML = '';
        
        if (config.options && Array.isArray(config.options)) {
            config.options.forEach(opt => {
                const parts = opt.split(/[\u00D7xX]/);
                const w = parts[0] ? parts[0].trim() : '';
                const h = parts[1] ? parts[1].trim() : '';
                
                const item = document.createElement('div');
                item.className = 'option-item';
                item.style.display = 'flex';
                item.style.gap = '8px';
                item.style.alignItems = 'center';
                item.innerHTML = `
                    <input type="text" class="dimension-width" value="${escapeHtml(w)}" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    <span style="color: #cbd5e1; font-weight: bold;">Ã—</span>
                    <input type="text" class="dimension-height" value="${escapeHtml(h)}" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    <button type="button" class="btn-remove" onclick="removeEditDimensionOption(this)">Remove</button>
                `;
                list.appendChild(item);
            });
        }
    } else {
        optionsSection.style.display = 'none';
        dimensionSection.style.display = 'none';
    }
    
    document.getElementById('editFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeEditFieldModal() {
    document.getElementById('editFieldModal').classList.remove('active');
    document.body.style.overflow = '';
}

function addEditFieldOption() {
    const list = document.getElementById('edit-field-options-list');
    const item = document.createElement('div');
    item.className = 'option-item';
    item.innerHTML = `
        <input type="text" class="option-input" placeholder="Enter option value">
        <button type="button" class="btn-remove" onclick="removeEditFieldOption(this)">Remove</button>
    `;
    list.appendChild(item);
}

function removeEditFieldOption(btn) {
    const list = document.getElementById('edit-field-options-list');
    if (list.querySelectorAll('.option-item').length > 1) {
        btn.closest('.option-item').remove();
    } else {
        alert('At least one option is required.');
    }
}

function addEditDimensionOption() {
    const list = document.getElementById('edit-field-dimension-list');
    const item = document.createElement('div');
    item.className = 'option-item';
    item.style.cssText = 'display: flex; gap: 8px; align-items: center;';
    item.innerHTML = `
        <input type="text" class="dimension-width" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        <span style="color: #cbd5e1; font-weight: bold;">Ã—</span>
        <input type="text" class="dimension-height" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        <button type="button" class="btn-remove" onclick="removeEditDimensionOption(this)">Remove</button>
    `;
    list.appendChild(item);
}

function removeEditDimensionOption(btn) {
    btn.closest('.option-item').remove();
}

function saveEditField() {
    const key = document.getElementById('edit-field-key').value;
    const label = document.getElementById('edit-field-label').value.trim();
    const type = document.getElementById('edit-field-type').value;
    const required = document.getElementById('edit-field-required').checked;
    
    if (!label) {
        alert('Please enter a field label');
        return;
    }
    
    // Update in-memory configuration
    fieldConfigurations[key].label = label;
    fieldConfigurations[key].required = required;
    
    // Update label in table row
    const tableRow = document.querySelector('tr[onclick*="' + key + '"]');
    if (tableRow) {
        tableRow.querySelector('td:first-child').textContent = label;
        
        // Update required display in table
        const requiredCell = tableRow.querySelectorAll('td')[3];
        if (requiredCell) {
            requiredCell.innerHTML = required 
                ? '<span style="color:#059669;font-weight:600;font-size:12px;">âœ“ Yes</span>'
                : '<span style="color:#6b7280;font-weight:500;font-size:12px;">Optional</span>';
        }
    }
    
    // Update options for select/radio
    if (type === 'select' || type === 'radio') {
        const optionInputs = document.querySelectorAll('#edit-field-options-list .option-input');
        const options = Array.from(optionInputs).map(input => input.value.trim()).filter(val => val !== '');
        
        if (options.length === 0) {
            alert('Please enter at least one option for this field type');
            return;
        }
        
        fieldConfigurations[key].options = options;
    } else if (type === 'dimension') {
        const unit = document.getElementById('edit-field-unit').value;
        const allowOthers = document.getElementById('edit-field-allow-others').checked;
        
        const dimensionItems = document.querySelectorAll('#edit-field-dimension-list .option-item');
        const dimensions = [];
        dimensionItems.forEach(item => {
            const w = item.querySelector('.dimension-width').value.trim();
            const h = item.querySelector('.dimension-height').value.trim();
            if (w && h) {
                dimensions.push(w + 'Ã—' + h);
            }
        });
        
        if (dimensions.length === 0) {
            alert('Please enter at least one dimension option');
            return;
        }
        
        fieldConfigurations[key].unit = unit;
        fieldConfigurations[key].allow_others = allowOthers;
    }
    
    // Save Conditional Logic
    const parentKey = document.getElementById('edit-field-parent-key').value;
    const parentValue = document.getElementById('edit-field-parent-value').value;
    
    if (parentKey && parentValue) {
        fieldConfigurations[key].parent_field_key = parentKey;
        fieldConfigurations[key].parent_value = parentValue;
    } else {
        fieldConfigurations[key].parent_field_key = null;
        fieldConfigurations[key].parent_value = null;
    }
    
    closeEditFieldModal();
}

function toggleFieldRow(key) {
    // Open view modal when clicking row
    showViewFieldModal(key);
}

function updateUnitDisplay(select) {
    // Not needed anymore
}

function deleteField(btn) {
    const key = btn.getAttribute('data-field-key');
    const card = document.querySelector('.section-card[data-field-key="' + key + '"]');
    if (card && card.dataset.isDefault === 'true') {
        alert('This is a default field and cannot be deleted.');
        return;
    }
    document.getElementById('delete-field-key').value = key;
    document.getElementById('deleteFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteFieldModal() {
    document.getElementById('deleteFieldModal').classList.remove('active');
    document.body.style.overflow = '';
}

function confirmDeleteField() {
    const key = document.getElementById('delete-field-key').value;
    const row = document.querySelector('tr[onclick*="' + key + '"]');
    const detailRow = document.getElementById('field-detail-' + key);
    
    // Remove from in-memory configuration
    delete fieldConfigurations[key];
    
    if (row) row.remove();
    if (detailRow) detailRow.remove();
    
    closeDeleteFieldModal();
}

function showAddFieldModal() {
    populateParentDropdown('new-field-parent-key', '', '');
    updateNewTriggerValues();
    document.getElementById('addFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAddFieldModal() {
    document.getElementById('addFieldModal').classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('new-field-label').value = '';
    document.getElementById('new-field-type').value = '';
    document.getElementById('new-field-required').checked = true;
    document.getElementById('new-field-options-section').style.display = 'none';
    document.getElementById('new-field-dimension-section').style.display = 'none';
    document.getElementById('new-field-unit').value = 'ft';
    document.getElementById('new-field-allow-others').checked = true;
    
    // Reset Conditional Logic
    const pf_ParentKeySelect = document.getElementById('new-field-parent-key');
    if (pf_ParentKeySelect) pf_ParentKeySelect.value = '';
    const pf_TriggerValueGroup = document.getElementById('new-trigger-value-group');
    if (pf_TriggerValueGroup) pf_TriggerValueGroup.style.display = 'none';
    const pf_ParentValueSelect = document.getElementById('new-field-parent-value');
    if (pf_ParentValueSelect) {
        pf_ParentValueSelect.innerHTML = '<option value="">-- Select Value --</option>';
        pf_ParentValueSelect.value = '';
    }

    
    // Reset options list to 3 empty fields
    const optionsList = document.getElementById('new-field-options-list');
    optionsList.innerHTML = `
        <div class="option-item" style="border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; background:#fff;">
            <input type="text" class="option-input" placeholder="e.g., Small, Red, Matte" style="width:100%; margin-bottom:8px;">
            <div style="display:flex; gap:8px; margin-bottom:8px;">
                <button type="button" class="btn-remove" onclick="removeNewFieldOption(this)" style="flex:1;">Remove</button>
                <button type="button" onclick="toggleOptionNestedFields(this)" style="flex:1; padding:8px 12px; background:#f0fdfa; color:#0d9488; border:1px solid #0d9488; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;">+ Add Nested Fields</button>
            </div>
            <div class="option-nested-fields" style="display:none; margin-top:12px; padding-top:12px; border-top:1px dashed #cbd5e1;">
                <div class="option-nested-container"></div>
                <button type="button" class="btn-add" onclick="addOptionNestedField(this)" style="margin-top:8px; font-size:12px;">+ Add Field</button>
            </div>
        </div>
        <div class="option-item" style="border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; background:#fff;">
            <input type="text" class="option-input" placeholder="e.g., Medium, Blue, Glossy" style="width:100%; margin-bottom:8px;">
            <div style="display:flex; gap:8px; margin-bottom:8px;">
                <button type="button" class="btn-remove" onclick="removeNewFieldOption(this)" style="flex:1;">Remove</button>
                <button type="button" onclick="toggleOptionNestedFields(this)" style="flex:1; padding:8px 12px; background:#f0fdfa; color:#0d9488; border:1px solid #0d9488; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;">+ Add Nested Fields</button>
            </div>
            <div class="option-nested-fields" style="display:none; margin-top:12px; padding-top:12px; border-top:1px dashed #cbd5e1;">
                <div class="option-nested-container"></div>
                <button type="button" class="btn-add" onclick="addOptionNestedField(this)" style="margin-top:8px; font-size:12px;">+ Add Field</button>
            </div>
        </div>
        <div class="option-item" style="border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; background:#fff;">
            <input type="text" class="option-input" placeholder="e.g., Large, Green, Vinyl" style="width:100%; margin-bottom:8px;">
            <div style="display:flex; gap:8px; margin-bottom:8px;">
                <button type="button" class="btn-remove" onclick="removeNewFieldOption(this)" style="flex:1;">Remove</button>
                <button type="button" onclick="toggleOptionNestedFields(this)" style="flex:1; padding:8px 12px; background:#f0fdfa; color:#0d9488; border:1px solid #0d9488; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;">+ Add Nested Fields</button>
            </div>
            <div class="option-nested-fields" style="display:none; margin-top:12px; padding-top:12px; border-top:1px dashed #cbd5e1;">
                <div class="option-nested-container"></div>
                <button type="button" class="btn-add" onclick="addOptionNestedField(this)" style="margin-top:8px; font-size:12px;">+ Add Field</button>
            </div>
        </div>
    `;
    
    // Reset dimension list
    const dimensionList = document.getElementById('new-field-dimension-list');
    dimensionList.innerHTML = `
        <div class="option-item" style="display: flex; gap: 8px; align-items: center;">
            <input type="text" class="dimension-width" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            <span style="color: #cbd5e1; font-weight: bold;">Ã—</span>
            <input type="text" class="dimension-height" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            <button type="button" class="btn-remove" onclick="removeNewDimensionOption(this)">Remove</button>
        </div>
        <div class="option-item" style="display: flex; gap: 8px; align-items: center;">
            <input type="text" class="dimension-width" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            <span style="color: #cbd5e1; font-weight: bold;">Ã—</span>
            <input type="text" class="dimension-height" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            <button type="button" class="btn-remove" onclick="removeNewDimensionOption(this)">Remove</button>
        </div>
        <div class="option-item" style="display: flex; gap: 8px; align-items: center;">
            <input type="text" class="dimension-width" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            <span style="color: #cbd5e1; font-weight: bold;">Ã—</span>
            <input type="text" class="dimension-height" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            <button type="button" class="btn-remove" onclick="removeNewDimensionOption(this)">Remove</button>
        </div>
    `;
}

function toggleNewFieldOptions() {
    const typeSelect = document.getElementById('new-field-type');
    const type = typeSelect ? typeSelect.value : '';
    const optionsSection = document.getElementById('new-field-options-section');
    const dimensionSection = document.getElementById('new-field-dimension-section');
    
    if (optionsSection) optionsSection.style.display = (type === 'select' || type === 'radio') ? 'block' : 'none';
    if (dimensionSection) dimensionSection.style.display = (type === 'dimension') ? 'block' : 'none';
}

function removeNewDimensionOption(btn) {
    btn.closest('.option-item').remove();
    checkNewDimensionDuplicates();
}

function checkNewDimensionDuplicates() {
    const dimensionItems = document.querySelectorAll('#new-field-dimension-list .option-item');
    const dimensions = [];
    const duplicates = new Set();
    
    dimensionItems.forEach(item => {
        const w = item.querySelector('.dimension-width').value.trim();
        const h = item.querySelector('.dimension-height').value.trim();
        if (w && h) {
            const dim = w + 'x' + h;
            if (dimensions.includes(dim)) {
                duplicates.add(dim);
                item.style.backgroundColor = '#fee2e2';
                item.style.border = '1px solid #ef4444';
                item.style.borderRadius = '6px';
                item.style.padding = '4px';
            } else {
                dimensions.push(dim);
                item.style.backgroundColor = '';
                item.style.border = '';
                item.style.padding = '';
            }
        }
    });
    
    return duplicates.size === 0;
}

function addNewField() {
    const label = document.getElementById('new-field-label').value.trim();
    const type = document.getElementById('new-field-type').value;
    const required = document.getElementById('new-field-required').checked;
    
    if (!label) {
        alert('Please enter a field label');
        return;
    }
    if (!type) {
        alert('Please select a field type');
        return;
    }
    
    // Collect options from individual input fields
    let options = null;
    if (type === 'select' || type === 'radio') {
        const optionItems = document.querySelectorAll('#new-field-options-list .option-item');
        options = [];
        
        optionItems.forEach(item => {
            const optionInput = item.querySelector('.option-input');
            const optionValue = optionInput ? optionInput.value.trim() : '';
            
            if (optionValue !== '') {
                const optionData = { value: optionValue };
                
                // Check for nested fields
                const nestedFields = [];
                const nestedItems = item.querySelectorAll('.nested-field-item');
                
                nestedItems.forEach(nestedItem => {
                    const nestedLabel = nestedItem.querySelector('.nested-field-label')?.value.trim();
                    const nestedType = nestedItem.querySelector('.nested-field-type')?.value;
                    const nestedRequired = nestedItem.querySelector('.nested-required')?.checked || false;
                    
                    if (nestedLabel && nestedType) {
                        const nestedFieldData = {
                            label: nestedLabel,
                            type: nestedType,
                            required: nestedRequired
                        };
                        
                        // If nested field is select/radio, collect its options
                        if (nestedType === 'select' || nestedType === 'radio') {
                            const nestedOptions = [];
                            nestedItem.querySelectorAll('.nested-option-input').forEach(input => {
                                const val = input.value.trim();
                                if (val) nestedOptions.push(val);
                            });
                            if (nestedOptions.length > 0) {
                                nestedFieldData.options = nestedOptions;
                            }
                        }
                        
                        // If nested field is dimension, collect dimension data
                        if (nestedType === 'dimension') {
                            const dimensionOptions = [];
                            nestedItem.querySelectorAll('.nested-dimension-list > div').forEach(dimItem => {
                                const w = dimItem.querySelector('.nested-dimension-w')?.value.trim();
                                const h = dimItem.querySelector('.nested-dimension-h')?.value.trim();
                                if (w && h) dimensionOptions.push(w + 'Ã—' + h);
                            });
                            if (dimensionOptions.length > 0) {
                                nestedFieldData.options = dimensionOptions;
                            }
                            nestedFieldData.unit = nestedItem.querySelector('.nested-dimension-unit')?.value || 'ft';
                            nestedFieldData.allow_others = nestedItem.querySelector('.nested-allow-others')?.checked || false;
                        }
                        
                        nestedFields.push(nestedFieldData);
                    }
                });
                
                if (nestedFields.length > 0) {
                    optionData.nested_fields = nestedFields;
                }
                
                options.push(optionData);
            }
        });
        
        if (options.length === 0) {
            alert('Please enter at least one option for this field type');
            return;
        }
    } else if (type === 'dimension') {
        const dimensionItems = document.querySelectorAll('#new-field-dimension-list .option-item');
        options = [];
        dimensionItems.forEach(item => {
            const w = item.querySelector('.dimension-width').value.trim();
            const h = item.querySelector('.dimension-height').value.trim();
            if (w && h) {
                options.push(w + 'Ã—' + h);
            }
        });

        
        if (options.length === 0) {
            alert('Please enter at least one dimension option');
            return;
        }
        
        // Check for duplicates
        if (!checkNewDimensionDuplicates()) {
            alert('Duplicate dimensions detected! Please remove or change duplicate entries (highlighted in red).');
            return;
        }
    }
    
    // Get unit for dimension fields
    const unit = (type === 'dimension') ? document.getElementById('new-field-unit').value : null;
    const allowOthers = (type === 'dimension') ? document.getElementById('new-field-allow-others').checked : null;
    
    const fieldKey = 'custom_' + label.toLowerCase().replace(/[^a-z0-9]/g, '_');
    
    // Conditional Logic
    const pf_NewParentKeySelect = document.getElementById('new-field-parent-key');
    const pf_NewParentValueSelect = document.getElementById('new-field-parent-value');
    
    // Add to in-memory configuration
    fieldConfigurations[fieldKey] = {
        label: label,
        type: type,
        options: options,
        required: required,
        visible: true,
        unit: unit,
        allow_others: allowOthers,
        parent_field_key: (pf_NewParentKeySelect && pf_NewParentKeySelect.value) ? pf_NewParentKeySelect.value : null,
        parent_value: (pf_NewParentValueSelect && pf_NewParentValueSelect.value) ? pf_NewParentValueSelect.value : null
    };

    
    // Add to table
    const tbody = document.querySelector('.orders-table tbody');
    const newRow = document.createElement('tr');
    newRow.setAttribute('onclick', "toggleFieldRow('" + fieldKey + "')");
    newRow.style.cursor = 'pointer';
    newRow.innerHTML = `
        <td style="font-weight:500;color:#1f2937;">${escapeHtml(label)}</td>
        <td>
            <span style="display:inline-block;padding:3px 8px;background:#e0e7ff;color:#4338ca;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase;">${escapeHtml(type)}</span>
        </td>
        <td style="text-align:center;">
            <span style="display:inline-block;padding:3px 8px;background:#f3f4f6;color:#6b7280;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase;">CUSTOM</span>
        </td>
        <td style="text-align:center;">
            <span style="color:${required ? '#059669' : '#6b7280'};font-weight:${required ? '600' : '500'};font-size:12px;">${required ? 'âœ“ Yes' : 'Optional'}</span>
        </td>
        <td style="text-align:center;">
            <span style="color:#059669;font-weight:600;font-size:12px;">âœ“ Visible</span>
        </td>

        <td style="text-align:right;white-space:nowrap;" onclick="event.stopPropagation();">
            <button type="button" class="btn-action blue" data-key="${fieldKey}" onclick="event.stopPropagation(); handleEditClick('${fieldKey}')">Edit</button>
            <button type="button" class="btn-action red" onclick="handleDeleteClick(this)" data-field-key="${fieldKey}">Delete</button>
        </td>
    `;
    tbody.appendChild(newRow);
    
    closeAddFieldModal();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Parent Content Helpers
function populateParentDropdown(selectId, currentParentKey, currentFieldKey) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    // Keep the first option ("No Condition")
    const firstOption = select.options[0];
    select.innerHTML = '';
    select.appendChild(firstOption);
    
    // Add all fields that can be parents (radio, select)
    for (const key in fieldConfigurations) {
        // A field cannot be its own parent
        if (key === currentFieldKey) continue;
        
        const config = fieldConfigurations[key];
        if (config.type === 'radio' || config.type === 'select') {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = config.label + (key === 'branch' ? ' (Default)' : '');
            if (key === currentParentKey) option.selected = true;
            select.appendChild(option);
        }
    }
}

function updateEditTriggerValues(currentVal = '') {
    const parentKey = document.getElementById('edit-field-parent-key').value;
    const valueGroup = document.getElementById('edit-trigger-value-group');
    const valueSelect = document.getElementById('edit-field-parent-value');
    
    populateTriggerValues(parentKey, valueGroup, valueSelect, currentVal);
}

function updateNewTriggerValues(currentVal = '') {
    const parentKey = document.getElementById('new-field-parent-key').value;
    const valueGroup = document.getElementById('new-trigger-value-group');
    const valueSelect = document.getElementById('new-field-parent-value');
    
    populateTriggerValues(parentKey, valueGroup, valueSelect, currentVal);
}

function addNewFieldOption() {
    const list = document.getElementById('new-field-options-list');
    const item = document.createElement('div');
    item.className = 'option-item';
    item.style.cssText = 'border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; background:#fff;';
    item.innerHTML = `
        <input type="text" class="option-input" placeholder="Enter option value" style="width:100%; margin-bottom:8px;">
        <div style="display:flex; gap:8px; margin-bottom:8px;">
            <button type="button" class="btn-remove" onclick="removeNewFieldOption(this)" style="flex:1;">Remove</button>
            <button type="button" onclick="toggleOptionNestedFields(this)" style="flex:1; padding:8px 12px; background:#f0fdfa; color:#0d9488; border:1px solid #0d9488; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;">+ Add Nested Fields</button>
        </div>
        <div class="option-nested-fields" style="display:none; margin-top:12px; padding-top:12px; border-top:1px dashed #cbd5e1;">
            <div class="option-nested-container"></div>
            <button type="button" class="btn-add" onclick="addOptionNestedField(this)" style="margin-top:8px; font-size:12px;">+ Add Field</button>
        </div>
    `;
    list.appendChild(item);
}

function toggleOptionNestedFields(btn) {
    const optionItem = btn.closest('.option-item');
    const nestedSection = optionItem.querySelector('.option-nested-fields');
    if (nestedSection.style.display === 'none') {
        nestedSection.style.display = 'block';
        btn.textContent = '- Hide Nested Fields';
    } else {
        nestedSection.style.display = 'none';
        btn.textContent = '+ Add Nested Fields';
    }
}

function addOptionNestedField(btn) {
    const container = btn.previousElementSibling;
    const nestedField = document.createElement('div');
    nestedField.className = 'nested-field-item';
    nestedField.style.cssText = 'margin-bottom:12px; padding:12px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px;';
    
    // Create the nested field structure
    nestedField.innerHTML = `
        <div style="margin-bottom:8px;">
            <label style="display:block; font-size:11px; font-weight:600; color:#6b7280; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Field Label *</label>
            <input type="text" class="nested-field-label" placeholder="e.g., Special Instructions" style="width:100%; padding:8px 10px; border:1px solid #e5e7eb; border-radius:4px; font-size:13px;">
        </div>
        <div style="margin-bottom:8px;">
            <label style="display:block; font-size:11px; font-weight:600; color:#6b7280; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Field Type *</label>
            <select class="nested-field-type" style="width:100%; padding:8px 10px; border:1px solid #e5e7eb; border-radius:4px; font-size:13px; background:#fff;" onchange="toggleNestedFieldOptions(this)">
                <option value="">-- Select Type --</option>
                <option value="select">Select (Dropdown)</option>
                <option value="dimension">Dimension (Size)</option>
                <option value="radio">Radio Buttons</option>
                <option value="file">File Upload</option>
                <option value="textarea">Textarea (Multi-line)</option>
            </select>
        </div>
        
        <div class="nested-field-options-section" style="display:none; margin-bottom:8px;">
            <label style="display:block; font-size:11px; font-weight:600; color:#6b7280; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Options (Choices shown to customer)</label>
            <div class="nested-options-list" style="display:flex; flex-direction:column; gap:8px; margin-bottom:8px;"></div>
            <button type="button" onclick="addNestedOption(this)" class="btn-add" style="padding:8px 14px; background:#f0fdfa; color:#0d9488; border:1px solid #0d9488; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;">+ Add Option</button>
        </div>
        
        <div class="nested-field-dimension-section" style="display:none; margin-bottom:8px;">
            <div style="margin-bottom:8px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#6b7280; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Measurement Unit</label>
                <select class="nested-dimension-unit" style="width:100%; padding:8px 10px; border:1px solid #e5e7eb; border-radius:4px; font-size:13px; background:#fff;">
                    <option value="ft">Feet (ft)</option>
                    <option value="in">Inches (in)</option>
                </select>
            </div>
            <label style="display:inline-flex; align-items:center; gap:10px; font-size:13px; color:#374151; font-weight:500; margin-bottom:8px;">
                <label style="position:relative; display:inline-block; width:48px; height:26px;">
                    <input type="checkbox" class="nested-allow-others" checked style="opacity:0; width:0; height:0;">
                    <span style="position:absolute; cursor:pointer; inset:0; background:#cbd5e1; border-radius:26px; transition:0.3s;"></span>
                    <span style="position:absolute; content:''; height:20px; width:20px; left:3px; bottom:3px; background:white; border-radius:50%; transition:0.3s; box-shadow:0 2px 4px rgba(0,0,0,0.2);"></span>
                </label>
                <span>Allow "Others" (Custom Size Input)</span>
            </label>
            <div style="margin-bottom:8px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#6b7280; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Dimension Options (Width Ã— Height)</label>
                <div class="nested-dimension-list" style="display:flex; flex-direction:column; gap:8px; margin-bottom:8px;"></div>
                <button type="button" onclick="addNestedDimension(this)" class="btn-add" style="padding:8px 14px; background:#f0fdfa; color:#0d9488; border:1px solid #0d9488; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;">+ Add Dimension</button>
            </div>
        </div>
        
        <div style="margin-bottom:8px;">
            <label style="display:inline-flex; align-items:center; gap:10px; font-size:13px; color:#374151; font-weight:500;">
                <label style="position:relative; display:inline-block; width:48px; height:26px;">
                    <input type="checkbox" class="nested-required" checked style="opacity:0; width:0; height:0;">
                    <span style="position:absolute; cursor:pointer; inset:0; background:#0d9488; border-radius:26px; transition:0.3s;"></span>
                    <span style="position:absolute; content:''; height:20px; width:20px; left:25px; bottom:3px; background:white; border-radius:50%; transition:0.3s; box-shadow:0 2px 4px rgba(0,0,0,0.2);"></span>
                </label>
                <span>Required Field</span>
            </label>
        </div>
        
        <button type="button" onclick="removeOptionNestedField(this)" style="width:100%; padding:8px 12px; background:#fee2e2; color:#dc2626; border:none; border-radius:4px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;">Remove Field</button>
    `;
    container.appendChild(nestedField);
}

function toggleNestedFieldOptions(select) {
    const nestedField = select.closest('.nested-field-item');
    const optionsSection = nestedField.querySelector('.nested-field-options-section');
    const dimensionSection = nestedField.querySelector('.nested-field-dimension-section');
    const type = select.value;
    
    // Hide all sections first
    optionsSection.style.display = 'none';
    dimensionSection.style.display = 'none';
    
    if (type === 'select' || type === 'radio') {
        optionsSection.style.display = 'block';
        const optionsList = optionsSection.querySelector('.nested-options-list');
        if (optionsList.children.length === 0) {
            // Add 3 default option inputs
            const addBtn = optionsSection.querySelector('button');
            for (let i = 0; i < 3; i++) {
                addNestedOption(addBtn);
            }
        }
    } else if (type === 'dimension') {
        dimensionSection.style.display = 'block';
        const dimensionList = dimensionSection.querySelector('.nested-dimension-list');
        if (dimensionList.children.length === 0) {
            // Add 3 default dimension inputs
            const addBtn = dimensionSection.querySelector('button');
            for (let i = 0; i < 3; i++) {
                addNestedDimension(addBtn);
            }
        }
    }
}

function addNestedOption(btn) {
    const optionsSection = btn.closest('.nested-field-options-section');
    const optionsList = optionsSection.querySelector('.nested-options-list');
    const optionItem = document.createElement('div');
    optionItem.style.cssText = 'display:flex; gap:8px; align-items:center;';
    optionItem.innerHTML = `
        <input type="text" class="nested-option-input" placeholder="Enter option" style="flex:1; padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px;">
        <button type="button" onclick="removeNestedOption(this)" class="btn-remove" style="padding:8px 12px; background:#fee2e2; color:#dc2626; border:none; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; transition:all 0.2s;">Remove</button>
    `;
    optionsList.appendChild(optionItem);
}

function removeNestedOption(btn) {
    const optionsList = btn.closest('.nested-options-list');
    if (optionsList.children.length > 1) {
        btn.closest('div').remove();
    } else {
        alert('At least one option is required.');
    }
}

function addNestedDimension(btn) {
    const dimensionSection = btn.closest('.nested-field-dimension-section');
    const dimensionList = dimensionSection.querySelector('.nested-dimension-list');
    const dimensionItem = document.createElement('div');
    dimensionItem.style.cssText = 'display:flex; gap:8px; align-items:center;';
    dimensionItem.innerHTML = `
        <input type="text" class="nested-dimension-w" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex:1; padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; text-align:center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        <span style="color:#cbd5e1; font-weight:bold;">Ã—</span>
        <input type="text" class="nested-dimension-h" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex:1; padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; text-align:center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        <button type="button" onclick="removeNestedDimension(this)" class="btn-remove" style="padding:8px 12px; background:#fee2e2; color:#dc2626; border:none; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; transition:all 0.2s;">Remove</button>
    `;
    dimensionList.appendChild(dimensionItem);
}

function removeNestedDimension(btn) {
    btn.closest('div').remove();
}

function removeOptionNestedField(btn) {
    btn.closest('.nested-field-item').remove();
}

function removeNewFieldOption(btn) {
    const list = document.getElementById('new-field-options-list');
    if (list.querySelectorAll('.option-item').length > 1) {
        btn.closest('.option-item').remove();
    } else {
        alert('At least one option is required.');
    }
}

function populateTriggerValues(parentKey, group, select, currentVal) {
    if (!parentKey) {
        group.style.display = 'none';
        select.innerHTML = '<option value="">-- Select Value --</option>';
        return;
    }
    
    const parentConfig = fieldConfigurations[parentKey];
    if (!parentConfig) {
        group.style.display = 'none';
        return;
    }
    
    group.style.display = 'block';
    select.innerHTML = '<option value="">-- Select Value --</option>';
    
    let options = [];
    if (parentKey === 'branch') {
        // Branches are dynamic, but for admin we might not have the list.
        // However, the rule is "radio/select" only.
        // For 'branch', we need to decide if we want to support it.
        // Let's assume for now we only support custom radio/select fields.
        // Or if it's branch, we might need to fetch branches or just show a warning.
    } else {
        options = parentConfig.options || [];
    }
    
    options.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt;
        option.textContent = opt;
        if (opt === currentVal) option.selected = true;
        select.appendChild(option);
    });
}

document.getElementById('configForm').addEventListener('submit', function(e) {
    // Use the in-memory fieldConfigurations object
    const configs = {};
    let order = 0;
    
    // Add all fields from fieldConfigurations
    for (const key in fieldConfigurations) {
        const config = fieldConfigurations[key];
        configs[key] = {
            label: config.label,
            type: config.type,
            options: config.options,
            visible: config.visible !== false,
            required: config.required || false,
            order: order++
        };
        
        // Add unit for dimension fields
        if (config.type === 'dimension') {
            configs[key].unit = config.unit || 'ft';
            configs[key].allow_others = config.allow_others !== false;
        }
        
        // Add Conditional Logic
        if (config.parent_field_key && config.parent_value) {
            configs[key].parent_field_key = config.parent_field_key;
            configs[key].parent_value = config.parent_value;
        }
    }
    
    console.log('Saving configurations:', configs);
    document.getElementById('fieldConfigsInput').value = JSON.stringify(configs);
});

