<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Database\Seeder;

/**
 * Tipe gudang bawaan untuk setiap company baru (Blueprint §6.2).
 * Kode `site` punya arti khusus di BR-WH-04, jadi ketiganya ditandai bawaan
 * dan kodenya tidak bisa diubah. Aman dijalankan berulang.
 */
class WarehouseReferenceSeeder extends Seeder
{
    /** @var array<string, string> */
    private const TYPES = [
        'MAIN' => 'Gudang Utama',
        'BRANCH' => 'Gudang Cabang',
        'SITE' => 'Gudang Site',
    ];

    public function run(): void
    {
        foreach (self::TYPES as $kode => $nama) {
            WarehouseType::updateOrCreate(
                ['code' => $kode],
                ['name' => $nama, 'is_builtin' => true, 'is_active' => true],
            );
        }

        $this->command?->info('Referensi gudang siap: '.count(self::TYPES).' tipe gudang bawaan.');
    }
}
