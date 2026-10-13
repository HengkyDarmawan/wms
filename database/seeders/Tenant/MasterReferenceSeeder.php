<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Master\Support\DefaultUoms;
use Illuminate\Database\Seeder;

/**
 * Data acuan modul Master untuk setiap company baru: kategori satuan dan satuan
 * standar (D-11), alasan baku (BR-GEN-02), kategori penyimpanan (A-37), dan
 * saklar fitur stok (P-08). Aman dijalankan berulang.
 */
class MasterReferenceSeeder extends Seeder
{
    /** @var array<string, array<string, string>> konteks => [kode => label] */
    private const REASONS = [
        'reject' => [
            'SPEC' => 'Tidak sesuai spesifikasi',
            'DAMAGE' => 'Barang rusak saat diterima',
            'QTY' => 'Jumlah tidak sesuai dokumen',
            'DOC' => 'Dokumen pendukung kurang',
            'OTHER' => 'Alasan lain',
        ],
        'cancel' => [
            'DUPLICATE' => 'Dokumen dobel',
            'WRONG_INPUT' => 'Salah input',
            'NOT_NEEDED' => 'Kebutuhan berubah',
            'PROJECT_HOLD' => 'Proyek ditunda',
            'OTHER' => 'Alasan lain',
        ],
        'adjustment' => [
            'COUNT_FIX' => 'Koreksi hasil hitung ulang',
            'SYSTEM_FIX' => 'Koreksi kesalahan sistem',
            'FOUND' => 'Barang ditemukan kembali',
            'OPENING' => 'Saldo awal',
            'OTHER' => 'Alasan lain',
        ],
        'waste' => [
            'OFFCUT' => 'Sisa potongan di bawah panjang minimum',
            'KERF' => 'Susut mata potong',
            'SPOIL' => 'Rusak dalam proses',
            'OTHER' => 'Alasan lain',
        ],
        'damage' => [
            'TRANSPORT' => 'Rusak saat pengiriman',
            'HANDLING' => 'Rusak saat penanganan gudang',
            'WEATHER' => 'Rusak karena cuaca',
            'USE' => 'Rusak saat pemakaian di proyek',
            'VENDOR' => 'Cacat dari vendor',
            'OTHER' => 'Alasan lain',
        ],
        'short_pick' => [
            'NO_STOCK' => 'Stok fisik kurang',
            'LOCATION' => 'Barang tidak ditemukan di lokasi',
            'PARTIAL' => 'Sengaja dikirim sebagian',
            'OTHER' => 'Alasan lain',
        ],
        'discrepancy' => [
            'MISCOUNT' => 'Salah hitung',
            'MISPLACED' => 'Salah lokasi',
            'UNRECORDED' => 'Mutasi belum tercatat',
            'OTHER' => 'Alasan lain',
        ],
        'lost' => [
            'MISSING' => 'Hilang tidak diketahui',
            'THEFT' => 'Dugaan pencurian',
            'SITE' => 'Hilang di lokasi proyek',
            'OTHER' => 'Alasan lain',
        ],
    ];

    /** @var array<string, array{name: string, mode: CapacityMode}> */
    private const STORAGE = [
        'UMUM' => ['name' => 'Umum', 'mode' => CapacityMode::Warn],
        'RAK' => ['name' => 'Rak kecil', 'mode' => CapacityMode::Warn],
        'YARD' => ['name' => 'Lapangan terbuka', 'mode' => CapacityMode::Warn],
        'B3' => ['name' => 'Bahan berbahaya', 'mode' => CapacityMode::Block],
        'DINGIN' => ['name' => 'Ruang dingin', 'mode' => CapacityMode::Block],
        'KARANTINA' => ['name' => 'Karantina & barang rusak', 'mode' => CapacityMode::Block],
    ];

    /**
     * Saklar fitur stok lapis 1 (P-08) untuk company **baru** (A-284): tiga
     * jenis barang menyala; per potong dan QC penerimaan mati.
     */
    public const FEATURES = [
        'lot' => true,
        'serial' => true,
        'piece' => false,
        'expiry' => true,
        'fefo' => true,
        'qc' => false,
        'rfid' => false,
        // A-420: tugas picking dibuat otomatis setelah REQ disetujui.
        'auto_pick_request' => true,
    ];

    public function run(): void
    {
        $this->seedUoms();
        $this->seedReasons();
        $this->seedStorageCategories();
        $this->seedFeatures();

        $this->command?->info('Referensi master siap: satuan, alasan, kategori penyimpanan, saklar fitur.');
    }

    /** A-411: satuan bawaan dari satu sumber ({@see DefaultUoms}); company baru = disamakan dengan daftar. */
    private function seedUoms(): void
    {
        DefaultUoms::pasang(timpa: true);
    }

    private function seedReasons(): void
    {
        foreach (self::REASONS as $konteks => $daftar) {
            foreach ($daftar as $kode => $label) {
                ReasonCode::updateOrCreate(
                    ['context' => ReasonContext::from($konteks), 'code' => $kode],
                    ['label' => $label, 'is_active' => true],
                );
            }
        }
    }

    private function seedStorageCategories(): void
    {
        foreach (self::STORAGE as $kode => $definisi) {
            StorageCategory::updateOrCreate(
                ['code' => $kode],
                ['name' => $definisi['name'], 'capacity_mode' => $definisi['mode'], 'is_active' => true],
            );
        }
    }

    private function seedFeatures(): void
    {
        // A-284: hanya menulis saklar yang belum ada, supaya seed ulang tidak
        // mengembalikan pilihan company ke bawaan.
        FeatureSetting::seed(self::FEATURES);
    }
}
