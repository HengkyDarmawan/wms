<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\LatestInbound;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use Illuminate\Support\Collection;

/**
 * Laporan 19-receipt-putaway §9 *Barang di Karantina menurut umur*: saldo
 * berkondisi Karantina (menunggu QC, BR-GRN-02) di bin mana pun — umumnya bin
 * Penerimaan/Karantina QC.
 *
 * Umur = hari sejak baris kartu stok terakhir yang memasukkan barang ke saldo
 * Karantina itu (lihat `LatestInbound`), bukan tanggal GRN: saldo tidak
 * menyimpan GRN asalnya, dan barang bisa masuk Karantina dari dokumen lain.
 * Nomor dokumen baris itu ditampilkan sebagai jejak.
 */
class QuarantineAgeReport extends Report
{
    use LatestInbound;
    use PeriodFilter;

    public function key(): string
    {
        return 'karantina-umur';
    }

    public function title(): string
    {
        return 'Barang karantina menurut umur';
    }

    public function permission(): string
    {
        return 'receipt.view';
    }

    public function description(): string
    {
        return 'Saldo berkondisi Karantina yang menunggu QC: bin, item, jumlah, dokumen masuk terakhir, dan umurnya dalam hari.';
    }

    public function columns(): array
    {
        return ['gudang' => 'Gudang', 'bin' => 'Bin', 'item' => 'Item', 'pelacakan' => 'Lot / serial / potongan', 'jumlah' => 'Jumlah', 'satuan' => 'Satuan', 'dokumen' => 'Dokumen masuk', 'masuk' => 'Masuk', 'umur' => 'Umur (hari)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang(), 'min_age_days' => ['label' => 'Umur minimal (hari)']];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);
        $minimal = (int) ($filters['min_age_days'] ?? 0);

        $saldo = StockBalance::query()
            ->with('item:id,code,base_uom_id', 'item.baseUom:id,code', 'bin:id,code,warehouse_id', 'bin.warehouse:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no')
            ->where('stock_status', StockStatus::Quarantine->value)
            ->where('qty_base', '>', 0)
            ->whereHas('bin', fn ($q) => $q->when($gudang !== null, fn ($w) => $w->whereIn('warehouse_id', $gudang)))
            ->get();

        $masuk = $this->masukTerakhir($saldo);

        return $saldo->map(function (StockBalance $b) use ($masuk) {
            $m = $masuk[$this->kunciUntuk($b)] ?? null;

            return [
                'gudang' => $b->bin?->warehouse?->code,
                'bin' => $b->bin?->code,
                'item' => $b->item?->code,
                'pelacakan' => $b->lot?->lot_no ?? $b->serial?->serial_no ?? $b->piece?->piece_no ?? '—',
                'jumlah' => round((float) $b->qty_base, 4),
                'satuan' => $b->item?->baseUom?->code,
                'dokumen' => $m?->document_number ?? '—',
                'masuk' => $m?->occurred_at?->lokal()->format('d/m/Y') ?? '—',
                'umur' => $this->umurHari($m),
            ];
        })
            ->filter(fn (array $r) => ($r['umur'] ?? 0) >= $minimal)
            ->sortByDesc('umur')
            ->map(fn (array $r) => ['umur' => $r['umur'] ?? '—'] + $r)
            ->values();
    }
}
