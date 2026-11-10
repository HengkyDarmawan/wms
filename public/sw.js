/* Service worker WMS Proyek (Blueprint §11, A-193).
 * - Aset build (/build/*, /img/*) disimpan cache-first agar aplikasi cepat & bisa dipasang.
 * - Navigasi halaman selalu ke jaringan; bila offline tampilkan /offline.html.
 * - Kecuali halaman offline /offline/* (A-438): disimpan saat dibuka, dipakai tanpa sinyal.
 * - Data dokumen & permintaan POST tidak pernah disimpan (server acuan akhir).
 */
const CACHE = 'wms-static-v2';
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
    // A-438 (AD-16): halaman offline (/offline/*) disimpan setiap dibuka online,
    // lalu dipakai saat tidak ada sinyal. Halaman lain tetap selalu ke jaringan.
    if (url.pathname.startsWith('/offline/') && !url.pathname.startsWith('/offline/tinjauan')) {
      event.respondWith(
        fetch(req).then((res) => {
          if (res.ok) {
            const salin = res.clone();
            caches.open(CACHE).then((c) => c.put(url.pathname, salin));
          }
          return res;
        }).catch(() => caches.match(url.pathname).then((hit) => hit || caches.match(OFFLINE))),
      );
      return;
    }
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
