<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Livewire;

use App\Domain\Shared\Messaging\PhoneNumber;
use App\Domain\WhatsApp\Actions\VerifyWhatsAppNumber;
use App\Domain\WhatsApp\Support\WhatsAppChannel;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Kartu *WhatsApp* di profil (31-whatsapp §6, A-275): nomor, kirim kode,
 * isi kode, hapus nomor. Hanya untuk diri sendiri (`profile.update`).
 */
class WhatsAppNumber extends Component
{
    public string $phone = '';

    public string $code = '';

    public bool $menungguKode = false;

    public function mount(): void
    {
        $this->phone = (string) (auth()->user()?->phone ?? '');
        $this->menungguKode = auth()->user()?->wa_code_hash !== null && auth()->user()?->wa_code_expires_at?->isFuture() === true;
    }

    public function kirimKode(VerifyWhatsAppNumber $action): void
    {
        $this->resetErrorBag();
        $action->sendCode(auth()->user(), $this->phone);
        $this->phone = (string) auth()->user()->refresh()->phone;
        $this->menungguKode = true;
        $this->code = '';
        $this->dispatch('pesan', teks: __('Kode verifikasi dikirim lewat WhatsApp.'));
    }

    public function verifikasi(VerifyWhatsAppNumber $action): void
    {
        $this->resetErrorBag();
        $action->confirm(auth()->user(), $this->code);
        $this->menungguKode = false;
        $this->code = '';
        $this->dispatch('pesan', teks: __('Nomor WhatsApp terverifikasi.'));
    }

    public function hapus(VerifyWhatsAppNumber $action): void
    {
        $action->remove(auth()->user());
        $this->phone = '';
        $this->menungguKode = false;
        $this->dispatch('pesan', teks: __('Nomor WhatsApp dihapus.'));
    }

    public function render(WhatsAppChannel $channel): View
    {
        $user = auth()->user();

        return view('livewire.whatsapp.number', [
            'aktif' => $channel->enabled(),
            'terverifikasi' => $user?->phone_verified_at !== null && $user?->phone !== null,
            'tersamar' => $user?->phone ? PhoneNumber::mask((string) (PhoneNumber::normalize($user->phone) ?? $user->phone)) : null,
        ]);
    }
}
