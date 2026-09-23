<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Exceptions\LedgerException;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.approve` — menyetujui atau menolak REQ.
 *
 * Persetujuan adalah saat REQ berhenti menjadi niat dan mulai mengikat stok:
 * setiap baris bersumber stok mendapat **reservasi lunak** (BR-REQ-05), yaitu
 * janji bahwa barang itu tidak akan dijanjikan ke dokumen lain.
 *
 * Aturan approval berlapis ada di modul `approval` yang belum dibangun. Sampai
 * modul itu ada, berlaku bunyi Katalog Status §2.1 untuk company tanpa aturan
 * approval: REQ disetujui langsung oleh yang berwenang (BR-GEN-10).
 */
class ApproveRequest
{
    public function __construct(private readonly ManageReservation $reservasi) {}

    public function handle(MaterialRequest $request, ?User $actor = null): MaterialRequest
    {
        $this->pastikanMenungguApproval($request);
        $this->pastikanBukanPemohonSendiri($request, $actor);

        // BR-REQ-05: baris tanpa sumber menahan approval seluruh dokumen.
        if ($request->lines()->withoutSource()->exists()) {
            throw RequestRuleException::rule(
                'BR-REQ-05',
                'Masih ada baris tanpa gudang sumber atau cara pemenuhan.',
            );
        }

        return DB::transaction(function () use ($request, $actor) {
            $request->forceFill([
                'status' => MaterialRequestStatus::Approved,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ])->save();

            $this->buatReservasi($request, $actor);

            activity('request')->performedOn($request)->causedBy($actor)->log('REQ disetujui');

            return $request->refresh();
        });
    }

    public function reject(MaterialRequest $request, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): MaterialRequest
    {
        $this->pastikanMenungguApproval($request);
        $this->pastikanBukanPemohonSendiri($request, $actor);

        if ($reasonCodeId === null) {
            throw RequestRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan penolakan wajib dipilih.');
        }

        return DB::transaction(function () use ($request, $reasonCodeId, $notes, $actor) {
            $request->forceFill([
                'status' => MaterialRequestStatus::Rejected,
                'cancel_reason_id' => $reasonCodeId,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ])->save();

            activity('request')
                ->performedOn($request)
                ->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'notes' => $notes])
                ->log('REQ ditolak approver');

            return $request->refresh();
        });
    }

    /**
     * Reservasi lunak untuk baris bersumber stok.
     *
     * Baris bersumber transfer atau pembelian belum punya barang untuk
     * dijanjikan; reservasinya lahir belakangan saat TRF atau PRQ-nya tiba
     * (BR-REQ-08), di modul masing-masing.
     */
    private function buatReservasi(MaterialRequest $request, ?User $actor): void
    {
        $baris = $request->lines()
            ->open()
            ->with('item', 'sourceWarehouse')
            ->get()
            ->filter(fn (MaterialRequestLine $l) => $l->fulfillment_source?->reservesOnApproval() === true);

        foreach ($baris as $l) {
            if ($l->item === null || $l->sourceWarehouse === null) {
                continue;
            }

            try {
                $this->reservasi->reserveSoft(
                    $l->item,
                    $l->sourceWarehouse,
                    (float) $l->qty_base,
                    'material_request',
                    (int) $request->id,
                    (int) $l->id,
                    $actor,
                );
            } catch (LedgerException $e) {
                // Stok tersedia berkurang antara tinjau dan approval. Penolakan
                // diterjemahkan ke bahasa REQ supaya peninjau tahu baris mana
                // yang harus dipindahkan ke transfer atau pembelian.
                throw RequestRuleException::rule(
                    'BR-REQ-05',
                    'Baris '.$l->displayName().' tidak bisa direservasi: '.$e->getMessage(),
                );
            }

            $l->forceFill(['qty_reserved' => $l->qty_base])->save();
        }
    }

    private function pastikanMenungguApproval(MaterialRequest $request): void
    {
        if ($request->status !== MaterialRequestStatus::PendingApproval) {
            throw RequestRuleException::rule(
                'BR-REQ-05',
                'Hanya REQ yang menunggu persetujuan yang bisa disetujui atau ditolak.',
            );
        }
    }

    /** BR-REQ-07: pemisahan tugas — pemohon tidak menyetujui dokumennya sendiri. */
    private function pastikanBukanPemohonSendiri(MaterialRequest $request, ?User $actor): void
    {
        if ($actor !== null && (int) $request->requester_id === (int) $actor->id) {
            throw RequestRuleException::rule(
                'BR-REQ-07',
                'Pemohon tidak boleh menyetujui permintaannya sendiri.',
            );
        }
    }
}
