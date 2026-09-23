<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Enums\SubstitutionResponse;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.respond_substitution` — klien menanggapi penggantian
 * item (A-55, BR-REQ-13).
 *
 * Diam sampai tenggat dianggap setuju. Itu keputusan sadar: REQ tidak boleh
 * tertahan menunggu klien yang tidak membuka portal, sementara penolakan yang
 * sungguh-sungguh selalu disertai alasan dan langsung membatalkan barisnya.
 */
class RespondSubstitution
{
    public function __construct(private readonly ManageReservation $reservasi) {}

    public function accept(MaterialRequestLine $line, ?User $actor = null): MaterialRequestLine
    {
        $this->pastikanMasihBisaDitanggapi($line);

        $line->forceFill(['substitution_response' => SubstitutionResponse::Accepted])->save();

        activity('request')
            ->performedOn($line->request)
            ->causedBy($actor)
            ->withProperties(['baris' => $line->id])
            ->log('Klien menyetujui penggantian item');

        return $line->refresh();
    }

    /** Penolakan membatalkan barisnya dan melepas reservasinya (BR-STK-05). */
    public function reject(MaterialRequestLine $line, ?int $reasonCodeId, ?User $actor = null): MaterialRequestLine
    {
        $this->pastikanMasihBisaDitanggapi($line);

        if ($reasonCodeId === null) {
            throw RequestRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan penolakan wajib dipilih.');
        }

        return DB::transaction(function () use ($line, $reasonCodeId, $actor) {
            $line->forceFill([
                'substitution_response' => SubstitutionResponse::Rejected,
                'status' => RequestLineStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'cancel_confirmed_by' => $actor?->id,
                'cancel_confirmed_at' => now(),
            ])->save();

            $this->lepasReservasiBaris($line, 'SUBSTITUTION_REJECTED', $actor);

            activity('request')
                ->performedOn($line->request)
                ->causedBy($actor)
                ->withProperties(['baris' => $line->id, 'reason_code_id' => $reasonCodeId])
                ->log('Klien menolak penggantian item; baris dibatalkan');

            return $line->refresh();
        });
    }

    /**
     * Penuaan tenggat: penggantian yang lewat batas dianggap disetujui.
     *
     * Dijalankan job harian. Dibuat sebagai metode biasa, bukan hanya di dalam
     * job, supaya bisa diuji tanpa menjalankan penjadwal.
     *
     * @return int  jumlah baris yang ditandai kedaluwarsa
     */
    public function expireOverdue(): int
    {
        $baris = MaterialRequestLine::query()->substitutionExpired()->get();

        foreach ($baris as $l) {
            $l->forceFill(['substitution_response' => SubstitutionResponse::Expired])->save();

            activity('request')
                ->performedOn($l->request)
                ->withProperties(['baris' => $l->id])
                ->log('Tenggat keberatan penggantian lewat; dianggap disetujui');
        }

        return $baris->count();
    }

    private function pastikanMasihBisaDitanggapi(MaterialRequestLine $line): void
    {
        if (! $line->isSubstituted()) {
            throw RequestRuleException::rule('BR-REQ-13', 'Baris ini tidak sedang diganti item lain.');
        }

        if ($line->substitution_response !== null) {
            throw RequestRuleException::rule(
                'BR-REQ-13',
                'Penggantian ini sudah ditanggapi ('.$line->substitution_response->label().').',
            );
        }

        if ($line->substitution_deadline_at?->isPast() === true) {
            throw RequestRuleException::rule(
                'BR-REQ-13',
                'Tenggat keberatan sudah lewat; penggantian dianggap disetujui.',
            );
        }
    }

    /** Reservasi baris dilepas satu per satu, bukan sedokumen. */
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
