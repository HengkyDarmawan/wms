<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Support;

use App\Domain\Platform\Models\Company;
use App\Domain\WhatsApp\Actions\DecideViaWhatsApp;
use App\Domain\WhatsApp\Models\WaMessageLog;
use App\Domain\WhatsApp\Transport\WhatsAppNotSent;
use Illuminate\Support\Facades\Log;

/**
 * Pemroses webhook WhatsApp di domain pusat (Arsitektur §8, 31-whatsapp §4).
 *
 * - `statuses[]` memperbarui status pesan keluar di `wa_message_logs`.
 * - `messages[]` bertombol (`button.payload` template atau
 *   `interactive.button_reply.id`) berawalan `APR|` diarahkan ke company dari
 *   payload, tenancy diinisialisasi, lalu {@see DecideViaWhatsApp}. Pesan yang
 *   sama diproses sekali (BR-WA-04). Pesan lain diabaikan — bukan chatbot.
 */
class WhatsAppInbound
{
    /** Urutan status agar status lama yang datang terlambat tidak menimpa. */
    private const URUT = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

    public function __construct(private readonly WhatsAppChannel $channel) {}

    /**
     * @param  array<string, mixed>  $payload  body webhook Meta
     * @return array{statuses: int, decisions: int, ignored: int}
     */
    public function process(array $payload): array
    {
        $hasil = ['statuses' => 0, 'decisions' => 0, 'ignored' => 0];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $hasil['statuses'] += $this->status((array) $status);
                }

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    $this->pesan((array) $message) ? $hasil['decisions']++ : $hasil['ignored']++;
                }
            }
        }

        return $hasil;
    }

    /** @param  array<string, mixed>  $status */
    private function status(array $status): int
    {
        $baru = (string) ($status['status'] ?? '');
        $log = WaMessageLog::query()->where('wa_message_id', (string) ($status['id'] ?? ''))->first();

        if ($log === null || ! isset(self::URUT[$baru]) || (self::URUT[$log->status] ?? 0) >= self::URUT[$baru]) {
            return 0;
        }

        $log->forceFill(['status' => $baru])->save();

        return 1;
    }

    /** @param  array<string, mixed>  $message */
    private function pesan(array $message): bool
    {
        $isi = match ($message['type'] ?? null) {
            'button' => (string) ($message['button']['payload'] ?? ''),
            'interactive' => (string) ($message['interactive']['button_reply']['id'] ?? ''),
            default => '',
        };

        $bagian = ApprovalWhatsApp::parse($isi);
        $waId = (string) ($message['id'] ?? '');
        $dari = (string) ($message['from'] ?? '');

        if ($bagian === null || $waId === '' || $dari === '') {
            return false;
        }

        [, $companyId, $token, $aksi] = $bagian;
        $company = Company::query()->find($companyId);

        if ($company === null) {
            return false;
        }

        $kanal = $this->channel->forCompany($company);

        if (! $kanal->logInbound($waId, $dari, ['payload' => $isi])) {
            return false; // pesan ganda (BR-WA-04)
        }

        // Kembalikan konteks sebelumnya (perintah simulasi/uji bisa sudah bertenant).
        $sebelum = tenancy()->initialized ? tenant() : null;
        tenancy()->initialize($company);

        try {
            $hasil = app(DecideViaWhatsApp::class)->handle($token, $aksi, $dari, $waId,
                $company->url(route('approval.inbox', absolute: false)));

            if ($hasil['decided'] === false || WhatsAppSettings::confirmReply()) {
                try {
                    $kanal->text($dari, $hasil['reply'], ['balasan' => $aksi, 'wa_message_id' => $waId]);
                } catch (WhatsAppNotSent $e) {
                    Log::warning('Balasan WhatsApp gagal: '.$e->getMessage());
                }
            }

            return $hasil['decided'];
        } finally {
            $sebelum !== null ? tenancy()->initialize($sebelum) : tenancy()->end();
        }
    }
}
