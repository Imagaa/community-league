// Service worker minimal -- cuma supaya kriteria "installable" PWA Chrome/Android terpenuhi
// (butuh SW terdaftar, bukan cuma manifest). Belum ada caching/offline strategy sungguhan.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    const title = payload.title || 'Community League';

    event.waitUntil(self.registration.showNotification(title, {
        body: payload.body || '',
        icon: payload.icon || '/images/main-logo.webp',
        data: payload.data || {},
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data?.url || '/'));
});
