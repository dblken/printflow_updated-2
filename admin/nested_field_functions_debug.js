// Enhanced debug version of nested field functions
console.log('🔧 Debug nested field functions loading...');

// Enhanced form submission to collect nested fields with extensive debugging
window.collectNestedFieldConfigurations = function() {
    console.log('🔍 collectNestedFieldConfigurations called');
    
    const configs = {};
    
    // Collect configurations from each field
    const sectionCards = document.querySelectorAll('.section-card');
    console.log('📋 Found section cards:', sectionCards.length);
    
    sectionCards.forEach((card, index) => {
        const key = card.getAttribute('data-field-key');
        console.log(`🔑 Processing field: ${key} (index: ${index})`);
        
        if (!key) {
            console.log('⚠️ No field key found, skipping');
            return;
        }
        
        const isDefault = card.getAttribute('data-is-default') === 'true';
        const labelInput = card.querySelector('.label-input');
        const requiredToggle = card.querySelector('.required-toggle');
        
        const config = {
            label: labelInput ? labelInput.value : window.fieldConfigurations[key]?.label || key,
            type: window.fieldConfigurations[key]?.type || 'text',
            visible: true,
            required: isDefault ? (key === 'notes' ? false : true) : (requiredToggle ? requiredToggle.checked : false),
            order: index
        };
        
        console.log(`📝 Base config for ${key}:`, config);
        
        // Handle radio fields with nested fields
        if (config.type === 'radio') {
            console.log(`📻 Processing radio field: ${key}`);
            const radioOptionsList = card.querySelector('.radio-options-list');
            
            if (radioOptionsList) {
                const options = [];
                const radioOptionItems = radioOptionsList.querySelectorAll('.radio-option-item');
                console.log(`🔘 Found radio options: ${radioOptionItems.length}`);
                
                radioOptionItems.forEach((optionItem, optIdx) => {
                    const optionValue = optionItem.querySelector('.option-input').value.trim();
                    console.log(`🔘 Processing option ${optIdx}: "${optionValue}"`);
                    
                    if (!optionValue) {
                        console.log('⚠️ Empty option value, skipping');
                        return;
                    }
                    
                    // Check if this option has a nested field panel
                    const nestedPanel = optionItem.querySelector('.nested-field-panel');
                    console.log(`🔍 Nested panel for option ${optIdx}:`, nestedPanel ? 'found' : 'not found');
                    
                    if (nestedPanel && nestedPanel.style.display !== 'none') {
                        console.log(`✅ Processing nested fields for option: "${optionValue}"`);
                        
                        // Has nested field panel - collect all nested fields directly
                        const nestedFieldItems = nestedPanel.querySelectorAll('.nested-field-item');
                        console.log(`🔧 Found nested field items: ${nestedFieldItems.length}`);
                        
                        const nestedFields = [];
                        
                        nestedFieldItems.forEach((nestedFieldItem, nIdx) => {
                            const nLabel = nestedFieldItem.querySelector('.nested-label')?.value.trim();
                            const nTypeSelect = nestedFieldItem.querySelector('.nested-type-select');
                            const nType = nTypeSelect ? nTypeSelect.value : '';
                            const nRequired = nestedFieldItem.querySelector('.nested-required')?.checked || false;
                            
                            console.log(`🔧 Nested field ${nIdx}: label="${nLabel}", type="${nType}", required=${nRequired}`);
                            
                            if (nLabel && nType) {
                                const nField = {
                                    label: nLabel,
                                    type: nType,
                                    required: nRequired
                                };
                                
                                // Collect options for select/radio
                                if (['select', 'radio'].includes(nType)) {
                                    const nOptions = [];
                                    nestedFieldItem.querySelectorAll('.nested-option-input').forEach(nOptInput => {
                                        const nOptVal = nOptInput.value.trim();
                                        if (nOptVal) nOptions.push(nOptVal);
                                    });
                                    if (nOptions.length > 0) {
                                        nField.options = nOptions;
                                        console.log(`📋 Added ${nOptions.length} options to nested field`);
                                    }
                                }
                                
                                // Collect dimensions
                                if (nType === 'dimension') {
                                    const nDimensions = [];
                                    nestedFieldItem.querySelectorAll('.nested-dimension-list .option-item').forEach(dimItem => {
                                        const w = dimItem.querySelector('.nested-dimension-width')?.value.trim();
                                        const h = dimItem.querySelector('.nested-dimension-height')?.value.trim();
                                        if (w && h) nDimensions.push(w + '×' + h);
                                    });
                                    if (nDimensions.length > 0) {
                                        nField.options = nDimensions;
                                        console.log(`📐 Added ${nDimensions.length} dimensions to nested field`);
                                    }
                                    
                                    const allowOthersCheckbox = nestedFieldItem.querySelector('.nested-allow-others');
                                    if (allowOthersCheckbox) {
                                        nField.allow_others = allowOthersCheckbox.checked;
                                    }
                                    
                                    const unitSelect = nestedFieldItem.querySelector('.nested-unit-select');
                                    nField.unit = unitSelect ? unitSelect.value : 'ft';
                                }
                                
                                nestedFields.push(nField);
                                console.log(`✅ Added nested field: ${nLabel}`);
                            } else {
                                console.log(`⚠️ Skipping incomplete nested field: label="${nLabel}", type="${nType}"`);
                            }
                        });
                        
                        if (nestedFields.length > 0) {
                            options.push({
                                value: optionValue,
                                nested_fields: nestedFields
                            });
                            console.log(`✅ Added option with ${nestedFields.length} nested fields: "${optionValue}"`);
                        } else {
                            options.push(optionValue);
                            console.log(`➕ Added simple option: "${optionValue}"`);
                        }
                    } else {
                        // No nested field panel
                        options.push(optionValue);
                        console.log(`➕ Added simple option: "${optionValue}"`);
                    }
                });
                
                if (options.length > 0) {
                    config.options = options;
                    console.log(`📋 Final options for ${key}:`, options);
                }
            }
        }
        // Handle select fields (no nested support)
        else if (config.type === 'select') {
            const optionList = card.querySelector('.option-list:not(.radio-options-list)');
            if (optionList) {
                const options = [];
                optionList.querySelectorAll('.option-input').forEach(input => {
                    const val = input.value.trim();
                    if (val) options.push(val);
                });
                if (options.length > 0) config.options = options;
            }
        }
        // Handle dimension fields
        else if (config.type === 'dimension') {
            const dimensionList = card.querySelector('.dimension-options');
            if (dimensionList) {
                const dimensions = [];
                dimensionList.querySelectorAll('.option-item').forEach(item => {
                    const width = item.querySelector('.dimension-width')?.value.trim();
                    const height = item.querySelector('.dimension-height')?.value.trim();
                    if (width && height) {
                        dimensions.push(width + '×' + height);
                    }
                });
                if (dimensions.length > 0) {
                    config.options = dimensions;
                }
            }
            
            const allowOthersCheckbox = card.querySelector('.allow-others');
            if (allowOthersCheckbox) {
                config.allow_others = allowOthersCheckbox.checked;
            }
            
            const unitSelect = card.querySelector('.unit-select');
            config.unit = unitSelect ? unitSelect.value : 'ft';
        }
        
        configs[key] = config;
        console.log(`✅ Final config for ${key}:`, config);
    });
    
    console.log('🎯 Final configurations:', configs);
    return configs;
};

// Enhanced form submission handler
window.handleFormSubmission = function(event) {
    console.log('📤 Form submission started');
    
    const configs = window.collectNestedFieldConfigurations();
    
    // Add configurations to form data
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'field_configurations';
    hiddenInput.value = JSON.stringify(configs);
    
    event.target.appendChild(hiddenInput);
    
    console.log('✅ Form submission prepared with configurations');
};

console.log('✅ Debug nested field functions loaded successfully');tem => {
                    const w = item.querySelector('.dimension-w')?.value.trim();
                    const h = item.querySelector('.dimension-h')?.value.trim();
                    if (w && h) dimensions.push(w + '×' + h);
                });
                if (dimensions.length > 0) config.options = dimensions;
                
                const unitSelect = card.querySelector('.unit-select');
                if (unitSelect) config.unit = unitSelect.value;
                
                const allowOthersToggle = card.querySelector('.allow-others-toggle');
                if (allowOthersToggle) config.allow_others = allowOthersToggle.checked;
            }
        }
        
        configs[key] = config;
        console.log(`✅ Final config for ${key}:`, config);
    });
    
    console.log('🎯 Final collected configurations:', configs);
    return configs;
};

// Debug version of toggle nested field panel
window.toggleNestedFieldPanel = function(btn, fieldKey, optionIndex) {
    console.log(`🔧 toggleNestedFieldPanel called: fieldKey=${fieldKey}, optionIndex=${optionIndex}`);
    
    const optionItem = btn.closest('.radio-option-item');
    let nestedPanel = optionItem.querySelector('.nested-field-panel');
    
    console.log('🔍 Option item:', optionItem);
    console.log('🔍 Existing nested panel:', nestedPanel);
    
    if (!nestedPanel) {
        console.log('➕ Creating new nested panel');
        // Create nested panel if it doesn't exist
        nestedPanel = document.createElement('div');
        nestedPanel.className = 'nested-field-panel';
        nestedPanel.style.cssText = 'margin-top:12px;padding:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;';
        nestedPanel.innerHTML = `
            <label style="display:block;font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;text-transform:uppercase;">Add Nested Fields (Optional)</label>
            <div class="nested-fields-config" id="nested-config-${fieldKey}-${optionIndex}" style="display:block;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding:8px 0;border-bottom:1px solid #e5e7eb;">
                    <label style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;">Nested Fields</label>
                    <button type="button" class="btn-add" onclick="addNestedFieldItem(this)" style="padding:4px 8px;font-size:11px;">+ Add Field</button>
                </div>
                <div class="nested-fields-list" style="display:flex;flex-direction:column;gap:12px;"></div>
            </div>
        `;
        optionItem.appendChild(nestedPanel);
        btn.textContent = '−'; // Change to minus
        btn.style.background = '#ef4444'; // Red color
        btn.title = 'Remove Nested Field';
        console.log('✅ Nested panel created and added');
    } else {
        console.log('🔄 Toggling existing nested panel');
        // Toggle visibility
        if (nestedPanel.style.display === 'none') {
            nestedPanel.style.display = 'block';
            btn.textContent = '−';
            btn.style.background = '#ef4444';
            btn.title = 'Remove Nested Field';
            console.log('👁️ Nested panel shown');
        } else {
            nestedPanel.style.display = 'none';
            btn.textContent = '+';
            btn.style.background = '#10b981';
            btn.title = 'Add Nested Field';
            console.log('🙈 Nested panel hidden');
        }
    }
};

// Debug version of add nested field item
window.addNestedFieldItem = function(btn) {
    console.log('➕ addNestedFieldItem called');
    
    const container = btn.closest('.nested-fields-config');
    const fieldsList = container.querySelector('.nested-fields-list');
    
    console.log('🔍 Container:', container);
    console.log('🔍 Fields list:', fieldsList);
    
    const item = document.createElement('div');
    item.className = 'nested-field-item';
    item.style.cssText = 'padding:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;position:relative;';
    
    item.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <label style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;">Field Configuration</label>
            <button type="button" onclick="removeNestedFieldItem(this)" style="padding:4px 8px;background:#fee2e2;color:#dc2626;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;">Remove</button>
        </div>
        
        <div style="margin-bottom:16px;padding-top:8px;">
            <label class="field-label">Field Label *</label>
            <input type="text" class="nested-label field-input" placeholder="e.g., Special Instructions" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;transition:all 0.2s;">
        </div>
        
        <div style="margin-bottom:16px;">
            <label class="field-label">Field Type *</label>
            <select class="nested-type-select field-input" onchange="updateNestedFieldType(this)">
                <option value="">-- Select Type --</option>
                <option value="select">Select (Dropdown)</option>
                <option value="radio">Radio Buttons</option>
                <option value="dimension">Dimension (Size)</option>
                <option value="file">File Upload</option>
                <option value="textarea">Textarea</option>
            </select>
        </div>
        
        <div class="nested-options-container" style="display:none;margin-bottom:16px;">
            <label class="field-label">Options (Choices shown to customer)</label>
            <div class="nested-options-list option-list">
                <div class="option-item">
                    <input type="text" class="nested-option-input option-input" placeholder="e.g., Small, Red, Matte">
                    <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Remove</button>
                </div>
                <div class="option-item">
                    <input type="text" class="nested-option-input option-input" placeholder="e.g., Medium, Blue, Glossy">
                    <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Remove</button>
                </div>
                <div class="option-item">
                    <input type="text" class="nested-option-input option-input" placeholder="e.g., Large, Green, Vinyl">
                    <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Remove</button>
                </div>
            </div>
            <button type="button" class="btn-add" onclick="addNestedOption(this)" style="margin-top:12px;">+ Add Option</button>
        </div>
        
        <div style="margin-top:16px;">
            <label class="toggle-label">
                <label class="toggle-switch">
                    <input type="checkbox" class="nested-required">
                    <span class="toggle-slider"></span>
                </label>
                <span>Required Field</span>
            </label>
        </div>
    `;
    
    fieldsList.appendChild(item);
    console.log('✅ Nested field item added');
};

// Debug version of remove nested field item
window.removeNestedFieldItem = function(btn) {
    console.log('🗑️ removeNestedFieldItem called');
    btn.closest('.nested-field-item').remove();
    console.log('✅ Nested field item removed');
};

// Debug version of update nested field type
window.updateNestedFieldType = function(select) {
    console.log('🔄 updateNestedFieldType called, type:', select.value);
    
    const item = select.closest('.nested-field-item');
    const optionsContainer = item.querySelector('.nested-options-container');
    const type = select.value;
    
    // Hide all containers first
    if (optionsContainer) optionsContainer.style.display = 'none';
    
    // Show appropriate container based on type
    if (['select', 'radio'].includes(type)) {
        if (optionsContainer) {
            optionsContainer.style.display = 'block';
            console.log('👁️ Options container shown for type:', type);
        }
    }
    
    console.log('✅ Nested field type updated');
};

window.addNestedOption = function(btn) {
    console.log('➕ addNestedOption called');
    const list = btn.previousElementSibling;
    const div = document.createElement('div');
    div.className = 'option-item';
    div.innerHTML = `
        <input type="text" class="nested-option-input option-input" placeholder="Enter option">
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Remove</button>
    `;
    list.appendChild(div);
    console.log('✅ Nested option added');
};

console.log('✅ Debug nested field functions loaded successfully');