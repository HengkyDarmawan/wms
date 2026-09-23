<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Actions\EnsureSystemBins;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\BinCodeBuilder;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Gudang demo company DEMO — sumber kebenaran: docs/00-akun-uji.md §2.
 * HANYA untuk dev, demo, dan staging.
 *
 * Dijalankan setelah proyek ada, karena Gudang Site terikat proyek (BR-WH-04).
 */
class WarehouseDemoSeeder extends Seeder
{
    /**
     * Kode gudang => [nama, tipe, kode induk, kode proyek].
     *
     * @var array<string, array{name: string, type: string, parent: string|null, project: string|null}>
     */
    private const WAREHOUSES = [
        'CKG' => ['name' => 'Gudang Utama Cakung', 'type' => 'MAIN', 'parent' => null, 'project' => null],
        'BKS' => ['name' => 'Gudang Cabang Bekasi', 'type' => 'BRANCH', 'parent' => 'CKG', 'project' => null],
        'KRW1' => ['name' => 'Gudang Site Pipa Karawang STA 0+000', 'type' => 'SITE', 'parent' => 'CKG', 'project' => 'PRJ-001'],
        'KRW2' => ['name' => 'Gudang Site Pipa Karawang STA 2+500', 'type' => 'SITE', 'parent' => 'CKG', 'project' => 'PRJ-001'],
    ];

    /** Zona contoh hanya di gudang besar; gudang site memakai satu zona (A-02). */
    private const ZONES = [
        'CKG' => ['A' => 'Material besi', 'B' => 'Kelistrikan'],
        'BKS' => ['A' => 'Umum'],
        'KRW1' => ['A' => 'Lapangan'],
        'KRW2' => ['A' => 'Lapangan'],
    ];

    public function run(): void
    {
        $tipe = WarehouseType::query()->pluck('id', 'code')->all();

        if ($tipe === []) {
            throw new RuntimeException('Tipe gudang belum ada. Jalankan WarehouseReferenceSeeder lebih dulu.');
        }

        $proyek = Project::query()->pluck('id', 'code')->all();
        $systemBins = app(EnsureSystemBins::class);
        $dibuat = [];

        foreach (self::WAREHOUSES as $kode => $data) {
            $gudang = Warehouse::withoutGlobalScopes()->updateOrCreate(
                ['code' => $kode],
                [
                    'name' => $data['name'],
                    'warehouse_type_id' => $tipe[$data['type']] ?? throw new RuntimeException('Tipe '.$data['type'].' tidak ada.'),
                    'parent_id' => $data['parent'] === null ? null : ($dibuat[$data['parent']]->id ?? null),
                    'project_id' => $data['project'] === null ? null : ($proyek[$data['project']] ?? null),
                    'is_active' => true,
                ],
            );

            $systemBins->handle($gudang);
            $dibuat[$kode] = $gudang;
        }

        $this->seedLocations($dibuat);
        $this->seedOnSiteBins($dibuat, $systemBins);

        $this->command?->info(
            'Gudang demo siap: '.Warehouse::withoutGlobalScopes()->count().' gudang, '
            .Bin::withoutGlobalScopes()->count().' bin.',
        );
    }

    /** @param  array<string, Warehouse>  $gudang */
    private function seedLocations(array $gudang): void
    {
        foreach (self::ZONES as $kodeGudang => $zona) {
            if (! isset($gudang[$kodeGudang])) {
                continue;
            }

            foreach ($zona as $kodeZona => $namaZona) {
                $z = Zone::updateOrCreate(
                    ['warehouse_id' => $gudang[$kodeGudang]->id, 'code' => $kodeZona],
                    ['name' => $namaZona, 'is_active' => true],
                );

                // Satu rak dua level per zona: cukup untuk mencoba alur picking
                // dan put-away tanpa membuat ratusan baris data contoh.
                $rak = Rack::updateOrCreate(['zone_id' => $z->id, 'code' => 'R01'], ['is_active' => true]);

                foreach (['L1', 'L2'] as $kodeLevel) {
                    $level = RackLevel::updateOrCreate(
                        ['rack_id' => $rak->id, 'code' => $kodeLevel],
                        ['is_active' => true],
                    );

                    foreach (['B01', 'B02'] as $kodeBin) {
                        $kode = BinCodeBuilder::forRackLevel($level, $kodeBin);

                        Bin::withoutGlobalScopes()->updateOrCreate(
                            ['code' => $kode],
                            [
                                'warehouse_id' => $gudang[$kodeGudang]->id,
                                'rack_level_id' => $level->id,
                                'bin_type' => BinType::Storage,
                                'bin_status' => BinStatus::Active,
                                'is_virtual' => false,
                            ],
                        );
                    }
                }
            }
        }
    }

    /**
     * BR-WH-03: bin On-site Proyek satu per proyek, melekat pada Gudang Site.
     *
     * @param  array<string, Warehouse>  $gudang
     */
    private function seedOnSiteBins(array $gudang, EnsureSystemBins $systemBins): void
    {
        foreach (['KRW1', 'KRW2'] as $kode) {
            $site = $gudang[$kode] ?? null;

            if ($site === null || $site->project_id === null) {
                continue;
            }

            $proyek = Project::query()->find($site->project_id);

            if ($proyek !== null) {
                $systemBins->onSiteBin($site, $proyek);
            }
        }
    }
}
