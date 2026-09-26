<?php

declare(strict_types=1);

namespace App\Http\Controllers\Waste;

use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Waste\Actions\CloseWasteDisposal;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposal;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman Berita Acara Waste (24-konversi-waste §6). Halaman lewat GET;
 * penutupan dengan bukti lewat POST form biasa (unggah foto, A-160) — sengaja
 * bukan unggahan Livewire, lihat ItemPhotoController. Foto bukti dialirkan
 * lewat route berotorisasi, bukan URL publik.
 */
class WasteDisposalController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', WasteDisposal::class);

        return view('waste.index');
    }

    public function create(): View
    {
        $this->authorize('create', WasteDisposal::class);

        return view('waste.create');
    }

    public function show(WasteDisposal $wasteDisposal): View
    {
        $this->authorize('view', $wasteDisposal);

        return view('waste.show', ['wst' => $wasteDisposal]);
    }

    public function close(Request $request, WasteDisposal $wasteDisposal, CloseWasteDisposal $action): RedirectResponse
    {
        $this->authorize('close', $wasteDisposal);

        $request->validate([
            'evidence_photo' => ['nullable', ...StoreUpload::ATURAN_FOTO],
            'evidence_note' => ['nullable', 'string', 'max:255'],
        ], attributes: ['evidence_photo' => __('Foto berita acara'), 'evidence_note' => __('Nomor / keterangan BA')]);

        try {
            $action->handle($wasteDisposal, $request->file('evidence_photo'), $request->input('evidence_note'), $request->user());
        } catch (WasteRuleException|LedgerException $e) {
            return back()->withInput()->withErrors(['evidence' => $e->getMessage()])->with('rule', $e->rule);
        }

        return redirect()->route('waste-disposals.show', $wasteDisposal)->with('pesan', __('BA waste ditutup; stok diperbarui.'));
    }

    public function evidence(WasteDisposal $wasteDisposal, StoreUpload $files): StreamedResponse
    {
        $this->authorize('view', $wasteDisposal);

        abort_unless($files->exists($wasteDisposal->evidence_path), 404);

        return $files->stream((string) $wasteDisposal->evidence_path);
    }
}
