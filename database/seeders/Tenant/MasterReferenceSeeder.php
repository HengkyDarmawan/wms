<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\UomCategory;
use Illuminate\Database\Seeder;

/**
 * Data acuan modul Master untuk setiap company baru: kategori satuan dan satuan
 * standar (D-11), alasan baku (BR-GEN-02), kategori penyimpanan (A-37), dan
 * saklar fitur stok (P-08). Aman dijalankan berulang.
 */
class MasterReferenceSeeder extends Seeder
{
    /**
     * Kategori satuan => satuan acuan + daftar satuan [kode, nama, faktor ke acuan].
     *
     * @var array<string, array{name: string, reference: string, uoms: array<int, array{0: string, 1: string, 2: float}>}>
     */
    private const UOM = [
        'COUNT' => [
            'name' => 'Hitung',
            'reference' => 'PCS',
            'uoms' => [
                ['PCS', 'Pieces', 1],
                ['LSN', 'Lusin', 12],
                ['BOX', 'Box', 1],
                ['SET', 'Set', 1],
                ['ROLL', 'Roll', 1],
                ['BATANG', 'Batang', 1],
            ],
        ],
        'LENGTH' => [
            'name' => 'Panjang',
            'reference' => 'M',
            'uoms' => [
                ['M', 'Meter', 1],
                ['CM', 'Sentimeter', 0.01],
                ['MM', 'Milimeter', 0.001],
                ['INCH', 'Inci', 0.0254],
                ['FT', 'Kaki', 0.3048],
            ],
        ],
        'WEIGHT' => [
            'name' => 'Berat',
            'reference' => 'KG',
            'uoms' => [
                ['KG', 'Kilogram', 1],
                ['G', 'Gram', 0.001],
                ['TON', 'Ton', 1000],
                ['SAK', 'Sak 50 kg', 50],
            ],
        ],
        'VOLUME' => [
            'name' => 'Volume',
            'reference' => 'L',
            'uoms' => [
                ['L', 'Liter', 1],
                ['ML', 'Mililiter', 0.001],
                ['M3', 'Meter kubik', 1000],
                ['DRUM', 'Drum 200 L', 200],
            ],
        ],
        'AREA' => [
            'name' => 'Luas',
            'reference' => 'M2',
            'uoms' => [
                ['M2', 'Meter persegi', 1],
                ['CM2', 'Sentimeter persegi', 0.0001],
            ],
        ],
    ];

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

    /** Saklar fitur stok lapis 1 (P-08). */
    private const FEATURES = [
        'lot' => true,
        'serial' => true,
        'piece' => true,
        'expiry' => true,
        'fefo' => true,
        'qc' => true,
        'rfid' => false,
    ];

    public function run(): void
    {
        $this->seedUoms();
        $this->seedReasons();
        $this->seedStorageCategories();
        $this->seedFeatures();

        $this->command?->info('Referensi master siap: satuan, alasan, kategori penyimpanan, saklar fitur.');
    }

    private function seedUoms(): void
    {
        foreach (self::UOM as $kode => $definisi) {
            $kategori = UomCategory::updateOrCreate(
                ['code' => $kode],
                ['name' => $definisi['name'], 'is_active' => true],
            );

            foreach ($definisi['uoms'] as [$uomKode, $uomNama, $faktor]) {
                Uom::updateOrCreate(
                    ['code' => $uomKode],
                    [
                        'uom_category_id' => $kategori->id,
                        'name' => $uomNama,
                        'factor_to_reference' => $faktor,
                        'is_active' => true,
                    ],
                );
            }

            // BR-MST-03: satuan acuan kategori selalu berfaktor 1.
            $acuan = Uom::query()->where('code', $definisi['reference'])->first();

            if ($acuan !== null) {
                $kategori->forceFill(['reference_uom_id' => $acuan->id])->save();
            }
        }
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
        foreach (self::FEATURES as $kunci => $aktif) {
            FeatureSetting::updateOrCreate(['key' => $kunci], ['enabled' => $aktif]);
        }
    }
}
