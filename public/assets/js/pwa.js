/**
 * PrintFlow PWA Manager - Professional Install Flow
 * Handles Progressive Web App lifecycle and installation prompt
 */

if (typeof window.PwaManager === 'undefined') {
    class PwaManager {
        constructor() {
            if (PwaManager.instance) return PwaManager.instance;
            PwaManager.instance = this;

            this.deferredPrompt = null;
            this.installButtonId = 'pwa-install-btn';
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

            this.init();
        }

        init() {
            // 1. Register Service Worker
            this.registerServiceWorker();

            // 2. Lifecycle Listeners
            window.addEventListener('beforeinstallprompt', (e) => this.handleBeforeInstallPrompt(e));
            window.addEventListener('appinstalled', () => this.handleAppInstalled());

            // 3. UI Sync (Turbo-compatible)
            document.addEventListener('DOMContentLoaded', () => this.syncUI());
            document.addEventListener('turbo:load', () => this.syncUI());
        }

        registerServiceWorker() {
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/printflow/public/sw.js')
                        .then(reg => {
                            reg.onupdatefound = () => {
                                const sw = reg.installing;
                                sw.onstatechange = () => {
                                    if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                                        this.notifyUpdate();
                                    }
                                };
                            };
                        })
                        .catch(e => console.error('[PWA] Registration failed', e));
                });
            }
        }

        handleBeforeInstallPrompt(e) {
            // Prevent default browser banner
            e.preventDefault();
            // Store for later user gesture
            this.deferredPrompt = e;
            console.log('[PWA] BeforeInstallPrompt captured');
            
            // Show the install UI
            this.syncUI();
        }

        handleAppInstalled() {
            console.log('[PWA] Application installed successfully');
            this.deferredPrompt = null;
            this.syncUI();
        }

        async triggerInstall() {
            if (!this.deferredPrompt) {
                this.handleManualInstructions();
                return;
            }

            try {
                this.deferredPrompt.prompt();
                const { outcome } = await this.deferredPrompt.userChoice;
                console.log(`[PWA] Install outcome: ${outcome}`);
                
                // Clear prompt after use
                this.deferredPrompt = null;
                this.syncUI();
            } catch (err) {
                console.error('[PWA] Installation failed', err);
            }
        }

        handleManualInstructions() {
            const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
            if (isIOS) {
                alert('To install PrintFlow on iOS:\n1. Tap the Share button in Safari\n2. Scroll and tap "Add to Home Screen"\n3. Tap "Add"');
            } else {
                console.info('[PWA] Install prompt not available yet');
            }
        }

        syncUI() {
            const btn = document.getElementById(this.installButtonId);
            if (!btn) return;

            // Hide if already running as standalone app
            if (this.isStandalone) {
                btn.classList.add('hidden');
                return;
            }

            // Only show button if we have the deferred prompt ready
            if (this.deferredPrompt) {
                btn.classList.remove('hidden');
                btn.onclick = () => this.triggerInstall();
            } else {
                // Keep hidden if no prompt (unless it's iOS where we might want to show instructions)
                const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
                if (isIOS) {
                    btn.classList.remove('hidden');
                    btn.onclick = () => this.handleManualInstructions();
                } else {
                    btn.classList.add('hidden');
                }
            }
        }

        notifyUpdate() {
            // Modern non-blocking update notification
            const toast = document.createElement('div');
            toast.className = 'pwa-update-toast';
            toast.innerHTML = `
                <span>New version available!</span>
                <button onclick="window.location.reload()">Update</button>
            `;
            document.body.appendChild(toast);
        }
    }

    // Initialize Global Manager
    window.PwaManager = PwaManager;
    window.PF_PWA = new PwaManager();
}
