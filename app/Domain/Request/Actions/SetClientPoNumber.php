<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;

/**
 * A-313/A-318 — No. PO klien di REQ (permission `request.create` atau
 * `request.review`, lihat `MaterialRequestPolicy::setClientPo`).
 *
 * Teks bebas yang tidak divalidasi ke sistem klien: dicetak di SJ supaya
 * admin site klien bisa membuat GR atas PO-nya. WMS tidak menunggunya.
 */
class SetClientPoNumber
{
    public function handle(MaterialRequest $request, ?string $number, ?User $actor = null): MaterialRequest
    {
        if ($request->status->isFinal()) {
            throw RequestRuleException::rule('BR-REQ-06', 'No. PO klien tidak bisa diubah pada permintaan yang sudah selesai atau batal.');
        }

        $baru = trim((string) $number);
        $baru = $baru === '' ? null : $baru;

        if ($baru !== null && mb_strlen($baru) > 60) {
            throw RequestRuleException::field('BR-GEN-11', 'client_po_number', 'No. PO klien paling panjang 60 karakter.');
        }

        $lama = $request->client_po_number;

        if ($lama === $baru) {
            return $request;
        }

        $request->forceFill(['client_po_number' => $baru])->save();

        activity('request')->performedOn($request)->causedBy($actor)
            ->withProperties(['sebelum' => $lama, 'sesudah' => $baru])
            ->log('No. PO klien diubah');

        return $request->refresh();
    }
}
