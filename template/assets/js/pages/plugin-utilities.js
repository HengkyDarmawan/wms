/* NexaDash — plugins/utilities.html (Day.js, Fuse.js, mark.js, List.js, QRious, Lottie, Prism) */
(function ($) {
  'use strict';

  var DOCS = [
    { title: 'Deploying to Kubernetes', tag: 'Guide', text: 'Roll out the API with a blue-green strategy and zero downtime.' },
    { title: 'Configuring webhooks', tag: 'API', text: 'Verify the HMAC signature before trusting any payload.' },
    { title: 'Rate limits explained', tag: 'API', text: '600 requests per minute per key on Pro, 6,000 on Enterprise.' },
    { title: 'Single sign-on with SAML', tag: 'Security', text: 'Map IdP groups onto workspace roles automatically.' },
    { title: 'Importing from a spreadsheet', tag: 'Guide', text: 'CSV and XLSX up to 50 MB, with automatic column mapping.' },
    { title: 'Dark mode and theming', tag: 'Design', text: 'Every colour is a token, so a whole theme is one file.' },
    { title: 'Audit logs', tag: 'Security', text: 'Every write is recorded with actor, timestamp and IP address.' },
    { title: 'Billing and invoices', tag: 'Billing', text: 'Seats are pro-rated; annual plans are refundable for 30 days.' },
    { title: 'Data residency regions', tag: 'Security', text: 'Choose eu-west-1, us-east-1 or ap-southeast-1 at creation.' },
    { title: 'Building custom charts', tag: 'Design', text: 'Register a chart once and it re-renders itself on theme change.' }
  ];

  var TEAM = [
    { name: 'Sarah Chen', dept: 'Product', city: 'Singapore', img: 47 },
    { name: 'Marcus Webb', dept: 'Engineering', city: 'Berlin', img: 68 },
    { name: 'Lena Iversen', dept: 'Design', city: 'Austin', img: 32 },
    { name: 'Tom Baker', dept: 'Support', city: 'Tokyo', img: 15 },
    { name: 'Ava Novak', dept: 'Design', city: 'Jakarta', img: 5 },
    { name: 'Chen Wei', dept: 'Engineering', city: 'London', img: 25 },
    { name: 'Amina Diallo', dept: 'Sales', city: 'Toronto', img: 31 },
    { name: 'Hana Sato', dept: 'Support', city: 'Tokyo', img: 20 }
  ];

  var qr = null, markInstance = null, lottieAnim = null;

  document.addEventListener('nx:layout-ready', function () {

    /* ===================== Day.js ===================== */
    dayjs.extend(dayjs_plugin_relativeTime);

    function renderDates() {
      var raw = $('#dayInput').val();
      var d = raw ? dayjs(raw) : dayjs();
      var rows = [
        ['YYYY-MM-DD', d.format('YYYY-MM-DD')],
        ['D MMMM YYYY', d.format('D MMMM YYYY')],
        ['ddd, HH:mm', d.format('ddd, HH:mm')],
        ['ISO 8601', d.toISOString()],
        ['Relative to now', d.fromNow()],
        ['Start of month', d.startOf('month').format('D MMM YYYY')],
        ['Plus 45 days', d.add(45, 'day').format('D MMM YYYY')],
        ['Unix timestamp', String(d.unix())],
        ['Day of year', String(d.diff(d.startOf('year'), 'day') + 1)]
      ];
      $('#dayTable').html(rows.map(function (r) {
        return '<tr><td class="text-muted"><code>' + r[0] + '</code></td><td class="nx-num">' + r[1] + '</td></tr>';
      }).join(''));
    }
    $('#dayInput').val(dayjs().format('YYYY-MM-DDTHH:mm')).on('input', renderDates);
    renderDates();

    /* ===================== Fuse.js ===================== */
    var fuse = new Fuse(DOCS, { keys: ['title', 'text', 'tag'], threshold: 0.42, includeScore: true });

    function renderFuse(q) {
      var list = q ? fuse.search(q).map(function (r) { return r.item; }) : DOCS;
      $('#fuseCount').text(list.length);
      $('#fuseResults').html(list.length ? list.map(function (d) {
        return '<div class="nx-row-item align-items-start">' +
          '<span class="nx-icon-sq secondary"><i class="bi bi-file-earmark-text"></i></span>' +
          '<div class="nx-row-main"><div class="nx-row-title">' + d.title + '</div>' +
          '<div class="nx-row-sub">' + d.text + '</div></div>' +
          '<span class="badge badge-soft-secondary">' + d.tag + '</span></div>';
      }).join('') : '<p class="text-muted small text-center py-4 mb-0">Nothing matched, even fuzzily.</p>');
    }
    $('#fuseInput').on('input', function () { renderFuse(this.value.trim()); });
    renderFuse('');

    /* ===================== mark.js ===================== */
    markInstance = new Mark(document.getElementById('markTarget'));
    function runMark() {
      var term = $('#markInput').val();
      markInstance.unmark({
        done: function () { if (term) markInstance.mark(term, { separateWordSearch: false }); }
      });
    }
    $('#markInput').on('input', runMark);
    $('#markClear').on('click', function () { $('#markInput').val(''); runMark(); });
    runMark();

    /* ===================== List.js ===================== */
    $('#listContainer .list').html(TEAM.map(function (t) {
      return '<div class="nx-row-item">' +
        '<span class="nx-avatar"><img src="https://i.pravatar.cc/64?img=' + t.img + '" alt=""></span>' +
        '<div class="nx-row-main"><div class="nx-row-title name">' + t.name + '</div>' +
        '<div class="nx-row-sub city">' + t.city + '</div></div>' +
        '<span class="badge badge-soft-primary dept">' + t.dept + '</span></div>';
    }).join(''));
    new List('listContainer', { valueNames: ['name', 'dept', 'city'], listClass: 'list' });

    /* ===================== QRious ===================== */
    var canvas = document.getElementById('qrCanvas');
    qr = new QRious({ element: canvas, value: 'https://nexadash.io', size: 200, level: 'H', foreground: '#0f172a', background: '#ffffff' });

    function updateQr() {
      qr.value = $('#qrValue').val() || ' ';
      qr.size = parseInt($('#qrSize').val(), 10);
      qr.foreground = $('#qrColor').val();
      $('#qrSizeLabel').text(qr.size + ' px');
      $('#qrDownload').attr('href', canvas.toDataURL('image/png'));
    }
    $('#qrValue, #qrSize, #qrColor').on('input', updateQr);
    updateQr();

    /* ===================== Prism ===================== */
    Prism.highlightAll();

    /* ===================== Lottie ===================== */
    lottieAnim = lottie.loadAnimation({
      container: document.getElementById('lottieBox'),
      renderer: 'svg',
      loop: true,
      autoplay: true,
      // Data disematkan, bukan di-fetch, agar tetap jalan di file://
      animationData: window.NX_LOTTIE_PULSE
    });
    $('#lottiePlay').on('click', function () { lottieAnim.play(); });
    $('#lottiePause').on('click', function () { lottieAnim.pause(); });
    $('#lottieStop').on('click', function () { lottieAnim.stop(); });
    $('#lottieSpeed').on('input', function () {
      lottieAnim.setSpeed(parseFloat(this.value));
      $('#lottieSpeedLabel').text(this.value + '×');
    });
  });
})(jQuery);
