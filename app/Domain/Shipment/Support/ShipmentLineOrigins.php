<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Support;

use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Transfer\Models\Transfer;
use Illuminate\Support\Collection;

/**
 * Asal tiap baris SJ untuk dicetak dan ditampilkan (A-248): supaya satu SJ yang
 * membawa barang dari banyak REQ tetap bisa dilacak per baris.
 *
 * - Baris dari PCK → dokumen sumber PCK (REQ/TRF/RET).
 * - Baris SJ jemput → asal baris RET: SJ kirim + REQ-nya (barang di tangan
 *   klien), AST (aset On-site), atau DSC (ditinggal ekspedisi).
 * - Baris SJ antar site → AST aset di proyek asal (A-249).
 */
class ShipmentLineOrigins
{
    /**
     * @param  Collection<int, ShipmentLine>  $lines
     * @return array<int, string> shipment_line_id => teks asal
     */
    public function for(Shipment $sj, Collection $lines): array
    {
        $hasil = [];

        foreach ($lines as $l) {
            $teks = match (true) {
                $sj->isReturnPickup() => $this->dariRetur($sj, $l),
                $sj->isSiteTransfer() => $this->dariTransferAset($sj, $l),
                default => $this->dariPicking($l),
            };

            if ($teks !== null) {
                $hasil[(int) $l->id] = $teks;
            }
        }

        return $hasil;
    }

    /** @return array<int, string> nomor dokumen sumber SJ tanpa PCK (RET/TRF) */
    public function sourceDocument(Shipment $sj): array
    {
        $nomor = match ($sj->source_type) {
            'goods_return' => GoodsReturn::withoutGlobalScopes()->whereKey($sj->source_id)->value('number'),
            'transfer' => Transfer::withoutGlobalScopes()->whereKey($sj->source_id)->value('number'),
            default => null,
        };

        return $nomor === null ? [] : [(string) $nomor];
    }

    private function dariPicking(ShipmentLine $l): ?string
    {
        $pck = $l->pickTaskLine?->pickTask;

        return $pck === null ? null : $this->nomorSumberPck($pck);
    }

    private function dariRetur(Shipment $sj, ShipmentLine $l): ?string
    {
        $rl = GoodsReturnLine::query()->with('originShipmentLine.shipment:id,number', 'originShipmentLine.pickTaskLine.pickTask', 'originDiscrepancyLine.discrepancy:id,number')
            ->find($l->source_line_id);

        if ($rl === null) {
            return null;
        }

        if ($rl->originShipmentLine !== null) {
            $pck = $rl->originShipmentLine->pickTaskLine?->pickTask;

            return trim(implode(' / ', array_filter([$pck !== null ? $this->nomorSumberPck($pck) : null, $rl->originShipmentLine->shipment?->number])));
        }

        if ($rl->originDiscrepancyLine !== null) {
            return $rl->originDiscrepancyLine->discrepancy?->number;
        }

        if ($rl->serial_id !== null) {
            $ast = AssetHandover::query()->withoutGlobalScopes()
                ->where('serial_id', $rl->serial_id)
                ->where('project_id', $sj->origin_project_id)
                ->where('status', AssetHandoverStatus::CheckedOut->value)
                ->latest('id')->value('number');

            return $ast !== null ? __('Aset').', '.$ast : null;
        }

        return null;
    }

    /** SJ antar site (A-249): AST di proyek asal yang dipindah. */
    private function dariTransferAset(Shipment $sj, ShipmentLine $l): ?string
    {
        if ($l->serial_id === null) {
            return null;
        }

        $ast = AssetHandover::query()->withoutGlobalScopes()
            ->where('serial_id', $l->serial_id)
            ->where('project_id', $sj->origin_project_id)
            ->whereIn('status', [AssetHandoverStatus::CheckedOut->value, AssetHandoverStatus::Transferred->value])
            ->latest('id')->value('number');

        return $ast !== null ? __('Aset').', '.$ast : null;
    }

    private function nomorSumberPck(PickTask $pck): ?string
    {
        return match ($pck->source_type) {
            'material_request' => MaterialRequest::query()->withoutGlobalScopes()->whereKey($pck->source_id)->value('number'),
            'transfer' => Transfer::withoutGlobalScopes()->whereKey($pck->source_id)->value('number'),
            'goods_return' => GoodsReturn::withoutGlobalScopes()->whereKey($pck->source_id)->value('number'),
            default => null,
        };
    }
}
