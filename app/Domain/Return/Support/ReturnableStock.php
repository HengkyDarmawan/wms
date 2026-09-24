<?php

declare(strict_types=1);

namespace App\Domain\Return\Support;

use App\Domain\Master\Models\Project;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnOwnership;
use App\Domain\Return\Enums\ReturnSource;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Enums\DiscrepancyDisposition;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\DiscrepancyType;
use App\Domain\Shipment\Enums\OwnershipEffect;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\DeliveryDiscrepancyLine;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Barang yang boleh diretur dari satu proyek (A-110, BR-RET-03, BR-RET-05).
 *
 * Empat asal, sesuai sub-tampilan Stok On-site (BR-PRJ-05) ditambah barang rusak
 * yang ditinggal ekspedisi (BR-SJ-10):
 *
 * - **Di Gudang Site** — saldo Tersedia di bin penyimpanan Gudang Site proyek
 *   (stok company), dikurangi alokasi keras dan retur lain yang masih berjalan;
 * - **Aset di Proyek** — serial di bin On-site proyek;
 * - **Terkirim ke Klien** — baris SJ jual-putus yang sudah diterima baik;
 * - **Rusak ditinggal ekspedisi** — baris DSC rusak berdisposisi `claimed`.
 *
 * Setiap calon punya kunci stabil; form mengirim kunci + jumlah dan aksi
 * menghitung ulang daftar ini, jadi batas jumlah tidak pernah dipercaya dari
 * peramban.
 */
class ReturnableStock
{
    /**
     * @return Collection<string, array<string, mixed>> kunci => calon
     */
    public function forProject(Project $project, ?int $exceptReturnId = null): Collection
    {
        return collect()
            ->merge($this->stokSite($project, $exceptReturnId))
            ->merge($this->asetOnSite($project, $exceptReturnId))
            ->merge($this->terkirim($project, $exceptReturnId))
            ->merge($this->ditinggalEkspedisi($project, $exceptReturnId))
            ->filter(fn (array $c) => $c['max'] > 0.00005)
            ->keyBy('key');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function stokSite(Project $project, ?int $kecuali): Collection
    {
        $gudang = Warehouse::query()->withoutGlobalScopes()->with('type')
            ->where('project_id', $project->id)->where('is_active', true)->get()
            ->filter(fn (Warehouse $w) => $w->isSite());

        if ($gudang->isEmpty()) {
            return collect();
        }

        return StockBalance::query()
            ->with('item:id,code,name,tracking_mode,ownership_model', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no,length')
            ->where('stock_status', StockStatus::Available->value)
            ->nonZero()
            ->whereHas('bin', fn (Builder $q) => $q->withoutGlobalScopes()
                ->whereIn('warehouse_id', $gudang->pluck('id'))
                ->where('bin_type', BinType::Storage->value)
                ->where('bin_status', BinStatus::Active->value))
            ->get()
            ->each(fn (StockBalance $s) => $s->setRelation('bin', Bin::query()->withoutGlobalScopes()->find($s->bin_id)))
            ->map(function (StockBalance $s) use ($kecuali, $gudang): array {
                $kunci = implode(':', ['site', $s->bin_id, $s->item_id, $s->lot_id ?? 0, $s->serial_id ?? 0, $s->piece_id ?? 0]);
                $keras = (float) StockReservation::query()->active()
                    ->where('level', ReservationLevel::Hard->value)
                    ->where('bin_id', $s->bin_id)->where('item_id', $s->item_id)
                    ->where(fn ($q) => $s->lot_id === null ? $q->whereNull('lot_id') : $q->where('lot_id', $s->lot_id))
                    ->where(fn ($q) => $s->serial_id === null ? $q->whereNull('serial_id') : $q->where('serial_id', $s->serial_id))
                    ->where(fn ($q) => $s->piece_id === null ? $q->whereNull('piece_id') : $q->where('piece_id', $s->piece_id))
                    ->sum('qty_base');
                $diajukan = $this->diajukan(fn (Builder $q) => $q
                    ->where('from_bin_id', $s->bin_id)->where('item_id', $s->item_id)
                    ->where(fn ($w) => $s->lot_id === null ? $w->whereNull('lot_id') : $w->where('lot_id', $s->lot_id))
                    ->where(fn ($w) => $s->serial_id === null ? $w->whereNull('serial_id') : $w->where('serial_id', $s->serial_id))
                    ->where(fn ($w) => $s->piece_id === null ? $w->whereNull('piece_id') : $w->where('piece_id', $s->piece_id)), $kecuali, true);

                return $this->calon($kunci, ReturnSource::SiteStock, $s, ReturnOwnership::Company, StockStatus::Available,
                    (float) $s->qty_base - $keras - $diajukan,
                    ['from_bin_id' => (int) $s->bin_id, 'warehouse_id' => (int) $s->bin->warehouse_id,
                        'warehouse_code' => $gudang->firstWhere('id', $s->bin->warehouse_id)?->code]);
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function asetOnSite(Project $project, ?int $kecuali): Collection
    {
        return StockBalance::query()
            ->with('item:id,code,name,tracking_mode,ownership_model', 'serial:id,serial_no')
            ->nonZero()
            ->whereHas('bin', fn (Builder $q) => $q->withoutGlobalScopes()
                ->where('bin_type', BinType::OnSite->value)
                ->where('project_id', $project->id))
            ->get()
            ->each(fn (StockBalance $s) => $s->setRelation('bin', Bin::query()->withoutGlobalScopes()->find($s->bin_id)))
            ->map(function (StockBalance $s) use ($kecuali): array {
                $kunci = implode(':', ['asset', $s->bin_id, $s->item_id, $s->serial_id ?? 0]);
                $diajukan = $this->diajukan(fn (Builder $q) => $q->where('from_bin_id', $s->bin_id)
                    ->where('serial_id', $s->serial_id), $kecuali);

                return $this->calon($kunci, ReturnSource::OnSiteAsset, $s, ReturnOwnership::Company, $s->stock_status,
                    (float) $s->qty_base - $diajukan, ['from_bin_id' => (int) $s->bin_id]);
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function terkirim(Project $project, ?int $kecuali): Collection
    {
        return ShipmentLine::query()
            ->with('pickTaskLine.item:id,code,name,tracking_mode,ownership_model', 'pickTaskLine.lot:id,lot_no', 'pickTaskLine.serial:id,serial_no', 'pickTaskLine.piece:id,piece_no,length', 'shipment:id,number')
            ->where('ownership_effect', OwnershipEffect::Sold->value)
            ->where('qty_delivered', '>', 0)
            ->whereHas('shipment', fn (Builder $q) => $q->withoutGlobalScopes()
                ->where('destination_project_id', $project->id)
                ->whereIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::PartiallyDelivered->value]))
            ->get()
            ->map(function (ShipmentLine $sl) use ($kecuali): array {
                $asal = $sl->pickTaskLine;
                $diajukan = $this->diajukan(fn (Builder $q) => $q->where('origin_shipment_line_id', $sl->id)
                    ->whereNull('origin_discrepancy_line_id'), $kecuali);

                return $this->calon('sold:'.$sl->id, ReturnSource::DeliveredToClient, $asal, ReturnOwnership::Sold, StockStatus::Available,
                    (float) $sl->qty_delivered - $diajukan,
                    ['origin_shipment_line_id' => (int) $sl->id, 'shipment_id' => (int) $sl->shipment_id, 'shipment_number' => $sl->shipment?->number]);
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function ditinggalEkspedisi(Project $project, ?int $kecuali): Collection
    {
        return DeliveryDiscrepancyLine::query()
            ->with('shipmentLine.pickTaskLine.item:id,code,name,tracking_mode,ownership_model', 'shipmentLine.pickTaskLine.lot:id,lot_no',
                'shipmentLine.pickTaskLine.serial:id,serial_no', 'shipmentLine.pickTaskLine.piece:id,piece_no,length', 'shipmentLine.shipment:id,number')
            ->where('discrepancy_type', DiscrepancyType::Damaged->value)
            ->where('disposition', DiscrepancyDisposition::Claimed->value)
            ->whereHas('discrepancy', fn (Builder $q) => $q->withoutGlobalScopes()
                ->where('status', DiscrepancyStatus::Resolved->value)
                ->whereHas('shipment', fn (Builder $s) => $s->withoutGlobalScopes()->where('destination_project_id', $project->id)))
            ->get()
            ->map(function (DeliveryDiscrepancyLine $d) use ($kecuali): array {
                $asal = $d->shipmentLine?->pickTaskLine;
                $diajukan = $this->diajukan(fn (Builder $q) => $q->where('origin_discrepancy_line_id', $d->id), $kecuali);

                return $this->calon('claim:'.$d->id, ReturnSource::CarrierLeftDamaged, $asal, ReturnOwnership::Company, StockStatus::Damaged,
                    (float) $d->qty_base - $diajukan,
                    ['origin_shipment_line_id' => (int) $d->shipment_line_id, 'origin_discrepancy_line_id' => (int) $d->id,
                        'shipment_id' => (int) $d->shipmentLine?->shipment_id, 'shipment_number' => $d->shipmentLine?->shipment?->number]);
            });
    }

    /**
     * Jumlah yang sudah diajukan RET lain yang masih berjalan. Untuk stok Gudang
     * Site, RET yang sudah disetujui memegangnya lewat alokasi keras, jadi hanya
     * yang belum disetujui yang dihitung di sini.
     */
    private function diajukan(callable $filter, ?int $kecuali, bool $hanyaBelumDisetujui = false): float
    {
        $status = $hanyaBelumDisetujui
            ? [GoodsReturnStatus::Submitted->value, GoodsReturnStatus::PendingApproval->value]
            : array_map(fn (GoodsReturnStatus $s) => $s->value, array_filter(GoodsReturnStatus::cases(), fn ($s) => $s->holdsQuantity()));

        return (float) GoodsReturnLine::query()
            ->whereNull('split_from_line_id')
            ->whereHas('goodsReturn', fn (Builder $q) => $q->withoutGlobalScopes()->whereIn('status', $status)
                ->when($kecuali !== null, fn ($w) => $w->where('id', '!=', $kecuali)))
            ->where($filter)
            ->sum('qty_base');
    }

    /**
     * @param  object|null  $sumber  saldo atau baris PCK: item, lot, serial, potongan
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function calon(string $kunci, ReturnSource $asal, ?object $sumber, ReturnOwnership $milik, StockStatus $kondisi, float $max, array $extra): array
    {
        return $extra + [
            'key' => $kunci,
            'source' => $asal,
            'item_id' => (int) $sumber?->item_id,
            'item_code' => $sumber?->item?->code,
            'item_name' => $sumber?->item?->name,
            'tracking_mode' => $sumber?->item?->tracking_mode?->value,
            'lot_id' => $sumber?->lot_id,
            'serial_id' => $sumber?->serial_id,
            'piece_id' => $sumber?->piece_id,
            'tracking' => match (true) {
                $sumber?->serial_id !== null => 'SN '.$sumber->serial?->serial_no,
                $sumber?->lot_id !== null => 'Lot '.$sumber->lot?->lot_no,
                $sumber?->piece_id !== null => 'Potongan '.$sumber->piece?->piece_no,
                default => '',
            },
            'bin_code' => $sumber instanceof StockBalance ? $sumber->bin?->code : null,
            'ownership' => $milik,
            'stock_status' => $kondisi,
            'max' => round(max(0, $max), 4),
            'from_bin_id' => $extra['from_bin_id'] ?? null,
            'origin_shipment_line_id' => $extra['origin_shipment_line_id'] ?? null,
            'origin_discrepancy_line_id' => $extra['origin_discrepancy_line_id'] ?? null,
        ];
    }
}
