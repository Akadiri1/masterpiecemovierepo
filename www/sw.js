const CACHE_NAME = 'zenmovies-cache-v1';
const urlsToCache = [
  '/',
  'assets/css/core/libs.min.css',
  'assets/css/core/custom.min.css',
  'assets/css/core/zen.min.css',
  'assets/images/logo.png'
];

// 1. Install Event (with better error handling)
self.addEventListener('install', event => {
  console.log('[Service Worker] Installing...');

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] Caching files');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('[Service Worker] All files cached successfully');
        return self.skipWaiting();
      })
      .catch(err => {
        console.error('[Service Worker] Caching failed! Check your file paths.', err);
      })
  );
});

// 2. Activate Event (Clean up old caches)
self.addEventListener('activate', event => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then(keyList => {
      return Promise.all(keyList.map(key => {
        if (key !== CACHE_NAME) {
          console.log('[Service Worker] Removing old cache', key);
          return caches.delete(key);
        }
      }));
    })
  );
  return self.clients.claim();
});

// 3. Fetch Event
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request)
      .catch(() => {
        return caches.match(event.request);
      })
  );
});