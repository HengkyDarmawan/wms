#!/usr/bin/env node
/* NexaDash — uji fungsional: klik kontrol nyata di halaman interaktif dan
   pastikan tidak ada error runtime. Jalankan: node tools/qa-interaction.js  */
const path = require('path');
const puppeteer = require('puppeteer-core');
const CHROME = process.env.NX_CHROME || require('os').homedir() + '/.cache/puppeteer/chrome/mac_arm-152.0.7977.75/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';
const BASE = process.env.NX_BASE || 'file://' + path.join(__dirname, '..') + '/';

/* Tiap langkah: [selector, label]. Selector yang tidak ada dilewati dengan catatan. */
const SUITES = [
  ['plugins/org-chart.html', [
    ['#ocExpand', 'expand all'], ['#ocCollapse', 'collapse all'],
    ['.oc-layout[data-layout="left"]', 'layout left'],
    ['.oc-layout[data-layout="bottom"]', 'layout bottom'],
    ['.oc-layout[data-layout="right"]', 'layout right'],
    ['.oc-layout[data-layout="top"]', 'layout top'],
    ['#ocZoomIn', 'zoom in'], ['#ocZoomOut', 'zoom out'], ['#ocFit', 'fit'],
    ['#ocCompact', 'compact toggle'], ['#ocCompact', 'compact untoggle'],
    ['.oc-node', 'select a node'],
    ['#ocAdd', 'add without name (validation)'],
    ['@type:#ocNewName:Test Person', 'type name'],
    ['@type:#ocNewRole:QA Engineer', 'type role'],
    ['#ocAdd', 'add report'],
    ['@type:#ocSearch:sarah', 'search'],
    ['@type:#ocSearch:', 'clear search'],
    ['#ocRemove', 'remove selected'],
    ['#ocReset', 'reset chart']
  ]],
  ['plugins/maps.html', [
    ['.loc-item', 'fly to location'],
    ['#pickReset', 'clear pin']
  ]],
  ['plugins/data-grids.html', [
    ['#tabAddRow', 'add row'], ['#tabGroup', 'group'], ['#tabGroup', 'ungroup'],
    ['@type:#tabFilter:Sarah', 'filter'], ['@type:#tabFilter:', 'clear filter']
  ]],
  ['plugins/media.html', [
    ['.crop-ratio[data-ratio="1"]', 'crop 1:1'],
    ['.crop-ratio[data-ratio="1.7777"]', 'crop 16:9'],
    ['#cropRotL', 'rotate left'], ['#cropRotR', 'rotate right'],
    ['#cropFlipH', 'flip'], ['#cropApply', 'apply crop'], ['#cropReset', 'reset crop'],
    ['.swiper-button-next', 'swiper next']
  ]],
  ['plugins/inputs.html', [
    ['#sigClear', 'clear signature'], ['#sigUndo', 'undo signature'],
    ['#sigPng', 'export png (empty)'], ['#sigSvg', 'export svg (empty)']
  ]],
  ['plugins/documents.html', [
    ['#pdfInvoice', 'generate invoice'],
    ['#pdfNext', 'pdf next page'], ['#pdfPrev', 'pdf prev page'],
    ['#pdfZoomIn', 'pdf zoom in'], ['#pdfZoomOut', 'pdf zoom out'],
    ['#pdfTable', 'pdf from table'],
    ['#capShot', 'html2canvas capture']
  ]],
  ['plugins/ux.html', [
    ['#tourHighlight', 'highlight'], ['@key:Escape', 'close highlight'],
    ['#npStart', 'nprogress start'], ['#npInc', 'increment'], ['#npDone', 'done'],
    ['#copyBtn', 'copy'],
    ['.toastify-demo[data-kind="success"]', 'toast success'],
    ['.toastify-demo[data-kind="action"]', 'toast with action']
  ]],
  ['plugins/layout.html', [
    ['#gsAdd', 'add widget'], ['#gsSave', 'save layout'], ['#gsLoad', 'restore layout'],
    ['#gsLock', 'lock'], ['#gsLock', 'unlock'], ['#gsReset', 'reset'],
    ['#ixSnap', 'toggle snap'], ['#ixInertia', 'toggle inertia'], ['#ixReset', 'reset boxes']
  ]],
  ['plugins/utilities.html', [
    ['@type:#fuseInput:kubrnetes', 'fuzzy search typo'],
    ['@type:#markInput:metric', 'highlight term'],
    ['#markClear', 'clear highlight'],
    ['@type:#qrValue:https://example.com', 'qr value'],
    ['#lottiePause', 'lottie pause'], ['#lottiePlay', 'lottie play'], ['#lottieStop', 'lottie stop']
  ]],
  ['plugins/diagrams.html', [
    ['.mm-sample[data-sample="sequence"]', 'sequence sample'],
    ['.mm-sample[data-sample="state"]', 'state sample'],
    ['.mm-sample[data-sample="er"]', 'er sample'],
    ['.mm-sample[data-sample="flow"]', 'flow sample'],
    ['#mmRender', 'render']
  ]],
  ['plugins/index.html', [
    ['#pluginFilters button[data-cat="maps"]', 'filter maps'],
    ['#pluginFilters button[data-cat="all"]', 'filter all'],
    ['@type:#pluginSearch:pdf', 'search plugins'],
    ['@type:#pluginSearch:', 'clear search']
  ]],
  /* ---------- Halaman baru ---------- */
  ['apps/social.html', [
    ['.btn-like', 'like a post'], ['.btn-like', 'unlike'],
    ['.btn-comment', 'open comments'],
    ['@type:#postInput:Testing the composer', 'write a post'],
    ['#postSubmit', 'publish post'],
    ['.btn-follow', 'follow someone']
  ]],
  ['apps/contacts.html', [
    ['@type:#contactSearch:sarah', 'search contacts'], ['@type:#contactSearch:', 'clear search'],
    ['#cvTable', 'table view'], ['#cvGrid', 'grid view']
  ]],
  ['apps/notes.html', [
    ['.note-filter[data-label="Work"]', 'filter work'],
    ['.note-filter[data-label=""]', 'all notes'],
    ['.note-pin', 'pin a note'],
    ['@type:#taskInput:Write the release notes', 'type task'],
    ['#taskAdd', 'add task'],
    ['.task-check', 'complete task'],
    ['#noteNew', 'open editor'],
    ['@type:#noteTitle:A new note', 'type title'],
    ['#noteSave', 'save note']
  ]],
  ['apps/tickets.html', [
    ['@type:#tkSearch:billing', 'search'], ['@type:#tkSearch:', 'clear'],
    ['#tkReset', 'reset filters'],
    ['#tkCheckAll', 'select all'], ['#tkBulkResolve', 'bulk resolve'],
    ['@open:#ticketsDT tbody tr:first-child [data-bs-toggle="dropdown"]', 'open row menu'],
    ['.btn-resolve', 'resolve one']
  ]],
  ['apps/ticket-detail.html', [
    ['#tdNote', 'internal note without text'],
    ['#tdResolve', 'open resolve dialog'], ['@key:Escape', 'dismiss']
  ]],
  ['apps/events.html', [
    ['#evFilters button[data-type="Webinar"]', 'filter webinars'],
    ['#evFilters button[data-type="all"]', 'all events'],
    ['@type:#evSearch:nexacon', 'search'], ['@type:#evSearch:', 'clear']
  ]],
  ['apps/event-detail.html', [
    ['#evPlus', 'more tickets'], ['#evMinus', 'fewer tickets'],
    ['#evSave', 'save event'],
    ['[data-bs-target="#evVenue"]', 'open venue tab']
  ]],
  ['apps/courses.html', [
    ['#trA', 'toggle analytics track'], ['#lvB', 'beginner only'],
    ['#crEnrolled', 'only my courses'], ['#crClear', 'clear filters']
  ]],
  ['apps/course-detail.html', [
    ['.lesson', 'toggle a lesson'], ['#cdResume', 'resume'], ['#cdDownload', 'download resources']
  ]],
  ['commerce/product-add.html', [
    ['@type:#paName:Test Product', 'type name'],
    ['#paAddVariant', 'add variant'], ['.pa-del-variant', 'remove variant'],
    ['@type:#paPrice:200', 'change price'],
    ['#btnPublish', 'publish']
  ]],
  ['commerce/categories.html', [
    ['.nx-tree-row', 'select a category'],
    ['#catExpandAll', 'expand all'], ['#catExpandAll', 'collapse all'],
    ['@type:#catSearch:speak', 'search tree'], ['@type:#catSearch:', 'clear'],
    ['#catSave', 'save category']
  ]],
  ['commerce/order-detail.html', [
    ['@type:#odNoteInput:Checked with the warehouse', 'type note'],
    ['#odNoteAdd', 'add note'],
    ['#odTrack', 'track parcel'], ['@key:Escape', 'dismiss'],
    ['#odResend', 'resend confirmation']
  ]],
  ['commerce/customer-detail.html', [
    ['[data-bs-target="#cdOrders"]', 'orders tab'],
    ['[data-bs-target="#cdAddresses"]', 'addresses tab'],
    ['[data-bs-target="#cdNotify"]', 'notifications tab']
  ]],
  ['commerce/cart.html', [
    ['.qty-plus', 'increase quantity'], ['.qty-minus', 'decrease quantity'],
    ['@type:#promoInput:WELCOME10', 'enter promo'], ['#promoApply', 'apply promo'],
    ['.cart-add', 'add suggested item'],
    ['.cart-remove', 'remove an item']
  ]],
  ['commerce/checkout.html', [
    ['#coNext', 'step 1 to 2'],
    ['@label:.co-ship[value="24"]', 'pick express'],
    ['#coNext', 'step 2 to 3'],
    ['#payPaypal', 'switch to paypal'], ['#payCard', 'back to card'],
    // Tombol Back sengaja hilang setelah pesanan dibuat, jadi diuji sebelum langkah terakhir.
    ['#coPrev', 'back to delivery'],
    ['#coNext', 'forward again'],
    ['#coNext', 'place order']
  ]],
  ['commerce/reviews.html', [
    ['#rvFilters button[data-f="pending"]', 'pending only'],
    ['#rvFilters button[data-f="all"]', 'all reviews'],
    ['.rv-approve', 'approve a review'],
    ['.rv-reply', 'open reply box'],
    ['.rv-flag', 'flag a review']
  ]],
  ['ui/ratings.html', [
    ['label[for="rm4"]', 'rate 4 stars'], ['#rateSubmit', 'submit rating']
  ]],
  ['ui/treeview.html', [
    ['.nx-tree-row', 'select a node'],
    ['#tvToggleAll', 'expand all'], ['#tvToggleAll', 'collapse all'],
    ['@type:#tvSearch:app', 'filter tree'], ['@type:#tvSearch:', 'clear'],
    ['#permCommerce', 'tick a parent permission']
  ]],
  ['ui/blockui.html', [
    ['#blockCard', 'block a card'], ['#blockToggle', 'toggle block'], ['#blockToggle', 'unblock'],
    ['#buSave', 'block the form while saving']
  ]],
  ['ui/carousel.html', [
    ['#carBasic .carousel-control-next', 'next slide'],
    ['[data-bs-target="#carCards"][data-bs-slide="next"]', 'next card']
  ]],
  ['ui/media-player.html', [
    ['#mpVideoFwd', 'skip forward'], ['#mpVideoBack', 'skip back'],
    ['#mpVideoMute', 'mute'], ['#mpVideoMute', 'unmute'],
    ['.playlist-item', 'switch track']
  ]],
  ['ui/stat-cards.html', [['.nx-card-lift', 'hover a card']]],
  ['forms/custom-options.html', [
    ['label[for="cwCyan"]', 'pick a colour'],
    ['#cpAnnual', 'annual billing'],
    ['@label:.co-addon', 'toggle an add-on']
  ]],
  ['forms/sticky-actions.html', [
    ['@type:#saName:Renamed workspace', 'edit a field'],
    ['#saSave', 'save'],
    ['#saNav a[href="#saLimits"]', 'jump to limits']
  ]],
  ['pages/roles.html', [
    ['.role-edit', 'edit a role'], ['@key:Escape', 'close'],
    ['#roleAdd', 'add a role'], ['@type:#rlName:QA Lead', 'name it'], ['#rlSave', 'save']
  ]],
  ['pages/permissions.html', [
    ['@type:#pmSearch:refund', 'search permissions'], ['@type:#pmSearch:', 'clear'],
    ['.pm-check', 'toggle a permission'],
    ['#pmSave', 'save matrix'],
    ['#pmReset', 'reset']
  ]],
  ['pages/user-detail.html', [
    ['[data-bs-target="#udSecurity"]', 'security tab'],
    ['[data-bs-target="#udActivity"]', 'activity tab'],
    ['[data-bs-target="#udBilling"]', 'billing tab']
  ]],
  ['pages/teams.html', [
    ['@type:#tmSearch:design', 'search teams'], ['@type:#tmSearch:', 'clear'],
    ['#tmAdd', 'add a team'],
    ['@open:#tmGrid .tm-item:first-child [data-bs-toggle="dropdown"]', 'open team menu'],
    ['.tm-del', 'delete a team']
  ]],
  ['pages/projects.html', [
    ['#prFilters button[data-s="At risk"]', 'at-risk only'],
    ['#prFilters button[data-s="all"]', 'all projects'],
    ['#prTableBtn', 'table view'], ['#prGridBtn', 'grid view']
  ]],
  ['pages/connections.html', [
    ['@type:#cnSearch:jira', 'search integrations'],
    ['.cn-connect', 'connect one'],
    ['@type:#cnSearch:', 'clear'],
    ['.cn-social', 'toggle a social account'],
    ['.cn-config', 'open config'], ['@key:Escape', 'dismiss']
  ]],
  ['pages/help-center.html', [
    ['.hc-chip', 'search a popular term'],
    ['.hc-cat', 'pick a category'], ['.hc-cat', 'clear category']
  ]],
  ['pages/payment.html', [
    ['@type:#pmNumber:4242424242424242', 'type a card number'],
    ['@type:#pmName:Maria Gomez', 'type the name'],
    ['#pmMonthly', 'monthly billing'], ['#pmAnnual', 'annual billing'],
    ['@type:#pmPromo:WELCOME10', 'enter promo'], ['#pmPromoApply', 'apply promo'],
    ['#pmPay', 'pay']
  ]],
  ['pages/landing.html', [['[data-bs-target="#lpTraffic"]', 'traffic tab']]],
  ['layouts/horizontal.html', [['.nx-hnav-list a', 'horizontal nav renders']]],
  ['errors/coming-soon.html', [
    ['@type:#csEmail:me@example.com', 'enter email'], ['#csForm button', 'subscribe']
  ]],
  ['auth/reset-password.html', [
    ['@type:#rpPassword:Str0ng!Passphrase', 'type password'],
    ['.btn-toggle-pw', 'reveal password']
  ]],

  ['apps/kanban.html', [['#kanbanSearch', 'focus search']]],
  ['apps/calendar.html', [['.fc-next-button', 'next month'], ['.fc-today-button', 'today']]],
  ['apps/wizard.html', [['#wizNext', 'next (validation blocks)']]]
];

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', executablePath: CHROME, args: ['--no-sandbox'] });
  let problems = 0;

  for (const [file, steps] of SUITES) {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    const errors = [];
    page.on('pageerror', e => errors.push(String(e).slice(0, 200)));
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text().slice(0, 200)); });

    await page.goto(BASE + file, { waitUntil: 'load', timeout: 60000 });
    await new Promise(r => setTimeout(r, 2200));

    const skipped = [];
    for (const [sel, label] of steps) {
      try {
        if (sel.startsWith('@type:')) {
          const [, target, value = ''] = sel.split(':');
          const ok = await page.$(target);
          if (!ok) { skipped.push(label); continue; }
          // Kosongkan dulu lewat DOM — mengetik string kosong tidak menghapus isi lama.
          await page.evaluate((t, v) => {
            const el = document.querySelector(t);
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
            if (!v) el.dispatchEvent(new Event('change', { bubbles: true }));
          }, target, value);
          if (value) {
            await page.type(target, value, { delay: 8 });
            await page.evaluate(t => {
              const el = document.querySelector(t);
              el.dispatchEvent(new Event('input', { bubbles: true }));
              el.dispatchEvent(new Event('change', { bubbles: true }));
            }, target);
          }
        } else if (sel.startsWith('@open:')) {
          // Buka dropdown Bootstrap yang menampung langkah berikutnya.
          const target = sel.slice(6);
          const el = await page.$(target);
          if (!el) { skipped.push(label); continue; }
          await el.click({ delay: 10 });
        } else if (sel.startsWith('@label:')) {
          // Input yang disembunyikan (d-none) hanya bisa diaktifkan lewat label-nya.
          const target = sel.slice(7);
          const found = await page.evaluate(t => {
            const input = document.querySelector(t);
            if (!input) return false;
            const label = input.id ? document.querySelector('label[for="' + input.id + '"]') : input.closest('label');
            (label || input).click();
            return true;
          }, target);
          if (!found) { skipped.push(label); continue; }
        } else if (sel.startsWith('@key:')) {
          await page.keyboard.press(sel.split(':')[1]);
        } else {
          const el = await page.$(sel);
          if (!el) { skipped.push(label); continue; }
          await el.click({ delay: 10 });
        }
        await new Promise(r => setTimeout(r, 620));
      } catch (e) {
        errors.push('STEP "' + label + '": ' + e.message.slice(0, 140));
      }
    }

    await new Promise(r => setTimeout(r, 700));
    if (errors.length) {
      problems++;
      console.log('✗ ' + file);
      [...new Set(errors)].slice(0, 6).forEach(e => console.log('    - ' + e));
    } else {
      console.log('✓ ' + file + '  (' + steps.length + ' interactions' + (skipped.length ? ', ' + skipped.length + ' skipped' : '') + ')');
    }
    if (skipped.length) console.log('    skipped: ' + skipped.join(', '));
    await page.close();
  }

  await browser.close();
  console.log('\n' + (problems ? problems + ' page(s) threw during interaction.' : 'All interactions ran without errors.'));
})();
