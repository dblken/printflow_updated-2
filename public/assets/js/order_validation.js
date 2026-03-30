document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const buyNowBtn = form.querySelector('button[name="buy_now"], button[value="Buy Now"], button[type="submit"]');
        if (!buyNowBtn) return;
        
        form.setAttribute('novalidate', 'novalidate');
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            let firstInvalidField = null;
            
            clearAllErrors(form);

            const requiredElements = form.querySelectorAll('input[required], select[required], textarea[required]');
            const processedGroups = new Set();
            
            requiredElements.forEach(el => {
                if (el.disabled || (el.type !== 'hidden' && el.offsetParent === null)) return;
                
                let isEmpty = false;
                if (el.type === 'radio' || el.type === 'checkbox') {
                    const name = el.name;
                    if (!name) {
                        if (!el.checked) isEmpty = true;
                    } else {
                        if (processedGroups.has(name)) return;
                        processedGroups.add(name);
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (!checked) isEmpty = true;
                    }
                } else if (el.type === 'file') {
                    if (el.files.length === 0) isEmpty = true;
                } else {
                    if (!el.value || !el.value.trim()) isEmpty = true;
                }
                
                if (isEmpty) {
                    isValid = false;
                    if (!firstInvalidField) firstInvalidField = el;
                    showError(el, getFieldName(el, form) + ' is required.');
                }
            });
            
            const customValidationEvent = new CustomEvent('customOrderValidation', {
                cancelable: true,
                detail: { form: form, showError: showError }
            });
            
            if (!form.dispatchEvent(customValidationEvent)) {
                isValid = false;
                if (!firstInvalidField) {
                    const firstError = form.querySelector('.validation-highlight-error');
                    if (firstError) firstInvalidField = firstError;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                if (firstInvalidField) {
                    const rect = firstInvalidField.getBoundingClientRect();
                    const absoluteY = window.pageYOffset + rect.top;
                    window.scrollTo({ top: absoluteY - 150, behavior: 'smooth' });
                    if (typeof firstInvalidField.focus === 'function' && firstInvalidField.type !== 'hidden') {
                        setTimeout(() => firstInvalidField.focus(), 500);
                    }
                }
            }
        }, true);

        // Clear error on input/change
        form.addEventListener('input', function(e) {
            clearErrorOnElement(e.target);
        });
        form.addEventListener('change', function(e) {
            clearErrorOnElement(e.target);
            if (e.target.type === 'radio') {
                const name = e.target.name;
                form.querySelectorAll(`input[name="${name}"]`).forEach(r => clearErrorOnElement(r));
            }
        });
    });

    function getFieldName(el, form) {
        let fieldName = 'This field';
        let label = null;
        if (el.id) label = form.querySelector(`label[for="${el.id}"]`);
        if (!label) label = el.closest('label') || (el.parentElement ? el.parentElement.querySelector('label') : null);
        if (label) {
            fieldName = label.innerText.replace(/\*/g, '').replace(/ⓘ/g, '').replace(/i$/, '').replace(/\(.*\)/g, '').trim();
        } else if (el.placeholder) {
            fieldName = el.placeholder;
        } else if (el.name) {
            fieldName = el.name.replace(/_/g, ' ').charAt(0).toUpperCase() + el.name.replace(/_/g, ' ').slice(1);
        }
        return fieldName;
    }

    function showError(el, message) {
        if (!el) return;
        
        const container = getErrorContainer(el);
        el.classList.add('validation-highlight-error');
        container.classList.add('validation-error-container');

        const errMsgId = el.name || el.id || 'err-' + Math.floor(Math.random() * 1000);
        const existingMsg = container.parentNode.querySelector(`.custom-validation-error[data-for="${errMsgId}"]`);
        if (existingMsg) existingMsg.remove();
        
        const errorMsg = document.createElement('div');
        errorMsg.className = 'custom-validation-error';
        errorMsg.dataset.for = errMsgId;
        errorMsg.innerHTML = `<span class="error-icon">!</span> ${message}`;
        
        container.parentNode.insertBefore(errorMsg, container.nextSibling);
        
        requestAnimationFrame(() => {
            errorMsg.classList.add('show');
        });
    }

    window.showOrderValidationError = function(el, message) {
        showError(el, message);
    };

    function getErrorContainer(el) {
        return el.closest('.opt-btn-group') || el.closest('.option-grid') || el.closest('.qty-control') || el.closest('.file-input-wrap') || el.closest('.file-input-container') || el;
    }

    function clearErrorOnElement(el) {
        if (!el) return;
        el.classList.remove('validation-highlight-error');
        const container = getErrorContainer(el);
        container.classList.remove('validation-error-container');
        
        const name = el.name || el.id;
        const msg = container.parentNode.querySelector(`.custom-validation-error[data-for="${name}"]`);
        if (msg) {
            msg.classList.remove('show');
            setTimeout(() => { if (msg && msg.parentNode) msg.remove(); }, 300);
        }
    }

    function clearAllErrors(form) {
        form.querySelectorAll('.custom-validation-error').forEach(msg => { if (msg.parentNode) msg.remove(); });
        form.querySelectorAll('.validation-highlight-error').forEach(el => el.classList.remove('validation-highlight-error'));
        form.querySelectorAll('.validation-error-container').forEach(el => el.classList.remove('validation-error-container'));
    }

    // Inject styles
    if (!document.getElementById('order-validation-enhanced-styles')) {
        const style = document.createElement('style');
        style.id = 'order-validation-enhanced-styles';
        style.innerHTML = `
            /* Suppress ALL browser-native :invalid styling so fields look normal on load */
            input:invalid,
            select:invalid,
            textarea:invalid,
            input:required:invalid,
            select:required:invalid,
            textarea:required:invalid {
                box-shadow: none !important;
                outline: none !important;
                border-color: inherit !important;
                background-color: inherit !important;
            }

            .validation-highlight-error {
                border-color: #ef4444 !important;
                background-color: #fef2f2 !important;
                box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.2) !important;
                transition: all 0.25s ease-in-out;
            }
            .opt-btn-group.validation-highlight-error .opt-btn-wrap,
            .opt-btn-group.validation-highlight-error .opt-btn,
            .option-grid.validation-highlight-error .opt-btn-wrap,
            .option-grid.validation-highlight-error .placement-card {
                border-color: #ef4444 !important;
                background-color: #fef2f2 !important;
            }

            .validation-error-container {
                position: relative;
            }
            .custom-validation-error {
                color: #dc2626;
                font-size: 0.75rem;
                margin-top: 0.45rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 0.45rem;
                opacity: 0;
                transform: translateY(-8px);
                transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                pointer-events: none;
            }
            .custom-validation-error.show {
                opacity: 1;
                transform: translateY(0);
            }
            .error-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 14px;
                height: 14px;
                background: #dc2626;
                color: #ffffff;
                border-radius: 50%;
                font-size: 10px;
                font-weight: 900;
                line-height: 1;
                flex-shrink: 0;
            }
            textarea.validation-highlight-error {
                background-color: #fef2f2 !important;
            }
            
            textarea[name*="notes"], .notes-textarea, .tarp-notes, .refl-notes-area {
                min-height: 80px !important;
                max-height: 400px !important;
                overflow-y: auto !important;
                word-wrap: break-word !important;
                line-height: 1.5 !important;
                resize: vertical !important;
                box-sizing: border-box !important;
                width: 100% !important;
                display: block !important;
            }
        `;
        document.head.appendChild(style);
    }
});
