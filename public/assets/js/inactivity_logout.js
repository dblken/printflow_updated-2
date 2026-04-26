/**
 * Inactivity Logout - PrintFlow
 * Auto logout after 1 hour of inactivity with 55-min warning
 * Tracks: mousemove, click, keydown, scroll
 */
(function() {
    'use strict';

    var INACTIVITY_MS = 60 * 60 * 1000;      // 1 hour
    var WARNING_MS    = 55 * 60 * 1000;      // Show warning at 55 min
    var logoutUrl    = '/printflow/logout/';

    var lastActivity = Date.now();
    var timerId      = null;
    var warningShown = false;
    var modalEl      = null;

    function resetTimer() {
        lastActivity = Date.now();
        warningShown = false;
        if (modalEl) modalEl.style.display = 'none';
        if (timerId) clearTimeout(timerId);
        timerId = setTimeout(checkInactivity, WARNING_MS);
    }

    function showWarningModal() {
        if (modalEl) {
            modalEl.style.display = 'flex';
            return;
        }
        modalEl = document.createElement('div');
        modalEl.id = 'inactivity-warning-modal';
        modalEl.style.cssText = 'display:flex;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;padding:16px;';
        modalEl.innerHTML = '<div style="background:white;border-radius:16px;padding:32px;max-width:420px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.25);text-align:center;">' +
            '<div style="width:56px;height:56px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">' +
            '<svg width="28" height="28" fill="none" stroke="#d97706" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>' +
            '<h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0 0 8px;">Session Expiring Soon</h3>' +
            '<p style="font-size:14px;color:#6b7280;margin:0 0 24px;">Your session will expire in 5 minutes due to inactivity. Would you like to stay logged in?</p>' +
            '<div style="display:flex;gap:12px;">' +
            '<button id="inactivity-stay-btn" style="flex:1;padding:12px;background:#10b981;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Stay Logged In</button>' +
            '<a id="inactivity-logout-btn" href="' + logoutUrl + '" style="flex:1;padding:12px;background:#ef4444;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;">Logout Now</a>' +
            '</div></div>';
        document.body.appendChild(modalEl);

        document.getElementById('inactivity-stay-btn').addEventListener('click', function() {
            resetTimer();
        });
    }

    function doLogout() {
        if (modalEl) modalEl.style.display = 'none';
        modalEl = document.createElement('div');
        modalEl.id = 'inactivity-logged-out';
        modalEl.style.cssText = 'display:flex;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;padding:16px;';
        modalEl.innerHTML = '<div style="background:white;border-radius:16px;padding:32px;max-width:380px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.25);text-align:center;">' +
            '<p style="font-size:16px;color:#374151;margin:0 0 20px;">You have been logged out due to inactivity.</p>' +
            '<a href="/printflow/public/login.php" style="display:inline-block;padding:12px 24px;background:#3b82f6;color:white;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Return to Login</a></div>';
        document.body.appendChild(modalEl);
        window.location.href = '/printflow/public/login.php?timeout=1';
    }

    function checkInactivity() {
        var elapsed = Date.now() - lastActivity;
        if (elapsed >= WARNING_MS && !warningShown) {
            warningShown = true;
            showWarningModal();
            timerId = setTimeout(function() {
                if (Date.now() - lastActivity >= INACTIVITY_MS) {
                    doLogout();
                } else {
                    resetTimer();
                }
            }, 5 * 60 * 1000); // 5 min until actual logout
        } else if (elapsed >= INACTIVITY_MS) {
            doLogout();
        } else {
            timerId = setTimeout(checkInactivity, Math.min(WARNING_MS - elapsed, 60000));
        }
    }

    function onActivity() {
        lastActivity = Date.now();
        if (!warningShown) {
            if (timerId) clearTimeout(timerId);
            timerId = setTimeout(checkInactivity, WARNING_MS);
        }
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(evt) {
        document.addEventListener(evt, onActivity, { passive: true });
    });

    // Ping server periodically to refresh session (optional - backend handles this on requests)
    setInterval(function() {
        if (document.visibilityState === 'visible' && Date.now() - lastActivity < WARNING_MS) {
            fetch('/printflow/customer/api_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_count', csrf_token: (document.querySelector('[name="csrf_token"]') || {}).value || '' }),
                credentials: 'same-origin'
            }).catch(function() {});
        }
    }, 15 * 60 * 1000); // every 15 min when visible

    timerId = setTimeout(checkInactivity, WARNING_MS);
})();
