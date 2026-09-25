/* ============================================================
   WMS Proyek — bundel landing page produk (30-landing-page)
   Hanya Alpine.js (Blueprint §17: "Landing page: Blade + Alpine.js").
   Sengaja TIDAK mengimpor app.js: jQuery, shell NexaDash, dan service
   worker PWA (wms/pwa.js) tidak boleh terpasang di domain pusat.
   ============================================================ */

import Alpine from 'alpinejs';

const THEME_KEY = 'nx-theme';

const readTheme = () => document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';

const reducedMotion = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Navigasi atas: menu HP, bayangan saat digulir, dan tema terang/gelap
// dengan kunci localStorage yang sama dengan aplikasi (nx-theme).
Alpine.data('landingNav', () => ({
    open: false,
    scrolled: false,
    theme: readTheme(),

    init() {
        const onScroll = () => { this.scrolled = window.scrollY > 8; };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', () => { if (window.innerWidth >= 992) this.open = false; }, { passive: true });
    },

    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', this.theme);
        try { localStorage.setItem(THEME_KEY, this.theme); } catch (e) { /* mode privat: abaikan */ }
    },

    close() { this.open = false; },
}));

// Rel kartu mendatar (gaya indonesia.travel, D-06): tombol geser kiri/kanan.
Alpine.data('rail', () => ({
    atStart: true,
    atEnd: false,

    init() {
        this.update();
        this.$refs.track.addEventListener('scroll', () => this.update(), { passive: true });
        window.addEventListener('resize', () => this.update(), { passive: true });
    },

    update() {
        const t = this.$refs.track;
        this.atStart = t.scrollLeft <= 4;
        this.atEnd = t.scrollLeft + t.clientWidth >= t.scrollWidth - 4;
    },

    go(dir) {
        const t = this.$refs.track;
        const card = t.querySelector('li');
        const step = card ? card.getBoundingClientRect().width + 20 : t.clientWidth * 0.8;
        t.scrollBy({ left: dir * step, behavior: reducedMotion() ? 'auto' : 'smooth' });
    },
}));

// Formulir "Masuk ke company Anda": pratinjau alamat & validasi di sisi klien.
// Tanpa JavaScript formulir tetap jalan lewat GET /masuk (A-221).
Alpine.data('companyEnter', (domain, port, initial = '', initialAs = 'team', serverError = false) => ({
    company: initial,
    as: initialAs,
    serverError,
    touched: false,

    get invalid() {
        return (this.serverError && !this.touched) || (this.clean !== '' && !this.valid);
    },

    get clean() { return (this.company || '').trim().toLowerCase(); },
    get valid() { return /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/.test(this.clean); },
    get preview() {
        const sub = this.clean || 'nama-company';
        return `${sub}.${domain}${port ? ':' + port : ''}${this.as === 'portal' ? '/portal/login' : '/login'}`;
    },
}));

window.Alpine = Alpine;
Alpine.start();
