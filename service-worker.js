const CACHE_NAME = 'nexus-cache-v1';
const ASSETS_TO_CACHE = [
  './',
  './index.php',
  './style.css',
  './app2.js',
  './manifest.json',
  './images/icon-192.png',
  './images/icon-512.png'
];

// Install Event
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[Service Worker] Caching static assets');
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('[Service Worker] Clearing old cache:', cache);
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event (Stale-While-Revalidate for static assets, network-only for api/POST calls)
self.addEventListener('fetch', (event) => {
  // Do not intercept API requests or non-GET requests
  if (event.request.url.includes('api.php') || event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        // Fetch fresh copy in background to update cache
        fetch(event.request)
          .then((networkResponse) => {
            if (networkResponse.status === 200) {
              caches.open(CACHE_NAME).then((cache) => {
                cache.put(event.request, networkResponse);
              });
            }
          })
          .catch(() => { /* Ignore offline fetch errors */ });
        return cachedResponse;
      }

      return fetch(event.request).then((networkResponse) => {
        // Dynamically cache new static assets from same origin or font CDNs
        if (
          networkResponse.status === 200 &&
          (event.request.url.startsWith(self.location.origin) || 
           event.request.url.includes('fonts.googleapis.com') || 
           event.request.url.includes('fonts.gstatic.com'))
        ) {
          const responseClone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return networkResponse;
      });
    })
  );
});
