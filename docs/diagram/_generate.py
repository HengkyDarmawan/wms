# -*- coding: utf-8 -*-
"""
Generator diagram proses bisnis WMS (Part 2).

Satu sumber data (FLOWS) menghasilkan:
  - docs/diagram/bpmn-<nn>-<slug>.drawio   (BPMN, bisa dibuka di draw.io / diagrams.net)
  - docs/wms/07-proses-bisnis.md            (alur 1-3)
  - docs/wms/07a-proses-bisnis-lanjutan.md  (alur 4-7)
  - docs/wms/07b-proses-bisnis-pendukung.md (alur 8-10)

Jalankan:  py -3 docs/diagram/_generate.py   (dari root proyek)

Bila asumsi A-xx berubah: ubah data alur di bawah, jalankan ulang. Jangan mengedit
file keluaran secara manual; perubahan akan tertimpa.

Format node:  (id, lane, kind, label, ref)
  kind: start | end | task | gw (gateway XOR) | timer (start berbasis waktu)
  ref : status/efek/BR yang dicatat di tabel langkah (tidak digambar)
Format edge:  (from, to, label, back)  back=True untuk panah balik (loop)
"""
import os, re, html

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DIAG = os.path.join(ROOT, "docs", "diagram")
WMS = os.path.join(ROOT, "docs", "wms")

# ---------------------------------------------------------------------------
# DATA ALUR
# ---------------------------------------------------------------------------
FLOWS = [
dict(
    n=1, slug="permintaan-sampai-terima", title="Permintaan material sampai bukti terima",
    lanes=["Pemohon (Internal / Klien)", "Staf Gudang", "Kepala Gudang", "Approver", "Driver / Penerima", "Sistem"],
    intro="Alur inti outbound: dari kebutuhan material di proyek sampai barang diterima di tujuan dan REQ selesai. "
          "Mencakup tinjauan staf untuk permintaan klien, reservasi dua tahap, picking dengan short pick, pengiriman, "
          "bukti terima, dan selisih pengiriman.",
    assumptions=["A-07", "A-30", "A-31", "A-33", "A-35", "A-39", "A-41"],
    br=["BR-REQ-01..10", "BR-STK-03..05", "BR-SJ-01..08", "BR-GEN-06"],
    status=["REQ", "PCK", "SJ", "DSC"],
    nodes=[
        ("s", "Pemohon (Internal / Klien)", "start", "Kebutuhan material di proyek", ""),
        ("t1", "Pemohon (Internal / Klien)", "task", "Buat & ajukan REQ (baris katalog / non-katalog, tanggal dibutuhkan)", "REQ draft → submitted; BR-REQ-01"),
        ("g1", "Sistem", "gw", "Pemohon klien?", "A-07"),
        ("t2", "Staf Gudang", "task", "Tinjau: petakan non-katalog, tetapkan gudang sumber, ubah baris bila perlu", "REQ under_review; BR-REQ-02..04; A-39, A-31"),
        ("t3", "Sistem", "task", "Snapshot aturan approval; lewati lapis pengaju (SoD)", "REQ pending_approval; BR-APR-01, BR-APR-03"),
        ("g2", "Sistem", "gw", "Ada lapis approval?", "A-08"),
        ("t4", "Approver", "task", "Setujui / tolak (alasan) — lihat alur 9", "BR-APR"),
        ("g3", "Sistem", "gw", "Disetujui?", ""),
        ("e_rej", "Pemohon (Internal / Klien)", "end", "REQ ditolak", "REQ rejected; notifikasi pemohon"),
        ("t5", "Sistem", "task", "Reservasi lunak per gudang; kekurangan → TRF (alur 5) / PRQ (alur 2)", "REQ approved; BR-REQ-05; BR-STK-04; A-30"),
        ("t6", "Sistem", "task", "Buat PCK per gudang sumber; alokasi keras bin/lot/serial/potongan", "PCK pending; REQ in_progress; BR-STK-04"),
        ("t7", "Staf Gudang", "task", "Picking: scan bin & item → Loading Area; short pick + alasan", "PCK in_progress → completed; BR-SJ-01, BR-SJ-02"),
        ("t8", "Staf Gudang", "task", "Buat SJ: tujuan, kendaraan / ekspedisi", "SJ prepared; BR-SJ-07"),
        ("t9", "Driver / Penerima", "task", "Konfirmasi muat & berangkat", "SJ shipped; ledger → in_transit; goods_shipped"),
        ("t10", "Driver / Penerima", "task", "Bukti terima: foto, tanda tangan, jumlah per baris (driver atau tautan bertoken + OTP)", "BR-SJ-05; A-41"),
        ("g4", "Sistem", "gw", "Jumlah diterima = dikirim?", ""),
        ("t11", "Sistem", "task", "Buat DSC (selisih tetap di in_transit)", "SJ partially_delivered; DSC open; BR-SJ-06"),
        ("t12", "Kepala Gudang", "task", "Selesaikan DSC: disposisi kembali / disesuaikan / klaim", "DSC resolved; delivery_discrepancy"),
        ("t13", "Sistem", "task", "Efek stok per tujuan: jual putus keluar (goods_delivered), aset → on_site (asset_checked_out), gudang → GRN tujuan", "SJ delivered; BR-SJ-04; A-25"),
        ("t14", "Pemohon (Internal / Klien)", "task", "Konfirmasi terima (otomatis setelah 3 hari)", "BR-REQ-10"),
        ("g5", "Sistem", "gw", "Semua baris terpenuhi?", ""),
        ("t15", "Sistem", "task", "Tunggu backorder (TRF / PRQ tiba → reservasi otomatis, cross-dock)", "REQ partially_fulfilled; BR-REQ-08"),
        ("e", "Sistem", "end", "REQ selesai", "REQ completed; reservasi = 0"),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "g1", "", False), ("g1", "t2", "ya", False), ("g1", "t3", "tidak", False),
        ("t2", "t3", "", False), ("t3", "g2", "", False), ("g2", "t4", "ya", False), ("g2", "t5", "tidak → otomatis disetujui", False),
        ("t4", "g3", "", False), ("g3", "e_rej", "tidak", False), ("g3", "t5", "ya", False),
        ("t5", "t6", "", False), ("t6", "t7", "", False), ("t7", "t8", "", False), ("t8", "t9", "", False), ("t9", "t10", "", False),
        ("t10", "g4", "", False), ("g4", "t11", "tidak", False), ("t11", "t12", "", False), ("t12", "t13", "", False), ("g4", "t13", "ya", False),
        ("t13", "t14", "", False), ("t14", "g5", "", False), ("g5", "e", "ya", False), ("g5", "t15", "tidak", False), ("t15", "t6", "barang tiba", True),
    ],
    gateways_note="Pembatalan: REQ bisa dibatalkan sampai sebelum ada SJ `shipped` (reservasi & PCK dilepas). `closed_short` dari `partially_fulfilled` oleh Kepala Gudang/pemohon melepas sisa backorder.",
),
dict(
    n=2, slug="penerimaan-qc-putaway-rtv", title="Penerimaan dari vendor, QC, put-away, retur ke vendor",
    lanes=["Penindak Lanjut PR", "Staf Gudang", "Approver", "Sistem"],
    intro="Alur inti inbound: barang dari vendor (dengan atau tanpa PRQ) diterima, diposting ke bin Penerimaan/Karantina, "
          "diperiksa (QC sebagai langkah), lalu di-put-away atau cross-dock. Baris yang ditolak QC diproses lewat RTV.",
    assumptions=["A-34", "A-47"],
    br=["BR-GRN-01..05", "BR-REQ-08", "BR-SJ-03"],
    status=["PRQ", "GRN", "PUT", "RTV"],
    nodes=[
        ("s", "Staf Gudang", "start", "Barang vendor tiba", ""),
        ("t1", "Staf Gudang", "task", "Buat GRN; pilih baris PRQ yang dipenuhi (bila ada)", "GRN draft; A-47"),
        ("t2", "Staf Gudang", "task", "Hitung; isi lot / serial / potongan; Diterima", "GRN received; BR-GRN-01"),
        ("t3", "Sistem", "task", "Ledger → bin Penerimaan (atau Karantina bila wajib QC); goods_received; PRQ terpenuhi; reservasi ke REQ penunggu", "BR-GRN-01; BR-REQ-08"),
        ("g1", "Sistem", "gw", "QC wajib?", "pengaturan item / company"),
        ("t4", "Staf Gudang", "task", "QC per baris: lolos / karantina / ditolak", "qc_result; BR-GRN-02"),
        ("g2", "Sistem", "gw", "Hasil QC?", ""),
        ("t5", "Staf Gudang", "task", "Tahan di Karantina; putuskan ulang", "stock_status quarantine"),
        ("g3", "Sistem", "gw", "Ditunggu REQ (backorder)?", "BR-SJ-03"),
        ("t6", "Staf Gudang", "task", "Cross-dock: pindah ke Loading Area (lanjut alur 1)", "GRN completed; tanpa PUT"),
        ("t7", "Sistem", "task", "Buat PUT; saran bin (kategori penyimpanan, kapasitas)", "GRN completed; PUT pending; BR-GRN-03"),
        ("t8", "Staf Gudang", "task", "Put-away: scan bin tujuan", "PUT completed; ledger Penerimaan → bin"),
        ("e1", "Sistem", "end", "Stok tersedia", ""),
        ("t9", "Staf Gudang", "task", "Buat RTV dari baris ditolak (Karantina)", "RTV submitted; BR-GRN-04"),
        ("t10", "Approver", "task", "Setujui / tolak RTV", "RTV approved / rejected"),
        ("t11", "Staf Gudang", "task", "Kirim ke vendor (surat jalan retur)", "RTV shipped; ledger Karantina → keluar; goods_rejected"),
        ("t12", "Penindak Lanjut PR", "task", "Konfirmasi vendor; barang pengganti → GRN baru merujuk RTV", "RTV completed"),
        ("e2", "Penindak Lanjut PR", "end", "RTV selesai", ""),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "t2", "", False), ("t2", "t3", "", False), ("t3", "g1", "", False),
        ("g1", "t4", "ya", False), ("g1", "g3", "tidak", False), ("t4", "g2", "", False),
        ("g2", "g3", "lolos", False), ("g2", "t5", "karantina", False), ("t5", "t4", "periksa ulang", True), ("g2", "t9", "ditolak", False),
        ("g3", "t6", "ya", False), ("g3", "t7", "tidak", False), ("t7", "t8", "", False), ("t8", "e1", "", False), ("t6", "e1", "", False),
        ("t9", "t10", "", False), ("t10", "t11", "disetujui", False), ("t11", "t12", "", False), ("t12", "e2", "", False),
    ],
    gateways_note="GRN `received` tidak bisa dibatalkan; koreksi jumlah lewat ADJ. Kelebihan terima dibanding SJ/PRQ dicatat sebagai baris tanpa rujukan dan memicu ADJ (BR-GRN-05). GRN dari SJ (transfer) dan dari RET mengikuti alur 5 dan 4.",
),
dict(
    n=3, slug="pemakaian-material-site", title="Pemakaian material di Gudang Site",
    lanes=["Staf Gudang Site / Pemohon Internal", "Approver", "Sistem"],
    intro="Alur baru (A-32): barang habis pakai yang berada di Gudang Site keluar dari stok company saat dipakai proyek, "
          "sehingga beban material proyek tercatat dan laporan Material per Proyek punya kolom Terpakai.",
    assumptions=["A-32"],
    br=["BR-PRJ-08", "BR-GEN-03", "BR-GEN-04"],
    status=["ISU"],
    nodes=[
        ("s", "Staf Gudang Site / Pemohon Internal", "start", "Material dipakai di proyek", ""),
        ("t1", "Staf Gudang Site / Pemohon Internal", "task", "Buat ISU: proyek, item habis pakai, jumlah, (foto)", "ISU draft; BR-PRJ-08"),
        ("g1", "Sistem", "gw", "Stok tersedia di Gudang Site cukup & bin tidak dibeku?", "BR-STK-06; BR-OPN-02"),
        ("t2", "Staf Gudang Site / Pemohon Internal", "task", "Ajukan REQ untuk kekurangan (alur 1)", ""),
        ("t3", "Staf Gudang Site / Pemohon Internal", "task", "Konfirmasi pemakaian", "ISU confirmed"),
        ("t4", "Sistem", "task", "Ledger: bin Gudang Site → keluar (dipakai proyek); material_consumed → Akuntansi; kolom Terpakai", "BR §14"),
        ("e", "Sistem", "end", "Beban proyek tercatat", ""),
        ("g2", "Staf Gudang Site / Pemohon Internal", "gw", "Salah input setelah konfirmasi?", ""),
        ("t5", "Staf Gudang Site / Pemohon Internal", "task", "Buat ISU pembalik (jumlah negatif, alasan)", "reversal_of_id; BR-GEN-03"),
        ("t6", "Approver", "task", "Setujui ISU pembalik", "BR-GEN-04"),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "g1", "", False), ("g1", "t2", "tidak", False), ("g1", "t3", "ya", False),
        ("t3", "t4", "", False), ("t4", "g2", "", False), ("g2", "e", "tidak", False), ("g2", "t5", "ya", False), ("t5", "t6", "", False), ("t6", "t4", "kejadian pembalik", True),
    ],
    gateways_note="ISU hanya untuk item habis pakai; aset tidak pernah 'dipakai habis' (lihat alur 7). ISU tidak memakai SJ karena tidak ada pergerakan antar lokasi.",
),
dict(
    n=4, slug="retur-dan-pemilahan", title="Retur dari proyek dan pemilahan",
    lanes=["Pemohon / Klien", "Approver", "Driver", "Staf Gudang", "Sistem"],
    intro="Barang kembali dari proyek atau klien: sisa material, barang jual-putus yang tidak terpakai (A-26), atau aset yang selesai dipinjam. "
          "RET adalah dokumen niat; pergerakan fisik lewat SJ balik (opsional) dan GRN jenis retur, lalu dipilah.",
    assumptions=["A-26", "A-33", "A-36"],
    br=["BR-RET-01..05", "BR-AST-03"],
    status=["RET", "SJ", "GRN", "AST"],
    nodes=[
        ("s", "Pemohon / Klien", "start", "Ada barang / aset yang harus kembali", ""),
        ("t1", "Pemohon / Klien", "task", "Buat RET merujuk SJ asal atau proyek; baris & alasan", "RET submitted; BR-RET-03, BR-RET-05"),
        ("g1", "Sistem", "gw", "Ada lapis approval?", ""),
        ("t2", "Approver", "task", "Setujui / tolak (alur 9)", "RET approved / rejected"),
        ("g2", "Sistem", "gw", "Diantar sendiri ke gudang?", ""),
        ("t3", "Driver", "task", "Ambil di site: SJ balik, muat, kirim", "SJ prepared → shipped; RET in_progress"),
        ("t4", "Staf Gudang", "task", "GRN jenis retur: hitung, Diterima", "GRN received; RET received; ledger → bin Retur"),
        ("t5", "Staf Gudang", "task", "Pilah per baris: layak / rusak / offcut / waste; aset: pemeriksaan grade + foto", "return_sorting; BR-RET-04; BR-AST-03"),
        ("t6", "Sistem", "task", "Ledger bin Retur → bin tujuan; offcut → ID potongan + silsilah; goods_returned (ownership sold / company); aset: asset_returned, usage_days", "RET sorted; AST inspected; BR §14"),
        ("g3", "Sistem", "gw", "Hasil pilah?", ""),
        ("t7", "Staf Gudang", "task", "Put-away barang layak (PUT)", "PUT"),
        ("t8", "Sistem", "task", "Rusak → kondisi damaged; waste → bin Waste (alur 6, WST); aset C/D → maintenance / damaged", ""),
        ("e", "Sistem", "end", "Retur selesai", ""),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "g1", "", False), ("g1", "t2", "ya", False), ("g1", "g2", "tidak", False), ("t2", "g2", "disetujui", False),
        ("g2", "t3", "tidak", False), ("g2", "t4", "ya", False), ("t3", "t4", "", False), ("t4", "t5", "", False), ("t5", "t6", "", False),
        ("t6", "g3", "", False), ("g3", "t7", "layak / offcut", False), ("g3", "t8", "rusak / waste / aset C-D", False), ("t7", "e", "", False), ("t8", "e", "", False),
    ],
    gateways_note="Klien hanya bisa mengajukan RET untuk barang berstatus *Terkirim ke Klien* atau aset `on_loan` di proyeknya (BR-RET-05). Baris jual-putus yang kembali menghasilkan `goods_returned` dengan `ownership = sold` (retur penjualan di Akuntansi).",
),
dict(
    n=5, slug="transfer-antar-gudang-proyek", title="Transfer antar gudang dan antar proyek",
    lanes=["Pengaju (Staf / Kepala Gudang)", "Approver", "Staf Gudang Asal", "Driver", "Staf Gudang Tujuan", "Sistem"],
    intro="TRF dibuat manual atau otomatis dari backorder REQ. Sebagai dokumen niat, TRF memakai jalur fisik yang sama dengan pengiriman: "
          "PCK di gudang asal, SJ, GRN di gudang tujuan, PUT. Transfer antar proyek = transfer antar Gudang Site (atau bin on_site untuk aset).",
    assumptions=["A-30", "A-33", "A-43"],
    br=["BR-RET-01", "BR-RET-02", "BR-STK-13", "BR-GEN-06"],
    status=["TRF", "PCK", "SJ", "GRN", "PUT"],
    nodes=[
        ("s", "Pengaju (Staf / Kepala Gudang)", "start", "Kebutuhan pindah stok (manual / backorder REQ)", ""),
        ("t1", "Pengaju (Staf / Kepala Gudang)", "task", "Buat TRF: gudang/proyek asal & tujuan, baris", "TRF submitted; nomor memakai gudang asal (A-43)"),
        ("g1", "Sistem", "gw", "Ada lapis approval?", ""),
        ("t2", "Approver", "task", "Setujui / tolak (alur 9)", "TRF approved / rejected"),
        ("t3", "Sistem", "task", "Reservasi lunak di gudang asal; buat PCK", "TRF approved → in_progress; PCK pending; BR-STK-04"),
        ("t4", "Staf Gudang Asal", "task", "Picking → Loading Area; buat SJ tujuan gudang", "PCK completed; SJ prepared"),
        ("t5", "Driver", "task", "Muat & kirim", "SJ shipped; ledger → in_transit (milik gudang asal); goods_shipped; BR-STK-13"),
        ("t6", "Staf Gudang Tujuan", "task", "GRN dari SJ: hitung, Diterima", "GRN received; SJ delivered; stock_transferred"),
        ("g2", "Sistem", "gw", "Jumlah = dikirim?", ""),
        ("t7", "Sistem", "task", "DSC untuk selisih (alur 1)", "DSC open"),
        ("t8", "Staf Gudang Tujuan", "task", "Put-away", "PUT completed"),
        ("t9", "Sistem", "task", "TRF selesai; bila dari backorder: reservasi ke REQ penunggu, saran cross-dock", "TRF completed; BR-REQ-08"),
        ("e", "Sistem", "end", "Stok di gudang tujuan", ""),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "g1", "", False), ("g1", "t2", "ya", False), ("g1", "t3", "tidak", False), ("t2", "t3", "disetujui", False),
        ("t3", "t4", "", False), ("t4", "t5", "", False), ("t5", "t6", "", False), ("t6", "g2", "", False), ("g2", "t7", "tidak", False), ("t7", "t8", "", False),
        ("g2", "t8", "ya", False), ("t8", "t9", "", False), ("t9", "e", "", False),
    ],
    gateways_note="TRF bisa dibatalkan sampai sebelum SJ `shipped`; setelah itu koreksi lewat DSC atau TRF balik. Selama `in_transit`, stok masih dihitung milik gudang asal di laporan saldo.",
),
dict(
    n=6, slug="konversi-dan-waste", title="Konversi material, offcut, dan berita acara waste",
    lanes=["Staf Gudang", "Approver", "Sistem"],
    intro="Pembeda utama produk: pipa/lembar dipotong menjadi item lain; sisa menjadi offcut (kembali ke stok dengan ID baru dan silsilah) "
          "atau waste. Waste ditutup lewat Berita Acara Waste dengan disposisi.",
    assumptions=["A-06", "A-19", "A-36"],
    br=["BR-CNV-01..05", "BR-GEN-04"],
    status=["CNV", "WST"],
    nodes=[
        ("s", "Staf Gudang", "start", "Perlu potong / rakit / bongkar / ganti kemasan", ""),
        ("t1", "Staf Gudang", "task", "Buat CNV: proyek (atau Proyek Internal), input potongan / lot", "CNV draft; BR-CNV-01"),
        ("t2", "Staf Gudang", "task", "Isi output, offcut (≥ minimum), waste, kerf", "BR-CNV-03; A-19"),
        ("g1", "Sistem", "gw", "Neraca ukuran seimbang?", "BR-CNV-02"),
        ("g2", "Sistem", "gw", "Ada lapis approval?", ""),
        ("t3", "Approver", "task", "Setujui / tolak", "CNV pending_approval"),
        ("t4", "Sistem", "task", "Ledger: input keluar; output & offcut masuk (ID potongan, silsilah); waste → bin Waste; material_converted", "CNV completed; BR-CNV-04"),
        ("e1", "Sistem", "end", "Konversi selesai", ""),
        ("s2", "Staf Gudang", "timer", "Bin Waste perlu ditutup (berkala)", ""),
        ("t5", "Staf Gudang", "task", "Buat WST: baris dari bin Waste, disposisi dibuang / scrap / dipakai ulang", "WST submitted"),
        ("t6", "Approver", "task", "Setujui / tolak WST", "WST approved"),
        ("t7", "Staf Gudang", "task", "Tutup WST dengan bukti (foto / berita acara)", "WST closed"),
        ("t8", "Sistem", "task", "Ledger bin Waste → keluar (atau kembali ke stok bila dipakai ulang); waste_disposed", "BR §14"),
        ("e2", "Sistem", "end", "Waste terdisposisi", ""),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "t2", "", False), ("t2", "g1", "", False), ("g1", "t2", "tidak → perbaiki", True), ("g1", "g2", "ya", False),
        ("g2", "t3", "ya", False), ("g2", "t4", "tidak", False), ("t3", "t4", "disetujui", False), ("t4", "e1", "", False),
        ("s2", "t5", "", False), ("t5", "t6", "", False), ("t6", "t7", "disetujui", False), ("t7", "t8", "", False), ("t8", "e2", "", False),
    ],
    gateways_note="CNV `completed` hanya bisa dibalik bila semua output & offcut masih di bin dan belum dipakai (BR-CNV-05). Resep konversi (F2) hanya mempercepat pengisian, tidak mengunci hasil nyata.",
),
dict(
    n=7, slug="aset-dipinjamkan", title="Aset dipinjamkan: keluar, jatuh tempo, kembali, hilang",
    lanes=["Pemohon / PIC Proyek", "Staf Gudang", "Driver", "Approver", "Sistem"],
    intro="Siklus aset ber-serial: keluar bersama pengiriman (alur 1), berada di bin virtual On-site Proyek, diingatkan saat lewat jatuh tempo, "
          "kembali lewat retur (alur 4) dengan pemeriksaan, atau ditandai hilang dan dihapuskan lewat ADJ.",
    assumptions=["A-29", "A-38"],
    br=["BR-AST-01..07", "BR-STK-08", "BR-STK-14"],
    status=["AST", "ADJ"],
    nodes=[
        ("s", "Pemohon / PIC Proyek", "start", "Baris REQ 'Pinjam' disetujui (alur 1)", "line_ownership = loan; A-38"),
        ("t1", "Staf Gudang", "task", "Picking serial aset; catat kondisi & foto keluar; tanggal kembali", "AST; due_return_date; BR-STK-08"),
        ("t2", "Driver", "task", "Kirim & bukti terima (alur 1)", "SJ delivered"),
        ("t3", "Sistem", "task", "Aset → bin On-site Proyek; state on_loan; asset_checked_out", "AST checked_out; BR-AST-01"),
        ("g1", "Sistem", "timer", "Cek harian: lewat jatuh tempo?", "BR-AST-06"),
        ("t4", "Sistem", "task", "Notifikasi PIC proyek & Kepala Gudang; laporan aset terlambat", ""),
        ("g2", "Pemohon / PIC Proyek", "gw", "Aset masih ada?", ""),
        ("t5", "Pemohon / PIC Proyek", "task", "Ajukan RET aset (alur 4)", "RET"),
        ("t6", "Staf Gudang", "task", "Pemeriksaan: grade A–D + foto + catatan", "AST returned → inspected; BR-AST-03"),
        ("g3", "Sistem", "gw", "Grade?", ""),
        ("t7", "Sistem", "task", "State available; asset_returned dengan usage_days", "BR-AST-05"),
        ("t8", "Sistem", "task", "State maintenance / damaged; asset_lost_or_damaged → Akuntansi (ganti rugi)", "BR-AST-04"),
        ("t9", "Staf Gudang", "task", "Tandai hilang (alasan) → buat ADJ keluar", "asset_state lost; asset_lost_or_damaged; ADJ submitted"),
        ("t10", "Approver", "task", "Setujui ADJ", "ADJ approved → posted; stock_adjusted"),
        ("e1", "Sistem", "end", "Aset tersedia kembali", ""),
        ("e2", "Sistem", "end", "Aset di maintenance / rusak", ""),
        ("e3", "Sistem", "end", "Aset dihapuskan", "asset_state written_off"),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "t2", "", False), ("t2", "t3", "", False), ("t3", "g1", "", False), ("g1", "t4", "ya", False), ("g1", "g2", "proyek selesai / diminta kembali", False),
        ("t4", "g2", "", False), ("g2", "t5", "ya", False), ("g2", "t9", "tidak (hilang)", False), ("t5", "t6", "", False), ("t6", "g3", "", False),
        ("g3", "t7", "A / B", False), ("g3", "t8", "C / D", False), ("t7", "e1", "", False), ("t8", "e2", "", False), ("t9", "t10", "", False), ("t10", "e3", "", False),
    ],
    gateways_note="Peminjaman ke orang (bukan proyek klien) wajib memakai Proyek Internal agar bin on_site selalu punya proyek (BR-AST-02). Aset dalam maintenance tidak bisa dicadangkan (F2).",
),
dict(
    n=8, slug="stock-opname", title="Stock opname: sesi, hitung buta, hitung ulang, rekonsiliasi",
    lanes=["Kepala Gudang / Auditor", "Penghitung (Staf)", "Approver", "Sistem"],
    intro="Sesi opname bulanan/tahunan/ad-hoc dengan pembekuan bin opsional, hitung buta, klasifikasi selisih berdasarkan ambang relatif dan absolut, "
          "hitung ulang oleh orang berbeda, dan rekonsiliasi yang menghasilkan satu ADJ per gudang yang disetujui di tingkat sesi.",
    assumptions=["A-09", "A-42", "A-46"],
    br=["BR-OPN-01..08"],
    status=["OPN", "ADJ"],
    nodes=[
        ("s", "Kepala Gudang / Auditor", "start", "Jadwal opname / permintaan audit", ""),
        ("t1", "Kepala Gudang / Auditor", "task", "Rencanakan sesi: jenis, cakupan (gudang/zona/bin), tim, pembekuan ya/tidak", "OPN planned"),
        ("g1", "Sistem", "gw", "Ada PCK berjalan di cakupan (bila pembekuan)?", "BR-OPN-02"),
        ("t2", "Kepala Gudang / Auditor", "task", "Selesaikan atau batalkan PCK dulu", ""),
        ("t3", "Sistem", "task", "Bekukan bin; snapshot saldo fisik (termasuk dicadangkan & Loading Area)", "OPN in_progress; bin frozen; BR-OPN-01"),
        ("t4", "Penghitung (Staf)", "task", "Hitung buta per bin di PWA (scan bin, input jumlah; draf lokal bila offline)", "count_assignment; blind_count"),
        ("t5", "Sistem", "task", "Klasifikasi selisih per baris: kecil / sedang / besar (ambang relatif & absolut)", "variance_class; BR-OPN-04"),
        ("g2", "Sistem", "gw", "Kelas selisih?", ""),
        ("t6", "Penghitung (Staf)", "task", "Hitung ulang oleh penghitung berbeda", "OPN recount; BR-OPN-05"),
        ("t7", "Kepala Gudang / Auditor", "task", "Isi kategori akar masalah untuk selisih besar", "root_cause_category; BR-OPN-07"),
        ("t8", "Kepala Gudang / Auditor", "task", "Rekonsiliasi: tinjau semua baris; draf ADJ per gudang", "OPN reconciling; ADJ submitted"),
        ("t9", "Approver", "task", "Setujui sesi (persetujuan di tingkat sesi, bukan per ADJ)", "OPN approved; A-09; BR-OPN-06"),
        ("t10", "Sistem", "task", "ADJ posted; stock_adjusted; buka bin; laporan PDF; dashboard akurasi", "OPN closed"),
        ("e", "Sistem", "end", "Sesi ditutup", ""),
        ("g3", "Kepala Gudang / Auditor", "gw", "SJ mendesak dari bin beku?", ""),
        ("t11", "Kepala Gudang / Auditor", "task", "Override dengan alasan; bin ditandai hitung ulang", "BR-OPN-02"),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "g1", "", False), ("g1", "t2", "ya", False), ("t2", "g1", "", True), ("g1", "t3", "tidak", False),
        ("t3", "t4", "", False), ("t4", "t5", "", False), ("t5", "g2", "", False), ("g2", "t6", "sedang", False), ("t6", "t5", "", True),
        ("g2", "t7", "besar", False), ("g2", "t8", "kecil", False), ("t7", "t8", "", False), ("t8", "t9", "", False), ("t9", "t10", "disetujui", False), ("t10", "e", "", False),
        ("t3", "g3", "selama sesi", False), ("g3", "t11", "ya", False), ("t11", "t4", "", True),
    ],
    gateways_note="Transaksi PWA offline yang tiba saat bin beku masuk antrean tinjauan (F2, BR-OPN-03). Auditor eksternal (F2) memakai akun tamu berbatas gudang & periode.",
),
dict(
    n=9, slug="approval-generik", title="Approval generik (semua jenis dokumen)",
    lanes=["Pengaju", "Sistem", "Approver", "Delegat / Cadangan / Atasan"],
    intro="Mesin approval yang dipakai REQ, TRF, RET, CNV, ADJ, WST, PRQ, RTV, dan OPN. Aturan di-snapshot saat diajukan, "
          "lapis diproses sesuai cara putus, dengan delegasi, eskalasi, dan pemisahan tugas.",
    assumptions=["A-08", "A-09", "A-18", "A-45"],
    br=["BR-APR-01..11"],
    status=["(semua dokumen dengan pending_approval)"],
    nodes=[
        ("s", "Pengaju", "start", "Dokumen diajukan", "submitted"),
        ("t1", "Sistem", "task", "Snapshot aturan yang cocok (jenis, gudang, proyek, kategori, jumlah, klien); lewati lapis yang menunjuk pengaju", "BR-APR-01, BR-APR-03, BR-APR-07"),
        ("g1", "Sistem", "gw", "Ada lapis?", ""),
        ("t2", "Sistem", "task", "Setujui otomatis (kecuali ADJ manual: minimal satu lapis)", "approved; BR-APR-02"),
        ("t3", "Sistem", "task", "Kirim tugas approval lapis n: in-app / email / WhatsApp (F2, token sekali pakai)", "pending_approval; BR-APR-10"),
        ("g2", "Sistem", "gw", "Approver nonaktif atau delegasi aktif?", "BR-APR-05, BR-APR-06"),
        ("t4", "Delegat / Cadangan / Atasan", "task", "Terima tugas sebagai delegat / cadangan", "approval_decision delegated"),
        ("t5", "Approver", "task", "Setujui / tolak (+ alasan); via web atau tombol WA", "BR-APR-09: keputusan pertama menang"),
        ("g3", "Sistem", "timer", "Lewat batas waktu (24 jam kalender)?", "BR-APR-08"),
        ("t6", "Delegat / Cadangan / Atasan", "task", "Eskalasi: cadangan / atasan / Admin Company", "approval_decision escalated"),
        ("g4", "Sistem", "gw", "Keputusan?", ""),
        ("t7", "Sistem", "task", "Catat timeline: pelaku, kanal, nomor WA, message_id", "document_timeline"),
        ("g5", "Sistem", "gw", "Cara putus terpenuhi & masih ada lapis berikutnya?", "berurutan / salah satu / semua"),
        ("e1", "Pengaju", "end", "Dokumen disetujui", "approved"),
        ("e2", "Pengaju", "end", "Dokumen ditolak", "rejected; notifikasi"),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "g1", "", False), ("g1", "t2", "tidak", False), ("t2", "e1", "", False), ("g1", "t3", "ya", False),
        ("t3", "g2", "", False), ("g2", "t4", "ya", False), ("t4", "t5", "", False), ("g2", "t5", "tidak", False), ("t5", "g4", "", False),
        ("t3", "g3", "menunggu", False), ("g3", "t6", "ya", False), ("t6", "t5", "", True),
        ("g4", "e2", "tolak", False), ("g4", "t7", "setuju", False), ("t7", "g5", "", False), ("g5", "t3", "lapis berikutnya", True), ("g5", "e1", "selesai", False),
    ],
    gateways_note="Orang yang sama di dua lapis berurutan cukup menyetujui sekali (BR-APR-04). Simulasi aturan wajib tersedia saat menyusun aturan (BR-APR-11).",
),
dict(
    n=10, slug="langganan-company", title="Siklus langganan company (platform)",
    lanes=["Super Admin", "Admin Company", "Sistem"],
    intro="Dari pembuatan company sampai penghentian: provisioning otomatis, trial, tagihan bulanan dengan pembayaran manual, "
          "tenggang, penangguhan hanya-baca, dan pengakhiran dengan masa ekspor.",
    assumptions=["A-11", "A-12", "A-27", "A-44"],
    br=["BR-SUB-01..04"],
    status=["subscription_status"],
    nodes=[
        ("s", "Super Admin", "start", "Company baru", ""),
        ("t1", "Super Admin", "task", "Buat company: subdomain, paket, durasi trial", ""),
        ("t2", "Sistem", "task", "Provisioning: buat DB, migrasi, data awal, undangan Admin Company", "subscription trial; A-11"),
        ("t3", "Admin Company", "task", "Terima undangan; jalankan wizard setup; setujui kebijakan privasi & ketentuan", "NFR-11"),
        ("g1", "Sistem", "timer", "Trial / periode berakhir?", ""),
        ("t4", "Sistem", "task", "Terbitkan tagihan bulanan; pengingat email / WA", "subscription_invoice"),
        ("t5", "Admin Company", "task", "Unggah bukti transfer", "subscription_payment"),
        ("t6", "Super Admin", "task", "Verifikasi pembayaran", ""),
        ("g2", "Sistem", "gw", "Pembayaran valid sebelum jatuh tempo?", ""),
        ("t7", "Sistem", "task", "Aktif; perpanjang masa aktif", "subscription active"),
        ("t8", "Sistem", "task", "Jatuh tempo: tenggang 7 hari, pengingat", "past_due; BR-SUB-01"),
        ("g3", "Sistem", "timer", "Dibayar dalam tenggang?", ""),
        ("t9", "Sistem", "task", "Ditangguhkan 30 hari: hanya-baca, antrean PWA ditahan, job & token WA berhenti", "suspended; BR-SUB-02; A-44"),
        ("g4", "Sistem", "timer", "Dibayar dalam 30 hari?", ""),
        ("t10", "Sistem", "task", "Diakhiri: 90 hari hanya Admin Company login untuk ekspor", "terminated; BR-SUB-03"),
        ("e", "Sistem", "end", "Data dihapus setelah 90 hari", "A-12"),
    ],
    edges=[
        ("s", "t1", "", False), ("t1", "t2", "", False), ("t2", "t3", "", False), ("t3", "g1", "", False), ("g1", "t4", "ya", False),
        ("t4", "t5", "", False), ("t5", "t6", "", False), ("t6", "g2", "", False), ("g2", "t7", "ya", False), ("t7", "g1", "periode berikutnya", True),
        ("g2", "t8", "tidak", False), ("t8", "g3", "", False), ("g3", "t7", "ya", False), ("g3", "t9", "tidak", False), ("t9", "g4", "", False),
        ("g4", "t7", "ya", False), ("g4", "t10", "tidak", False), ("t10", "e", "", False),
    ],
    gateways_note="Super Admin tidak bisa membuka data operasional company tanpa akses dukungan yang diberikan Admin Company (BR-SUB-04). Payment gateway (F3) menggantikan langkah unggah bukti & verifikasi manual.",
),
]

# ---------------------------------------------------------------------------
# LAYOUT
# ---------------------------------------------------------------------------
COLW, LANEH, X0 = 200, 150, 60
SIZES = {"task": (150, 66), "gw": (56, 56), "start": (42, 42), "end": (42, 42), "timer": (42, 42)}
STYLE = {
    "task": "rounded=1;whiteSpace=wrap;html=1;fontSize=10;strokeColor=#1F5C99;fillColor=#DAE8FC;",
    "gw": "rhombus;whiteSpace=wrap;html=1;fontSize=9;strokeColor=#B8860B;fillColor=#FFF2CC;",
    "start": "ellipse;whiteSpace=wrap;html=1;aspect=fixed;fontSize=8;strokeColor=#2E7D32;fillColor=#D5E8D4;",
    "timer": "ellipse;whiteSpace=wrap;html=1;aspect=fixed;fontSize=8;strokeColor=#2E7D32;fillColor=#D5E8D4;dashed=1;",
    "end": "ellipse;whiteSpace=wrap;html=1;aspect=fixed;fontSize=8;strokeWidth=3;strokeColor=#B71C1C;fillColor=#F8CECC;",
}
EDGE = "edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;fontSize=9;"
EDGE_BACK = EDGE + "dashed=1;"

def ranks(flow):
    ids = [n[0] for n in flow["nodes"]]
    fwd = [(a, b) for a, b, _, back in flow["edges"] if not back]
    preds = {i: [a for a, b in fwd if b == i] for i in ids}
    rank = {}
    def r(i, seen=()):
        if i in rank: return rank[i]
        if i in seen: return 0
        v = 0 if not preds[i] else 1 + max(r(p, seen + (i,)) for p in preds[i])
        rank[i] = v
        return v
    for i in ids: r(i)
    return rank

def layout(flow):
    rank = ranks(flow)
    lanes = flow["lanes"]
    used = {}
    pos = {}
    for nid, lane, kind, label, ref in flow["nodes"]:
        c = rank[nid]
        k = (lane, c)
        slot = used.get(k, 0); used[k] = slot + 1
        w, h = SIZES[kind]
        x = X0 + c * COLW + (COLW - w) // 2
        y = (LANEH - h) // 2 + slot * 40 - (20 if slot else 0)
        pos[nid] = (lanes.index(lane), x, y, w, h)
    ncols = max(rank.values()) + 1
    return pos, ncols

def drawio(flow):
    pos, ncols = layout(flow)
    W = X0 * 2 + ncols * COLW
    lanes = flow["lanes"]
    H = 30 + LANEH * len(lanes)
    out = ['<mxfile host="app.diagrams.net" agent="wms-generator">',
           f'  <diagram name="Alur {flow["n"]}" id="flow{flow["n"]}">',
           f'    <mxGraphModel dx="1400" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="{W+80}" pageHeight="{H+80}" math="0" shadow="0">',
           '      <root>', '        <mxCell id="0"/>', '        <mxCell id="1" parent="0"/>']
    title = html.escape(f'Alur {flow["n"]} — {flow["title"]} (draf berdasarkan default {", ".join(flow["assumptions"])})')
    out.append(f'        <mxCell id="pool" value="{title}" style="swimlane;html=1;childLayout=stackLayout;horizontal=1;startSize=30;horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=0;marginBottom=0;fontStyle=1;" vertex="1" parent="1">')
    out.append(f'          <mxGeometry x="40" y="40" width="{W}" height="{H}" as="geometry"/>')
    out.append('        </mxCell>')
    for li, lane in enumerate(lanes):
        out.append(f'        <mxCell id="lane{li}" value="{html.escape(lane)}" style="swimlane;html=1;startSize=30;horizontal=0;collapsible=0;fontStyle=1;" vertex="1" parent="pool">')
        out.append(f'          <mxGeometry x="0" y="{30 + li*LANEH}" width="{W}" height="{LANEH}" as="geometry"/>')
        out.append('        </mxCell>')
    for nid, lane, kind, label, ref in flow["nodes"]:
        li, x, y, w, h = pos[nid]
        out.append(f'        <mxCell id="{nid}" value="{html.escape(label)}" style="{STYLE[kind]}" vertex="1" parent="lane{li}">')
        out.append(f'          <mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/>')
        out.append('        </mxCell>')
    for k, (a, b, label, back) in enumerate(flow["edges"]):
        st = EDGE_BACK if back else EDGE
        out.append(f'        <mxCell id="e{k}" value="{html.escape(label)}" style="{st}" edge="1" parent="1" source="{a}" target="{b}">')
        out.append('          <mxGeometry relative="1" as="geometry"/>')
        out.append('        </mxCell>')
    out += ['      </root>', '    </mxGraphModel>', '  </diagram>', '</mxfile>', '']
    return "\n".join(out)

# ---------------------------------------------------------------------------
# MARKDOWN
# ---------------------------------------------------------------------------
def mm_id(flow, nid): return f"f{flow['n']}_{nid}"

def mermaid(flow):
    lines = ["```mermaid", "flowchart LR"]
    for li, lane in enumerate(flow["lanes"]):
        lines.append(f'  subgraph L{li}["{lane}"]')
        for nid, ln, kind, label, ref in flow["nodes"]:
            if ln != lane: continue
            lab = label.replace('"', "'")
            shape = {"task": f'["{lab}"]', "gw": f'{{"{lab}"}}', "start": f'(("{lab}"))', "timer": f'(("⏱ {lab}"))', "end": f'((("{lab}")))'}[kind]
            lines.append(f"    {mm_id(flow, nid)}{shape}")
        lines.append("  end")
    for a, b, label, back in flow["edges"]:
        arrow = "-.->" if back else "-->"
        lab = f'|"{label}"|' if label else ""
        lines.append(f"  {mm_id(flow, a)} {arrow}{lab} {mm_id(flow, b)}")
    lines.append("```")
    return "\n".join(lines)

def link_ids(text):
    text = re.sub(r"\b(A-\d{2})\b", lambda m: f"[{m.group(1)}](04-keputusan-dan-asumsi.md#{m.group(1).lower()})", text)
    text = re.sub(r"\b(BR-([A-Z]+))-(\d{2})\.\.(\d{2})\b", lambda m: f"[{m.group(1)}-{m.group(3)}–{m.group(4)}](05-aturan-bisnis.md#br-{m.group(2).lower()})", text)
    text = re.sub(r"(?<!\[)\b(BR-([A-Z]+)-\d{2})\b(?![\d–])", lambda m: f"[{m.group(1)}](05-aturan-bisnis.md#br-{m.group(2).lower()})", text)
    return text

FILE_OF = {1: "07-proses-bisnis.md", 2: "07-proses-bisnis.md", 3: "07-proses-bisnis.md",
           4: "07a-proses-bisnis-lanjutan.md", 5: "07a-proses-bisnis-lanjutan.md", 6: "07a-proses-bisnis-lanjutan.md", 7: "07a-proses-bisnis-lanjutan.md",
           8: "07b-proses-bisnis-pendukung.md", 9: "07b-proses-bisnis-pendukung.md", 10: "07b-proses-bisnis-pendukung.md"}

def anchor(flow):
    return f"alur-{flow['n']}--" + re.sub(r"[^a-z0-9 \-]", "", flow["title"].lower()).replace(" ", "-")

def flow_md(flow):
    rank = ranks(flow)
    order = sorted(flow["nodes"], key=lambda n: (rank[n[0]], flow["lanes"].index(n[1])))
    o = [f'## Alur {flow["n"]} — {flow["title"]}', "",
         f'**Diagram:** [`diagram/bpmn-{flow["n"]:02d}-{flow["slug"]}.drawio`](../diagram/bpmn-{flow["n"]:02d}-{flow["slug"]}.drawio) · '
         f'**Asumsi yang dipakai (default):** ' + ", ".join(f"[{a}](04-keputusan-dan-asumsi.md#{a.lower()})" for a in flow["assumptions"]) + " · "
         f'**Aturan:** ' + link_ids(", ".join(flow["br"])) + " · **Status:** " + ", ".join(f"`{s}`" for s in flow["status"]), "",
         flow["intro"], "",
         "**Lane (aktor):** " + " · ".join(flow["lanes"]), "",
         "### Langkah", "", "| # | Lane | Langkah | Status / efek / aturan |", "|---|---|---|---|"]
    for i, (nid, lane, kind, label, ref) in enumerate(order, 1):
        tag = {"gw": "◇ ", "start": "● ", "timer": "⏱ ", "end": "◉ ", "task": ""}[kind]
        o.append(f"| {i} | {lane} | {tag}{label} | {link_ids(ref)} |")
    gws = [n for n in flow["nodes"] if n[2] in ("gw", "timer")]
    if gws:
        o += ["", "### Percabangan", "", "| Gateway | Cabang → langkah |", "|---|---|"]
        labels = {n[0]: n[3] for n in flow["nodes"]}
        for nid, lane, kind, label, ref in gws:
            outs = [f"*{l or '→'}* → {labels[b]}" for a, b, l, back in flow["edges"] if a == nid]
            o.append(f"| {label} | " + " · ".join(outs) + " |")
    o += ["", "### Diagram (Mermaid)", "", mermaid(flow), "", f'> {link_ids(flow["gateways_note"])}', ""]
    return "\n".join(o)

HEADER = """# Proses Bisnis To-Be — {part}

**Versi:** 0.1 (draf Part 2)
**Tanggal:** 23 September 2026
**Status:** **draf berdasarkan nilai default asumsi A-25–A-49** yang belum divalidasi; setiap alur mencantumkan asumsi yang dipakainya. Bila asumsi berubah, ubah data di [`diagram/_generate.py`](../diagram/_generate.py) dan jalankan ulang — file ini dan `.drawio` dibuat otomatis, **jangan diedit manual**.
**Dokumen terkait:** [Blueprint §7](01-blueprint.md#7-dokumen--alur-utama) · [Aturan Bisnis](05-aturan-bisnis.md) · [Katalog Status](06-katalog-status-dan-enum.md) · [Keputusan & Asumsi](04-keputusan-dan-asumsi.md) · {other}

{scope}

Konvensi: ● awal · ⏱ awal berbasis waktu · ◇ gateway XOR · ◉ akhir · panah putus-putus = jalur balik (loop). Status memakai nilai `enum` dari Katalog Status. Diagram `.drawio` dibuka dengan diagrams.net / ekstensi Draw.io di VS Code.

---

"""

def write_md(path, part, other, scope, flows):
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write(HEADER.format(part=part, other=other, scope=scope))
        f.write("\n".join(flow_md(fl) for fl in flows))

def main():
    os.makedirs(DIAG, exist_ok=True)
    for fl in FLOWS:
        p = os.path.join(DIAG, f"bpmn-{fl['n']:02d}-{fl['slug']}.drawio")
        with open(p, "w", encoding="utf-8", newline="\n") as f:
            f.write(drawio(fl))
        print("ditulis", os.path.relpath(p, ROOT))
    idx = "\n".join(f"{fl['n']}. [{fl['title']}]({FILE_OF[fl['n']]}#{anchor(fl)})" for fl in FLOWS)
    write_md(os.path.join(WMS, "07-proses-bisnis.md"), "Alur 1–3 (permintaan–pengiriman, penerimaan, pemakaian)",
             "[Alur 4–7](07a-proses-bisnis-lanjutan.md) · [Alur 8–10](07b-proses-bisnis-pendukung.md)",
             "Daftar semua alur Part 2:\n\n" + idx, [f for f in FLOWS if FILE_OF[f["n"]].startswith("07-")])
    write_md(os.path.join(WMS, "07a-proses-bisnis-lanjutan.md"), "Alur 4–7 (retur, transfer, konversi & waste, aset)",
             "[Alur 1–3](07-proses-bisnis.md) · [Alur 8–10](07b-proses-bisnis-pendukung.md)",
             "Lanjutan dari [07-proses-bisnis.md](07-proses-bisnis.md); daftar alur lengkap ada di sana.", [f for f in FLOWS if FILE_OF[f["n"]].startswith("07a")])
    write_md(os.path.join(WMS, "07b-proses-bisnis-pendukung.md"), "Alur 8–10 (opname, approval, langganan)",
             "[Alur 1–3](07-proses-bisnis.md) · [Alur 4–7](07a-proses-bisnis-lanjutan.md)",
             "Lanjutan dari [07-proses-bisnis.md](07-proses-bisnis.md); daftar alur lengkap ada di sana.", [f for f in FLOWS if FILE_OF[f["n"]].startswith("07b")])
    print("ditulis docs/wms/07-proses-bisnis.md, 07a-proses-bisnis-lanjutan.md, 07b-proses-bisnis-pendukung.md")

if __name__ == "__main__":
    main()
