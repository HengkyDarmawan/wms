<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\Item;
use App\Domain\Shared\Reports\Report;
use App\Domain\Warehouse\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan *Mutasi periode* (Blueprint §6.9a): per gudang & item — saldo awal,
 * masuk, keluar, saldo akhir dari kartu stok (BR-LED-01). Pergerakan antar bin
 * di gudang yang sama tidak dihitung masuk/keluar. Periode bawaan = bulan
 * berjalan, tanggal zona company (BR-GEN-07). Tanpa nilai uang (D-07).
 */
class StockMovementPeriodReport extends Report
{
    public function key(): string
    {
        return 'mutasi-periode';
    }

    public function title(): string
    {
        return 'Mutasi periode';
    }

    public function permission(): string
    {
        return 'stock.view';
    }

    public function description(): string
    {
        return 'Saldo awal, masuk, keluar, dan saldo akhir per gudang dan item dalam periode (isi tanggal TTTT-BB-HH).';
    }

    public function columns(): array
    {
        return [
            'gudang' => 'Gudang', 'kode_item' => 'Kode item', 'nama_item' => 'Nama item', 'satuan' => 'Satuan',
            'awal' => 'Saldo awal', 'masuk' => 'Masuk', 'keluar' => 'Keluar', 'akhir' => 'Saldo akhir',
        ];
    }

    public function filters(): array
    {
        return [
            'date_from' => ['label' => 'Dari tanggal'],
            'date_to' => ['label' => 'Sampai tanggal'],
            'warehouse_id' => ['label' => 'Gudang', 'options' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Warehouse $w) => [$w->id => $w->code.' — '.$w->name])->all()],
        ];
    }

    public function rows(array $filters): Collection
    {
        $tz = tenant()?->timezone ?? 'Asia/Jakarta';
        $dari = $this->tanggal($filters['date_from'] ?? null, $tz) ?? now($tz)->startOfMonth();
        $sampai = ($this->tanggal($filters['date_to'] ?? null, $tz) ?? now($tz))->endOfDay();
        [$awalUtc, $akhirUtc] = [$dari->copy()->startOfDay()->utc(), $sampai->copy()->utc()];

        $izin = auth()->user()?->accessibleWarehouseIds();
        $gudang = Warehouse::query()->withoutGlobalScopes()
            ->when($izin !== null, fn ($q) => $q->whereIn('id', $izin))
            ->when((int) ($filters['warehouse_id'] ?? 0) > 0, fn ($q) => $q->where('id', (int) $filters['warehouse_id']))
            ->pluck('code', 'id');

        if ($gudang->isEmpty()) {
            return collect();
        }

        // Masuk = baris ke bin gudang W dari luar W; keluar = dari bin W ke luar W.
        $sisi = fn (string $kolom, string $lawan, string $tanda) => DB::table('stock_movements as m')
            ->join('bins as b', 'b.id', '=', 'm.'.$kolom)
            ->leftJoin('bins as o', 'o.id', '=', 'm.'.$lawan)
            ->whereIn('b.warehouse_id', $gudang->keys())
            ->where(fn ($q) => $q->whereNull('o.id')->orWhereColumn('o.warehouse_id', '!=', 'b.warehouse_id'))
            ->where('m.occurred_at', '<=', $akhirUtc)
            ->groupBy('b.warehouse_id', 'm.item_id')
            ->selectRaw("b.warehouse_id as gudang, m.item_id as item, '".$tanda."' as arah,
                SUM(CASE WHEN m.occurred_at < ? THEN m.qty_base ELSE 0 END) as sebelum,
                SUM(CASE WHEN m.occurred_at >= ? THEN m.qty_base ELSE 0 END) as selama", [$awalUtc, $awalUtc])
            ->get();

        $data = [];

        foreach ([$sisi('to_bin_id', 'from_bin_id', 'in'), $sisi('from_bin_id', 'to_bin_id', 'out')] as $hasil) {
            foreach ($hasil as $r) {
                $k = $r->gudang.'|'.$r->item;
                $data[$k] ??= ['gudang' => (int) $r->gudang, 'item' => (int) $r->item, 'awal' => 0.0, 'masuk' => 0.0, 'keluar' => 0.0];
                $tanda = $r->arah === 'in' ? 1 : -1;
                $data[$k]['awal'] += $tanda * (float) $r->sebelum;
                $data[$k][$r->arah === 'in' ? 'masuk' : 'keluar'] += (float) $r->selama;
            }
        }

        $items = Item::query()->with('baseUom:id,code')->whereIn('id', array_column($data, 'item'))->get(['id', 'code', 'name', 'base_uom_id'])->keyBy('id');

        return collect($data)
            ->filter(fn ($d) => abs($d['awal']) > 0.00005 || $d['masuk'] > 0.00005 || $d['keluar'] > 0.00005)
            ->map(fn ($d) => [
                'gudang' => $gudang[$d['gudang']] ?? null,
                'kode_item' => $items[$d['item']]?->code,
                'nama_item' => $items[$d['item']]?->name,
                'satuan' => $items[$d['item']]?->baseUom?->code,
                'awal' => round($d['awal'], 4),
                'masuk' => round($d['masuk'], 4),
                'keluar' => round($d['keluar'], 4),
                'akhir' => round($d['awal'] + $d['masuk'] - $d['keluar'], 4),
            ])
            ->sortBy(fn ($r) => $r['gudang'].'|'.$r['kode_item'])
            ->values();
    }

    private function tanggal(mixed $nilai, string $tz): ?Carbon
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        if ($isi === '') {
            return null;
        }

        try {
            return Carbon::parse($isi, $tz)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
