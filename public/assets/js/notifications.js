(function () {
    'use strict';

    /* ── Config ──────────────────────────────────────────────────────────── */
    var POLL_INTERVAL_MS       = 15000;
    var POLL_INTERVAL_HIDDEN   = 60000;
    var SW_PATH                = '/printflow/public/sw.js';
    var SW_SCOPE               = '/printflow/public/';
    var API_VAPID_PUB          = '/printflow/public/api/push/vapid_public_key.php';
    var API_SUBSCRIBE          = '/printflow/public/api/push/subscribe.php';
    var API_POLL               = '/printflow/public/api/push/poll.php';
    var API_LIST               = '/printflow/public/api/notifications/list.php';
    var SEEN_STORAGE_KEY       = 'pf_seen_notifications';
    var PERM_ASKED_KEY         = 'pf_notify_perm_asked';
    var BADGE_SELECTOR         = '#sidebar-notif-badge, #nav-notif-badge, [data-notif-badge]';

    var USER_TYPE = (window.PFConfig && window.PFConfig.userType) ? window.PFConfig.userType : 'Customer';

    var pollTimer   = null;
    var lastPollTs  = Math.floor(Date.now() / 1000) - 30;

    /* ── Export Early ────────────────────────────────────────────────────── */
    // Using simple var to ensure global access without modern scoping issues
    window.PFNotifications = {
        markSeen: markSeen,
        updateBadge: updateBadge,
        poll: poll,
        loadDropdown: loadDropdown
    };

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    function seenIds() {
        try {
            var data = sessionStorage.getItem(SEEN_STORAGE_KEY);
            return new Set(JSON.parse(data || '[]'));
        } catch (e) {
            return new Set();
        }
    }

    function markSeen(id) {
        var s = seenIds();
        s.add(String(id));
        var arr = [];
        s.forEach(function(val) { arr.push(val); });
        arr = arr.slice(-200);
        sessionStorage.setItem(SEEN_STORAGE_KEY, JSON.stringify(arr));
    }

    function urlB64ToUint8Array(base64String) {
        var pad = '='.repeat((4 - base64String.length % 4) % 4);
        var b64 = (base64String + pad).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(b64);
        var outputArray = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) {
            outputArray[i] = raw.charCodeAt(i);
        }
        return outputArray;
    }

    function updateBadge(count) {
        var els = document.querySelectorAll(BADGE_SELECTOR);
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : count;
                el.style.display = el.getAttribute('data-badge-display') || (el.id === 'nav-notif-badge' ? 'flex' : 'inline-flex');
                el.style.visibility = 'visible';
            } else {
                el.textContent = '';
                el.style.display = 'none';
                el.style.visibility = 'hidden';
            }
        }
    }

    function timeAgo(date) {
        if (!date) return 'just now';
        var d = new Date(date.replace(/-/g, '/'));
        var seconds = Math.floor((new Date() - d) / 1000);
        if (seconds < 60) return 'just now';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        var days = Math.floor(hours / 24);
        if (days < 7) return days + 'd ago';
        return d.toLocaleDateString();
    }

    function loadDropdown() {
        var lists = document.querySelectorAll('[data-pf-notif-list]');
        if (lists.length === 0) return;

        fetch(API_LIST + '?limit=8', { credentials: 'include' })
            .then(function(res) {
                if (!res.ok) throw new Error('Response ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (!data.success) {
                    for (var i = 0; i < lists.length; i++) lists[i].innerHTML = '<div class="pf-notif-empty">' + escHtml(data.error || 'Failed to load.') + '</div>';
                    return;
                }

                if (!data.notifications || data.notifications.length === 0) {
                    for (var i = 0; i < lists.length; i++) lists[i].innerHTML = '<div class="pf-notif-empty">No notifications yet.</div>';
                    updateBadge(0);
                    return;
                }

                updateBadge(data.unread_count || 0);

                var html = '';
                for (var j = 0; j < data.notifications.length; j++) {
                    var n = data.notifications[j];
                    var target = n.target_link || getNotifUrl(n.type, n.data_id, n.message, n.id, (n.order_type || ''));
                    var unreadClass = n.is_read == 0 ? 'unread' : '';
                    
                    var iconHtml = '<div class="pf-notif-item-icon default"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-14v4m0 0l-8-4m8 4v10l-8-4m0-10L4 7m8 4L4 7"/></svg></div>';

                    var imageSrc = n.display_image || n.fallback_image || '';
                    if (imageSrc) {
                        var fallbackSrc = n.fallback_image || '';
                        var fallback = fallbackSrc
                            ? 'this.onerror=null;this.src=&quot;' + escHtml(fallbackSrc) + '&quot;;'
                            : 'this.onerror=null;';
                        iconHtml = '<div class="pf-notif-item-icon" style="background:transparent; padding:0; border:1px solid rgba(83,197,224,0.15);"><img src="' + escHtml(imageSrc) + '" style="width:100%; height:100%; object-fit:cover; border-radius:6px;" alt="" onerror="' + fallback + '"></div>';
                    }

                    html += '<a href="' + target + '" class="pf-notif-item ' + unreadClass + '">' +
                        iconHtml +
                        '<div class="pf-notif-item-content">' +
                            (n.display_name ? '<div style="font-size:0.75rem; font-weight:800; color:#53c5e0; margin-bottom:2px;">' + escHtml(n.display_name) + '</div>' : '') +
                            '<div class="pf-notif-item-text">' + escHtml(n.message) + '</div>' +
                            '<div class="pf-notif-item-time">' + escHtml(n.time_ago || timeAgo(n.created_at)) + '</div>' +
                        '</div>' +
                    '</a>';
                }
                for (var k = 0; k < lists.length; k++) lists[k].innerHTML = html;
            })
            .catch(function(err) {
                for (var i = 0; i < lists.length; i++) lists[i].innerHTML = '<div class="pf-notif-empty">Error: ' + escHtml(err.message) + '</div>';
            });
    }

    function getNotifUrl(type, dataId, message, notifId, orderType) {
        var base = '/printflow';
        var t = (type || '').toLowerCase();
        var isStaff = (USER_TYPE.toLowerCase() === 'admin' || USER_TYPE.toLowerCase() === 'staff' || USER_TYPE.toLowerCase() === 'manager');
        var msg = (message || '').toLowerCase();
        var did = (dataId != null && dataId !== '') ? parseInt(dataId, 10) : 0;
        var url = base + '/';

        if (isStaff && t === 'system' && did > 0 && (msg.indexOf('ready for admin review') !== -1 || msg.indexOf('completed their profile') !== -1)) {
            url = base + '/admin/user_staff_management.php?open_user=' + did;
        } else if (isStaff) {
            if (t.indexOf('inventory') !== -1) url = base + '/admin/inv_items_management.php';
            else if (t.indexOf('order') !== -1 || t.indexOf('job') !== -1 || t.indexOf('design') !== -1 || t.indexOf('custom') !== -1) {
                var oType = (orderType || '').toLowerCase();
                if (oType === 'custom' || t.indexOf('job') !== -1 || t.indexOf('custom') !== -1) {
                    url = base + '/staff/customizations.php?order_id=' + did + '&job_type=ORDER';
                } else {
                    url = base + '/staff/orders.php?order_id=' + did;
                }
            }
            else if (t.indexOf('chat') !== -1 || t.indexOf('message') !== -1) url = did ? base + '/staff/orders.php?order_id=' + did : base + '/staff/orders.php';
            else url = base + '/staff/dashboard.php';
        } else {
            if (t.indexOf('order') !== -1 || t.indexOf('status') !== -1) url = base + '/customer/orders.php?highlight=' + did;
            else if (t.indexOf('payment') !== -1) url = base + '/customer/payment.php?order_id=' + did;
            else if (t.indexOf('job') !== -1) url = base + '/customer/new_job_order.php';
            else if (t.indexOf('chat') !== -1 || t.indexOf('message') !== -1) url = did ? base + '/customer/chat.php?order_id=' + did : base + '/customer/messages.php';
            else if ((t.indexOf('design') !== -1 || t.indexOf('custom') !== -1) && did) url = base + '/customer/chat.php?order_id=' + did;
            else if (t.indexOf('review') !== -1 || t.indexOf('rating') !== -1) url = did ? base + '/customer/orders.php?highlight=' + did : base + '/customer/orders.php?tab=completed';
            else url = base + '/customer/notifications.php';
        }

        if (notifId) {
            url += (url.indexOf('?') !== -1 ? '&' : '?') + 'mark_read=' + notifId;
        }
        return url;
    }

    /* ── Polling ─────────────────────────────────────────────────────────── */

    function poll() {
        var url = API_POLL + '?since=' + lastPollTs;
        fetch(url, { credentials: 'include' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) return;
                updateBadge(data.unread_count || 0);
                if (data.server_time) lastPollTs = data.server_time;

                var seen = seenIds();
                var notifs = data.notifications || [];
                for (var i = 0; i < notifs.length; i++) {
                    var n = notifs[i];
                    var sid = String(n.id);
                    if (seen.has(sid)) continue;
                    markSeen(sid);
                    var targetUrl = getNotifUrl(n.type, n.data_id, n.message, n.id, n.order_type);
                    if (window.location.pathname + window.location.search === targetUrl) continue;
                    showToast('PrintFlow', n.message, targetUrl);
                }
            })
            .catch(function(){});
    }

    function schedulePoll() {
        clearTimeout(pollTimer);
        var delay = document.hidden ? POLL_INTERVAL_HIDDEN : POLL_INTERVAL_MS;
        pollTimer = setTimeout(function() { poll(); schedulePoll(); }, delay);
    }

    function showToast(title, body, url) {
        var container = document.getElementById('pf-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pf-toast-container';
            container.style.position = 'fixed';
            container.style.bottom = '24px';
            container.style.right = '24px';
            container.style.zIndex = '99999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            container.style.maxWidth = '340px';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.style.background = '#ffffff';
        toast.style.border = '1px solid #e5e7eb';
        toast.style.borderLeft = '4px solid #f97316';
        toast.style.borderRadius = '8px';
        toast.style.boxShadow = '0 4px 16px rgba(0,0,0,.12)';
        toast.style.padding = '12px 16px';
        toast.style.cursor = url ? 'pointer' : 'default';
        toast.style.display = 'flex';
        toast.style.alignItems = 'flex-start';
        toast.style.gap = '10px';

        var icon = document.createElement('img');
        icon.src = '/printflow/public/assets/images/icon-72.png';
        icon.style.width = '32px';
        icon.style.height = '32px';
        icon.style.borderRadius = '6px';
        icon.style.flexShrink = '0';

        var text = document.createElement('div');
        text.innerHTML = '<div style="font-weight:600;font-size:.875rem;color:#111827;margin-bottom:2px">' + escHtml(title) + '</div>' +
                         '<div style="font-size:.8125rem;color:#6b7280;line-height:1.4">' + escHtml(body) + '</div>';

        var close = document.createElement('button');
        close.style.marginLeft = 'auto';
        close.style.background = 'none';
        close.style.border = 'none';
        close.style.cursor = 'pointer';
        close.style.color = '#9ca3af';
        close.style.fontSize = '1rem';
        close.style.padding = '0 0 0 8px';
        close.style.flexShrink = '0';
        close.innerHTML = '&times;';
        close.onclick = function(e) { e.stopPropagation(); toast.remove(); };

        toast.appendChild(icon);
        toast.appendChild(text);
        toast.appendChild(close);
        container.appendChild(toast);

        if (url) toast.onclick = function() { window.location.href = url; };
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 6000);
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function init() {
        poll();
        schedulePoll();
    }



    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

})();
