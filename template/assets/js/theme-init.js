/* NexaDash — dijalankan di <head> agar tema terpasang sebelum paint (anti-FOUC). */
(function () {
  var t = localStorage.getItem('nx-theme');
  if (!t) {
    t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  }
  document.documentElement.setAttribute('data-bs-theme', t);
})();
