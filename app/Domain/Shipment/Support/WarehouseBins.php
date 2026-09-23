<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Support;

use App\Domain\Master\Models\Project;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;

/**
 * Menemukan bin sistem sebuah gudang (BR-WH-02).
 *
 * Dipusatkan di sini karena modul ini memakai empat di antaranya — Loading
 * Area, Dalam Perjalanan, Retur, dan On-site — dan mencarinya sendiri-sendiri
 * di setiap aksi akan melahirkan empat cara berbeda menangani bin yang hilang.
 *
 * Global scope sengaja dilewati: bin sistem harus tetap ditemukan meskipun
 * penggunanya tidak bercakupan gudang itu, misalnya saat SJ berpindah tangan
 * antar gudang.
 */
class WarehouseBins
{
    public function loadingArea(Warehouse $warehouse): Bin
    {
        return $this->cari($warehouse, BinType::Staging);
    }

    public function inTransit(Warehouse $warehouse): Bin
    {
        return $this->cari($warehouse, BinType::InTransit);
    }

    public function returnBin(Warehouse $warehouse): Bin
    {
        return $this->cari($warehouse, BinType::Return);
    }

    /**
     * Bin On-site satu proyek (A-69, BR-WH-03).
     *
     * Dicari lintas gudang: satu proyek hanya punya satu bin On-site meskipun
     * punya beberapa Gudang Site.
     */
    public function onSite(Project $project): Bin
    {
        $bin = Bin::query()->withoutGlobalScopes()
            ->where('bin_type', BinType::OnSite->value)
            ->where('project_id', $project->id)
            ->first();

        if ($bin === null) {
            throw ShipmentRuleException::rule(
                'BR-WH-03',
                'Proyek '.$project->code.' belum punya bin On-site. Simpan ulang Gudang Site-nya untuk membuatnya.',
            );
        }

        return $bin;
    }

    private function cari(Warehouse $warehouse, BinType $type): Bin
    {
        $bin = Bin::query()->withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('bin_type', $type->value)
            ->first();

        if ($bin === null) {
            throw ShipmentRuleException::rule(
                'BR-WH-02',
                'Gudang '.$warehouse->code.' belum punya bin '.$type->label().'. Simpan ulang gudangnya untuk membuatnya.',
            );
        }

        return $bin;
    }
}
