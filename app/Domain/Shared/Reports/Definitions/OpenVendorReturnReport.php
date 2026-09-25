<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 19-receipt-putaway §9 *RTV terbuka*: retur ke vendor yang belum
 * berstatus akhir (Selesai, Ditolak, Dibatalkan — Katalog §2.16), dengan
 * umur sejak diajukan. Tanpa penyaring periode: yang final keluar sendiri.
 */
class OpenVendorReturnReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'rtv-terbuka';
    }

    public function title(): string
    {
        return 'RTV terbuka';
    }

    public function permission(): string
    {
        return 'vendor_return.view';
    }

    public function description(): string
    {
        return 'Retur ke vendor yang masih berjalan (diajukan, menunggu approval, disetujui, dikirim): vendor, GRN asal, jumlah, status, dan umurnya.';
    }

    public function columns(): array
    {
        return ['rtv' => 'RTV', 'vendor' => 'Vendor', 'gudang' => 'Gudang', 'grn' => 'GRN asal', 'jumlah' => 'Jumlah', 'status' => 'Status', 'dibuat' => 'Diajukan', 'umur' => 'Umur (hari)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);
        $hariIni = now()->lokal()->startOfDay();

        return VendorReturn::query()
            ->with('vendor:id,code,name', 'warehouse:id,code', 'receipt:id,number')
            ->withSum('lines', 'qty_base')
            ->whereNotIn('status', [VendorReturnStatus::Completed->value, VendorReturnStatus::Rejected->value, VendorReturnStatus::Cancelled->value])
            ->when($gudang !== null, fn ($q) => $q->whereIn('warehouse_id', $gudang))
            ->orderBy('created_at')
            ->get()
            ->map(fn (VendorReturn $r) => [
                'rtv' => $r->number,
                'vendor' => $r->vendor !== null ? $r->vendor->code.' — '.$r->vendor->name : '—',
                'gudang' => $r->warehouse?->code,
                'grn' => $r->receipt?->number ?? '—',
                'jumlah' => round((float) $r->lines_sum_qty_base, 4),
                'status' => $r->status->label(),
                'dibuat' => $r->created_at?->lokal()->format('d/m/Y H:i'),
                'umur' => (int) $r->created_at?->lokal()->startOfDay()->diffInDays($hariIni),
            ]);
    }
}
