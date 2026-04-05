// Fix for nested field saving issue
// This script will override the form submission to properly collect nested field data

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('configForm');
    if (!form) return;
    
    // Remove existing event listeners and add our enhanced one
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);
    
    newForm.addEventListener('submit', function(e) {
        // Use the enhanced nested field collection if available
        let configs;
        if (typeof window.collectNestedFieldConfigurations === 'function') {
            configs = window.collectNestedFieldConfigurations();
        } else {
            // Fallback to basic collection
            configs = {};
            document.querySelectorAll('.section-card').forEach((card, index) => {
                const key = card.getAttribute('data-field-key');
                if (!key) return;
                
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
                
                // Handle radio fields with potential nested fields
                if (config.type === 'radio') {
                    const radioOptionsList = card.querySelector('.radio-options-list');
                    if (radioOptionsList) {
                        const options = [];
                        radioOptionsList.querySelectorAll('.radio-option-item').forEach(optionItem => {
                            const optionValue = optionItem.querySelector('.option-input').value.trim();
                            if (!optionValue) return;
                            
                            // Check if this option has a nested field panel
                            const nestedPanel = optionItem.querySelector('.nested-field-panel');
                            
                            if (nestedPanel && nestedPanel.style.display !== 'none') {
                                // Has nested field panel - collect nested fields
                                const nestedFieldItems = nestedPanel.querySelectorAll('.nested-field-item');
                                const nestedFields = [];
                                
                                nestedFieldItems.forEach(nestedFieldItem => {
                                    const nLabel = nestedFieldItem.querySelector('.nested-label')?.value.trim();
                                    const nTypeSelect = nestedFieldItem.querySelector('.nested-type-select');
                                    const nType = nTypeSelect ? nTypeSelect.value : '';
                                    const nRequired = nestedFieldItem.querySelector('.nested-required')?.checked || false;
                                    
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
                                            if (nOptions.length > 0) nField.options = nOptions;
                                        }
                                        
                                        nestedFields.push(nField);
                                    }
                                });
                                
                                if (nestedFields.length > 0) {
                                    options.push({
                                        value: optionValue,
                                        nested_fields: nestedFields
                                    });
                                } else {
                                    options.push(optionValue);
                                }
                            } else {
                                // No nested field panel
                                options.push(optionValue);
                            }
                        });
                        
                        if (options.length > 0) config.options = options;
                    }
                } else {
                    // Handle other field types normally
                    const optionList = card.querySelector('.option-list:not(.dimension-options)');
                    if (optionList) {
                        const options = [];
                        optionList.querySelectorAll('.option-input').forEach(input => {
                            if (input.value.trim()) options.push(input.value.trim());
                        });
                        if (options.length > 0) config.options = options;
                    }
                    
                    const dimensionList = card.querySelector('.dimension-options');
                    if (dimensionList) {
                        const dimensions = [];
                        dimensionList.querySelectorAll('.option-item').forEach(item => {
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
            });
        }
        
        // Debug: Log the collected configs to console
        console.log('Collected field configurations:', configs);
        
        document.getElementById('fieldConfigsInput').value = JSON.stringify(configs);
    });
});

// Override the addNewField function to handle nested fields
window.addNewField = function() {
    const label = document.getElementById('new-field-label').value.trim();
    const type = document.getElementById('new-field-type').value;
    const required = document.getElementById('new-field-required').checked;
    
    if (!label || !type) {
        alert('Please fill in all required fields');
        return;
    }
    
    const key = label.toLowerCase().replace(/[^a-z0-9]+/g, '_');
    if (window.fieldConfigurations[key]) {
        alert('A field with this name already exists');
        return;
    }
    
    const config = { label, type, required, visible: true, order: Object.keys(window.fieldConfigurations).length };
    
    if (type === 'select' || type === 'radio') {
        const options = [];
        const optionItems = document.querySelectorAll('#new-field-options-list .option-item');
        
        optionItems.forEach(optionItem => {
            const optionInput = optionItem.querySelector('.option-input');
            const optionValue = optionInput ? optionInput.value.trim() : '';
            
            if (!optionValue) return;
            
            // Check if this is a radio field with nested fields
            if (type === 'radio') {
                const nestedPanel = optionItem.nextElementSibling;
                
                if (nestedPanel && nestedPanel.classList.contains('nested-field-panel') && nestedPanel.style.display !== 'none') {
                    // Has nested field panel - collect nested fields
                    const nestedFieldItems = nestedPanel.querySelectorAll('.nested-field-item');
                    const nestedFields = [];
                    
                    nestedFieldItems.forEach(nestedFieldItem => {
                        const nLabel = nestedFieldItem.querySelector('.nested-label')?.value.trim();
                        const nTypeSelect = nestedFieldItem.querySelector('.nested-type-select');
                        const nType = nTypeSelect ? nTypeSelect.value : '';
                        const nRequired = nestedFieldItem.querySelector('.nested-required')?.checked || false;
                        
                        if (nLabel && nType) {
                            const nField = {
                                label: nLabel,
                                type: nType,
                                required: nRequired
                            };
                            
                            // Collect options for select/radio nested fields
                            if (['select', 'radio'].includes(nType)) {
                                const nOptions = [];
                                nestedFieldItem.querySelectorAll('.nested-option-input').forEach(nOptInput => {
                                    const nOptVal = nOptInput.value.trim();
                                    if (nOptVal) nOptions.push(nOptVal);
                                });
                                if (nOptions.length > 0) nField.options = nOptions;
                            }
                            
                            // Collect dimensions for dimension nested fields
                            if (nType === 'dimension') {
                                const nDimensions = [];
                                nestedFieldItem.querySelectorAll('.nested-dimension-list .option-item').forEach(dimItem => {
                                    const w = dimItem.querySelector('.nested-dimension-width')?.value.trim();
                                    const h = dimItem.querySelector('.nested-dimension-height')?.value.trim();
                                    if (w && h) nDimensions.push(w + '×' + h);
                                });
                                if (nDimensions.length > 0) nField.options = nDimensions;
                                
                                const allowOthersCheckbox = nestedFieldItem.querySelector('.nested-allow-others');
                                if (allowOthersCheckbox) {
                                    nField.allow_others = allowOthersCheckbox.checked;
                                }
                                
                                const unitSelect = nestedFieldItem.querySelector('.nested-unit-select');
                                nField.unit = unitSelect ? unitSelect.value : 'ft';
                            }
                            
                            nestedFields.push(nField);
                        }
                    });
                    
                    if (nestedFields.length > 0) {
                        options.push({
                            value: optionValue,
                            nested_fields: nestedFields
                        });
                    } else {
                        options.push(optionValue);
                    }
                } else {
                    // No nested fields
                    options.push(optionValue);
                }
            } else {
                // Select field - no nested support
                options.push(optionValue);
            }
        });
        
        if (options.length === 0) {
            alert('Please add at least one option');
            return;
        }
        config.options = options;
    }
    
    if (type === 'dimension') {
        const unit = document.getElementById('new-field-unit').value;
        const allowOthers = document.getElementById('new-field-allow-others').checked;
        const dimensions = [];
        document.querySelectorAll('#new-field-dimension-list .option-item').forEach(item => {
            const w = item.querySelector('.dimension-width').value.trim();
            const h = item.querySelector('.dimension-height').value.trim();
            if (w && h) dimensions.push(w + '×' + h);
        });
        if (dimensions.length === 0) {
            alert('Please add at least one dimension');
            return;
        }
        config.options = dimensions;
        config.unit = unit;
        config.allow_others = allowOthers;
    }
    
    // Debug log
    console.log('Adding new field with config:', config);
    
    window.fieldConfigurations[key] = config;
    document.getElementById('fieldConfigsInput').value = JSON.stringify(window.fieldConfigurations);
    document.getElementById('configForm').submit();
};