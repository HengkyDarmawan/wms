<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\Shipment;
use Illuminate\Support\Collection;

/** Laporan 15-picking-shipment §9 *Kinerja pengiriman*: per gudang, SJ berangkat dalam periode — diterima utuh, bersisa, rata-rata hari kirim → terima. */
class DeliveryPerformanceReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'kinerja-pengiriman';
    }

    public function title(): string
    {
        return 'Kinerja pengiriman';
    }

    public function permission(): string
    {
        return 'shipment.view';
    }

    public function description(): string
    {
        return 'Per gudang: jumlah SJ yang berangkat dalam periode, yang diterima utuh, yang bersisa (DSC), yang masih di jalan, dan rata-rata hari dari berangkat sampai bukti terima.';
    }

    public function columns(): array
    {
        return ['gudang' => 'Gudang', 'dikirim' => 'Dikirim', 'utuh' => 'Diterima utuh', 'bersisa' => 'Bersisa', 'di_jalan' => 'Masih di jalan', 'dibatalkan' => 'Dibatalkan', 'rata_hari' => 'Rata-rata hari', 'utuh_pct' => 'Utuh (%)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);

        $sj = Shipment::query()
            ->with('warehouse:id,code,name')
            ->whereBetween('shipped_at', [$dari, $sampai])
            ->when((int) ($filters['warehouse_id'] ?? 0) > 0, fn ($q) => $q->where('warehouse_id', (int) $filters['warehouse_id']))
            ->get(['id', 'warehouse_id', 'status', 'shipped_at', 'delivered_at']);

        $baris = $sj->groupBy('warehouse_id')->map(fn (Collection $grup) => $this->ringkas($grup->first()->warehouse?->code ?? '—', $grup))->sortBy('gudang')->values();

        if ($baris->count() > 1) {
            $baris->push($this->ringkas('Semua gudang', $sj));
        }

        return $baris;
    }

    /** @param  Collection<int, Shipment>  $grup  @return array<string, mixed> */
    private function ringkas(string $label, Collection $grup): array
    {
        $utuh = $grup->where('status', ShipmentStatus::Delivered)->count();
        $bersisa = $grup->where('status', ShipmentStatus::PartiallyDelivered)->count();
        $diJalan = $grup->where('status', ShipmentStatus::Shipped)->count();
        $batal = $grup->where('status', ShipmentStatus::Cancelled)->count();
        $selesai = $grup->filter(fn (Shipment $s) => $s->delivered_at !== null && $s->shipped_at !== null);
        $rata = $selesai->isEmpty() ? null : round($selesai->avg(fn (Shipment $s) => $s->shipped_at->diffInMinutes($s->delivered_at) / 1440), 1);
        $total = $grup->count();

        return [
            'gudang' => $label,
            'dikirim' => $total,
            'utuh' => $utuh,
            'bersisa' => $bersisa,
            'di_jalan' => $diJalan,
            'dibatalkan' => $batal,
            'rata_hari' => $rata ?? '—',
            'utuh_pct' => $total > 0 ? round($utuh / $total * 100, 1) : 0,
        ];
    }
}
