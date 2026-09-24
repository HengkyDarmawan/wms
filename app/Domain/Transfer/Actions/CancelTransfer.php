<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Models\Transfer;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `transfer.cancel` — `submitted`/`pending_approval`/`approved` →
 * `cancelled` (Katalog §2.7).
 *
 * Guard: belum ada SJ `shipped` — dan karena TRF menjadi `in_progress` begitu
 * PCK dibuat, pembatalan hanya mungkin sebelum ada tugas picking hidup.
 * Efek: reservasi lunak di gudang asal dilepas (BR-STK-05, BR-STK-16).
 */
class CancelTransfer
{
    public function __construct(
        private readonly ApprovalEngine $approval,
        private readonly ManageReservation $reservasi,
    ) {}

    public function handle(Transfer $trf, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): Transfer
    {
        if (! $trf->status->isCancellable()) {
            throw TransferRuleException::rule(
                'BR-GEN-03',
                'TRF berstatus '.$trf->status->label().' tidak bisa dibatalkan; barangnya sudah dipetik atau dikirim.',
            );
        }

        if ($trf->hasLivePickTask()) {
            throw TransferRuleException::rule(
                'BR-GEN-03',
                'TRF ini sudah punya tugas picking; batalkan tugas picking-nya lebih dulu.',
            );
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw TransferRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        return DB::transaction(function () use ($trf, $reasonCodeId, $keterangan, $actor) {
            $trf->forceFill([
                'status' => TransferStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'cancelled_at' => now(),
            ])->save();

            $this->approval->withdraw(ApprovalDocumentType::Transfer, (int) $trf->id, 'TRF dibatalkan.', $actor);

            $dilepas = $this->reservasi->releaseForDocument('transfer', (int) $trf->id, 'TRANSFER_CANCELLED', $actor);

            activity('transfer')->performedOn($trf)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan, 'reservasi_dilepas' => $dilepas])
                ->log('TRF dibatalkan');

            return $trf->refresh();
        });
    }
}
