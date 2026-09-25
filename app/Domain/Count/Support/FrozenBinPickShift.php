<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Access\Models\User;
use App\Domain\Count\Models\CountLine;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Models\Bin;

/**
 * Akibat override picking dari bin beku (BR-OPN-02, A-240) pada sesi opname
 * yang membekukannya.
 *
 * Selisih sesi diposting sebagai delta (`ReconcileStockCount`), jadi yang
 * penting semua angka satu baris berada di pijakan yang sama. Pengambilan X
 * adalah pergerakan tercatat, maka angka sistem **dan** hitungan yang sudah
 * masuk (diambil sebelum pengambilan) sama-sama digeser −X; hitungan yang
 * masuk sesudahnya sudah mencerminkan barang yang tersisa. Selisih yang
 * sebenarnya tidak berubah. Bin lalu ditandai ⚑ supaya dihitung ulang (A-67).
 */
class FrozenBinPickShift
{
    private const KOLOM = ['system_qty', 'counted_qty_r1', 'counted_qty_r2', 'final_qty'];

    public function __construct(private readonly ChangeBinStatus $binStatus) {}

    public function apply(Bin $bin, int $itemId, ?int $lotId, ?int $serialId, ?int $pieceId, float $qty, ?User $actor = null): void
    {
        if ($bin->frozen_by_count_id !== null) {
            $line = CountLine::query()
                ->where('stock_count_id', $bin->frozen_by_count_id)
                ->where('bin_id', $bin->id)
                ->where('item_id', $itemId)
                ->where('stock_status', StockStatus::Available->value)
                ->when($lotId !== null, fn ($q) => $q->where('lot_id', $lotId), fn ($q) => $q->whereNull('lot_id'))
                ->when($serialId !== null, fn ($q) => $q->where('serial_id', $serialId), fn ($q) => $q->whereNull('serial_id'))
                ->when($pieceId !== null, fn ($q) => $q->where('piece_id', $pieceId), fn ($q) => $q->whereNull('piece_id'))
                ->first();

            if ($line !== null) {
                $geser = [];

                foreach (self::KOLOM as $kolom) {
                    if ($line->{$kolom} !== null) {
                        $geser[$kolom] = max(0.0, round((float) $line->{$kolom} - $qty, 4));
                    }
                }

                $line->forceFill($geser)->save();

                activity('count')->performedOn($line->stockCount)->causedBy($actor)
                    ->withProperties(['bin' => $bin->code, 'baris' => $line->id, 'qty' => $qty])
                    ->log('Angka bin '.$bin->code.' digeser karena picking override bin beku');
            }
        }

        $this->binStatus->flagForCount($bin, true, $actor);
    }
}
