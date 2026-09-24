<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.submit` — mengajukan REQ (Katalog Status §2.1).
 *
 * Status berikutnya tidak dipilih pengaju melainkan ditentukan asal permintaan
 * (BR-REQ-02): REQ klien selalu singgah di `under_review` supaya staf memetakan
 * baris non-katalog dan menetapkan gudang sumber; REQ internal yang sudah
 * lengkap langsung menunggu approval.
 */
class SubmitRequest
{
    public function __construct(private readonly ApprovalEngine $approval) {}

    public function handle(MaterialRequest $request, ?User $actor = null): MaterialRequest
    {
        if ($request->status !== MaterialRequestStatus::Draft) {
            throw RequestRuleException::rule(
                'BR-REQ-02',
                'Hanya REQ berstatus Draf yang bisa diajukan.',
            );
        }

        // BR-REQ-01: REQ tanpa baris tidak menyatakan kebutuhan apa pun.
        if ($request->openLines()->count() === 0) {
            throw RequestRuleException::rule('BR-REQ-01', 'REQ harus punya minimal satu baris.');
        }

        $berikutnya = $this->statusBerikutnya($request);

        return DB::transaction(function () use ($request, $berikutnya, $actor) {
            $request->forceFill(['status' => $berikutnya])->save();

            activity('request')
                ->performedOn($request)
                ->causedBy($actor)
                ->withProperties(['ke' => $berikutnya->value])
                ->log('REQ diajukan');

            // Katalog §2.1: submitted → pending_approval → snapshot aturan
            // approval; tanpa aturan langsung approved (A-08).
            if ($berikutnya === MaterialRequestStatus::PendingApproval) {
                $this->approval->submit(ApprovalDocumentType::MaterialRequest, $request, $actor);
            }

            return $request->refresh();
        });
    }

    /**
     * BR-REQ-04: REQ internal hanya lolos ke approval bila setiap baris sudah
     * punya gudang sumber dan cara pemenuhan. Yang belum lengkap ikut ditinjau
     * staf, bukan ditolak — pemohon internal tidak selalu tahu gudang mana yang
     * punya stok.
     */
    private function statusBerikutnya(MaterialRequest $request): MaterialRequestStatus
    {
        if ($request->isFromClient()) {
            return MaterialRequestStatus::UnderReview;
        }

        $belumLengkap = $request->lines()->withoutSource()->exists()
            || $request->lines()->unmapped()->exists();

        return $belumLengkap
            ? MaterialRequestStatus::UnderReview
            : MaterialRequestStatus::PendingApproval;
    }
}
