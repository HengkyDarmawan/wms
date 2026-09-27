<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Livewire;

use App\Domain\Notification\Support\NotificationEvents;
use App\Domain\WhatsApp\Actions\SaveWhatsAppSettings;
use App\Domain\WhatsApp\Support\WhatsAppChannel;
use App\Domain\WhatsApp\Support\WhatsAppSettings;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Kartu *WhatsApp* di Pengaturan company (31-whatsapp §6): kejadian ⇒
 * Mati / Langsung / Ringkasan harian, balasan konfirmasi, pemakaian bulan
 * ini vs kuota paket. Tampil hanya bila fitur `whatsapp` company menyala.
 */
class WhatsAppCompanySettings extends Component
{
    /** @var array<string, string> */
    public array $events = [];

    public bool $confirmReply = true;

    public function mount(): void
    {
        $this->authorize('company_setting.view');
        $aktif = WhatsAppSettings::events();

        foreach (array_keys($this->daftar()) as $k) {
            $this->events[$this->kunci($k)] = $aktif[$k] ?? 'off';
        }

        $this->confirmReply = WhatsAppSettings::confirmReply();
    }

    public function simpan(SaveWhatsAppSettings $action): void
    {
        $this->authorize('company_setting.manage');

        $isian = [];

        foreach (array_keys($this->daftar()) as $k) {
            $isian[$k] = (string) ($this->events[$this->kunci($k)] ?? 'off');
        }

        $action->handle($isian, $this->confirmReply, auth()->user());
        $this->dispatch('pesan', teks: __('Pengaturan WhatsApp disimpan.'));
    }

    public function render(WhatsAppChannel $channel): View
    {
        return view('livewire.whatsapp.company-settings', [
            'aktif' => $channel->enabled(),
            'platform' => $channel->available(),
            'daftar' => $this->daftar(),
            'kuota' => $channel->quota(),
            'bolehUbah' => auth()->user()?->hasPermission('company_setting.manage') ?? false,
        ]);
    }

    /** @return array<string, array{label: string}> */
    private function daftar(): array
    {
        return array_filter(NotificationEvents::ALL, fn ($e, $k) => ! in_array($k, WhatsAppSettings::TIDAK_BOLEH, true), ARRAY_FILTER_USE_BOTH);
    }

    /** Kunci array Livewire tidak boleh memuat titik (pola A-229). */
    private function kunci(string $event): string
    {
        return str_replace('.', '__', $event);
    }
}
