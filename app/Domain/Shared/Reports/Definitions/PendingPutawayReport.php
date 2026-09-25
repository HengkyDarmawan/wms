<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 19-receipt-putaway §9 *PUT tertunda*: tugas put-away berstatus
 * Menunggu (belum Selesai/Dibatalkan), umur dihitung dari tugas dibuat.
 * Tanpa penyaring periode: jumlahnya kecil karena tugas yang selesai keluar.
 */
class PendingPutawayReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'put-tertunda';
    }

    public function title(): string
    {
        return 'PUT tertunda';
    }

    public function permission(): string
    {
        return 'putaway.view';
    }

    public function description(): string
    {
        return 'Tugas put-away yang belum selesai: GRN asal, gudang, petugas, jumlah baris, dan umurnya dalam hari.';
    }

    public function columns(): array
    {
        return ['put' => 'PUT', 'grn' => 'GRN', 'gudang' => 'Gudang', 'petugas' => 'Petugas', 'dibuat' => 'Dibuat', 'umur' => 'Umur (hari)', 'baris' => 'Jumlah baris', 'status' => 'Status'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);
        $hariIni = now()->lokal()->startOfDay();

        return PutawayTask::query()
            ->with('receipt:id,number', 'warehouse:id,code', 'assignee:id,name')
            ->withCount('lines')
            ->where('status', PutawayTaskStatus::Pending->value)
            ->when($gudang !== null, fn ($q) => $q->whereIn('warehouse_id', $gudang))
            ->orderBy('created_at')
            ->get()
            ->map(fn (PutawayTask $t) => [
                'put' => $t->number,
                'grn' => $t->receipt?->number ?? '—',
                'gudang' => $t->warehouse?->code,
                'petugas' => $t->assignee?->name ?? '—',
                'dibuat' => $t->created_at?->lokal()->format('d/m/Y H:i'),
                'umur' => (int) $t->created_at?->lokal()->startOfDay()->diffInDays($hariIni),
                'baris' => (int) $t->lines_count,
                'status' => $t->status->label(),
            ]);
    }
}
