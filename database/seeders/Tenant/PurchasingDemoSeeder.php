<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\Purchasing\Models\VendorPrice;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Harga beli vendor demo company DEMO (docs/00-akun-uji.md §2, A-218). HANYA
 * untuk dev, demo, dan staging. Aman dijalankan berulang.
 */
class PurchasingDemoSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string, 2: float}> vendor, item, harga per satuan dasar */
    public const PRICES = [
        ['BAJA-PRIMA', 'PIPA-PVC-4', 14500],
        ['BESI-JAYA', 'BAUT-M12', 1500],
        ['BESI-JAYA', 'SEMEN-PCC-50', 1400],
        ['TOKO-ALAT', 'BAUT-M12', 1350],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('PurchasingDemoSeeder tidak boleh dijalankan di produksi.');
        }

        foreach (self::PRICES as [$vendor, $item, $harga]) {
            $vendorId = Vendor::query()->where('code', $vendor)->value('id');
            $itemId = Item::query()->where('code', $item)->value('id');

            if ($vendorId === null || $itemId === null) {
                throw new RuntimeException('Vendor '.$vendor.' atau item '.$item.' belum ada. Jalankan MasterDemoSeeder lebih dulu.');
            }

            VendorPrice::query()->updateOrCreate(
                ['vendor_id' => $vendorId, 'item_id' => $itemId, 'valid_from' => '2026-09-01'],
                ['unit_price' => $harga, 'currency' => 'IDR', 'is_active' => true, 'notes' => 'Harga demo'],
            );
        }

        $this->command?->info('Harga beli vendor demo siap: '.count(self::PRICES).' harga.');
    }
}
