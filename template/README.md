# NexaDash — Admin Dashboard Template

A 123-page static admin dashboard built with **HTML5 + CSS3 + JavaScript**, **Bootstrap 5.3**
and **jQuery 3.7**. No build step and no server-side code: every page is a plain `.html` file.

Built to the specification in [`PROMPT.md`](PROMPT.md), with visual and structural reference
from `flux.dashboardpack.com` (SaaS / dev-tools layout), `ember.dashboardpack.com`
(clinical dashboard, KPI density), and the page inventories of
`demos.themeselection.com` and `prium.github.io/falcon-tailwind`.

---

## Running it

Open `index.html` directly, or serve the folder — **both work**. The sidebar, header and
footer are inlined into every page at generate time, so there is no `fetch()` at runtime and
nothing breaks under the `file://` protocol.

```
file:///Applications/MAMP/htdocs/nexa/dashboard-template/index.html
http://localhost:8888/nexa/dashboard-template/index.html
```

---

## Folder structure

Pages are grouped by section; every link between them is relative, so the whole folder can be
moved or renamed without touching a single file.

```
dashboard-template/
├── index.html              main SaaS dashboard
├── dashboards/       (8)   analytics, ecommerce, crm, project, support, academy, logistics, clinical
├── charts/           (3)   apexcharts, chartjs, sparkline
├── apps/            (15)   mail, chat, social, contacts, notes, files, kanban, calendar,
│                           tickets, events, courses (+ their detail pages), wizard
├── commerce/        (13)   orders, products, categories, customers, cart, checkout,
│                           reviews, invoices (+ detail pages)
├── plugins/         (11)   index + 10 library showcase pages
├── ui/              (23)   component pages
├── forms/           (10)   form pages
├── tables/           (2)   basic, datatables
├── pages/           (22)   landing, profile, settings, pricing, payment, faq, help centre,
│                           users, roles, permissions, teams, projects, connections, timeline…
├── auth/             (7)   login, register, verify-email, forgot/reset password, lock, 2FA
├── errors/           (5)   401, 404, 500, maintenance, coming soon
├── layouts/          (3)   horizontal menu, fluid width, blank
├── partials/               sidebar, header, footer — the single source for the shell
├── assets/
│   ├── css/                variables · layout · components · pages · motion · dark
│   ├── js/
│   │   ├── theme-init.js   runs in <head> so the theme never flashes
│   │   ├── app.js          layout, theme, sidebar, command palette, chart registry
│   │   ├── data/           dummy datasets + generated route map
│   │   └── pages/          one script per page that needs it
│   └── img/                logo and product placeholders (SVG)
└── tools/                  generator + QA scripts (never loaded by the pages)
```

---

## What's inside

**Dashboards (8)** — SaaS overview, Analytics, eCommerce, CRM, Project (burndown and
capacity), Support Desk (queue and SLA), Academy (enrolments and completion), Logistics
(live fleet map), and Clinical (live vitals monitor).

**Charts (3)** — every ApexCharts type, a Chart.js page, and sparklines.

**Apps (15)** — Mail (3-pane with a Quill composer), Chat (live bubbles, typing indicator),
Social feed (posting, likes, threaded comments), Contacts (cards and table), Notes & Tasks,
Files (Dropzone + storage breakdown), Kanban (SortableJS), Calendar (FullCalendar), Support
Tickets (list + full conversation view), Events (list + detail with venue map), E-Learning
(course catalogue + curriculum), and a four-step validated Wizard.

**Commerce (13)** — Orders (DataTables with bulk actions), full Order detail with fulfilment
timeline, Products (grid/table with a price slider), Product detail, Add Product (editor,
variants, SEO preview, live margin), nested Categories tree, Customers, Customer detail with
tabs, Shopping cart, three-step Checkout, Reviews moderation, Invoices, printable Invoice.

**Plugins (11)** — a searchable catalogue of **50 libraries** plus ten working demo pages:
Maps, Data Grids, Media & Gallery, Advanced Inputs, Documents & Export, UX & Onboarding,
Drag & Drop Layout, Utilities, Diagrams, and an Org Chart. See the table below.

**UI components (23)** — buttons, cards, statistic cards (KPI, gradient, split, analytics,
gamification, action), carousel, modals, tabs, alerts, progress, BlockUI overlays and text
dividers, tooltips, toasts, dropdowns, navbars and footers, avatars, star ratings, media
player, treeview, pagination, timelines, offcanvas, typography, a searchable icon browser and
a live design-token colour reference.

**Forms (10)** — elements, custom options (selectable cards, segmented controls, switch
lists), layouts, a sticky action bar with dirty-state tracking, validation, Select2, pickers,
Quill, uploads, input masks.

**Tables (2)** — every basic table style, plus a fully-featured DataTables page.

**Pages (22)** — landing page, profile, settings, pricing, payment (live card preview),
FAQ, help centre, timeline, starter page, users, user detail, roles, a full permission
matrix, teams, projects, connections, notifications, roadmap, activity, changelog, plus
a two- and three-level menu demo.

**Layouts (3)** — horizontal top menu, fluid full-width, and a blank shell-free canvas.

**Auth & errors (12)** — login, register, verify email, forgot password, reset password,
lock screen, two-factor with a 6-box OTP input, plus 401, 404, 500, maintenance and a
coming-soon page, each with its own countdown where relevant.

---

## Plugin showcase

Every library below is past 1.0, actively maintained, framework-free, pinned to an exact
version on jsDelivr, and under a permissive licence. Browse them at `plugins/index.html`.

| Page | Libraries demonstrated |
|---|---|
| `plugins/maps.html` | Leaflet, Leaflet.markercluster, Leaflet.heat |
| `plugins/data-grids.html` | Grid.js, Tabulator |
| `plugins/media.html` | Swiper, GLightbox, Cropper.js |
| `plugins/inputs.html` | Tom Select, Signature Pad, Pickr, Cleave.js, Autosize |
| `plugins/documents.html` | jsPDF, jsPDF-AutoTable, PDF.js, html2canvas, SheetJS |
| `plugins/ux.html` | Driver.js, Tippy.js, NProgress, clipboard.js, Toastify, AOS, Typed.js |
| `plugins/layout.html` | Gridstack.js, Interact.js |
| `plugins/utilities.html` | Day.js, Fuse.js, mark.js, List.js, QRious, Lottie, Prism.js |
| `plugins/diagrams.html` | Mermaid |
| `plugins/org-chart.html` | d3-org-chart, D3.js, d3-flextree |

The org chart supports collapsible branches, four layout directions, compact mode, search
with ancestor highlighting, click-to-centre, adding and removing nodes, and PNG export.

**Note on map tiles:** the maps page uses OpenStreetMap, Humanitarian OSM and OpenTopoMap.
Carto Basemaps now stamps an "API key required" watermark, so it is deliberately not used.

---

## Design tokens

Everything visual comes from CSS custom properties in `assets/css/variables.css` — colours,
radii, shadows and the chart palette, each with a dark-mode counterpart. No hex values are
hardcoded in markup. Open `ui/colors.html` to browse and copy any token.

Key values: primary `#6366f1`, card radius `16px`, control radius `10px`, body text `14px`
Inter, card padding `20px`, grid gap `24px`.

Surfaces are deliberately **tinted rather than pure white**: the canvas carries a faint indigo
cast (`--nx-body-bg: #f2f4fc`) so white cards read as raised, and secondary surfaces — card
headers and footers, table heads, inputs, wells — use `--nx-surface-1` / `--nx-surface-2`
instead of more white. Dark mode is indigo-navy (`#0a0e1f` canvas, `#141a33` cards) rather
than neutral grey. Every surface token has a dark counterpart, so both themes stay in step.

The radius scale runs `--nx-radius-xs` `6px` → `-sm` `10px` → base `16px` → `-lg` `20px` →
`-xl` `28px` → `-pill`. Bootstrap's own `--bs-border-radius-*` variables are mapped onto it,
so stock Bootstrap components round consistently with the custom ones.

---

## Global behaviour

- **Command palette** — `⌘K` / `Ctrl+K` fuzzy-searches every page plus actions, fully
  keyboard navigable.
- **Menu filter** — the box at the top of the sidebar filters the menu itself (distinct from
  `⌘K`, which searches all pages). It matches parents *and* children, so typing `sparkline`
  surfaces it through its collapsed `Charts` parent; a parent match reveals all its children;
  hits are highlighted, empty sections drop out, and a "no matches" line shows when nothing
  is left. `Esc` or the x clears it, `Enter` opens the first hit. While filtering, sections
  and submenus are force-opened **in CSS only** (`body.nx-menu-filtering`), so the real
  collapse state is untouched and clearing restores exactly what was open before. Hidden in
  the 72px rail, where there is no room for it.
- **Dark mode** — follows the OS by default, toggled from the header, persisted in
  `localStorage`. Charts rebuild on the `nx:theme-changed` event; DataTables, Select2,
  Flatpickr, Quill, Dropzone, FullCalendar, Leaflet, Tabulator, Tom Select, Gridstack,
  Tippy, Driver.js, Mermaid and the org chart are all themed in `dark.css`.
- **Sidebar** — the 65 menu items are split across **9 collapsible sections**. Only the
  section holding the current page is open on load, which keeps the nav ~780px instead of the
  ~3200px it would be fully expanded. Sections the user opens are remembered in
  `localStorage` (`nx-sidebar-sections`); a closed section that contains the active page is
  flagged with a dot. Still collapses to a 72px icon rail with tooltips and flyout submenus
  (where the accordion is bypassed, since labels are hidden and all icons stay reachable),
  becomes an offcanvas below 992px, and supports three levels of nesting.
- **Active menu** — resolved from the page URL, so pages need no per-page markup. Every
  ancestor collapse opens itself on load — section *and* submenu, including third-level
  entries — and the active link gets `aria-current="page"`.
- **Motion** — `assets/css/motion.css` holds every keyframe and transition: staggered card
  and menu entrances, hover lift, press feedback, pop-in dropdowns and toasts, shimmering
  skeletons. All of it collapses to near-zero duration under
  `@media (prefers-reduced-motion: reduce)`.
- **Keyboard & mobile** — a skip link (first Tab) jumps past the nav to `#nxContent`; the
  mobile panel closes on `Esc`, on backdrop tap, or via its own X button, and locks
  background scroll while open.
- **Alternate layouts** — one class on `<body>` switches to a horizontal top menu, fluid
  full-width content, or a blank canvas with no shell at all.

---

## Editing pages

Pages are generated from body fragments so the shell stays identical everywhere:

```bash
node tools/build.js     # regenerates all 123 pages + assets/js/data/routes.js
```

- `tools/body/<section>/<page>.html` — the content of one page
- `tools/pages.js` — title, breadcrumb, vendor libraries and page script per page
- `tools/routes.js` — canonical name → folder location. Links inside body fragments and
  partials are written flat (`ui-buttons.html`); the generator rewrites each one to the
  correct relative path for that page's depth.
- `partials/` — edit the shell once, rebuild, and all 123 pages update.

Editing the generated `.html` files directly works too — just remember `tools/build.js`
would overwrite them.

---

## QA

Four Puppeteer-based suites check the built output. They need a one-off
`npm install puppeteer-core` and a local Chrome.

```bash
node tools/qa-links.js        # every local href/src resolves
node tools/qa.js              # console errors, failed requests, layout, dark mode
node tools/qa-responsive.js   # horizontal overflow at 5 breakpoints + screenshots
node tools/qa-interaction.js  # clicks real controls and catches runtime errors
```

Set `NX_BASE` to test over a different protocol, for example
`NX_BASE="file://$(pwd)/" node tools/qa.js`.

**Current status:** 123/123 pages pass over both `file://` and `http://` — zero console
errors, zero failed requests, 17,159 local references all resolving, no horizontal overflow
at 1440 / 1100 / 900 / 700 / 390 px, and 235 scripted interactions across 50 pages running
without a single runtime error.

---

## Libraries

All loaded from jsDelivr; swap the tags for local copies in `assets/vendors/` to work
offline. Core stack:

| Library | Version | Used for |
|---|---|---|
| Bootstrap | 5.3.3 | grid, components, utilities, `data-bs-theme` |
| jQuery | 3.7.1 | DOM helper and plugin host |
| Bootstrap Icons | 1.11.3 | all icons |
| ApexCharts | 3.54 | every chart except the Chart.js page |
| Chart.js | 4.4 | `charts/chartjs.html` |
| DataTables | 2.1 | advanced tables, export, column visibility |
| Select2 | 4.1 | searchable and tagged selects |
| Flatpickr | 4.6 | date and time pickers |
| noUiSlider | 15.8 | range sliders |
| Quill | 2.0 | rich text editor |
| Dropzone | 5.9 | drag-and-drop uploads |
| FullCalendar | 6.1 | calendar app |
| SortableJS | 1.15 | Kanban drag and drop |
| SweetAlert2 | 11 | confirmations and alerts |
| CountUp.js | 2.8 | animated KPI numbers |
| SimpleBar | 6.2 | custom scrollbars |
| Inputmask | 5.0 | formatted inputs |

The 33 additional libraries used by the Plugins section are listed on `plugins/index.html`
with their version, licence and a link to both the demo and the upstream docs.

All data is dummy and hardcoded — there is no backend.
