<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Carrier;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Master data contoh company DEMO — sumber kebenaran: docs/00-akun-uji.md §2.
 * HANYA untuk dev/demo/staging.
 *
 * Dijalankan sebelum user demo karena `users.client_id` menunjuk ke `clients`.
 */
class MasterDemoSeeder extends Seeder
{
    /** @var array<string, array{name: string, contact: string, phone: string}> */
    private const CLIENTS = [
        'KL1' => ['name' => 'PT Klien Satu', 'contact' => 'Lina', 'phone' => '+6281200000013'],
        'KL2' => ['name' => 'CV Klien Dua', 'contact' => 'Maman', 'phone' => '+6281200000014'],
    ];

    /** @var array<string, array{name: string, client: string|null, internal: bool}> */
    private const PROJECTS = [
        'PRJ-001' => ['name' => 'Pipa Karawang', 'client' => 'KL1', 'internal' => false],
        'PRJ-INT' => ['name' => 'Proyek Internal', 'client' => null, 'internal' => true],
        'PRJ-002' => ['name' => 'Gudang Cikarang', 'client' => 'KL2', 'internal' => false],
    ];

    /** @var array<string, array{name: string, type: VendorType, phone: string}> */
    private const VENDORS = [
        'BAJA-PRIMA' => ['name' => 'PT Baja Prima', 'type' => VendorType::Company, 'phone' => '+6221500001'],
        'BESI-JAYA' => ['name' => 'Toko Besi Jaya', 'type' => VendorType::Shop, 'phone' => '+6221500002'],
        'TOKO-ALAT' => ['name' => 'Tokopedia Toko Alat', 'type' => VendorType::OnlineMarketplace, 'phone' => '+6221500003'],
    ];

    /** @var array<string, array{name: string, parent: string|null, storage: string}> */
    private const CATEGORIES = [
        'MATERIAL' => ['name' => 'Material', 'parent' => null, 'storage' => 'UMUM'],
        'PIPA' => ['name' => 'Pipa', 'parent' => 'MATERIAL', 'storage' => 'YARD'],
        'FASTENER' => ['name' => 'Baut & mur', 'parent' => 'MATERIAL', 'storage' => 'RAK'],
        'ALAT' => ['name' => 'Alat kerja', 'parent' => null, 'storage' => 'UMUM'],
    ];

    public function run(): void
    {
        $clients = $this->seedClients();
        $this->seedProjects($clients);
        $vendors = $this->seedVendors();
        $categories = $this->seedCategories();
        $this->seedItems($categories, $vendors);
        $this->seedCarriers();

        $this->command?->info('Master demo siap: '.Client::count().' klien, '
            .Project::count().' proyek, '.Vendor::count().' vendor, '.Item::count().' item.');
    }

    /** @return array<string, Client> */
    private function seedClients(): array
    {
        $hasil = [];

        foreach (self::CLIENTS as $kode => $data) {
            $hasil[$kode] = Client::updateOrCreate(
                ['code' => $kode],
                [
                    'name' => $data['name'],
                    'contact_name' => $data['contact'],
                    'phone' => $data['phone'],
                    'is_active' => true,
                ],
            );
        }

        return $hasil;
    }

    /** @param  array<string, Client>  $clients */
    private function seedProjects(array $clients): void
    {
        foreach (self::PROJECTS as $kode => $data) {
            Project::updateOrCreate(
                ['code' => $kode],
                [
                    'name' => $data['name'],
                    'client_id' => $data['client'] === null ? null : $clients[$data['client']]->id,
                    'is_internal' => $data['internal'],
                    'status' => ProjectStatus::Active,
                    'start_date' => now()->subMonths(2)->toDateString(),
                ],
            );
        }
    }

    /** @return array<string, Vendor> */
    private function seedVendors(): array
    {
        $hasil = [];

        foreach (self::VENDORS as $kode => $data) {
            $hasil[$kode] = Vendor::updateOrCreate(
                ['code' => $kode],
                [
                    'name' => $data['name'],
                    'vendor_type' => $data['type'],
                    'status' => VendorStatus::Active,
                    'phone' => $data['phone'],
                    'is_active' => true,
                ],
            );
        }

        return $hasil;
    }

    /** @return array<string, ItemCategory> */
    private function seedCategories(): array
    {
        $hasil = [];

        foreach (self::CATEGORIES as $kode => $data) {
            $hasil[$kode] = ItemCategory::updateOrCreate(
                ['code' => $kode],
                [
                    'name' => $data['name'],
                    'parent_id' => $data['parent'] === null ? null : $hasil[$data['parent']]->id,
                    'storage_category_id' => StorageCategory::query()->where('code', $data['storage'])->value('id'),
                    'is_active' => true,
                ],
            );
        }

        return $hasil;
    }

    /**
     * Empat item yang mewakili empat mode pelacakan, supaya layar dan aturan
     * bisa dicoba tanpa membuat data sendiri.
     *
     * @param  array<string, ItemCategory>  $categories
     * @param  array<string, Vendor>  $vendors
     */
    private function seedItems(array $categories, array $vendors): void
    {
        $meter = Uom::query()->where('code', 'M')->value('id');
        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        $kg = Uom::query()->where('code', 'KG')->value('id');

        $definisi = [
            [
                'code' => 'PIPA-PVC-4',
                'name' => 'Pipa PVC 4 inci',
                'category' => 'PIPA',
                'base_uom_id' => $meter,
                'tracking_mode' => TrackingMode::Piece,
                'ownership_model' => OwnershipModel::Consumable,
                'removal_strategy' => RemovalStrategy::OffcutFirst,
                'is_cuttable' => true,
                'min_offcut_length' => 0.5,
                'kerf' => 0.005,
                'reorder_point' => 100,
                'vendors' => ['BAJA-PRIMA', 'BESI-JAYA'],
            ],
            [
                'code' => 'BAUT-M12',
                'name' => 'Baut M12 x 50 galvanis',
                'category' => 'FASTENER',
                'base_uom_id' => $pcs,
                'tracking_mode' => TrackingMode::None,
                'ownership_model' => OwnershipModel::Consumable,
                'removal_strategy' => RemovalStrategy::Fifo,
                'reorder_point' => 500,
                'min_stock' => 200,
                'vendors' => ['BESI-JAYA'],
            ],
            [
                'code' => 'SEMEN-PCC-50',
                'name' => 'Semen PCC sak 50 kg',
                'category' => 'MATERIAL',
                'base_uom_id' => $kg,
                'tracking_mode' => TrackingMode::Lot,
                'ownership_model' => OwnershipModel::Consumable,
                'removal_strategy' => RemovalStrategy::Fefo,
                'has_expiry' => true,
                'reorder_point' => 1000,
                'vendors' => ['BAJA-PRIMA'],
            ],
            [
                'code' => 'GENSET-5KVA',
                'name' => 'Genset 5 kVA',
                'category' => 'ALAT',
                'base_uom_id' => $pcs,
                'tracking_mode' => TrackingMode::Serial,
                'ownership_model' => OwnershipModel::Asset,
                'removal_strategy' => RemovalStrategy::Manual,
                'requires_qc' => true,
                'vendors' => ['TOKO-ALAT'],
            ],
        ];

        foreach ($definisi as $data) {
            $kategori = $categories[$data['category']]->id;
            $pemasok = $data['vendors'];

            unset($data['category'], $data['vendors']);

            $item = Item::updateOrCreate(
                ['code' => $data['code']],
                $data + [
                    'item_category_id' => $kategori,
                    'status' => ItemStatus::Active,
                ],
            );

            $pasangan = [];

            foreach ($pemasok as $urutan => $kodeVendor) {
                $pasangan[$vendors[$kodeVendor]->id] = [
                    'priority' => $urutan + 1,
                    'is_preferred' => $urutan === 0,
                ];
            }

            $item->vendors()->sync($pasangan);
        }

        // BR-STK-09: pipa dijual per batang 6 m, tapi disimpan dalam meter.
        $pipa = Item::query()->where('code', 'PIPA-PVC-4')->first();
        $batang = Uom::query()->where('code', 'BATANG')->value('id');

        if ($pipa !== null && $batang !== null) {
            $pipa->uomConversions()->updateOrCreate(
                ['uom_id' => $batang],
                ['qty_base' => 6, 'is_nominal_piece' => true],
            );
        }
    }

    private function seedCarriers(): void
    {
        foreach ([
            ['name' => 'JNE Trucking', 'phone' => '+622129278888'],
            ['name' => 'Indah Cargo', 'phone' => '+622129279999'],
        ] as $data) {
            Carrier::updateOrCreate(['name' => $data['name']], ['phone' => $data['phone'], 'is_active' => true]);
        }
    }
}
