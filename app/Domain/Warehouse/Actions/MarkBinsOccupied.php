<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `bin.manage` — barang besar yang tak terduga memakan bin
 * sebelahnya (A-255). Stok tetap dicatat di **satu bin utama**; bin tetangga
 * hanya ditandai "ikut terpakai" supaya tidak disarankan put-away dan tampil
 * di denah. Penanda dilepas manual, atau otomatis saat bin utama kosong
 * ({@see releaseWhenEmpty()}, observer kartu stok).
 */
class MarkBinsOccupied
{
    /** @param  array<int, int|string>  $binIds */
    public function handle(Bin $utama, array $binIds, ?string $reason, ?User $actor = null): int
    {
        $alasan = trim((string) $reason);

        if ($alasan === '') {
            throw WarehouseRuleException::fields(['occupied_reason' => 'Alasan wajib diisi, mis. "genset besar memakan 2 bin".'], 'BR-GEN-11');
        }

        if ($utama->bin_type !== BinType::Storage || $utama->isOccupied()) {
            throw WarehouseRuleException::fields(['occupied_by' => 'Bin utama harus bin penyimpanan yang tidak sedang ikut terpakai.'], 'BR-WH-06');
        }

        if (! $this->berisi($utama)) {
            throw WarehouseRuleException::fields(['occupied_by' => 'Bin utama '.$utama->code.' masih kosong; catat barangnya di bin itu dulu.'], 'BR-WH-06');
        }

        $bins = Bin::query()->withoutGlobalScopes()->whereIn('id', array_map('intval', $binIds))->get();

        if ($bins->isEmpty()) {
            throw WarehouseRuleException::fields(['bins' => 'Pilih minimal satu bin tetangga.'], 'BR-GEN-11');
        }

        foreach ($bins as $b) {
            if ((int) $b->id === (int) $utama->id || (int) $b->warehouse_id !== (int) $utama->warehouse_id || $b->bin_type !== BinType::Storage) {
                throw WarehouseRuleException::fields(['bins' => 'Bin '.$b->code.' bukan bin penyimpanan lain di gudang yang sama.'], 'BR-WH-06');
            }

            if ($this->berisi($b)) {
                throw WarehouseRuleException::fields(['bins' => 'Bin '.$b->code.' masih berisi stok; hanya bin kosong yang bisa ditandai ikut terpakai.'], 'BR-WH-06');
            }
        }

        DB::transaction(function () use ($bins, $utama, $alasan, $actor) {
            foreach ($bins as $b) {
                $b->forceFill(['occupied_by_bin_id' => $utama->id, 'occupied_reason' => mb_substr($alasan, 0, 255), 'occupied_at' => now()])->save();

                activity('warehouse')->performedOn($b)->causedBy($actor)
                    ->withProperties(['bin_utama' => $utama->code, 'alasan' => $alasan])
                    ->log('Bin ditandai ikut terpakai oleh '.$utama->code);
            }
        });

        return $bins->count();
    }

    /** Lepas penanda satu bin (manual). */
    public function release(Bin $bin, ?User $actor = null): void
    {
        if (! $bin->isOccupied()) {
            return;
        }

        $utama = $bin->occupiedBy?->code;
        $bin->forceFill(['occupied_by_bin_id' => null, 'occupied_reason' => null, 'occupied_at' => null])->save();

        activity('warehouse')->performedOn($bin)->causedBy($actor)->log('Penanda ikut terpakai oleh '.$utama.' dilepas');
    }

    /**
     * Observer kartu stok: barang keluar dari bin utama dan bin itu kosong →
     * semua bin yang ikut terpakai olehnya dilepas otomatis.
     */
    public function releaseWhenEmpty(StockMovement $m): void
    {
        if ($m->from_bin_id === null || ! Bin::query()->withoutGlobalScopes()->where('occupied_by_bin_id', $m->from_bin_id)->exists()) {
            return;
        }

        $utama = Bin::query()->withoutGlobalScopes()->find($m->from_bin_id);

        if ($utama === null || $this->berisi($utama)) {
            return;
        }

        Bin::query()->withoutGlobalScopes()->where('occupied_by_bin_id', $utama->id)->get()
            ->each(fn (Bin $b) => $this->release($b));
    }

    private function berisi(Bin $bin): bool
    {
        return StockBalance::query()->withoutGlobalScopes()->where('bin_id', $bin->id)->where('qty_base', '>', 0.00005)->exists();
    }
}
