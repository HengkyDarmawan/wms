<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Support;

use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Models\ConversionOutput;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Models\StockReservation;

/**
 * BR-CNV-05 / BR-GEN-04: CNV `completed` hanya bisa dibalik bila semua
 * output, offcut, dan waste masih ada di bin tujuannya dan belum dipakai
 * dokumen lain (A-157).
 *
 * Per kombinasi bin × item × lot × potongan × kondisi: saldo dikurangi alokasi
 * keras harus ≥ jumlah hasil CNV; potongan baru tidak boleh punya pergerakan
 * lain setelah CNV. Diperiksa saat pembalik dibuat dan diulang saat posting.
 */
class ConversionReversibility
{
    public function assert(Conversion $cnv): void
    {
        $hasil = ConversionOutput::query()->with('item:id,code', 'bin:id,code')
            ->where('conversion_id', $cnv->id)
            ->whereNotNull('movement_id')
            ->orderBy('id')->get();

        $perKunci = [];

        foreach ($hasil as $o) {
            if ($o->new_piece_id !== null) {
                $dipakai = StockMovement::query()
                    ->where('piece_id', $o->new_piece_id)
                    ->where('id', '>', $o->movement_id)
                    ->whereNull('reverses_movement_id')
                    ->exists();

                if ($dipakai) {
                    throw ConversionRuleException::rule('BR-CNV-05', 'Potongan '.$o->trackingLabel().' hasil '.$cnv->number.' sudah dipakai dokumen lain; CNV tidak bisa dibalik.');
                }
            }

            $kunci = implode('|', [$o->bin_id, $o->item_id, $o->lot_id ?? 0, $o->new_piece_id ?? 0, $o->stock_status?->value]);
            $perKunci[$kunci] ??= ['baris' => $o, 'qty' => 0.0];
            $perKunci[$kunci]['qty'] = round($perKunci[$kunci]['qty'] + (float) $o->qty_base, 4);
        }

        foreach ($perKunci as ['baris' => $o, 'qty' => $jumlah]) {
            $saldo = (float) StockBalance::query()
                ->where('bin_id', $o->bin_id)->where('item_id', $o->item_id)
                ->where(fn ($q) => $o->lot_id === null ? $q->whereNull('lot_id') : $q->where('lot_id', $o->lot_id))
                ->where(fn ($q) => $o->new_piece_id === null ? $q->whereNull('piece_id') : $q->where('piece_id', $o->new_piece_id))
                ->whereNull('serial_id')
                ->where('stock_status', $o->stock_status?->value)
                ->sum('qty_base');

            $keras = (float) StockReservation::query()->active()
                ->where('level', ReservationLevel::Hard->value)
                ->where('bin_id', $o->bin_id)->where('item_id', $o->item_id)
                ->where(fn ($q) => $o->lot_id === null ? $q->whereNull('lot_id') : $q->where('lot_id', $o->lot_id))
                ->where(fn ($q) => $o->new_piece_id === null ? $q->whereNull('piece_id') : $q->where('piece_id', $o->new_piece_id))
                ->sum('qty_base');

            if ($saldo - $keras - $jumlah < -0.00005) {
                throw ConversionRuleException::rule('BR-CNV-05', 'Hasil '.$o->item?->code.' '.$o->trackingLabel().' di bin '.$o->bin?->code.' tinggal '
                    .ConvertibleStock::angka(max(0, $saldo - $keras)).' dari '.ConvertibleStock::angka($jumlah).'; sebagian sudah dipakai atau dialokasikan, CNV tidak bisa dibalik.');
            }
        }
    }
}
