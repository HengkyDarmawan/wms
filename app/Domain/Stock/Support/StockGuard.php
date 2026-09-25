<?php

declare(strict_types=1);

namespace App\Domain\Stock\Support;

use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockReservation;

/**
 * BR-GEN-04 — master dengan saldo atau reservasi bukan nol tidak bisa
 * dinonaktifkan.
 *
 * Penjagaan ini sengaja ditunda saat modul Master dan Warehouse dibangun,
 * karena tabel saldo belum ada. Sekarang ada, jadi kedua modul itu memanggil
 * kelas ini alih-alih memeriksa sendiri — dan tetap berjalan ketika modul
 * Stock belum dipasang, karena pemeriksaannya mengembalikan nol.
 */
class StockGuard
{
    /** Saldo bukan nol di satu gudang. */
    public function warehouseBalance(int $warehouseId): float
    {
        return (float) StockBalance::query()
            ->whereHas('bin', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('qty_base');
    }

    public function warehouseReservations(int $warehouseId): int
    {
        return StockReservation::query()->active()->where('warehouse_id', $warehouseId)->count();
    }

    public function binBalance(int $binId): float
    {
        return (float) StockBalance::query()->where('bin_id', $binId)->sum('qty_base');
    }

    public function binReservations(int $binId): int
    {
        return StockReservation::query()->active()->where('bin_id', $binId)->count();
    }

    public function itemBalance(int $itemId): float
    {
        return (float) StockBalance::query()->where('item_id', $itemId)->sum('qty_base');
    }

    public function itemReservations(int $itemId): int
    {
        return StockReservation::query()->active()->where('item_id', $itemId)->count();
    }

    /**
     * Pesan penolakan yang menyebut angkanya, atau null bila boleh dinonaktifkan.
     */
    public function refuseWarehouse(int $warehouseId): ?string
    {
        return $this->pesan(
            $this->warehouseBalance($warehouseId),
            $this->warehouseReservations($warehouseId),
            'Gudang ini',
        );
    }

    public function refuseBin(int $binId): ?string
    {
        return $this->pesan($this->binBalance($binId), $this->binReservations($binId), 'Bin ini');
    }

    public function refuseItem(int $itemId): ?string
    {
        return $this->pesan($this->itemBalance($itemId), $this->itemReservations($itemId), 'Item ini');
    }

    private function pesan(float $saldo, int $reservasi, string $subjek): ?string
    {
        if ($saldo > 0) {
            return $subjek.' masih punya saldo '
                .rtrim(rtrim(number_format($saldo, 4, '.', ''), '0'), '.')
                .'. Kosongkan stoknya dulu.'; // BR-GEN-04
        }

        if ($reservasi > 0) {
            return $subjek.' masih punya '.$reservasi.' reservasi aktif. Lepaskan dulu.'; // BR-GEN-04
        }

        return null;
    }
}
