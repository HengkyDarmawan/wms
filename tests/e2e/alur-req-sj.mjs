// E2E skenario 00-setup-lokal.md §5 di Chrome headless lewat CDP.
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const BASE = process.env.WMS_BASE || 'http://demo.wms.test:8000';
const PASS = 'Demo#2026!';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const sql = (q) => execFileSync(process.env.MYSQL_BIN || 'mysql', ['-uroot', ...(process.env.DB_PASSWORD ? ['-p' + process.env.DB_PASSWORD] : []), '-N', '-B', process.env.WMS_TENANT_DB || 'wms_tenant_demo', '-e', q]).toString().trim();

const chrome = spawn(CHROME, ['--headless=new', '--remote-debugging-port=9334', '--no-first-run', '--disable-gpu',
  `--user-data-dir=${mkdtempSync(join(tmpdir(), 'wmse2e-'))}`,
  '--host-resolver-rules=MAP demo.wms.test 127.0.0.1, MAP wms.test 127.0.0.1', 'about:blank'], { stdio: 'ignore' });

let ver;
for (let i = 0; i < 50 && !ver; i++) { try { ver = await (await fetch('http://127.0.0.1:9334/json/version')).json(); } catch { await sleep(200); } }
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
const angka = (s) => Number(String(s).replace(/\./g, '').replace(',', '.'));

// Saldo awal dibaca dari data demo (StockDemoSeeder sudah memuat satu alur contoh REQ → SJ).
const saldoBin = (kode) => Number(sql(`SELECT COALESCE(SUM(sb.qty_base),0) FROM stock_balances sb JOIN bins b ON b.id=sb.bin_id JOIN items i ON i.id=sb.item_id WHERE i.code='BAUT-M12' AND b.code ${kode}`));
const rupa = (n) => n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// Layar Saldo stok menampilkan per gudang (termasuk Dalam Perjalanan milik CKG, BR-STK-13).
const bautCkgAwal = saldoBin("LIKE 'CKG-%'");
const cadangan = () => Number(sql("SELECT COALESCE(SUM(r.qty_base),0) FROM stock_reservations r JOIN items i ON i.id=r.item_id JOIN warehouses w ON w.id=r.warehouse_id WHERE i.code='BAUT-M12' AND w.code='CKG' AND r.status='active'"));
const transitAwal = saldoBin("= 'CKG-TRANSIT'");

// ---------------------------------------------------------------- 1
const admin = await page();
await cek(1, 'admin login & buka Stok', async () => {
  const p = await admin.login('admin@demo.wms.test');
  await admin.go('/stock'); await admin.shot('e2e-1-stok.png');
  const t = await admin.text();
  const ok = p === '/' && ['BAUT-M12', 'SEMEN-PCC-50', 'PIPA-PVC-4', 'GENSET-5KVA'].every((k) => t.includes(k)) && t.includes(rupa(bautCkgAwal));
  return { ok, bukti: `e2e-1-stok.png; 4 item tampil, BAUT CKG ${rupa(bautCkgAwal)}` };
});

// ---------------------------------------------------------------- 2
const pemohon = await page();
let reqId, reqNo;
await cek(2, 'pemohon buat REQ PRJ-001, 50 BAUT-M12, ajukan', async () => {
  await pemohon.login('pemohon.prj001@demo.wms.test');
  await pemohon.go('/requests/create');
  const prj = sql("SELECT id FROM projects WHERE code='PRJ-001'");
  const item = sql("SELECT id FROM items WHERE code='BAUT-M12'");
  const lines = await pemohon.ev(`Livewire.find(document.querySelector('main [wire\\\\:id]').getAttribute('wire:id')).get('lines')`);
  const baris = { ...(lines[0] || {}), item_id: item, qty_base: '50' };
  await pemohon.wire({ 'form.project_id': prj, 'lines.0': baris }, 'simpanDanAjukan');
  await sleep(1000);
  await pemohon.shot('e2e-2-req.png');
  reqId = sql('SELECT id FROM material_requests ORDER BY id DESC LIMIT 1');
  const [no, st] = sql(`SELECT number, status FROM material_requests WHERE id=${reqId || 0}`).split('\t');
  reqNo = no;
  return { ok: !!reqId && st !== 'draft', bukti: `e2e-2-req.png; ${no} status=${st}; url=${await pemohon.ev('location.pathname')}` };
});

// ---------------------------------------------------------------- 2b (tinjau: sumber gudang)
const kagudang = await page();
await kagudang.login('kagudang.ckg@demo.wms.test');
const st2 = () => sql(`SELECT status FROM material_requests WHERE id=${reqId}`);
if (reqId && st2() === 'under_review') {
  await cek('2b', 'kepala gudang tinjau: sumber CKG, stok, kirim ke approval', async () => {
    await kagudang.go(`/requests/${reqId}`);
    const lineId = sql(`SELECT id FROM material_request_lines WHERE material_request_id=${reqId} LIMIT 1`);
    const ckg = sql("SELECT id FROM warehouses WHERE code='CKG'");
    await kagudang.wire({}, 'simpanSumber', [Number(lineId), String(ckg), 'stock', null]);
    await kagudang.wire({}, 'kirimKeApproval');
    await kagudang.shot('e2e-2b-tinjau.png');
    const s = st2();
    return { ok: s === 'pending_approval', bukti: `e2e-2b-tinjau.png; status=${s}; ${(await kagudang.text()).match(/(BR-[A-Z]+-\d+[^\n]*)/)?.[1] || ''}` };
  });
}

// ---------------------------------------------------------------- 3
await cek(3, 'kepala gudang CKG setujui REQ (aturan approval demo, 20-approval); reservasi lunak', async () => {
  await kagudang.go(`/requests/${reqId}`);
  await kagudang.wire({}, 'setujui');
  await kagudang.shot('e2e-3-setuju.png');
  const s = st2();
  const res = sql(`SELECT COUNT(*) FROM stock_reservations WHERE document_id=${reqId} AND status='active'`);
  await admin.go('/stock'); await admin.shot('e2e-3-stok.png');
  const t = await admin.text();
  const harap = rupa(bautCkgAwal - cadangan());
  return { ok: s === 'approved' && Number(res) >= 1 && t.includes(harap), bukti: `e2e-3-setuju.png, e2e-3-stok.png; status=${s}; reservasi aktif=${res}; tersedia CKG ${harap} tampil=${t.includes(harap)}` };
});

// ---------------------------------------------------------------- 4
let pckId;
await cek(4, 'kepala gudang buat & kerjakan PCK', async () => {
  await kagudang.go('/picks');
  await kagudang.wire({}, 'buatDariReq', [Number(reqId)]);
  pckId = sql('SELECT id FROM pick_tasks ORDER BY id DESC LIMIT 1');
  await kagudang.go(`/picks/${pckId}`);
  await kagudang.wire({}, 'mulai');
  const baris = sql(`SELECT id FROM pick_task_lines WHERE pick_task_id=${pckId}`).split('\n');
  for (const l of baris) {
    const isian = await kagudang.ev(`Livewire.find(document.querySelector('main [wire\\\\:id]').getAttribute('wire:id')).get('isian.${l}')`);
    await kagudang.wire({ [`isian.${l}.qty_picked`]: String(isian?.qty_planned ?? isian?.qty_picked ?? 50) }, 'catat', [Number(l)]);
  }
  await kagudang.wire({}, 'selesaikan');
  await kagudang.shot('e2e-4-pck.png');
  const [no, s] = sql(`SELECT number, status FROM pick_tasks WHERE id=${pckId}`).split('\t');
  const stg = sql(`SELECT COALESCE(SUM(sb.qty_base),0) FROM stock_balances sb JOIN bins b ON b.id=sb.bin_id WHERE b.code='CKG-STG'`);
  return { ok: s === 'completed' && Number(stg) === 50, bukti: `e2e-4-pck.png; ${no} status=${s}; saldo CKG-STG=${stg}` };
});

// ---------------------------------------------------------------- 5
let sjId;
await cek(5, 'kepala gudang susun & berangkatkan SJ (kendaraan B 9001 XX)', async () => {
  await kagudang.go('/shipments/create');
  const ckg = sql("SELECT id FROM warehouses WHERE code='CKG'");
  await kagudang.wire({ 'form.warehouse_id': String(ckg) }, null);
  const veh = sql("SELECT id FROM vehicles WHERE plate_no='B 9001 XX'");
  const drv = sql("SELECT id FROM users WHERE email='driver1@demo.wms.test'");
  const prj = sql("SELECT id FROM projects WHERE code='PRJ-001'");
  const metode = await kagudang.ev(`[...document.querySelectorAll('select[wire\\\\:model\\\\.live="form.shipment_method"] option')].map(o=>o.value).filter(Boolean)`);
  const tujuan = await kagudang.ev(`[...document.querySelectorAll('select[wire\\\\:model\\\\.live="form.destination_type"] option')].map(o=>o.value).filter(Boolean)`);
  await kagudang.wire({
    pickTaskIds: [String(pckId)],
    'form.destination_type': tujuan.includes('project') ? 'project' : tujuan[0],
    'form.destination_project_id': String(prj),
    'form.shipment_method': metode.find((m) => /own|vehicle|internal/.test(m)) || metode[0],
    'form.vehicle_id': String(veh), 'form.driver_id': String(drv),
  }, 'simpan');
  sjId = sql('SELECT id FROM shipments ORDER BY id DESC LIMIT 1');
  if (!sjId) { await kagudang.shot('e2e-5-sj-gagal.png'); return { ok: false, bukti: `e2e-5-sj-gagal.png; ${(await kagudang.text()).split('\n').filter((x) => /wajib|tidak|harus|BR-/.test(x)).slice(0, 3).join(' / ')}` }; }
  await kagudang.go(`/shipments/${sjId}`);
  await kagudang.wire({}, 'berangkatkan');
  await kagudang.shot('e2e-5-sj.png');
  const [no, s] = sql(`SELECT number, status FROM shipments WHERE id=${sjId}`).split('\t');
  const trn = sql(`SELECT COALESCE(SUM(sb.qty_base),0) FROM stock_balances sb JOIN bins b ON b.id=sb.bin_id WHERE b.code='CKG-TRANSIT'`);
  return { ok: s === 'shipped' && Number(trn) === transitAwal + 50, bukti: `e2e-5-sj.png; ${no} status=${s}; metode=${metode.join('/')}; saldo CKG-TRANSIT=${trn}` };
});

// ---------------------------------------------------------------- 5b
// Halaman penerima bertoken (A-231): kepala gudang menerbitkan tautan + OTP (tampil sekali),
// halaman dibuka tanpa login, OTP salah ditolak, OTP benar membuka formulir. Formulir tidak
// dikirim supaya langkah 6 (driver) tetap berjalan; token yang tak terpakai tidak mengganggu.
await cek('5b', 'tautan penerima bertoken: OTP salah ditolak, OTP benar membuka formulir', async () => {
  await kagudang.go(`/shipments/${sjId}`);
  await kagudang.wire({}, 'mintaDialog', ['tautan']);
  await kagudang.wire({ 'form.phone': '0812' }, 'terbitkanTautan');
  const teks = await kagudang.text();
  const otp = (teks.match(/Kode OTP untuk penerima:\s*(\d{6})/) || [])[1];
  const tautan = await kagudang.ev(`document.querySelector('main a[href*="/terima/"]')?.getAttribute('href') || ''`);
  await kagudang.shot('e2e-5b-tautan.png');
  if (!otp || !tautan) return { ok: false, bukti: `e2e-5b-tautan.png; otp=${otp}; tautan=${tautan}` };
  const path = new URL(tautan).pathname;
  const penerima = await page();
  const tunggu = async (expr) => { for (let i = 0; i < 60; i++) { try { if (await penerima.ev(expr)) return true; } catch {} await sleep(250); } return false; };
  await penerima.go(path);
  const adaOtp = await tunggu('!!document.getElementById("otp")');
  await penerima.ev(`document.getElementById('otp').value='000000'; document.querySelector('form').submit();`);
  const tolak = await tunggu('/tidak cocok/i.test(document.body.innerText)');
  await penerima.ev(`document.getElementById('otp').value=${JSON.stringify(otp)}; document.querySelector('form').submit();`);
  const adaForm = await tunggu('!!document.getElementById("received_by_name") && !!document.querySelector("[data-signature] canvas")');
  await penerima.shot('e2e-5b-form.png');
  const percobaan = sql(`SELECT attempts FROM delivery_tokens WHERE shipment_id=${sjId} ORDER BY id DESC LIMIT 1`);
  return { ok: adaOtp && tolak && adaForm && Number(percobaan) === 1, bukti: `e2e-5b-tautan.png, e2e-5b-form.png; otp form=${adaOtp}; salah ditolak=${tolak}; form terbuka=${adaForm}; percobaan=${percobaan}` };
});

// ---------------------------------------------------------------- 6
const driver = await page();
await cek(6, 'driver isi bukti terima: baik 48, kurang 2', async () => {
  await driver.login('driver1@demo.wms.test');
  await driver.go(`/shipments/${sjId}`);
  await driver.wire({}, 'mintaDialog', ['terima']);
  const l = sql(`SELECT id FROM shipment_lines WHERE shipment_id=${sjId} LIMIT 1`);
  await driver.wire({ 'form.received_by_name': 'Indra (site)', [`terima.${l}.qty_good`]: '48', [`terima.${l}.qty_damaged`]: '0', [`terima.${l}.qty_missing`]: '2' }, 'simpanBuktiTerima');
  await driver.shot('e2e-6-terima.png');
  const s = sql(`SELECT status FROM shipments WHERE id=${sjId}`);
  const dsc = sql(`SELECT CONCAT(number,' ',status) FROM delivery_discrepancies WHERE shipment_id=${sjId}`);
  await kagudang.go('/discrepancies'); await kagudang.shot('e2e-6-dsc.png');
  const t = await kagudang.text();
  return { ok: s === 'partially_delivered' && !!dsc && t.includes(dsc.split(' ')[0]), bukti: `e2e-6-terima.png, e2e-6-dsc.png; SJ status=${s}; DSC=${dsc || '-'}${(await driver.text()).match(/BR-[A-Z]+-\d+[^\n]*/)?.[0] ? ' | ' + (await driver.text()).match(/BR-[A-Z]+-\d+[^\n]*/)[0] : ''}` };
});

// ---------------------------------------------------------------- 7 & 8
const k1 = await page();
await cek(7, 'klien1 portal: hanya PRJ-001', async () => {
  const p = await k1.login('klien1@klien-satu.test', true);
  await k1.go('/portal/requests'); await k1.shot('e2e-7-portal.png');
  const t = await k1.text();
  return { ok: p.startsWith('/portal') && t.includes(reqNo) && !t.includes('PRJ-002'), bukti: `e2e-7-portal.png; mendarat di ${p}; ${reqNo} tampil=${t.includes(reqNo)}` };
});
const k2 = await page();
await cek(8, 'klien2 portal: kosong', async () => {
  const p = await k2.login('klien2@klien-dua.test', true);
  await k2.go('/portal/requests'); await k2.shot('e2e-8-portal.png');
  const t = await k2.text();
  return { ok: p.startsWith('/portal') && !t.includes(reqNo), bukti: `e2e-8-portal.png; mendarat di ${p}; ${reqNo} tidak tampil=${!t.includes(reqNo)}` };
});

writeFileSync('e2e-hasil.json', JSON.stringify({ hasil, errors }, null, 2));
console.log(`\nerror console total: ${errors.length}`);
ws.close(); chrome.kill(); process.exit(0);
