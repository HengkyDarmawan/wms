<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Models\Bin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * BR-OPN-11, A-424 — perencana cycle count ABC untuk satu gudang.
 *
 * Kelas dari **frekuensi mutasi** (WMS tanpa nilai uang, D-07): jumlah baris
 * kartu stok item yang menyentuh bin gudang itu dalam JENDELA_HARI terakhir,
 * diurutkan menurun lalu dibagi Pareto — A = penyumbang 80 % mutasi teratas,
 * B = 15 % berikutnya, C = sisanya (termasuk item bersaldo tanpa mutasi).
 * Jatuh tempo: belum pernah dihitung di gudang ini, atau hitungan terakhir
 * (sesi `approved`/`closed`) lebih lama dari jadwal kelasnya.
 *
 * Kelas tidak disimpan: dihitung saat dibutuhkan, jadi selalu mengikuti data.
 */
class AbcCyclePlanner
{
    /** A-427: angka bawaan, menunggu validasi pemilik produk. */
    public const JENDELA_HARI = 90;

    public const BATAS_A = 0.80;

    public const BATAS_B = 0.95;

    /** @var array<string, int> kelas => jadwal hitung (hari) */
    public const JADWAL_HARI = ['A' => 30, 'B' => 90, 'C' => 180];

    public const MAKS_ITEM = 30;

    /**
     * Item bersaldo di gudang beserta kelas, mutasi, hitungan terakhir, dan status jatuh tempo.
     *
     * @return array<int, array{item_id: int, kelas: string, mutasi: int, terakhir: ?string, jatuh_tempo: bool}>
     */
    public function klasifikasi(int $gudangId, ?CarbonImmutable $hari = null): array
    {
        $hari ??= CarbonImmutable::now();
        $binIds = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudangId)->where('is_virtual', false)->pluck('id')->all();

        if ($binIds === []) {
            return [];
        }

        $itemIds = StockBalance::query()->withoutGlobalScopes()->whereIn('bin_id', $binIds)->where('qty_base', '>', 0)
            ->distinct()->pluck('item_id')->map(fn ($i) => (int) $i)->all();

        if ($itemIds === []) {
            return [];
        }

        $mutasi = DB::table('stock_movements')
            ->whereIn('item_id', $itemIds)
            ->where('occurred_at', '>=', $hari->subDays(self::JENDELA_HARI)->utc())
            ->where(fn ($q) => $q->whereIn('from_bin_id', $binIds)->orWhereIn('to_bin_id', $binIds))
            ->groupBy('item_id')->selectRaw('item_id, COUNT(*) as n')
            ->pluck('n', 'item_id')->map(fn ($n) => (int) $n)->all();

        $terakhir = DB::table('count_lines')
            ->join('stock_counts', 'stock_counts.id', '=', 'count_lines.stock_count_id')
            ->whereIn('count_lines.item_id', $itemIds)
            ->whereIn('count_lines.bin_id', $binIds)
            ->whereIn('stock_counts.status', [StockCountStatus::Approved->value, StockCountStatus::Closed->value])
            ->groupBy('count_lines.item_id')
            ->selectRaw('count_lines.item_id, MAX(COALESCE(stock_counts.approved_at, stock_counts.updated_at)) as t')
            ->pluck('t', 'item_id')->all();

        // Urut mutasi terbanyak; seri dipecah id item supaya hasil stabil.
        usort($itemIds, fn (int $a, int $b) => [($mutasi[$b] ?? 0), $a] <=> [($mutasi[$a] ?? 0), $b]);

        $total = array_sum($mutasi);
        $kumulatif = 0;
        $hasil = [];

        foreach ($itemIds as $id) {
            $n = $mutasi[$id] ?? 0;
            // Kelas ditentukan oleh posisi SEBELUM item ini ditambahkan: item yang
            // melewati batas 80 % tetap A, sehingga A tidak pernah kosong.
            $kelas = $n === 0 || $total === 0 ? 'C'
                : ($kumulatif / $total < self::BATAS_A ? 'A' : ($kumulatif / $total < self::BATAS_B ? 'B' : 'C'));
            $kumulatif += $n;

            $t = isset($terakhir[$id]) ? CarbonImmutable::parse($terakhir[$id]) : null;
            $hasil[] = [
                'item_id' => $id,
                'kelas' => $kelas,
                'mutasi' => $n,
                'terakhir' => $t?->toDateString(),
                'jatuh_tempo' => $t === null || $t->lt($hari->subDays(self::JADWAL_HARI[$kelas])),
            ];
        }

        return $hasil;
    }

    /**
     * Item jatuh tempo untuk satu sesi: kelas A dulu, lalu yang paling lama belum
     * dihitung (belum pernah = paling lama), maksimal MAKS_ITEM.
     *
     * @return array<int, array{item_id: int, kelas: string, mutasi: int, terakhir: ?string, jatuh_tempo: bool}>
     */
    public function untukSesi(int $gudangId, ?CarbonImmutable $hari = null): array
    {
        $jatuhTempo = array_values(array_filter($this->klasifikasi($gudangId, $hari), fn (array $r) => $r['jatuh_tempo']));

        usort($jatuhTempo, fn (array $a, array $b) => [$a['kelas'], $a['terakhir'] ?? '0000-00-00', $a['item_id']]
            <=> [$b['kelas'], $b['terakhir'] ?? '0000-00-00', $b['item_id']]);

        return array_slice($jatuhTempo, 0, self::MAKS_ITEM);
    }
}
