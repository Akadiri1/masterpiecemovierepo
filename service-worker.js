const CACHE_NAME = 'masterpiece-v2';
const STATIC_ASSETS = [
    '/',
    '/v1/views/watch.php',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Install: pre-cache static assets
self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch: stale-while-revalidate for pages, cache-first for static assets
self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);

    // Skip non-GET and cross-origin video/embed requests (let them pass through)
    if (e.request.method !== 'GET') return;
    if (url.hostname.includes('vidsrc')) return;
    if (url.hostname.includes('youtube')) return;

    // For navigation requests (HTML pages): network-first, fallback to cache
    if (e.request.mode === 'navigate') {
        e.respondWith(
            fetch(e.request)
                .then(response => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(e.request, clone));
                    return response;
                })
                .catch(() => caches.match(e.request))
        );
        return;
    }

    // For static assets (CSS, JS, images, fonts): stale-while-revalidate
    e.respondWith(
        caches.match(e.request).then(cached => {
            const fetchPromise = fetch(e.request).then(response => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(e.request, clone));
                return response;
            }).catch(() => cached);

            return cached || fetchPromise;
        })
    );
});
