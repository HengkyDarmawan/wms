/* ============================================================
   Global untuk shell NexaDash.
   Harus diimpor PALING AWAL di app.js: pernyataan import dievaluasi
   sebelum kode biasa di modul pemanggil, jadi menulis window.jQuery di
   app.js setelah import nexadash terlambat — nexadash/app.js berakhir
   dengan `})(jQuery)` dan gagal sebelum sempat memasang apa pun.
   ============================================================ */

import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import SimpleBar from 'simplebar';

window.$ = window.jQuery = $;
window.bootstrap = bootstrap;
// Sidebar memakai SimpleBar untuk area scroll-nya (template memuatnya dari CDN).
window.SimpleBar = SimpleBar;
