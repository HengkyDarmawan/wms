<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\ChangePassword;
use App\Domain\Access\Actions\ManageTwoFactor;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Shared\Files\StoreUpload;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Profil user: data diri, tanda tangan, keamanan, perangkat (10-access §6.2).
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ChangePassword $changePassword,
        private readonly ManageTwoFactor $twoFactor,
        private readonly StoreUpload $files,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('access.profile', [
            'user' => $user,
            'devices' => $user->devices()->orderByDesc('last_seen_at')->get(),
            'assignments' => $user->validAssignments(),
            'sisaKodePemulihan' => $this->twoFactor->remainingRecoveryCodes($user),
            'adaTandaTangan' => $this->files->exists($user->signature_path),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Nomor WhatsApp diatur kartu *WhatsApp* (verifikasi kode, A-275), bukan di sini.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [], [
            'name' => 'Nama',
        ]);

        $request->user()->fill($data)->save();

        return back()->with('status', __('Profil diperbarui.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:'.config('access.password.min_length', 10), 'confirmed'],
        ], [], [
            'current_password' => 'Password lama',
            'password' => 'Password baru',
        ]);

        try {
            $this->changePassword->handle(
                $request->user(),
                $data['password'],
                requireCurrent: true,
                currentPassword: $data['current_password'],
            );
        } catch (AccessRuleException $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        return back()->with('status', __('Password berhasil diubah.'));
    }

    /**
     * Unggah tanda tangan (10-access §6.2). Disimpan di disk privat per company;
     * legalitasnya masih menunggu [O-13], jadi ini baru gambar untuk dokumen cetak.
     */
    public function updateSignature(Request $request): RedirectResponse
    {
        $user = $request->user();

        // A-264: tanda tangan boleh digambar di kanvas profil (data URL PNG).
        if ($request->filled('signature_data') && ! $request->hasFile('signature')) {
            $request->validate(['signature_data' => ['required', 'string', 'max:2000000']], attributes: ['signature_data' => __('Tanda tangan')]);

            try {
                $path = $this->files->handleDataUrl((string) $request->input('signature_data'), 'signatures', $user->id.'-'.now()->format('YmdHis'));
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['signature_data' => $e->getMessage()]);
            }

            $this->files->delete($user->signature_path);
            $user->forceFill(['signature_path' => $path])->save();

            return back()->with('status', __('Tanda tangan disimpan.'));
        }

        $request->validate([
            'signature' => ['required', ...StoreUpload::ATURAN_FOTO],
        ], attributes: ['signature' => __('Tanda tangan')]);

        try {
            // A-257: tanda tangan tetap PNG (transparansi), hanya dikecilkan bila perlu.
            $path = $this->files->handle($request->file('signature'), 'signatures', (string) $user->id, keepFormat: true);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['signature' => $e->getMessage()]);
        }

        $user->forceFill(['signature_path' => $path])->save();

        return back()->with('status', __('Tanda tangan disimpan.'));
    }

    public function deleteSignature(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->files->delete($user->signature_path);
        $user->forceFill(['signature_path' => null])->save();

        return back()->with('status', __('Tanda tangan dihapus.'));
    }
}
