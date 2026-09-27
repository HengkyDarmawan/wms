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
  const go = async (path) => { await send('Page.navigate', { url: BASE + path }); await siap(); };
  const shot = async (name) => { try { const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }); if (r.result?.data) writeFileSync(name, Buffer.from(r.result.data, 'base64')); } catch { /* halaman sedang berpindah */ } return name; };
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
  // POST + redirect bisa > 2 detik saat server dev sibuk: tunggu barisnya muncul, bukan tidur tetap.
  let pref = '';
  for (let i = 0; i < 40 && pref !== '1'; i++) { await sleep(500); pref = sql(`SELECT p.email FROM notification_preferences p JOIN users u ON u.id=p.user_id WHERE u.email='admin@demo.wms.test' AND p.event_key='asset.overdue'`); }
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

// ---------------------------------------------------------------- P8
// Purchasing inti Fase 1b (purchasing/02): PRQ → PO dari layar (harga bawaan
// vendor) → approval nilai ≥ Rp 50 juta oleh Manajemen → catatan pemesanan →
// GRN merujuk → PO selesai; cetak PO.
const direktur = await page();
await cek('P8', 'PRQ → PO (harga vendor) → approval nilai Manajemen → GRN → PO selesai, cetak PO', async () => {
  const ckg = sql("SELECT id FROM warehouses WHERE code='CKG'");
  const baut = sql("SELECT id FROM items WHERE code='BAUT-M12'");
  const vendor = sql("SELECT id FROM vendors WHERE code='BESI-JAYA'");

  await staf.go('/purchase-requests/create');
  await staf.wire({ 'form.warehouse_id': String(ckg), 'rows.0.item_id': String(baut), 'rows.0.qty_base': '40000' }, 'simpan');
  const prqId = sql('SELECT id FROM purchase_requests ORDER BY id DESC LIMIT 1');

  await pr.go(`/purchase-orders/create?prq=${prqId}`);
  await pr.wire({ 'form.vendor_id': String(vendor) }, 'simpanDanAjukan');
  const poId = sql('SELECT id FROM purchase_orders ORDER BY id DESC LIMIT 1');
  const [nilai, st1] = sql(`SELECT total_amount, status FROM purchase_orders WHERE id=${poId || 0}`).split('\t');
  await pr.go(`/purchase-orders/${poId}`); await pr.shot('e2f-8-po.png');

  await direktur.login('manajemen@demo.wms.test');
  await direktur.go(`/purchase-orders/${poId}`);
  await direktur.wire({}, 'setujui');
  const st2 = sql(`SELECT status FROM purchase_orders WHERE id=${poId}`);
  const olId = sql(`SELECT ol.id FROM purchase_request_order_lines ol JOIN purchase_request_orders o ON o.id=ol.purchase_request_order_id WHERE o.purchase_order_id=${poId} LIMIT 1`);

  await staf.go('/receipts/create');
  await staf.wire({ 'form.receipt_type': 'vendor', 'form.warehouse_id': String(ckg), 'form.vendor_id': String(vendor), 'form.vendor_doc_no': 'SJV-E2E-PO' }, 'pakaiPesanan', [Number(olId)]);
  await staf.wire({}, 'simpan');
  const grnId = sql('SELECT id FROM goods_receipts ORDER BY id DESC LIMIT 1');
  await staf.go(`/receipts/${grnId}`);
  await staf.wire({}, 'terima');

  const st3 = sql(`SELECT status FROM purchase_orders WHERE id=${poId}`);
  const prqSt = sql(`SELECT status FROM purchase_requests WHERE id=${prqId}`);
  const [cp, ct] = await ambil(pr, `/print/purchase-order/${poId}`);
  return {
    ok: Number(nilai) === 60000000 && st1 === 'pending_approval' && st2 === 'approved' && Number(olId) > 0 && st3 === 'completed' && prqSt === 'fulfilled' && cp === 200 && ct.includes('pdf'),
    bukti: `e2f-8-po.png; nilai ${nilai}; PO ${st1} → ${st2} → ${st3}; PRQ ${prqSt}; cetak ${cp} ${ct}`,
  };
});

// ---------------------------------------------------------------- P9
// Konversi Ganti kemasan (24-konversi-waste §6, A-285): barang biasa tanpa tanda Bisa
// dipotong boleh dikonversi; DEMO tanpa saklar per potong → tombol Potong tidak ada (A-284).
await cek('P9', 'Konversi Ganti kemasan dari layar: BAUT 10 pcs tanpa mode Potong, selesai', async () => {
  const ckg = sql("SELECT id FROM warehouses WHERE code='CKG'");
  const prjInt = sql("SELECT id FROM projects WHERE code='PRJ-INT'");
  const baut = sql("SELECT id FROM items WHERE code='BAUT-M12'");
  const bin = sql(`SELECT sb.bin_id FROM stock_balances sb JOIN bins b ON b.id=sb.bin_id WHERE sb.item_id=${baut} AND b.warehouse_id=${ckg} AND sb.stock_status='available' AND sb.qty_base>=10 ORDER BY sb.bin_id LIMIT 1`);
  const kunci = `${bin}_${baut}_0_0`;

  await staf.go('/conversions/create');
  const awal = await staf.ev('document.querySelector("main")?.innerHTML || ""');
  // Dua permintaan: hook updated gudang mengosongkan input, jadi jumlah & hasil dikirim setelahnya.
  await staf.wire({ 'form.project_id': String(prjInt), 'form.warehouse_id': String(ckg), 'form.conversion_type': 'repack' });
  await staf.wire({ [`qty.${kunci}`]: '10', 'hasil.0.item_id': String(baut), 'hasil.0.qty': '10' });
  await staf.shot('e2f-9-cnv.png');
  await staf.wire({}, 'simpanDanSelesaikan');
  const cnvId = sql('SELECT id FROM conversions ORDER BY id DESC LIMIT 1');
  const [st, jenis, tIn, tOut] = sql(`SELECT status, conversion_type, total_input, total_output FROM conversions WHERE id=${cnvId || 0}`).split('	');
  return {
    ok: !awal.includes('cnv-jenis-cut') && awal.includes('cnv-jenis-repack') && st === 'completed' && jenis === 'repack' && Number(tIn) === 10 && Number(tOut) === 10,
    bukti: `e2f-9-cnv.png; tombol Potong ${awal.includes('cnv-jenis-cut') ? 'ADA' : 'tidak ada'}; CNV ${jenis} ${st}; input ${tIn} → output ${tOut}`,
  };
});

// ---------------------------------------------------------------- P10
// GRN vendor Baik/Rusak/Kurang (A-287) dalam kemasan (A-291): 2 DUS dikirim vendor,
// 1 DUS rusak → Karantina berkondisi Rusak, kemasan DUS = 12 diingat untuk item,
// lalu Retur ke vendor dari barang rusak tanpa QC (A-290); PRQ tetap sebagian.
await cek('P10', 'GRN 2 DUS (1 rusak) → Karantina Rusak → RTV → PRQ masih sebagian', async () => {
  const ckg = sql("SELECT id FROM warehouses WHERE code='CKG'");
  const baut = sql("SELECT id FROM items WHERE code='BAUT-M12'");
  const vendor = sql("SELECT id FROM vendors WHERE code='BESI-JAYA'");
  const dus = sql("SELECT id FROM uoms WHERE code='DUS'");
  const alasan = sql("SELECT code FROM reason_codes WHERE context='damage' AND code='VENDOR'") || 'TRANSPORT';

  await staf.go('/purchase-requests/create');
  await staf.wire({ 'form.warehouse_id': String(ckg), 'rows.0.item_id': String(baut), 'rows.0.qty_base': '24' }, 'simpan');
  const prqId = sql('SELECT id FROM purchase_requests ORDER BY id DESC LIMIT 1');
  await pr.go(`/purchase-requests/${prqId}`);
  await pr.wire({}, 'mintaDialog', ['pesan']);
  await pr.wire({ 'order.vendor_id': String(vendor), 'order.external_po_no': 'PO-E2E-10' }, 'catatPesanan');
  const olId = sql(`SELECT ol.id FROM purchase_request_order_lines ol JOIN purchase_request_orders o ON o.id=ol.purchase_request_order_id WHERE o.purchase_request_id=${prqId} LIMIT 1`);

  await staf.go('/receipts/create');
  await staf.wire({ 'form.receipt_type': 'vendor', 'form.warehouse_id': String(ckg), 'form.vendor_id': String(vendor), 'form.vendor_doc_no': 'SJV-E2E-10' }, 'pakaiPesanan', [Number(olId)]);
  await staf.wire({ 'rows.0.uom': 'lain', 'rows.0.uom_lain': String(dus), 'rows.0.uom_factor': '12', 'rows.0.ingat': true });
  await staf.wire({ 'rows.0.vendor': '2', 'rows.0.damaged': '1', 'rows.0.damage_reason': alasan });
  const hasil = await staf.text();
  await staf.shot('e2f-10-grn.png');
  await staf.wire({}, 'simpan');
  const grnId = sql('SELECT id FROM goods_receipts ORDER BY id DESC LIMIT 1');
  await staf.go(`/receipts/${grnId}`);
  await staf.wire({}, 'terima');
  const [baik, rusak, kurang, diketik] = sql(`SELECT qty_received, qty_damaged, qty_short, qty_input FROM goods_receipt_lines WHERE goods_receipt_id=${grnId || 0} LIMIT 1`).split('\t');
  const rusakKarantina = Number(sql(`SELECT COALESCE(SUM(sb.qty_base),0) FROM stock_balances sb JOIN bins b ON b.id=sb.bin_id WHERE b.warehouse_id=${ckg} AND b.bin_type='quarantine' AND sb.item_id=${baut} AND sb.stock_status='damaged'`));
  const kemasan = sql(`SELECT qty_base FROM item_uom_conversions WHERE item_id=${baut} AND uom_id=${dus} AND is_active=1`);

  await staf.go(`/vendor-returns/create?receipt=${grnId}`);
  await staf.wire({}, 'simpan');
  const [rtvQty, rtvRusak] = sql(`SELECT vrl.qty_base, vrl.is_receipt_damage FROM vendor_return_lines vrl JOIN vendor_returns vr ON vr.id=vrl.vendor_return_id WHERE vr.goods_receipt_id=${grnId || 0} LIMIT 1`).split('\t');
  await staf.shot('e2f-10-rtv.png');
  const prqSt = sql(`SELECT status FROM purchase_requests WHERE id=${prqId}`);
  return {
    ok: hasil.includes('2 DUS = 24') && Number(baik) === 12 && Number(rusak) === 12 && Number(kurang) === 0 && Number(diketik) === 2
      && rusakKarantina >= 12 && Number(kemasan) === 12 && Number(rtvQty) === 12 && Number(rtvRusak) === 1 && prqSt === 'partially_fulfilled',
    bukti: `e2f-10-grn.png, e2f-10-rtv.png; baik ${baik} rusak ${rusak} kurang ${kurang} (diketik ${diketik} DUS); Karantina rusak ${rusakKarantina}; kemasan DUS=${kemasan}; RTV ${rtvQty} (rusak=${rtvRusak}); PRQ ${prqSt}`,
  };
});

// ---------------------------------------------------------------- P11
// Label kemasan (A-296–A-302): GRN vendor 24 baut diselesaikan dengan 2 dus × 12 →
// label induk → cetak PDF → PCK REQ 12 baut ditolak tanpa pindai (BR-LBL-04), pindai
// label induk → selesai → Telusuri label menampilkan GRN dan PCK.
await cek('P11', 'GRN → label induk → cetak → PCK wajib pindai label → Telusuri label', async () => {
  const ckg = sql("SELECT id FROM warehouses WHERE code='CKG'");
  const baut = sql("SELECT id FROM items WHERE code='BAUT-M12'");
  const vendor = sql("SELECT id FROM vendors WHERE code='BESI-JAYA'");
  const prj = sql("SELECT id FROM projects WHERE code='PRJ-001'");

  // 1. GRN vendor → terima → Selesaikan dengan rencana label.
  await staf.go('/receipts/create');
  await staf.wire({ 'form.receipt_type': 'vendor', 'form.warehouse_id': String(ckg), 'form.vendor_id': String(vendor), 'form.vendor_doc_no': 'SJV-E2E-11' });
  await staf.wire({ 'rows.0.item_id': String(baut), 'rows.0.qty': '24' }, 'simpan');
  const grnId = sql('SELECT id FROM goods_receipts ORDER BY id DESC LIMIT 1');
  const grnNo = sql(`SELECT number FROM goods_receipts WHERE id=${grnId || 0}`);
  const lineId = sql(`SELECT id FROM goods_receipt_lines WHERE goods_receipt_id=${grnId || 0} LIMIT 1`);
  await staf.go(`/receipts/${grnId}`);
  await staf.wire({}, 'terima');
  await staf.wire({}, 'mintaSelesai');
  await staf.wire({ [`labelRencana.${lineId}.packages`]: '2', [`labelRencana.${lineId}.per_package`]: '12' }, 'selesaikan');
  await staf.go(`/receipts/${grnId}`); await staf.shot('e2f-11-label.png');
  const labels = sql(`SELECT GROUP_CONCAT(id ORDER BY id), GROUP_CONCAT(code ORDER BY id) FROM package_labels WHERE goods_receipt_id=${grnId || 0} AND parent_id IS NULL`).split('\t');
  const ids = labels[0] || ''; const kode = (labels[1] || '').split(',')[0];

  // 2. Cetak label induk (PDF).
  const [cetakSt, cetakCt] = await ambil(staf, `/labels/print?type=label_package&ids=${ids}`);

  // 3. REQ 12 baut → tinjau/setujui → PCK: selesaikan tanpa pindai ditolak, lalu pindai label induk.
  await pemohon.go('/requests/create');
  const lines = await pemohon.ev(`Livewire.find(document.querySelector('main [wire\\\\:id]').getAttribute('wire:id')).get('lines')`);
  await pemohon.wire({ 'form.project_id': prj, 'lines.0': { ...(lines[0] || {}), item_id: baut, qty_base: '12' } }, 'simpanDanAjukan');
  const reqId11 = sql('SELECT id FROM material_requests ORDER BY id DESC LIMIT 1');
  await kagudang.go(`/requests/${reqId11}`);
  if (sql(`SELECT status FROM material_requests WHERE id=${reqId11}`) === 'under_review') {
    const rl = sql(`SELECT id FROM material_request_lines WHERE material_request_id=${reqId11} LIMIT 1`);
    await kagudang.wire({}, 'simpanSumber', [Number(rl), String(ckg), 'stock', null]);
    await kagudang.wire({}, 'kirimKeApproval');
  }
  await kagudang.go(`/requests/${reqId11}`);
  await kagudang.wire({}, 'setujui');
  await kagudang.go('/picks');
  await kagudang.wire({}, 'buatDariReq', [Number(reqId11)]);
  const pckId = sql(`SELECT id FROM pick_tasks WHERE source_type='material_request' AND source_id=${reqId11 || 0} ORDER BY id DESC LIMIT 1`);
  const pckNo = sql(`SELECT number FROM pick_tasks WHERE id=${pckId || 0}`);
  await kagudang.go(`/picks/${pckId}`);
  await kagudang.wire({}, 'mulai');
  const pl = sql(`SELECT id FROM pick_task_lines WHERE pick_task_id=${pckId || 0}`).split('\n').filter(Boolean);
  for (const l of pl) await kagudang.wire({}, 'catat', [Number(l)]);
  await kagudang.wire({}, 'selesaikan');
  const ditolak = await kagudang.ev(`Livewire.find(document.querySelector('main [wire\\\\:id]').getAttribute('wire:id')).get('ruleCode')`);
  for (let i = 0; i < pl.length; i++) {
    await kagudang.wire({ kodePindai: kode }, 'pindai');
    await kagudang.wire({}, 'simpanIsiLabel');
  }
  await kagudang.wire({}, 'selesaikan');
  await kagudang.shot('e2f-11-pck.png');
  const pckSt = sql(`SELECT status FROM pick_tasks WHERE id=${pckId || 0}`);
  const labelSt = sql(`SELECT status FROM package_labels WHERE code='${kode}'`);

  // 4. Telusuri label.
  await staf.go(`/labels/trace?code=${encodeURIComponent(kode)}`); await staf.shot('e2f-11-trace.png');
  const jejak = await staf.text();
  return {
    ok: ids.split(',').length === 2 && cetakSt === 200 && /pdf/.test(cetakCt) && ditolak === 'BR-LBL-04' && pckSt === 'completed'
      && labelSt === 'issued' && jejak.includes(grnNo) && jejak.includes(pckNo),
    bukti: `e2f-11-label.png, e2f-11-pck.png, e2f-11-trace.png; ${grnNo} label ${labels[1]}; cetak ${cetakSt} ${cetakCt}; PCK ${pckNo} tanpa pindai=${ditolak}, akhir ${pckSt}; label ${kode} ${labelSt}`,
  };
});

writeFileSync('e2f-hasil.json', JSON.stringify({ hasil, errors }, null, 2));
console.log(`\nerror console total: ${errors.length}`);
ws.close(); chrome.kill(); process.exit(0);
