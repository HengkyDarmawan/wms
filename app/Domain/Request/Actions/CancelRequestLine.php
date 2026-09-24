<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Transfer\Support\BackorderTransfers;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.request_cancel` dan `request.confirm_cancel` — pembatalan
 * baris setelah REQ disetujui (A-61, BR-REQ-15).
 *
 * Dua langkah, bukan satu. Setelah disetujui, gudang sudah mulai bekerja:
 * reservasi dibuat, mungkin picking sudah berjalan. Karena itu klien hanya
 * **meminta**, dan staf yang memastikan barisnya memang belum terkirim sebelum
 * benar-benar membatalkannya.
 */
class CancelRequestLine
{
    public function __construct(private readonly ManageReservation $reservasi) {}

    /** Langkah 1 — klien mengajukan pembatalan satu baris. */
    public function request(MaterialRequestLine $line, ?int $reasonCodeId, ?User $actor = null): MaterialRequestLine
    {
        if ($line->status !== RequestLineStatus::Open) {
            throw RequestRuleException::rule('BR-REQ-15', 'Baris ini sudah tidak terbuka.');
        }

        if ($reasonCodeId === null) {
            throw RequestRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        // BR-REQ-15: baris yang barangnya sudah berjalan tidak bisa ditarik lagi.
        if ((float) $line->qty_shipped > 0) {
            throw RequestRuleException::rule(
                'BR-REQ-15',
                'Baris ini sudah dikirim sebagian dan tidak bisa dibatalkan.',
            );
        }

        if ($line->awaitsCancelConfirmation()) {
            throw RequestRuleException::rule('BR-REQ-15', 'Permintaan pembatalan untuk baris ini sudah diajukan.');
        }

        $line->forceFill([
            'cancel_requested_at' => now(),
            'cancel_reason_id' => $reasonCodeId,
        ])->save();

        activity('request')
            ->performedOn($line->request)
            ->causedBy($actor)
            ->withProperties(['baris' => $line->id, 'reason_code_id' => $reasonCodeId])
            ->log('Klien meminta pembatalan baris REQ');

        return $line->refresh();
    }

    /** Langkah 2a — staf menyetujui: baris dibatalkan dan reservasinya dilepas. */
    public function confirm(MaterialRequestLine $line, ?User $actor = null): MaterialRequestLine
    {
        $this->pastikanAdaPermintaan($line);

        if ((float) $line->qty_shipped > 0) {
            throw RequestRuleException::rule(
                'BR-REQ-15',
                'Baris sudah terkirim sebagian sejak permintaan diajukan; pembatalan tidak bisa dilanjutkan.',
            );
        }

        return DB::transaction(function () use ($line, $actor) {
            $line->forceFill([
                'status' => RequestLineStatus::Cancelled,
                'cancel_confirmed_by' => $actor?->id,
                'cancel_confirmed_at' => now(),
            ])->save();

            $this->lepasReservasiBaris($line, 'CANCELLED_BY_CLIENT', $actor);

            // BR-REQ-15: TRF backorder baris ini ikut dilepas bila belum berjalan (A-108).
            app(BackorderTransfers::class)->releaseForRequestLines([(int) $line->id], $line->cancel_reason_id !== null ? (int) $line->cancel_reason_id : null, $actor);

            activity('request')
                ->performedOn($line->request)
                ->causedBy($actor)
                ->withProperties(['baris' => $line->id])
                ->log('Pembatalan baris REQ dikonfirmasi');

            return $line->refresh();
        });
    }

    /**
     * Langkah 2b — staf menolak: baris tetap berjalan.
     *
     * Penanda permintaan dilepas supaya klien bisa mengajukan lagi bila
     * keadaannya berubah, dan penolakannya tercatat di timeline.
     */
    public function refuse(MaterialRequestLine $line, ?string $notes = null, ?User $actor = null): MaterialRequestLine
    {
        $this->pastikanAdaPermintaan($line);

        $line->forceFill([
            'cancel_requested_at' => null,
            'cancel_reason_id' => null,
        ])->save();

        activity('request')
            ->performedOn($line->request)
            ->causedBy($actor)
            ->withProperties(['baris' => $line->id, 'notes' => $notes])
            ->log('Permintaan pembatalan baris REQ ditolak');

        return $line->refresh();
    }

    private function pastikanAdaPermintaan(MaterialRequestLine $line): void
    {
        if (! $line->awaitsCancelConfirmation()) {
            throw RequestRuleException::rule(
                'BR-REQ-15',
                'Tidak ada permintaan pembatalan yang menunggu konfirmasi untuk baris ini.',
            );
        }
    }

    private function lepasReservasiBaris(MaterialRequestLine $line, string $reason, ?User $actor): void
    {
        $reservasi = StockReservation::query()
            ->active()
            ->forDocument('material_request', (int) $line->material_request_id)
            ->where('document_line_id', $line->id)
            ->get();

        foreach ($reservasi as $r) {
            $this->reservasi->release($r, $reason, $actor);
        }
    }
}
