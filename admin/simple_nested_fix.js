// Simple nested field enhancement - just adds to existing functionality
console.log('Loading simple nested field enhancement...');

// Override addNewField to handle nested fields in Add Field modal
const originalAddNewField = window.addNewField;
window.addNewField = function() {
    console.log('Enhanced addNewField called');
    
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
            
            // For radio fields, check for nested fields
            if (type === 'radio') {
                const nestedPanel = optionItem.nextElementSibling;
                
                if (nestedPanel && nestedPanel.classList.contains('nested-field-panel') && nestedPanel.style.display !== 'none') {
                    // Collect nested fields
                    const nestedFields = [];
                    const nestedFieldItems = nestedPanel.querySelectorAll('.nested-field-item');
                    
                    nestedFieldItems.forEach(nestedFieldItem => {
                        const nLabel = nestedFieldItem.querySelector('.nested-label')?.value.trim();
                        const nTypeSelect = nestedFieldItem.querySelector('.nested-type-select');
                        const nType = nTypeSelect ? nTypeSelect.value : '';
                        const nRequired = nestedFieldItem.querySelector('.nested-required')?.checked || false;
                        
                        if (nLabel && nType) {
                            const nField = { label: nLabel, type: nType, required: nRequired };
                            
                            // Add options for select/radio nested fields
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
                        options.push({ value: optionValue, nested_fields: nestedFields });
                    } else {
                        options.push(optionValue);
                    }
                } else {
                    options.push(optionValue);
                }
            } else {
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
    
    console.log('New field config:', config);
    
    window.fieldConfigurations[key] = config;
    document.getElementById('fieldConfigsInput').value = JSON.stringify(window.fieldConfigurations);
    document.getElementById('configForm').submit();
};

console.log('Simple nested field enhancement loaded');