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
const go = async (url) => { await send('Page.navigate', { url }); await sleep(1500); };
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
await sleep(2500);
cek('login berhasil', (await ev('location.pathname')) === '/', await ev('location.href'));

// 3. Sidebar scroll
const s = await ev(`(() => { const n = document.getElementById('nxSidebarNav');
  const w = n.querySelector('.simplebar-content-wrapper') || n;
  const before = { sh: w.scrollHeight, ch: w.clientHeight };
  w.scrollTop = 200; return { simplebar: !!n.querySelector('.simplebar-content-wrapper'), ...before, top: w.scrollTop }; })()`);
cek('sidebar: SimpleBar aktif', s.simplebar);
cek('sidebar: bisa di-scroll', s.sh > s.ch && s.top > 0, JSON.stringify(s));

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

// 6. Semua tautan menu tanpa error
await viewport(1366, 800);
const links = await ev('[...new Set([...document.querySelectorAll("#nxSidebar a.nx-menu-link[href]")].map(a => a.href).filter(h => h.startsWith(location.origin)))]');
for (const url of links) {
  const sebelum = errors.length;
  await go(url);
  const st = await ev('document.title');
  cek(`halaman ${new URL(url).pathname}`, errors.length === sebelum, errors.slice(sebelum).join(' | ') || st);
}

// 7. Layar Super Admin di domain pusat (akun dari docs/00-akun-uji.md)
const PUSAT = process.env.WMS_CENTRAL || BASE.replace('://demo.', '://');
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
