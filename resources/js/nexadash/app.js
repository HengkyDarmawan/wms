/* ============================================================
   NexaDash — app.js
   Global init: layout partials, theme, sidebar, command palette,
   tooltips, CountUp, back-to-top, chart helpers.
   ============================================================ */
(function ($) {
  'use strict';

  /* ===================== Helpers ===================== */
  function nxTheme() {
    return document.documentElement.getAttribute('data-bs-theme') || 'light';
  }
  // Pindah halaman lewat wire:navigate (tanpa muat ulang) bila Livewire ada.
  function go(url) {
    if (window.Livewire && window.Livewire.navigate) window.Livewire.navigate(url);
    else window.location.href = url;
  }

  /* ===================== Theme toggle ===================== */
  function setTheme(t) {
    document.documentElement.setAttribute('data-bs-theme', t);
    localStorage.setItem('nx-theme', t);
    syncThemeIcon();
  }
  function syncThemeIcon() {
    var btn = document.getElementById('nxThemeToggle');
    if (!btn) return;
    btn.innerHTML = nxTheme() === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
  }

  /* ===================== Command palette ===================== */
  // Daftar halaman dikirim server lewat window.nxPalette (lihat layouts/app.blade.php)
  // supaya hanya menu yang benar-benar ada dan boleh dibuka user yang muncul.
  // Template NexaDash aslinya menyertakan 72 tautan .html statis; semuanya dibuang.
  var PALETTE_PAGES = Array.isArray(window.nxPalette) ? window.nxPalette : [];
  var PALETTE_ACTIONS = [
    { g: 'Actions', t: 'Ganti tema terang/gelap', u: '#theme', i: 'bi-moon-stars' }
  ];
  var RECENT = PALETTE_PAGES.slice(0, 3);
  var paletteIndex = 0;

  function buildPalette() {
    if (document.getElementById('nxPalette')) return;
    var html =
      '<div class="nx-palette-backdrop" id="nxPaletteBackdrop"></div>' +
      '<div class="nx-palette" id="nxPalette" role="dialog" aria-modal="true" aria-label="Pencarian cepat">' +
      '  <div class="nx-palette-input-wrap">' +
      '    <i class="bi bi-search"></i>' +
      '    <input type="text" class="nx-palette-input" id="nxPaletteInput" placeholder="Cari halaman dan aksi&hellip;" autocomplete="off">' +
      '  </div>' +
      '  <div class="nx-palette-results" id="nxPaletteResults"></div>' +
      '  <div class="nx-palette-foot">' +
      '    <span><span class="nx-kbd">&uarr;&darr;</span> pilih</span>' +
      '    <span><span class="nx-kbd">&crarr;</span> buka</span>' +
      '    <span><span class="nx-kbd">esc</span> tutup</span>' +
      '  </div>' +
      '</div>';
    document.body.insertAdjacentHTML('beforeend', html);
    $('#nxPaletteBackdrop').on('click', closePalette);
    $('#nxPaletteInput').on('input', function () { renderPalette(this.value); });
    $('#nxPaletteResults').on('click', '.nx-palette-item', function (e) {
      e.preventDefault();
      openPaletteItem($(this));
    });
  }
  function highlight(text, q) {
    if (!q) return text;
    var idx = text.toLowerCase().indexOf(q.toLowerCase());
    if (idx < 0) return text;
    return text.slice(0, idx) + '<mark>' + text.slice(idx, idx + q.length) + '</mark>' + text.slice(idx + q.length);
  }
  function renderPalette(q) {
    q = (q || '').trim();
    var groups = [];
    if (!q) {
      groups = [
        { name: 'Terakhir dibuka', items: RECENT },
        { name: 'Aksi', items: PALETTE_ACTIONS },
        { name: 'Halaman', items: PALETTE_PAGES.slice(0, 8) }
      ];
    } else {
      var match = function (it) { return it.t.toLowerCase().indexOf(q.toLowerCase()) > -1; };
      var actions = PALETTE_ACTIONS.filter(match);
      var pages = PALETTE_PAGES.filter(match);
      if (actions.length) groups.push({ name: 'Aksi', items: actions });
      if (pages.length) groups.push({ name: 'Halaman', items: pages.slice(0, 12) });
    }
    var out = '';
    groups.forEach(function (g) {
      out += '<div class="nx-palette-group">' + g.name + '</div>';
      g.items.forEach(function (it) {
        // URL halaman datang utuh dari server; hanya aksi yang memakai anchor.
        var href = it.u;
        out += '<a class="nx-palette-item" href="' + href + '" data-url="' + href + '">' +
          '<i class="bi ' + it.i + '"></i><span>' + highlight(it.t, q) + '</span>' +
          '<span class="nx-palette-path">' + (it.u.charAt(0) === '#' ? 'action' : it.u) + '</span></a>';
      });
    });
    if (!out) out = '<div class="nx-palette-empty"><i class="bi bi-search d-block fs-4 mb-2"></i>Tidak ada hasil untuk &ldquo;' + $('<span>').text(q).html() + '&rdquo;</div>';
    $('#nxPaletteResults').html(out);
    paletteIndex = 0;
    setActivePaletteItem();
  }
  function setActivePaletteItem() {
    var $items = $('#nxPaletteResults .nx-palette-item');
    $items.removeClass('active-item');
    if (!$items.length) return;
    paletteIndex = Math.max(0, Math.min(paletteIndex, $items.length - 1));
    var $cur = $items.eq(paletteIndex).addClass('active-item');
    var el = $cur[0];
    if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
  }
  function openPaletteItem($item) {
    var url = $item.data('url');
    if (url === '#theme') {
      setTheme(nxTheme() === 'dark' ? 'light' : 'dark');
      closePalette();
    } else {
      closePalette();
      go(url);
    }
  }
  function openPalette() {
    buildPalette();
    $('#nxPaletteBackdrop, #nxPalette').addClass('show');
    renderPalette('');
    $('#nxPaletteInput').val('').trigger('focus');
  }
  function closePalette() {
    $('#nxPaletteBackdrop, #nxPalette').removeClass('show');
  }
  function paletteKeys(e) {
    var isOpen = $('#nxPalette').hasClass('show');
    if ((e.metaKey || e.ctrlKey) && String(e.key).toLowerCase() === 'k') {
      e.preventDefault();
      isOpen ? closePalette() : openPalette();
      return;
    }
    if (!isOpen) return;
    var $items = $('#nxPaletteResults .nx-palette-item');
    if (e.key === 'Escape') { closePalette(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); paletteIndex++; setActivePaletteItem(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); paletteIndex--; setActivePaletteItem(); }
    else if (e.key === 'Enter') {
      e.preventDefault();
      var $cur = $items.eq(paletteIndex);
      if ($cur.length) openPaletteItem($cur);
    }
  }

  /* ===================== Sidebar: seksi menu ===================== */
  /* Sidebar punya 65 item di 9 grup — kalau semua terbuka tingginya ~3200px.
     Jadi tiap grup adalah collapse: seksi halaman aktif selalu terbuka,
     sisanya mengikuti apa yang terakhir dibuka pengguna. */
  var SECTION_KEY = 'nx-sidebar-sections';

  function readOpenSections() {
    try {
      var parsed = JSON.parse(localStorage.getItem(SECTION_KEY));
      // Hanya array yang dianggap sah; nilai rusak diperlakukan seperti belum ada.
      return Array.isArray(parsed) ? parsed : null;
    } catch (e) { return null; }
  }
  function writeOpenSections() {
    var open = $('#nxSidebar .nx-section-body.show').map(function () { return this.id; }).get();
    try { localStorage.setItem(SECTION_KEY, JSON.stringify(open)); } catch (e) { /* storage penuh/diblokir */ }
  }

  function initSidebarSections(hasActive) {
    var $sections = $('#nxSidebar .nx-section-body');
    if (!$sections.length) return;

    var stored = readOpenSections();
    if (stored) {
      stored.forEach(function (id) {
        // getElementById, bukan selektor jQuery: nilai localStorage yang rusak
        // tidak boleh sampai melempar error sintaks selektor dan menggagalkan
        // sisa inisialisasi layout.
        if (typeof id !== 'string') return;
        var el = document.getElementById(id);
        if (el && el.classList.contains('nx-section-body')) el.classList.add('show');
      });
    } else if (!hasActive) {
      // Kunjungan pertama pada halaman yang tak ada di menu: buka seksi pertama
      // supaya sidebar tidak tampak kosong.
      $sections.first().addClass('show');
    }

    // Samakan aria-expanded header dengan keadaan nyata seksinya.
    $sections.each(function () {
      $('#nxSidebar [data-bs-target="#' + this.id + '"]')
        .attr('aria-expanded', $(this).hasClass('show') ? 'true' : 'false');
    });

    $sections.on('shown.bs.collapse hidden.bs.collapse', function (e) {
      if (e.target !== this) return; // abaikan event submenu di dalamnya
      writeOpenSections();
    });
  }

  /* ===================== Sidebar ===================== */
  function closeMobileSidebar() {
    document.body.classList.remove('nx-sidebar-open');
  }

  function initSidebar() {
    var sidebarNav = document.getElementById('nxSidebarNav');
    if (sidebarNav && typeof SimpleBar !== 'undefined') new SimpleBar(sidebarNav);

    // Active menu dicocokkan lewat path lengkap yang sudah di-resolve. Rute Laravel
    // tidak memakai index.html; garis miring penutup dibuang kecuali untuk akar.
    var trim = function (p) { return p.length > 1 ? p.replace(/\/+$/, '') : p; };
    var current = trim(window.location.pathname);
    var matched = false;
    $('#nxSidebar .nx-menu-link[href]').each(function () {
      var raw = ($(this).attr('href') || '').split('#')[0];
      if (!raw) return;
      var href = trim(new URL(raw, window.location.href).pathname);
      if (href !== current) return;
      matched = true;
      $(this).addClass('active').attr('aria-current', 'page');
      // Buka SEMUA collapse induk (submenu bisa bersarang di dalam seksi grup),
      // bukan cuma yang terdekat.
      $(this).parents('.collapse').each(function () {
        var id = this.id;
        $(this).addClass('show');
        // Toggle submenu memakai href="#id", header seksi memakai data-bs-target.
        $('#nxSidebar [href="#' + id + '"], #nxSidebar [data-bs-target="#' + id + '"]')
          .attr('aria-expanded', 'true');
        $('#nxSidebar a.nx-menu-link[href="#' + id + '"]').addClass('active');
      });
      // Tandai seksi induk supaya tetap terlihat walau nanti dilipat pengguna.
      $(this).closest('.nx-menu-section').addClass('has-active');
    });

    // Server sudah membuka grup halaman aktif (termasuk halaman detail) — anggap cocok.
    initSidebarSections(matched || $('#nxSidebar .nx-section-body.show').length > 0);

    // Clone submenu menjadi flyout untuk mode collapsed.
    $('#nxSidebar .nx-menu-item.has-submenu').each(function () {
      var $ul = $(this).find('.nx-submenu').first();
      var $fly = $('<ul class="nx-flyout"></ul>').append($ul.children().clone());
      $(this).append($fly);
    });

    // Restore state collapsed.
    if (localStorage.getItem('nx-sidebar') === 'collapsed' && window.innerWidth >= 992) {
      document.body.classList.add('nx-sidebar-collapsed');
    }

    $('#nxSidebarToggle').on('click', function () {
      if (window.innerWidth < 992) {
        document.body.classList.toggle('nx-sidebar-open');
      } else {
        document.body.classList.toggle('nx-sidebar-collapsed');
        localStorage.setItem('nx-sidebar', document.body.classList.contains('nx-sidebar-collapsed') ? 'collapsed' : 'expanded');
      }
    });
    $('#nxSidebarBackdrop, #nxSidebarClose').on('click', closeMobileSidebar);
  }

  /* ===================== Filter menu sidebar =====================
     Menyaring item menu yang sudah ada. Berbeda dari command palette
     (⌘K) yang mencari seluruh halaman template.
     Prinsip: keadaan collapse Bootstrap TIDAK disentuh sama sekali —
     seksi/submenu dibuka paksa lewat class body.nx-menu-filtering di CSS,
     jadi mengosongkan kotak memulihkan tampilan persis seperti sebelumnya. */
  function initMenuFilter() {
    var $input = $('#nxMenuFilter');
    if (!$input.length) return;
    var $clear = $('#nxMenuFilterClear');
    var $nav = $('#nxSidebarNav');

    // Simpan teks label asli sekali saja; penyorotan menulis ulang innerHTML,
    // jadi teks asli harus punya sumber yang tidak ikut berubah.
    var targets = [];
    $('#nxSidebar .nx-menu-link').each(function () {
      var $link = $(this);
      // Level atas memakai <span class="nx-menu-label">, submenu memakai teks langsung.
      var $label = $link.children('.nx-menu-label');
      var el = $label.length ? $label[0] : ($link.closest('.nx-submenu').length ? $link[0] : null);
      if (!el) return;
      // Abaikan klon flyout: hanya menu asli yang disaring.
      if ($link.closest('.nx-flyout').length) return;
      targets.push({ el: el, text: el.textContent.trim(), $link: $link });
    });

    // Pesan "tidak ada hasil" ditambahkan sekali, di dalam area scroll nav.
    var $scroll = $nav.find('.simplebar-content').first();
    if (!$scroll.length) $scroll = $nav;
    $scroll.append('<div class="nx-menu-empty">Tidak ada menu yang cocok.</div>');

    function esc(s) { return $('<span>').text(s).html(); }

    function mark(t, q) {
      var i = t.text.toLowerCase().indexOf(q);
      if (i < 0) { t.el.textContent = t.text; return; }
      t.el.innerHTML = esc(t.text.slice(0, i)) + '<mark>' + esc(t.text.slice(i, i + q.length)) +
                       '</mark>' + esc(t.text.slice(i + q.length));
    }

    function reset() {
      document.body.classList.remove('nx-menu-filtering', 'nx-menu-noresult');
      targets.forEach(function (t) { t.el.textContent = t.text; });
      $('#nxSidebar .nx-filter-hide').removeClass('nx-filter-hide');
    }

    function apply(raw) {
      var q = (raw || '').trim().toLowerCase();
      $clear.prop('hidden', !q);
      if (!q) { reset(); return; }

      document.body.classList.add('nx-menu-filtering');
      targets.forEach(function (t) { mark(t, q); });

      var total = 0;
      $('#nxSidebar .nx-menu-section').each(function () {
        var $sec = $(this);
        var shown = 0;

        // Item level atas: cocok bila labelnya sendiri cocok, ATAU salah satu
        // anaknya cocok. Kalau induknya yang cocok, semua anak ikut ditampilkan.
        $sec.find('> .nx-section-body > .nx-menu-item').each(function () {
          var $item = $(this);
          var ownHit = $item.children('.nx-menu-link').find('mark').length > 0;
          var $kids = $item.find('.nx-submenu').first().children('li');
          var kidHits = 0;

          $kids.each(function () {
            var $li = $(this);
            // Sub-submenu (level 3) dihitung lewat seluruh keturunannya.
            var hit = ownHit || $li.find('mark').length > 0;
            $li.toggleClass('nx-filter-hide', !hit);
            if (hit) kidHits++;
          });

          var visible = ownHit || kidHits > 0;
          $item.toggleClass('nx-filter-hide', !visible);
          if (visible) shown++;
        });

        $sec.toggleClass('nx-filter-hide', shown === 0);
        total += shown;
      });

      // Item di footer sidebar (Documentation) ada di luar seksi mana pun,
      // jadi harus disaring terpisah — kalau tidak, ia tetap terlihat
      // meski tidak cocok dan membuat hasil "tidak ada" tampak salah.
      $('#nxSidebar .nx-sidebar-foot .nx-menu-item').each(function () {
        var hit = $(this).find('mark').length > 0;
        $(this).toggleClass('nx-filter-hide', !hit);
        if (hit) total++;
      });

      document.body.classList.toggle('nx-menu-noresult', total === 0);
    }

    $input.on('input', function () { apply(this.value); });
    $input.on('keydown', function (e) {
      if (e.key === 'Escape') { this.value = ''; apply(''); this.blur(); }
      // Enter membuka hasil pertama yang terlihat.
      if (e.key === 'Enter') {
        var first = $('#nxSidebar .nx-menu-item:not(.nx-filter-hide) .nx-menu-link[href]:not([data-bs-toggle])')
          .filter(function () { return $(this).closest('.nx-filter-hide').length === 0; })[0];
        if (first) go(first.getAttribute('href'));
      }
    });
    $clear.on('click', function () { $input.val('').trigger('focus'); apply(''); });
  }

  /* ===================== Header ===================== */
  function initHeader() {
    syncThemeIcon();
    $('#nxThemeToggle').on('click', function () {
      setTheme(nxTheme() === 'dark' ? 'light' : 'dark');
    });
    $('#nxPaletteTrigger, #nxPaletteTriggerSm').on('click', openPalette);
    $('#nxFullscreen').on('click', function () {
      if (!document.fullscreenElement) document.documentElement.requestFullscreen();
      else document.exitFullscreen();
    });
  }

  /* ===================== Utils global ===================== */
  function initUtils() {
    // Tooltip & popover Bootstrap.
    $('[data-bs-toggle="tooltip"]').each(function () { new bootstrap.Tooltip(this); });
    $('[data-bs-toggle="popover"]').each(function () { new bootstrap.Popover(this); });

    // Back to top.
    if (!document.getElementById('nxBackTop')) {
      $('body').append('<button class="nx-backtop" id="nxBackTop" aria-label="Kembali ke atas"><i class="bi bi-arrow-up"></i></button>');
    }
    $('#nxBackTop').on('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ===================== Boot =====================
     wire:navigate mengganti seluruh <body> tanpa memuat ulang halaman
     (A-421). Jadi:
     - handler di document/window dipasang SEKALI (bindOnce);
     - elemen layout dipasang ulang setiap halaman (boot), karena elemennya baru.
     livewire:navigated juga terpicu saat halaman pertama dimuat; halaman tanpa
     Livewire jatuh ke DOMContentLoaded. */
  function sidebarScroller() {
    return document.querySelector('#nxSidebarNav .simplebar-content-wrapper');
  }
  var sidebarScroll = 0;

  function boot() {
    if (document.body.hasAttribute('data-nx-layout')) {
      initSidebar();
      initMenuFilter();
      initHeader();
      var sc = sidebarScroller();
      if (sc) sc.scrollTop = sidebarScroll;
    }
    initUtils();
    buildPalette();
    document.dispatchEvent(new CustomEvent('nx:layout-ready'));
  }

  function bindOnce() {
    $(document).on('keydown', paletteKeys);
    // Hamburger di header tertutup backdrop saat panel terbuka, jadi Esc
    // dan tombol X di dalam sidebar adalah jalan keluar utamanya.
    $(document).on('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('nx-sidebar-open')) closeMobileSidebar();
    });
    $(window).on('resize', function () {
      if (window.innerWidth >= 992) closeMobileSidebar();
    });
    $(window).on('scroll', function () {
      $('#nxBackTop').toggleClass('show', window.scrollY > 300);
    });

    document.addEventListener('livewire:navigating', function (e) {
      var sc = sidebarScroller();
      sidebarScroll = sc ? sc.scrollTop : 0;
      // Livewire menyalin atribut <html> dari respons server, yang tidak membawa
      // data-bs-theme (dipasang JS) — pasang lagi tepat setelah body ditukar.
      var tema = nxTheme();
      e.detail.onSwap(function () { document.documentElement.setAttribute('data-bs-theme', tema); });
    });
    document.addEventListener('livewire:navigated', boot);
  }

  bindOnce();
  $(function () {
    if (!window.Livewire) boot();
  });
})(jQuery);
