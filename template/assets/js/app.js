/* ============================================================
   NexaDash — app.js
   Global init: layout partials, theme, sidebar, command palette,
   tooltips, CountUp, back-to-top, chart helpers.
   ============================================================ */
(function ($) {
  'use strict';

  /* ===================== Helpers publik ===================== */
  window.nxCss = function (name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  };
  window.nxTheme = function () {
    return document.documentElement.getAttribute('data-bs-theme') || 'light';
  };
  // Awalan relatif menuju akar template ('' di root, '../' untuk halaman dalam folder).
  window.nxRoot = function () {
    return document.body.getAttribute('data-nx-root') || '';
  };
  window.nxChartColors = function () {
    return [nxCss('--nx-chart-1'), nxCss('--nx-chart-2'), nxCss('--nx-chart-3'), nxCss('--nx-chart-4'), nxCss('--nx-chart-5')];
  };
  // Opsi dasar ApexCharts yang theme-aware — merge ke setiap chart.
  window.nxApexBase = function () {
    return {
      chart: {
        fontFamily: 'Inter, system-ui, sans-serif',
        foreColor: nxCss('--nx-text-muted'),
        background: 'transparent',
        toolbar: { show: false }
      },
      grid: { borderColor: nxCss('--nx-border'), strokeDashArray: 4 },
      tooltip: { theme: nxTheme() }
    };
  };
  // Registry chart: setiap chart didaftarkan dengan fungsi build()
  // agar bisa dibangun ulang saat tema berubah.
  var chartRegistry = [];
  window.nxMountChart = function (selector, build) {
    var el = document.querySelector(selector);
    if (!el || typeof ApexCharts === 'undefined') return null;
    var entry = { el: el, build: build, chart: new ApexCharts(el, build()) };
    entry.chart.render();
    chartRegistry.push(entry);
    return entry.chart;
  };
  document.addEventListener('nx:theme-changed', function () {
    chartRegistry.forEach(function (r) {
      r.chart.destroy();
      r.chart = new ApexCharts(r.el, r.build());
      r.chart.render();
    });
  });

  /* ===================== Theme toggle ===================== */
  function setTheme(t) {
    document.documentElement.setAttribute('data-bs-theme', t);
    localStorage.setItem('nx-theme', t);
    syncThemeIcon();
    document.dispatchEvent(new CustomEvent('nx:theme-changed', { detail: { theme: t } }));
  }
  function syncThemeIcon() {
    var btn = document.getElementById('nxThemeToggle');
    if (!btn) return;
    btn.innerHTML = nxTheme() === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
  }

  /* ===================== Command palette ===================== */
  var PALETTE_PAGES = [
    { g: 'Pages', t: 'Dashboard', u: 'index.html', i: 'bi-grid-1x2' },
    { g: 'Pages', t: 'Analytics', u: 'dashboards/analytics.html', i: 'bi-graph-up' },
    { g: 'Pages', t: 'eCommerce Dashboard', u: 'dashboards/ecommerce.html', i: 'bi-bag' },
    { g: 'Pages', t: 'CRM Dashboard', u: 'dashboards/crm.html', i: 'bi-people' },
    { g: 'Pages', t: 'Clinical Dashboard', u: 'dashboards/clinical.html', i: 'bi-heart-pulse' },
    { g: 'Pages', t: 'ApexCharts', u: 'charts/apexcharts.html', i: 'bi-bar-chart' },
    { g: 'Pages', t: 'Chart.js', u: 'charts/chartjs.html', i: 'bi-bar-chart' },
    { g: 'Pages', t: 'Sparkline', u: 'charts/sparkline.html', i: 'bi-bar-chart' },
    { g: 'Pages', t: 'Orders', u: 'commerce/orders.html', i: 'bi-receipt' },
    { g: 'Pages', t: 'Products', u: 'commerce/products.html', i: 'bi-box-seam' },
    { g: 'Pages', t: 'Product Detail', u: 'commerce/product-detail.html', i: 'bi-box-seam' },
    { g: 'Pages', t: 'Customers', u: 'commerce/customers.html', i: 'bi-person-badge' },
    { g: 'Pages', t: 'Invoices', u: 'commerce/invoices.html', i: 'bi-file-earmark-text' },
    { g: 'Pages', t: 'Invoice Detail', u: 'commerce/invoice-detail.html', i: 'bi-file-earmark-text' },
    { g: 'Pages', t: 'Mail', u: 'apps/mail.html', i: 'bi-envelope' },
    { g: 'Pages', t: 'Chat', u: 'apps/chat.html', i: 'bi-chat-dots' },
    { g: 'Pages', t: 'Files', u: 'apps/files.html', i: 'bi-folder' },
    { g: 'Pages', t: 'Kanban', u: 'apps/kanban.html', i: 'bi-kanban' },
    { g: 'Pages', t: 'Calendar', u: 'apps/calendar.html', i: 'bi-calendar3' },
    { g: 'Pages', t: 'Wizard', u: 'apps/wizard.html', i: 'bi-magic' },
    { g: 'Pages', t: 'Buttons', u: 'ui/buttons.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Cards', u: 'ui/cards.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Modals', u: 'ui/modals.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Tabs & Accordions', u: 'ui/tabs-accordions.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Alerts & Badges', u: 'ui/alerts-badges.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Progress & Spinners', u: 'ui/progress-spinners.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Tooltips & Popovers', u: 'ui/tooltips-popovers.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Toasts & Notifications', u: 'ui/toasts.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Dropdowns', u: 'ui/dropdowns.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Avatars & Images', u: 'ui/avatars-images.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Pagination & Breadcrumbs', u: 'ui/pagination-breadcrumbs.html', i: 'bi-palette' },
    { g: 'Pages', t: 'List Group & Timeline', u: 'ui/list-group-timeline.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Offcanvas & Placeholders', u: 'ui/offcanvas-placeholders.html', i: 'bi-palette' },
    { g: 'Pages', t: 'Typography', u: 'ui/typography.html', i: 'bi-fonts' },
    { g: 'Pages', t: 'Icons', u: 'ui/icons.html', i: 'bi-emoji-smile' },
    { g: 'Pages', t: 'Colors', u: 'ui/colors.html', i: 'bi-droplet' },
    { g: 'Pages', t: 'Form Elements', u: 'forms/elements.html', i: 'bi-input-cursor-text' },
    { g: 'Pages', t: 'Form Layouts', u: 'forms/layouts.html', i: 'bi-input-cursor-text' },
    { g: 'Pages', t: 'Form Validation', u: 'forms/validation.html', i: 'bi-input-cursor-text' },
    { g: 'Pages', t: 'Select2', u: 'forms/select2.html', i: 'bi-input-cursor-text' },
    { g: 'Pages', t: 'Pickers', u: 'forms/pickers.html', i: 'bi-calendar-week' },
    { g: 'Pages', t: 'Editors', u: 'forms/editors.html', i: 'bi-pencil-square' },
    { g: 'Pages', t: 'File Upload', u: 'forms/upload.html', i: 'bi-cloud-arrow-up' },
    { g: 'Pages', t: 'Input Mask', u: 'forms/input-mask.html', i: 'bi-input-cursor' },
    { g: 'Pages', t: 'Basic Tables', u: 'tables/basic.html', i: 'bi-table' },
    { g: 'Pages', t: 'DataTables', u: 'tables/datatables.html', i: 'bi-table' },
    { g: 'Pages', t: 'Profile', u: 'pages/profile.html', i: 'bi-person-circle' },
    { g: 'Pages', t: 'Settings', u: 'pages/settings.html', i: 'bi-gear' },
    { g: 'Pages', t: 'Pricing', u: 'pages/pricing.html', i: 'bi-tags' },
    { g: 'Pages', t: 'FAQ', u: 'pages/faq.html', i: 'bi-question-circle' },
    { g: 'Pages', t: 'Timeline', u: 'pages/timeline.html', i: 'bi-clock-history' },
    { g: 'Pages', t: 'Users', u: 'pages/users.html', i: 'bi-people-fill' },
    { g: 'Pages', t: 'Notifications', u: 'pages/notifications.html', i: 'bi-bell' },
    { g: 'Pages', t: 'Roadmap', u: 'pages/roadmap.html', i: 'bi-signpost-split' },
    { g: 'Pages', t: 'Activity', u: 'pages/activity.html', i: 'bi-activity' },
    { g: 'Pages', t: 'Changelog', u: 'pages/changelog.html', i: 'bi-journal-code' },
    { g: 'Pages', t: 'Login', u: 'auth/login.html', i: 'bi-shield-lock' },
    { g: 'Pages', t: 'Register', u: 'auth/register.html', i: 'bi-shield-lock' },
    { g: 'Pages', t: 'Forgot Password', u: 'auth/forgot-password.html', i: 'bi-shield-lock' },
    { g: 'Pages', t: 'Lock Screen', u: 'auth/lock-screen.html', i: 'bi-shield-lock' },
    { g: 'Pages', t: 'Two Factor', u: 'auth/two-factor.html', i: 'bi-shield-lock' },
    { g: 'Pages', t: '404 Not Found', u: 'errors/404.html', i: 'bi-exclamation-triangle' },
    { g: 'Pages', t: '500 Server Error', u: 'errors/500.html', i: 'bi-exclamation-triangle' },
    { g: 'Pages', t: 'Maintenance', u: 'errors/maintenance.html', i: 'bi-cone-striped' }
  ];
  var PALETTE_ACTIONS = [
    { g: 'Actions', t: 'New Order', u: 'commerce/orders.html', i: 'bi-plus-lg' },
    { g: 'Actions', t: 'Toggle Theme', u: '#theme', i: 'bi-moon-stars' },
    { g: 'Actions', t: 'Lock Screen', u: 'auth/lock-screen.html', i: 'bi-lock' }
  ];
  var RECENT = [PALETTE_PAGES[0], PALETTE_PAGES[8], PALETTE_PAGES[17]];
  var paletteIndex = 0;

  function buildPalette() {
    if (document.getElementById('nxPalette')) return;
    var html =
      '<div class="nx-palette-backdrop" id="nxPaletteBackdrop"></div>' +
      '<div class="nx-palette" id="nxPalette" role="dialog" aria-modal="true" aria-label="Command palette">' +
      '  <div class="nx-palette-input-wrap">' +
      '    <i class="bi bi-search"></i>' +
      '    <input type="text" class="nx-palette-input" id="nxPaletteInput" placeholder="Search for pages, actions, and quick links&hellip;" autocomplete="off">' +
      '  </div>' +
      '  <div class="nx-palette-results" id="nxPaletteResults"></div>' +
      '  <div class="nx-palette-foot">' +
      '    <span><span class="nx-kbd">&uarr;&darr;</span> navigate</span>' +
      '    <span><span class="nx-kbd">&crarr;</span> open</span>' +
      '    <span><span class="nx-kbd">esc</span> close</span>' +
      '  </div>' +
      '</div>';
    document.body.insertAdjacentHTML('beforeend', html);
    $('#nxPaletteBackdrop').on('click', closePalette);
    $('#nxPaletteInput').on('input', function () { renderPalette(this.value); });
    $(document).on('keydown', paletteKeys);
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
        { name: 'Recent', items: RECENT },
        { name: 'Actions', items: PALETTE_ACTIONS },
        { name: 'Pages', items: PALETTE_PAGES.slice(0, 8) }
      ];
    } else {
      var match = function (it) { return it.t.toLowerCase().indexOf(q.toLowerCase()) > -1; };
      var actions = PALETTE_ACTIONS.filter(match);
      var pages = PALETTE_PAGES.filter(match);
      if (actions.length) groups.push({ name: 'Actions', items: actions });
      if (pages.length) groups.push({ name: 'Pages', items: pages.slice(0, 12) });
    }
    var out = '';
    groups.forEach(function (g) {
      out += '<div class="nx-palette-group">' + g.name + '</div>';
      g.items.forEach(function (it) {
        // Rute disimpan relatif terhadap akar template, jadi diberi awalan per halaman.
        var href = it.u.charAt(0) === '#' ? it.u : nxRoot() + it.u;
        out += '<a class="nx-palette-item" href="' + href + '" data-url="' + href + '">' +
          '<i class="bi ' + it.i + '"></i><span>' + highlight(it.t, q) + '</span>' +
          '<span class="nx-palette-path">' + (it.u.charAt(0) === '#' ? 'action' : it.u) + '</span></a>';
      });
    });
    if (!out) out = '<div class="nx-palette-empty"><i class="bi bi-search d-block fs-4 mb-2"></i>No results for &ldquo;' + $('<span>').text(q).html() + '&rdquo;</div>';
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
      window.location.href = url;
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

    // Active menu dicocokkan lewat path lengkap yang sudah di-resolve, bukan nama
    // berkas, karena beberapa rute memakai nama sama (index.html di root dan di plugins/).
    var current = window.location.pathname.replace(/\/$/, '/index.html');
    var matched = false;
    $('#nxSidebar .nx-menu-link[href]').each(function () {
      var raw = ($(this).attr('href') || '').split('#')[0];
      if (!raw) return;
      var href = new URL(raw, window.location.href).pathname;
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

    initSidebarSections(matched);

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
    // Hamburger di header tertutup backdrop saat panel terbuka, jadi Esc
    // dan tombol X di dalam sidebar adalah jalan keluar utamanya.
    $(document).on('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('nx-sidebar-open')) closeMobileSidebar();
    });
    $(window).on('resize', function () {
      if (window.innerWidth >= 992) closeMobileSidebar();
    });
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
    $scroll.append('<div class="nx-menu-empty">No menu matches that.</div>');

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
        if (first) window.location.href = first.getAttribute('href');
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
    // Label aksi cepat kontekstual (mis. halaman clinical).
    var qa = document.body.getAttribute('data-quick-action');
    if (qa) $('#nxQuickAction span').text(qa);
    var qaHref = document.body.getAttribute('data-quick-action-href') || 'commerce-orders.html';
    $('#nxQuickAction').on('click', function () { window.location.href = qaHref; });
  }

  /* ===================== Utils global ===================== */
  function initUtils() {
    // Tooltip & popover Bootstrap.
    $('[data-bs-toggle="tooltip"]').each(function () { new bootstrap.Tooltip(this); });
    $('[data-bs-toggle="popover"]').each(function () { new bootstrap.Popover(this); });

    // CountUp untuk [data-countup].
    if (window.countUp && window.countUp.CountUp) {
      $('[data-countup]').each(function () {
        var $el = $(this);
        var c = new countUp.CountUp(this, parseFloat($el.data('value')), {
          duration: 1.6,
          decimalPlaces: parseInt($el.data('decimals') || 0, 10),
          prefix: $el.data('prefix') || '',
          suffix: $el.data('suffix') || ''
        });
        if (!c.error) c.start();
      });
    }

    // Back to top.
    if (!document.getElementById('nxBackTop')) {
      $('body').append('<button class="nx-backtop" id="nxBackTop" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>');
    }
    $(window).on('scroll', function () {
      $('#nxBackTop').toggleClass('show', window.scrollY > 300);
    });
    $('#nxBackTop').on('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ===================== Boot =====================
     Sidebar, header dan footer sudah ter-inline di setiap halaman oleh
     tools/build.js, jadi tidak ada fetch saat runtime dan halaman tetap
     berfungsi penuh ketika dibuka langsung lewat file://. */
  $(function () {
    if (document.body.hasAttribute('data-nx-layout')) {
      initSidebar();
      initMenuFilter();
      initHeader();
    }
    initUtils();
    buildPalette();
    document.dispatchEvent(new CustomEvent('nx:layout-ready'));
  });
})(jQuery);
