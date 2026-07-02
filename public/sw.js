const CACHE_NAME = 'pokemonscanner-shell-v2';
const APP_SHELL = ['/scan', '/manifest.json', '/icons/icon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
        )),
    );
    self.clients.claim();
});

// Network-first untuk navigasi & API (data harus fresh); cache dipakai sebagai fallback offline.
// Antrian scan offline (IndexedDB) + sinkronisasi ditangani di resources/js/offline-sync.js (Fase 6).
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));

                return response;
            })
            .catch(() => caches.match(event.request)),
    );
});
