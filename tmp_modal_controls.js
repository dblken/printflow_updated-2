
// BLOCK 2: Modal UI Controls
console.log('Modal controls script loading...');

// Helper functions - must be defined first
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function populateParentDropdown(selectId, currentParentKey, currentFieldKey) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    const firstOption = select.options[0];
    select.innerHTML = '';
    select.appendChild(firstOption);
    
    for (const key in window.fieldConfigurations) {
        if (key === currentFieldKey) continue;
        
        const config = window.fieldConfigurations[key];
        if (config.type === 'radio' || config.type === 'select') {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = config.label + (key === 'branch' ? ' (Default)' : '');
            if (key === currentParentKey) option.selected = true;
            select.appendChild(option);
        }
    }
}

function populateTriggerValues(parentKey, group, select, currentVal) {
    if (!parentKey) {
        group.style.display = 'none';
        select.innerHTML = '<option value="">-- Select Value --</option>';
        return;
    }
    
    const parentConfig = window.fieldConfigurations[parentKey];
    if (!parentConfig) {
        group.style.display = 'none';
        return;
    }
    
    group.style.display = 'block';
    select.innerHTML = '<option value="">-- Select Value --</option>';
    
    let options = [];
    if (parentKey !== 'branch') {
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

window.showViewFieldModal = function showViewFieldModal(keyOrElem) {
    const key = (typeof keyOrElem === 'string') ? keyOrElem : keyOrElem.dataset.key;
    const config = window.fieldConfigurations[key];
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
        document.getElementById('view-field-parent').value = window.fieldConfigurations[config.parent_field_key] ? window.fieldConfigurations[config.parent_field_key].label : config.parent_field_key;
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
            optionsList.innerHTML = options.map(opt => `<div style="padding:4px 0;color:#374151;font-size:13px;">• ${escapeHtml(opt)}</div>`).join('');
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
            dimensionList.innerHTML = options.map(opt => `<div style="padding:4px 0;color:#374151;font-size:13px;">• ${escapeHtml(opt)}</div>`).join('');
        } else {
            dimensionList.innerHTML = '<div style="color:#9ca3af;font-size:13px;">No dimensions configured</div>';
        }
    }
    
    document.getElementById('viewFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

window.closeViewFieldModal = function closeViewFieldModal() {
    document.getElementById('viewFieldModal').classList.remove('active');
    document.body.style.overflow = '';
}

window.showEditFieldModal = function showEditFieldModal(keyOrElem) {
    console.log('showEditFieldModal called with:', keyOrElem);
    const key = (typeof keyOrElem === 'string') ? keyOrElem : (keyOrElem.dataset ? keyOrElem.dataset.key : keyOrElem.getAttribute('data-key'));
    const config = window.fieldConfigurations[key];
    if (!config) {
        alert('Field configuration not found');
        return;
    }
    
    // Populate form
    document.getElementById('edit-field-key').value = key;
    document.getElementById('edit-field-label').value = config.label;
    document.getElementById('edit-field-type').value = config.type;
    document.getElementById('edit-field-type-display').value = config.type.charAt(0).toUpperCase() + config.type.slice(1);
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
                    <span style="color: #cbd5e1; font-weight: bold;">×</span>
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
};

console.log('showEditFieldModal defined:', typeof window.showEditFieldModal);

window.closeEditFieldModal = function closeEditFieldModal() {
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
        <span style="color: #cbd5e1; font-weight: bold;">×</span>
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
    window.fieldConfigurations[key].label = label;
    window.fieldConfigurations[key].required = required;
    
    // Update label in table row
    const tableRow = document.querySelector('tr[onclick*="' + key + '"]');
    if (tableRow) {
        tableRow.querySelector('td:first-child').textContent = label;
        
        // Update required display in table
        const requiredCell = tableRow.querySelectorAll('td')[3];
        if (requiredCell) {
            requiredCell.innerHTML = required 
                ? '<span style="color:#059669;font-weight:600;font-size:12px;">✓ Yes</span>'
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
        
        window.fieldConfigurations[key].options = options;
    } else if (type === 'dimension') {
        const unit = document.getElementById('edit-field-unit').value;
        const allowOthers = document.getElementById('edit-field-allow-others').checked;
        
        const dimensionItems = document.querySelectorAll('#edit-field-dimension-list .option-item');
        const dimensions = [];
        dimensionItems.forEach(item => {
            const w = item.querySelector('.dimension-width').value.trim();
            const h = item.querySelector('.dimension-height').value.trim();
            if (w && h) {
                dimensions.push(w + '×' + h);
            }
        });
        
        if (dimensions.length === 0) {
            alert('Please enter at least one dimension option');
            return;
        }
        
        window.fieldConfigurations[key].unit = unit;
        window.fieldConfigurations[key].allow_others = allowOthers;
    }
    
    // Save Conditional Logic
    const parentKey = document.getElementById('edit-field-parent-key').value;
    const parentValue = document.getElementById('edit-field-parent-value').value;
    
    if (parentKey && parentValue) {
        window.fieldConfigurations[key].parent_field_key = parentKey;
        window.fieldConfigurations[key].parent_value = parentValue;
    } else {
        window.fieldConfigurations[key].parent_field_key = null;
        window.fieldConfigurations[key].parent_value = null;
    }
    
    closeEditFieldModal();
}

function toggleFieldRow(elem) {
    // Get the key from the element's data attribute
    const key = elem.getAttribute('data-key');
    if (key) {
        showViewFieldModal(key);
    }
}

function updateUnitDisplay(select) {
    // Not needed anymore
}

window.deleteField = function deleteField(btn) {
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

window.showAddFieldModal = function showAddFieldModal() {
    console.log('showAddFieldModal called');
    populateParentDropdown('new-field-parent-key', '', '');
    updateNewTriggerValues();
    document.getElementById('addFieldModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

console.log('showAddFieldModal defined:', typeof window.showAddFieldModal);

window.closeAddFieldModal = function closeAddFieldModal() {
    document.getElementById('addFieldModal').classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('new-field-label').value = '';
    document.getElementById('new-field-type').value = '';
    document.getElementById('new-field-required').checked = true;
    document.getElementById('new-field-options-section').style.display = 'none';
    document.getElementById('new-field-dimension-section').style.display = 'none';
    document.getElementById('new-field-unit').value = 'ft';
    document.getElementById('new-field-allow-others').checked = true;
    
    const pf_ParentKeySelect = document.getElementById('new-field-parent-key');
    if (pf_ParentKeySelect) pf_ParentKeySelect.value = '';
    const pf_TriggerValueGroup = document.getElementById('new-trigger-value-group');
    if (pf_TriggerValueGroup) pf_TriggerValueGroup.style.display = 'none';
    const pf_ParentValueSelect = document.getElementById('new-field-parent-value');
    if (pf_ParentValueSelect) {
        pf_ParentValueSelect.innerHTML = '<option value="">-- Select Value --</option>';
        pf_ParentValueSelect.value = '';
    }
    
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
    
    const dimensionList = document.getElementById('new-field-dimension-list');
    dimensionList.innerHTML = `
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
    `;
}

window.closeDeleteFieldModal = function closeDeleteFieldModal() {
    document.getElementById('deleteFieldModal').classList.remove('active');
    document.body.style.overflow = '';
}

window.confirmDeleteField = function confirmDeleteField() {
    const key = document.getElementById('delete-field-key').value;
    const row = document.querySelector('tr[onclick*="' + key + '"]');
    const detailRow = document.getElementById('field-detail-' + key);
    
    delete window.fieldConfigurations[key];
    
    if (row) row.remove();
    if (detailRow) detailRow.remove();
    
    closeDeleteFieldModal();
}

console.log('All modal functions loaded');

// ---- Inline-field helpers (used in existing-field detail rows) ----

function addOption(btn) {
    const fieldSection = btn.closest('.section-card') || btn.closest('.section-body') || btn.closest('td');
    const list = fieldSection ? fieldSection.querySelector('.option-list') : null;
    if (!list) return;
    const item = document.createElement('div');
    item.className = 'option-item';
    item.innerHTML = `
        <input type="text" class="option-input" value="" placeholder="Enter option">
        <button type="button" class="btn-remove" onclick="removeOption(this)">Remove</button>
    `;
    list.appendChild(item);
}

function removeOption(btn) {
    const list = btn.closest('.option-list');
    if (list && list.querySelectorAll('.option-item').length > 1) {
        btn.closest('.option-item').remove();
    } else {
        alert('At least one option is required.');
    }
}

function addDimensionOption(btn) {
    const list = btn.previousElementSibling;
    const item = document.createElement('div');
    item.className = 'option-item';
    item.style.cssText = 'display:flex; gap:8px; align-items:center;';
    item.innerHTML = `
        <input type="text" class="option-input dimension-w" value="" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex:1;text-align:center;" oninput="this.value=this.value.replace(/[^0-9]/g,''); checkDimensionDuplicates(this);">
        <span style="color:#cbd5e1;font-weight:bold;">×</span>
        <input type="text" class="option-input dimension-h" value="" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex:1;text-align:center;" oninput="this.value=this.value.replace(/[^0-9]/g,''); checkDimensionDuplicates(this);">
        <button type="button" class="btn-remove" onclick="removeDimensionOption(this)">Remove</button>
    `;
    list.appendChild(item);
}

function removeDimensionOption(btn) {
    btn.closest('.option-item').remove();
}

function checkDimensionDuplicates(input) {
    const list = input.closest('.option-list') || input.closest('.dimension-options');
    if (!list) return;
    const items = list.querySelectorAll('.option-item');
    const seen = {};
    items.forEach(item => {
        const w = (item.querySelector('.dimension-w') || item.querySelector('.dimension-width') || {}).value || '';
        const h = (item.querySelector('.dimension-h') || item.querySelector('.dimension-height') || {}).value || '';
        const key = w.trim() + 'x' + h.trim();
        if (w && h) {
            if (seen[key]) {
                item.style.background = '#fee2e2';
            } else {
                seen[key] = true;
                item.style.background = '';
            }
        } else {
            item.style.background = '';
        }
    });
}

function addNewDimensionOption() {
    const list = document.getElementById('new-field-dimension-list');
    if (!list) return;
    const item = document.createElement('div');
    item.className = 'option-item';
    item.style.cssText = 'display:flex; gap:8px; align-items:center;';
    item.innerHTML = `
        <input type="text" class="dimension-width" placeholder="Width" maxlength="2" pattern="[0-9]*" style="flex:1; padding:9px 12px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; text-align:center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        <span style="color:#cbd5e1; font-weight:bold;">×</span>
        <input type="text" class="dimension-height" placeholder="Height" maxlength="2" pattern="[0-9]*" style="flex:1; padding:9px 12px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; text-align:center;" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        <button type="button" class="btn-remove" onclick="removeNewDimensionOption(this)">Remove</button>
    `;
    list.appendChild(item);
}

