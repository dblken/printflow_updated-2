/**
 * PrintFlow WebRTC Call System v3.3 (Stable Fix)
 * ============================================
 */
if (window.__PF_CALL_LOADED__) {
    console.warn('[PFCall] Script already loaded, skipping execution.');
} else {
    window.__PF_CALL_LOADED__ = true;

'use strict';

// ─── CONSTANTS ────────────────────────────────────────────────────────────────
const PF_STATE = Object.freeze({
    IDLE:     'idle',
    CALLING:  'calling',
    INCOMING: 'incoming',
    IN_CALL:  'in-call',
    ENDED:    'ended'
});

const PF_ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' }
];

// ─── AUDIO MANAGER ────────────────────────────────────────────────────────────
class PFAudio {
    constructor() {
        this._el  = null;
        this._ctx = null;
        this._osc = null;
    }

    play(basePath) {
        console.log('[PFCall][Audio] Starting ringtone');
        if (!this._el) {
            this._el = document.createElement('audio');
            this._el.loop = true;
            this._el.style.display = 'none';
            document.body.appendChild(this._el);
        }
        this._el.src = 'https://www.soundjay.com/phone/sounds/phone-ringing-1.mp3'; // High reliability CDN
        this._el.play().catch(() => {
            // Internal Local Fallback (if user added it later)
            this._el.src = `${basePath}/public/assets/audio/ringtone.mp3`;
            this._el.play().catch(() => this._beep());
        });
    }

    _beep() {
        if (this._osc) return;
        try {
            this._ctx = new (window.AudioContext || window.webkitAudioContext)();
            const tick = () => {
                const osc  = this._ctx.createOscillator();
                const gain = this._ctx.createGain();
                osc.frequency.value = 480;
                gain.gain.setValueAtTime(0.05, this._ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, this._ctx.currentTime + 0.4);
                osc.connect(gain); gain.connect(this._ctx.destination);
                osc.start(); osc.stop(this._ctx.currentTime + 0.4);
            };
            tick();
            this._osc = setInterval(tick, 2000);
        } catch (e) {}
    }

    stop() {
        if (this._el) { this._el.pause(); this._el.src = ''; }
        if (this._osc) { clearInterval(this._osc); this._osc = null; }
        try { if (this._ctx) { this._ctx.close(); this._ctx = null; } } catch (e) {}
    }
}

// ─── CALL MANAGER ─────────────────────────────────────────────────────────────
class PFCallManager {

    constructor() {
        // ── Identity ──
        this.userId      = null;
        this.userType    = null;
        this.userName    = '';
        this.userAvatar  = '';
        this.basePath    = '/printflow';

        // ── State ──
        this.state       = PF_STATE.IDLE;

        // ── Active call ──
        this.partnerId   = null;
        this.partnerType = null;
        this.callType    = 'voice'; // 'voice' | 'video'
        this.isInitiator = false;

        // ── Socket ──
        this.socket      = null;
        this._connectTs  = 0;
        this._connectErrorCount = 0;
        this._connectErrorHinted = false;

        // ── WebRTC ──
        this.pc          = null;
        this.localStream = null;
        this.iceQueue    = [];

        // ── Timer ──
        this._timerInt   = null;
        this._timerStart = 0;

        // ── Duration (seconds elapsed in-call) ──
        this._callDuration = 0;

        // ── No-answer timeout ──
        this._noAnswerTimeout = null;

        // ── Signal monitor ──
        this._signalInt   = null;
        this._signalLevel = 'good'; // 'good' | 'poor' | 'bad'

        // ── Audio ──
        this.audio       = new PFAudio();

        // ── UI flag ──
        this._uiReady    = false;
        this.isSocketConnected = false;
        this._pendingIncoming = null; 
        this._isMediaLoading  = false; // Lock for media acquisition

        // ── Notifications ──
        this._notification = null;
        this._notifRetryInt = null;
        this._tabActive = true;

        this._initVisibilityTracker();
    }

    _initVisibilityTracker() {
        document.addEventListener('visibilitychange', () => {
            this._tabActive = (document.visibilityState === 'visible');
            if (this._tabActive && this._notification) {
                this._notification.close();
                this._notification = null;
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════
    // CALL EVENT LOGGING
    // ══════════════════════════════════════════════════════════════════

    /**
     * Post a call event system message to the active chat.
     * @param {'missed'|'ended'|'declined'|'busy'|'no_answer'} eventType
     * @param {number} duration - seconds (0 if not answered)
     */
    _postCallEvent(eventType, duration = 0) {
        const orderId = (window.PFCallState && window.PFCallState.activeId)
            || (typeof activeId !== 'undefined' ? activeId : null);
        if (!orderId) return;

        const fd = new FormData();
        const cid = (this.isInitiator ? this.userId : this.partnerId) || 0;
        const ctype = (this.isInitiator ? this.userType : this.partnerType) || '';

        fd.append('order_id',    orderId);
        fd.append('event_type',  eventType);
        fd.append('call_type',   this.callType || 'voice');
        fd.append('duration',    duration);
        fd.append('caller_id',   cid);
        fd.append('caller_type', ctype);

        console.log(`[PFCall] Logging event: ${eventType}, Caller: ${ctype} (${cid})`);

        fetch(`${this.basePath}/public/api/chat/send_call_event.php`, {
            method: 'POST', body: fd
        }).catch(() => {});
    }

    // ══════════════════════════════════════════════════════════════════
    // SIGNAL QUALITY MONITOR
    // ══════════════════════════════════════════════════════════════════

    _setupSignalMonitor() {
        this._stopSignalMonitor();
        if (!this.pc) return;
        this._signalInt = setInterval(async () => {
            if (!this.pc || this.state !== PF_STATE.IN_CALL) return;
            try {
                const stats = await this.pc.getStats();
                let rtt = null;
                stats.forEach(report => {
                    if (report.type === 'candidate-pair' && report.state === 'succeeded' && report.currentRoundTripTime !== undefined) {
                        rtt = report.currentRoundTripTime * 1000; // ms
                    }
                });
                let level = 'good';
                if (rtt !== null) {
                    if (rtt > 300) level = 'bad';
                    else if (rtt > 150) level = 'poor';
                }
                if (level !== this._signalLevel) {
                    this._signalLevel = level;
                    this._updateSignalUI(level);
                }
            } catch(e) {}
        }, 4000);
    }

    _stopSignalMonitor() {
        if (this._signalInt) { clearInterval(this._signalInt); this._signalInt = null; }
    }

    _updateSignalUI(level) {
        const el = this.$('pf-signal-indicator');
        if (!el) return;
        el.className = `pf-signal-indicator pf-signal-${level}`;
        const labels = { good: '', poor: 'Poor connection', bad: 'Weak signal' };
        el.textContent = labels[level] || '';
        el.style.display = level === 'good' ? 'none' : 'flex';
    }

    // ══════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ══════════════════════════════════════════════════════════════════

    /**
     * Call once after DOMContentLoaded. Sets up identity, UI, and socket.
     */
    initialize(userId, userType, userName, userAvatar, basePath) {
        // If already initialized, we STILL need to rebuild the UI if it was removed by Turbo/logout
        if (window.__PF_CALL_INIT__) {
            console.log('[PFCall] Already initialized, verifying UI existence...');
            this.userId     = userId     || this.userId;
            this.userType   = userType   || this.userType;
            this.userName   = userName   || this.userName;
            this.userAvatar = userAvatar || this.userAvatar;
            this.basePath   = (basePath  || this.basePath).replace(/\/$/, '');
            this._ensureUI(); 
            return;
        }
        window.__PF_CALL_INIT__ = true;

        console.log(`[PFCall] initialize() — userId=${userId} userType=${userType}`);

        if (!userId || !userType) {
            console.error('[PFCall] initialize() missing userId or userType — aborting');
            return;
        }

        this.userId    = userId;
        this.userType  = userType;
        this.userName  = userName  || 'User';
        this.userAvatar= userAvatar|| '';
        this.basePath  = (basePath || '/printflow').replace(/\/$/, '');

        this._buildUI();
        this._connectSocket();
        this._initPush();
        
        // Request notification permission early
        if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Mark as ready immediately after essential init
        window.PFCallReady = true;
        document.dispatchEvent(new Event("PFCallGlobalReady"));
        window.dispatchEvent(new Event("PFCallGlobalReady"));

        console.log(`[PFCall] Initializing identity — ${userType} #${userId} (${userName})`);
    }

    async _initPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('[PFCall][Push] Browser does not support push notifications');
            return;
        }

        try {
            // Register Service Worker
            const registration = await navigator.serviceWorker.register(`${this.basePath}/public/sw.js`);
            console.log('[PFCall][Push] SW registered:', registration.scope);

            // Request Notification Permission on first load or if not granted
            if (Notification.permission === 'default') {
                // We don't force prompt immediately, but we can if we want
                // Notification.requestPermission();
            }

            if (Notification.permission === 'granted') {
                this._subscribeToPush(registration);
            }
        } catch (err) {
            console.error('[PFCall][Push] SW registration failed:', err);
        }
    }

    async _subscribeToPush(registration) {
        try {
            // Get VAPID public key
            const resp = await fetch(`${this.basePath}/public/api/push/vapid_public_key.php`);
            const { public_key } = await resp.json();
            if (!public_key) return;

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this._urlBase64ToUint8Array(public_key)
            });

            console.log('[PFCall][Push] Subscribed successfully');

            // Send to server
            await fetch(`${this.basePath}/public/api/push/subscribe.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''),
                        auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
                    }
                })
            });
        } catch (err) {
            console.error('[PFCall][Push] Subscription failed:', err);
        }
    }

    _urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * Initiate an outbound call.
     * Called ONLY from a button click handler.
     * @param {number|string} targetId   - receiver user ID
     * @param {string}        targetType - 'Staff' | 'Customer'
     * @param {string}        targetName
     * @param {string}        targetAvatar
     * @param {string}        type       - 'voice' | 'video'
     */
    async startCall(targetId, targetType, targetName, targetAvatar, type) {
        console.log(`[PFCall] startCall() clicked — target=${targetId}(${targetType}) type=${type}`);

        if (this.state !== PF_STATE.IDLE) {
            console.warn('[PFCall] Already in state:', this.state, '— ignoring new call request');
            return;
        }

        // Lock state immediately to prevent double-click race conditions
        this.state = PF_STATE.CALLING;

        const notify = (msg, type = 'error') => {
            if (typeof window.showToast === 'function') window.showToast(msg, type);
            else alert(msg);
        };

        if (!this.socket || !this.socket.connected) {
            console.warn('[PFCall] Socket not connected. Attempting call anyway (Messenger style)...');
            // We allow the call to proceed. The server will handle relay failures
            // and the caller will eventually timeout to a missed call.
        }
        if (!targetId) {
            console.error('[PFCall] targetId is empty — cannot call');
            this.state = PF_STATE.IDLE;
            notify('Cannot start call: no target selected. Please open a conversation first.', 'warning');
            return;
        }

        this.partnerId    = targetId;
        this.partnerType  = targetType;
        this.callType     = type || 'voice';
        this.isInitiator  = true;
        this._callDuration = 0;

        // 1. Show UI IMMEDIATELY
        this._showOverlay(PF_STATE.CALLING, targetName || 'Unknown', targetAvatar || '',
            `Calling\u2026 (${this.callType === 'video' ? 'Video' : 'Voice'})`);
        this._showActions('calling');
        this.audio.play(this.basePath);

        // 2. No-answer timeout (30s)
        this._noAnswerTimeout = setTimeout(() => {
            if (this.state === PF_STATE.CALLING) {
                this._postCallEvent('no_answer', 0);
                this._flashEnded('No answer.');
            }
        }, 30000);

        // 3. Request Media — with video-to-audio fallback
        this._isMediaLoading = true;
        let stream = null;
        let mediaError = null;

        try {
            stream = await this._getMedia(this.callType === 'video');
        } catch (err) {
            mediaError = err;
        }

        this._isMediaLoading = false;

        // CRITICAL: Check if call was cancelled while we were waiting for media
        if (this.state === PF_STATE.IDLE) {
            console.warn('[PFCall] Call was cancelled during media acquisition');
            if (stream) stream.getTracks().forEach(t => t.stop());
            return;
        }

        if (mediaError) {
            console.error('[PFCall] Media failed (all fallbacks):', mediaError.message);
            clearTimeout(this._noAnswerTimeout);
            this._postCallEvent('missed', 0);
            this._flashEnded('Microphone/Camera access denied.');
            return;
        }

        this.localStream = stream;
        // If _getMedia downgraded us to voice, update local callType
        if (this.callType === 'video' && !stream.getVideoTracks().length) {
            this.callType = 'voice';
            const lbl = this.$('pf-call-label');
            if (lbl) lbl.textContent = 'Calling\u2026 (Voice – camera unavailable)';
        }

        // 4. Signal Server
        if (this.state !== PF_STATE.CALLING) return;
        const payload = {
            receiverId:  targetId,
            toUserId:    targetId,
            toUserType:  targetType,
            type:        this.callType,
            callType:    this.callType,
            fromName:    this.userName,
            fromAvatar:  this.userAvatar,
            orderId:     (window.PFCallState ? window.PFCallState.activeId : null)
        };
        this.socket.emit('callUser', payload);
    }

    /**
     * Accept an incoming call. Called from Accept button.
     */
    async accept() {
        console.log('[PFCall] Accept button clicked');
        if (this.state !== PF_STATE.INCOMING || this._isMediaLoading) return;

        this.audio.stop();
        this._isMediaLoading = true;

        this._showOverlay(PF_STATE.INCOMING, null, null, 'Starting media\u2026');

        let stream = null;
        let mediaError = null;

        try {
            stream = await this._getMedia(this.callType === 'video');
        } catch (err) {
            mediaError = err;
        }

        this._isMediaLoading = false;

        // Check if caller hung up or we cancelled while waiting for media
        if (this.state === PF_STATE.IDLE || this.state === PF_STATE.ENDED) {
            console.warn('[PFCall] Call ended during accept media acquisition');
            if (stream) stream.getTracks().forEach(t => t.stop());
            return;
        }

        if (mediaError) {
            console.error('[PFCall] accept() failed:', mediaError.message);
            this._flashEnded('Device access denied.');
            return;
        }

        this.localStream = stream;
        // Downgrade if needed
        if (this.callType === 'video' && !stream.getVideoTracks().length) {
            this.callType = 'voice';
        }

        const payload = { toUserId: this.partnerId, toUserType: this.partnerType };
        console.log('[PFCall] Emitting pf-accept-call:', payload);
        this.socket.emit('pf-accept-call', payload);

        this._showOverlay(PF_STATE.IN_CALL,
            this.$('pf-call-name')?.textContent || 'Caller',
            this.$('pf-call-avatar')?.src || '',
            'Connected');
        this._showActions('incall');
        this._startTimer();

        // Start WebRTC (receiver creates PC, waits for offer)
        this._createPC();
        if (this.localStream) {
            this.localStream.getTracks().forEach(t => this.pc.addTrack(t, this.localStream));
            console.log('[PFCall] Local tracks added to PC (receiver)');
        }
    }

    /**
     * Internal helper for media acquisition with fallback.
     */
    async _getMedia(withVideo) {
        try {
            // First attempt: preferred mode
            return await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: withVideo ? { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } } : false
            });
        } catch (err) {
            if (withVideo) {
                console.warn('[PFCall] Video failed, trying audio-only fallback:', err.name);
                // Fallback to audio-only if video failed
                return await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            }
            throw err; // Re-throw if audio-only also fails or was the only request
        }
    }

    /**
     * Reject an incoming call. Called from Reject button.
     */
    reject() {
        console.log('[PFCall] Reject button clicked');
        if (this.state !== PF_STATE.INCOMING) return;
        if (this.socket && this.partnerId) {
            this.socket.emit('pf-reject-call', {
                toUserId: this.partnerId, toUserType: this.partnerType
            });
        }
        this._postCallEvent('declined', 0);
        // IMPORTANT: use _flashEnded NOT _cleanUp here.
        // _cleanUp() instantly hides the overlay while the click event is still
        // propagating — the browser then delivers the click to the voice/video
        // button underneath, immediately starting a new outgoing call.
        // _flashEnded() keeps the overlay visible for 2.5 s, blocking click-through.
        this._flashEnded('Call declined.');
    }

    /**
     * End any active call. Called from End button.
     */
    endCall() {
        console.log('[PFCall] End Call button clicked');
        if (this.state === PF_STATE.IDLE) return;
        const wasInCall  = this.state === PF_STATE.IN_CALL;
        const duration   = wasInCall ? Math.floor((Date.now() - this._timerStart) / 1000) : 0;
        if (this.socket && this.partnerId) {
            this.socket.emit('pf-end-call', { toUserId: this.partnerId, toUserType: this.partnerType });
        }
        if (wasInCall)                       this._postCallEvent('ended',    duration);
        else if (this.state === PF_STATE.CALLING) this._postCallEvent('no_answer', 0);
        this._cleanUp();
    }

    /**
     * Toggle mic mute.
     */
    toggleMute() {
        if (!this.localStream) return;
        const track = this.localStream.getAudioTracks()[0];
        if (!track) return;
        track.enabled = !track.enabled;
        const btn = this.$('pf-btn-mute');
        if (btn) {
            btn.innerHTML = track.enabled
                ? '<i class="bi bi-mic-fill"></i>'
                : '<i class="bi bi-mic-mute-fill"></i>';
            btn.classList.toggle('muted', !track.enabled);
        }
        console.log('[PFCall] Mute toggled — mic enabled:', track.enabled);
    }

    // ══════════════════════════════════════════════════════════════════
    // SOCKET.IO
    // ══════════════════════════════════════════════════════════════════

    _connectSocket() {
        if (this.socket) return; // Prevent multiple connections
        console.log('[PFCall] Initializing single socket instance...');

        if (typeof io === 'undefined') {
            console.error('[PFCall] Socket.IO (io) is not loaded! Check <script src=".../socket.io.min.js">');
            return;
        }

        let host = window.location.hostname || 'localhost';
        
        const url  = `http://${host}:3000`;
        console.log(`[PFCall] Connecting socket to: ${url} ...`);

        this.socket = io(url, {
            transports: ['websocket'],
            query: { userId: this.userId, userType: this.userType },
            reconnection: true,
            reconnectionDelay: 500,
            reconnectionAttempts: 50
        });

        this.socket.on('connect', () => {
            console.log('[PFCall] Socket connected successfully!');
            this._connectTs = Date.now();
            this.isSocketConnected = true;
            console.log(`[PFCall] ID: ${this.socket.id}`);

            // Register with server so we appear in activeUsers for relaying
            this.socket.emit('register', {
                userId:   this.userId,
                userType: this.userType,
                name:     this.userName,
                avatar:   this.userAvatar
            });
            console.log('[PFCall] Emitted registration to server.');
            window.dispatchEvent(new Event('PFCallConnected'));
        });

        this.socket.on('disconnect', reason => {
            console.warn('[PFCall] Socket disconnected:', reason);
            this.isSocketConnected = false;
            window.dispatchEvent(new Event('PFCallDisconnected'));
        });

        this.socket.on('connect_error', err => {
            this._connectErrorCount++;
            const msg = (err && err.message) ? err.message : String(err);
            console.error('[PFCall] Socket connection error:', msg);

            if (!this._connectErrorHinted) {
                const m = msg.toLowerCase();
                if (m.includes('xhr poll error') || m.includes('websocket error') || m.includes('timeout') || m.includes('connection refused')) {
                    this._connectErrorHinted = true;
                    console.error('[PFCall] Call server unreachable. Start the signaling server with `node server.js` in the PrintFlow project root (port 3000).');
                }
            }
        });

        this.socket.on('reconnect', attempt => {
            console.log('[PFCall] Socket reconnected after', attempt, 'attempts — re-registering');
            this._connectTs = Date.now();
            // Re-emit register with full data so server updates name/avatar
            this.socket.emit('register', {
                userId:   this.userId,
                userType: this.userType,
                name:     this.userName,
                avatar:   this.userAvatar
            });
        });

        // ── pf-call-error ─────────────────────────────────────────────
        this.socket.on('pf-call-error', data => {
            console.warn('[PFCall] Call error from server:', data.message);
            this._flashEnded(data.message || 'Call failed.');
        });

        // ── pf-call-busy ──────────────────────────────────────────────
        this.socket.on('pf-call-busy', data => {
            console.warn('[PFCall] Target busy:', data.message);
            this._postCallEvent('busy', 0);
            this._flashEnded(data.message || 'User is currently on another call.');
        });

        this.socket.on('pf-call-ringing-offline', (d) => {
            console.log('[PFCall] Receiver is offline. Ringing via Push Notification...');
            const lbl = this.$('pf-call-label');
            if (lbl) lbl.textContent = 'Ringing... (User notified)';
        });

        this.socket.on('pf-call-missed', (d) => {
            console.log('[PFCall] Call was not answered (missed).');
            clearTimeout(this._noAnswerTimeout);
            this._flashEnded('No answer.');
        });

        // ── CALL SYNC (recovery) ──────────────────────────────────────
        this.socket.on('pf-call-sync', (d) => {
            console.log('[PFCall] Syncing active call state:', d);
            if (this.state !== PF_STATE.IDLE) return;

            this.partnerId = d.partnerId;
            this.partnerType = d.partnerType;
            this.callType = d.callType || 'voice';
            this._timerStart = d.startTime || Date.now();
            
            // Note: We don't have caller name/avatar here easily without another lookup
            // but we can at least show the "In Call" overlay.
            this._showOverlay(PF_STATE.IN_CALL, 'Ongoing Call', '', 'Connected');
            this._showActions('incall');
            this._startTimer();
            
            // Re-negotiation would be needed for WebRTC to resume, 
            // which usually requires the initiator to send a new offer.
        });

        // ── INCOMING CALL ─────────────────────────────────────────────
        this.socket.on('incomingCall', data => {
            if (data.orderId) {
                // Sync global state if we got an orderId
                if (window.PFCallState) window.PFCallState.activeId = data.orderId;
            }
            this._handleIncomingCall(data);
        }); 

        // ── CALL ACCEPTED (caller side) ───────────────────────────────
        this.socket.on('pf-call-accepted', () => {
            if (this.state !== PF_STATE.CALLING) return;
            clearTimeout(this._noAnswerTimeout);
            this.audio.stop();
            this._showOverlay(PF_STATE.IN_CALL, null, null, 'Connected');
            this._showActions('incall');
            this._startTimer();

            this._createPC();
            if (this.localStream) this.localStream.getTracks().forEach(t => this.pc.addTrack(t, this.localStream));

            this.pc.createOffer()
                .then(offer => this.pc.setLocalDescription(offer).then(() => offer))
                .then(offer => this.socket.emit('pf-webrtc-offer', { toUserId: this.partnerId, toUserType: this.partnerType, offer }))
                .catch(err => console.error('[PFCall] createOffer failed:', err));
        });

        // ── CALL REJECTED ─────────────────────────────────────────────
        this.socket.on('pf-call-rejected', () => {
            clearTimeout(this._noAnswerTimeout);
            if (this.isInitiator) this._postCallEvent('missed', 0);
            else                  this._postCallEvent('declined', 0);
            this._flashEnded('Call declined.');
        });

        // ── CALL ENDED ────────────────────────────────────────────────
        this.socket.on('pf-call-ended', () => {
            if (this.state === PF_STATE.IDLE) return;
            const dur = this.state === PF_STATE.IN_CALL
                ? Math.floor((Date.now() - this._timerStart) / 1000) : 0;
            if (dur > 0) this._postCallEvent('ended', dur);
            this._flashEnded('Call ended.');
        });

        // ── WEBRTC OFFER (receiver gets this) ─────────────────────────
        this.socket.on('pf-webrtc-offer', async data => {
            console.log('[PFCall] pf-webrtc-offer received — creating answer');
            if (!this.pc) {
                console.warn('[PFCall] PC not ready when offer arrived — creating now');
                this._createPC();
                if (this.localStream) {
                    this.localStream.getTracks().forEach(t => this.pc.addTrack(t, this.localStream));
                }
            }
            try {
                await this.pc.setRemoteDescription(new RTCSessionDescription(data.offer));
                console.log('[PFCall] setRemoteDescription (offer) OK');
                const answer = await this.pc.createAnswer();
                await this.pc.setLocalDescription(answer);
                console.log('[PFCall] createAnswer() OK — emitting pf-webrtc-answer');
                this.socket.emit('pf-webrtc-answer', {
                    toUserId: this.partnerId, toUserType: this.partnerType, answer
                });
                this._flushIceQueue();
            } catch (err) {
                console.error('[PFCall] Offer/Answer failed:', err);
            }
        });

        // ── WEBRTC ANSWER (caller gets this) ──────────────────────────
        this.socket.on('pf-webrtc-answer', async data => {
            console.log('[PFCall] pf-webrtc-answer received');
            if (!this.pc) { console.error('[PFCall] PC is null when answer arrived'); return; }
            try {
                await this.pc.setRemoteDescription(new RTCSessionDescription(data.answer));
                console.log('[PFCall] setRemoteDescription (answer) OK');
                this._flushIceQueue();
            } catch (err) {
                console.error('[PFCall] setRemoteDescription (answer) failed:', err);
            }
        });

        // ── ICE CANDIDATES ────────────────────────────────────────────
        this.socket.on('pf-ice-candidate', data => {
            const c = new RTCIceCandidate(data.candidate);
            if (this.pc && this.pc.remoteDescription) {
                this.pc.addIceCandidate(c).catch(e => console.warn('[PFCall] addIceCandidate err:', e));
            } else {
                this.iceQueue.push(c);
            }
        });
    }

    /**
     * Handle an incoming call event (logic shared between real-time events and buffered calls).
     */
    _handleIncomingCall(data) {
        console.log('[PFCall] _handleIncomingCall:', data);

        // Ensure UI exists before proceeding
        this._ensureUI();

        if (this.state !== PF_STATE.IDLE) {
            console.warn('[PFCall] Already busy. Auto-rejecting.');
            this.socket.emit('pf-reject-call', { toUserId: data.fromUserId, toUserType: data.fromUserType });
            return;
        }

        this.partnerId    = data.fromUserId;
        this.partnerType  = data.fromUserType;
        this.callType     = data.callType || 'voice';
        this.isInitiator  = false;
        this._callDuration = 0;

        // Use page-stored avatar as fallback when fromAvatar is empty
        const resolvedAvatar = data.fromAvatar ||
            (typeof partnerAvatarUrl !== 'undefined' ? partnerAvatarUrl : '') || '';

        this._showOverlay(PF_STATE.INCOMING,
            data.fromName || 'Unknown',
            resolvedAvatar,
            `Incoming ${this.callType === 'video' ? 'Video' : 'Voice'} Call`);
        this._showActions('incoming');
        this.audio.play(this.basePath);

        // Show Browser Notification if tab is backgrounded
        this._showBrowserNotification(data.fromName || 'Someone', data.callType || 'voice');
    }

    _showBrowserNotification(callerName, callType) {
        if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;

        const show = () => {
            if (this.state !== PF_STATE.INCOMING) return;
            
            const title = `Incoming ${callType} Call`;
            const options = {
                body: `${callerName} is calling you on PrintFlow`,
                icon: `${this.basePath}/public/assets/images/icon-192.png`,
                tag: 'pf-incoming-call',
                renotify: true,
                requireInteraction: true,
                vibrate: [200, 100, 200]
            };

            this._notification = new Notification(title, options);
            this._notification.onclick = () => {
                window.focus();
                this._notification.close();
                this._notification = null;
            };
        };

        // Initial show
        show();

        // Repeat notification every 5 seconds until call is no longer incoming
        if (this._notifRetryInt) clearInterval(this._notifRetryInt);
        this._notifRetryInt = setInterval(() => {
            if (this.state !== PF_STATE.INCOMING) {
                clearInterval(this._notifRetryInt);
                this._notifRetryInt = null;
                if (this._notification) this._notification.close();
                return;
            }
            if (!this._tabActive) show();
        }, 5000);
    }

    // ══════════════════════════════════════════════════════════════════
    // WEBRTC
    // ══════════════════════════════════════════════════════════════════

    _createPC() {
        if (this.pc) { try { this.pc.close(); } catch(e){} }
        this.pc = new RTCPeerConnection({ iceServers: PF_ICE_SERVERS });

        this.pc.onicecandidate = e => {
            if (e.candidate) this.socket.emit('pf-ice-candidate', {
                toUserId: this.partnerId, toUserType: this.partnerType, candidate: e.candidate
            });
        };

        this.pc.oniceconnectionstatechange = () => {
            const s = this.pc.iceConnectionState;
            if (s === 'connected' || s === 'completed') this._setupSignalMonitor();
            if (s === 'disconnected' || s === 'failed') {
                this._updateSignalUI('bad');
                this._stopSignalMonitor();
            }
        };

        this.pc.ontrack = e => {
            const remote = this.$('pf-remote-video');
            if (remote && e.streams[0]) { remote.srcObject = e.streams[0]; remote.play().catch(() => {}); }
            if (e.track.kind === 'audio' && this.callType !== 'video') {
                let audioOut = this.$('pf-call-audio-out');
                if (!audioOut) {
                    audioOut = document.createElement('audio');
                    audioOut.id = 'pf-call-audio-out';
                    audioOut.autoplay = true;
                    audioOut.style.display = 'none';
                    document.body.appendChild(audioOut);
                }
                audioOut.srcObject = e.streams[0];
                audioOut.play().catch(() => {});
            }
        };
    }

    _flushIceQueue() {
        console.log(`[PFCall] Flushing ${this.iceQueue.length} queued ICE candidates`);
        while (this.iceQueue.length > 0) {
            const c = this.iceQueue.shift();
            this.pc?.addIceCandidate(c).catch(e => console.warn('[PFCall] Flush ICE err:', e));
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // UI
    // ══════════════════════════════════════════════════════════════════

    _ensureUI() {
        const overlay = this.$('pf-call-overlay');
        if (!overlay) {
            console.log('[PFCall][UI] Overlay missing from DOM, rebuilding...');
            this._buildUI();
            return false;
        }
        return true;
    }

    /**
     * Build the Call UI overlay if it doesn't exist.
     */
    _buildUI() {
        console.log('[PFCall][UI] _buildUI() started');
        const defaultAvatar = `${this.basePath}/public/assets/uploads/profiles/default.png`;

        let overlay = this.$('pf-call-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'pf-call-overlay';
            document.body.appendChild(overlay);
        }
        // CSS: #pf-call-overlay { display:none } by default
        // Only visible when class pf-call-overlay--* is applied

        overlay.innerHTML = `
            <!-- Remote audio/video (voice calls) -->
            <div id="pf-video-grid" style="display:none;position:fixed;inset:0;background:#0f172a;z-index:99998;">
                <video id="pf-remote-video" autoplay playsinline
                    style="width:100%;height:100%;object-fit:cover;"></video>
                <video id="pf-local-video" autoplay playsinline muted
                    style="position:absolute;bottom:32px;right:32px;width:180px;height:260px;
                           border-radius:24px;object-fit:cover;border:2px solid rgba(255,255,255,0.4);
                           box-shadow:0 20px 40px rgba(0,0,0,.4);"></video>
            </div>

            <!-- Call card -->
            <div class="pf-call-card" id="pf-call-card">

                <!-- Avatar + ripple rings -->
                <div class="pf-avatar-ring" id="pf-avatar-ring">
                    <div class="pf-ripple pf-ripple-1"></div>
                    <div class="pf-ripple pf-ripple-2"></div>
                    <div class="pf-ripple pf-ripple-3"></div>
                    <img id="pf-call-avatar" src="${defaultAvatar}" alt="Caller"
                         onerror="this.onerror=null;this.src='${defaultAvatar}';"
                         class="pf-call-avatar-img">
                </div>

                <!-- Text -->
                <div id="pf-call-name"  class="pf-call-name">...</div>
                <div id="pf-call-label" class="pf-call-label">...</div>
                <div id="pf-call-timer" class="pf-call-timer" style="display:none;">00:00</div>
                <!-- Signal Quality Indicator -->
                <div id="pf-signal-indicator" class="pf-signal-indicator" style="display:none;"></div>

                <!-- CALLING: only cancel -->
                <div id="pf-actions-calling" class="pf-call-actions" style="display:none;">
                    <div style="text-align:center;">
                        <button class="pf-btn pf-btn-end" onclick="window.PFCall.endCall()" title="Cancel Call">
                            <svg fill="currentColor" viewBox="0 0 16 16" style="width:50%;height:50%;"><path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511zM11.111 2.247a.5.5 0 0 1 .706.002L13 3.42l1.183-1.171a.5.5 0 1 1 .708.706L13.707 4.14l1.183 1.183a.5.5 0 1 1-.708.708L13 4.846l-1.183 1.185a.5.5 0 1 1-.706-.708L12.293 4.14l-1.182-1.187a.5.5 0 0 1 .001-.706z"/></svg>
                        </button>
                        <div class="pf-btn-label">Cancel</div>
                    </div>
                </div>

                <!-- INCOMING: reject + accept -->
                <div id="pf-actions-incoming" class="pf-call-actions" style="display:none;">
                    <div style="text-align:center;">
                        <button class="pf-btn pf-btn-reject" onclick="window.PFCall.reject()" title="Decline">
                            <svg fill="currentColor" viewBox="0 0 16 16" style="width:50%;height:50%;"><path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511zM11.111 2.247a.5.5 0 0 1 .706.002L13 3.42l1.183-1.171a.5.5 0 1 1 .708.706L13.707 4.14l1.183 1.183a.5.5 0 1 1-.708.708L13 4.846l-1.183 1.185a.5.5 0 1 1-.706-.708L12.293 4.14l-1.182-1.187a.5.5 0 0 1 .001-.706z"/></svg>
                        </button>
                        <div class="pf-btn-label" style="color:#ef4444;">Decline</div>
                    </div>
                    <div style="text-align:center;">
                        <button class="pf-btn pf-btn-accept" onclick="window.PFCall.accept()" title="Accept">
                            <svg fill="currentColor" viewBox="0 0 16 16" style="width:50%;height:50%;"><path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/></svg>
                        </button>
                        <div class="pf-btn-label">Accept</div>
                    </div>
                </div>

                <!-- IN-CALL: mute + end -->
                <div id="pf-actions-incall" class="pf-call-actions" style="display:none;">
                    <div style="text-align:center;">
                        <button class="pf-btn pf-btn-mute" id="pf-btn-mute"
                                onclick="window.PFCall.toggleMute()" title="Mute / Unmute">
                            <svg fill="currentColor" viewBox="0 0 16 16" style="width:50%;height:50%;"><path d="M5 3a3 3 0 0 1 6 0v5a3 3 0 0 1-6 0V3z"/><path d="M3.5 6.5A.5.5 0 0 1 4 7v1a4 4 0 0 0 8 0V7a.5.5 0 0 1 1 0v1a5 5 0 0 1-4.5 4.975V15h3a.5.5 0 0 1 0 1h-7a.5.5 0 0 1 0-1h3v-2.025A5 5 0 0 1 3 8V7a.5.5 0 0 1 .5-.5z"/></svg>
                        </button>
                        <div class="pf-btn-label">Mute</div>
                    </div>
                    <div style="text-align:center;">
                        <button class="pf-btn pf-btn-end pf-btn-end-large"
                                onclick="window.PFCall.endCall()" title="End Call">
                            <svg fill="currentColor" viewBox="0 0 16 16" style="width:50%;height:50%;"><path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511zM11.111 2.247a.5.5 0 0 1 .706.002L13 3.42l1.183-1.171a.5.5 0 1 1 .708.706L13.707 4.14l1.183 1.183a.5.5 0 1 1-.708.708L13 4.846l-1.183 1.185a.5.5 0 1 1-.706-.708L12.293 4.14l-1.182-1.187a.5.5 0 0 1 .001-.706z"/></svg>
                        </button>
                        <div class="pf-btn-label">End</div>
                    </div>
                </div>

            </div><!-- /.pf-call-card -->
        `;

        document.body.appendChild(overlay);
        this._uiReady = true;
        console.log('[PFCall] UI overlay built and appended to body');

        // Process any buffered incoming call now that UI is ready
        if (this._pendingIncoming) {
            console.log('[PFCall] Processing buffered incoming call...');
            const data = this._pendingIncoming;
            this._pendingIncoming = null;
            // No delay needed now that it's a method
            this._handleIncomingCall(data);
        }
    }

    /**
     * Show overlay in a given state and update name/avatar/label.
     */
    _showOverlay(state, name, avatar, label) {
        this.state = state;
        this._ensureUI();
        const overlay = this.$('pf-call-overlay');
        if (!overlay) { console.error('[PFCall] Overlay element not found even after ensure!'); return; }

        // Update text
        if (name  != null) this._txt('pf-call-name',  name);
        if (label != null) this._txt('pf-call-label', label);

        // Update avatar or show initials
        const defaultAvatar = `${this.basePath}/public/assets/uploads/profiles/default.png`;
        const avatarImg = this.$('pf-call-avatar');
        let initialEl = this.$('pf-call-initial');
        
        if (!initialEl) {
            initialEl = document.createElement('div');
            initialEl.id = 'pf-call-initial';
            initialEl.className = 'pf-call-avatar-img';
            initialEl.style.display = 'none';
            initialEl.style.alignItems = 'center';
            initialEl.style.justifyContent = 'center';
            initialEl.style.background = 'rgba(83,197,224,0.15)';
            initialEl.style.color = '#0369a1';
            initialEl.style.fontSize = '3.5rem';
            initialEl.style.fontWeight = '800';
            initialEl.style.borderRadius = '50%';
            initialEl.style.zIndex = '2';
            const ring = this.$('pf-avatar-ring');
            if (ring) ring.appendChild(initialEl);
        }

        if (avatar && avatar !== defaultAvatar && !avatar.includes('default.png')) {
            if (avatarImg) {
                avatarImg.src = this._avatar(avatar);
                avatarImg.style.display = 'block';
            }
            if (initialEl) initialEl.style.display = 'none';
        } else {
            if (avatarImg) avatarImg.style.display = 'none';
            if (initialEl) {
                initialEl.style.display = 'flex';
                initialEl.textContent = (name || '?').charAt(0).toUpperCase();
            }
        }

        // Apply state class (CSS shows the overlay only when a class is present)
        overlay.className = `pf-call-overlay--${state}`;

        // Ripple only while ringing
        const ring = this.$('pf-avatar-ring');
        if (ring) {
            ring.classList.toggle('pf-ripple-active',
                state === PF_STATE.CALLING || state === PF_STATE.INCOMING);
        }

        // Timer shown only in-call
        const timer = this.$('pf-call-timer');
        if (timer) timer.style.display = state === PF_STATE.IN_CALL ? 'block' : 'none';

        // Hide all action rows; specific one shown in _showActions()
        ['pf-actions-calling','pf-actions-incoming','pf-actions-incall'].forEach(id => {
            const el = this.$(id);
            if (el) el.style.display = 'none';
        });

        console.log('[PFCall] Overlay state →', state);
    }

    /**
     * Show the correct action row for the current state.
     * @param {'calling'|'incoming'|'incall'} which
     */
    _showActions(which) {
        const id = `pf-actions-${which}`;
        const el = this.$(id);
        if (el) el.style.display = 'flex';
        else console.error('[PFCall] Action row not found:', id);

        // Show local video preview for video calls
        if (which === 'incall' && this.callType === 'video') {
            const vGrid = this.$('pf-video-grid');
            const localVid = this.$('pf-local-video');
            if (vGrid)    vGrid.style.display = 'block';
            if (localVid && this.localStream) localVid.srcObject = this.localStream;
        }
    }

    _flashEnded(message) {
        this._showOverlay(PF_STATE.ENDED, null, null, message);
        this._stopTimer();
        this.audio.stop();
        if (this._notifRetryInt) { clearInterval(this._notifRetryInt); this._notifRetryInt = null; }
        if (this._notification) { this._notification.close(); this._notification = null; }
        setTimeout(() => this._cleanUp(), 2500);
    }

    _cleanUp() {
        this.state       = PF_STATE.IDLE;
        this.partnerId   = null;
        this.partnerType = null;
        this.isInitiator = false;
        this.iceQueue    = [];
        this._callDuration = 0;

        // Clear all state classes from overlay to hide it
        const overlay = this.$('pf-call-overlay');
        if (overlay) {
            overlay.classList.remove(
                'pf-call-overlay--calling',
                'pf-call-overlay--incoming',
                'pf-call-overlay--in-call',
                'pf-call-overlay--ended'
            );
        }

        clearTimeout(this._noAnswerTimeout);
        this._noAnswerTimeout = null;
        this._stopSignalMonitor();

        const sigEl = this.$('pf-signal-indicator');
        if (sigEl) { sigEl.style.display = 'none'; sigEl.className = 'pf-signal-indicator'; }

        this.audio.stop();
        this._stopTimer();
        if (this._notifRetryInt) { clearInterval(this._notifRetryInt); this._notifRetryInt = null; }
        if (this._notification) { this._notification.close(); this._notification = null; }

        if (this.pc) {
            try { this.pc.close(); } catch(e) {}
            this.pc = null;
        }
        if (this.localStream) {
            this.localStream.getTracks().forEach(t => t.stop());
            this.localStream = null;
        }

        // Clean audio output element
        const audioOut = this.$('pf-call-audio-out');
        if (audioOut) { audioOut.srcObject = null; }

        // Hide overlay (no class = CSS hides it)
        if (overlay) overlay.className = '';

        // Hide video grid
        const vGrid = this.$('pf-video-grid');
        if (vGrid) vGrid.style.display = 'none';

        // Reset all action rows
        ['pf-actions-calling','pf-actions-incoming','pf-actions-incall'].forEach(id => {
            const el = this.$(id);
            if (el) el.style.display = 'none';
        });

        // Reset timer
        const timer = this.$('pf-call-timer');
        if (timer) { timer.textContent = '00:00'; timer.style.display = 'none'; }

        // Reset mute
        const muteBtn = this.$('pf-btn-mute');
        if (muteBtn) { muteBtn.innerHTML='<i class="bi bi-mic-fill"></i>'; muteBtn.classList.remove('muted'); }
    }

    // ══════════════════════════════════════════════════════════════════
    // TIMER
    // ══════════════════════════════════════════════════════════════════

    _startTimer() {
        this._stopTimer();
        this._callDuration = 0;
        this._timerStart = Date.now();
        this._timerInt = setInterval(() => {
            const s  = Math.floor((Date.now() - this._timerStart) / 1000);
            const mm = String(Math.floor(s / 60)).padStart(2, '0');
            const ss = String(s % 60).padStart(2, '0');
            const el = this.$('pf-call-timer');
            if (el) el.textContent = `${mm}:${ss}`;
        }, 1000);
    }

    _stopTimer() {
        if (this._timerInt) { clearInterval(this._timerInt); this._timerInt = null; }
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════

    $(id) { return document.getElementById(id); }

    _txt(id, text) {
        const el = this.$(id);
        if (el) el.textContent = text;
    }

    _avatar(path) {
        const def = `${this.basePath}/public/assets/uploads/profiles/default.png`;
        if (!path) return def;
        if (/^(https?:)?\/\//.test(path) || path.startsWith('data:') || path.startsWith('/')) return path;
        // Relative path — extract filename and resolve
        const filename = path.split(/[\\/]/).pop();
        return `${this.basePath}/public/assets/uploads/profiles/${filename}`;
    }
}

// ─── GLOBAL SINGLETON ─────────────────────────────────────────────────────────
// PFCall instantiation has zero side-effects (no DOM, no socket, no overlay).
window.PFCall = new PFCallManager();

// Maintain backward compatibility with old call method name
// Both startCall() and call() work
window.PFCall.call = function(targetId, targetType, targetName, targetAvatar, type) {
    return window.PFCall.startCall(targetId, targetType, targetName, targetAvatar, type);
};

// Fire PFCallGlobalReady ONLY after DOM is fully parsed
// so that initialize() can safely build the UI overlay
// Fire PFCallGlobalReady if system was already initialized
if (window.PFCall && window.PFCall.userId) {
    window.PFCallReady = true;
}

if (window.PFCallReady) {
    document.dispatchEvent(new Event("PFCallGlobalReady"));
    window.dispatchEvent(new Event("PFCallGlobalReady"));
    console.log('[PFCall] System is READY');
} else {
    const readyFn = () => {
        if (window.PFCall && window.PFCall.userId) {
            window.PFCallReady = true;
            document.dispatchEvent(new Event("PFCallGlobalReady"));
            window.dispatchEvent(new Event("PFCallGlobalReady"));
            console.log('[PFCall] System is READY (DOMContentLoaded/Turbo)');
        }
    };
    document.addEventListener('DOMContentLoaded', readyFn, { once: true });
    // Turbo resilience: re-fire ready if needed
    document.addEventListener('turbo:load', readyFn);
}

// ── Turbo / Navigation Recovery ──
document.addEventListener('turbo:render', () => {
    if (window.PFCall && window.PFCall.userId) {
        console.log('[PFCall][Turbo] Page render detected, ensuring UI exists');
        window.PFCall._ensureUI();
    }
});

document.addEventListener('printflow:page-init', () => {
    if (window.PFCall && window.PFCall.userId) {
        console.log('[PFCall][Turbo] printflow:page-init detected, ensuring UI exists');
        window.PFCall._ensureUI();
    }
});
}
