<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\Item;
use App\Domain\Shared\Files\StoreUpload;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Foto item (11-master §3.4, kolom `photo_path`).
 *
 * Sengaja memakai form HTML biasa, bukan unggahan Livewire: endpoint unggah
 * Livewire didaftarkan paketnya sendiri tanpa middleware tenant, sehingga
 * berkas bisa mendarat di konteks yang salah.
 */
class ItemPhotoController extends Controller
{
    public function __construct(private readonly StoreUpload $files) {}

    public function store(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], attributes: ['photo' => __('Foto item')]);

        try {
            $path = $this->files->handle($request->file('photo'), 'items', (string) $item->id);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['photo' => $e->getMessage()]);
        }

        $item->forceFill(['photo_path' => $path])->save();

        activity('master')
            ->performedOn($item)
            ->causedBy($request->user())
            ->log('Foto item diunggah');

        return back()->with('pesan', __('Foto item disimpan.'));
    }

    public function destroy(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $this->files->delete($item->photo_path);
        $item->forceFill(['photo_path' => null])->save();

        activity('master')
            ->performedOn($item)
            ->causedBy($request->user())
            ->log('Foto item dihapus');

        return back()->with('pesan', __('Foto item dihapus.'));
    }
}
