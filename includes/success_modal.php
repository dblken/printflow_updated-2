<?php
/**
 * Success Modal Component
 * Reusable modal for successful actions (Order placed, updated, etc.)
 */
?>
<style>
    .pf-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 5, 10, 0.65);
        backdrop-filter: blur(8px);
        z-index: 30000;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .pf-modal-overlay.active {
        display: flex;
        opacity: 1;
    }
    .pf-modal-card {
        background: #081d26; /* Solid dark color to support the SVG masking */
        background-image: linear-gradient(165deg, rgba(10, 37, 48, 0.98), rgba(7, 26, 34, 1));
        border-radius: 1.5rem;
        width: 100%;
        max-width: 420px;
        padding: 2.5rem 2rem;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        transform: scale(0.9);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        border: 1px solid rgba(83, 197, 224, 0.2);
    }
    .pf-modal-overlay.active .pf-modal-card {
        transform: scale(1);
    }
    
    /* Checkmark Animation */
    .pf-success-checkmark {
        width: 80px;
        height: 80px;
        margin: 0 auto 1.5rem;
        position: relative;
    }
    .pf-check-icon {
        width: 80px;
        height: 80px;
        position: relative;
        border-radius: 50%;
        box-sizing: content-box;
        border: 4px solid #10B981;
    }
    .pf-check-icon::before {
        top: 3px;
        left: -2px;
        width: 30px;
        transform-origin: 100% 50%;
        border-radius: 100px 0 0 100px;
    }
    .pf-check-icon::after {
        top: 0;
        left: 30px;
        width: 60px;
        transform-origin: 0 50%;
        border-radius: 0 100px 100px 0;
        animation: rotate-circle 4.25s ease-in;
    }
    .pf-check-icon::before, .pf-check-icon::after {
        content: '';
        height: 100px;
        position: absolute;
        background: #081d26; /* Masks the animation over the dark background */
        transform: rotate(-45deg);
    }
    .pf-icon-line {
        height: 5px;
        background-color: #10B981;
        display: block;
        border-radius: 2px;
        position: absolute;
        z-index: 10;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
    }
    .pf-icon-line.line-tip {
        top: 46px;
        left: 14px;
        width: 25px;
        transform: rotate(45deg);
        animation: icon-line-tip 0.75s;
    }
    .pf-icon-line.line-long {
        top: 38px;
        right: 8px;
        width: 47px;
        transform: rotate(-45deg);
        animation: icon-line-long 0.75s;
    }
    .pf-icon-circle {
        top: -4px;
        left: -4px;
        z-index: 10;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        position: absolute;
        box-sizing: content-box;
        border: 4px solid rgba(16, 185, 129, 0.2);
    }
    .pf-icon-fix {
        top: 8px;
        width: 5px;
        left: 26px;
        z-index: 1;
        height: 85px;
        position: absolute;
        transform: rotate(-45deg);
        background-color: #081d26; /* Masks the animation over the dark background */
    }

    @keyframes rotate-circle {
        0% { transform: rotate(-45deg); }
        5% { transform: rotate(-45deg); }
        12% { transform: rotate(-405deg); }
        100% { transform: rotate(-405deg); }
    }
    @keyframes icon-line-tip {
        0% { width: 0; left: 1px; top: 19px; }
        54% { width: 0; left: 1px; top: 19px; }
        70% { width: 50px; left: -8px; top: 37px; }
        84% { width: 17px; left: 21px; top: 48px; }
        100% { width: 25px; left: 14px; top: 46px; }
    }
    @keyframes icon-line-long {
        0% { width: 0; right: 46px; top: 54px; }
        65% { width: 0; right: 46px; top: 54px; }
        84% { width: 55px; right: 0px; top: 35px; }
        100% { width: 47px; right: 8px; top: 38px; }
    }

    .pf-modal-title {
        font-size: 1.65rem;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 0.75rem;
        letter-spacing: -0.01em;
    }
    .pf-modal-message {
        color: #a6e7f6;
        line-height: 1.6;
        margin-bottom: 2rem;
        font-size: 0.95rem;
        font-weight: 500;
    }
    .pf-modal-actions {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .pf-modal-btn {
        padding: 0.95rem 1.5rem;
        border-radius: 0.85rem;
        font-weight: 800;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .pf-modal-btn-primary {
        background: linear-gradient(135deg, #53C5E0, #32a1c4);
        color: #ffffff;
        box-shadow: 0 6px 16px rgba(50, 161, 196, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .pf-modal-btn-primary:hover {
        background: linear-gradient(135deg, #32a1c4, #2788a8);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(50, 161, 196, 0.35);
    }
    .pf-modal-btn-secondary {
        background: rgba(10, 37, 48, 0.6);
        color: #e2e8f0;
        border: 1px solid rgba(83, 197, 224, 0.25);
    }
    .pf-modal-btn-secondary:hover {
        background: rgba(15, 54, 70, 0.9);
        border-color: #53c5e0;
        color: #ffffff;
    }
</style>

<div id="pfSuccessModal" class="pf-modal-overlay">
    <div class="pf-modal-card">
        <div class="pf-success-checkmark">
            <div class="pf-check-icon">
                <span class="pf-icon-line line-tip"></span>
                <span class="pf-icon-line line-long"></span>
                <div class="pf-icon-circle"></div>
                <div class="pf-icon-fix"></div>
            </div>
        </div>
        <h2 id="pfModalTitle" class="pf-modal-title">Success!</h2>
        <p id="pfModalMessage" class="pf-modal-message">Your action was completed successfully.</p>
        
        <div class="pf-modal-actions">
            <a id="pfModalPrimaryBtn" href="#" class="pf-modal-btn pf-modal-btn-primary">View Results</a>
            <a id="pfModalSecondaryBtn" href="#" class="pf-modal-btn pf-modal-btn-secondary">Go to Dashboard</a>
        </div>
    </div>
</div>

<script>
function showSuccessModal(title, message, primaryUrl, secondaryUrl, primaryText = 'View Details', secondaryText = 'Go to Dashboard', autoRedirectUrl = null, autoRedirectDelay = 3000) {
    const modal = document.getElementById('pfSuccessModal');
    const titleEl = document.getElementById('pfModalTitle');
    const messageEl = document.getElementById('pfModalMessage');
    const primaryBtn = document.getElementById('pfModalPrimaryBtn');
    const secondaryBtn = document.getElementById('pfModalSecondaryBtn');

    titleEl.textContent = title;
    messageEl.innerHTML = message;
    primaryBtn.href = primaryUrl;
    primaryBtn.textContent = primaryText;
    secondaryBtn.href = secondaryUrl;
    secondaryBtn.textContent = secondaryText;

    // Handle Close behavior if URL is '#'
    primaryBtn.onclick = (e) => {
        if (primaryUrl === '#') {
            e.preventDefault();
            hideSuccessModal();
        }
    };
    secondaryBtn.onclick = (e) => {
        if (secondaryUrl === '#') {
            e.preventDefault();
            hideSuccessModal();
        }
    };

    modal.classList.add('active');

    // Auto-redirect if requested
    if (autoRedirectUrl) {
        setTimeout(() => {
            hideSuccessModal();
            setTimeout(() => {
                window.location.href = autoRedirectUrl;
            }, 300); // Wait for fade out animation
        }, autoRedirectDelay);
    }
}

function hideSuccessModal() {
    document.getElementById('pfSuccessModal').classList.remove('active');
}

// Auto-show if session variables are set (using script injection in the page that needs it)
</script>
