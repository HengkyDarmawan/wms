/* ============================================================
   PWA installable (Blueprint §11, A-193): daftarkan service worker di
   subdomain company. Service worker hanya menyimpan aset statis dan
   halaman offline; data dokumen tidak pernah disimpan di cache.
   ============================================================ */

(function () {
  'use strict';

  if ('serviceWorker' in navigator && window.isSecureContext !== false) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () { /* opsional */ });
    });
  }
})();
