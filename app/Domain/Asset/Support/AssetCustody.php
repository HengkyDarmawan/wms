<?php

declare(strict_types=1);

namespace App\Domain\Asset\Support;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Serial;
use App\Domain\Return\Enums\ReturnSorting;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Warehouse;
use Carbon\CarbonInterface;

/**
 * Titik sambung modul Aset dengan alur keluar-masuk yang sudah ada (A-163):
 *
 * - `checkOut()` — dipanggil `ConfirmDelivery` saat SJ aset diterima di
 *   proyek: AST `checked_out` lahir, payload `asset_checked_out` diperkaya
 *   (serial, proyek, tanggal kembali, meter keluar — matriks §14);
 * - `returned()` — dipanggil `ReturnProgress` saat GRN retur aset diterima:
 *   AST `returned`, hari pakai dihitung (BR-AST-05);
 * - `sortProblem()` / `returnPayload()` — dipanggil `SortGoodsReturn`: aset
 *   dipilah setelah diperiksa, dan `asset_returned` membawa hasil pemeriksaan
 *   (menggantikan `inspection = null` A-116).
 */
class AssetCustody
{
    public function __construct(private readonly DocumentNumber $nomor) {}

    /**
     * @return array<string, mixed> payload tambahan kejadian `asset_checked_out`
     */
    public function checkOut(Shipment $shipment, ShipmentLine $line, ?User $actor = null): array
    {
        $serial = $line->serial_id !== null ? Serial::query()->with('item')->find($line->serial_id) : null;
        $proyek = $shipment->destination_project_id !== null ? Project::query()->withoutGlobalScopes()->find($shipment->destination_project_id) : null;

        if ($serial === null || $proyek === null || ! $serial->item?->isAsset()) {
            return [];
        }

        $shipment->loadMissing('warehouse');
        $jatuhTempo = $proyek->target_end_date !== null && $proyek->target_end_date->gte(now()->startOfDay())
            ? $proyek->target_end_date->toDateString()
            : null;

        $ast = AssetHandover::create([
            'number' => $this->nomor->next('AST', (string) $shipment->warehouse->code),
            'serial_id' => $serial->id,
            'item_id' => $serial->item_id,
            'project_id' => $proyek->id,
            'warehouse_id' => $shipment->warehouse_id,
            'shipment_id' => $shipment->id,
            'shipment_line_id' => $line->id,
            'status' => AssetHandoverStatus::CheckedOut,
            'checked_out_at' => now(),
            'due_return_date' => $jatuhTempo,
            'condition_out' => $serial->condition_grade,
            'meter_out' => $this->meterTerakhir($serial),
            'updated_by' => $actor?->id,
        ]);

        $serial->forceFill(['due_return_date' => $jatuhTempo])->save();

        activity('asset')->performedOn($ast)->causedBy($actor)
            ->withProperties(['sj' => $shipment->number, 'serial' => $serial->serial_no])
            ->log('Aset diserahkan ke proyek '.$proyek->code.' (SJ '.$shipment->number.')');

        return array_filter([
            'handover_number' => $ast->number,
            'serial_id' => (int) $serial->id,
            'serial_no' => $serial->serial_no,
            'project_code' => $proyek->code,
            'due_return_date' => $jatuhTempo,
            'meter_unit' => $serial->meter_unit?->value,
            'meter_out' => $ast->meter_out !== null ? (float) $ast->meter_out : null,
            'condition_out' => $serial->condition_grade,
        ], fn ($v) => $v !== null);
    }

    /**
     * SJ antar site diterima proyek tujuan (A-249, Katalog §2.11 `checked_out →
     * transferred`): AST proyek asal ditutup `transferred` dengan hari pakainya,
     * AST baru `checked_out` lahir di proyek tujuan merujuk AST lama. Tanpa
     * pemeriksaan: aset tidak kembali ke gudang (BR-AST-03 berlaku saat retur).
     *
     * @return array<string, mixed> payload kejadian `asset_transferred`
     */
    public function transfer(Shipment $shipment, ShipmentLine $line, bool $rusak, ?User $actor = null): array
    {
        $serial = Serial::query()->with('item')->findOrFail($line->serial_id);
        $asal = Project::query()->withoutGlobalScopes()->findOrFail($shipment->origin_project_id);
        $tujuan = Project::query()->withoutGlobalScopes()->findOrFail($shipment->destination_project_id);
        $sekarang = now();

        $lama = AssetHandover::query()->withoutGlobalScopes()
            ->where('serial_id', $serial->id)
            ->where('project_id', $asal->id)
            ->where('status', AssetHandoverStatus::CheckedOut->value)
            ->latest('id')->first()
            ?? $this->susulanDiProyek($serial, $asal, (int) $shipment->warehouse_id, $actor);

        $jatuhTempo = $tujuan->target_end_date !== null && $tujuan->target_end_date->gte(now()->startOfDay())
            ? $tujuan->target_end_date->toDateString()
            : null;

        $baru = AssetHandover::create([
            'number' => $this->nomor->next('AST', (string) $lama->warehouse?->code),
            'serial_id' => $serial->id,
            'item_id' => $serial->item_id,
            'project_id' => $tujuan->id,
            'warehouse_id' => $lama->warehouse_id,
            'shipment_id' => $shipment->id,
            'shipment_line_id' => $line->id,
            'transfer_id' => $shipment->source_id,
            'previous_handover_id' => $lama->id,
            'status' => AssetHandoverStatus::CheckedOut,
            'checked_out_at' => $sekarang,
            'due_return_date' => $jatuhTempo,
            'condition_out' => $serial->condition_grade,
            'meter_out' => $this->meterTerakhir($serial),
            'notes' => $rusak ? 'Diterima rusak di proyek tujuan (lihat bukti terima SJ '.$shipment->number.')' : null,
            'updated_by' => $actor?->id,
        ]);

        $lama->forceFill([
            'status' => AssetHandoverStatus::Transferred,
            'transfer_id' => $shipment->source_id,
            'next_handover_id' => $baru->id,
            'returned_at' => $sekarang,
            'usage_days' => self::usageDays($lama->checked_out_at, $sekarang),
            'updated_by' => $actor?->id,
        ])->save();

        $serial->forceFill(['due_return_date' => $jatuhTempo])->save();

        activity('asset')->performedOn($lama)->causedBy($actor)
            ->withProperties(['sj' => $shipment->number, 'ke' => $tujuan->code, 'hari_pakai' => $lama->usage_days])
            ->log('Aset dipindah ke proyek '.$tujuan->code.' ('.$baru->number.')');

        activity('asset')->performedOn($baru)->causedBy($actor)
            ->withProperties(['sj' => $shipment->number, 'dari' => $asal->code])
            ->log('Aset diterima dari proyek '.$asal->code.' ('.$lama->number.')');

        return array_filter([
            'handover_number' => $baru->number,
            'previous_handover_number' => $lama->number,
            'serial_id' => (int) $serial->id,
            'serial_no' => $serial->serial_no,
            'from_project_code' => $asal->code,
            'to_project_code' => $tujuan->code,
            'usage_days_from_project' => $lama->usage_days,
            'due_return_date' => $jatuhTempo,
            'meter_unit' => $serial->meter_unit?->value,
            'meter_out' => $baru->meter_out !== null ? (float) $baru->meter_out : null,
            'received_damaged' => $rusak ?: null,
        ], fn ($v) => $v !== null);
    }

    /** GRN retur berisi aset `received` → AST `returned` (Katalog §2.11). */
    public function returned(GoodsReturnLine $line, ?User $actor = null): ?AssetHandover
    {
        if ($line->serial_id === null) {
            return null;
        }

        $line->loadMissing('goodsReturn', 'item');

        if (! $line->item?->isAsset()) {
            return null;
        }

        $ret = $line->goodsReturn;
        $ast = AssetHandover::query()->withoutGlobalScopes()
            ->where('serial_id', $line->serial_id)
            ->where('project_id', $ret->project_id)
            ->where('status', AssetHandoverStatus::CheckedOut->value)
            ->latest('id')->first()
            ?? $this->susulan($line, $actor);

        $kembali = now();

        $ast->forceFill([
            'status' => AssetHandoverStatus::Returned,
            'goods_return_id' => $ret->id,
            'goods_return_line_id' => $line->id,
            'returned_at' => $kembali,
            'usage_days' => self::usageDays($ast->checked_out_at, $kembali),
            'updated_by' => $actor?->id,
        ])->save();

        activity('asset')->performedOn($ast)->causedBy($actor)
            ->withProperties(['ret' => $ret->number, 'hari_pakai' => $ast->usage_days])
            ->log('Aset kembali ke bin Retur (RET '.$ret->number.'); menunggu pemeriksaan');

        return $ast;
    }

    /**
     * Pesan penolakan bila baris aset belum boleh dipilah dengan hasil ini, atau
     * null bila boleh. Aset dipilah setelah diperiksa: A/B → layak; C/D → rusak
     * (BR-AST-03, A-164). Aset tidak pernah menjadi waste atau offcut.
     */
    public function sortProblem(GoodsReturnLine $line, ReturnSorting $sorting): ?string
    {
        $ast = $this->untukBarisRetur($line);

        if ($ast === null) {
            return null;
        }

        if ($ast->status !== AssetHandoverStatus::Inspected) {
            return 'Aset '.$line->serial?->serial_no.' belum diperiksa ('.$ast->number.'); periksa dulu sebelum dipilah.'; // BR-AST-03
        }

        $hasil = $ast->latestInspection()?->resulting_state;
        $boleh = $hasil === AssetState::Available ? ReturnSorting::Good : ReturnSorting::Damaged;

        if ($sorting !== $boleh) {
            return 'Aset '.$line->serial?->serial_no.' hasil pemeriksaan '.$hasil?->label().' dipilah sebagai '.$boleh->label().'.';
        }

        return null;
    }

    /** @return array<string, mixed> payload `asset_returned` (matriks §14) */
    public function returnPayload(GoodsReturnLine $line): array
    {
        $ast = $this->untukBarisRetur($line);
        $periksa = $ast?->latestInspection();

        if ($ast === null) {
            return ['inspection' => null, 'serial_id' => $line->serial_id];
        }

        return [
            'serial_id' => $line->serial_id,
            'handover_number' => $ast->number,
            'usage_days' => $ast->usage_days,
            'usage_hours' => $ast->usage_hours !== null ? (float) $ast->usage_hours : null,
            'usage_km' => $ast->usage_km !== null ? (float) $ast->usage_km : null,
            'meter_out' => $ast->meter_out !== null ? (float) $ast->meter_out : null,
            'meter_in' => $ast->meter_in !== null ? (float) $ast->meter_in : null,
            'inspection' => $periksa === null ? null : [
                'condition_grade' => $periksa->condition_grade->value,
                'condition_score' => $periksa->condition_score,
                'resulting_state' => $periksa->resulting_state->value,
                'inspected_at' => $periksa->inspected_at?->toIso8601String(),
            ],
        ];
    }

    /** BR-AST-05: hari kalender zona company, inklusif hari pertama. */
    public static function usageDays(CarbonInterface $keluar, CarbonInterface $kembali): int
    {
        $zona = tenant()?->timezone ?? 'Asia/Jakarta';

        return (int) $keluar->copy()->setTimezone($zona)->startOfDay()
            ->diffInDays($kembali->copy()->setTimezone($zona)->startOfDay()) + 1;
    }

    private function untukBarisRetur(GoodsReturnLine $line): ?AssetHandover
    {
        return AssetHandover::query()->withoutGlobalScopes()
            ->where('goods_return_line_id', $line->split_from_line_id ?? $line->id)
            ->latest('id')->first();
    }

    /** Pembacaan meter terakhir: meter kembali pemeriksaan terakhir, atau akumulasi serial. */
    private function meterTerakhir(Serial $serial): ?float
    {
        if ($serial->meter_unit === null || $serial->meter_unit->value === 'none') {
            return null;
        }

        $terakhir = $serial->inspections()->whereNotNull('meter_in')->latest('id')->value('meter_in');

        return $terakhir !== null ? (float) $terakhir : (float) $serial->meter_total;
    }

    /** AST susulan untuk aset di On-site proyek tanpa catatan serah terima (A-249). */
    private function susulanDiProyek(Serial $serial, Project $proyek, int $warehouseId, ?User $actor): AssetHandover
    {
        $masuk = StockMovement::query()
            ->where('serial_id', $serial->id)
            ->whereHas('toBin', fn ($q) => $q->withoutGlobalScopes()->where('bin_type', BinType::OnSite->value))
            ->latest('id')->first();

        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($warehouseId);

        $ast = AssetHandover::create([
            'number' => $this->nomor->next('AST', (string) $gudang->code),
            'serial_id' => $serial->id,
            'item_id' => $serial->item_id,
            'project_id' => $proyek->id,
            'warehouse_id' => $gudang->id,
            'status' => AssetHandoverStatus::CheckedOut,
            'checked_out_at' => $masuk?->occurred_at ?? now(),
            'meter_out' => $this->meterTerakhir($serial),
            'notes' => 'AST susulan: aset sudah di proyek tanpa catatan serah terima',
            'updated_by' => $actor?->id,
        ]);

        activity('asset')->performedOn($ast)->causedBy($actor)->log('AST susulan dibuat saat aset dipindah antar proyek');

        return $ast;
    }

    /**
     * Aset di On-site tanpa AST (mis. dipindahkan sebelum modul Aset ada):
     * AST dibuat susulan dari pergerakan terakhir ke bin On-site proyek.
     */
    private function susulan(GoodsReturnLine $line, ?User $actor): AssetHandover
    {
        $ret = $line->goodsReturn;
        $masuk = StockMovement::query()
            ->where('serial_id', $line->serial_id)
            ->whereHas('toBin', fn ($q) => $q->withoutGlobalScopes()->where('bin_type', BinType::OnSite->value))
            ->latest('id')->first();

        $ast = AssetHandover::create([
            'number' => $this->nomor->next('AST', (string) $ret->toWarehouse?->code),
            'serial_id' => $line->serial_id,
            'item_id' => $line->item_id,
            'project_id' => $ret->project_id,
            'warehouse_id' => $ret->to_warehouse_id,
            'status' => AssetHandoverStatus::CheckedOut,
            'checked_out_at' => $masuk?->occurred_at ?? now(),
            'meter_out' => $this->meterTerakhir(Serial::query()->findOrFail($line->serial_id)),
            'notes' => 'AST susulan: aset sudah di proyek tanpa catatan serah terima',
            'updated_by' => $actor?->id,
        ]);

        activity('asset')->performedOn($ast)->causedBy($actor)->log('AST susulan dibuat saat aset kembali');

        return $ast;
    }
}
