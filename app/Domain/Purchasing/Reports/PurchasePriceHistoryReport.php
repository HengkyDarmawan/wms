<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Reports;

use App\Domain\Master\Models\Vendor;
use App\Domain\Purchasing\Support\PurchasePriceHistory;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan *Riwayat harga beli* (A-309, purchasing/02 §9): baris PO per item —
 * satu-satunya laporan bernilai uang, karena itu berada di domain Purchasing
 * dan berizin `po.view` (D-07). Bawaan periode = 12 bulan terakhir.
 */
class PurchasePriceHistoryReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'riwayat-harga-beli';
    }

    public function title(): string
    {
        return 'Riwayat harga beli';
    }

    public function permission(): string
    {
        return 'po.view';
    }

    public function description(): string
    {
        return 'Semua baris PO per item: tanggal, nomor PO, vendor, jumlah, harga satuan, PPN, dan status — dasar memilih vendor dan menilai kenaikan harga.';
    }

    public function columns(): array
    {
        return ['tanggal' => 'Tanggal PO', 'po' => 'Nomor PO', 'vendor' => 'Vendor', 'item' => 'Item', 'jumlah' => 'Jumlah', 'satuan' => 'Satuan',
            'harga' => 'Harga satuan', 'ppn' => 'PPN', 'status' => 'Status'];
    }

    public function filters(): array
    {
        return [
            'item' => ['label' => 'Kode item'],
            'vendor_id' => ['label' => 'Vendor', 'options' => Vendor::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Vendor $v) => [$v->id => $v->code.' — '.$v->name])->all()],
        ] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        $tz = tenant()?->timezone ?? 'Asia/Jakarta';
        $dari = $this->tanggal($filters['date_from'] ?? null, $tz) ?? now($tz)->subMonths(12)->startOfDay();
        $sampai = ($this->tanggal($filters['date_to'] ?? null, $tz) ?? now($tz))->endOfDay();
        $vendor = (int) ($filters['vendor_id'] ?? 0);

        return app(PurchasePriceHistory::class)
            ->lines(null, $vendor > 0 ? $vendor : null, $dari, $sampai, trim((string) ($filters['item'] ?? '')))
            ->map(fn (array $l) => [
                'tanggal' => $l['tanggal']->format('d/m/Y'),
                'po' => $l['po'],
                'vendor' => $l['vendor'],
                'item' => $l['item'],
                'jumlah' => $l['jumlah'],
                'satuan' => $l['satuan'],
                'harga' => $l['harga'],
                'ppn' => $l['ppn'] ? 'Termasuk' : 'Belum termasuk',
                'status' => $l['status']->label(),
            ]);
    }
}
