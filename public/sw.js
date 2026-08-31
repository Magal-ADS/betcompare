const staticCacheName = 'oddradar-static-v1';
const staticAssets = [
    '/manifest.webmanifest',
    '/offline.html',
    '/pwa-icon.svg',
    '/pwa-icon-192.png',
    '/pwa-icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(staticCacheName).then((cache) => cache.addAll(staticAssets)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => Promise.all(cacheNames
                .filter((cacheName) => cacheName.startsWith('oddradar-') && cacheName !== staticCacheName)
                .map((cacheName) => caches.delete(cacheName))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const requestUrl = new URL(request.url);

    if (request.method !== 'GET' || requestUrl.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));

        return;
    }

    if (!['font', 'image', 'script', 'style'].includes(request.destination)) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cachedResponse) => cachedResponse || fetch(request).then((response) => {
            const responseCopy = response.clone();

            caches.open(staticCacheName).then((cache) => cache.put(request, responseCopy));

            return response;
        })),
    );
});
