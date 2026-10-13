<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\BinUsage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `bin.manage` — hapus zona, rak, atau tingkat yang **belum pernah
 * dipakai** (A-410; perluasan A-362 dari bin ke induknya).
 *
 * Boleh hanya bila semua bin di bawahnya belum dirujuk data mana pun
 * ({@see BinUsage}); bin dalam gabungan harus dipisah dulu. Selain itu hanya
 * bisa dinonaktifkan ({@see DeactivateLocation}, P-03, BR-WH-07). Tempat simpan
 * barang yang menunjuk ke sana hanya pengaturan — ikut dilepas (A-368).
 */
class DeleteLocation
{
    public function __construct(private readonly BinUsage $pemakaian) {}

    public function zone(Zone $zone, ?User $actor = null): void
    {
        $rak = $zone->racks()->get();
        $this->bolehHapus($this->binDari(RackLevel::query()->whereIn('rack_id', $rak->pluck('id'))->pluck('id')), 'Zona '.$zone->code);

        DB::transaction(function () use ($zone, $rak, $actor): void {
            foreach ($rak as $r) {
                $this->hapusRak($r, $actor);
            }

            $zone->disableLogging()->delete();
            activity('warehouse')->performedOn($zone->warehouse)->causedBy($actor)
                ->withProperties(['zona' => $zone->code])->log('Zona '.$zone->code.' dihapus (belum pernah dipakai)');
        });
    }

    public function rack(Rack $rack, ?User $actor = null): void
    {
        $this->bolehHapus($this->binDari($rack->levels()->pluck('id')), 'Rak '.$rack->code);

        DB::transaction(function () use ($rack, $actor): void {
            $this->hapusRak($rack, $actor);
            activity('warehouse')->performedOn($rack->zone)->causedBy($actor)
                ->withProperties(['rak' => $rack->code])->log('Rak '.$rack->code.' dihapus (belum pernah dipakai)');
        });
    }

    public function level(RackLevel $level, ?User $actor = null): void
    {
        $bins = $this->binDari(collect([$level->id]));
        $this->bolehHapus($bins, 'Tingkat '.$level->code);

        DB::transaction(function () use ($level, $bins, $actor): void {
            $this->hapusBin($bins, $actor);
            $level->disableLogging()->delete();
            activity('warehouse')->performedOn($level->rack)->causedBy($actor)
                ->withProperties(['tingkat' => $level->code])->log('Tingkat '.$level->code.' dihapus (belum pernah dipakai)');
        });
    }

    /**
     * Alasan lokasi ini tidak boleh dihapus, atau null bila boleh — dipakai layar
     * untuk memilih tombol Hapus atau Nonaktifkan.
     *
     * @param  array<int, true>|null  $dipakai  hasil {@see BinUsage::dipakai()} yang sudah dihitung
     */
    public function alasanTolak(Collection $bins, string $nama, ?array $dipakai = null): ?string
    {
        if ($bins->contains(fn (Bin $b) => $b->isMerged() || $b->mergedBins()->exists())) {
            return $nama.' berisi bin yang sedang digabung. Pisah dulu.';
        }

        $dipakai ??= $this->pemakaian->dipakai($bins->pluck('id')->map(fn ($id) => (int) $id)->all());
        $contoh = $bins->first(fn (Bin $b) => isset($dipakai[(int) $b->id]));

        return $contoh === null ? null : $nama.' tidak bisa dihapus karena bin '.$contoh->code.' pernah dipakai. Nonaktifkan saja.';
    }

    /** @param  Collection<int, Bin>  $bins */
    private function bolehHapus(Collection $bins, string $nama): void
    {
        if (($alasan = $this->alasanTolak($bins, $nama)) !== null) {
            throw WarehouseRuleException::rule('BR-WH-09', $alasan);
        }
    }

    private function hapusRak(Rack $rack, ?User $actor): void
    {
        $levels = $rack->levels()->pluck('id');
        $this->hapusBin($this->binDari($levels), $actor);

        // A-368: tempat simpan "seluruh rak" hanya pengaturan — ikut dilepas.
        foreach (ItemStorageLocation::query()->withoutGlobalScopes()->with('item')->where('rack_id', $rack->id)->get() as $t) {
            $t->delete();
            activity('master')->performedOn($t->item)->causedBy($actor)
                ->withProperties(['rak' => $rack->code])->log('Tempat simpan rak '.$rack->code.' dilepas karena rak dihapus');
        }

        RackLevel::query()->whereIn('id', $levels)->delete();
        $rack->disableLogging()->delete();
    }

    /** @param  Collection<int, Bin>  $bins */
    private function hapusBin(Collection $bins, ?User $actor): void
    {
        foreach ($bins as $b) {
            foreach (ItemStorageLocation::query()->withoutGlobalScopes()->with('item')->where('bin_id', $b->id)->get() as $t) {
                $t->delete();
                activity('master')->performedOn($t->item)->causedBy($actor)
                    ->withProperties(['bin' => $b->code])->log('Tempat simpan '.$b->code.' dilepas karena bin dihapus');
            }

            $b->disableLogging()->delete();
        }
    }

    /** @return Collection<int, Bin> */
    private function binDari(Collection $levelIds): Collection
    {
        return Bin::query()->withoutGlobalScopes()->whereIn('rack_level_id', $levelIds)->get();
    }
}
