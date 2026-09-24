<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Support;

use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;

/**
 * Bin sistem yang dipakai modul penerimaan (BR-WH-02): Penerimaan, Karantina
 * QC, Retur, dan Dalam Perjalanan gudang asal transfer.
 *
 * Global scope sengaja dilewati: GRN transfer menarik barang dari bin Dalam
 * Perjalanan milik gudang lain yang biasanya di luar cakupan penerimanya.
 */
class ReceiptBins
{
    public function receiving(Warehouse $warehouse): Bin
    {
        return $this->cari($warehouse, BinType::Receiving);
    }

    public function quarantine(Warehouse $warehouse): Bin
    {
        return $this->cari($warehouse, BinType::Quarantine);
    }

    public function inTransit(Warehouse $warehouse): Bin
    {
        return $this->cari($warehouse, BinType::InTransit);
    }

    /** Bin Retur gudang penerima GRN retur (BR-RET-04, A-112). */
    public function returnBin(Warehouse $warehouse): Bin
    {
        return $this->cari($warehouse, BinType::Return);
    }

    private function cari(Warehouse $warehouse, BinType $type): Bin
    {
        $bin = Bin::query()->withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('bin_type', $type->value)
            ->orderBy('id')
            ->first();

        if ($bin === null) {
            throw ReceiptRuleException::rule(
                'BR-WH-02',
                'Gudang '.$warehouse->code.' belum punya bin '.$type->label().'. Simpan ulang gudangnya untuk membuatnya.',
            );
        }

        return $bin;
    }
}
