const CACHE_NAME = "masterpiece-v1";
const ASSETS = [
  "watch.php",
  "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css",
];

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)));
});

self.addEventListener("fetch", (e) => {
  // Basic Stale-While-Revalidate or Network-First strategy
  e.respondWith(fetch(e.request).catch(() => caches.match(e.request)));
});
