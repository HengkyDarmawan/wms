<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\VendorReturn;

/**
 * Permission: `vendor_return.approve` — memutus tugas approval RTV milik
 * pengguna dari layar detail RTV (Katalog §2.16).
 *
 * Sejak modul approval (20-approval §13) keputusan dicatat lewat mesin
 * approval; RTV baru `approved`/`rejected` setelah keputusan akhir
 * (`VendorReturnApprovalHandler`). Pengaju tidak boleh memutus dokumennya
 * sendiri (BR-APR-03); menolak menuntut Alasan `*` (BR-GEN-11).
 */
class ApproveVendorReturn
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(VendorReturn $rtv, ?User $actor = null): VendorReturn
    {
        $actor = $this->pastikanBisaDiputus($rtv, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::VendorReturn, (int) $rtv->id, $actor);

        return $rtv->refresh();
    }

    public function reject(VendorReturn $rtv, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): VendorReturn
    {
        $actor = $this->pastikanBisaDiputus($rtv, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw ReceiptRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::VendorReturn, (int) $rtv->id, $actor, $reasonCodeId, $keterangan);

        return $rtv->refresh();
    }

    private function pastikanBisaDiputus(VendorReturn $rtv, ?User $actor): User
    {
        if ($rtv->status !== VendorReturnStatus::PendingApproval) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya RTV berstatus Menunggu Approval yang bisa diputus.');
        }

        if ($actor !== null && (int) $rtv->submitted_by === (int) $actor->id) {
            throw ReceiptRuleException::rule('BR-APR-03', 'Pengaju tidak boleh menyetujui atau menolak RTV-nya sendiri.');
        }

        if ($actor === null) {
            throw ReceiptRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        return $actor;
    }
}
