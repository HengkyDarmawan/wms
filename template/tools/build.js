#!/usr/bin/env node
/* ============================================================
   NexaDash — generator halaman statis.
   Membungkus body-fragment di tools/body/*.html dengan shell
   HTML identik (head, vendor, layout mount, script).
   Jalankan: node tools/build.js
   Output-nya file HTML statis biasa — tidak dibutuhkan saat runtime.
   ============================================================ */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const BODY = path.join(__dirname, 'body');
const PARTIALS = path.join(ROOT, 'partials');

/* Partial di-inline ke setiap halaman saat generate, bukan di-fetch saat runtime,
   supaya halaman tetap utuh ketika dibuka langsung lewat file://. */
const readPartial = name => fs.readFileSync(path.join(PARTIALS, name + '.html'), 'utf8').trimEnd();
const SIDEBAR = readPartial('sidebar');
const HEADER = readPartial('header');
const FOOTER = readPartial('footer');

const indent = (html, pad) => html.split('\n').map(l => (l ? pad + l : l)).join('\n');

/* ---------- Vendor assets ---------- */
const CSS = {
  simplebar: '<link href="https://cdn.jsdelivr.net/npm/simplebar@6.2.7/dist/simplebar.min.css" rel="stylesheet">',
  flatpickr: '<link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">',
  datatables: '<link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">\n  <link href="https://cdn.datatables.net/buttons/3.1.2/css/buttons.bootstrap5.min.css" rel="stylesheet">',
  select2: '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">\n  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">',
  nouislider: '<link href="https://cdn.jsdelivr.net/npm/nouislider@15.8.1/dist/nouislider.min.css" rel="stylesheet">',
  quill: '<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">',
  dropzone: '<link href="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.css" rel="stylesheet">',

  /* ---------- Plugin showcase ---------- */
  leaflet: '<link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">\n  <link href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" rel="stylesheet">\n  <link href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" rel="stylesheet">',
  gridjs: '<link href="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/theme/mermaid.min.css" rel="stylesheet">',
  tabulator: '<link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">',
  swiper: '<link href="https://cdn.jsdelivr.net/npm/swiper@11.1.14/swiper-bundle.min.css" rel="stylesheet">',
  glightbox: '<link href="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/css/glightbox.min.css" rel="stylesheet">',
  cropper: '<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">',
  tomselect: '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">',
  pickr: '<link href="https://cdn.jsdelivr.net/npm/@simonwep/pickr@1.9.1/dist/themes/nano.min.css" rel="stylesheet">',
  driver: '<link href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css" rel="stylesheet">',
  tippy: '<link href="https://cdn.jsdelivr.net/npm/tippy.js@6.3.7/dist/tippy.css" rel="stylesheet">',
  nprogress: '<link href="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.css" rel="stylesheet">',
  toastify: '<link href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.css" rel="stylesheet">',
  aos: '<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">',
  gridstack: '<link href="https://cdn.jsdelivr.net/npm/gridstack@11.3.0/dist/gridstack.min.css" rel="stylesheet">',
  prism: '<link href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet">'
};
const JS = {
  simplebar: '<script src="https://cdn.jsdelivr.net/npm/simplebar@6.2.7/dist/simplebar.min.js"></script>',
  apex: '<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>',
  chartjs: '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>',
  flatpickr: '<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>',
  countup: '<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.8.0/countUp.umd.min.js"></script>',
  swal: '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>',
  datatables: '<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>\n  <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>\n  <script src="https://cdn.datatables.net/buttons/3.1.2/js/dataTables.buttons.min.js"></script>\n  <script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.bootstrap5.min.js"></script>\n  <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>\n  <script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.html5.min.js"></script>\n  <script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.print.min.js"></script>\n  <script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.colVis.min.js"></script>',
  select2: '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>',
  nouislider: '<script src="https://cdn.jsdelivr.net/npm/nouislider@15.8.1/dist/nouislider.min.js"></script>',
  quill: '<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>',
  dropzone: '<script src="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.js"></script>',
  fullcalendar: '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>',
  sortable: '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>',
  inputmask: '<script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.9/dist/inputmask.min.js"></script>',

  /* ---------- Plugin showcase ---------- */
  leaflet: '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>\n  <script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>\n  <script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>',
  gridjs: '<script src="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/gridjs.umd.js"></script>',
  tabulator: '<script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>',
  swiper: '<script src="https://cdn.jsdelivr.net/npm/swiper@11.1.14/swiper-bundle.min.js"></script>',
  glightbox: '<script src="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js"></script>',
  cropper: '<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>',
  tomselect: '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/js/tom-select.complete.min.js"></script>',
  signaturepad: '<script src="https://cdn.jsdelivr.net/npm/signature_pad@5.0.4/dist/signature_pad.umd.min.js"></script>',
  pickr: '<script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr@1.9.1/dist/pickr.min.js"></script>',
  cleave: '<script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>',
  autosize: '<script src="https://cdn.jsdelivr.net/npm/autosize@6.0.1/dist/autosize.min.js"></script>',
  jspdf: '<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>\n  <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>',
  html2canvas: '<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>',
  xlsx: '<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>',
  pdfjs: '<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>',
  driver: '<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script>',
  tippy: '<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>\n  <script src="https://cdn.jsdelivr.net/npm/tippy.js@6.3.7/dist/tippy-bundle.umd.min.js"></script>',
  nprogress: '<script src="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.js"></script>',
  clipboard: '<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.11/dist/clipboard.min.js"></script>',
  toastify: '<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.js"></script>',
  aos: '<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>',
  typed: '<script src="https://cdn.jsdelivr.net/npm/typed.js@2.1.0/dist/typed.umd.js"></script>',
  gridstack: '<script src="https://cdn.jsdelivr.net/npm/gridstack@11.3.0/dist/gridstack-all.js"></script>',
  interact: '<script src="https://cdn.jsdelivr.net/npm/interactjs@1.10.27/dist/interact.min.js"></script>',
  dayjs: '<script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/dayjs.min.js"></script>\n  <script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/plugin/relativeTime.js"></script>',
  fuse: '<script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0/dist/fuse.min.js"></script>',
  markjs: '<script src="https://cdn.jsdelivr.net/npm/mark.js@8.11.1/dist/mark.min.js"></script>',
  listjs: '<script src="https://cdn.jsdelivr.net/npm/list.js@2.3.1/dist/list.min.js"></script>',
  qrious: '<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>',
  lottie: '<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>',
  prism: '<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.js"></script>',
  mermaid: '<script src="https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.min.js"></script>',
  orgchart: '<script src="https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js"></script>\n  <script src="https://cdn.jsdelivr.net/npm/d3-flextree@2.1.2/build/d3-flextree.js"></script>\n  <script src="https://cdn.jsdelivr.net/npm/d3-org-chart@3.1.1/build/d3-org-chart.js"></script>'
};

const ROUTES = require('./routes.js');

/* Semua href/src di body dan partial ditulis dengan nama kanonik datar
   (mis. "ui-buttons.html", "assets/css/app.css"). Di sini nama itu
   diterjemahkan ke path relatif yang benar untuk kedalaman folder halaman. */
function rewriteLinks(html, prefix) {
  return html.replace(/(href|src)="([^"]+)"/g, (full, attr, value) => {
    if (/^(https?:|\/\/|#|mailto:|tel:|data:)/.test(value)) return full;
    const hash = value.indexOf('#');
    const base = hash === -1 ? value : value.slice(0, hash);
    const frag = hash === -1 ? '' : value.slice(hash);
    if (!base) return full;
    if (ROUTES[base]) return `${attr}="${prefix}${ROUTES[base]}${frag}"`;
    if (base.startsWith('assets/')) return `${attr}="${prefix}${base}${frag}"`;
    return full;
  });
}

function shell(p, body) {
  const css = (p.css || []).map(k => CSS[k]).join('\n  ');
  const js = (p.js || []).map(k => JS[k]).join('\n  ');
  const dataJs = (p.data || []).map(f => `\n  <script src="assets/js/data/${f}"></script>`).join('');
  const pageJs = dataJs + (p.pageJs ? `\n  <script src="assets/js/pages/${p.pageJs}"></script>` : '');
  const standalone = !!p.standalone;

  const head = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${p.title} | NexaDash</title>
  <link rel="icon" href="assets/img/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  ${css}
  <link href="assets/css/variables.css" rel="stylesheet">
  <link href="assets/css/layout.css" rel="stylesheet">
  <link href="assets/css/components.css" rel="stylesheet">
  <link href="assets/css/pages.css" rel="stylesheet">
  <link href="assets/css/motion.css" rel="stylesheet">
  <link href="assets/css/dark.css" rel="stylesheet">
  <script src="assets/js/theme-init.js"></script>
</head>`;

  const scripts = `  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  ${js}
  <script src="assets/js/app.js"></script>${pageJs}`;

  const route = ROUTES[p.file] || p.file;
  const prefix = '../'.repeat(route.split('/').length - 1);

  if (standalone) {
    return rewriteLinks(`${head}
<body class="${p.bodyClass || ''}" data-nx-root="${prefix}">

${body}

${scripts}
</body>
</html>
`, prefix);
  }

  const bodyAttrs = ['data-nx-layout="app"', `data-nx-root="${prefix}"`];
  if (p.bodyClass) bodyAttrs.push(`class="${p.bodyClass}"`);
  if (p.quickAction) bodyAttrs.push(`data-quick-action="${p.quickAction}"`);
  if (p.quickActionHref) {
    const target = ROUTES[p.quickActionHref] || p.quickActionHref;
    bodyAttrs.push(`data-quick-action-href="${prefix}${target}"`);
  }

  const crumbs = (p.breadcrumb || []).map((c, i, arr) => {
    if (i === arr.length - 1) return `<li class="breadcrumb-item active" aria-current="page">${c.t}</li>`;
    // Segmen tanpa URL hanya menandai bagian, bukan tautan.
    return c.u
      ? `<li class="breadcrumb-item"><a href="${c.u}">${c.t}</a></li>`
      : `<li class="breadcrumb-item">${c.t}</li>`;
  }).join('\n              ');

  const pageHead = p.noHead ? '' : `      <!-- ===== Page header ===== -->
      <div class="nx-page-head d-flex flex-wrap align-items-end gap-3">
        <div class="me-auto">
          <nav class="nx-breadcrumb" aria-label="Breadcrumb">
            <ol class="breadcrumb">
              ${crumbs}
            </ol>
          </nav>
          <h1>${p.h1 || p.title}</h1>
          ${p.sub ? `<p class="text-muted mb-0">${p.sub}</p>` : ''}
        </div>
        ${p.actions || ''}
      </div>

`;

  return rewriteLinks(`${head}
<body ${bodyAttrs.join(' ')}>

  <a class="nx-skip-link" href="#nxContent">Skip to main content</a>

${indent(SIDEBAR, '  ')}

  <div class="nx-main">

${indent(HEADER, '    ')}

    <main class="nx-content" id="nxContent" tabindex="-1">
${pageHead}${body}
    </main>

${indent(FOOTER, '    ')}
  </div>

${scripts}
</body>
</html>
`, prefix);
}

const manifest = require('./pages.js');
let count = 0;
manifest.forEach(p => {
  const route = ROUTES[p.file];
  if (!route) {
    console.warn('  ! no route for:', p.file);
    return;
  }
  // Fragmen body disimpan mengikuti struktur folder keluaran.
  const bodyFile = path.join(BODY, route);
  if (!fs.existsSync(bodyFile)) {
    console.warn('  ! body missing:', route);
    return;
  }
  const body = fs.readFileSync(bodyFile, 'utf8');
  const out = path.join(ROOT, route);
  fs.mkdirSync(path.dirname(out), { recursive: true });
  fs.writeFileSync(out, shell(p, body));
  count++;
});
/* Peta rute juga diekspor untuk JS sisi klien (katalog plugin menautkan
   halaman demo lewat nama kanonik yang sama). */
fs.writeFileSync(
  path.join(ROOT, 'assets/js/data/routes.js'),
  '/* NexaDash — dibuat otomatis oleh tools/build.js. Jangan diedit manual. */\n' +
  'window.NX_ROUTES = ' + JSON.stringify(ROUTES, null, 2) + ';\n'
);

console.log('Generated ' + count + ' pages + assets/js/data/routes.js');
