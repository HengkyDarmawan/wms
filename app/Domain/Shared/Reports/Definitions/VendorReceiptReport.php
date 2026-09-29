<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 19-receipt-putaway §9 *Penerimaan per vendor/periode*: GRN dari
 * vendor yang sudah diterima (Diterima/Selesai) dengan tanggal terima dalam
 * periode — jumlah baris dan total jumlah diterima (satuan dasar, tanpa nilai
 * uang, D-07).
 */
class VendorReceiptReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'penerimaan-vendor';
    }

    public function title(): string
    {
        return 'Penerimaan per vendor';
    }

    public function permission(): string
    {
        return 'receipt.view';
    }

    public function description(): string
    {
        return 'GRN dari vendor yang diterima dalam periode: vendor, gudang, tanggal terima, jumlah baris, total diterima baik, rusak, dan kurang.';
    }

    public function columns(): array
    {
        return ['grn' => 'GRN', 'vendor' => 'Vendor', 'gudang' => 'Gudang', 'diterima' => 'Tanggal terima', 'surat_jalan' => 'SJ vendor', 'baris' => 'Jumlah baris', 'jumlah' => 'Total diterima', 'rusak' => 'Rusak', 'kurang' => 'Kurang', 'status' => 'Status'];
    }

    public function filters(): array
    {
        return [
            'warehouse_id' => $this->penyaringGudang(),
            'vendor_id' => ['label' => 'Vendor', 'server' => true],
        ] + $this->penyaringPeriode();
    }

    /** Semua vendor (query lama), dicari ke server (A-396). */
    public function pilihanPenyaring(string $kunci): ?Pilihan
    {
        return $kunci === 'vendor_id' ? $this->pilihanVendorSemua() : null;
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $gudang = $this->gudangDipilih($filters);
        $vendor = (int) ($filters['vendor_id'] ?? 0);

        return GoodsReceipt::query()
            ->with('vendor:id,code,name', 'warehouse:id,code')
            ->withCount('lines')
            ->withSum('lines', 'qty_received')
            ->withSum('lines', 'qty_damaged')
            ->withSum('lines', 'qty_short')
            ->where('receipt_type', ReceiptType::Vendor->value)
            ->whereIn('status', [GoodsReceiptStatus::Received->value, GoodsReceiptStatus::Completed->value])
            ->whereBetween('received_at', [$dari, $sampai])
            ->when($gudang !== null, fn ($q) => $q->whereIn('warehouse_id', $gudang))
            ->when($vendor > 0, fn ($q) => $q->where('vendor_id', $vendor))
            ->orderBy('received_at')
            ->get()
            ->map(fn (GoodsReceipt $g) => [
                'grn' => $g->number,
                'vendor' => $g->vendor !== null ? $g->vendor->code.' — '.$g->vendor->name : '—',
                'gudang' => $g->warehouse?->code,
                'diterima' => $g->received_at?->lokal()->format('d/m/Y H:i'),
                'surat_jalan' => $g->vendor_doc_no ?? '—',
                'baris' => (int) $g->lines_count,
                'jumlah' => round((float) $g->lines_sum_qty_received, 4),
                // A-295: kondisi barang saat diterima (A-287).
                'rusak' => round((float) $g->lines_sum_qty_damaged, 4),
                'kurang' => round((float) $g->lines_sum_qty_short, 4),
                'status' => $g->status->label(),
            ]);
    }
}
