# -*- coding: utf-8 -*-
"""
Halaman diagram aktivitas per peran (activity diagram per user).

Sumber data: FLOWS di docs/diagram/_generate.py (tidak diketik ulang). Alur di sana disusun
per proses dengan lane; di sini data yang sama diputar per peran — "sebagai Kepala Gudang,
apa saja yang saya kerjakan" — untuk panduan uji manual, demo, dan bahan presentasi.

Keluaran: docs/00-audit/alur-per-peran.html  (jangan diedit manual)

Jalankan:  py -3 docs/diagram/_generate_peran_html.py   (dari root proyek)

Pemetaan lane -> peran memakai LANE_ROLES di bawah; itu tafsir substantif, dicatat sebagai
asumsi A-226 (Perlu validasi) di docs/wms/04-keputusan-dan-asumsi.md. Nama & kode peran
mengikuti docs/wms/03-glosarium.md dan Blueprint §4.2.

Catatan: GROUPS disalin dari _generate_alur_html.py, tidak diimpor, karena modul itu menulis
berkas keluarannya saat diimpor.
"""
import os, sys, io, re, html

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import _generate as g  # noqa: E402

AUDIT = os.path.join(os.path.dirname(HERE), "00-audit")
OUT = os.path.join(AUDIT, "alur-per-peran.html")
VENDOR_MERMAID = os.path.join(AUDIT, "vendor", "mermaid.min.js")
MERMAID_CDN = "https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js"

# Warna per kelompok pelaku: isi pastel + teks gelap agar terbaca di tema terang & gelap.
COLORS = {
    "sys":   ("#e4e7ec", "#8a94a3"),
    "kagud": ("#cdeee6", "#2f8f7a"),
    "staf":  ("#d6e6fb", "#3b73c4"),
    "apr":   ("#fbe7c2", "#b7801b"),
    "pemoh": ("#e6dcfb", "#7654c4"),
    "klien": ("#d8f0f8", "#2f7f96"),
    "drv":   ("#fcdcc8", "#c4632a"),
    "pr":    ("#dcf1c8", "#5c9a2a"),
    "admin": ("#f9d3dc", "#b8445f"),
    "manaj": ("#d9dcf7", "#4a52a8"),
    "audit": ("#cfe8d4", "#3f8a54"),
    "super": ("#f1dff0", "#8a4a8a"),
}

# ---------------------------------------------------------------------------
# PERAN (Blueprint §4.2 + glosarium §peran)
# ---------------------------------------------------------------------------
ROLES = [
    dict(key="kagud", name="Kepala Gudang", code="warehouse_head", color="kagud",
         kanal="Web · PWA · WhatsApp [F2]",
         tugas="Menyetujui permintaan, transfer, penyesuaian; mengawasi gudang yang ditugaskan; "
               "menyelesaikan selisih pengiriman; meninjau permintaan tanpa gudang sumber."),
    dict(key="staf", name="Staf Gudang", code="warehouse_staff", color="staf",
         kanal="PWA (utama) · Web",
         tugas="Penerimaan, QC, put-away, picking, konversi, pemakaian material di site, hitung stok."),
    dict(key="drv", name="Driver", code="driver", color="drv",
         kanal="PWA",
         tugas="Menerima tugas kirim, surat jalan digital, bukti terima (foto + tanda tangan)."),
    dict(key="pemoh", name="Pemohon Internal", code="internal_requester", color="pemoh",
         kanal="Web · PWA",
         tugas="Engineer / PIC proyek: mengajukan permintaan, konfirmasi terima, mengajukan retur."),
    dict(key="klien", name="Klien", code="client_user", color="klien",
         kanal="Portal klien (web)",
         tugas="User dari pemilik proyek: mengajukan permintaan termasuk non-katalog, menanggapi "
               "pengganti, mengajukan pembatalan, mengonfirmasi atau mengajukan keberatan terima, "
               "mengajukan retur, melacak status."),
    dict(key="pr", name="Penindak Lanjut PR", code="pr_follow_up", color="pr",
         kanal="Web",
         tugas="Mencatat catatan pemesanan per vendor / toko online (nomor PO, resi, perkiraan "
               "datang) dan membuat vendor sementara."),
    dict(key="audit", name="Auditor Internal", code="internal_auditor", color="audit",
         kanal="Web · PWA",
         tugas="Stock opname & rekonsiliasi; hanya-baca terhadap mutasi stok, boleh menginput hitungan."),
    dict(key="kagud_apr", name="Approver (fungsi)", code="—", color="apr",
         kanal="Web · WhatsApp [F2]",
         tugas="Bukan role tersendiri: fungsi yang diisi Kepala Gudang, Manajemen, Admin Company, "
               "atasan langsung, atau PIC proyek sesuai aturan approval company. Ditampilkan "
               "terpisah agar alur keputusannya terlihat utuh."),
    dict(key="manaj", name="Manajemen", code="management", color="manaj",
         kanal="Web · WhatsApp [F2]",
         tugas="Dashboard & laporan seluruh gudang; approver tingkat atas."),
    dict(key="admin", name="Admin Company", code="company_admin", color="admin",
         kanal="Web",
         tugas="Setup awal (wizard), user, role, struktur organisasi, aturan approval, pengaturan "
               "fitur stok, template dokumen."),
    dict(key="super", name="Super Admin (platform)", code="platform_admin", color="super",
         kanal="Web (database pusat)",
         tugas="Membuat company, mengatur paket & trial, memverifikasi pembayaran langganan, "
               "memantau tenant, menangguhkan company."),
]
ROLE_BY_KEY = {r["key"]: r for r in ROLES}

# ---------------------------------------------------------------------------
# PEMETAAN LANE -> PERAN (A-226, Perlu validasi)
# ---------------------------------------------------------------------------
LANE_ROLES = {
    "Sistem": [],
    "Pemohon (Internal / Klien)": ["pemoh", "klien"],
    "Pemohon / Klien": ["pemoh", "klien"],
    "Pemohon / PIC Proyek": ["pemoh"],
    "Staf Gudang": ["staf"],
    "Staf Gudang Site / Pemohon Internal": ["staf", "pemoh"],
    "Staf Gudang Asal": ["staf"],
    "Staf Gudang Tujuan": ["staf"],
    "Penghitung (Staf)": ["staf"],
    "Kepala Gudang": ["kagud"],
    "Kepala Gudang / Auditor": ["kagud", "audit"],
    "Pengaju (Staf / Kepala Gudang)": ["staf", "kagud"],
    "Pengaju": ["pemoh", "staf", "kagud", "pr", "audit"],
    # Approver bukan role tersendiri: fungsinya diisi Kepala Gudang, Manajemen, atau Admin
    # Company sesuai aturan approval company (Blueprint §4.2, §8.1), jadi langkahnya muncul di
    # keempat halaman. Kolom "Lane asal" di tabel langkah menandai bahwa ini bersyarat aturan.
    "Approver": ["kagud_apr", "kagud", "manaj", "admin"],
    "Delegat / Cadangan / Atasan": ["kagud_apr", "kagud", "manaj", "admin"],
    "Driver / Penerima": ["drv", "pemoh", "klien"],
    "Driver": ["drv"],
    "Penindak Lanjut PR": ["pr"],
    "Super Admin": ["super"],
    "Admin Company": ["admin"],
}

# Layar yang dipakai tiap peran; rute nyata dari routes/tenant.php, tabel "Layar" spesifikasi modul.
ROLE_SCREENS = {
    "kagud": ["/approvals", "/requests", "/picks", "/shipments", "/discrepancies", "/transfers",
              "/returns", "/vendor-returns", "/counts", "/adjustments", "/purchase-requests",
              "/waste-disposals", "/assets", "/reports"],
    "staf": ["/receipts", "/putaways", "/picks", "/shipments", "/issues", "/conversions",
             "/count-tasks", "/waste-disposals", "/transfers", "/stock"],
    "drv": ["/shipments"],
    "pemoh": ["/requests", "/requests/create", "/returns", "/notifications", "/reports"],
    "klien": ["/portal/login", "/portal (permintaan, pengiriman, stok on-site, retur)"],
    "pr": ["/purchase-requests", "/vendors", "/receipts"],
    "audit": ["/counts", "/counts/create", "/count-tasks", "/adjustments", "/reports", "/stock"],
    "kagud_apr": ["/approvals", "/approval-delegations", "/approval-simulation"],
    "manaj": ["/reports", "/approvals", "/stock", "/"],
    "admin": ["/setup", "/users", "/roles", "/org", "/approval-rules", "/approval-delegations",
              "/approval-simulation", "/items", "/warehouses", "/bins", "/projects", "/clients",
              "/vendors", "/uoms", "/references", "/imports", "/labels/print",
              "/settings/document-layout", "/settings/stock-period", "/settings/support-access",
              "/notifications/preferences", "/devices", "/billing"],
    "super": ["pusat: login Super Admin, daftar & pembuatan company, paket & trial, tagihan, "
              "verifikasi bukti bayar, penangguhan, akses dukungan"],
}

# Aktivitas konfigurasi yang TIDAK ada di FLOWS (Part 2 tidak memuat setup & pengaturan).
# Sumber: 10-access, 11-master, 12-warehouse, 18-template-dokumen-label, 20-approval,
# 21-opname-penyesuaian, 27-pendukung-f1.
CONFIG_STEPS = {
    "admin": [
        ("Wizard setup awal company — 8 langkah, termasuk persetujuan ketentuan layanan", "27-pendukung-f1.md"),
        ("Struktur organisasi: unit, jabatan, relasi atasan (dipakai approval engine)", "10-access.md"),
        ("User & undangan, role dari daftar permission, cakupan role × gudang/proyek (BR-GEN-09)", "10-access.md"),
        ("Master data: item, kategori, satuan & konversi kemasan, vendor, klien, proyek, alasan", "11-master.md"),
        ("Gudang, tipe gudang, zona/rak/level/bin, pembuat bin massal", "12-warehouse.md"),
        ("Impor master dari Excel dengan validasi baris & pratinjau (item, proyek, klien)", "27-pendukung-f1.md"),
        ("Aturan approval per jenis dokumen: lapis, cara putus, kondisi, batas waktu; simulasi sebelum simpan", "20-approval.md"),
        ("Delegasi approval berperiode", "20-approval.md"),
        ("Layout induk dokumen (logo, kop, footer, tanda tangan) & template per dokumen; label barcode/QR", "18-template-dokumen-label.md"),
        ("Pengaturan fitur stok & kunci periode stok", "21-opname-penyesuaian.md"),
        ("Preferensi notifikasi per kejadian & kanal", "27-pendukung-f1.md"),
        ("Akses dukungan berperiode untuk Super Admin (tautan sekali pakai, tercatat)", "10-access.md"),
        ("Unggah bukti bayar langganan", "17-platform-login.md"),
    ],
    "manaj": [
        ("Beranda: antrean pekerjaan & ringkasan seluruh gudang", "27-pendukung-f1.md"),
        ("14 laporan inti dengan filter periode bebas, ekspor Excel & PDF", "16-shared-laporan-berkas.md"),
        ("Laporan Material per Proyek: diminta vs terkirim vs terpakai vs diretur vs on-site vs waste", "16-shared-laporan-berkas.md"),
        ("Laporan akurasi stok hasil opname dan posisi aset dipinjamkan", "16-shared-laporan-berkas.md"),
        ("Approver tingkat atas sesuai aturan approval company", "20-approval.md"),
    ],
    "audit": [
        ("Laporan akurasi stok per gudang/zona dan trennya", "16-shared-laporan-berkas.md"),
        ("Riwayat audit per sesi opname", "21-opname-penyesuaian.md"),
        ("Hanya-baca terhadap kartu stok & mutasi; boleh menginput hitungan", "21-opname-penyesuaian.md"),
    ],
}


# ---------------------------------------------------------------------------
# HAK AKSES: dibaca dari seeder role (sumber sebenarnya), bukan diketik ulang
# ---------------------------------------------------------------------------
SEEDER = os.path.join(os.path.dirname(os.path.dirname(HERE)),
                      "database", "seeders", "Tenant", "ReferenceSeeder.php")

# Modul permission digabung jadi area yang enak dibaca; urutannya mengikuti alur kerja.
AREAS = [
    ("Akses & profil", ["auth", "profile", "user", "role", "org", "device", "support_access"]),
    ("Master data", ["master"]),
    ("Gudang & lokasi", ["warehouse"]),
    ("Stok & kartu stok", ["stock"]),
    ("Permintaan material", ["request"]),
    ("Picking & pengiriman", ["picking", "shipment"]),
    ("Penerimaan & put-away", ["receipt", "putaway", "vendor_return"]),
    ("Transfer & retur", ["transfer", "return"]),
    ("Pemakaian di proyek", ["issue"]),
    ("Konversi & waste", ["conversion", "waste"]),
    ("Aset dipinjamkan", ["asset"]),
    ("Opname & penyesuaian", ["count", "adjustment"]),
    ("Pembelian", ["purchase_request", "purchase_order", "vendor_price"]),
    ("Approval", ["approval"]),
    ("Template & label", ["template"]),
    ("Langganan", ["billing"]),
]
AREA_OF = {m: a for a, mods in AREAS for m in mods}

LEVELS = {  # simbol matriks: (simbol, keterangan)
    "none": ("·", "tidak ada akses"),
    "view": ("○", "lihat saja"),
    "appr": ("◇", "lihat + menyetujui"),
    "work": ("●", "mengerjakan"),
    "full": ("◆", "mengerjakan + menyetujui"),
}


def read_access():
    """(labels, module_of, roles) dari ReferenceSeeder.php."""
    src = io.open(SEEDER, encoding="utf-8").read()
    labels, module_of, cur = {}, {}, None
    for ln in src.split("private const ROLES")[0].splitlines():
        m = re.match(r"^ {8}'(\w+)' => \[", ln)
        if m:
            cur = m.group(1)
            continue
        m = re.match(r"^ {12}'([\w.]+)' => '([^']*)'", ln)
        if m and cur:
            labels[m.group(1)], module_of[m.group(1)] = m.group(2), cur
    roles, cur = {}, None
    for ln in ("private const ROLES" + src.split("private const ROLES")[1]).splitlines():
        m = re.match(r"^ {8}'(\w+)' => \[", ln)
        if m:
            cur = m.group(1)
            roles[cur] = {"name": cur, "perms": []}
        m2 = re.search(r"'name' => '([^']+)'", ln)
        if m2 and cur:
            roles[cur]["name"] = m2.group(1)
        m3 = re.search(r"'permissions' => (.+),\s*$", ln)
        if m3 and cur:
            roles[cur]["perms"] = (list(labels) if m3.group(1).strip() == "'*'"
                                   else re.findall(r"'([\w.]+)'", m3.group(1)))
    return labels, module_of, roles


def level_of(perms, area_mods, module_of):
    got = [p for p in perms if module_of.get(p) in area_mods]
    if not got:
        return "none"
    approve = any(p.endswith((".approve", ".reconcile")) for p in got)
    work = any(not p.endswith(".view") and not p.endswith((".approve", ".reconcile")) for p in got)
    return "full" if (work and approve) else "work" if work else "appr" if approve else "view"


# ---------------------------------------------------------------------------
# ALUR CERITA: urutan kejadian sebenarnya, dengan input dan output tiap tahap
# ---------------------------------------------------------------------------
# (no, judul, peran utama, input, yang dikerjakan + layar, output/hasil, dokumen)
STORY = [
    (0, "Menyiapkan sistem", "admin",
     "Data perusahaan, daftar barang, daftar gudang, daftar proyek & klien, daftar orang",
     "Wizard setup, lalu master: barang & satuan, gudang + zona/rak/bin, proyek (dengan Gudang Site-nya), "
     "klien, vendor, user & role + cakupan, aturan approval, layout dokumen "
     "(<span class='mono'>/setup /items /warehouses /bins /projects /clients /users /roles /approval-rules</span>)",
     "Master siap, orang bisa login sesuai cakupan gudang/proyeknya, aturan persetujuan aktif", "—"),
    (1, "Mengisi stok awal", "staf",
     "Barang datang dari vendor, atau saldo awal saat mulai pakai sistem",
     "Permintaan pembelian → catat pesanan ke vendor → terima barang → periksa mutu per baris → "
     "simpan ke bin (<span class='mono'>/purchase-requests /receipts /putaways</span>)",
     "Saldo stok tercatat per bin/lot/serial/potongan; kartu stok terisi", "PRQ → GRN → PUT"),
    (2, "Meminta barang", "pemoh",
     "Kebutuhan material di titik pekerjaan: proyek, daftar barang, jumlah, tanggal dibutuhkan",
     "Pemohon Internal lewat <span class='mono'>/requests/create</span>, atau Klien lewat "
     "<span class='mono'>/portal</span>. Boleh mengisi barang yang belum ada di katalog",
     "REQ diajukan dan masuk antrean; pemohon bisa melacak statusnya", "REQ"),
    (3, "Meninjau & menentukan sumber", "kagud",
     "REQ yang baru diajukan",
     "Kepala Gudang/Staf memetakan barang non-katalog, memilih gudang sumber, mengisi tanggal janji; "
     "satu baris boleh dipecah ke beberapa gudang (<span class='mono'>/requests</span>)",
     "REQ punya sumber pemenuhan yang jelas dan naik ke persetujuan", "REQ"),
    (4, "Menyetujui", "kagud_apr",
     "REQ yang sudah bersumber",
     "Approver menyetujui atau menolak dengan alasan; berlapis sesuai aturan company "
     "(<span class='mono'>/approvals</span>)",
     "Stok <b>dicadangkan</b> (reservasi lunak). Kalau kurang, sisanya otomatis jadi transfer dari "
     "gudang lain atau permintaan pembelian", "REQ → TRF / PRQ"),
    (5, "Menyiapkan barang", "staf",
     "REQ yang disetujui",
     "Tugas picking: sistem menyarankan bin & lot (FIFO/FEFO/sisa potongan), staf memindai dan mencatat; "
     "kurang ambil wajib beralasan (<span class='mono'>/picks</span>)",
     "Barang pindah ke Loading Area, alokasi menjadi keras (terkunci ke dokumen)", "PCK"),
    (6, "Mengirim", "drv",
     "Tugas picking yang selesai",
     "Susun surat jalan (boleh gabung beberapa permintaan ke tujuan sama), pilih kendaraan/ekspedisi, "
     "berangkatkan (<span class='mono'>/shipments</span>)",
     "Stok pindah ke lokasi <i>Dalam Perjalanan</i> milik gudang asal — masih milik perusahaan", "SJ"),
    (7, "Menerima di tujuan", "drv",
     "Barang tiba di titik pekerjaan atau di tempat klien",
     "Driver atau penerima mengisi bukti terima per baris: baik / kurang / rusak, foto bila rusak, "
     "tanda tangan di perangkat",
     "Barang baik keluar/masuk sesuai tujuan; yang kurang & rusak <b>tidak hilang</b> — tetap tercatat "
     "dan membuka dokumen selisih", "SJ → DSC"),
    (8, "Menyelesaikan selisih", "kagud",
     "Dokumen selisih yang terbuka",
     "Kepala Gudang memilih disposisi: kembali ke gudang, disesuaikan, diklaim ke ekspedisi, atau "
     "kirim pengganti (<span class='mono'>/discrepancies</span>)",
     "Selisih tertutup dengan alasan yang bisa dilaporkan; tidak ada stok yang menggantung", "DSC"),
    (9, "Konfirmasi penerima", "pemoh",
     "Bukti terima dari driver",
     "Pemohon atau Klien mengonfirmasi, atau mengajukan keberatan bila kurang/rusak (batas 3 hari, "
     "lewat itu dianggap diterima)",
     "REQ selesai, atau membuka selisih baru bila ada keberatan", "REQ"),
    (10, "Memakai di proyek", "staf",
     "Barang yang sudah ada di Gudang Site",
     "Catat pemakaian material saat dipakai (<span class='mono'>/issues</span>); pindahkan antar titik "
     "pekerjaan bila perlu (<span class='mono'>/transfers</span>)",
     "<b>Beban material proyek tercatat</b> — inilah angka yang dipakai laporan Material per Proyek", "ISU / TRF"),
    (11, "Memotong & sisa potongan", "staf",
     "Barang berukuran panjang, mis. pipa atau batang angkur",
     "Dokumen konversi: input, hasil, sisa; sisa ≥ panjang minimum kembali jadi stok, sisanya jadi waste "
     "(<span class='mono'>/conversions /waste-disposals</span>)",
     "Neraca ukuran seimbang, tiap potongan punya silsilah, persentase waste per proyek terukur", "CNV / WST"),
    (12, "Alat dipinjamkan", "kagud",
     "Permintaan berisi barang bertipe aset (mis. mesin grouting)",
     "Serah terima otomatis saat surat jalan diterima; catat jatuh tempo & meter; saat kembali diperiksa "
     "grade + skor kondisi + foto (<span class='mono'>/assets /asset-handovers</span>)",
     "Posisi tiap alat terlihat, alat telat kembali terdeteksi, hari & jam pakai per proyek tercatat", "AST"),
    (13, "Mengembalikan sisa", "pemoh",
     "Barang sisa di Gudang Site atau barang yang tidak jadi dipakai",
     "Ajukan retur → surat jalan balik → terima di gudang → pilah: layak / rusak / offcut / waste "
     "(<span class='mono'>/returns</span>)",
     "Saldo kembali sesuai hasil pemilahan, bukan asal masuk gudang", "RET"),
    (14, "Menghitung & mengoreksi", "audit",
     "Jadwal opname bulanan/tahunan, atau pemeriksaan mendadak",
     "Buat sesi, bekukan lokasi, hitung buta lewat HP, selisih besar dihitung ulang orang berbeda, "
     "rekonsiliasi (<span class='mono'>/counts /count-tasks</span>)",
     "Penyesuaian diposting lewat kartu stok dengan akar masalah; akurasi stok terukur", "OPN → ADJ"),
    (15, "Melihat hasilnya", "manaj",
     "Semua dokumen di atas",
     "14 laporan dengan filter periode, ekspor Excel & PDF (<span class='mono'>/reports</span>)",
     "Material per proyek, kartu stok, mutasi, akurasi stok, posisi aset, permintaan terbuka", "—"),
]


def story_diagram():
    L = ["flowchart TD"]
    for k, (fill, stroke) in COLORS.items():
        L.append(f"  classDef {k} fill:{fill},stroke:{stroke},stroke-width:1.5px,color:#1b2430")
    for no, judul, who, *_ in STORY:
        L.append(f'  s{no}["{no}. {q(judul)}"]:::{ROLE_BY_KEY[who]["color"]}')
    for no, *_ in STORY[:-1]:
        L.append(f"  s{no} --> s{no + 1}")
    return "\n".join(L)


def q(s):
    """Escape untuk label Mermaid (tanda kutip & kurung merusak parser)."""
    return (s.replace("&", "#amp;").replace('"', "#quot;")
             .replace("<", "#lt;").replace(">", "#gt;")
             .replace("(", "#40;").replace(")", "#41;"))


def roles_of(lane):
    return LANE_ROLES.get(lane, [])


def lane_color(lane):
    keys = roles_of(lane)
    if not keys:
        return "sys"
    return ROLE_BY_KEY[keys[0]]["color"]


def role_flows(role_key):
    """[(flow, [node...])] — langkah peran ini per alur, urut topologis."""
    out = []
    for f in g.FLOWS:
        rank = g.ranks(f)
        mine = [n for n in f["nodes"] if role_key in roles_of(n[1])]
        if mine:
            out.append((f, sorted(mine, key=lambda n: rank[n[0]])))
    return out


def diagram(role, f, mine):
    """Mermaid flowchart TD: langkah peran + efek Sistem + penanda serah-terima."""
    ids = {n[0] for n in mine}
    lanes = {n[0]: n[1] for n in f["nodes"]}
    kinds = {n[0]: n[2] for n in f["nodes"]}
    labels = {n[0]: n[3] for n in f["nodes"]}
    pre = f'{role["key"]}{f["n"]}_'
    L, seen, done = [], set(), set()

    def node(nid, cls):
        if nid in seen:
            return
        seen.add(nid)
        t, k = q(labels[nid]), kinds[nid]
        shape = {"start": f'(["{t}"])', "end": f'(["{t}"])', "gw": f'{{"{t}"}}',
                 "timer": f'(("{t}"))'}.get(k, f'["{t}"]')
        L.append(f"  {pre}{nid}{shape}:::{cls}")

    def marker(key, text, cls):
        if key in seen:
            return
        seen.add(key)
        L.append(f'  {key}(["{q(text)}"]):::{cls}')

    def edge(a, b, label, back):
        arrow = "-.->" if back else "-->"
        lab = f'|"{q(label)}"|' if label else ""
        L.append(f"  {a} {arrow}{lab} {b}")

    for nid in [n[0] for n in mine]:
        node(nid, role["color"])

    for a, b, label, back in f["edges"]:
        a_mine, b_mine = a in ids, b in ids
        if a_mine and b_mine:
            edge(f"{pre}{a}", f"{pre}{b}", label, back)
        elif a_mine:
            if lanes[b] == "Sistem":
                node(b, "sys")
                edge(f"{pre}{a}", f"{pre}{b}", label, back)
                for c, d, l2, bk2 in f["edges"]:
                    if c == b and d not in ids and lanes[d] != "Sistem":
                        key = f"{pre}out_{b}_{d}"
                        marker(key, "ke: " + lanes[d], lane_color(lanes[d]))
                        edge(f"{pre}{b}", key, l2, bk2)
            else:
                key = f"{pre}out_{a}_{b}"
                marker(key, "ke: " + lanes[b], lane_color(lanes[b]))
                edge(f"{pre}{a}", key, label, back)
        elif b_mine:
            src = lanes[a]
            if src == "Sistem":
                node(a, "sys")
                edge(f"{pre}{a}", f"{pre}{b}", label, back)
                for c, d, l2, bk2 in f["edges"]:
                    if d == a and c not in ids and lanes[c] != "Sistem" and (a, c) not in done:
                        done.add((a, c))
                        key = f"{pre}in_{c}_{a}"
                        marker(key, "dari: " + lanes[c], lane_color(lanes[c]))
                        edge(key, f"{pre}{a}", l2, bk2)
            else:
                key = f"{pre}in_{a}_{b}"
                marker(key, "dari: " + src, lane_color(src))
                edge(key, f"{pre}{b}", label, back)

    head = ["flowchart TD"]
    for k, (fill, stroke) in COLORS.items():
        head.append(f"  classDef {k} fill:{fill},stroke:{stroke},stroke-width:1.5px,color:#1b2430")
    return "\n".join(head + L)


KIND = {"start": "Mulai", "end": "Selesai", "task": "Langkah", "gw": "Keputusan", "timer": "Tunggu"}


# ---------------------------------------------------------------------------
# SEQUENCE DIAGRAM
# ---------------------------------------------------------------------------
def sq(s, limit=160):
    """Teks aman untuk sequence diagram: satu baris, tanpa karakter perusak parser."""
    s = " ".join(s.split())
    if len(s) > limit:
        s = s[:limit - 1].rstrip(" ,;/") + "…"
    return (s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
             .replace("|", "/").replace(";", " ·").replace(":", " –"))


def wrap(s, width=44):
    """Potong baris dengan <br/> supaya kotak pesan tidak melebar ke samping."""
    out, line = [], ""
    for w in s.split(" "):
        if line and len(line) + 1 + len(w) > width:
            out.append(line)
            line = w
        else:
            line = f"{line} {w}".strip()
    if line:
        out.append(line)
    return "<br/>".join(out)


def msg(text, limit=160):
    return wrap(sq(text, limit))


# Cast tetap untuk sequence end-to-end: (id, label, peran untuk warna)
CAST = [("PMH", "Pemohon / Klien"), ("KG", "Kepala Gudang"), ("APR", "Approver"),
        ("SG", "Staf Gudang"), ("DRV", "Driver"), ("VND", "Vendor"), ("SYS", "Sistem")]
# Tahap STORY -> (pengirim, penerima) pesan utamanya
STORY_ACTORS = {
    0: ("KG", "SYS"), 1: ("SG", "SYS"), 2: ("PMH", "SYS"), 3: ("KG", "SYS"), 4: ("APR", "SYS"),
    5: ("SG", "SYS"), 6: ("DRV", "SYS"), 7: ("DRV", "SYS"), 8: ("KG", "SYS"), 9: ("PMH", "SYS"),
    10: ("SG", "SYS"), 11: ("SG", "SYS"), 12: ("KG", "SYS"), 13: ("PMH", "SYS"),
    14: ("SG", "SYS"), 15: ("KG", "SYS"),
}


def seq_endtoend():
    """Sequence besar mengikuti 16 tahap STORY, cast tetap tingkat bisnis."""
    L = ["sequenceDiagram", "  autonumber"]
    for pid, label in CAST:
        L.append(f"  participant {pid} as {label}")
    for no, judul, who, inp, aksi, out, dok in STORY:
        frm, to = STORY_ACTORS[no]
        aksi_plain = re.sub(r"<[^>]+>", "", aksi)
        out_plain = re.sub(r"<[^>]+>", "", out)
        L.append(f"  Note over {frm}: {sq(f'{no}. {judul}', 70)}")
        if no == 1:                       # vendor: pesan lalu barang datang
            L.append(f"  SG->>VND: {msg('Catatan pemesanan – nomor PO, perkiraan datang')}")
            L.append(f"  VND-->>SG: {msg('Barang datang beserta surat jalan vendor')}")
        L.append(f"  {frm}->>{to}: {msg(aksi_plain, 190)}")
        L.append(f"  Note right of SYS: {msg(out_plain, 190)}")
        if dok != "—":
            L.append(f"  SYS-->>{frm}: {msg('Dokumen – ' + dok, 70)}")
        if no == 4:                       # keputusan approval
            L.append("  alt Disetujui")
            L.append(f"  SYS->>SG: {msg('Stok dicadangkan, tugas picking dibuat')}")
            L.append("  else Ditolak")
            L.append(f"  SYS-->>PMH: {msg('Permintaan ditolak beserta alasannya')}")
            L.append("  end")
        if no == 7:                       # keputusan bukti terima
            L.append("  alt Diterima lengkap dan baik")
            L.append(f"  SYS-->>PMH: {msg('Permintaan lanjut ke konfirmasi penerima')}")
            L.append("  else Ada yang kurang atau rusak")
            L.append(f"  SYS->>KG: {msg('Dokumen selisih pengiriman terbuka')}")
            L.append("  end")
    return "\n".join(L)


def seq_flow(f):
    """Sequence satu alur, diturunkan dari FLOWS. Nomor langkah = nomor tabel langkah."""
    rank = g.ranks(f)
    order = sorted(f["nodes"], key=lambda n: (rank[n[0]], f["lanes"].index(n[1])))
    no = {n[0]: i for i, (n) in enumerate(order, 1)}
    lanes = {n[0]: n[1] for n in f["nodes"]}
    kinds = {n[0]: n[2] for n in f["nodes"]}
    labels = {n[0]: n[3] for n in f["nodes"]}
    refs = {n[0]: n[4] for n in f["nodes"]}
    pid = {lane: f"P{i}" for i, lane in enumerate(f["lanes"], 1)}
    seen = set()

    L = ["sequenceDiagram", "  autonumber"]
    for lane in f["lanes"]:
        L.append(f"  participant {pid[lane]} as {sq(lane, 40)}")

    incoming = {}
    for a, b, lab, back in f["edges"]:
        incoming.setdefault(b, []).append((a, lab, back))

    for nid, lane, kind, label, ref in order:
        me = pid[lane]
        if kind in ("start", "timer"):
            L.append(f"  Note over {me}: {sq(f'● {no[nid]}. {label}', 90)}")
            seen.add(nid)
            continue
        if kind == "gw":
            L.append(f"  Note over {me}: {sq(f'◇ {no[nid]}. {label}', 90)}")
            outs = [(b, lab, back) for a, b, lab, back in f["edges"] if a == nid]
            for i, (b, lab, back) in enumerate(outs):
                L.append(("  alt " if i == 0 else "  else ") + sq(lab or "lanjut", 48))
                L.append(f"    {me}->>{pid[lanes[b]]}: {msg(f'{no[b]}. {labels[b]}', 130)}")
                seen.add(b)
                if back:
                    L.append(f"    Note over {pid[lanes[b]]}: ⟲ kembali ke langkah {no[b]}")
                elif kinds[b] == "end":
                    L.append(f"    Note over {pid[lanes[b]]}: ◉ selesai")
                else:
                    L.append(f"    Note over {pid[lanes[b]]}: lanjut ke langkah {no[b]}")
            if outs:
                L.append("  end")
            seen.add(nid)
            continue
        # langkah biasa: gambarkan sebagai pesan dari pengirimnya
        src = [a for a, lab, back in incoming.get(nid, []) if kinds[a] != "gw"]
        frm = pid[lanes[src[0]]] if src else me
        arrow = "->>" if frm != me else "-)"
        text = msg(f"{no[nid]}. {label}", 150)
        L.append(f"  {frm}{arrow}{me}: {text}" if frm != me else f"  {me}->>{me}: {text}")
        if ref:
            side = "right of" if lane == "Sistem" else "over"
            L.append(f"  Note {side} {me}: {msg(ref, 150)}")
        if kind == "end":
            L.append(f"  Note over {me}: ◉ selesai")
        seen.add(nid)
    missing = [nid for nid, lane, *_ in f["nodes"] if nid not in seen and lane != "Sistem"]
    return "\n".join(L), missing


def sequence_html():
    blocks, warn = [], []
    for f in g.FLOWS:
        txt, missing = seq_flow(f)
        opens = sum(1 for ln in txt.splitlines() if ln.strip().startswith(("alt ", "opt ")))
        ends = sum(1 for ln in txt.splitlines() if ln.strip() == "end")
        if opens != ends:
            warn.append(f'alur {f["n"]}: blok alt/opt {opens} vs end {ends}')
        if missing:
            warn.append(f'alur {f["n"]}: node tidak muncul {missing}')
        rank = g.ranks(f)
        order = sorted(f["nodes"], key=lambda n: (rank[n[0]], f["lanes"].index(n[1])))
        rows = "".join(
            f'<tr><td class="k">{i}</td><td class="k">{KIND[k]}</td><td>{html.escape(lane)}</td>'
            f'<td>{html.escape(label)}</td><td class="ref">{html.escape(ref) or "—"}</td></tr>'
            for i, (nid, lane, k, label, ref) in enumerate(order, 1))
        blocks.append(f'''
  <article class="alur">
    <h4><span class="num">Alur {f["n"]}</span> {html.escape(f["title"])}
      <span class="mono tag">{" · ".join(f["status"])}</span></h4>
    <div class="diagram"><pre class="mermaid">
{html.escape(txt, quote=False)}
</pre></div>
    <details><summary>Rincian {len(order)} langkah (nomor sama dengan di diagram)</summary>
      <div class="tw"><table><thead><tr><th>#</th><th>Jenis</th><th>Pelaku</th><th>Langkah</th>
      <th>Status / efek / aturan</th></tr></thead><tbody>{rows}</tbody></table></div>
    </details>
  </article>''')
    return warn, f'''
<section id="sequence" class="role">
  <header class="rh">
    <p class="eyebrow">Urutan waktu</p>
    <h2>Sequence: siapa mengirim apa ke siapa</h2>
    <p class="intro">Diagram aktivitas menjawab <i>"apa yang saya kerjakan"</i>; sequence
      menjawab <i>"bagaimana kami saling berkirim sampai barang sampai"</i>. Baris
      <b>Sistem</b> memperlihatkan kapan stok benar-benar berubah dan dokumen apa yang lahir.</p>
    <p class="note"><b>Cara baca:</b> nomor langkah di sini <b>sama</b> dengan nomor di tabel
      rincian tiap alur, jadi bisa dicocokkan dengan diagram aktivitas per peran.
      <code>alt</code> = percabangan keputusan; tiap cabang ditampilkan satu langkah lalu
      ditunjuk nomor lanjutannya. Untuk percabangan yang rumit, rujukan yang utuh tetap diagram
      BPMN <span class="mono">bpmn-*.drawio</span> dan tabel langkah di bawah tiap diagram.</p>
  </header>
  <article class="alur">
    <h4><span class="num">Menyeluruh</span> Dari sistem kosong sampai barang dipakai
      <span class="mono tag">16 tahap</span></h4>
    <div class="diagram"><pre class="mermaid">
{html.escape(seq_endtoend(), quote=False)}
</pre></div>
  </article>
  {"".join(blocks)}
  <p class="top"><a href="#daftar">↑ Daftar peran</a></p>
</section>'''


def story_html():
    rows = "".join(
        f'<tr><td class="k"><span class="num">{no}</span></td>'
        f'<td><b>{html.escape(judul)}</b><br><span class="who" style="background:'
        f'{COLORS[ROLE_BY_KEY[who]["color"]][0]};border-color:{COLORS[ROLE_BY_KEY[who]["color"]][1]}">'
        f'{html.escape(ROLE_BY_KEY[who]["name"])}</span></td>'
        f'<td>{inp}</td><td>{aksi}</td><td class="outp">{out}</td>'
        f'<td class="ref">{html.escape(dok)}</td></tr>'
        for no, judul, who, inp, aksi, out, dok in STORY)
    return f'''
<section id="cerita" class="role">
  <header class="rh">
    <p class="eyebrow">Baca ini dulu</p>
    <h2>Alur cerita: dari sistem kosong sampai barang dipakai di proyek</h2>
    <p class="intro">Enam belas tahap berurutan seperti kejadian sebenarnya, lengkap dengan
      <b>apa yang masuk</b> dan <b>apa yang keluar</b> di tiap tahap. Halaman peran di bawah
      memperdalam tiap tahap dari sudut orang yang mengerjakannya.</p>
    <p class="note"><b>Catatan:</b> proyek dan akun klien <b>dibuat oleh Admin Company</b> sebagai
      master data (tahap 0), bukan oleh klien. Yang dilakukan klien adalah <b>mengajukan
      permintaan barang</b> lewat portal untuk proyek miliknya (tahap 2). Klien tidak pernah
      melihat gudang atau proyek perusahaan lain.</p>
  </header>
  <div class="diagram"><pre class="mermaid">
{html.escape(story_diagram(), quote=False)}
</pre></div>
  <div class="tw"><table class="story"><thead><tr><th>#</th><th>Tahap &amp; pelaku</th>
    <th>Input (yang masuk)</th><th>Yang dikerjakan</th><th>Output (yang keluar)</th>
    <th>Dokumen</th></tr></thead><tbody>{rows}</tbody></table></div>
  <p class="top"><a href="#daftar">↑ Daftar peran</a></p>
</section>'''


def matrix_html(module_of, roles):
    cols = [r for r in ROLES if r["code"] in roles]
    head = "".join(f'<th class="rot"><span>{html.escape(r["name"])}</span></th>' for r in cols)
    body = []
    for area, mods in AREAS:
        cells = []
        for r in cols:
            lv = level_of(roles[r["code"]]["perms"], mods, module_of)
            sym, ket = LEVELS[lv]
            cells.append(f'<td class="lv lv-{lv}" title="{html.escape(r["name"])}: {ket}">{sym}</td>')
        body.append(f'<tr><th class="area">{html.escape(area)}</th>{"".join(cells)}</tr>')
    legend = " · ".join(f'<b>{s}</b> {k}' for s, k in LEVELS.values())
    extra = [c for c in roles if c not in {r["code"] for r in cols}]
    note = ""
    if extra:
        note = ('<p class="note"><b>Tidak ditampilkan di kolom:</b> '
                + ", ".join(html.escape(roles[c]["name"]) for c in extra)
                + '. Role ini ada di sistem tetapi tidak punya lane sendiri di alur Part 2.</p>')
    return f'''
<section id="akses" class="role">
  <header class="rh">
    <p class="eyebrow">Siapa boleh apa</p>
    <h2>Matriks hak akses per peran</h2>
    <p class="intro">Dibaca langsung dari seeder role aplikasi
      (<span class="mono">database/seeders/Tenant/ReferenceSeeder.php</span>), jadi ini hak akses
      yang benar-benar berlaku — bukan rencana. {len(module_of)} permission dikelompokkan jadi
      {len(AREAS)} area.</p>
    <p class="note">{legend}</p>
    <p class="note"><b>Selain ini berlaku cakupan data:</b> hak akses dibatasi lagi oleh penugasan
      role ke gudang dan/atau proyek tertentu. Dua orang dengan role sama bisa melihat data yang
      berbeda. Role bawaan boleh diubah atau ditambah sendiri oleh Admin Company.</p>
  </header>
  <div class="tw"><table class="mx"><thead><tr><th class="area">Area</th>{head}</tr></thead>
    <tbody>{"".join(body)}</tbody></table></div>
  {note}
  <p class="top"><a href="#daftar">↑ Daftar peran</a></p>
</section>'''


def usecase_html(role, labels, module_of, roles):
    if role["code"] not in roles:
        return ('<article class="alur cfg"><h4>Use case &amp; hak akses</h4>'
                '<p class="note">Bukan role di database tenant, jadi tidak punya daftar permission '
                'sendiri — lihat peran yang mengisinya.</p></article>')
    perms = roles[role["code"]]["perms"]
    rows = []
    for area, mods in AREAS:
        got = [p for p in perms if module_of.get(p) in mods]
        if not got:
            continue
        sym, ket = LEVELS[level_of(perms, mods, module_of)]
        chips = " · ".join(html.escape(labels[p]) for p in got)
        rows.append(f'<tr><td class="k"><b>{sym}</b> {html.escape(area)}</td><td>{chips}</td></tr>')
    return f'''
  <article class="alur">
    <h4>Use case &amp; hak akses <span class="mono tag">{len(perms)} permission</span></h4>
    <p class="note">Yang boleh dilakukan peran ini, apa adanya dari seeder role aplikasi.
      Masih dibatasi lagi oleh cakupan gudang/proyek pada penugasannya.</p>
    <div class="tw"><table><thead><tr><th>Area</th><th>Boleh melakukan</th></tr></thead>
      <tbody>{"".join(rows)}</tbody></table></div>
  </article>'''


def main():
    labels, module_of, acc_roles = read_access()
    unmapped, sections, toc, total = [], [], [], 0
    for f in g.FLOWS:
        for nid, lane, _k, label, _r in f["nodes"]:
            if lane not in LANE_ROLES:
                unmapped.append(f'alur {f["n"]} · lane tidak dikenal "{lane}" · {label}')
            elif lane != "Sistem" and not roles_of(lane):
                unmapped.append(f'alur {f["n"]} · lane "{lane}" tidak memetakan ke peran · {label}')

    for role in ROLES:
        rf = role_flows(role["key"])
        steps = sum(len(m) for _, m in rf)
        total += steps
        fill, stroke = COLORS[role["color"]]
        toc.append(f'<li><a href="#p-{role["key"]}">'
                   f'<span class="sw" style="background:{fill};border-color:{stroke}"></span>'
                   f'{html.escape(role["name"])}<span class="cnt">{steps}</span></a></li>')

        blocks = []
        for f, mine in rf:
            rows = "".join(
                f'<tr><td class="k">{KIND[k]}</td><td>{html.escape(label)}</td>'
                f'<td>{html.escape(lane)}</td><td class="ref">{html.escape(ref) or "—"}</td></tr>'
                for _nid, lane, k, label, ref in mine)
            blocks.append(f'''
  <article class="alur">
    <h4><span class="num">Alur {f["n"]}</span> {html.escape(f["title"])}
      <span class="mono tag">{" · ".join(f["status"])}</span></h4>
    <div class="diagram"><pre class="mermaid">
{html.escape(diagram(role, f, mine), quote=False)}
</pre></div>
    <details><summary>Rincian {len(mine)} langkah</summary>
      <div class="tw"><table><thead><tr><th>Jenis</th><th>Langkah</th><th>Lane asal</th>
      <th>Status / efek / aturan</th></tr></thead><tbody>{rows}</tbody></table></div>
    </details>
  </article>''')

        apr = ('<p class="note"><b>Catatan:</b> langkah dengan lane asal <i>Approver</i> atau '
               '<i>Delegat / Cadangan / Atasan</i> bersifat <b>bersyarat</b> — hanya berlaku bila '
               'aturan approval company menunjuk peran ini sebagai approver untuk jenis dokumen '
               'tersebut (Blueprint §8.1). Tanpa aturan, dokumen langsung disetujui (A-08), '
               'kecuali Penyesuaian Stok manual (A-09).</p>'
               if role["key"] in ("kagud", "manaj", "admin", "kagud_apr") else "")

        cfg = ""
        if role["key"] in CONFIG_STEPS:
            items = "".join(
                f'<li>{html.escape(t)} <span class="src mono">{html.escape(src)}</span></li>'
                for t, src in CONFIG_STEPS[role["key"]])
            cfg = f'''
  <article class="alur cfg">
    <h4>Pengaturan &amp; konfigurasi <span class="tag warn">bukan turunan BPMN</span></h4>
    <p class="note">Aktivitas ini tidak ada di alur Part 2 (Part 2 belum memuat setup &amp;
    konfigurasi company). Daftar di bawah disusun dari spesifikasi modul, bukan dari
    <span class="mono">FLOWS</span>.</p>
    <ol class="cfglist">{items}</ol>
  </article>'''

        uc = usecase_html(role, labels, module_of, acc_roles)
        screens = "".join(f'<li class="mono">{html.escape(s)}</li>'
                          for s in ROLE_SCREENS.get(role["key"], []))
        body = "".join(blocks) or ('<p class="note">Tidak ada langkah peran ini di alur Part 2 — '
                                   'lihat bagian Pengaturan &amp; konfigurasi di bawah.</p>')
        sections.append(f'''
<section id="p-{role["key"]}" class="role">
  <header class="rh">
    <p class="eyebrow"><span class="sw" style="background:{fill};border-color:{stroke}"></span>
      Peran · <span class="mono">{html.escape(role["code"])}</span></p>
    <h2>{html.escape(role["name"])}</h2>
    <p class="intro">{html.escape(role["tugas"])}</p>
    <p class="meta"><b>Kanal:</b> {html.escape(role["kanal"])} · <b>Langkah di alur Part 2:</b>
      {steps} · <b>Cakupan data:</b> melekat pada penugasan role (gudang dan/atau proyek), BR-GEN-09</p>
    <p class="meta"><b>Layar:</b></p><ul class="screens">{screens}</ul>
    {apr}
  </header>
  {uc}
  {body}
  {cfg}
  <p class="top"><a href="#daftar">↑ Daftar peran</a></p>
</section>''')
        print(f'{role["name"]}: {steps} langkah di {len(rf)} alur')

    if unmapped:
        print("\nPERINGATAN — node tidak terpetakan ke peran mana pun:")
        for u in unmapped:
            print("  -", u)
    else:
        print("\nOK: semua lane di FLOWS terpetakan (tidak ada langkah yang hilang).")
    nonsys = sum(1 for f in g.FLOWS for n in f["nodes"] if n[1] != "Sistem")
    print(f"node non-Sistem di FLOWS: {nonsys} · total langkah di diagram peran: {total}")

    if os.path.exists(VENDOR_MERMAID):
        src, srcnote = "vendor/mermaid.min.js", "Mermaid dimuat dari berkas lokal (bisa dipakai luring)."
    else:
        src, srcnote = (MERMAID_CDN, "Mermaid dimuat dari CDN, jadi perlu koneksi internet. Untuk "
                        "demo luring, simpan mermaid.min.js di docs/00-audit/vendor/ lalu generate ulang.")
    print("mermaid:", src)

    seq_warn, seq = sequence_html()
    print(f"sequence: 1 menyeluruh + {len(g.FLOWS)} per alur")
    if seq_warn:
        print("PERINGATAN sequence:")
        for w in seq_warn:
            print("  -", w)
    else:
        print("OK: blok alt/end seimbang dan semua langkah muncul di sequence.")

    page = PAGE.format(toc="".join(toc), sections="".join(sections), src=src, srcnote=srcnote,
                       nroles=len(ROLES), nflows=len(g.FLOWS), ntahap=len(STORY),
                       story=story_html(), matrix=matrix_html(module_of, acc_roles),
                       sequence=seq)
    os.makedirs(AUDIT, exist_ok=True)
    with open(OUT, "w", encoding="utf-8") as fh:
        fh.write(page)
    print("OK", OUT)


PAGE = '''<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alur Aktivitas per Peran — WMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
:root {{
  --bg:#f4f6f5; --surface:#ffffff; --ink:#1b2430; --muted:#5b6673; --line:#d9dfdc; --accent:#1f6f66;
  --warn:#b7801b;
  --sans:"IBM Plex Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
  --mono:"IBM Plex Mono", ui-monospace, Consolas, monospace;
}}
@media (prefers-color-scheme: dark) {{
  :root:not([data-theme="light"]) {{ --bg:#141a1d; --surface:#1c2428; --ink:#e6ecea; --muted:#9aa7a4;
    --line:#2e3a3e; --accent:#6cc5b8; --warn:#e0b25c; color-scheme:dark; }}
}}
:root[data-theme="dark"] {{ --bg:#141a1d; --surface:#1c2428; --ink:#e6ecea; --muted:#9aa7a4;
  --line:#2e3a3e; --accent:#6cc5b8; --warn:#e0b25c; color-scheme:dark; }}
* {{ box-sizing:border-box; }}
body {{ margin:0; background:var(--bg); color:var(--ink); font:15px/1.6 var(--sans);
  padding-inline:16px; padding-block:32px 64px; }}
.wrap {{ max-width:1100px; margin:0 auto; display:grid; gap:40px; }}
h1 {{ font-size:1.9rem; line-height:1.2; margin:0 0 8px; text-wrap:balance; }}
h2 {{ font-size:1.35rem; line-height:1.3; margin:0; text-wrap:balance; }}
h4 {{ font-size:1rem; margin:0; display:flex; flex-wrap:wrap; gap:8px; align-items:baseline; }}
.lede {{ color:var(--muted); max-width:70ch; margin:0; }}
.mono {{ font-family:var(--mono); }}
a {{ color:var(--accent); }}
a:focus-visible, summary:focus-visible {{ outline:2px solid var(--accent); outline-offset:2px; }}
.toc {{ list-style:none; padding:0; margin:20px 0 0;
  display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:4px 20px; }}
.toc a {{ display:flex; gap:9px; align-items:center; text-decoration:none; color:var(--ink);
  padding:7px 0; border-bottom:1px solid var(--line); }}
.toc a:hover {{ color:var(--accent); }}
.cnt {{ margin-left:auto; font-family:var(--mono); font-size:.8rem; color:var(--muted);
  font-variant-numeric:tabular-nums; }}
.sw {{ width:13px; height:13px; border-radius:3px; border:1.5px solid; display:inline-block;
  flex:none; }}
.keybox {{ display:flex; flex-wrap:wrap; gap:8px 20px; margin-top:16px; color:var(--muted);
  font-size:.85rem; }}
.role {{ background:var(--surface); border:1px solid var(--line); border-radius:10px;
  padding:24px 20px; display:grid; gap:20px; scroll-margin-top:16px; }}
.rh {{ display:grid; gap:8px; }}
.eyebrow {{ margin:0; font-size:.75rem; letter-spacing:.06em; text-transform:uppercase;
  color:var(--accent); font-weight:600; display:flex; align-items:center; gap:8px; }}
.eyebrow .mono {{ text-transform:none; letter-spacing:0; }}
.intro {{ margin:0; color:var(--muted); max-width:80ch; }}
.meta {{ margin:0; font-size:.85rem; color:var(--muted); }}
.meta b {{ color:var(--ink); }}
.screens {{ list-style:none; padding:0; margin:0; display:flex; flex-wrap:wrap; gap:5px 8px;
  font-size:.78rem; }}
.screens li {{ background:var(--bg); border:1px solid var(--line); border-radius:5px;
  padding:2px 7px; color:var(--muted); }}
.alur {{ display:grid; gap:10px; border-top:1px solid var(--line); padding-top:16px; }}
.num {{ font-family:var(--mono); color:var(--accent); font-weight:500; }}
.tag {{ font-size:.72rem; color:var(--muted); border:1px solid var(--line); border-radius:4px;
  padding:1px 6px; }}
.tag.warn {{ color:var(--warn); border-color:var(--warn); text-transform:none; }}
.diagram {{ overflow-x:auto; background:var(--bg); border:1px solid var(--line);
  border-radius:8px; padding:12px; }}
.diagram pre {{ margin:0; }}
/* SVG lebar (sequence) harus memakai ukuran aslinya lalu digeser di dalam .diagram;
   sebagai anak flex ia ikut menyusut sampai teksnya tidak terbaca. */
.diagram pre svg {{ display:block; margin:0 auto; max-width:none; }}
.note {{ margin:0; font-size:.9rem; color:var(--muted); max-width:85ch; }}
.note b {{ color:var(--ink); }}
.cfglist {{ margin:0; padding-left:1.4em; display:grid; gap:6px; font-size:.9rem; }}
.src {{ font-size:.72rem; color:var(--muted); }}
details summary {{ cursor:pointer; color:var(--accent); font-weight:500; font-size:.9rem; }}
.tw {{ overflow-x:auto; margin-top:10px; }}
table {{ border-collapse:collapse; width:100%; font-size:.85rem; }}
th, td {{ text-align:left; vertical-align:top; padding:6px 8px; border-bottom:1px solid var(--line); }}
th {{ color:var(--muted); font-weight:600; }}
td.k {{ white-space:nowrap; color:var(--muted); }}
td.ref {{ font-family:var(--mono); font-size:.76rem; color:var(--muted); }}
.top {{ margin:0; font-size:.85rem; }}
.who {{ display:inline-block; margin-top:4px; font-size:.72rem; padding:1px 7px; border-radius:4px;
  border:1.5px solid; color:#1b2430; }}
table.story td {{ font-size:.82rem; }}
table.story td.outp {{ background:color-mix(in srgb, var(--accent) 7%, transparent); }}
table.story .num {{ font-size:1rem; }}
table.mx {{ font-size:.8rem; }}
table.mx th.area {{ text-align:left; white-space:nowrap; font-weight:500; color:var(--ink); }}
table.mx th.rot {{ vertical-align:bottom; height:110px; padding:0 2px; }}
table.mx th.rot span {{ display:block; writing-mode:vertical-rl; transform:rotate(180deg);
  white-space:nowrap; font-weight:500; }}
table.mx td.lv {{ text-align:center; font-size:1.05rem; }}
td.lv-none {{ color:var(--line); }}
td.lv-view {{ color:var(--muted); }}
td.lv-appr, td.lv-full {{ color:var(--warn); }}
td.lv-work {{ color:var(--accent); }}
@media print {{
  body {{ background:#fff; color:#000; padding:0; }}
  .wrap {{ max-width:none; gap:0; }}
  .role {{ break-after:page; border:0; padding:0 0 12px; }}
  .toc, .top, .keybox {{ display:none; }}
  details {{ display:none; }}
  .diagram {{ border:0; padding:0; }}
}}
</style>
</head>
<body>
<div class="wrap">
  <header id="daftar">
    <h1>Alur Aktivitas per Peran</h1>
    <p class="lede">Apa yang dikerjakan setiap pengguna, diambil dari {nflows} alur proses
      bisnis to-be lalu diputar per peran. Sumber data sama dengan
      <span class="mono">bpmn-*.drawio</span> dan <span class="mono">07-proses-bisnis.md</span>,
      yaitu <span class="mono">docs/diagram/_generate.py</span> — tidak ada langkah yang diketik
      ulang. Dipakai untuk panduan uji manual, penjelasan ke calon pengguna, dan bahan presentasi.</p>
    <div class="keybox"><span>▭ langkah peran ini</span><span>◇ keputusan</span>
      <span>⬭ mulai / selesai / serah-terima</span><span>abu-abu = langkah Sistem</span>
      <span>“dari:” / “ke:” = serah-terima ke peran lain</span>
      <span>- - → kembali ke langkah sebelumnya</span></div>
    <p class="lede"><b>{nroles} peran.</b> Pemetaan lane alur ke peran memakai asumsi
      <span class="mono">A-226</span> (Perlu validasi); nama &amp; kode peran mengikuti glosarium
      dan Blueprint §4.2. Satu lane bisa memetakan ke beberapa peran, jadi satu langkah dapat
      muncul di lebih dari satu diagram.</p>
    <p class="lede"><b>Urutan baca yang disarankan:</b>
      <a href="#cerita">1. Alur cerita</a> (apa yang terjadi dari awal sampai akhir, beserta input
      dan outputnya) → <a href="#sequence">2. Sequence</a> (siapa mengirim apa ke siapa, urut
      waktu) → <a href="#akses">3. Matriks hak akses</a> (siapa boleh membuka apa) →
      4. halaman peran di bawah (langkah rinci tiap orang).</p>
    <ol class="toc">{toc}</ol>
  </header>
  {story}
  {sequence}
  {matrix}
  {sections}
  <p class="lede">Berkas ini dibuat otomatis — jangan diedit manual. Buat ulang dengan
    <span class="mono">py -3 docs/diagram/_generate_peran_html.py</span>. {srcnote}</p>
</div>
<script src="{src}"></script>
<script>
  var dark = document.documentElement.getAttribute("data-theme") === "dark" ||
    (document.documentElement.getAttribute("data-theme") !== "light" &&
     window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches);
  mermaid.initialize({{ startOnLoad: true, theme: dark ? "dark" : "default",
    flowchart: {{ useMaxWidth: false }},
    sequence: {{ useMaxWidth: false, wrap: false, actorFontSize: 13, noteFontSize: 12,
                 messageFontSize: 12, boxMargin: 8 }} }});
</script>
</body>
</html>
'''

if __name__ == "__main__":
    main()
