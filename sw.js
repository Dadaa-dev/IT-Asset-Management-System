/**
 * AssetVault SPIST — Service Worker
 * Enables PWA offline caching and background sync
 */

const CACHE_NAME    = 'assetvault-v3';
const OFFLINE_URL   = '/asset_management/offline.html';

// Static assets to pre-cache (app shell)
const PRECACHE_URLS = [
  '/asset_management/',
  '/asset_management/index.php',
  '/asset_management/login.php',
  '/asset_management/dashboard.php',
  '/asset_management/style.css',
  '/asset_management/barcode.js',
  '/asset_management/assets/assetvault-icon.svg',
  '/asset_management/assets/icons/icon-192.png',
  '/asset_management/assets/icons/icon-512.png',
  '/asset_management/assets/google-icon.svg',
  '/asset_management/offline.html',
];

// ── Install: Pre-cache the app shell ─────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

// ── Activate: Clean old caches ────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// ── Fetch: Network-first for PHP, cache-first for static ──────
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Skip non-GET and cross-origin
  if (event.request.method !== 'GET') return;
  if (url.origin !== location.origin)  return;

  const isPhp    = url.pathname.endsWith('.php');
  const isStatic = /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff2?)$/.test(url.pathname);

  if (isStatic) {
    // Cache-first for static files
    event.respondWith(
      caches.match(event.request).then(cached =>
        cached || fetch(event.request).then(resp => {
          if (resp.ok) {
            const clone = resp.clone();
            caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
          }
          return resp;
        })
      )
    );
  } else if (isPhp) {
    // Network-first for PHP pages with offline fallback
    event.respondWith(
      fetch(event.request).catch(() =>
        caches.match(event.request).then(cached =>
          cached || caches.match(OFFLINE_URL)
        )
      )
    );
  }
});

// ── Push Notifications (future use) ──────────────────────────
self.addEventListener('push', event => {
  const data = event.data?.json() || {};
  event.waitUntil(
    self.registration.showNotification(data.title || 'AssetVault', {
      body:    data.body    || 'You have a new notification.',
      icon:    '/asset_management/assets/icons/icon-192.png',
      badge:   '/asset_management/assets/icons/icon-96.png',
      data:    data,
      actions: [{ action: 'view', title: 'View' }]
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(clients.openWindow('/asset_management/dashboard.php'));
});
