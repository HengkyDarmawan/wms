<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\BinCodeBuilder;

/**
 * BR-WH-02 — setiap gudang wajib punya bin bawaan dan satu bin virtual
 * Dalam Perjalanan miliknya sendiri (BR-STK-13).
 *
 * Dipanggil otomatis setiap kali gudang disimpan, jadi gudang lama yang dibuat
 * sebelum aturan ini ada pun ikut dilengkapi saat diubah. Aman dijalankan
 * berulang: bin yang sudah ada tidak digandakan maupun ditimpa.
 */
class EnsureSystemBins
{
    /** @return array<int, Bin> bin yang baru dibuat */
    public function handle(Warehouse $warehouse, ?User $actor = null): array
    {
        $dibuat = [];

        foreach (BinType::cases() as $type) {
            if (! $type->isSystemDefault()) {
                continue;
            }

            $kode = BinCodeBuilder::forSystemBin($warehouse, $type);

            $ada = Bin::query()->withoutGlobalScopes()
                ->where('warehouse_id', $warehouse->id)
                ->where('bin_type', $type->value)
                ->exists();

            if ($ada) {
                continue;
            }

            $dibuat[] = Bin::create([
                'warehouse_id' => $warehouse->id,
                'rack_level_id' => null,
                'code' => $kode,
                'bin_type' => $type,
                'bin_status' => BinStatus::Active,
                'is_virtual' => $type->isVirtual(),
            ]);
        }

        if ($dibuat !== []) {
            activity('warehouse')
                ->performedOn($warehouse)
                ->causedBy($actor)
                ->withProperties(['jumlah' => count($dibuat)])
                ->log('Bin bawaan gudang dibuat');
        }

        return $dibuat;
    }

    /**
     * BR-WH-03 — bin On-site Proyek: satu per proyek, hanya menampung aset.
     *
     * Dibuat terpisah dari bin bawaan karena terikat proyek, bukan gudang saja.
     */
    public function onSiteBin(Warehouse $warehouse, Project $project, ?User $actor = null): Bin
    {
        // Blueprint 6.3: satu bin On-site per PROYEK, bukan per gudang. Satu proyek
        // boleh punya beberapa Gudang Site (A-40), jadi pencariannya lintas gudang.
        $ada = Bin::query()->withoutGlobalScopes()
            ->where('bin_type', BinType::OnSite->value)
            ->where('project_id', $project->id)
            ->first();

        if ($ada !== null) {
            return $ada;
        }

        $kode = BinCodeBuilder::forOnSiteBin($warehouse, (string) $project->code);

        if (Bin::query()->withoutGlobalScopes()->where('code', $kode)->exists()) {
            throw WarehouseRuleException::fields(
                ['code' => 'Kode bin "'.$kode.'" sudah dipakai.'],
                'BR-WH-01',
            );
        }

        $bin = Bin::create([
            'warehouse_id' => $warehouse->id,
            'rack_level_id' => null,
            'code' => $kode,
            'bin_type' => BinType::OnSite,
            'bin_status' => BinStatus::Active,
            'project_id' => $project->id,
            'is_virtual' => true,
        ]);

        activity('warehouse')
            ->performedOn($bin)
            ->causedBy($actor)
            ->withProperties(['project' => $project->code])
            ->log('Bin On-site Proyek dibuat');

        return $bin;
    }
}
