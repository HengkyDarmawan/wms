// E2E layar Pendukung F1 (27-pendukung-f1) di Chrome headless lewat CDP.
// Jalankan SETELAH alur-req-sj.mjs pada data demo yang sama: memakai REQ & SJ
// yang baru dibuat skenario itu (bukti terima 48/2 dan DSC terbuka).
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const BASE = process.env.WMS_BASE || 'http://demo.wms.test:8000';
const PASS = 'Demo#2026!';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const sql = (q) => execFileSync(process.env.MYSQL_BIN || 'mysql', ['-uroot', ...(process.env.DB_PASSWORD ? ['-p' + process.env.DB_PASSWORD] : []), '-N', '-B', process.env.WMS_TENANT_DB || 'wms_tenant_demo', '-e', q]).toString().trim();

const chrome = spawn(CHROME, ['--headless=new', '--remote-debugging-port=9335', '--no-first-run', '--disable-gpu',
  `--user-data-dir=${mkdtempSync(join(tmpdir(), 'wmse2f-'))}`,
  '--host-resolver-rules=MAP demo.wms.test 127.0.0.1, MAP wms.test 127.0.0.1', 'about:blank'], { stdio: 'ignore' });

let ver;
for (let i = 0; i < 50 && !ver; i++) { try { ver = await (await fetch('http://127.0.0.1:9335/json/version')).json(); } catch { await sleep(200); } }
const ws = new WebSocket(ver.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener('open', r));
let seq = 0; const pending = new Map(); const errors = [];
ws.addEventListener('message', (e) => {
  const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
  if (m.method === 'Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text);
  if (m.method === 'Log.entryAdded' && m.params.entry.level === 'error') errors.push(`${m.params.entry.text} ${m.params.entry.url || ''}`);
});
const raw = (method, params = {}, sessionId) => new Promise((r) => { const i = ++seq; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params, sessionId })); });

async function page() {
  const { result: { browserContextId } } = await raw('Target.createBrowserContext');
  const { result: { targetId } } = await raw('Target.createTarget', { url: 'about:blank', browserContextId });
  const { result: { sessionId } } = await raw('Target.attachToTarget', { targetId, flatten: true });
  const send = (m, p) => raw(m, p, sessionId);
  await send('Page.enable'); await send('Runtime.enable'); await send('Log.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
  const ev = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
    if (r.result?.exceptionDetails) throw new Error(r.result.exceptionDetails.exception?.description || 'eval gagal');
    return r.result?.result?.value;
  };
  const go = async (path) => { await send('Page.navigate', { url: BASE + path }); await sleep(1800); };
  const shot = async (name) => { const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }); writeFileSync(name, Buffer.from(r.result.data, 'base64')); return name; };
  // Panggil aksi Livewire pada komponen utama halaman (atau yang memuat selector).
  const wire = async (sets, method, args = [], within = 'main [wire\\:id]') => {
    await ev(`(async () => {
      const el = document.querySelector(${JSON.stringify(within)});
      const c = Livewire.find(el.getAttribute('wire:id'));
      for (const [k, v] of ${JSON.stringify(Object.entries(sets))}) await c.set(k, v, false);
      ${method ? `await c.call(${JSON.stringify(method)}, ...${JSON.stringify(args)});` : 'await c.$refresh();'}
    })()`);
    await sleep(1500);
  };
  const text = () => ev('document.querySelector("main")?.innerText || document.body.innerText');
  const login = async (email, portal = false) => {
    await go(portal ? '/portal/login' : '/login');
    await ev(`document.getElementById('email').value=${JSON.stringify(email)}; document.getElementById('password').value=${JSON.stringify(PASS)}; document.querySelector('form').submit();`);
    await sleep(2500);
    return ev('location.pathname');
  };
  return { ev, go, shot, wire, text, login };
}

const hasil = [];
const catat = (no, langkah, status, bukti) => { hasil.push({ no, langkah, status, bukti }); console.log(`${status.padEnd(9)} ${no} ${langkah} — ${bukti}`); };
const cek = async (no, langkah, fn) => {
  const sebelum = errors.length;
  try { const r = await fn(); const e = errors.slice(sebelum); catat(no, langkah, r.ok ? (e.length ? 'LULUS*' : 'LULUS') : 'GAGAL', r.bukti + (e.length ? ` | console: ${e.join(' ; ')}` : '')); return r.ok; }
  catch (e) { catat(no, langkah, 'GAGAL', String(e.message).split('\n')[0]); return false; }
};
// Ambil sumber lewat fetch di halaman (membawa cookie sesi) → status & content-type.
const ambil = (pg, path) => pg.ev(`fetch(${JSON.stringify(path)}).then(r => [r.status, r.headers.get('content-type') || ''])`);

const reqId = sql("SELECT id FROM material_requests WHERE number LIKE 'REQ/PRJ-001/%' ORDER BY id DESC LIMIT 1");
const sjId = sql(`SELECT s.id FROM shipments s WHERE EXISTS (SELECT 1 FROM shipment_lines sl JOIN pick_task_lines pl ON pl.id=sl.pick_task_line_id JOIN pick_tasks p ON p.id=pl.pick_task_id WHERE sl.shipment_id=s.id AND p.source_type='material_request' AND p.source_id=${reqId || 0}) ORDER BY s.id DESC LIMIT 1`);
const podId = sql(`SELECT id FROM proofs_of_delivery WHERE shipment_id=${sjId || 0}`);

// ---------------------------------------------------------------- P1
const pemohon = await page();
await cek('P1', 'pemohon melihat pengiriman & mengonfirmasi terima (BR-REQ-10)', async () => {
  await pemohon.login('pemohon.prj001@demo.wms.test');
  await pemohon.go(`/requests/${reqId}`);
  const t = await pemohon.text();
  const adaKartu = t.includes('Pengiriman & konfirmasi terima');
  await pemohon.ev(`document.querySelector('form[action$="/receipts/${podId}/confirm"]').submit()`);
  await sleep(2500);
  await pemohon.shot('e2f-1-konfirmasi.png');
  const c = sql(`SELECT COALESCE(confirmation,'-') FROM proofs_of_delivery WHERE id=${podId}`);
  return { ok: adaKartu && c === 'confirmed', bukti: `e2f-1-konfirmasi.png; kartu=${adaKartu}; confirmation=${c}` };
});

await cek('P2', 'pemohon punya notifikasi barang diterima & lonceng', async () => {
  await pemohon.go('/notifications'); await pemohon.shot('e2f-2-notif.png');
  const t = await pemohon.text();
  const n = sql(`SELECT COUNT(*) FROM notifications n JOIN users u ON u.id=n.user_id WHERE u.email='pemohon.prj001@demo.wms.test' AND n.type='delivery.received'`);
  return { ok: Number(n) >= 1 && t.includes('diterima'), bukti: `e2f-2-notif.png; delivery.received=${n}` };
});

// ---------------------------------------------------------------- P3
const kagudang = await page();
await cek('P3', 'kepala gudang: Beranda antrean pekerjaan & notifikasi DSC', async () => {
  await kagudang.login('kagudang.ckg@demo.wms.test');
  await kagudang.go('/'); await kagudang.shot('e2f-3-beranda.png');
  const t = await kagudang.text();
  const n = sql(`SELECT COUNT(*) FROM notifications n JOIN users u ON u.id=n.user_id WHERE u.email='kagudang.ckg@demo.wms.test' AND n.type='discrepancy.opened'`);
  const lonceng = await kagudang.ev(`!!document.querySelector('[aria-label^="Notifikasi"]')`);
  return { ok: /pekerjaan menunggu/i.test(t) && /selisih pengiriman terbuka/i.test(t) && lonceng && Number(n) >= 1, bukti: `e2f-3-beranda.png; discrepancy.opened=${n}; lonceng=${lonceng}` };
});

// ---------------------------------------------------------------- P4
const admin = await page();
await cek('P4', 'admin: laporan saldo stok, mutasi periode, ekspor PDF & Excel', async () => {
  await admin.login('admin@demo.wms.test');
  await admin.go('/reports/saldo-stok'); await admin.shot('e2f-4-saldo.png');
  const t = await admin.text();
  const [sp, tp] = await ambil(admin, '/reports/saldo-stok/pdf');
  const [sx] = await ambil(admin, '/reports/mutasi-periode/export');
  await admin.go('/reports/mutasi-periode');
  const t2 = await admin.text();
  return { ok: t.includes('BAUT-M12') && t.includes('Ekspor PDF') && sp === 200 && tp.includes('pdf') && sx === 200 && t2.includes('Saldo awal'), bukti: `e2f-4-saldo.png; pdf=${sp} ${tp}; excel=${sx}` };
});

// ---------------------------------------------------------------- P5
await cek('P5', 'admin: wizard setup, impor item (templat), preferensi notifikasi', async () => {
  await admin.go('/setup'); await admin.shot('e2f-5-setup.png');
  const t = await admin.text();
  const [st] = await ambil(admin, '/imports/items/template');
  await admin.go('/imports');
  const t2 = await admin.text();
  await admin.go('/notifications/preferences');
  await admin.ev(`document.querySelector('input[name="prefs[asset.overdue][email]"]').checked = true; document.querySelector('form[action$="/notifications/preferences"]').submit();`);
  await sleep(2000);
  const pref = sql(`SELECT p.email FROM notification_preferences p JOIN users u ON u.id=p.user_id WHERE u.email='admin@demo.wms.test' AND p.event_key='asset.overdue'`);
  return { ok: t.includes('Setup awal company') && t.includes('Buat gudang') && st === 200 && t2.includes('Unduh templat') && pref === '1', bukti: `e2f-5-setup.png; templat=${st}; pref email aset=${pref}` };
});

// ---------------------------------------------------------------- P6
await cek('P6', 'PWA: manifest, service worker, halaman offline di subdomain company', async () => {
  const [m, mt] = await ambil(admin, '/manifest.webmanifest');
  const [w] = await ambil(admin, '/sw.js');
  const [o] = await ambil(admin, '/offline.html');
  const tautan = await admin.ev(`!!document.querySelector('link[rel="manifest"]')`);
  return { ok: m === 200 && w === 200 && o === 200 && tautan, bukti: `manifest=${m} ${mt}; sw=${w}; offline=${o}; link manifest=${tautan}` };
});

// ---------------------------------------------------------------- P7
// PRQ manual → catatan pemesanan → GRN vendor merujuk pesanan → terima → selesai → put-away.
const staf = await page();
const pr = await page();
await cek('P7', 'PRQ → pesan → GRN vendor merujuk pesanan → put-away → PRQ dipenuhi', async () => {
  const ckg = sql("SELECT id FROM warehouses WHERE code='CKG'");
  const semen = sql("SELECT id FROM items WHERE code='SEMEN-PCC-50'");
  const vendor = sql("SELECT id FROM vendors WHERE code='BAJA-PRIMA'");
  const tersediaAwal = Number(sql(`SELECT COALESCE(SUM(sb.qty_base),0) FROM stock_balances sb JOIN bins b ON b.id=sb.bin_id WHERE b.warehouse_id=${ckg} AND sb.item_id=${semen} AND sb.stock_status='available' AND b.bin_type='storage'`));

  await staf.login('staf1.ckg@demo.wms.test');
  await staf.go('/purchase-requests/create');
  await staf.wire({ 'form.warehouse_id': String(ckg), 'rows.0.item_id': String(semen), 'rows.0.qty_base': '40' }, 'simpan');
  const prqId = sql('SELECT id FROM purchase_requests ORDER BY id DESC LIMIT 1');
  const st1 = sql(`SELECT status FROM purchase_requests WHERE id=${prqId || 0}`);

  await pr.login('pr@demo.wms.test');
  await pr.go(`/purchase-requests/${prqId}`);
  await pr.wire({}, 'mintaDialog', ['pesan']);
  await pr.wire({ 'order.vendor_id': String(vendor), 'order.external_po_no': 'PO-E2E-1' }, 'catatPesanan');
  await pr.shot('e2f-7-prq.png');
  const st2 = sql(`SELECT status FROM purchase_requests WHERE id=${prqId}`);
  const olId = sql(`SELECT ol.id FROM purchase_request_order_lines ol JOIN purchase_request_orders o ON o.id=ol.purchase_request_order_id WHERE o.purchase_request_id=${prqId} LIMIT 1`);

  await staf.go('/receipts/create');
  await staf.wire({ 'form.receipt_type': 'vendor', 'form.warehouse_id': String(ckg), 'form.vendor_id': String(vendor), 'form.vendor_doc_no': 'SJV-E2E-1' }, 'pakaiPesanan', [Number(olId)]);
  const kedaluwarsa = new Date(Date.now() + 365 * 864e5).toISOString().slice(0, 10);
  await staf.wire({ 'rows.0.lot_no': 'LOT-E2E-1', 'rows.0.expiry_date': kedaluwarsa }, 'simpan');
  const grnId = sql('SELECT id FROM goods_receipts ORDER BY id DESC LIMIT 1');
  const rujuk = sql(`SELECT COALESCE(MAX(purchase_request_order_line_id),0) FROM goods_receipt_lines WHERE goods_receipt_id=${grnId || 0}`);
  await staf.go(`/receipts/${grnId}`);
  await staf.wire({}, 'terima');
  await staf.wire({}, 'selesaikan');
  const putId = sql(`SELECT id FROM putaway_tasks WHERE goods_receipt_id=${grnId} ORDER BY id DESC LIMIT 1`);
  await staf.go(`/putaways/${putId}`);
  await staf.wire({}, 'selesaikan');
  await staf.shot('e2f-7-put.png');

  const st3 = sql(`SELECT status FROM purchase_requests WHERE id=${prqId}`);
  const putSt = sql(`SELECT status FROM putaway_tasks WHERE id=${putId || 0}`);
  const tersedia = Number(sql(`SELECT COALESCE(SUM(sb.qty_base),0) FROM stock_balances sb JOIN bins b ON b.id=sb.bin_id WHERE b.warehouse_id=${ckg} AND sb.item_id=${semen} AND sb.stock_status='available' AND b.bin_type='storage'`));
  return {
    ok: st1 === 'approved' && st2 === 'forwarded' && Number(rujuk) === Number(olId) && st3 === 'fulfilled' && putSt === 'completed' && tersedia === tersediaAwal + 40,
    bukti: `e2f-7-prq.png, e2f-7-put.png; PRQ ${st1} → ${st2} → ${st3}; GRN merujuk baris pesanan ${rujuk}; PUT ${putSt}; semen tersedia CKG ${tersediaAwal} → ${tersedia}`,
  };
});

writeFileSync('e2f-hasil.json', JSON.stringify({ hasil, errors }, null, 2));
console.log(`\nerror console total: ${errors.length}`);
ws.close(); chrome.kill(); process.exit(0);
