// AMS service worker — CACHE_VERSION invalidates all old caches when it
// changes. deploy.sh stamps it with the git short SHA on every deploy; bump
// the fallback here manually when changing precached files (offline.html…)
// on a host that serves the working tree directly.
const CACHE_VERSION = 'ams-v8';
const STATIC_CACHE = `${CACHE_VERSION}-static`;

// Base path is derived from where THIS script is served, so the worker adapts to
// both the root deployment and the nginx sub-path deployment without hardcoding
// any prefix — see CLAUDE.md. new URL('./', self.location) is the directory that
// holds sw.js (e.g. the site root, or the app's sub-path on the proxied host).
const BASE_PATH = new URL('./', self.location).pathname;

const OFFLINE_URL = `${BASE_PATH}offline.html`;

// How long to wait on the network for a page navigation before showing the
// offline page. Without this, a flaky mobile connection leaves iOS staring at
// a blank screen until the OS TCP timeout (~30-75s). The offline page has a
// Retry button, so falling back early is safe and feels far smoother.
const NAV_TIMEOUT_MS = 10000;

// Static, fingerprint-free assets that are safe to precache.
const PRECACHE_URLS = [
    OFFLINE_URL,
    `${BASE_PATH}icons/icon-192.png`,
    `${BASE_PATH}icons/icon-512.png`,
    `${BASE_PATH}manifest.webmanifest`,
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
        url.pathname.startsWith(`${BASE_PATH}build/`) ||
        url.pathname.startsWith(`${BASE_PATH}icons/`) ||
        url.pathname.startsWith(`${BASE_PATH}fonts/`);

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
