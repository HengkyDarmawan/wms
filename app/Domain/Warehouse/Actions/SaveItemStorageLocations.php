<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `bin.manage` (keputusan #5) — **Tempat Simpan** satu item di satu
 * gudang (K-A, A-365/A-366).
 *
 * {@see replace()} mengganti seluruh daftar berurutan (kartu di detail item);
 * {@see add()}/{@see remove()} menambah/melepas satu tempat (mode Tata letak
 * Denah, impor Excel). Tempat = `bin:{id}` (bin tertentu) atau `rak:{id}`
 * (seluruh rak; rak area = area lantai), selalu di gudang yang sama.
 *
 * Aturan: bin penyimpanan aktif di rak (bukan bin sistem/virtual, tidak
 * tergabung), rak aktif; tanpa tempat ganda; cakupan gudang (BR-ACC-05).
 * **Khusus Barang Ini** (BR-WH-10): tempat yang seluruhnya di dalam tempat
 * khusus barang lain ditolak; menandai Khusus ditolak bila barang lain sudah
 * bertempat di dalamnya. Tumpang-tindih sebagian dibiarkan — saran bin
 * melewati bin yang khusus untuk barang lain.
 */
class SaveItemStorageLocations
{
    public const MAKS_TEMPAT = 50;

    /**
     * @param  array<int, array{tempat?: string, khusus?: bool|string|int|null}>  $baris  berurutan
     * @return array<int, ItemStorageLocation>
     */
    public function replace(Item $item, Warehouse $gudang, array $baris, ?User $actor = null): array
    {
        $this->cakupan($gudang, $actor);

        $baris = array_values(array_filter($baris, fn ($b) => trim((string) ($b['tempat'] ?? '')) !== ''));

        if (count($baris) > self::MAKS_TEMPAT) {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Paling banyak '.self::MAKS_TEMPAT.' tempat simpan per barang per gudang.');
        }

        if ($baris !== [] && $item->status !== ItemStatus::Active) {
            throw WarehouseRuleException::rule('BR-REQ-03', 'Barang '.$item->code.' berstatus '.$item->status->label().'; tempat simpan hanya untuk barang aktif.');
        }

        $rencana = [];
        $dilihat = [];

        foreach ($baris as $i => $b) {
            [$bin, $rak] = $this->tempat($gudang, (string) $b['tempat']);
            $kunci = $bin !== null ? 'bin:'.$bin->id : 'rak:'.$rak->id;

            if (isset($dilihat[$kunci])) {
                throw WarehouseRuleException::rule('BR-GEN-11', 'Tempat '.$this->nama($bin, $rak).' tercantum dua kali.');
            }

            $dilihat[$kunci] = true;
            $khusus = filter_var($b['khusus'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $this->periksaKhusus($item, $bin, $rak, $khusus);
            $rencana[] = ['bin' => $bin, 'rak' => $rak, 'khusus' => $khusus, 'urut' => $i + 1];
        }

        $lama = $this->daftar($item, $gudang)->map(fn (ItemStorageLocation $t) => $this->ringkas($t))->all();

        return DB::transaction(function () use ($item, $gudang, $rencana, $actor, $lama) {
            ItemStorageLocation::query()->withoutGlobalScopes()
                ->where('item_id', $item->id)->where('warehouse_id', $gudang->id)->delete();

            $hasil = [];

            foreach ($rencana as $r) {
                $hasil[] = ItemStorageLocation::create([
                    'item_id' => $item->id,
                    'warehouse_id' => $gudang->id,
                    'bin_id' => $r['bin']?->id,
                    'rack_id' => $r['rak']?->id,
                    'sequence' => $r['urut'],
                    'is_dedicated' => $r['khusus'],
                    'updated_by' => $actor?->id,
                ]);
            }

            $baru = array_map(fn ($r) => $this->nama($r['bin'], $r['rak']).($r['khusus'] ? ' (khusus)' : ''), $rencana);

            if ($baru !== $lama) {
                activity('master')->performedOn($item)->causedBy($actor)
                    ->withProperties(['gudang' => $gudang->code, 'sebelum' => $lama, 'sesudah' => $baru])
                    ->log('Tempat simpan di gudang '.$gudang->code.' diubah: '.($baru === [] ? '—' : implode(', ', $baru)));
            }

            return $hasil;
        });
    }

    /** Tambah satu tempat di akhir urutan; bila sudah ada, tanda Khusus-nya diperbarui. */
    public function add(Item $item, Warehouse $gudang, string $tempat, bool $khusus = false, ?User $actor = null): void
    {
        $baris = $this->barisSekarang($item, $gudang);
        [$bin, $rak] = $this->tempat($gudang, $tempat);
        $kunci = $bin !== null ? 'bin:'.$bin->id : 'rak:'.$rak->id;
        $ada = false;

        foreach ($baris as $i => $b) {
            if ($b['tempat'] === $kunci) {
                $baris[$i]['khusus'] = $khusus;
                $ada = true;
            }
        }

        if (! $ada) {
            $baris[] = ['tempat' => $kunci, 'khusus' => $khusus];
        }

        $this->replace($item, $gudang, $baris, $actor);
    }

    /** Lepas satu tempat dari daftar item (tidak ada = ditolak). */
    public function remove(Item $item, Warehouse $gudang, string $tempat, ?User $actor = null): void
    {
        $baris = $this->barisSekarang($item, $gudang);
        [$bin, $rak] = $this->tempat($gudang, $tempat);
        $kunci = $bin !== null ? 'bin:'.$bin->id : 'rak:'.$rak->id;
        $sisa = array_values(array_filter($baris, fn ($b) => $b['tempat'] !== $kunci));

        if (count($sisa) === count($baris)) {
            throw WarehouseRuleException::rule('BR-GEN-11', $item->code.' tidak bertempat di '.$this->nama($bin, $rak).'.');
        }

        $this->replace($item, $gudang, $sisa, $actor);
    }

    /** @return array<int, array{tempat: string, khusus: bool}> */
    public function barisSekarang(Item $item, Warehouse $gudang): array
    {
        return $this->daftar($item, $gudang)
            ->map(fn (ItemStorageLocation $t) => ['tempat' => $t->kunci(), 'khusus' => (bool) $t->is_dedicated])
            ->all();
    }

    /**
     * Bin atau rak dari kunci `bin:{id}` / `rak:{id}` di gudang ini.
     *
     * @return array{0: ?Bin, 1: ?Rack}
     */
    public function tempat(Warehouse $gudang, string $kunci): array
    {
        if (preg_match('/^(bin|rak):(\d+)$/', trim($kunci), $m) !== 1) {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Tempat simpan tidak dikenal.');
        }

        if ($m[1] === 'rak') {
            $rak = Rack::query()->with('zone')->whereHas('zone', fn ($q) => $q->where('warehouse_id', $gudang->id))->find((int) $m[2]);

            if ($rak === null || ! $rak->is_active || ! $rak->zone->is_active) {
                throw WarehouseRuleException::rule('BR-WH-07', 'Rak tidak ada di gudang '.$gudang->code.' atau nonaktif.');
            }

            return [null, $rak];
        }

        $bin = Bin::query()->withoutGlobalScopes()->with('rackLevel.rack')->where('warehouse_id', $gudang->id)->find((int) $m[2]);

        if ($bin === null || $bin->bin_type !== BinType::Storage || $bin->isSystemBin() || $bin->rack_level_id === null) {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Tempat simpan harus bin penyimpanan di rak gudang '.$gudang->code.'.');
        }

        if ($bin->bin_status === BinStatus::Inactive) {
            throw WarehouseRuleException::rule('BR-WH-07', 'Bin '.$bin->code.' nonaktif.');
        }

        if ($bin->isMerged()) {
            throw WarehouseRuleException::rule('BR-WH-08', 'Bin '.$bin->code.' tergabung ke bin utama '.$bin->mainBin?->code.'; pilih bin utamanya.');
        }

        // Bin area lantai dicatat sebagai area (rak areanya), bukan bin.
        if ($bin->rackLevel?->rack?->is_area) {
            return [null, $bin->rackLevel->rack->loadMissing('zone')];
        }

        return [$bin, null];
    }

    /**
     * BR-WH-10 saat menyimpan: tempat di dalam tempat khusus barang lain ditolak;
     * menandai Khusus ditolak bila barang lain sudah bertempat di dalamnya.
     */
    private function periksaKhusus(Item $item, ?Bin $bin, ?Rack $rak, bool $khusus): void
    {
        $rakId = $rak?->id ?? $bin?->rackLevel?->rack_id;
        $lain = ItemStorageLocation::query()->withoutGlobalScopes()->with('item:id,code')
            ->where('item_id', '!=', $item->id);

        // Tempat ini seluruhnya di dalam tempat khusus barang lain?
        $pemilik = (clone $lain)->where('is_dedicated', true)
            ->where(fn ($q) => $bin !== null
                ? $q->where('bin_id', $bin->id)->orWhere('rack_id', $rakId)
                : $q->where('rack_id', $rakId))
            ->get();

        if ($pemilik->isNotEmpty()) {
            throw WarehouseRuleException::rule('BR-WH-10', 'Tempat '.$this->nama($bin, $rak).' khusus untuk barang '
                .$pemilik->pluck('item.code')->unique()->sort()->implode(', ').'.');
        }

        if (! $khusus) {
            return;
        }

        $binDiDalam = $bin !== null ? [$bin->id]
            : Bin::query()->withoutGlobalScopes()->whereHas('rackLevel', fn ($q) => $q->where('rack_id', $rakId))->pluck('id')->all();
        $sudah = (clone $lain)
            ->where(fn ($q) => $q->whereIn('bin_id', $binDiDalam)->when($bin === null, fn ($q) => $q->orWhere('rack_id', $rakId)))
            ->get();

        if ($sudah->isNotEmpty()) {
            throw WarehouseRuleException::rule('BR-WH-10', 'Tempat '.$this->nama($bin, $rak).' sudah menjadi tempat simpan barang '
                .$sudah->pluck('item.code')->unique()->sort()->implode(', ').'; lepas dulu atau jangan tandai Khusus.');
        }
    }

    private function cakupan(Warehouse $gudang, ?User $actor): void
    {
        if ($actor !== null && ! $actor->canAccessWarehouse((int) $gudang->id)) {
            throw WarehouseRuleException::rule('BR-GEN-09', 'Gudang '.$gudang->code.' di luar cakupan Anda.');
        }
    }

    /** @return Collection<int, ItemStorageLocation> */
    private function daftar(Item $item, Warehouse $gudang): Collection
    {
        return ItemStorageLocation::query()->withoutGlobalScopes()->with('bin:id,code', 'rack.zone')
            ->where('item_id', $item->id)->where('warehouse_id', $gudang->id)
            ->orderBy('sequence')->orderBy('id')->get();
    }

    private function ringkas(ItemStorageLocation $t): string
    {
        return $t->label().($t->is_dedicated ? ' (khusus)' : '');
    }

    private function nama(?Bin $bin, ?Rack $rak): string
    {
        if ($bin !== null) {
            return BinCode::pendek((string) $bin->code);
        }

        $zona = $rak?->zone?->code;

        return $rak?->is_area
            ? 'Area '.($zona ? $zona.' · ' : '').$rak->code
            : 'Rak '.($zona ? $zona.' · ' : '').$rak?->code.' (seluruh rak)';
    }
}
