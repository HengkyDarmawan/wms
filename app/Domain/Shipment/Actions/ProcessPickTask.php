<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Shipment\Support\WarehouseBins;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `pick.start`, `pick.complete`, `pick.cancel` — menjalankan tugas
 * picking (Katalog Status §2.2).
 *
 * Penyelesaian PCK adalah pergerakan stok sungguhan yang pertama di rantai ini:
 * barang pindah dari bin penyimpanan ke Loading Area. Sampai titik itu, semua
 * yang dilakukan modul Request dan alokasi keras hanyalah janji.
 */
class ProcessPickTask
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly ManageReservation $reservasi,
        private readonly WarehouseBins $bins,
        private readonly ChangeBinStatus $binStatus,
    ) {}

    public function start(PickTask $task, ?User $actor = null): PickTask
    {
        if ($task->status !== PickTaskStatus::Pending) {
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Hanya tugas berstatus Menunggu yang bisa dimulai.',
            );
        }

        // BR-OPN-02: bin yang sedang dihitung tidak boleh diambil isinya.
        $beku = $task->lines()
            ->whereHas('bin', fn ($q) => $q->withoutGlobalScopes()->where('bin_status', BinStatus::Frozen->value))
            ->with('bin:id,code')
            ->first();

        if ($beku !== null) {
            throw ShipmentRuleException::rule(
                'BR-OPN-02',
                'Bin '.$beku->bin?->code.' sedang dibeku untuk opname; picking dari bin itu ditolak.',
            );
        }

        $task->forceFill([
            'status' => PickTaskStatus::InProgress,
            'started_at' => now(),
            'assigned_to' => $task->assigned_to ?? $actor?->id,
        ])->save();

        activity('shipment')->performedOn($task)->causedBy($actor)->log('Picking dimulai');

        return $task->refresh();
    }

    /**
     * Mencatat jumlah yang benar-benar diambil satu baris.
     *
     * Bin boleh diganti dari saran sistem, tetapi penggantian menuntut alasan
     * (BR-SJ-01): bin yang dipakai ikut ke riwayat mutasi dan dipakai
     * menelusuri selisih saat opname.
     */
    public function recordLine(
        PickTaskLine $line,
        float $qtyPicked,
        ?int $binId = null,
        ?string $overrideReason = null,
        ?int $shortReasonId = null,
        ?User $actor = null,
    ): PickTaskLine {
        if ($line->pickTask->status !== PickTaskStatus::InProgress) {
            throw ShipmentRuleException::rule('BR-SJ-01', 'Tugas picking harus berstatus Dikerjakan.');
        }

        if ($qtyPicked < 0) {
            throw ShipmentRuleException::field('BR-SJ-02', 'qty_picked', 'Jumlah diambil tidak boleh negatif.');
        }

        if ($qtyPicked > (float) $line->qty_allocated) {
            throw ShipmentRuleException::field(
                'BR-SJ-02',
                'qty_picked',
                'Jumlah diambil tidak boleh melebihi alokasi ('.(float) $line->qty_allocated.').',
            );
        }

        $binBaru = $binId ?? (int) $line->bin_id;

        if ($binBaru !== (int) $line->bin_id) {
            if (trim((string) $overrideReason) === '') {
                throw ShipmentRuleException::field(
                    'BR-SJ-01',
                    'override_reason',
                    'Mengambil dari bin lain menuntut alasan.',
                );
            }

            Bin::query()->withoutGlobalScopes()->findOrFail($binBaru);
        }

        $line->forceFill([
            'bin_id' => $binBaru,
            'qty_picked' => $qtyPicked,
            'override_reason' => $binBaru !== (int) $line->suggested_bin_id ? $overrideReason : null,
            'short_reason_id' => $qtyPicked < (float) $line->qty_allocated ? $shortReasonId : null,
            'scanned_at' => now(),
        ])->save();

        return $line->refresh();
    }

    /**
     * Menyelesaikan PCK: stok pindah ke Loading Area.
     *
     * Short pick ditangani di sini juga (BR-SJ-02): alasannya wajib, binnya
     * ditandai perlu dihitung, dan sisanya kembali menjadi backorder baris REQ
     * supaya kebutuhan itu tidak hilang diam-diam.
     */
    public function complete(PickTask $task, ?User $actor = null): PickTask
    {
        if ($task->status !== PickTaskStatus::InProgress) {
            throw ShipmentRuleException::rule('BR-SJ-01', 'Hanya tugas yang sedang dikerjakan yang bisa diselesaikan.');
        }

        $lines = $task->lines()->with('item', 'bin')->get();

        foreach ($lines as $l) {
            if ($l->scanned_at === null) {
                throw ShipmentRuleException::rule(
                    'BR-SJ-01',
                    'Masih ada baris yang belum dicatat jumlah ambilnya.',
                );
            }

            // BR-SJ-02 dan BR-GEN-11: kekurangan fisik selalu punya sebab.
            if ($l->isShort() && $l->short_reason_id === null) {
                throw ShipmentRuleException::rule(
                    'BR-SJ-02',
                    'Baris yang diambil kurang dari alokasi wajib menyebutkan alasan.',
                );
            }
        }

        $loading = $this->bins->loadingArea($task->warehouse);

        return DB::transaction(function () use ($task, $lines, $loading, $actor) {
            foreach ($lines as $l) {
                $this->pindahkan($task, $l, $loading, $actor);
                $this->tanganiShortPick($task, $l, $actor);
            }

            // Alokasi keras berubah menjadi pergerakan; tidak lagi mengurangi
            // stok tersedia karena barangnya memang sudah pindah (BR-STK-05).
            $this->reservasi->releaseForDocument('pick_task', (int) $task->id, 'PICK_COMPLETED', $actor);

            $task->forceFill([
                'status' => PickTaskStatus::Completed,
                'completed_at' => now(),
            ])->save();

            activity('shipment')
                ->performedOn($task)
                ->causedBy($actor)
                ->withProperties(['short_pick' => $task->hasShortPick()])
                ->log('Picking selesai');

            return $task->refresh();
        });
    }

    /** `pending`/`in_progress` → `cancelled`; alokasi dilepas, stok tidak bergerak. */
    public function cancel(PickTask $task, ?int $reasonCodeId, ?User $actor = null): PickTask
    {
        if ($task->status->isFinal()) {
            throw ShipmentRuleException::rule(
                'BR-SJ-01',
                'Tugas berstatus '.$task->status->label().' tidak bisa dibatalkan.',
            );
        }

        if ($reasonCodeId === null) {
            throw ShipmentRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        return DB::transaction(function () use ($task, $reasonCodeId, $actor) {
            $this->reservasi->releaseForDocument('pick_task', (int) $task->id, 'PICK_CANCELLED', $actor);

            $task->forceFill([
                'status' => PickTaskStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
            ])->save();

            activity('shipment')
                ->performedOn($task)
                ->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId])
                ->log('Picking dibatalkan');

            return $task->refresh();
        });
    }

    private function pindahkan(PickTask $task, PickTaskLine $line, Bin $loading, ?User $actor): void
    {
        if ((float) $line->qty_picked <= 0) {
            return;
        }

        try {
            $this->ledger->post(new MovementRequest(
                item: $line->item,
                qtyBase: (float) $line->qty_picked,
                fromBinId: (int) $line->bin_id,
                toBinId: (int) $loading->id,
                lotId: $line->lot_id,
                serialId: $line->serial_id,
                pieceId: $line->piece_id,
                documentType: 'pick_task',
                documentId: (int) $task->id,
                documentLineId: (int) $line->id,
                documentNumber: $task->number,
                performedBy: $actor,
            ));
        } catch (LedgerException $e) {
            throw ShipmentRuleException::rule(
                $e->rule,
                'Baris '.$line->item?->code.' gagal dipindahkan: '.$e->getMessage(),
            );
        }
    }

    /**
     * BR-SJ-02: bin ditandai perlu dihitung dan sisanya menjadi backorder.
     *
     * Penandaan binnya penting — selisih fisik hampir selalu berarti pembukuan
     * bin itu meleset, dan sesi opname berikutnya harus mendahulukannya.
     */
    private function tanganiShortPick(PickTask $task, PickTaskLine $line, ?User $actor): void
    {
        if (! $line->isShort()) {
            return;
        }

        $bin = Bin::query()->withoutGlobalScopes()->find($line->bin_id);

        if ($bin !== null) {
            $this->binStatus->flagForCount($bin, true, $actor);
        }

        if ($task->source_type === 'material_request') {
            $baris = MaterialRequestLine::query()->find($line->source_line_id);

            if ($baris !== null) {
                $baris->forceFill([
                    'qty_backorder' => (float) $baris->qty_backorder + $line->shortQty(),
                ])->save();
            }
        }

        activity('shipment')
            ->performedOn($task)
            ->causedBy($actor)
            ->withProperties([
                'baris' => $line->id,
                'dialokasikan' => (float) $line->qty_allocated,
                'diambil' => (float) $line->qty_picked,
                'bin' => $bin?->code,
            ])
            ->log('Short pick tercatat');
    }
}
