<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemCategory;

/**
 * Daftar kunci `company_settings` dan saklar `feature_settings` yang dibaca kode
 * (11-master §13.5, A-230): label, nilai bawaan, rentang, dan siapa pemakainya.
 *
 * Satu sumber untuk layar pengaturan dan validasinya; kode pemakai tetap membaca
 * kuncinya sendiri lewat `CompanySetting::get()` dengan bawaan yang sama.
 */
class CompanySettingCatalog
{
    /** Zona waktu yang didukung; sama dengan `Platform\Actions\CreateCompany`. */
    public const ZONA_WAKTU = ['Asia/Jakarta' => 'WIB — Asia/Jakarta', 'Asia/Makassar' => 'WITA — Asia/Makassar', 'Asia/Jayapura' => 'WIT — Asia/Jayapura'];

    /**
     * @return array<string, array{label: string, hint: string, default: int|float, min: int|float, max: int|float, satuan: string, grup: string, desimal: bool}>
     */
    public static function nilai(): array
    {
        $hari = fn (string $label, string $hint, int $bawaan, string $grup) => ['label' => $label, 'hint' => $hint, 'default' => $bawaan, 'min' => 1, 'max' => 365, 'satuan' => 'hari', 'grup' => $grup, 'desimal' => false];
        $persen = fn (string $label, string $hint, float $bawaan, string $grup) => ['label' => $label, 'hint' => $hint, 'default' => $bawaan, 'min' => 0, 'max' => 100, 'satuan' => '%', 'grup' => $grup, 'desimal' => true];

        return [
            'review_sla_days' => $hari('SLA tinjau permintaan klien', 'Hari kerja (tanpa hari libur di kalender). Permintaan klien yang lebih lama dari ini di Menunggu Tinjauan ditandai terlambat.', 1, 'Permintaan & pengiriman'),
            'work_days_per_week' => ['label' => 'Hari kerja per minggu', 'hint' => '5 = Senin–Jumat, 6 = Senin–Sabtu, 7 = setiap hari. Dipakai bersama kalender libur di bawah untuk menghitung hari kerja.', 'default' => 6, 'min' => 5, 'max' => 7, 'satuan' => 'hari', 'grup' => 'Hari kerja', 'desimal' => false],
            'substitution_objection_days' => $hari('Batas keberatan pengganti', 'Lama klien boleh menolak item pengganti sebelum dianggap setuju.', 1, 'Permintaan & pengiriman'),
            'receipt_confirm_days' => $hari('Batas konfirmasi terima', 'Lama pemohon boleh mengajukan keberatan atas bukti terima; lewat itu dianggap diterima.', 3, 'Permintaan & pengiriman'),
            'discrepancy_alert_days' => $hari('Peringatan selisih pengiriman', 'Selisih pengiriman terbuka lebih lama dari ini ditandai di daftar.', 7, 'Permintaan & pengiriman'),
            'reservation_alert_days' => $hari('Peringatan reservasi menggantung', 'Reservasi tanpa tugas picking lebih lama dari ini ditandai menggantung.', 7, 'Stok & opname'),
            'count_tolerance_pct' => $persen('Toleransi selisih opname (%)', 'Selisih di bawah persen ini dan batas mutlaknya dianggap kecil; kategori item boleh menimpa.', 1, 'Stok & opname'),
            'count_tolerance_abs' => ['label' => 'Toleransi selisih opname (mutlak)', 'hint' => 'Batas selisih dalam satuan dasar untuk selisih kecil.', 'default' => 1, 'min' => 0, 'max' => 1000000, 'satuan' => 'satuan dasar', 'grup' => 'Stok & opname', 'desimal' => true],
            'count_moderate_pct' => $persen('Batas selisih sedang (%)', 'Di atas toleransi sampai persen ini = selisih sedang (hitung ulang); lebih dari itu = besar.', 5, 'Stok & opname'),
            // A-306: hanya tampil bagi pemegang po.view (persen, bukan nilai uang).
            'po_price_increase_pct' => ['label' => 'Peringatan kenaikan harga beli (%)', 'hint' => 'Baris PO ditandai bila harga satuannya lebih tinggi dari persen ini di atas harga PO terakhir item itu.', 'default' => 10, 'min' => 0, 'max' => 1000, 'satuan' => '%', 'grup' => 'Purchasing', 'desimal' => true],
            'asset_life_alert_pct' => $persen('Peringatan sisa umur aset (%)', 'Aset dengan sisa umur di bawah persen ini ditandai di daftar aset.', 20, 'Aset'),
        ];
    }

    /** @return array<string, array{label: string, hint: string, tetap: bool}> */
    public static function fitur(): array
    {
        return [
            // A-284: lot + kedaluwarsa membuka jenis *Barang berkedaluwarsa*, serial membuka *Alat bernomor seri*.
            'lot' => ['label' => 'Batch / lot', 'hint' => 'Item dilacak per lot; bersama Tanggal kedaluwarsa membuka jenis Barang berkedaluwarsa.', 'tetap' => false],
            'serial' => ['label' => 'Serial number', 'hint' => 'Item dilacak per unit; membuka jenis Alat bernomor seri (dipinjamkan ke proyek).', 'tetap' => false],
            'piece' => ['label' => 'Per potong', 'hint' => 'Batang/lembar dicatat per potong: mode Potong di Konversi, tab & label potongan. Mati: layar itu disembunyikan, item lama per potong tetap bisa ditransaksikan.', 'tetap' => false],
            'expiry' => ['label' => 'Tanggal kedaluwarsa', 'hint' => 'Kolom kedaluwarsa pada lot/serial; bersama Batch / lot membuka jenis Barang berkedaluwarsa.', 'tetap' => false],
            'fefo' => ['label' => 'Strategi FEFO', 'hint' => 'Ambil yang paling dekat kedaluwarsa lebih dulu.', 'tetap' => false],
            'qc' => ['label' => 'QC penerimaan', 'hint' => 'GRN vendor untuk item yang butuh QC masuk bin Karantina dulu.', 'tetap' => false],
            'otp_auto' => ['label' => 'OTP bukti terima otomatis', 'hint' => 'Kode OTP tautan penerima dikirim ke WhatsApp/SMS penerima dan tidak tampil ke staf; bila gagal terkirim, kode tampil untuk disampaikan staf/pengantar. Jalur cadangan bila penerima belum punya akun portal (A-312).', 'tetap' => false],
            'rfid' => ['label' => 'RFID', 'hint' => 'Tersedia di Fase 2; saklar hanya disimpan.', 'tetap' => true],
        ];
    }

    /** Nilai tersimpan atau bawaannya. @return array<string, int|float> */
    public static function nilaiSaatIni(): array
    {
        $hasil = [];

        foreach (self::nilai() as $kunci => $def) {
            $nilai = CompanySetting::get($kunci);
            $hasil[$kunci] = is_numeric($nilai) ? ($def['desimal'] ? (float) $nilai : (int) $nilai) : $def['default'];
        }

        return $hasil;
    }

    /** @return array<string, bool> */
    public static function fiturSaatIni(): array
    {
        $baris = FeatureSetting::query()->get()->keyBy('key');

        return collect(self::fitur())->map(fn ($def, $kunci) => (bool) ($baris[$kunci]?->enabled ?? false))->all();
    }

    /**
     * Item yang masih memakai tiap fitur — peringatan sebelum saklar dimatikan
     * (P-03: mematikan menyembunyikan, tidak menghapus data).
     *
     * @return array<string, int>
     */
    public static function pemakaiFitur(): array
    {
        return [
            'lot' => Item::query()->where('tracking_mode', TrackingMode::Lot->value)->count(),
            'serial' => Item::query()->where('tracking_mode', TrackingMode::Serial->value)->count(),
            'piece' => Item::query()->where('tracking_mode', TrackingMode::Piece->value)->count(),
            'expiry' => Item::query()->where('has_expiry', true)->count(),
            'fefo' => Item::query()->where('removal_strategy', RemovalStrategy::Fefo->value)->count()
                + ItemCategory::query()->where('removal_strategy', RemovalStrategy::Fefo->value)->count(),
            'qc' => Item::query()->where('requires_qc', true)->count(),
            'otp_auto' => 0,
            'rfid' => 0,
        ];
    }
}
