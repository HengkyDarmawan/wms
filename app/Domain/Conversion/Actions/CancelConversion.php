<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `conversion.cancel` — `draft`/`pending_approval → cancelled`
 * (Katalog §2.10). Belum ada stok yang bergerak; CNV yang menunggu approval
 * ditarik dari mesin approval. CNV `completed` hanya dikoreksi lewat CNV
 * pembalik (BR-GEN-04, BR-CNV-05). Alasan `*` wajib (BR-GEN-11).
 */
class CancelConversion
{
    public function __construct(private readonly ApprovalEngine $approval) {}

    public function handle(Conversion $conversion, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): Conversion
    {
        if (! $conversion->status->isCancellable()) {
            throw ConversionRuleException::rule('BR-GEN-04', 'CNV berstatus '.$conversion->status->label().' tidak bisa dibatalkan; koreksi konversi yang sudah selesai lewat CNV pembalik.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw ConversionRuleException::field('BR-GEN-11', 'reason', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : null;

        return DB::transaction(function () use ($conversion, $reasonCodeId, $keterangan, $actor) {
            $menunggu = $conversion->status === ConversionStatus::PendingApproval;

            $conversion->forceFill([
                'status' => ConversionStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'cancelled_at' => now(),
                'notes' => $keterangan ?? $conversion->notes,
            ])->save();

            if ($menunggu) {
                $this->approval->withdraw(ApprovalDocumentType::Conversion, (int) $conversion->id, 'CNV dibatalkan.', $actor);
            }

            activity('conversion')->performedOn($conversion)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
                ->log('CNV dibatalkan');

            return $conversion->refresh();
        });
    }
}
