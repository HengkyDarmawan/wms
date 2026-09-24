<?php

declare(strict_types=1);

namespace App\Domain\Count\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;

/**
 * Permission: `count.approve` — memutus tugas approval sesi opname dari
 * halaman sesi (Katalog §2.13, A-86).
 *
 * Guard BR-OPN-09: penghitung sesi tidak boleh menyetujui sesinya; mesin
 * approval juga sudah melewati mereka saat menentukan approver (A-96).
 * Setelah lapis terakhir setuju: ADJ diposting, bin dibuka, sesi ditutup.
 */
class ApproveStockCount
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(StockCount $count, ?User $actor = null, ?string $comment = null): StockCount
    {
        $actor = $this->pastikan($count, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::StockCount, (int) $count->id, $actor, $comment);

        return $count->refresh();
    }

    public function reject(StockCount $count, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): StockCount
    {
        $actor = $this->pastikan($count, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw CountRuleException::field('BR-GEN-11', 'reason', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::StockCount, (int) $count->id, $actor, $reasonCodeId, $keterangan);

        return $count->refresh();
    }

    private function pastikan(StockCount $count, ?User $actor): User
    {
        if ($count->status !== StockCountStatus::Reconciling) {
            throw CountRuleException::rule('BR-GEN-01', 'Hanya sesi berstatus Rekonsiliasi yang bisa diputus.');
        }

        if ($actor === null) {
            throw CountRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if (in_array((int) $actor->id, $count->counterIds(), true)) {
            throw CountRuleException::rule('BR-OPN-09', 'Penghitung sesi ini tidak boleh menyetujui atau menolak hasilnya.');
        }

        return $actor;
    }
}
