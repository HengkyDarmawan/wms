/* NexaDash — manifest halaman untuk tools/build.js */
const BASE_JS = ['simplebar'];
const HOME = { t: 'Home', u: 'index.html' };

const btn = (icon, label, cls, href) =>
  `<a href="${href || '#'}" class="btn ${cls} d-inline-flex align-items-center gap-1"><i class="bi ${icon}"></i>${label}</a>`;

module.exports = [
  /* ============ Dashboards ============ */
  {
    file: 'index.html', title: 'Dashboard', noHead: true,
    css: ['simplebar', 'flatpickr'], js: [...BASE_JS, 'apex', 'flatpickr', 'countup', 'swal'],
    pageJs: 'dashboard.js'
  },
  {
    file: 'dashboard-analytics.html', title: 'Analytics', h1: 'Analytics',
    sub: 'Traffic, engagement and conversion across all channels.',
    breadcrumb: [HOME, { t: 'Dashboards' }, { t: 'Analytics' }],
    css: ['simplebar', 'flatpickr'], js: [...BASE_JS, 'apex', 'flatpickr', 'countup', 'swal'],
    pageJs: 'analytics.js',
    actions: `<div class="d-flex gap-2">
          <div class="input-group input-group-sm" style="width:220px"><span class="input-group-text"><i class="bi bi-calendar-range"></i></span><input type="text" class="form-control" id="anaRange" aria-label="Date range"></div>
          <button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" type="button"><i class="bi bi-download"></i>Download Report</button>
        </div>`
  },
  {
    file: 'dashboard-ecommerce.html', title: 'eCommerce', h1: 'eCommerce Dashboard',
    sub: 'Store performance, orders and best selling products.',
    breadcrumb: [HOME, { t: 'Dashboards' }, { t: 'eCommerce' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup', 'swal'], pageJs: 'ecommerce.js',
    actions: btn('bi-plus-lg', 'New Product', 'btn-primary btn-sm', 'commerce-products.html')
  },
  {
    file: 'dashboard-crm.html', title: 'CRM', h1: 'CRM Dashboard',
    sub: 'Pipeline health, deals and lead activity.',
    breadcrumb: [HOME, { t: 'Dashboards' }, { t: 'CRM' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup', 'swal'], pageJs: 'crm.js',
    actions: btn('bi-plus-lg', 'Add Lead', 'btn-primary btn-sm', '#')
  },
  {
    file: 'dashboard-clinical.html', title: 'Clinical', h1: 'Welcome back, Dr. Smith',
    sub: 'Here is the clinical overview for today, September 11, 2026.',
    breadcrumb: [HOME, { t: 'Dashboards' }, { t: 'Clinical' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup', 'swal'], pageJs: 'clinical.js',
    quickAction: 'New Appointment', quickActionHref: 'apps-calendar.html',
    actions: btn('bi-printer', 'Print Schedule', 'btn-outline-primary btn-sm', '#')
  },

  /* ============ Charts ============ */
  {
    file: 'chart-apex.html', title: 'ApexCharts', h1: 'ApexCharts',
    sub: 'Every chart type used across the template, theme-aware.',
    breadcrumb: [HOME, { t: 'Charts' }, { t: 'ApexCharts' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex'], pageJs: 'chart-apex.js'
  },
  {
    file: 'chart-chartjs.html', title: 'Chart.js', h1: 'Chart.js',
    sub: 'Line, bar, doughnut, polar, radar and bubble charts.',
    breadcrumb: [HOME, { t: 'Charts' }, { t: 'Chart.js' }],
    css: ['simplebar'], js: [...BASE_JS, 'chartjs'], pageJs: 'chart-chartjs.js'
  },
  {
    file: 'chart-sparkline.html', title: 'Sparkline', h1: 'Sparkline Charts',
    sub: 'Compact KPI tiles with inline trend charts.',
    breadcrumb: [HOME, { t: 'Charts' }, { t: 'Sparkline' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup'], pageJs: 'chart-sparkline.js'
  },

  /* ============ Apps ============ */
  {
    file: 'apps-mail.html', title: 'Mail', h1: 'Mail', sub: 'Inbox with folders, labels and reading pane.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Mail' }],
    css: ['simplebar', 'quill'], js: [...BASE_JS, 'quill', 'swal'], pageJs: 'mail.js',
    actions: `<button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="modal" data-bs-target="#composeModal"><i class="bi bi-pencil-square"></i>Compose</button>`
  },
  {
    file: 'apps-chat.html', title: 'Chat', h1: 'Chat', sub: 'Team conversations in real time.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Chat' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'chat.js'
  },
  {
    file: 'apps-files.html', title: 'Files', h1: 'File Manager', sub: 'Browse, upload and share project files.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Files' }],
    css: ['simplebar', 'dropzone'], js: [...BASE_JS, 'apex', 'dropzone', 'swal'], pageJs: 'files.js',
    actions: `<button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-cloud-arrow-up"></i>Upload</button>`
  },
  {
    file: 'apps-kanban.html', title: 'Kanban', h1: 'Kanban Board', sub: 'Drag cards between columns to update status.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Kanban' }],
    css: ['simplebar'], js: [...BASE_JS, 'sortable', 'swal'], pageJs: 'kanban.js'
  },
  {
    file: 'apps-calendar.html', title: 'Calendar', h1: 'Calendar', sub: 'Schedule meetings, deadlines and holidays.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Calendar' }],
    css: ['simplebar', 'flatpickr', 'select2'], js: [...BASE_JS, 'fullcalendar', 'flatpickr', 'select2', 'swal'], pageJs: 'calendar.js'
  },
  {
    file: 'apps-wizard.html', title: 'Wizard', h1: 'Onboarding Wizard', sub: 'Multi-step form with validation per step.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Wizard' }],
    css: ['simplebar', 'select2'], js: [...BASE_JS, 'select2', 'swal'], pageJs: 'wizard.js'
  },

  /* ============ Commerce ============ */
  {
    file: 'commerce-orders.html', title: 'Orders', h1: 'Orders', sub: 'All store orders with filters and bulk actions.',
    breadcrumb: [HOME, { t: 'Commerce' }, { t: 'Orders' }],
    css: ['simplebar', 'datatables', 'flatpickr'], js: [...BASE_JS, 'datatables', 'flatpickr', 'swal'], data: ['orders.js'], pageJs: 'orders.js',
    actions: btn('bi-plus-lg', 'New Order', 'btn-primary btn-sm', '#')
  },
  {
    file: 'commerce-products.html', title: 'Products', h1: 'Products', sub: 'Catalogue with grid and table views.',
    breadcrumb: [HOME, { t: 'Commerce' }, { t: 'Products' }],
    css: ['simplebar', 'nouislider'], js: [...BASE_JS, 'nouislider', 'swal'], pageJs: 'products.js',
    actions: btn('bi-plus-lg', 'Add Product', 'btn-primary btn-sm', '#')
  },
  {
    file: 'commerce-product-detail.html', title: 'Product Detail', h1: 'Aurora Wireless Headphones',
    sub: 'SKU NX-AUR-220 &middot; Audio &middot; In stock',
    breadcrumb: [HOME, { t: 'Commerce', u: 'commerce-products.html' }, { t: 'Product Detail' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'product-detail.js'
  },
  {
    file: 'commerce-customers.html', title: 'Customers', h1: 'Customers', sub: 'Everyone who has ever bought from you.',
    breadcrumb: [HOME, { t: 'Commerce' }, { t: 'Customers' }],
    css: ['simplebar', 'datatables'], js: [...BASE_JS, 'datatables', 'swal'], pageJs: 'customers.js',
    actions: btn('bi-person-plus', 'Add Customer', 'btn-primary btn-sm', '#')
  },
  {
    file: 'commerce-invoices.html', title: 'Invoices', h1: 'Invoices', sub: 'Billing documents and payment status.',
    breadcrumb: [HOME, { t: 'Commerce' }, { t: 'Invoices' }],
    css: ['simplebar', 'datatables'], js: [...BASE_JS, 'datatables', 'swal'], pageJs: 'invoices.js',
    actions: btn('bi-plus-lg', 'New Invoice', 'btn-primary btn-sm', 'commerce-invoice-detail.html')
  },
  {
    file: 'commerce-invoice-detail.html', title: 'Invoice Detail', h1: 'Invoice #INV-2026-0042',
    sub: 'Issued September 1, 2026 &middot; Due September 30, 2026',
    breadcrumb: [HOME, { t: 'Invoices', u: 'commerce-invoices.html' }, { t: 'INV-2026-0042' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'invoice-detail.js',
    actions: `<div class="d-flex gap-2 nx-no-print">
          <button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" id="btnPrint" type="button"><i class="bi bi-printer"></i>Print</button>
          <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="btnDownload" type="button"><i class="bi bi-download"></i>Download</button>
        </div>`
  },

  /* ============ UI Components ============ */
  { file: 'ui-buttons.html', title: 'Buttons', h1: 'Buttons', sub: 'Every button variant, size and state.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Buttons' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-cards.html', title: 'Cards', h1: 'Cards', sub: 'Card layouts used across the template.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Cards' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-modals.html', title: 'Modals', h1: 'Modals', sub: 'Sizes, positions and confirmation patterns.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Modals' }], css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'ui-modals.js' },
  { file: 'ui-tabs-accordions.html', title: 'Tabs & Accordions', h1: 'Tabs &amp; Accordions', sub: 'Navigation inside a page.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Tabs & Accordions' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-alerts-badges.html', title: 'Alerts & Badges', h1: 'Alerts &amp; Badges', sub: 'Inline feedback and status labels.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Alerts & Badges' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-progress-spinners.html', title: 'Progress & Spinners', h1: 'Progress &amp; Spinners', sub: 'Loading and completion indicators.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Progress & Spinners' }], css: ['simplebar'], js: [...BASE_JS, 'apex'], pageJs: 'ui-progress.js' },
  { file: 'ui-tooltips-popovers.html', title: 'Tooltips & Popovers', h1: 'Tooltips &amp; Popovers', sub: 'Contextual hints on hover and click.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Tooltips & Popovers' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-toasts-notifications.html', title: 'Toasts & Notifications', h1: 'Toasts &amp; Notifications', sub: 'Transient messages and dialogs.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Toasts' }], css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'ui-toasts.js' },
  { file: 'ui-dropdowns.html', title: 'Dropdowns', h1: 'Dropdowns', sub: 'Menus, directions and rich content.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Dropdowns' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'ui-dropdowns.js' },
  { file: 'ui-avatars-images.html', title: 'Avatars & Images', h1: 'Avatars &amp; Images', sub: 'Sizes, shapes, status and groups.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Avatars' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-pagination-breadcrumbs.html', title: 'Pagination & Breadcrumbs', h1: 'Pagination &amp; Breadcrumbs', sub: 'Navigating long content.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Pagination' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-list-group-timeline.html', title: 'List Group & Timeline', h1: 'List Group &amp; Timeline', sub: 'Vertical content patterns.', breadcrumb: [HOME, { t: 'UI' }, { t: 'List & Timeline' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-offcanvas-placeholders.html', title: 'Offcanvas & Placeholders', h1: 'Offcanvas &amp; Placeholders', sub: 'Side panels and skeleton loading.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Offcanvas' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-typography.html', title: 'Typography', h1: 'Typography', sub: 'Type scale, weights and text utilities.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Typography' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-icons.html', title: 'Icons', h1: 'Icons', sub: 'Bootstrap Icons &mdash; click any icon to copy its class.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Icons' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'ui-icons.js' },
  { file: 'ui-colors.html', title: 'Colors', h1: 'Colors', sub: 'Every design token in light and dark.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Colors' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'ui-colors.js' },

  /* ============ Forms ============ */
  { file: 'form-elements.html', title: 'Form Elements', h1: 'Form Elements', sub: 'Inputs, selects, checks and their states.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Elements' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'form-elements.js' },
  { file: 'form-layouts.html', title: 'Form Layouts', h1: 'Form Layouts', sub: 'Horizontal, grid, inline and sectioned forms.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Layouts' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'form-validation.html', title: 'Form Validation', h1: 'Form Validation', sub: 'Native Bootstrap validation plus custom rules.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Validation' }], css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'form-validation.js' },
  { file: 'form-select2.html', title: 'Select2', h1: 'Select2', sub: 'Searchable selects with Bootstrap 5 theme.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Select2' }], css: ['simplebar', 'select2'], js: [...BASE_JS, 'select2'], pageJs: 'form-select2.js' },
  { file: 'form-pickers.html', title: 'Pickers', h1: 'Date, Time &amp; Range Pickers', sub: 'Flatpickr, noUiSlider and colour pickers.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Pickers' }], css: ['simplebar', 'flatpickr', 'nouislider'], js: [...BASE_JS, 'flatpickr', 'nouislider'], pageJs: 'form-pickers.js' },
  { file: 'form-editors.html', title: 'Editors', h1: 'WYSIWYG Editor', sub: 'Quill rich text editor with live HTML preview.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Editors' }], css: ['simplebar', 'quill'], js: [...BASE_JS, 'quill', 'swal'], pageJs: 'form-editors.js' },
  { file: 'form-upload.html', title: 'File Upload', h1: 'File Upload', sub: 'Dropzone area and custom image preview input.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Upload' }], css: ['simplebar', 'dropzone'], js: [...BASE_JS, 'dropzone'], pageJs: 'form-upload.js' },
  { file: 'form-input-mask.html', title: 'Input Mask', h1: 'Input Mask', sub: 'Formatted phone, date, card and currency inputs.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Input Mask' }], css: ['simplebar'], js: [...BASE_JS, 'inputmask'], pageJs: 'form-input-mask.js' },

  /* ============ Plugins ============ */
  {
    file: 'plugin-index.html', title: 'All Plugins', h1: 'Plugin Library',
    sub: 'Fifty stable, framework-free JavaScript libraries wired up and ready to copy.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'All Plugins' }],
    css: ['simplebar'], js: [...BASE_JS], data: ['routes.js', 'plugins.js'], pageJs: 'plugin-index.js'
  },
  {
    file: 'plugin-maps.html', title: 'Maps', h1: 'Maps &amp; Geospatial',
    sub: 'Leaflet with marker clustering, heatmaps, drawing and a choropleth-style overlay.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Maps' }],
    css: ['simplebar', 'leaflet'], js: [...BASE_JS, 'leaflet'], pageJs: 'plugin-maps.js'
  },
  {
    file: 'plugin-data-grids.html', title: 'Data Grids', h1: 'Data Grids',
    sub: 'Grid.js and Tabulator &mdash; two alternatives to DataTables with different strengths.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Data Grids' }],
    css: ['simplebar', 'gridjs', 'tabulator'], js: [...BASE_JS, 'gridjs', 'tabulator'], pageJs: 'plugin-data-grids.js'
  },
  {
    file: 'plugin-media.html', title: 'Media & Gallery', h1: 'Media &amp; Gallery',
    sub: 'Swiper carousels, GLightbox galleries and Cropper.js image editing.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Media' }],
    css: ['simplebar', 'swiper', 'glightbox', 'cropper'], js: [...BASE_JS, 'swiper', 'glightbox', 'cropper'], pageJs: 'plugin-media.js'
  },
  {
    file: 'plugin-inputs.html', title: 'Advanced Inputs', h1: 'Advanced Inputs',
    sub: 'Tom Select, signature capture, colour picking, formatting and auto-growing fields.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Inputs' }],
    css: ['simplebar', 'tomselect', 'pickr'], js: [...BASE_JS, 'tomselect', 'signaturepad', 'pickr', 'cleave', 'autosize'], pageJs: 'plugin-inputs.js'
  },
  {
    file: 'plugin-documents.html', title: 'Documents & Export', h1: 'Documents &amp; Export',
    sub: 'Generate PDFs, capture the DOM as an image, and read or write spreadsheets in the browser.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Documents' }],
    css: ['simplebar'], js: [...BASE_JS, 'jspdf', 'html2canvas', 'xlsx', 'pdfjs'], pageJs: 'plugin-documents.js'
  },
  {
    file: 'plugin-ux.html', title: 'UX & Onboarding', h1: 'UX &amp; Onboarding',
    sub: 'Product tours, rich tooltips, progress bars, clipboard, toasts and scroll animation.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'UX' }],
    css: ['simplebar', 'driver', 'tippy', 'nprogress', 'toastify', 'aos'],
    js: [...BASE_JS, 'driver', 'tippy', 'nprogress', 'clipboard', 'toastify', 'aos', 'typed'], pageJs: 'plugin-ux.js'
  },
  {
    file: 'plugin-layout.html', title: 'Drag & Drop Layout', h1: 'Drag &amp; Drop Layout',
    sub: 'Gridstack dashboards the user can rearrange, plus free-form dragging with Interact.js.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Layout' }],
    css: ['simplebar', 'gridstack'], js: [...BASE_JS, 'gridstack', 'interact', 'apex'], pageJs: 'plugin-layout.js'
  },
  {
    file: 'plugin-utilities.html', title: 'Utilities', h1: 'Utilities',
    sub: 'Dates, fuzzy search, text highlighting, list filtering, QR codes, Lottie and code highlighting.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Utilities' }],
    css: ['simplebar', 'prism'], js: [...BASE_JS, 'dayjs', 'fuse', 'markjs', 'listjs', 'qrious', 'lottie', 'prism'],
    data: ['lottie-pulse.js'], pageJs: 'plugin-utilities.js'
  },
  {
    file: 'plugin-diagrams.html', title: 'Diagrams', h1: 'Diagrams',
    sub: 'Mermaid renders flowcharts, sequence diagrams, Gantt charts and more from plain text.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Diagrams' }],
    css: ['simplebar'], js: [...BASE_JS, 'mermaid'], pageJs: 'plugin-diagrams.js'
  },
  {
    file: 'plugin-org-chart.html', title: 'Org Chart', h1: 'Organisation Chart',
    sub: 'd3-org-chart renders a large hierarchy that stays readable: collapse branches, change direction, search and export.',
    breadcrumb: [HOME, { t: 'Plugins' }, { t: 'Org Chart' }],
    css: ['simplebar'], js: [...BASE_JS, 'orgchart'], pageJs: 'plugin-org-chart.js'
  },

  /* ============ Tables ============ */
  { file: 'table-basic.html', title: 'Basic Tables', h1: 'Basic Tables', sub: 'Every table style and composition pattern.', breadcrumb: [HOME, { t: 'Tables' }, { t: 'Basic' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'table-datatables.html', title: 'DataTables', h1: 'DataTables', sub: 'Sorting, search, export, column visibility and row selection.', breadcrumb: [HOME, { t: 'Tables' }, { t: 'DataTables' }], css: ['simplebar', 'datatables'], js: [...BASE_JS, 'datatables', 'swal'], pageJs: 'table-datatables.js' },

  /* ============ Pages ============ */
  {
    file: 'page-profile.html', title: 'Profile', h1: 'Profile', sub: 'Public profile and activity.',
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'Profile' }], noHead: true,
    css: ['simplebar'], js: [...BASE_JS, 'apex'], pageJs: 'profile.js'
  },
  {
    file: 'page-settings.html', title: 'Settings', h1: 'Settings', sub: 'Manage your account, security and billing.',
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'Settings' }],
    css: ['simplebar', 'select2'], js: [...BASE_JS, 'select2', 'swal'], pageJs: 'settings.js'
  },
  {
    file: 'page-pricing.html', title: 'Pricing', h1: 'Plans &amp; Pricing', sub: 'Simple pricing that scales with your team.',
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'Pricing' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'pricing.js'
  },
  {
    file: 'page-faq.html', title: 'FAQ', noHead: true,
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'FAQ' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'faq.js'
  },
  {
    file: 'page-timeline.html', title: 'Timeline', h1: 'Timeline', sub: 'Company milestones through the year.',
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'Timeline' }], css: ['simplebar'], js: [...BASE_JS]
  },
  {
    file: 'page-users.html', title: 'Users', h1: 'User Management', sub: 'Invite teammates and manage their roles.',
    breadcrumb: [HOME, { t: 'System' }, { t: 'Users' }],
    css: ['simplebar', 'datatables', 'select2'], js: [...BASE_JS, 'datatables', 'select2', 'countup', 'swal'], pageJs: 'users.js',
    actions: `<button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="modal" data-bs-target="#inviteModal"><i class="bi bi-person-plus"></i>Invite User</button>`
  },
  {
    file: 'page-notifications.html', title: 'Notifications', h1: 'Notifications', sub: 'Everything that happened while you were away.',
    breadcrumb: [HOME, { t: 'System' }, { t: 'Notifications' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'notifications.js',
    actions: `<button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" id="markAllRead" type="button"><i class="bi bi-check2-all"></i>Mark all read</button>`
  },
  {
    file: 'page-roadmap.html', title: 'Roadmap', h1: 'Product Roadmap', sub: 'What we are planning, building and have shipped.',
    breadcrumb: [HOME, { t: 'Dev Tools' }, { t: 'Roadmap' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'roadmap.js',
    actions: btn('bi-lightbulb', 'Suggest a feature', 'btn-primary btn-sm', '#')
  },
  {
    file: 'page-activity.html', title: 'Activity', h1: 'Activity Feed', sub: 'Commits, deploys, comments and alerts.',
    breadcrumb: [HOME, { t: 'Dev Tools' }, { t: 'Activity' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'activity.js'
  },
  {
    file: 'page-changelog.html', title: 'Changelog', h1: 'Changelog', sub: 'Every release, newest first.',
    breadcrumb: [HOME, { t: 'Dev Tools' }, { t: 'Changelog' }],
    css: ['simplebar'], js: [...BASE_JS]
  },

  /* ============ Dashboards tambahan ============ */
  {
    file: 'dashboard-project.html', title: 'Project', h1: 'Project Dashboard',
    sub: 'Delivery health across every active project.',
    breadcrumb: [HOME, { t: 'Dashboards' }, { t: 'Project' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup'], pageJs: 'project.js',
    actions: btn('bi-plus-lg', 'New Project', 'btn-primary btn-sm', 'page-projects.html')
  },
  {
    file: 'dashboard-support.html', title: 'Support Desk', h1: 'Support Desk',
    sub: 'Queue volume, response times and agent workload.',
    breadcrumb: [HOME, { t: 'Dashboards' }, { t: 'Support Desk' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup'], pageJs: 'support.js',
    actions: btn('bi-plus-lg', 'New Ticket', 'btn-primary btn-sm', 'apps-tickets.html')
  },
  {
    file: 'dashboard-academy.html', title: 'Academy', h1: 'Academy Dashboard',
    sub: 'Enrolments, completion rates and top performing courses.',
    breadcrumb: [HOME, { t: 'Dashboards' }, { t: 'Academy' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup'], pageJs: 'academy.js',
    actions: btn('bi-plus-lg', 'New Course', 'btn-primary btn-sm', 'apps-courses.html')
  },
  {
    file: 'dashboard-logistics.html', title: 'Logistics', h1: 'Logistics Dashboard',
    sub: 'Fleet status, deliveries on time and warehouse capacity.',
    breadcrumb: [HOME, { t: 'Dashboards' }, { t: 'Logistics' }],
    css: ['simplebar', 'leaflet'], js: [...BASE_JS, 'apex', 'countup', 'leaflet'], pageJs: 'logistics.js'
  },

  /* ============ Apps tambahan ============ */
  {
    file: 'apps-social.html', title: 'Social Feed', h1: 'Social Feed',
    sub: 'Company-wide updates, reactions and comments.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Social Feed' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'social.js'
  },
  {
    file: 'apps-contacts.html', title: 'Contacts', h1: 'Contacts',
    sub: 'Everyone in the address book, as cards or a table.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Contacts' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'contacts.js',
    actions: `<button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="modal" data-bs-target="#contactModal"><i class="bi bi-person-plus"></i>Add Contact</button>`
  },
  {
    file: 'apps-notes.html', title: 'Notes & Tasks', h1: 'Notes &amp; Tasks',
    sub: 'A scratchpad and a checklist that share one board.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Notes' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'notes.js'
  },
  {
    file: 'apps-tickets.html', title: 'Support Tickets', h1: 'Support Tickets',
    sub: 'Every ticket in the queue with filters and bulk actions.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Tickets' }],
    css: ['simplebar', 'datatables'], js: [...BASE_JS, 'datatables', 'countup', 'swal'], pageJs: 'tickets.js',
    actions: btn('bi-plus-lg', 'New Ticket', 'btn-primary btn-sm', '#')
  },
  {
    file: 'apps-ticket-detail.html', title: 'Ticket Detail', h1: 'Ticket #TCK-2041',
    sub: 'Billing retry loop on soft declines',
    breadcrumb: [HOME, { t: 'Tickets', u: 'apps-tickets.html' }, { t: 'TCK-2041' }],
    css: ['simplebar', 'quill'], js: [...BASE_JS, 'quill', 'swal'], pageJs: 'ticket-detail.js'
  },
  {
    file: 'apps-events.html', title: 'Events', h1: 'Events',
    sub: 'Upcoming conferences, webinars and internal sessions.',
    breadcrumb: [HOME, { t: 'Apps' }, { t: 'Events' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'events.js',
    actions: btn('bi-plus-lg', 'Create Event', 'btn-primary btn-sm', 'apps-calendar.html')
  },
  {
    file: 'apps-event-detail.html', title: 'Event Detail', h1: 'NexaCon 2026',
    sub: 'October 14&ndash;16, 2026 &middot; Marina Bay Sands, Singapore',
    breadcrumb: [HOME, { t: 'Events', u: 'apps-events.html' }, { t: 'NexaCon 2026' }],
    css: ['simplebar', 'leaflet'], js: [...BASE_JS, 'leaflet', 'swal'], pageJs: 'event-detail.js'
  },
  {
    file: 'apps-courses.html', title: 'Courses', h1: 'Course Catalogue',
    sub: 'Browse every course, filter by track and difficulty.',
    breadcrumb: [HOME, { t: 'E-Learning' }, { t: 'Courses' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'courses.js'
  },
  {
    file: 'apps-course-detail.html', title: 'Course Detail', h1: 'Building Dashboards That People Trust',
    sub: '12 lessons &middot; 6h 40m &middot; Intermediate',
    breadcrumb: [HOME, { t: 'Courses', u: 'apps-courses.html' }, { t: 'Course Detail' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'course-detail.js'
  },

  /* ============ Commerce tambahan ============ */
  {
    file: 'commerce-product-add.html', title: 'Add Product', h1: 'Add Product',
    sub: 'Everything a listing needs before it goes live.',
    breadcrumb: [HOME, { t: 'Products', u: 'commerce-products.html' }, { t: 'Add Product' }],
    css: ['simplebar', 'select2', 'quill', 'dropzone'], js: [...BASE_JS, 'select2', 'quill', 'dropzone', 'swal'], pageJs: 'product-add.js',
    actions: `<div class="d-flex gap-2"><button class="btn btn-outline-primary btn-sm" type="button">Save draft</button><button class="btn btn-primary btn-sm" id="btnPublish" type="button">Publish</button></div>`
  },
  {
    file: 'commerce-categories.html', title: 'Categories', h1: 'Product Categories',
    sub: 'A nested catalogue tree with counts and visibility.',
    breadcrumb: [HOME, { t: 'Products', u: 'commerce-products.html' }, { t: 'Categories' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'categories.js',
    actions: btn('bi-plus-lg', 'Add Category', 'btn-primary btn-sm', '#')
  },
  {
    file: 'commerce-order-detail.html', title: 'Order Detail', h1: 'Order #ORD-1042',
    sub: 'Placed September 8, 2026 by Maria Gomez',
    breadcrumb: [HOME, { t: 'Orders', u: 'commerce-orders.html' }, { t: 'ORD-1042' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'order-detail.js',
    actions: `<div class="d-flex gap-2"><a class="btn btn-outline-primary btn-sm" href="commerce-invoice-detail.html"><i class="bi bi-file-earmark-text me-1"></i>Invoice</a><button class="btn btn-soft-danger btn-sm" id="btnCancelOrder" type="button">Cancel order</button></div>`
  },
  {
    file: 'commerce-customer-detail.html', title: 'Customer Detail', h1: 'Maria Gomez',
    sub: 'Customer since March 2024 &middot; Austin, United States',
    breadcrumb: [HOME, { t: 'Customers', u: 'commerce-customers.html' }, { t: 'Maria Gomez' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup', 'swal'], pageJs: 'customer-detail.js'
  },
  {
    file: 'commerce-cart.html', title: 'Shopping Cart', h1: 'Shopping Cart',
    sub: 'Three items ready to check out.',
    breadcrumb: [HOME, { t: 'Commerce' }, { t: 'Cart' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'cart.js'
  },
  {
    file: 'commerce-checkout.html', title: 'Checkout', h1: 'Checkout',
    sub: 'Address, delivery and payment in three steps.',
    breadcrumb: [HOME, { t: 'Commerce' }, { t: 'Checkout' }],
    css: ['simplebar', 'select2'], js: [...BASE_JS, 'select2', 'swal'], pageJs: 'checkout.js'
  },
  {
    file: 'commerce-reviews.html', title: 'Reviews', h1: 'Manage Reviews',
    sub: 'Moderate customer feedback and reply in public.',
    breadcrumb: [HOME, { t: 'Commerce' }, { t: 'Reviews' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'swal'], pageJs: 'reviews.js'
  },

  /* ============ UI tambahan ============ */
  { file: 'ui-stat-cards.html', title: 'Statistic Cards', h1: 'Statistic Cards', sub: 'Every KPI, analytics and gamification card in the system.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Statistic Cards' }], css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup'], pageJs: 'ui-stat-cards.js' },
  { file: 'ui-carousel.html', title: 'Carousel', h1: 'Carousel', sub: 'Bootstrap carousels plus a Swiper comparison.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Carousel' }], css: ['simplebar', 'swiper'], js: [...BASE_JS, 'swiper'], pageJs: 'ui-carousel.js' },
  { file: 'ui-navbar-footer.html', title: 'Navbar & Footer', h1: 'Navbar &amp; Footer', sub: 'Top bars, brand bars and footer layouts.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Navbar & Footer' }], css: ['simplebar'], js: [...BASE_JS] },
  { file: 'ui-ratings.html', title: 'Star Ratings', h1: 'Star Ratings', sub: 'Read-only stars, interactive input and rating summaries.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Ratings' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'ui-ratings.js' },
  { file: 'ui-media-player.html', title: 'Media Player', h1: 'Media Player', sub: 'Native audio and video with a custom control bar.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Media Player' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'ui-media-player.js' },
  { file: 'ui-treeview.html', title: 'Treeview', h1: 'Treeview', sub: 'Expandable hierarchies with checkboxes and search.', breadcrumb: [HOME, { t: 'UI' }, { t: 'Treeview' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'ui-treeview.js' },
  { file: 'ui-blockui.html', title: 'BlockUI & Dividers', h1: 'BlockUI &amp; Dividers', sub: 'Blocking overlays, skeleton states and text dividers.', breadcrumb: [HOME, { t: 'UI' }, { t: 'BlockUI' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'ui-blockui.js' },

  /* ============ Forms tambahan ============ */
  { file: 'form-custom-options.html', title: 'Custom Options', h1: 'Custom Options', sub: 'Selectable cards, switch lists and segmented choices.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Custom Options' }], css: ['simplebar'], js: [...BASE_JS], pageJs: 'form-custom-options.js' },
  { file: 'form-sticky-actions.html', title: 'Sticky Actions', h1: 'Sticky Action Bar', sub: 'A long form whose save bar never leaves the screen.', breadcrumb: [HOME, { t: 'Forms' }, { t: 'Sticky Actions' }], css: ['simplebar', 'select2'], js: [...BASE_JS, 'select2', 'swal'], pageJs: 'form-sticky-actions.js' },

  /* ============ Pages tambahan ============ */
  {
    file: 'page-roles.html', title: 'Roles', h1: 'Roles', sub: 'What each role can do, and who holds it.',
    breadcrumb: [HOME, { t: 'System' }, { t: 'Roles' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'roles.js',
    actions: `<button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="modal" data-bs-target="#roleModal"><i class="bi bi-plus-lg"></i>Add Role</button>`
  },
  {
    file: 'page-permissions.html', title: 'Permissions', h1: 'Permissions', sub: 'The full permission matrix, editable in place.',
    breadcrumb: [HOME, { t: 'System' }, { t: 'Permissions' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'permissions.js'
  },
  {
    file: 'page-user-detail.html', title: 'User Detail', h1: 'Sarah Chen', sub: 'Head of Product &middot; Singapore &middot; Joined March 2024',
    breadcrumb: [HOME, { t: 'Users', u: 'page-users.html' }, { t: 'Sarah Chen' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'swal'], pageJs: 'user-detail.js'
  },
  {
    file: 'page-teams.html', title: 'Teams', h1: 'Teams', sub: 'Who works with whom, and on what.',
    breadcrumb: [HOME, { t: 'Users' }, { t: 'Teams' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'teams.js'
  },
  {
    file: 'page-projects.html', title: 'Projects', h1: 'Projects', sub: 'Every project with progress, budget and owners.',
    breadcrumb: [HOME, { t: 'Users' }, { t: 'Projects' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'projects.js'
  },
  {
    file: 'page-connections.html', title: 'Connections', h1: 'Connections', sub: 'Third-party accounts linked to this workspace.',
    breadcrumb: [HOME, { t: 'Users' }, { t: 'Connections' }],
    css: ['simplebar'], js: [...BASE_JS, 'swal'], pageJs: 'connections.js'
  },
  {
    file: 'page-help-center.html', title: 'Help Center', noHead: true,
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'Help Center' }],
    css: ['simplebar'], js: [...BASE_JS], pageJs: 'help-center.js'
  },
  {
    file: 'page-landing.html', title: 'Landing Page', noHead: true,
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'Landing' }],
    css: ['simplebar'], js: [...BASE_JS, 'apex'], pageJs: 'landing.js'
  },
  {
    file: 'page-payment.html', title: 'Payment', h1: 'Payment', sub: 'Complete your Pro subscription.',
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'Payment' }],
    css: ['simplebar'], js: [...BASE_JS, 'inputmask', 'swal'], pageJs: 'payment.js'
  },
  {
    file: 'page-starter.html', title: 'Starter Page', h1: 'Starter Page',
    sub: 'An empty page with the shell already in place &mdash; copy it to begin.',
    breadcrumb: [HOME, { t: 'Pages' }, { t: 'Starter' }],
    css: ['simplebar'], js: [...BASE_JS]
  },
  {
    file: 'page-level-two.html', title: 'Level 2', h1: 'Level 2 Page',
    sub: 'Reached through a second-level menu entry.',
    breadcrumb: [HOME, { t: 'Multi Level' }, { t: 'Level 2' }],
    css: ['simplebar'], js: [...BASE_JS]
  },
  {
    file: 'page-level-three.html', title: 'Level 3', h1: 'Level 3 Page',
    sub: 'Reached through a nested third-level menu entry.',
    breadcrumb: [HOME, { t: 'Multi Level' }, { t: 'Level 2' }, { t: 'Level 3' }],
    css: ['simplebar'], js: [...BASE_JS]
  },

  /* ============ Layouts ============ */
  {
    file: 'layout-horizontal.html', title: 'Horizontal Menu', h1: 'Horizontal Menu Layout',
    sub: 'The same shell with navigation across the top instead of down the side.',
    breadcrumb: [HOME, { t: 'Layouts' }, { t: 'Horizontal' }],
    bodyClass: 'nx-layout-horizontal',
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup'], data: ['routes.js'], pageJs: 'layout-horizontal.js'
  },
  {
    file: 'layout-fluid.html', title: 'Fluid Width', h1: 'Fluid Width Layout',
    sub: 'Content stretches to the full viewport instead of capping at 1600px.',
    breadcrumb: [HOME, { t: 'Layouts' }, { t: 'Fluid' }],
    bodyClass: 'nx-layout-fluid',
    css: ['simplebar'], js: [...BASE_JS, 'apex', 'countup'], pageJs: 'layout-fluid.js'
  },
  {
    file: 'layout-blank.html', title: 'Blank Layout', noHead: true,
    bodyClass: 'nx-layout-blank',
    breadcrumb: [HOME, { t: 'Layouts' }, { t: 'Blank' }],
    css: ['simplebar'], js: [...BASE_JS]
  },

  /* ============ Auth (standalone) ============ */
  { file: 'auth-login.html', title: 'Login', standalone: true, js: [], pageJs: 'auth.js' },
  { file: 'auth-register.html', title: 'Register', standalone: true, js: [], pageJs: 'auth.js' },
  { file: 'auth-forgot-password.html', title: 'Forgot Password', standalone: true, js: [], pageJs: 'auth.js' },
  { file: 'auth-lock-screen.html', title: 'Lock Screen', standalone: true, js: [], pageJs: 'auth.js' },
  { file: 'auth-two-factor.html', title: 'Two Factor', standalone: true, js: [], pageJs: 'auth.js' },
  { file: 'auth-verify-email.html', title: 'Verify Email', standalone: true, js: [], pageJs: 'auth.js' },
  { file: 'auth-reset-password.html', title: 'Reset Password', standalone: true, js: [], pageJs: 'auth.js' },

  /* ============ Errors (standalone) ============ */
  { file: 'error-404.html', title: '404 Not Found', standalone: true, js: [] },
  { file: 'error-500.html', title: '500 Server Error', standalone: true, js: [] },
  { file: 'error-maintenance.html', title: 'Maintenance', standalone: true, js: [], pageJs: 'maintenance.js' },
  { file: 'error-401.html', title: '401 Not Authorized', standalone: true, js: [] },
  { file: 'error-coming-soon.html', title: 'Coming Soon', standalone: true, js: [], pageJs: 'coming-soon.js' }
];
