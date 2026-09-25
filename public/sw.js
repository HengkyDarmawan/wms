/* Service worker WMS Proyek (Blueprint §11, A-193).
 * - Aset build (/build/*, /img/*) disimpan cache-first agar aplikasi cepat & bisa dipasang.
 * - Navigasi halaman selalu ke jaringan; bila offline tampilkan /offline.html.
 * - Data dokumen & permintaan POST tidak pernah disimpan (server acuan akhir).
 */
const CACHE = 'wms-static-v1';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE, '/img/logo.svg', '/manifest.webmanifest'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;

  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);

  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }

  if (url.origin === location.origin && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/img/'))) {
    event.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) {
          const salin = res.clone();
          caches.open(CACHE).then((c) => c.put(req, salin));
        }
        return res;
      })),
    );
  }
});
