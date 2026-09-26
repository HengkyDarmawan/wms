// Uji perilaku template di Chrome headless lewat CDP (Node 24: fetch + WebSocket bawaan).
import { spawn } from 'node:child_process';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const BASE = process.env.WMS_BASE || 'http://demo.wms.test:8000';
const profile = mkdtempSync(join(tmpdir(), 'wmsui-'));
const chrome = spawn(CHROME, [
  '--headless=new', '--remote-debugging-port=9333', '--no-first-run', '--disable-gpu',
  `--user-data-dir=${profile}`,
  '--host-resolver-rules=MAP demo.wms.test 127.0.0.1, MAP wms.test 127.0.0.1',
  'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let target;
for (let i = 0; i < 50 && !target; i++) {
  try { target = (await (await fetch('http://127.0.0.1:9333/json')).json()).find((t) => t.type === 'page'); } catch {}
  if (!target) await sleep(200);
}
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener('open', r));
let id = 0; const pending = new Map(); const errors = [];
ws.addEventListener('message', (ev) => {
  const m = JSON.parse(ev.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
  if (m.method === 'Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text);
  if (m.method === 'Runtime.consoleAPICalled' && m.params.type === 'error') errors.push(m.params.args.map((a) => a.value ?? a.description).join(' '));
  if (m.method === 'Log.entryAdded' && m.params.entry.level === 'error') errors.push(m.params.entry.text + ' ' + (m.params.entry.url || ''));
});
const send = (method, params = {}) => new Promise((r) => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
const ev = async (expr) => (await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true })).result.result.value;
const siap = async () => {
  for (let i = 0; i < 60; i++) {
    await sleep(250);
    try {
      const s = await ev('document.readyState === "complete" && (!document.querySelector("[wire\\:id]") || !!window.Livewire)');
      if (s) break;
    } catch { /* konteks halaman sedang berganti */ }
  }
  await sleep(400);
};
const go = async (url) => { await send('Page.navigate', { url }); await siap(); };
const viewport = (w, h) => send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile: w < 992 });

await send('Page.enable'); await send('Runtime.enable'); await send('Log.enable');
const hasil = [];
const cek = (nama, ok, info = '') => hasil.push(`${ok ? 'OK  ' : 'GAGAL'} ${nama}${info ? ' — ' + info : ''}`);

// 1. Login: tombol mata
await viewport(1366, 600);
await go(`${BASE}/login`);
cek('login: jQuery global', await ev('typeof window.jQuery === "function"'));
await ev('document.querySelector(".btn-toggle-pw").click()');
cek('login: mata → text', (await ev('document.getElementById("password").type')) === 'text');
await ev('document.querySelector(".btn-toggle-pw").click()');
cek('login: mata → password', (await ev('document.getElementById("password").type')) === 'password');

// 2. Login admin
await ev(`document.getElementById("email").value="admin@demo.wms.test"; document.getElementById("password").value="Demo#2026!"; document.querySelector("form").submit();`);
await sleep(1500);
// Tunggu Beranda + SimpleBar sidebar terpasang (JS bundel bisa tertinggal saat server dev sibuk).
for (let i = 0; i < 30; i++) { try { if (await ev('location.pathname === "/" && !!document.querySelector("#nxSidebarNav .simplebar-content-wrapper")')) break; } catch {} await sleep(300); }
cek('login berhasil', (await ev('location.pathname')) === '/', await ev('location.href'));

// 3. Sidebar scroll
const s = await ev(`(() => { const n = document.getElementById('nxSidebarNav');
  const w = n.querySelector('.simplebar-content-wrapper') || n;
  const before = { sh: w.scrollHeight, ch: w.clientHeight };
  w.scrollTop = 200; return { simplebar: !!n.querySelector('.simplebar-content-wrapper'), ...before, top: w.scrollTop }; })()`);
cek('sidebar: SimpleBar aktif', s.simplebar);
cek('sidebar: bisa di-scroll', s.sh > s.ch && s.top > 0, JSON.stringify(s));

// 3a. Grup lipat + filter menu (10-access §6, A-227): hanya grup aktif terbuka; ketik "opname" → butir lain tersembunyi.
const grup = await ev('({ total: document.querySelectorAll("#nxSidebar .nx-menu-section .nx-section-body").length, terbuka: document.querySelectorAll("#nxSidebar .nx-section-body.show").length })');
cek('sidebar: grup lipat', grup.total >= 8 && grup.terbuka >= 1 && grup.terbuka < grup.total, JSON.stringify(grup));
await ev(`(() => { const i = document.getElementById('nxMenuFilter'); i.value = 'opname'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
await sleep(400);
const saring = await ev('({ tampil: [...document.querySelectorAll("#nxSidebar .nx-menu-item")].filter(e => e.offsetParent !== null).map(e => e.textContent.trim()), })');
cek('sidebar: filter menu', saring.tampil.length > 0 && saring.tampil.length < 10 && saring.tampil.every(t => /opname/i.test(t)), saring.tampil.join(' | '));
await ev(`(() => { const i = document.getElementById('nxMenuFilter'); i.value = ''; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);

// 4. Toggle sidebar, tema, palet, dropdown
await ev('document.getElementById("nxSidebarToggle").click()');
cek('toggle sidebar → collapsed', await ev('document.body.classList.contains("nx-sidebar-collapsed")'));
await ev('document.getElementById("nxSidebarToggle").click()');
const t0 = await ev('document.documentElement.getAttribute("data-bs-theme")');
await ev('document.getElementById("nxThemeToggle").click()');
cek('toggle tema', (await ev('document.documentElement.getAttribute("data-bs-theme")')) !== t0);
await ev('document.getElementById("nxThemeToggle").click()');
await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'k', code: 'KeyK', modifiers: 2, windowsVirtualKeyCode: 75 });
await sleep(300);
cek('Ctrl+K palet', await ev('!!document.querySelector("#nxPalette.show")'));
await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
await ev('document.querySelector(".nx-header-user").click()');
await sleep(300);
cek('dropdown pengguna', await ev('!!document.querySelector(".nx-header .dropdown-menu.show")'));

// 5. HP
await viewport(390, 800);
await go(`${BASE}/`);
await ev('document.getElementById("nxSidebarToggle").click()');
cek('HP: sidebar terbuka', await ev('document.body.classList.contains("nx-sidebar-open")'));
await ev('document.getElementById("nxSidebarBackdrop").click()');
cek('HP: backdrop menutup', !(await ev('document.body.classList.contains("nx-sidebar-open")')));

// 5a. Layar lapangan & form di lebar HP: tanpa scroll mendatar (bandingkan scrollWidth vs clientWidth,
// emulasi mobile melebarkan innerWidth mengikuti konten yang meluber).
for (const path of ['/counts', '/picks', '/receipts', '/conversions/create', '/projects/1', '/purchase-requests/create']) {
  await go(`${BASE}${path}`);
  const lebar = await ev('({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth })');
  cek(`HP ${path}: tanpa scroll mendatar`, lebar.sw <= lebar.cw, JSON.stringify(lebar));
}

// 6. Semua tautan menu tanpa error
await viewport(1366, 800);
const links = await ev('[...new Set([...document.querySelectorAll("#nxSidebar a.nx-menu-link[href]")].map(a => a.href).filter(h => h.startsWith(location.origin)))]');
for (const url of links) {
  const sebelum = errors.length;
  await go(url);
  const st = await ev('document.title');
  cek(`halaman ${new URL(url).pathname}`, errors.length === sebelum, errors.slice(sebelum).join(' | ') || st);
}

// 6a. Hub proyek (11-master §6.3, A-228): kartu ringkas, semua tab tanpa error konsol.
{
  const sebelum = errors.length;
  await go(`${BASE}/projects/1`);
  const judul = await ev('document.querySelector("main h1")?.textContent?.trim() || ""');
  cek('hub proyek: judul', /PRJ-001|Pipa Karawang/.test(judul), judul);
  const tabs = await ev('[...document.querySelectorAll("main .nav-tabs .nav-link")].map(b => b.textContent.trim())');
  cek('hub proyek: 9 tab', tabs.length === 9, tabs.join(' | '));
  for (const [i, nama] of tabs.entries()) {
    await ev(`document.querySelectorAll("main .nav-tabs .nav-link")[${i}].click()`);
    await sleep(900);
  }
  cek('hub proyek: tab tanpa error', errors.length === sebelum, errors.slice(sebelum).join(' | '));
  cek('hub proyek: tombol aksi', await ev('!!document.querySelector(`main a[href*="/requests/create?project="]`)'));
}

// 6b. Layar tinjauan pemilik 26 Sep 2026: denah gudang (A-254), pindahan proyek (A-250), dokumen terkait (A-252).
{
  const sebelum = errors.length;
  await go(`${BASE}/warehouses/1/layout`);
  cek('denah gudang: svg zona', await ev('document.querySelectorAll("main svg rect").length > 0'));
  await ev('document.querySelector("main svg g")?.dispatchEvent(new MouseEvent("click", { bubbles: true }))');
  await sleep(900);
  cek('denah gudang: panel rak', await ev('!!document.querySelector("main .border-primary")'));
  await go(`${BASE}/projects/1/move`);
  cek('pindahan proyek: layar', /Pindahkan/.test(await ev('document.querySelector("main h1")?.textContent || ""')));
  await go(`${BASE}/shipments`);
  const sj = await ev('document.querySelector(`main a[href*="/shipments/"]:not([href$="/create"])`)?.href || ""');
  if (sj) {
    await go(sj);
    cek('SJ: dokumen terkait', await ev('[...document.querySelectorAll("main .card-header")].some(h => /Dokumen terkait/.test(h.textContent))'));
  }
  cek('layar baru tanpa error console', errors.length === sebelum, errors.slice(sebelum).join(' | '));
}

// 6c. Impor struktur gudang (A-258): kartu di /imports dan templat bisa diunduh.
{
  const sebelum = errors.length;
  await go(`${BASE}/imports`);
  cek('impor: kartu struktur gudang', await ev('!!document.querySelector(`#impor-bins form[action$="/imports/bins"]`)'));
  cek('impor: templat struktur gudang', (await ev('fetch("/imports/bins/template").then(r => r.status)')) === 200);
  cek('impor: tanpa error console', errors.length === sebelum, errors.slice(sebelum).join(' | '));
}

// 7. Layar Super Admin di domain pusat (akun dari docs/00-akun-uji.md)
const PUSAT = process.env.WMS_CENTRAL || BASE.replace('://demo.', '://');

// 7a. Landing page produk (30-landing-page §10): hero, tanpa error, tanpa scroll mendatar di HP.
{
  const sebelum = errors.length;
  await go(`${PUSAT}/`);
  const h1 = await ev('document.querySelector("#hero h1")?.textContent?.trim() || ""');
  cek('landing: hero h1', h1.length > 0, h1);
  cek('landing: tanpa error console', errors.length === sebelum, errors.slice(sebelum).join(' | '));
  cek('landing: tanpa service worker', !(await ev('!!(navigator.serviceWorker && navigator.serviceWorker.controller)')));
  const t0 = await ev('document.documentElement.getAttribute("data-bs-theme")');
  await ev('document.getElementById("lpThemeToggle")?.click()');
  cek('landing: toggle tema', (await ev('document.documentElement.getAttribute("data-bs-theme")')) !== t0);
  await ev('document.getElementById("lpThemeToggle")?.click()');
  await viewport(390, 800);
  await go(`${PUSAT}/`);
  // Emulasi mobile melebarkan innerWidth mengikuti konten yang meluber, jadi
  // bandingkan juga dengan lebar viewport yang diminta (390) dan clientWidth.
  const lebar = await ev('({ sw: document.documentElement.scrollWidth, iw: window.innerWidth, cw: document.documentElement.clientWidth })');
  cek('landing HP: tanpa scroll mendatar', lebar.sw <= lebar.iw && lebar.iw <= 390 && lebar.sw <= lebar.cw, JSON.stringify(lebar));
  await viewport(1366, 800);
}

await go(`${PUSAT}/admin/login`);
await ev(`document.getElementById("email").value="superadmin@wms.test"; document.getElementById("password").value="Wms#2026!Admin"; document.querySelector("form").submit();`);
await sleep(2500);
cek('Super Admin: login', (await ev('location.pathname')) === '/admin', await ev('location.href'));
for (const path of ['/admin', '/admin/payments', '/admin/plans', '/admin/security', '/admin/companies/1']) {
  const sebelum = errors.length;
  await go(`${PUSAT}${path}`);
  const judul = await ev('document.querySelector("h1")?.textContent?.trim() || document.title');
  cek(`Super Admin ${path}`, errors.length === sebelum && !/404|403|500|Server Error/.test(judul), errors.slice(sebelum).join(' | ') || judul);
}

console.log(hasil.join('\n'));
console.log(`\nError console total: ${errors.length}`);
errors.slice(0, 10).forEach((e) => console.log('  ' + e));
ws.close(); chrome.kill();
process.exit(0);
