<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\Project;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnSorting;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 22-retur-transfer §9 *Retur per proyek dan hasil pilah*: satu baris
 * per baris RET yang diajukan dalam periode (tanggal RET dibuat), dengan
 * jumlah diajukan, diterima, dan hasil pilah per kategori `return_sorting`
 * (Layak/Rusak/Offcut/Waste). Baris hasil pilah tambahan (A-113) dijumlahkan
 * ke baris asalnya. Kolom Offcut berisi panjang potongan offcut (`sorted_qty`
 * offcut = panjang, BR-RET-04). RET Ditolak/Dibatalkan tidak ikut: tidak ada
 * barang yang kembali. Proyek dibatasi cakupan (BR-ACC-05, global scope RET).
 */
class ReturnByProjectReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'retur-per-proyek';
    }

    public function title(): string
    {
        return 'Retur per proyek';
    }

    public function permission(): string
    {
        return 'return.view';
    }

    public function description(): string
    {
        return 'Per proyek dan baris RET dalam periode: item, jumlah diajukan, diterima, dan hasil pilah layak, rusak, offcut, serta waste.';
    }

    public function columns(): array
    {
        return [
            'proyek' => 'Proyek', 'ret' => 'RET', 'tanggal' => 'Tanggal', 'status' => 'Status', 'item' => 'Item',
            'diajukan' => 'Diajukan', 'diterima' => 'Diterima', 'layak' => 'Layak', 'rusak' => 'Rusak', 'offcut' => 'Offcut', 'waste' => 'Waste',
        ];
    }

    public function filters(): array
    {
        $ids = auth()->user()?->accessibleProjectIds();

        return [
            'project_id' => ['label' => 'Proyek', 'options' => Project::query()
                ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
                ->orderBy('code')->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->name])->all()],
        ] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $proyek = (int) ($filters['project_id'] ?? 0);

        return GoodsReturn::query()
            ->with('project:id,code', 'lines:id,goods_return_id,item_id,split_from_line_id,qty_base,qty_received,sorting,sorted_qty', 'lines.item:id,code')
            ->whereNotIn('status', [GoodsReturnStatus::Rejected->value, GoodsReturnStatus::Cancelled->value])
            ->whereBetween('created_at', [$dari, $sampai])
            ->when($proyek > 0, fn ($q) => $q->where('project_id', $proyek))
            ->orderBy('created_at')
            ->get()
            ->flatMap(function (GoodsReturn $r) {
                $pecahan = $r->lines->whereNotNull('split_from_line_id')->groupBy('split_from_line_id');

                return $r->lines->whereNull('split_from_line_id')->map(function (GoodsReturnLine $l) use ($r, $pecahan) {
                    $semua = collect([$l])->merge($pecahan->get($l->id, collect()));
                    $hasil = fn (ReturnSorting $s) => round((float) $semua->filter(fn (GoodsReturnLine $x) => $x->sorting === $s)->sum(fn (GoodsReturnLine $x) => (float) $x->sorted_qty), 4);

                    return [
                        'proyek' => $r->project?->code,
                        'ret' => $r->number,
                        'tanggal' => $r->created_at?->lokal()->format('d/m/Y'),
                        'status' => $r->status->label(),
                        'item' => $l->item?->code,
                        'diajukan' => round((float) $l->qty_base, 4),
                        'diterima' => round((float) $l->qty_received, 4),
                        'layak' => $hasil(ReturnSorting::Good),
                        'rusak' => $hasil(ReturnSorting::Damaged),
                        'offcut' => $hasil(ReturnSorting::Offcut),
                        'waste' => $hasil(ReturnSorting::Waste),
                    ];
                });
            })
            ->sortBy([['proyek', 'asc'], ['ret', 'asc']])
            ->values();
    }
}
