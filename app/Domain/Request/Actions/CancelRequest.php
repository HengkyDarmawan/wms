<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\PurchaseRequest\Support\BackorderPurchases;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Transfer\Support\BackorderTransfers;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.cancel` — membatalkan seluruh REQ (Katalog Status §2.1).
 *
 * Sebelum disetujui, pembatalan hanya soal status. Sesudahnya ia melepas
 * reservasi, dan karena itu dibatasi: REQ yang barangnya sudah berjalan tidak
 * bisa ditarik kembali — sisanya diselesaikan lewat `closed_short` atau retur.
 */
class CancelRequest
{
    public function __construct(
        private readonly ManageReservation $reservasi,
        private readonly ApprovalEngine $approval,
    ) {}

    public function handle(MaterialRequest $request, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): MaterialRequest
    {
        $this->pastikanBisaDibatalkan($request);

        if ($reasonCodeId === null) {
            throw RequestRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        return DB::transaction(function () use ($request, $reasonCodeId, $notes, $actor) {
            $request->forceFill([
                'status' => MaterialRequestStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
            ])->save();

            $terbuka = $request->lines()->open()->pluck('id')->all();
            $request->lines()->open()->update(['status' => RequestLineStatus::Cancelled->value]);

            // Tugas approval yang masih terbuka ikut dihentikan (20-approval §4).
            $this->approval->withdraw(ApprovalDocumentType::MaterialRequest, (int) $request->id, 'REQ dibatalkan.', $actor);

            // BR-STK-05: reservasi dilepas seluruhnya, dalam transaksi yang sama.
            $this->reservasi->releaseForDocument(
                'material_request',
                (int) $request->id,
                'REQUEST_CANCELLED',
                $actor,
            );

            // BR-REQ-15, A-108: TRF backorder yang belum berjalan ikut dibatalkan.
            app(BackorderTransfers::class)->releaseForRequestLines($terbuka, $reasonCodeId, $actor);
            // BR-REQ-15: PRQ backorder yang belum diteruskan ikut dibatalkan (A-171).
            app(BackorderPurchases::class)->releaseForRequestLines($terbuka, $reasonCodeId, $actor);

            activity('request')
                ->performedOn($request)
                ->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'notes' => $notes])
                ->log('REQ dibatalkan');

            return $request->refresh();
        });
    }

    /**
     * Katalog Status §2.1: `draft`, `submitted`, `under_review`, dan
     * `pending_approval` bebas dibatalkan; `approved` dan `in_progress` hanya
     * selama belum ada SJ terkirim.
     */
    private function pastikanBisaDibatalkan(MaterialRequest $request): void
    {
        $bebas = [
            MaterialRequestStatus::Draft,
            MaterialRequestStatus::Submitted,
            MaterialRequestStatus::UnderReview,
            MaterialRequestStatus::PendingApproval,
        ];

        if (in_array($request->status, $bebas, true)) {
            return;
        }

        $bersyarat = [MaterialRequestStatus::Approved, MaterialRequestStatus::InProgress];

        if (! in_array($request->status, $bersyarat, true)) {
            throw RequestRuleException::rule(
                'BR-REQ-09',
                'REQ berstatus '.$request->status->label().' tidak bisa dibatalkan.',
            );
        }

        // Katalog §2.1: tidak boleh ada SJ `shipped`. ShipShipment mencatat jumlah
        // terkirim ke baris REQ lewat RequestFulfillment, jadi cukup dibaca dari sini.
        $sudahJalan = $request->lines()->where('qty_shipped', '>', 0)->exists();

        if ($sudahJalan) {
            throw RequestRuleException::rule(
                'BR-REQ-09',
                'Sebagian barang sudah dikirim; tutup dengan sisa alih-alih membatalkan.',
            );
        }
    }
}
