# PROMPT: Build "NexaDash" — Admin Dashboard Template (HTML/CSS/JS)

> Prompt ini dipakai untuk menginstruksikan AI/developer membangun template dashboard admin
> statis yang lengkap. Referensi visual & struktur: **flux.dashboardpack.com** dan
> **ember.dashboardpack.com**. Kerjakan sesuai spesifikasi di bawah, jangan menyederhanakan.

---

## 1. PERAN & TUJUAN

Kamu adalah senior front-end engineer. Bangun sebuah **template dashboard admin premium**
bernama **NexaDash** di folder `dashboard-template/`. Template harus:

- 100% statis: **HTML5 + CSS3 + JavaScript**, tanpa build step (tidak perlu npm/webpack/vite).
- Wajib memakai **Bootstrap 5.3.x** dan **jQuery 3.7.x**. Library lain bebas ditambah (daftar
  rekomendasi di bagian 3) selama dimuat via file lokal di `assets/vendors/` ATAU CDN.
- Kualitas visual setara template premium: rapi, konsisten, modern (gaya Flux: bersih,
  border tipis, radius besar, banyak whitespace; gaya Ember: KPI card padat + chart monitor).
- Setiap halaman harus bisa dibuka langsung via file (mis. `http://localhost/nexa/dashboard-template/index.html`) tanpa server-side rendering.
- Semua data adalah **dummy data statis** (hardcode di HTML atau file `assets/js/data/*.js`).

---

## 2. STRUKTUR FOLDER & FILE

```
dashboard-template/
├── index.html                  ← Dashboard utama (SaaS overview, gaya Flux)
├── dashboard-analytics.html
├── dashboard-ecommerce.html
├── dashboard-crm.html
├── dashboard-clinical.html     ← gaya Ember (medis/operasional)
│
├── apps-mail.html
├── apps-chat.html
├── apps-files.html
├── apps-kanban.html
├── apps-calendar.html
├── apps-wizard.html
│
├── commerce-orders.html
├── commerce-products.html
├── commerce-product-detail.html
├── commerce-customers.html
├── commerce-invoices.html
├── commerce-invoice-detail.html
│
├── ui-buttons.html
├── ui-cards.html
├── ui-modals.html
├── ui-tabs-accordions.html
├── ui-alerts-badges.html
├── ui-progress-spinners.html
├── ui-tooltips-popovers.html
├── ui-toasts-notifications.html
├── ui-dropdowns.html
├── ui-avatars-images.html
├── ui-pagination-breadcrumbs.html
├── ui-list-group-timeline.html
├── ui-offcanvas-placeholders.html
├── ui-typography.html
├── ui-icons.html
├── ui-colors.html              ← showcase seluruh design token
│
├── form-elements.html
├── form-layouts.html
├── form-validation.html
├── form-select2.html
├── form-pickers.html           ← datepicker, timepicker, colorpicker, range slider
├── form-editors.html           ← WYSIWYG (Quill/Summernote)
├── form-upload.html            ← Dropzone / FilePond
├── form-input-mask.html
│
├── table-basic.html
├── table-datatables.html
│
├── chart-apex.html             ← semua tipe ApexCharts
├── chart-chartjs.html
├── chart-sparkline.html
│
├── page-profile.html
├── page-settings.html
├── page-pricing.html
├── page-faq.html
├── page-timeline.html
├── page-users.html             ← user management + role badge (RBAC look)
├── page-notifications.html
├── page-roadmap.html           ← gaya Flux Dev Tools
├── page-activity.html
├── page-changelog.html
│
├── auth-login.html
├── auth-register.html
├── auth-forgot-password.html
├── auth-lock-screen.html
├── auth-two-factor.html
│
├── error-404.html
├── error-500.html
├── error-maintenance.html
│
└── assets/
    ├── css/
    │   ├── variables.css       ← SEMUA design token (CSS custom properties)
    │   ├── layout.css          ← sidebar, header, footer, page wrapper
    │   ├── components.css      ← override/extend komponen Bootstrap
    │   ├── pages.css           ← style spesifik per halaman (mail, chat, kanban…)
    │   └── dark.css            ← overrides dark mode (atau gabung via [data-bs-theme])
    ├── js/
    │   ├── app.js              ← init global: sidebar, theme, command palette, tooltip
    │   ├── data/               ← dummy data (orders.js, patients.js, dst.)
    │   └── pages/              ← 1 file JS per halaman yang butuh init khusus
    ├── img/
    │   ├── avatars/            ← pakai https://i.pravatar.cc atau SVG inisial lokal
    │   ├── products/           ← placeholder SVG buatan sendiri
    │   └── logo.svg            ← logo "NexaDash" (buat SVG sederhana: kotak rounded + huruf N)
    └── vendors/                ← jika tidak pakai CDN
```

Aturan: **JANGAN** menulis CSS/JS inline di HTML kecuali data attribute Bootstrap.
Semua halaman share layout yang sama (copy-paste partial header/sidebar konsisten 100%,
hanya class `active` pada menu yang berbeda).

---

## 3. TEKNOLOGI & LIBRARY (versi + kegunaan)

| Library | Versi | Dipakai untuk |
|---|---|---|
| Bootstrap | 5.3.x (bundle + Popper) | Grid, komponen dasar, utilities, `data-bs-theme` |
| jQuery | 3.7.x | DOM helper, plugin lama (DataTables, Select2) |
| Bootstrap Icons | 1.11.x | Ikon utama seluruh UI (`bi-*`) |
| ApexCharts | 3.x/4.x | Semua chart utama (area, bar, donut, radial, heatmap, sparkline) |
| Chart.js | 4.x | Halaman `chart-chartjs.html` saja |
| DataTables | 2.x (+ Bootstrap 5 styling) | `table-datatables.html`, orders, customers, users |
| Select2 | 4.1.x (tema Bootstrap 5) | Semua select advanced |
| Flatpickr | 4.6.x | Date/time picker |
| noUiSlider | 15.x | Range slider |
| Quill ATAU Summernote | terbaru | WYSIWYG editor |
| Dropzone | 6.x | Upload area |
| FullCalendar | 6.x | `apps-calendar.html` |
| SortableJS | 1.15.x | Drag-drop kartu Kanban |
| SweetAlert2 | 11.x | Konfirmasi delete, alert cantik |
| CountUp.js | 2.x | Animasi angka KPI |
| SimpleBar | 6.x | Custom scrollbar sidebar & panel chat |
| Inputmask | 5.x | `form-input-mask.html` |
| Google Fonts: **Inter** | 400/500/600/700/800 | Font utama |

Muat urutan JS di bawah `</body>`: jQuery → Bootstrap bundle → vendor lain → `app.js` → JS halaman.

---

## 4. DESIGN SYSTEM / TOKEN (tulis di `variables.css`)

Definisikan di `:root` dan override di `[data-bs-theme="dark"]`:

### 4.1 Warna
```css
:root {
  --nx-primary: #6366f1;        /* indigo — warna brand utama (gaya Flux) */
  --nx-primary-hover: #4f46e5;
  --nx-primary-subtle: #eef2ff; /* bg badge/soft button */
  --nx-secondary: #64748b;
  --nx-success: #10b981;  --nx-success-subtle: #ecfdf5;
  --nx-danger:  #ef4444;  --nx-danger-subtle:  #fef2f2;
  --nx-warning: #f59e0b;  --nx-warning-subtle: #fffbeb;
  --nx-info:    #0ea5e9;  --nx-info-subtle:    #f0f9ff;
  --nx-body-bg: #f8fafc;        /* background halaman */
  --nx-card-bg: #ffffff;
  --nx-border:  #e2e8f0;        /* border 1px semua kartu/tabel */
  --nx-text:        #0f172a;    /* heading */
  --nx-text-body:   #334155;
  --nx-text-muted:  #94a3b8;
  --nx-sidebar-bg:  #ffffff;    /* sidebar terang; dark mode: #0f172a */
  --nx-chart-1: #6366f1; --nx-chart-2: #22d3ee; --nx-chart-3: #f59e0b;
  --nx-chart-4: #10b981; --nx-chart-5: #f472b6;
}
```
Dark mode (`[data-bs-theme="dark"]`): body `#0b1220`, card `#111a2e`, border `#1e293b`,
teks `#e2e8f0`, muted `#64748b`. Chart harus re-render mengikuti tema.

### 4.2 Tipografi
- Font: `Inter, system-ui, sans-serif`. Base **14px**, line-height 1.5.
- h1 24px/700 · h2 20px/700 · h3 18px/600 · h4 16px/600 · h5 14px/600 · h6 12px/600 uppercase letter-spacing .05em.
- Angka KPI: 28px/800, `font-variant-numeric: tabular-nums`.
- Teks kecil (caption/label tabel): 12px, muted.

### 4.3 Bentuk & bayangan
- Radius: card **12px**, button/input **8px**, badge/pill **999px**, avatar **50%**.
- Shadow card: `0 1px 2px rgb(15 23 42 / .06)`; hover-lift: `0 8px 24px rgb(15 23 42 / .08)`.
- Border card selalu `1px solid var(--nx-border)` (gaya flat premium, bukan shadow tebal).
- Spacing scale: 4 / 8 / 12 / 16 / 20 / 24 / 32px. Padding card body: **20px**. Gap grid: 24px (`g-4`).
- Transisi global: `all .2s ease` untuk hover/collapse.

---

## 5. LAYOUT UTAMA (semua halaman non-auth/error)

```
┌────────────┬──────────────────────────────────────────┐
│            │  HEADER (sticky, h:64px)                 │
│  SIDEBAR   ├──────────────────────────────────────────┤
│  w:260px   │  PAGE CONTENT (padding 24px)             │
│  fixed     │  … breadcrumb / page title / grid …      │
│            ├──────────────────────────────────────────┤
│            │  FOOTER (h:56px)                         │
└────────────┴──────────────────────────────────────────┘
```

### 5.1 Sidebar — spesifikasi detail
- Fixed kiri, `width: 260px`, tinggi 100vh, bg `--nx-sidebar-bg`, border-kanan 1px.
- **Bagian atas (h:64px)**: logo SVG 28px + teks "NexaDash" 18px/800. Saat collapsed hanya logo.
- **Scroll area** pakai SimpleBar.
- **Group label**: h6 uppercase 11px muted, margin `24px 20px 8px`.
- **Menu item**: tinggi 40px, padding `0 12px`, margin `2px 12px`, radius 8px,
  ikon `bi-*` 18px + label 14px/500, gap 12px.
  - Hover: bg `#f1f5f9` (dark: `#1e293b`).
  - **Active**: bg `--nx-primary-subtle`, teks & ikon `--nx-primary`, font-weight 600,
    plus indikator bar 3px rounded di kiri item.
  - **Badge** di kanan item: pill 11px (contoh: Orders `12` primary, Notifications `3` danger,
    Appointments `8` success).
- **Submenu (collapse)**: item induk punya chevron `bi-chevron-right` yang rotate 90° saat
  terbuka (pakai Bootstrap collapse). Anak: padding-left 44px, dot 5px sebagai bullet.
- **Bagian bawah sidebar (pinned)**: link `Documentation` + mini user card
  (avatar 36px, nama "Aigars S.", role "Admin", ikon `bi-box-arrow-right`).
- **Mode collapsed (desktop)**: toggle di header menyusutkan sidebar ke **72px** — hanya ikon
  di tengah; label & group label disembunyikan; hover item memunculkan **tooltip** nama menu;
  submenu jadi **flyout dropdown** di kanan. Simpan state di `localStorage("nx-sidebar")`.
- **Mobile (<992px)**: sidebar jadi offcanvas dari kiri + backdrop; toggle hamburger di header.

### 5.2 Struktur menu sidebar (LENGKAP — ikuti persis)

**OVERVIEW**
1. Dashboard (`index.html`) — ikon `bi-grid-1x2`
2. Analytics — `bi-graph-up`
3. eCommerce — `bi-bag`
4. CRM — `bi-people`
5. Clinical — `bi-heart-pulse`  *(gaya Ember)*
6. Charts (submenu: ApexCharts, Chart.js, Sparkline) — `bi-bar-chart`

**COMMERCE**
7. Orders — `bi-receipt` — badge `12`
8. Products (submenu: Product List, Product Detail) — `bi-box-seam`
9. Customers — `bi-person-badge`
10. Invoices (submenu: Invoice List, Invoice Detail) — `bi-file-earmark-text`

**APPS**
11. Mail — `bi-envelope`
12. Chat — `bi-chat-dots`
13. Files — `bi-folder`
14. Kanban — `bi-kanban`
15. Calendar — `bi-calendar3`
16. Wizard — `bi-magic`

**UI COMPONENTS** (submenu berisi SEMUA halaman `ui-*.html`)
17. Components — `bi-palette`
18. Forms (submenu: semua `form-*.html`) — `bi-input-cursor-text`
19. Tables (submenu: Basic, DataTables) — `bi-table`

**DEV TOOLS**
20. Roadmap — `bi-signpost-split`
21. Activity — `bi-activity`
22. Changelog — `bi-journal-code`

**PAGES**
23. Profile — `bi-person-circle`
24. Pricing — `bi-tags`
25. FAQ — `bi-question-circle`
26. Timeline — `bi-clock-history`
27. Auth (submenu: Login, Register, Forgot, Lock, 2FA) — `bi-shield-lock`
28. Errors (submenu: 404, 500, Maintenance) — `bi-exclamation-triangle`

**SYSTEM**
29. Users — `bi-people-fill`
30. Notifications — `bi-bell` — badge `3`
31. Settings — `bi-gear`
32. Help & Support — `bi-life-preserver`

### 5.3 Header — spesifikasi detail (kiri → kanan)
1. **Hamburger** (`bi-list`, 20px): desktop = collapse sidebar; mobile = buka offcanvas.
2. **Search / Command palette trigger**: input readonly lebar 280px, radius 8px, bg body-bg,
   placeholder "Search anything…", kbd badge `⌘K` di kanan dalam input. Klik ATAU tekan
   `Ctrl/⌘+K` membuka **modal command palette** (lihat 7.1).
3. Spacer.
4. **Tombol aksi cepat**: `+ New Order` (btn-primary sm, ikon `bi-plus-lg`) — di halaman
   clinical ganti label jadi `+ New Appointment`.
5. **Theme toggle**: ikon `bi-moon-stars` ↔ `bi-sun`; simpan `localStorage("nx-theme")`,
  set `data-bs-theme` di `<html>`; hormati `prefers-color-scheme` saat pertama kali.
6. **Fullscreen toggle** (`bi-arrows-fullscreen`).
7. **Notifications dropdown** (`bi-bell` + dot merah): panel 360px — header "Notifications"
   + link "Mark all read"; 4 item (avatar/ikon bulat berwarna subtle, judul 14px, deskripsi
   12px muted, waktu "2m ago"); footer "View all notifications". Item unread punya dot primary.
8. **User dropdown**: avatar 36px + nama + role (sembunyikan teks di mobile). Menu:
   Profile, Settings, Billing, divider, Lock screen, Logout (merah).

### 5.4 Konten & footer
- Setiap halaman diawali **page header**: breadcrumb (Home / Section / Page) 12px muted di
  atas, lalu baris: judul h1 + subjudul muted, dan tombol aksi kontekstual di kanan
  (contoh: "Download Report" outline + "New …" primary).
- Footer: teks kiri `© 2026 NexaDash — Crafted by Nexa`, kanan link Docs · Support · License.

---

## 6. SPESIFIKASI PER HALAMAN

### 6.1 `index.html` — SaaS Dashboard (gaya Flux, WAJIB paling niat)
Urutan konten:
1. Greeting: "Good morning, Aigars 👋" + subteks "Here's what's happening with your product today." + di kanan: date-range picker (Flatpickr, default "Last 30 days") + tombol Export.
2. **Baris 4 KPI card** (col-xl-3 col-md-6): MRR `$48.2K` (+12.4%), Active Users `12,847`
   (+3.1%), Deployments `342` (−2.2%), Uptime `99.98%` (+0.01%).
   **Anatomi KPI card (komponen terkecil — ikuti persis)**:
   - kiri-atas: label 13px muted; kanan-atas: ikon dalam kotak 40×40 radius 10px bg subtle
     warna masing-masing (`bi-currency-dollar`, `bi-people`, `bi-rocket-takeoff`, `bi-shield-check`).
   - angka besar 28px/800 dengan animasi CountUp saat load.
   - baris bawah: badge trend (panah `bi-arrow-up-right`/`down-right`, bg success/danger subtle,
     teks 12px) + teks "vs last month" 12px muted.
   - sparkline ApexCharts 60×28 di pojok kanan bawah (opsional tapi diusahakan).
3. **Baris chart**: kiri (col-lg-8) card "Revenue Growth" — ApexCharts **area gradient**
   2 seri (Revenue, Expenses), 12 bulan, toolbar off, tab pill kecil `12M | 6M | 30D` di
   header card; kanan (col-lg-4) card "Sales by Channel" — **donut** 4 slice + legend bawah
   + total di tengah donut.
4. **Baris 3 kolom** (col-lg-4 masing-masing):
   - "Sprint 24": progress ring radial ApexCharts 68%, teks "5 days remaining",
     list 3 baris (Done 34 / In progress 12 / Blocked 3) dengan dot warna.
   - "Team Activity": timeline vertikal 5 item — avatar 32px, "**Sarah** deployed `v2.4.1` to
     production", waktu muted; garis konektor 2px.
   - "Recent Deployments": list 4 item — badge env (`prod` success, `staging` warning,
     `dev` secondary), nama release, status ikon check/x, waktu.
5. **Tabel "Recent Orders"** (card full-width): kolom Order ID (link primary `#ORD-1042`),
   Customer (avatar 28px + nama + email 12px muted), Product, Date, Amount (tabular-nums),
   Status (badge pill subtle: Completed/Pending/Cancelled/Refunded), Action (dropdown
   `bi-three-dots-vertical`: View, Edit, Delete-merah → SweetAlert2 confirm). 8 baris,
   header card punya input search kecil + tombol filter; footer card: "Showing 1–8 of 120" + pagination sm.

### 6.2 `dashboard-analytics.html`
KPI row (Sessions, Bounce Rate, Avg. Duration, Conversion) · line chart "Traffic Overview"
dengan annotation · bar horizontal "Top Pages" · **heatmap ApexCharts** "Visitors by Hour" ·
card "Traffic Sources" (progress bar per source: Organic 44%, Direct 27%, Referral 18%,
Social 11%) · card "Devices" radial multi-ring · tabel "Top Campaigns".

### 6.3 `dashboard-ecommerce.html`
KPI (Revenue, Orders, Customers, Conversion) · combo chart bar+line "Orders vs Revenue" ·
"Best Sellers" list (thumb 44px, nama, stok progress tipis, harga) · map placeholder atau
bar "Sales by Country" dengan bendera emoji · "Transactions" list (ikon metode bayar).

### 6.4 `dashboard-crm.html`
KPI (Leads, Deals Won, Pipeline Value, Win Rate) · **funnel** (bar horizontal bertingkat) ·
"Deals by Stage" donut · tabel leads dengan **avatar group** owner · "Tasks" checklist card
(checkbox strike-through saat dicentang, jQuery).

### 6.5 `dashboard-clinical.html` (gaya Ember — WAJIB ada)
1. Greeting "Welcome back, Dr. Smith".
2. KPI: Patients Today `48` (+12%), Appointments `24` (8 left), Bed Occupancy `78%` (+5%),
   Revenue `$42.5K` (+8%).
3. Card besar "24h Patient Vitals Monitor": ApexCharts line realtime-style (heart rate),
   update tiap 2 detik via `setInterval` (append data, animasi smooth), badge "LIVE" merah
   berkedip (CSS pulse).
4. "Bed Occupancy by Ward": 3 progress bar tebal 8px dengan label (ICU 85% danger,
   General 72% primary, Emergency 91% warning) + angka kanan.
5. "Department Workload": bar chart (Emergency, Cardiology, Pediatrics, Ortho, Neurology).
6. "Upcoming Appointments": list 6 pasien (avatar, nama, jam badge, departemen muted).
7. Tabel "Today's Schedule": Time, Patient, Doctor, Department, Type (badge outline:
   In-Person / Telehealth), Status (Confirmed success, Waiting warning, Cancelled danger).

### 6.6 Apps
- **Mail**: layout 3 panel (folder list w:220 — Inbox badge 24, Sent, Draft, Spam, Trash +
  Labels berwarna; list email w:360 — unread bold + dot, checkbox, star toggle jQuery;
  reading pane — header email, isi, attachment chip, toolbar Reply/Forward/Delete).
  Tombol "Compose" membuka modal dengan Quill.
- **Chat**: panel kontak (search, avatar + online dot hijau 10px, last message, unread pill)
  + area chat (bubble kiri bg card, bubble kanan bg primary teks putih, radius 16px dengan
  sudut "ekor" 4px, timestamp 11px, date divider "Today", typing indicator 3 dot animasi CSS)
  + input bawah (attachment, emoji, send). Enter = kirim (append bubble via jQuery + auto scroll).
- **Files**: toolbar (breadcrumb folder, toggle grid/list, tombol Upload → Dropzone modal),
  grid kartu folder (ikon warna, jumlah item, size) + tabel file (ikon per tipe: pdf merah,
  xls hijau, img ungu; kolom Name, Size, Modified, Shared-avatar-group, action) + sidebar
  kanan "Storage" radial 72% + breakdown per tipe.
- **Kanban**: 4 kolom (Backlog, In Progress `badge`, Review, Done) — SortableJS antar kolom;
  kartu: label kategori pill kecil berwarna, judul, deskripsi 2 baris clamp, footer
  (avatar group −2, ikon komentar `bi-chat` 4, ikon attachment 2, due date badge — merah jika
  lewat). Tombol "+ Add task" per kolom (prompt sederhana / modal).
- **Calendar**: FullCalendar bulan/minggu/hari, event berwarna per kategori (Meeting primary,
  Deadline danger, Holiday success), klik tanggal → modal tambah event (judul, kategori
  Select2, tanggal Flatpickr), drag-drop event aktif; sidebar kiri: mini list "Upcoming
  Events" + checkbox filter kategori.
- **Wizard**: stepper horizontal 4 langkah (Account → Company → Plan → Finish) — lingkaran
  nomor 32px (aktif primary, selesai success + ikon check, garis konektor berubah warna),
  validasi per step sebelum Next, step Plan = pilihan pricing card radio, step Finish =
  ringkasan + animasi check sukses.

### 6.7 Commerce
- **Orders**: DataTables penuh — search, filter status (dropdown), filter tanggal, kolom
  seperti tabel Recent Orders + checkbox select-all (menampilkan bulk action bar: badge
  "3 selected" + tombol Delete), export button (copy/csv/print), row klik → offcanvas detail order kanan (item list, alamat, timeline status pengiriman).
- **Products**: grid card produk (gambar 1:1 placeholder SVG, badge diskon kiri-atas, nama,
  kategori muted, rating bintang 5 `bi-star-fill` warning, harga + harga coret, hover:
  overlay tombol View/Edit) + toggle ke table view. Filter sidebar: kategori checkbox,
  price range (noUiSlider), rating.
- **Product Detail**: galeri (gambar utama + 4 thumb), info (rating, harga, varian pill,
  qty stepper `− 1 +` jQuery, Add to cart), tab Description/Specs/Reviews (review = avatar +
  bintang + teks + form balasan).
- **Customers**: DataTables (avatar+nama+email, phone, total orders, total spent, status
  Active/Inactive **form-switch** yang bisa diklik, joined date, action).
- **Invoices**: tabel + status (Paid/Due/Overdue) ; **Invoice Detail**: dokumen invoice rapi
  (logo, alamat from/to, tabel item, subtotal/tax/total rata kanan, catatan, tombol
  Print (`window.print()` + CSS `@media print` yang menyembunyikan sidebar/header) dan Download).

### 6.8 Halaman UI Components — ATURAN UMUM
Setiap halaman `ui-*.html` menampilkan semua varian dalam card per seksi, dengan judul card =
nama varian dan grid rapi. Minimal cakupan:
- **Buttons**: solid semua warna, outline, **soft/subtle** (bg subtle + teks warna — buat
  class custom `.btn-soft-*`), ukuran lg/default/sm/xs(custom), pill, icon-only (square &
  circle), icon+label, loading (spinner + disabled), button group, split dropdown, FAB bulat.
- **Cards**: basic, header+footer, gambar atas, horizontal, hover-lift, dengan tab di header,
  collapsible (chevron), stat card, pricing card, profile card (cover + avatar overlap −32px).
- **Modals**: basic, ukuran sm/lg/xl/fullscreen, centered, scrollable, static backdrop,
  dengan form, konfirmasi delete (ikon lingkaran danger subtle besar + 2 tombol).
- **Tabs & Accordions**: tab default, pill, underline (custom: border-bottom 2px primary),
  vertikal, dengan ikon; accordion default, flush, always-open, custom (ikon plus/minus).
- **Alerts & Badges**: alert semua warna, dengan ikon, dismissible, dengan tombol aksi;
  badge solid/subtle/outline/pill/dot+label/posisi di tombol & avatar (counter merah).
- **Progress & Spinners**: tinggi 4/8/12px, striped animated, stacked, dengan label,
  **circular progress** (SVG custom / ApexCharts radial); spinner border/grow semua warna
  & ukuran, di dalam tombol, skeleton placeholder (Bootstrap placeholder + wave).
- **Tooltips & Popovers**: 4 arah, custom warna, HTML content, dismiss-on-click.
- **Toasts**: posisi 6 titik (pilih via tombol demo), warna, dengan avatar, dengan aksi,
  auto-hide vs manual; plus notifikasi SweetAlert2 (success, confirm delete, input).
- **Dropdowns**: arah 4 sisi, dengan ikon, header+divider, dengan search input, checkbox di
  dalam, mega menu sederhana 2 kolom.
- **Avatars**: ukuran 24/32/40/48/64, rounded vs circle, inisial berwarna (bg subtle),
  dengan status dot (online/away/offline), **avatar group** overlap −8px + counter "+4".
- **Pagination & Breadcrumbs**: default, rounded, dengan ikon panah, disabled, sm/lg;
  breadcrumb dengan ikon home, separator chevron custom.
- **List group & Timeline**: list dengan badge, avatar, aksi, checkbox; timeline vertikal
  (dot berwarna + garis) dan timeline dengan ikon dalam lingkaran.
- **Offcanvas & Placeholders**: offcanvas 4 arah; skeleton card lengkap (avatar+baris teks).
- **Typography**: seluruh heading, display, lead, blockquote, inline text utilities, list.
- **Icons**: grid pencarian ikon Bootstrap Icons (input filter jQuery, klik = copy nama
  class + toast "Copied!").
- **Colors**: swatch semua token (kotak warna + nama variabel + hex), light & dark preview.

### 6.9 Forms
- **Elements**: semua input state (default, focus, disabled, readonly, valid, invalid),
  floating label, input group (ikon kiri/kanan, tombol, dropdown), textarea auto-grow,
  select, checkbox/radio/switch (+ inline, reverse, disabled), range, color, file.
- **Layouts**: form horizontal (label col-3), grid 2 kolom, inline filter bar, form dengan
  section header + divider, form di card dengan footer action (Cancel ghost + Save primary).
- **Validation**: Bootstrap validation (`needs-validation` + `was-validated`), contoh form
  registrasi lengkap; tampilkan juga validasi jQuery custom (password match, min length,
  strength meter progress 4 warna).
- **Select2**: single, multiple (tag), dengan avatar di option (templateResult), grouped,
  disabled, clearable, ajax-like (data lokal difilter).
- **Pickers**: Flatpickr (single, range, datetime, time-only, inline calendar), noUiSlider
  (single, range, tooltip, step, format Rp), colorpicker (input type color + swatch preset).
- **Editors**: Quill/Summernote full toolbar + preview hasil HTML.
- **Upload**: Dropzone (drag area besar, preview thumbnail, progress, remove), plus input
  file custom dengan preview gambar via FileReader.
- **Input mask**: telepon `+62 ___-____-____`, tanggal, kartu kredit (dengan deteksi ikon
  visa/mastercard sederhana), currency `Rp 1.000.000`.

### 6.10 Tables & Charts
- **Basic**: default, striped, hover, bordered, borderless, small, warna kontekstual baris,
  dengan avatar+badge+progress+action (pola "user table"), responsive scroll, sticky header
  (CSS `position: sticky`), tabel dengan footer total.
- **DataTables**: full featured (search, sort, paginate, page length, export buttons,
  column visibility toggle, select rows + bulk bar) — styling harus menyatu Bootstrap 5
  (rapikan input/selek bawaan DataTables dengan CSS).
- **chart-apex.html**: line, area gradient, bar vertikal, bar horizontal, stacked bar,
  mixed bar+line, donut, pie, radial single & multi, gauge setengah lingkaran, heatmap,
  radar, scatter, candlestick, sparkline row. Semua warna dari token `--nx-chart-*`,
  grid dash 4, font Inter, tooltip custom, dan **ikut berubah saat dark mode**
  (dengar event custom `nx:theme-changed` lalu `updateOptions`).
- **chart-chartjs.html**: line, bar, doughnut, polar, radar, bubble.
- **chart-sparkline.html**: grid KPI card kecil masing-masing dengan sparkline tipe berbeda.

### 6.11 Pages
- **Profile**: cover gradient 200px, avatar 96px overlap border 4px putih, nama + role +
  badge verified, stat (Posts/Followers/Following), tab (Overview: about card + skills
  pill + timeline aktivitas; Projects: grid card progress; Settings: form).
- **Settings**: nav pill vertikal kiri (Account, Security, Notifications, Billing, API,
  Danger Zone) — konten kanan per tab: form profil + upload avatar preview; ganti password +
  strength meter; matrix switch notifikasi (Email/Push/SMS per event); billing plan card +
  payment method + tabel invoice; API keys (input readonly + tombol copy + regenerate);
  Danger zone card border merah (Deactivate + Delete account → SweetAlert2 ketik "DELETE").
- **Pricing**: toggle Monthly/Yearly (switch — harga berubah via jQuery, badge "Save 20%"),
  3 card (Starter/Pro `Most Popular` highlight border primary + scale/Enterprise), list fitur
  check hijau / dash muted, tabel perbandingan fitur lengkap di bawah.
- **FAQ**: hero search besar, kategori card (ikon + jumlah artikel), accordion per kategori,
  CTA "Still need help?" + tombol Contact.
- **Users**: DataTables user + kolom Role (badge: Admin danger-subtle, Editor warning-subtle,
  Viewer secondary-subtle) + status dot + last active; header: tombol "Invite User" → modal
  (email + role Select2); stat row kecil di atas (Total, Active, Pending, Suspended).
- **Roadmap**: 3 kolom status (Planned / In Progress / Shipped) berisi card fitur dengan
  vote counter (▲ 23 — klik bertambah, jQuery), tag, quarter badge.
- **Activity**: feed timeline harian dengan filter tipe (commit, deploy, comment, alert) —
  ikon lingkaran warna per tipe.
- **Changelog**: list versi (badge `v2.4.1` + tanggal + label New/Improved/Fixed berwarna +
  bullet perubahan), garis timeline kiri.
- **Notifications**: list panjang dengan tab All/Unread/Mentions, group per hari, tombol
  mark-read per item & semua.
- **Timeline**: timeline tengah zigzag kiri-kanan (desktop) → satu sisi (mobile).

### 6.12 Auth & Error (layout khusus TANPA sidebar/header)
- Layout auth: split screen — kiri 55% panel brand (bg gradient primary→indigo tua, logo,
  headline, 3 bullet fitur, testimonial card blur); kanan form di card polos max-width 420px
  center. Mobile: panel brand disembunyikan.
- **Login**: email, password (toggle mata show/hide), remember switch, lupa password,
  tombol full, divider "or continue with", 2 tombol sosial outline (Google, GitHub ikon).
- **Register**: nama, email, password + strength meter, checkbox terms, link login.
- **Forgot**: 1 input + instruksi; sukses = ganti konten card jadi ikon amplop + teks cek email.
- **Lock screen**: avatar besar + nama + 1 input password.
- **2FA**: 6 kotak input OTP 48×56px (auto-focus pindah, paste 6 digit tersebar otomatis,
  backspace mundur), countdown resend 30 detik.
- **404/500**: angka raksasa 120px/800 warna primary subtle, ilustrasi SVG inline sederhana,
  teks, tombol "Back to Dashboard". **Maintenance**: + countdown timer JS.

---

## 7. INTERAKSI GLOBAL (`app.js`)

### 7.1 Command Palette (komponen andalan — buat serius)
- Modal custom (bukan modal Bootstrap standar look-nya): muncul dari atas-tengah, width 560px,
  radius 16px, backdrop blur.
- Input besar tanpa border + ikon search; di bawahnya list hasil digroup: **Pages**,
  **Actions** (New Order, Toggle Theme, Lock Screen), **Recent**.
- Data = array statis semua halaman (judul, url, ikon, keyword). Filter fuzzy sederhana saat
  mengetik (match substring, highlight `<mark>`).
- Navigasi keyboard penuh: ↑↓ pindah (item aktif bg subtle), Enter buka, Esc tutup.
- Footer palette: hint kbd `↑↓ navigate · ↵ open · esc close`.

### 7.2 Lainnya
- Init semua tooltip & popover Bootstrap otomatis.
- Theme toggle: set `data-bs-theme`, simpan localStorage, dispatch `nx:theme-changed`
  (semua chart mendengarkan dan update warna/grid/tooltip).
- Sidebar collapse state + active menu otomatis: `app.js` mencocokkan `location.pathname`
  dengan `href` menu → tambah `.active` + buka parent collapse (jadi tidak perlu set manual
  per halaman, tapi tetap pastikan bekerja).
- CountUp dijalankan untuk semua elemen `[data-countup]` (dukung prefix/suffix/desimal via
  data-attribute).
- Tombol back-to-top muncul setelah scroll 300px.
- Semua `console.error` bersih — nol error JS di setiap halaman.

---

## 8. RESPONSIVE — perilaku per breakpoint

| Breakpoint | Perilaku |
|---|---|
| ≥1200 (xl) | Layout penuh, KPI 4 kolom |
| 992–1199 (lg) | KPI 2×2, chart row jadi stack sebagian |
| 768–991 (md) | Sidebar → offcanvas, search header → ikon saja (buka palette), KPI 2×2 |
| <768 (sm) | Semua 1 kolom; tabel scroll-x; mail/chat/files jadi 1 panel dengan navigasi back; kanban scroll horizontal snap; page header action jadi icon button |
| <576 | Padding konten 16px; user dropdown hanya avatar |

---

## 9. KUALITAS & ATURAN WAJIB

1. HTML5 semantik (`nav`, `main`, `aside`, `header`, `footer`), heading berurutan.
2. Aksesibilitas: semua icon-button punya `aria-label`; kontras teks AA; focus-visible ring
   2px primary; modal/offcanvas trap focus (bawaan Bootstrap); `alt` di semua gambar.
3. Konsistensi mutlak: spacing, radius, ukuran ikon, gaya badge SAMA di semua halaman —
   sumber kebenaran = `variables.css` + `components.css`, dilarang hardcode warna hex di HTML.
4. Dark mode harus sempurna di SETIAP halaman termasuk chart, DataTables, Select2, Flatpickr,
   Dropzone, FullCalendar (tulis override di `dark.css`).
5. Tidak ada link mati: semua `href` menunjuk file yang benar-benar ada.
6. Komentar HTML pemisah seksi: `<!-- ===== KPI Cards ===== -->`.
7. Berat halaman wajar: jangan embed base64 besar; gambar pakai SVG placeholder ringan.
8. Cross-browser: Chrome, Safari, Firefox terbaru.

---

## 10. URUTAN PENGERJAAN (kerjakan bertahap, jangan sekaligus)

1. **Fase 1 — Fondasi**: `variables.css`, `layout.css`, `components.css`, `app.js`,
   `index.html` lengkap (sidebar + header + semua widget 6.1). Ini jadi master referensi.
2. **Fase 2 — Dashboards**: analytics, ecommerce, crm, clinical + 3 halaman chart.
3. **Fase 3 — UI kit**: semua `ui-*`, `form-*`, `table-*`.
4. **Fase 4 — Apps & Commerce**: mail, chat, files, kanban, calendar, wizard, orders,
   products, customers, invoices.
5. **Fase 5 — Pages & Auth**: profile, settings, pricing, faq, users, roadmap, activity,
   changelog, notifications, timeline, semua auth & error.
6. **Fase 6 — QA**: klik semua menu, cek dark mode semua halaman, cek 5 breakpoint,
   validasi nol error console, rapikan inkonsistensi.

Setiap fase: tunjukkan daftar file yang dibuat/diubah dan cara mengetesnya.

---

## 11. KRITERIA SELESAI (Definition of Done)

- [ ] Semua ±55 halaman ada, saling terhubung, sidebar aktif otomatis.
- [ ] Bootstrap 5 + jQuery termuat dan benar-benar dipakai.
- [ ] Command palette (⌘K), dark mode persist, sidebar collapse persist — ketiganya bekerja.
- [ ] Semua chart re-render mengikuti tema.
- [ ] Nol error console, nol link 404, responsif di 5 breakpoint.
- [ ] Visual konsisten setara referensi Flux/Ember (flat, border tipis, radius 12, Inter).

---

## Addendum — scope after the component audit

The template shipped past this original brief. After auditing the page inventories of
`demos.themeselection.com` (Sneat) and `prium.github.io/falcon-tailwind`, another 47 pages
were added to close the gaps:

**Dashboards** — Project, Support Desk, Academy, Logistics.

**Apps** — Social feed, Contacts, Notes & Tasks, Support Tickets (list + detail),
Events (list + detail), E-Learning (course list + detail).

**Commerce** — Add Product, Categories tree, full Order detail, Customer detail,
Shopping cart, Checkout, Reviews moderation.

**UI** — Statistic cards, Carousel, Navbar & Footer, Star ratings, Media player, Treeview,
BlockUI & dividers.

**Forms** — Custom options (selectable cards, segmented controls, switch lists),
Sticky action bar.

**Pages** — Landing, Payment, Help centre, Starter, Roles, Permissions matrix, User detail,
Teams, Projects, Connections, and a two/three-level menu demo.

**Auth & errors** — Verify email, Reset password, 401 Not Authorized, Coming soon.

**Layouts** — Horizontal top menu, fluid full width, blank canvas.

Plus a **Plugins** section: a searchable catalogue of 50 libraries with ten working demo
pages covering maps, data grids, media, advanced inputs, documents and export, UX and
onboarding, drag-and-drop layout, utilities, diagrams, and an org chart.

See [`README.md`](README.md) for the current inventory and the QA results.
