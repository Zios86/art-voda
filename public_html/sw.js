// Service Worker v18: кеширует публичные ресурсы, но никогда не перехватывает админку и форму.
const CACHE = 'kioskvoda-v18-20260901-1';
const STATIC = [
  '/', '/offline.html',
  '/css/offline.css?v=20260901-1', '/js/offline.js?v=20260901-1',
  '/css/grid.css?v=20260901-1', '/css/style.css?v=20260901-1',
  '/css/media.css?v=20260901-1', '/css/compliance.css?v=20260901-1',
  '/css/showcase.css?v=20260901-1', '/css/design-v14.css?v=20260901-1', '/js/function.js?v=20260901-1',
  '/js/external-content.js?v=20260901-1', '/list_box_layout.js?v=20260901-1',
  '/fonts/geist-cyrillic.woff2', '/fonts/geist-latin.woff2',
  '/img/brand-logo-artesian.png?v=20260815-1', '/img/hero-water-v3.webp',
  '/img/kiosk-cutout-v3.webp', '/data/kiosks.json',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  const clearOldCaches = caches.keys().then((keys) => Promise.all(
    keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)),
  ));
  event.waitUntil(clearOldCaches.then(() => self.clients.claim()));
});

function mayUseCache(request, url) {
  // Закрытые и чувствительные маршруты всегда идут напрямую на сервер.
  if (request.method !== 'GET' || url.origin !== location.origin) return false;
  if (url.pathname.startsWith('/admin/') || url.pathname.startsWith('/include/')) return false;
  return ![
    '/admin-map-frame.html', '/js/admin-map-frame.js',
    '/css/admin-map-frame.css', '/contact.php',
  ].includes(url.pathname);
}

async function navigationResponse(request) {
  try {
    const response = await fetch(request);
    if (response.ok && response.type === 'basic') {
      const cache = await caches.open(CACHE);
      await cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    return (await caches.match(request)) || caches.match('/offline.html');
  }
}

async function kiosksResponse(request) {
  // Сеть имеет приоритет; при сбое используем последний API-ответ или статический список.
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(CACHE);
      await cache.put('/api/kiosks-latest', response.clone());
    }
    return response;
  } catch (error) {
    return (await caches.match('/api/kiosks-latest')) || caches.match('/data/kiosks.json');
  }
}

async function staticResponse(request) {
  const saved = await caches.match(request);
  if (saved) return saved;
  const response = await fetch(request);
  if (response.ok) {
    const cache = await caches.open(CACHE);
    await cache.put(request, response.clone());
  }
  return response;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  if (!mayUseCache(request, url)) return;

  if (request.mode === 'navigate') {
    event.respondWith(navigationResponse(request));
  } else if (url.pathname === '/api/kiosks.php') {
    event.respondWith(kiosksResponse(request));
  } else {
    event.respondWith(staticResponse(request));
  }
});
