
// BLOCK 3: Complex Configuration Logic

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
                                if (w && h) dimensionOptions.push(w + '×' + h);
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
                options.push(w + '×' + h);
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
    window.fieldConfigurations[fieldKey] = {
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
            <span style="color:${required ? '#059669' : '#6b7280'};font-weight:${required ? '600' : '500'};font-size:12px;">${required ? '✓ Yes' : 'Optional'}</span>
        </td>
        <td style="text-align:center;">
            <span style="color:#059669;font-weight:600;font-size:12px;">✓ Visible</span>
        </td>

        <td style="text-align:right;white-space:nowrap;" onclick="event.stopPropagation();">
            <button type="button" class="btn-action blue" onclick="showEditFieldModal('${fieldKey}')" data-key="${fieldKey}">Edit</button>
            <button type="button" class="btn-action red" onclick="deleteField(this)" data-field-key="${fieldKey}">Delete</button>
        </td>
    `;
    tbody.appendChild(newRow);
    
    closeAddFieldModal();
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
                <label style="display:block; font-size:11px; font-weight:600; color:#6b7280; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Dimension Options (Width × Height)</label>
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
        <span style="color:#cbd5e1; font-weight:bold;">×</span>
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

document.getElementById('configForm').addEventListener('submit', function(e) {
    // Use the in-memory window.fieldConfigurations object
    const configs = {};
    let order = 0;
    
    // Add all fields from window.fieldConfigurations
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

