<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Transport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Driver `cloud`: WhatsApp Cloud API resmi (riset §2.6, A-274) —
 * `POST https://graph.facebook.com/{versi}/{phone_number_id}/messages`
 * dengan token sistem sebagai Bearer.
 */
class CloudTransport implements WhatsAppTransport
{
    /** @param  array<string, mixed>  $config  config('wms.whatsapp') */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return trim((string) ($this->config['token'] ?? '')) !== ''
            && trim((string) ($this->config['phone_number_id'] ?? '')) !== '';
    }

    public function send(array $message): string
    {
        if (! $this->available()) {
            throw new WhatsAppNotSent('Token atau phone number id WhatsApp belum diatur.');
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $this->config['graph_version'] ?? 'v23.0', $this->config['phone_number_id']);

        try {
            $jawaban = Http::withToken((string) $this->config['token'])
                ->timeout((int) ($this->config['timeout'] ?? 10))
                ->acceptJson()->asJson()
                ->post($url, ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual'] + $message);
        } catch (ConnectionException $e) {
            throw new WhatsAppNotSent('WhatsApp Cloud API tidak terjangkau.', 0, $e);
        }

        $id = $jawaban->json('messages.0.id');

        if (! $jawaban->successful() || ! is_string($id) || $id === '') {
            $galat = $jawaban->json('error.message') ?? ('HTTP '.$jawaban->status());

            throw new WhatsAppNotSent('WhatsApp menolak pesan: '.$galat);
        }

        return $id;
    }
}
