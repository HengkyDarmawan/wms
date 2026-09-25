<?php

declare(strict_types=1);

namespace App\Http\Controllers\Asset;

use App\Domain\Asset\Actions\InspectAsset;
use App\Domain\Asset\Exceptions\AssetRuleException;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Asset\Models\AssetInspection;
use App\Domain\Asset\Support\AssetQuery;
use App\Domain\Master\Models\Serial;
use App\Domain\Shared\Files\StoreUpload;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman aset dipinjamkan (25-aset §6). Halaman lewat GET; pemeriksaan aset
 * lewat POST form biasa karena memuat foto (seperti ItemPhotoController —
 * unggahan Livewire berjalan di luar middleware tenant). Foto dialirkan lewat
 * route berizin, bukan URL publik.
 */
class AssetController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Serial::class);

        return view('asset.index');
    }

    public function show(Serial $serial): View
    {
        // BR-ACC-05: di luar cakupan (atau bukan aset) = 404, seperti dokumen lain.
        abort_unless($serial->item?->isAsset() && app(AssetQuery::class)->for(auth()->user())->whereKey($serial->id)->exists(), 404);
        $this->authorize('view', $serial);

        return view('asset.show', ['aset' => $serial]);
    }

    public function handovers(): View
    {
        $this->authorize('viewAny', AssetHandover::class);

        return view('asset.handovers');
    }

    public function handover(AssetHandover $assetHandover): View
    {
        $this->authorize('view', $assetHandover);

        return view('asset.handover', ['ast' => $assetHandover]);
    }

    public function inspect(Request $request, AssetHandover $assetHandover, InspectAsset $action): RedirectResponse
    {
        $this->authorize('inspect', $assetHandover);

        $request->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], attributes: ['photo' => __('Foto')]);

        try {
            $action->handle($assetHandover, $request->only(['condition_grade', 'condition_score', 'component_notes', 'meter_in', 'meter_reset_reason', 'notes']), $request->file('photo'), $request->user());
        } catch (AssetRuleException $e) {
            return back()->withInput()->withErrors($e->fieldErrors ?: ['inspection' => $e->getMessage()])->with('rule', $e->rule);
        }

        return redirect()->route('asset-handovers.show', $assetHandover)->with('pesan', __('Pemeriksaan aset disimpan.'));
    }

    public function photo(AssetInspection $assetInspection, StoreUpload $files): StreamedResponse
    {
        $ast = AssetHandover::query()->find($assetInspection->asset_handover_id);
        abort_if($ast === null, 404);
        $this->authorize('view', $ast);

        abort_unless($files->exists($assetInspection->photo_path), 404);

        return $files->stream((string) $assetInspection->photo_path);
    }
}
