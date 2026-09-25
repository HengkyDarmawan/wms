<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Concerns;

use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use Illuminate\Support\Collection;

/**
 * Umur saldo dibaca dari kartu stok: baris `stock_movements` **terakhir** yang
 * memasukkan barang ke saldo itu (bin tujuan, item, lot/serial/potongan, dan
 * kondisi yang sama). Perubahan kondisi di tempat (mis. QC menolak di bin
 * Karantina) juga tercatat sebagai baris masuk ke bin yang sama berkondisi
 * baru (A-194), jadi ikut terbaca.
 *
 * Saldo yang terisi beberapa kali dihitung dari masuknya yang terakhir —
 * umur bagian termuda, bukan tertua. Ini sengaja: bagian lama bisa sudah
 * keluar lebih dulu, dan saldo tidak menyimpan asal per bagian (P-01).
 * "Terakhir" = id terbesar (kartu stok append-only).
 */
trait LatestInbound
{
    /**
     * @param  Collection<int, StockBalance>  $saldo
     * @return array<string, StockMovement> kunci saldo => baris masuk terakhir
     */
    protected function masukTerakhir(Collection $saldo): array
    {
        if ($saldo->isEmpty()) {
            return [];
        }

        $ids = StockMovement::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('to_bin_id', $saldo->pluck('bin_id')->unique()->values())
            ->whereIn('item_id', $saldo->pluck('item_id')->unique()->values())
            ->whereIn('stock_status', $saldo->map(fn (StockBalance $b) => $b->stock_status->value)->unique()->values())
            ->groupBy('item_id', 'to_bin_id', 'lot_id', 'serial_id', 'piece_id', 'stock_status')
            ->pluck('id');

        return StockMovement::query()
            ->whereIn('id', $ids)
            ->get(['id', 'item_id', 'to_bin_id', 'lot_id', 'serial_id', 'piece_id', 'stock_status', 'document_type', 'document_id', 'document_number', 'occurred_at'])
            ->keyBy(fn (StockMovement $m) => $this->kunciSaldo((int) $m->item_id, (int) $m->to_bin_id, $m->lot_id, $m->serial_id, $m->piece_id, $m->stock_status->value))
            ->all();
    }

    protected function kunciUntuk(StockBalance $b): string
    {
        return $this->kunciSaldo((int) $b->item_id, (int) $b->bin_id, $b->lot_id, $b->serial_id, $b->piece_id, $b->stock_status->value);
    }

    /** Umur dalam hari kalender zona company sejak baris masuk; null bila tidak ditemukan. */
    protected function umurHari(?StockMovement $masuk): ?int
    {
        return $masuk?->occurred_at === null ? null
            : (int) $masuk->occurred_at->lokal()->startOfDay()->diffInDays(now()->lokal()->startOfDay());
    }

    private function kunciSaldo(int $item, int $bin, mixed $lot, mixed $serial, mixed $piece, string $status): string
    {
        return implode(':', [$item, $bin, (int) $lot, (int) $serial, (int) $piece, $status]);
    }
}
