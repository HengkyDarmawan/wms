<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Shared\Files\StoreUpload;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan berkas tenant lewat route berotorisasi, bukan URL publik.
 *
 * Tanda tangan dan foto item adalah data company; menaruhnya di `public/`
 * membuat siapa pun yang menebak nama berkas bisa membukanya. Semua berkas
 * disimpan di disk privat dan dialirkan dari sini setelah izin diperiksa.
 */
class FileController extends Controller
{
    public function __construct(private readonly StoreUpload $files) {}

    public function signature(Request $request, User $user): StreamedResponse
    {
        // Tanda tangan sendiri selalu boleh; milik orang lain menuntut `user.view`.
        abort_unless(
            $request->user()->is($user) || $request->user()->hasPermission('user.view'),
            403,
        );

        abort_unless($this->files->exists($user->signature_path), 404);

        return $this->files->stream((string) $user->signature_path);
    }

    public function itemPhoto(Request $request, Item $item): StreamedResponse
    {
        $this->authorize('view', $item);

        abort_unless($this->files->exists($item->photo_path), 404);

        return $this->files->stream((string) $item->photo_path);
    }
}
