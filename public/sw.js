importScripts('https://storage.googleapis.com/workbox-cdn/releases/7.0.0/workbox-sw.js');

const { registerRoute } = workbox.routing;
const { StaleWhileRevalidate, NetworkFirst } = workbox.strategies;
const { ExpirationPlugin } = workbox.expiration;

workbox.core.setCacheNameDetails({ prefix: 'cut-tracker' });

// App shell: serve from cache, update in background
registerRoute(
    ({ request, url }) =>
        url.pathname === '/' ||
        request.destination === 'script' ||
        request.destination === 'style' ||
        url.pathname.endsWith('.webmanifest') ||
        url.pathname.startsWith('/icons/'),
    new StaleWhileRevalidate({
        cacheName: 'cut-tracker-shell',
        plugins: [
            new ExpirationPlugin({ maxEntries: 30, maxAgeSeconds: 60 * 60 * 24 * 7 }),
        ],
    })
);

// API GETs: network first, fall back to cache (3s timeout)
registerRoute(
    ({ url }) => url.pathname.startsWith('/api/days') || url.pathname.startsWith('/api/settings'),
    new NetworkFirst({
        cacheName: 'cut-tracker-api',
        networkTimeoutSeconds: 3,
        plugins: [
            new ExpirationPlugin({ maxEntries: 100, maxAgeSeconds: 60 * 60 * 24 }),
        ],
    }),
    'GET'
);
