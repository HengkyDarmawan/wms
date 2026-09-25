<?php

declare(strict_types=1);

namespace App\Http\Controllers\Request;

use App\Domain\Request\Actions\RespondDeliveryReceipt;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Models\ProofOfDelivery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tanggapan pemohon atas bukti terima SJ (BR-REQ-10, A-188) — dipakai layar
 * REQ back-office dan portal klien. POST biasa karena keberatan membawa foto.
 */
class DeliveryReceiptController extends Controller
{
    public function confirm(Request $request, MaterialRequest $materialRequest, int $proof, RespondDeliveryReceipt $action): RedirectResponse
    {
        $this->authorize('view', $materialRequest);
        $this->authorize('request.confirm_receipt');

        return $this->jalankan(fn () => $action->confirm($materialRequest, $this->bukti($proof), $request->user()),
            __('Penerimaan dikonfirmasi.'));
    }

    public function dispute(Request $request, MaterialRequest $materialRequest, int $proof, RespondDeliveryReceipt $action): RedirectResponse
    {
        $this->authorize('view', $materialRequest);
        $this->authorize('request.dispute_receipt');

        return $this->jalankan(fn () => $action->dispute(
            $materialRequest,
            $this->bukti($proof),
            (array) $request->input('lines', []),
            (array) ($request->file('photos') ?? []),
            $request->input('notes'),
            $request->user(),
        ), __('Keberatan tercatat; gudang akan menindaklanjuti selisihnya.'));
    }

    private function bukti(int $id): ProofOfDelivery
    {
        return ProofOfDelivery::query()->with('shipment')->findOrFail($id);
    }

    private function jalankan(callable $aksi, string $pesan): RedirectResponse
    {
        try {
            $aksi();
        } catch (RequestRuleException $e) {
            return back()->withErrors(['delivery' => $e->getMessage()])->with('ruleCode', $e->rule);
        }

        return back()->with('status', $pesan);
    }
}
