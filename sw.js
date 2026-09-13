/**
 * E-Pilketos v2.0 - Service Worker
 * SMK Semen Gresik (Yayasan Semen Indonesia)
 * Mendukung mode Offline Shell & PWA Standalone Kiosk
 */

const CACHE_NAME = 'epilketos-v2.1.0';

// Aset statis penting yang di-cache saat instalasi
const PRECACHE_ASSETS = [
  './offline.html',
  './manifest.json',
  './assets/css/style.css',
  './assets/js/app.js',
  './assets/js/pwa.js',
  './assets/images/logo-smk-sig.png',
  './assets/images/icons/icon-192x192.png',
  './assets/images/icons/icon-512x512.png',
  './assets/images/icons/apple-touch-icon.png',
  './assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
  './assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
  './face-poc/assets/css/face-poc.css',
  './face-poc/assets/js/face-api.js',
  './face-poc/assets/js/face-poc-recognize.js',
  './face-poc/assets/js/face-poc-enroll.js'
];

// 1. Event Install: Pre-cache aset statis
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Menggunakan allSettled atau cache satu-per-satu agar 1 file gagal tidak merusak seluruh instalasi
      return Promise.allSettled(
        PRECACHE_ASSETS.map((url) =>
          fetch(url)
            .then((res) => {
              if (res.ok) return cache.put(url, res);
            })
            .catch((err) => console.warn('[SW] Skip cache item:', url, err))
        )
      );
    }).then(() => self.skipWaiting())
  );
});

// 2. Event Activate: Bersihkan cache versi usang
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    }).then(() => self.clients.claim())
  );
});

// 3. Event Fetch: Strategi Cache Cerdas
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Jangan sentuh request non-GET (POST submit suara, login, upload harus selalu ke server)
  if (request.method !== 'GET') {
    return;
  }

  // A. Navigasi Halaman HTML/PHP: Network-First (agar suara & data selalu real-time)
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .catch(() => {
          return caches.match('./offline.html') || caches.match(request);
        })
    );
    return;
  }

  // B. Aset Statis (CSS, JS, Images, Fonts): Cache-First dengan Network Fallback
  if (
    url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|webp|woff2?|bin|json)$/)
  ) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          // Revalidate di background agar selalu terupdate
          fetch(request).then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
              caches.open(CACHE_NAME).then((cache) => cache.put(request, networkResponse));
            }
          }).catch(() => {/* Offline, ignore */});
          return cachedResponse;
        }

        return fetch(request).then((networkResponse) => {
          if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
            return networkResponse;
          }
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, responseToCache));
          return networkResponse;
        }).catch(() => {
          // Jika gambar gagal dimuat saat offline, fallback
          if (url.pathname.match(/\.(png|jpg|jpeg|webp)$/)) {
            return caches.match('./assets/images/logo-smk-sig.png');
          }
        });
      })
    );
    return;
  }

  // Default: Langsung fetch ke jaringan
  event.respondWith(fetch(request).catch(() => caches.match(request)));
});
