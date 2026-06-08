// AMS service worker — bump CACHE_VERSION to invalidate old caches on deploy.
const CACHE_VERSION = 'ams-v5';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const OFFLINE_URL = '/offline.html';

// How long to wait on the network for a page navigation before showing the
// offline page. Without this, a flaky mobile connection leaves iOS staring at
// a blank screen until the OS TCP timeout (~30-75s). The offline page has a
// Retry button, so falling back early is safe and feels far smoother.
const NAV_TIMEOUT_MS = 10000;

// Static, fingerprint-free assets that are safe to precache.
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => !key.startsWith(CACHE_VERSION))
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only handle same-origin GET. Never touch POST/PUT (CSRF, auth, payments).
    if (request.method !== 'GET') return;
    if (new URL(request.url).origin !== self.location.origin) return;

    // Page navigations: network-first with a timeout, falling back to the
    // offline page when offline OR when the network is too slow to respond.
    if (request.mode === 'navigate') {
        event.respondWith(
            Promise.race([
                fetch(request),
                new Promise((_, reject) =>
                    setTimeout(() => reject(new Error('nav-timeout')), NAV_TIMEOUT_MS)
                ),
            ]).catch(() => caches.match(OFFLINE_URL, { ignoreSearch: true }))
        );
        return;
    }

    // Built/static assets (Vite build, icons, fonts): cache-first, then network.
    const url = new URL(request.url);
    const isStatic =
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/fonts/');

    if (isStatic) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                        return response;
                    })
            )
        );
    }
});
