/**
 * PrintFlow Service Worker
 * Handles Push Notifications for Calling and Orders.
 */

self.addEventListener('push', function(event) {
    if (!event.data) return;

    try {
        const data = event.data.json();
        const title = data.title || 'PrintFlow';
        const options = {
            body: data.body || '',
            icon: data.icon || '/printflow/public/assets/images/icon-192.png',
            badge: data.badge || '/printflow/public/assets/images/icon-72.png',
            tag: data.tag || 'printflow-notification',
            data: {
                url: data.url || '/printflow/'
            },
            vibrate: [200, 100, 200],
            requireInteraction: data.type === 'call' // Keep call notifications visible until clicked
        };

        event.waitUntil(
            self.registration.showNotification(title, options)
        );
    } catch (e) {
        console.error('[SW] Push error:', e);
    }
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const url = event.notification.data.url;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            // If a window is already open, focus it and navigate
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url.includes('/printflow/') && 'focus' in client) {
                    return client.navigate(url).then(c => c.focus());
                }
            }
            // Otherwise open a new window
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
