/**
 * PrintFlow Call System
 * Real-time Voice and Video signaling using Socket.io and WebRTC
 */

(function() {
    // Prevent multiple definitions
    if (window.PrintFlowCall) return;

    class PrintFlowCall {
        constructor(config) {
            this.config = config;
            this.userId = config.userId;
            this.role = config.role;
            this.userName = config.userName;
            this.userAvatar = config.userAvatar;
            
            this.socket = null;
            this.pc = null;
            this.localStream = null;
            this.isInitialized = false;
            this.listenersBound = false;
            this.connectionPromise = null;
            this.connectionError = null;
        }

        connect() {
            if (typeof io === 'undefined') {
                this.connectionError = 'Socket.io client is unavailable.';
                return Promise.resolve(false);
            }

            if (this.socket && this.socket.connected) {
                this.isInitialized = true;
                return Promise.resolve(true);
            }

            if (!this.socket) {
                this.socket = io('http://localhost:3000', {
                    autoConnect: false,
                    reconnection: true,
                    reconnectionAttempts: 2,
                    timeout: 3000
                });
            }

            if (!this.listenersBound) {
                this.setupListeners();
                this.listenersBound = true;
            }

            if (this.connectionPromise) {
                return this.connectionPromise;
            }

            this.connectionPromise = new Promise((resolve) => {
                let settled = false;

                const finish = (ok, errorMessage) => {
                    if (settled) return;
                    settled = true;
                    this.connectionError = ok ? null : (errorMessage || 'Call server is unavailable.');
                    this.isInitialized = ok;
                    this.connectionPromise = null;
                    resolve(ok);
                };

                this.socket.once('connect', () => {
                    this.socket.emit('register', { userId: this.userId, role: this.role });
                    finish(true);
                });

                this.socket.once('connect_error', () => {
                    finish(false, 'Call server is unavailable.');
                });

                this.socket.connect();
            });

            return this.connectionPromise;
        }

        setupListeners() {
            if (!this.socket) {
                return;
            }

            this.socket.on('incomingCall', (data) => {
                const accept = confirm(`Incoming ${data.type} call from ${data.fromName}. Accept?`);
                if (accept) {
                    // WebRTC accept flow is intentionally deferred until signaling is completed.
                } else {
                    this.socket.emit('rejectCall', { to: data.fromId });
                }
            });

            this.socket.on('callRejected', (data) => {
                alert(data.reason || "Call was rejected.");
                this.cleanup();
            });

            this.socket.on('callEnded', () => {
                this.cleanup();
            });
        }

        async startCall(targetId, targetRole, type, orderId, partnerName, partnerAvatar) {
            const connected = await this.connect();
            if (!connected || !this.socket) {
                alert(this.connectionError || "Call server is unavailable right now.");
                return;
            }
            
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: type === 'video'
                });

                this.socket.emit('callUser', {
                    targetId,
                    targetRole,
                    type,
                    orderId,
                    fromName: this.userName,
                    fromAvatar: this.userAvatar
                });
            } catch (e) {
                alert("Could not access camera/microphone.");
            }
        }

        cleanup() {
            if (this.localStream) {
                this.localStream.getTracks().forEach(t => t.stop());
                this.localStream = null;
            }
            if (this.pc) {
                this.pc.close();
                this.pc = null;
            }
        }
    }

    window.PrintFlowCall = PrintFlowCall;
})();
