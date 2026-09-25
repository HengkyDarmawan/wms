<?php

declare(strict_types=1);

namespace App\Domain\Stock\Support;

use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Models\Item;
use Illuminate\Database\Eloquent\Builder;

/**
 * Urutan alokasi keras saat PCK dibuat menurut strategi pengambilan efektif
 * item (BR-STK-04, BR-STK-10, BR-STK-12, matriks §15, A-10, A-185):
 *
 * - `fifo`         : yang paling lama masuk dulu — tanggal terima lot, tanggal
 *                    perolehan serial, tanggal lahir potongan, lalu saat baris
 *                    saldo pertama kali terisi;
 * - `fefo`         : kedaluwarsa terdekat dulu, pemutus seri FIFO;
 * - `offcut_first` : sisa potongan dulu, pemutus seri FIFO;
 * - `manual`       : urut kode bin (staf memilih sendiri saat memetik).
 *
 * Lot/serial yang sudah kedaluwarsa tidak dialokasikan otomatis (A-185).
 * Pemutus seri terakhir selalu kode bin supaya hasilnya stabil.
 *
 * Query masukan harus sudah `join('bins as b', …)` dari `stock_balances`.
 */
class RemovalOrder
{
    public function apply(Builder $query, Item $item): Builder
    {
        $strategi = $item->effectiveRemovalStrategy();

        $query->leftJoin('lots as ro_l', 'ro_l.id', '=', 'stock_balances.lot_id')
            ->leftJoin('serials as ro_s', 'ro_s.id', '=', 'stock_balances.serial_id')
            ->leftJoin('pieces as ro_p', 'ro_p.id', '=', 'stock_balances.piece_id');

        $hariIni = now()->toDateString();
        $query->where(fn (Builder $q) => $q->whereNull('ro_l.expiry_date')->orWhere('ro_l.expiry_date', '>=', $hariIni))
            ->where(fn (Builder $q) => $q->whereNull('ro_s.expiry_date')->orWhere('ro_s.expiry_date', '>=', $hariIni));

        if ($strategi === RemovalStrategy::Manual) {
            return $query->orderBy('b.code')->orderBy('stock_balances.id');
        }

        if ($strategi === RemovalStrategy::Fefo) {
            $query->orderByRaw('COALESCE(ro_l.expiry_date, ro_s.expiry_date) IS NULL')
                ->orderByRaw('COALESCE(ro_l.expiry_date, ro_s.expiry_date) ASC');
        }

        if ($strategi === RemovalStrategy::OffcutFirst) {
            $query->orderByRaw('COALESCE(ro_p.is_offcut, 0) DESC');
        }

        return $query
            ->orderByRaw('COALESCE(ro_l.received_at, ro_s.acquired_at, DATE(ro_p.created_at), DATE(stock_balances.created_at)) ASC')
            ->orderBy('stock_balances.id')
            ->orderBy('b.code');
    }
}
