<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\ManageTwoFactor;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Pengaturan verifikasi dua langkah di profil (10-access §6.2).
 *
 * Rahasia yang belum dikonfirmasi disimpan di sesi supaya kode QR tetap sama
 * saat halaman dimuat ulang, dan supaya user tidak terkunci bila menutup
 * halaman di tengah jalan.
 */
class TwoFactorSetupController extends Controller
{
    public function __construct(private readonly ManageTwoFactor $twoFactor) {}

    public function begin(Request $request): RedirectResponse
    {
        try {
            $hasil = $this->twoFactor->begin($request->user());
        } catch (AccessRuleException $e) {
            return back()->withErrors(['two_factor' => $e->getMessage()]);
        }

        $request->session()->put('access.2fa.setup_secret', $hasil['secret']);
        $request->session()->put('access.2fa.setup_qr', $this->qrSvg($hasil['uri']));

        return back();
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['code' => ['required', 'string', 'max:10']],
            attributes: ['code' => __('Kode verifikasi')],
        );

        try {
            $kode = $this->twoFactor->confirm($request->user(), $data['code']);
        } catch (AccessRuleException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $request->session()->forget(['access.2fa.setup_secret', 'access.2fa.setup_qr']);

        // Kode pemulihan hanya ditampilkan sekali ini.
        return back()
            ->with('status', __('Verifikasi dua langkah aktif.'))
            ->with('recovery_codes', $kode);
    }

    public function cancel(Request $request): RedirectResponse
    {
        try {
            $this->twoFactor->cancel($request->user());
        } catch (AccessRuleException $e) {
            return back()->withErrors(['two_factor' => $e->getMessage()]);
        }

        $request->session()->forget(['access.2fa.setup_secret', 'access.2fa.setup_qr']);

        return back()->with('status', __('Pengaturan dua langkah dibatalkan.'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['current_password' => ['required', 'string']],
            attributes: ['current_password' => __('Password')],
        );

        try {
            $this->twoFactor->disable($request->user(), $data['current_password']);
        } catch (AccessRuleException $e) {
            throw ValidationException::withMessages(['current_password' => $e->getMessage()]);
        }

        return back()->with('status', __('Verifikasi dua langkah dimatikan.'));
    }

    public function regenerate(Request $request): RedirectResponse
    {
        try {
            $kode = $this->twoFactor->regenerateRecoveryCodes($request->user());
        } catch (AccessRuleException $e) {
            return back()->withErrors(['two_factor' => $e->getMessage()]);
        }

        return back()
            ->with('status', __('Kode pemulihan diganti. Kode lama tidak berlaku lagi.'))
            ->with('recovery_codes', $kode);
    }

    /**
     * QR dirender sebagai SVG inline, bukan berkas gambar, supaya rahasianya
     * tidak pernah tersimpan di disk atau melewati URL (AD-08).
     */
    private function qrSvg(string $uri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }
}
