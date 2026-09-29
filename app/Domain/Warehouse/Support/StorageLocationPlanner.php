<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Master\Models\Item;
use App\Domain\Receipt\Support\PutawaySuggester;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Saran bin dari **Tempat Simpan** (A-365, keputusan #3 & #4) — satu tempat
 * untuk put-away (Bagian 4) dan saldo awal (Bagian 5).
 *
 * Urutan: tempat simpan item di gudang itu menurut `sequence`:
 *  - **bin tertentu** → bin itu;
 *  - **seluruh rak** → bin yang sudah berisi barang itu dulu, lalu bin kosong
 *    dari tingkat bawah (L1) ke atas, kiri ke kanan (urut kode petak);
 *    bin berisi barang lain dilewati;
 *  - **area lantai** → bin areanya.
 * Bin nonaktif/beku/tergabung dan bin yang Khusus untuk barang lain dilewati.
 * Tempat yang **penuh** (kapasitas seluruh isi bin, A-361) dilewati ke tempat
 * berikutnya; bila semuanya penuh, dipakai aturan lama A-84
 * ({@see PutawaySuggester::suggestByRule()}) dengan tanda `penuh` — tidak ditolak.
 */
class StorageLocationPlanner
{
    public function __construct(private readonly StoragePolicy $khusus) {}

    /**
     * @param  array<int, float>  $planned  bin_id => jumlah yang sudah direncanakan di dokumen yang sama
     * @return array{bin: ?Bin, penuh: bool, dari_tempat_simpan: bool}
     */
    public function saranBin(Item $item, Warehouse $gudang, float $qty = 0.0, array $planned = []): array
    {
        $kandidat = $this->kandidat($item, $gudang);

        if ($kandidat->isNotEmpty()) {
            $isi = $this->isiBin($kandidat->pluck('id')->all());

            foreach ($kandidat as $bin) {
                $total = (float) ($isi[$bin->id]['total'] ?? 0) + (float) ($planned[$bin->id] ?? 0);

                if (! $bin->exceedsCapacity($total + $qty)) {
                    return ['bin' => $bin, 'penuh' => false, 'dari_tempat_simpan' => true];
                }
            }
        }

        $lama = app(PutawaySuggester::class)->suggestByRule($item, $gudang, $qty, $planned);

        return ['bin' => $lama, 'penuh' => $kandidat->isNotEmpty(), 'dari_tempat_simpan' => false];
    }

    /**
     * Bin calon dari tempat simpan item, berurutan (tanpa pemeriksaan kapasitas).
     *
     * @return Collection<int, Bin>
     */
    public function kandidat(Item $item, Warehouse $gudang): Collection
    {
        $tempat = ItemStorageLocation::query()->withoutGlobalScopes()
            ->with('rack:id,is_area')
            ->where('item_id', $item->id)->where('warehouse_id', $gudang->id)
            ->orderBy('sequence')->orderBy('id')->get();

        if ($tempat->isEmpty()) {
            return collect();
        }

        $rakIds = $tempat->pluck('rack_id')->filter()->all();
        $binRak = $rakIds === [] ? collect() : $this->binLayak($gudang)
            ->whereIn('rack_levels.rack_id', $rakIds)
            ->get(['bins.*', 'rack_levels.rack_id as rak_id', 'rack_levels.code as kode_level'])
            ->groupBy('rak_id');
        $binTunggal = $this->binLayak($gudang)->whereIn('bins.id', $tempat->pluck('bin_id')->filter()->all())->get(['bins.*'])->keyBy('id');

        $semua = $binRak->flatten()->pluck('id')->merge($binTunggal->keys())->unique()->all();
        $isi = $this->isiBin($semua);
        $hasil = collect();

        foreach ($tempat as $t) {
            if ($t->bin_id !== null) {
                if (($b = $binTunggal->get($t->bin_id)) !== null) {
                    $hasil->push($b);
                }

                continue;
            }

            $bins = ($binRak->get($t->rack_id) ?? collect())
                ->sortBy(fn (Bin $b) => sprintf('%08d|%s', (int) preg_replace('/\D/', '', (string) $b->kode_level), $b->code))
                ->values();
            $berisiItem = $bins->filter(fn (Bin $b) => in_array((int) $item->id, $isi[$b->id]['items'] ?? [], true));
            $kosong = $bins->filter(fn (Bin $b) => ($isi[$b->id]['total'] ?? 0) <= 0);

            $hasil = $hasil->merge($berisiItem)->merge($kosong);
        }

        return $hasil->unique('id')
            ->filter(fn (Bin $b) => ($p = $this->khusus->pemilikKhusus($b)) === [] || in_array((int) $item->id, $p, true))
            ->values();
    }

    /** Bin penyimpanan aktif, bukan tergabung, milik gudang ini (dengan tingkat raknya). */
    private function binLayak(Warehouse $gudang): Builder
    {
        return Bin::query()->withoutGlobalScopes()
            ->with('mergedBins')
            ->join('rack_levels', 'rack_levels.id', '=', 'bins.rack_level_id')
            ->where('bins.warehouse_id', $gudang->id)
            ->where('bins.bin_type', BinType::Storage->value)
            ->where('bins.bin_status', BinStatus::Active->value)
            ->whereNull('bins.occupied_by_bin_id')
            ->where('rack_levels.is_active', true);
    }

    /**
     * @param  array<int, int>  $binIds
     * @return array<int, array{total: float, items: array<int, int>}>
     */
    public function isiBin(array $binIds): array
    {
        $hasil = [];

        if ($binIds === []) {
            return $hasil;
        }

        StockBalance::query()->withoutGlobalScopes()
            ->whereIn('bin_id', $binIds)
            ->where('qty_base', '>', 0)
            ->get(['bin_id', 'item_id', 'qty_base'])
            ->each(function (StockBalance $s) use (&$hasil): void {
                $hasil[$s->bin_id]['total'] = ($hasil[$s->bin_id]['total'] ?? 0) + (float) $s->qty_base;
                $hasil[$s->bin_id]['items'][] = (int) $s->item_id;
            });

        return $hasil;
    }
}
