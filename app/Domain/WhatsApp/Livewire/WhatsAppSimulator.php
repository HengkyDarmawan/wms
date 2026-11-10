<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\WhatsApp\Models\WaMessageLog;
use App\Domain\WhatsApp\Support\WhatsAppInbound;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

/**
 * **Kotak pesan WhatsApp (simulasi)** — mode demo tanpa akun Meta (A-435,
 * 31-whatsapp): menampilkan pesan yang "terkirim" lewat driver `log` seperti
 * percakapan, dan tombol template (Setujui/Tolak) bisa ditekan seolah-olah
 * dari HP penerima. Hanya aktif bila driver = `log` dan bukan produksi.
 */
class WhatsAppSimulator extends Component
{
    public string $nomor = '';

    public string $hasil = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('company_setting.manage'), 403);
        abort_unless(self::aktif(), 404);
    }

    public static function aktif(): bool
    {
        return config('wms.whatsapp.driver') === 'log' && ! app()->isProduction();
    }

    public function render(): View
    {
        $company = tenant()?->getTenantKey();
        $semua = WaMessageLog::query()->where('company_id', $company)->latest('id')->limit(200)->get();
        $nomor = $semua->pluck('to_number')->filter()->unique()->values();

        return view('livewire.whatsapp.simulator', [
            'nomorList' => $nomor,
            'nama' => User::query()->whereIn('phone', $nomor->all())->pluck('name', 'phone'),
            'pesan' => $semua->when($this->nomor !== '', fn ($c) => $c->where('to_number', $this->nomor))->sortBy('id')->values(),
        ]);
    }

    /** Tiru penerima menekan tombol template (quick reply) dengan payload-nya. */
    public function tekan(int $logId, int $indeks, WhatsAppInbound $inbound): void
    {
        abort_unless(self::aktif(), 404);
        $log = WaMessageLog::query()->where('company_id', tenant()?->getTenantKey())->findOrFail($logId);
        $tombol = collect($log->payload['pesan']['template']['components'] ?? [])
            ->first(fn ($k) => ($k['type'] ?? '') === 'button' && (int) ($k['index'] ?? -1) === $indeks && ($k['sub_type'] ?? '') === 'quick_reply');
        $payload = (string) ($tombol['parameters'][0]['payload'] ?? '');

        if ($payload === '') {
            $this->hasil = __('Tombol ini tidak mengirim balasan.');

            return;
        }

        $r = $inbound->process(['entry' => [['changes' => [['value' => ['messages' => [[
            'id' => 'wamid.sim.'.Str::uuid()->toString(),
            'from' => (string) $log->to_number,
            'type' => 'button',
            'button' => ['payload' => $payload, 'text' => str_ends_with($payload, '|A') ? 'Setujui' : 'Tolak'],
        ]]]]]]]]);

        $this->hasil = $r['decisions'] > 0 ? __('Keputusan tercatat lewat WhatsApp (simulasi).') : __('Tombol diterima, tetapi tidak ada keputusan baru (token kedaluwarsa atau sudah dipakai).');
    }
}
