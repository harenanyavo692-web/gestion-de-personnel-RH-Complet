const CACHE_VERSION = 'gesperson-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;

// Fichiers à mettre en cache dès l'installation (app shell minimal)
const PRECACHE_ASSETS = [
  '/offline.html',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

// Extensions considérées comme "assets statiques" -> stratégie cache-first
const STATIC_EXTENSIONS = ['.css', '.js', '.png', '.jpg', '.jpeg', '.svg', '.webp', '.woff', '.woff2', '.ico'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(PRECACHE_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith('gesperson-') && key !== STATIC_CACHE)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // On ignore tout ce qui n'est pas GET (POST des formulaires, etc.)
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  const isStaticAsset = STATIC_EXTENSIONS.some((ext) => url.pathname.endsWith(ext));

  if (isStaticAsset) {
    event.respondWith(cacheFirst(request));
  } else {
    event.respondWith(networkFirst(request));
  }
});

// Assets statiques : on sert le cache si dispo, sinon on va sur le réseau et on met en cache
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    return cached || Response.error();
  }
}

// Pages (HTML) : on privilégie toujours le réseau (données RH à jour),
// avec repli sur le cache ou la page hors-ligne si la connexion échoue
async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (response.ok && request.headers.get('accept')?.includes('text/html')) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    const cached = await caches.match(request);
    if (cached) return cached;

    if (request.headers.get('accept')?.includes('text/html')) {
      return caches.match('/offline.html');
    }
    return Response.error();
  }
}
