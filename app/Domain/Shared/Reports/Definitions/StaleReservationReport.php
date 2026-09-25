<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\CompanySetting;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Stock\Livewire\ReservationList;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Transfer\Models\Transfer;
use Illuminate\Support\Collection;

/** Laporan 13-stock §9 *Reservasi menggantung* (BR-STK-16): reservasi lunak aktif yang melewati ambang umur. */
class StaleReservationReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'reservasi-menggantung';
    }

    public function title(): string
    {
        return 'Reservasi menggantung';
    }

    public function permission(): string
    {
        return 'reservation.view';
    }

    public function description(): string
    {
        return 'Reservasi lunak yang masih aktif lebih lama dari ambang (bawaan mengikuti pengaturan company) — janji stok yang belum berubah menjadi tugas picking.';
    }

    public function columns(): array
    {
        return ['dokumen' => 'Dokumen', 'item' => 'Item', 'gudang' => 'Gudang', 'bin' => 'Bin', 'jumlah' => 'Jumlah', 'satuan' => 'Satuan', 'umur' => 'Umur (hari)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang(), 'min_age_days' => ['label' => 'Umur minimal (hari)']];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);
        $umur = (int) ($filters['min_age_days'] ?? 0);
        $umur = $umur > 0 ? $umur : max(1, (int) CompanySetting::get(ReservationList::AMBANG, 7));

        $baris = StockReservation::query()
            ->with('item:id,code,base_uom_id', 'item.baseUom:id,code', 'warehouse:id,code', 'bin:id,code')
            ->stale($umur)
            ->when($gudang !== null, fn ($q) => $q->whereIn('warehouse_id', $gudang))
            ->orderBy('created_at')
            ->get();

        $nomor = $this->nomorDokumen($baris);

        return $baris->map(fn (StockReservation $r) => [
            'dokumen' => $nomor[$r->document_type.'#'.$r->document_id] ?? ($r->document_type.'#'.$r->document_id),
            'item' => $r->item?->code,
            'gudang' => $r->warehouse?->code,
            'bin' => $r->bin?->code ?? '—',
            'jumlah' => round((float) $r->qty_base, 4),
            'satuan' => $r->item?->baseUom?->code,
            'umur' => $r->ageInDays(),
        ]);
    }

    /**
     * Nomor dokumen dicari sekali per jenis, bukan per baris.
     *
     * @param  Collection<int, StockReservation>  $baris
     * @return array<string, string>
     */
    private function nomorDokumen(Collection $baris): array
    {
        $hasil = [];
        $model = ['material_request' => MaterialRequest::class, 'transfer' => Transfer::class, 'goods_return' => GoodsReturn::class];

        foreach ($baris->groupBy('document_type') as $jenis => $grup) {
            $kelas = $model[$jenis] ?? null;

            if ($kelas === null) {
                continue;
            }

            foreach ($kelas::query()->withoutGlobalScopes()->whereIn('id', $grup->pluck('document_id'))->pluck('number', 'id') as $id => $number) {
                $hasil[$jenis.'#'.$id] = (string) $number;
            }
        }

        return $hasil;
    }
}
