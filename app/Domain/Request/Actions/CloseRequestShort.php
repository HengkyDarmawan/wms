<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Transfer\Support\BackorderTransfers;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.close_short` — menutup REQ dengan sisa (BR-REQ-09).
 *
 * Dipakai ketika sisanya tidak lagi dibutuhkan: lebih jujur daripada menunggu
 * REQ selesai yang tidak akan pernah terjadi. Reservasi dan backorder sisa
 * dilepas supaya stok yang tertahan kembali bisa dijanjikan ke dokumen lain.
 */
class CloseRequestShort
{
    public function __construct(private readonly ManageReservation $reservasi) {}

    public function handle(MaterialRequest $request, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): MaterialRequest
    {
        $bisa = [MaterialRequestStatus::InProgress, MaterialRequestStatus::PartiallyFulfilled];

        if (! in_array($request->status, $bisa, true)) {
            throw RequestRuleException::rule(
                'BR-REQ-09',
                'Hanya REQ yang sedang diproses atau terpenuhi sebagian yang bisa ditutup dengan sisa.',
            );
        }

        if ($reasonCodeId === null) {
            throw RequestRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan penutupan wajib dipilih.');
        }

        return DB::transaction(function () use ($request, $reasonCodeId, $notes, $actor) {
            $request->forceFill([
                'status' => MaterialRequestStatus::ClosedShort,
                'closed_reason_id' => $reasonCodeId,
            ])->save();

            // Baris ditutup, bukan dibatalkan: yang sudah terkirim tetap sah.
            $terbuka = $request->lines()->open()->pluck('id')->all();
            $request->lines()->open()->update([
                'status' => RequestLineStatus::Closed->value,
                'qty_backorder' => 0,
            ]);

            $dilepas = $this->reservasi->releaseForDocument(
                'material_request',
                (int) $request->id,
                'CLOSED_SHORT',
                $actor,
            );

            // BR-REQ-09, A-108: TRF backorder yang belum berjalan ikut dibatalkan.
            app(BackorderTransfers::class)->releaseForRequestLines($terbuka, $reasonCodeId, $actor);

            activity('request')
                ->performedOn($request)
                ->causedBy($actor)
                ->withProperties([
                    'reason_code_id' => $reasonCodeId,
                    'notes' => $notes,
                    'reservasi_dilepas' => $dilepas,
                ])
                ->log('REQ ditutup dengan sisa');

            return $request->refresh();
        });
    }
}
