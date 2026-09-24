<?php

declare(strict_types=1);

namespace App\Domain\Return\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Stock\Actions\ManageReservation;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `return.cancel` — `submitted`/`pending_approval`/`approved` →
 * `cancelled` (Katalog §2.8). Guard: belum ada SJ balik berangkat — RET sudah
 * `in_progress` begitu SJ balik disusun. Alasan wajib (BR-GEN-11).
 *
 * Tugas picking SJ balik yang belum selesai ikut dibatalkan; bila sudah
 * selesai, barangnya sudah di Loading Area Gudang Site dan RET tidak bisa
 * dibatalkan lagi (A-111). Cadangan keras stok Gudang Site dilepas.
 */
class CancelGoodsReturn
{
    public function __construct(
        private readonly ApprovalEngine $approval,
        private readonly ManageReservation $reservasi,
        private readonly ProcessPickTask $picking,
    ) {}

    public function handle(GoodsReturn $ret, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): GoodsReturn
    {
        if (! $ret->status->isCancellable()) {
            throw ReturnRuleException::rule(
                'BR-GEN-03',
                'RET berstatus '.$ret->status->label().' tidak bisa dibatalkan; barangnya sudah dalam perjalanan atau diterima.',
            );
        }

        $pck = $ret->livePickTask();

        if ($pck !== null && $pck->status === PickTaskStatus::Completed) {
            throw ReturnRuleException::rule(
                'BR-GEN-03',
                'Barang RET ini sudah dipetik ke Loading Area ('.$pck->number.'); lanjutkan dengan SJ balik.',
            );
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw ReturnRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        return DB::transaction(function () use ($ret, $pck, $reasonCodeId, $keterangan, $actor) {
            if ($pck !== null) {
                try {
                    $this->picking->cancel($pck, $reasonCodeId, $actor);
                } catch (ShipmentRuleException $e) {
                    throw ReturnRuleException::rule($e->rule, $e->getMessage());
                }
            }

            $ret->forceFill([
                'status' => GoodsReturnStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'cancelled_at' => now(),
            ])->save();

            $this->approval->withdraw(ApprovalDocumentType::GoodsReturn, (int) $ret->id, 'RET dibatalkan.', $actor);
            $this->reservasi->releaseForDocument('goods_return', (int) $ret->id, 'RETURN_CANCELLED', $actor);

            activity('return')->performedOn($ret)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
                ->log('RET dibatalkan');

            return $ret->refresh();
        });
    }
}
