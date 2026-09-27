<?php

declare(strict_types=1);

namespace App\Domain\Shared\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Driver `http`: gateway WhatsApp/SMS umum lewat satu POST (A-273).
 *
 * Kebanyakan gateway lokal menerima bentuk yang sama — token di header,
 * nomor dan isi pesan di body — hanya nama field-nya yang berbeda, jadi semua
 * itu diatur di `config('wms.messaging.http')`, bukan di kode. Pilihan merek
 * penyedia tetap keputusan pemilik produk ([O-15]).
 */
class HttpGateway implements MessageGateway
{
    /** @param  array<string, mixed>  $config  url, token, token_header, format, phone_field, message_field, extra, timeout */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return trim((string) ($this->config['url'] ?? '')) !== '';
    }

    public function send(string $phone, string $text): void
    {
        if (! $this->available()) {
            throw new MessageNotSent('Alamat gateway WhatsApp/SMS belum diatur.');
        }

        $body = array_merge(
            $this->extra(),
            [
                (string) ($this->config['phone_field'] ?? 'target') => $phone,
                (string) ($this->config['message_field'] ?? 'message') => $text,
            ],
        );

        $permintaan = Http::timeout((int) ($this->config['timeout'] ?? 8))->acceptJson();
        $token = trim((string) ($this->config['token'] ?? ''));

        if ($token !== '') {
            $permintaan = $permintaan->withHeaders([(string) ($this->config['token_header'] ?? 'Authorization') => $token]);
        }

        if (($this->config['format'] ?? 'form') === 'json') {
            $permintaan = $permintaan->asJson();
        } else {
            $permintaan = $permintaan->asForm();
        }

        try {
            $jawaban = $permintaan->post((string) $this->config['url'], $body);
        } catch (ConnectionException $e) {
            throw new MessageNotSent('Gateway WhatsApp/SMS tidak terjangkau.', 0, $e);
        }

        // Sebagian gateway menjawab 200 dengan `status: false` saat menolak.
        if (! $jawaban->successful() || $jawaban->json('status') === false) {
            throw new MessageNotSent('Gateway WhatsApp/SMS menolak pesan (HTTP '.$jawaban->status().').');
        }
    }

    /** @return array<string, mixed> field tambahan tetap, mis. `{"countryCode":"62"}` */
    private function extra(): array
    {
        $extra = $this->config['extra'] ?? [];

        if (is_string($extra)) {
            $extra = json_decode($extra, true);
        }

        return is_array($extra) ? $extra : [];
    }
}
