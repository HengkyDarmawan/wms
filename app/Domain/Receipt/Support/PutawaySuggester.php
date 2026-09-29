<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Support;

use App\Domain\Master\Models\Item;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\StorageLocationPlanner;
use App\Domain\Warehouse\Support\StoragePolicy;
use Illuminate\Support\Collection;

/**
 * Saran bin put-away (BR-GRN-03, BR-WH-06).
 *
 * Aturan Fase 1, berurutan — yang pertama cocok menang:
 *
 * 1. bin penyimpanan yang sudah menyimpan item yang sama (barang sejenis
 *    berkumpul, picking lebih pendek);
 * 2. bin kosong yang kategori penyimpanannya sama dengan kategori
 *    penyimpanan bawaan kategori item;
 * 3. bin kosong tanpa kategori penyimpanan;
 * 4. tidak ada saran — staf memilih sendiri.
 *
 * Di setiap langkah bin yang akan melampaui kapasitasnya dilewati, apa pun
 * `capacity_mode`-nya: saran tidak boleh menjerumuskan staf ke peringatan atau
 * penolakan. Bin beku dan nonaktif tidak pernah disarankan (BR-OPN-02).
 * "Kedekatan zona" diwakili urutan kode bin, karena kode diturunkan dari
 * hierarki zona-rak-level (BR-WH-01).
 *
 * Bagian 3 (A-365): **Tempat Simpan** item didahulukan lewat
 * {@see StorageLocationPlanner::saranBin()}; aturan di atas menjadi cadangan
 * ({@see suggestByRule()}) dan tidak pernah menyarankan bin yang Khusus untuk
 * barang lain (BR-WH-10).
 */
class PutawaySuggester
{
    /**
     * @param  array<int, float>  $planned  bin_id => jumlah yang sudah disarankan di tugas yang sama
     */
    public function suggest(Item $item, Warehouse $warehouse, float $qty, array $planned = []): ?Bin
    {
        return app(StorageLocationPlanner::class)->saranBin($item, $warehouse, $qty, $planned)['bin'];
    }

    /**
     * Aturan lama A-84 (tanpa tempat simpan). Bin yang Khusus untuk barang lain
     * dilewati.
     *
     * @param  array<int, float>  $planned
     */
    public function suggestByRule(Item $item, Warehouse $warehouse, float $qty, array $planned = []): ?Bin
    {
        $khusus = app(StoragePolicy::class);
        $bins = $this->storageBins($warehouse)
            ->filter(fn (Bin $b) => ($p = $khusus->pemilikKhusus($b)) === [] || in_array((int) $item->id, $p, true))
            ->values();

        if ($bins->isEmpty()) {
            return null;
        }

        $isi = $this->contents($bins->pluck('id')->all());
        $total = fn (Bin $b) => (float) ($isi[$b->id]['total'] ?? 0) + (float) ($planned[$b->id] ?? 0);
        $muat = fn (Bin $b) => ! $b->exceedsCapacity($total($b) + $qty);
        $kosong = fn (Bin $b) => $total($b) <= 0;

        $sejenis = $bins->first(fn (Bin $b) => in_array($item->id, $isi[$b->id]['items'] ?? [], true) && $muat($b));

        if ($sejenis !== null) {
            return $sejenis;
        }

        $kategori = $item->category?->storage_category_id;

        if ($kategori !== null) {
            $cocok = $bins->first(fn (Bin $b) => (int) $b->storage_category_id === (int) $kategori && $kosong($b) && $muat($b));

            if ($cocok !== null) {
                return $cocok;
            }
        }

        return $bins->first(fn (Bin $b) => $b->storage_category_id === null && $kosong($b) && $muat($b));
    }

    /** @return Collection<int, Bin> bin penyimpanan aktif gudang, urut kode */
    public function storageBins(Warehouse $warehouse): Collection
    {
        return Bin::query()->withoutGlobalScopes()
            ->with('storageCategory', 'mergedBins')
            ->where('warehouse_id', $warehouse->id)
            ->where('bin_type', BinType::Storage->value)
            ->where('bin_status', BinStatus::Active->value)
            // A-359: bin tergabung tidak disarankan — stoknya dicatat di bin utama
            // (kapasitas bin utama = gabungan, Bin::effectiveCapacity).
            ->whereNull('occupied_by_bin_id')
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  array<int, int>  $binIds
     * @return array<int, array{total: float, items: array<int, int>}>
     */
    private function contents(array $binIds): array
    {
        $hasil = [];

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
