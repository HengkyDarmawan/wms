<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Support;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Support\Notifier;
use App\Domain\Platform\Models\Company;
use App\Domain\WhatsApp\Models\WaMessageLog;
use App\Domain\WhatsApp\Transport\WhatsAppNotSent;
use App\Domain\WhatsApp\Transport\WhatsAppTransport;
use Illuminate\Support\Str;

/**
 * Kanal WhatsApp untuk satu company (31-whatsapp §4, A-274, A-278).
 *
 * Membangun pesan Cloud API (template bertombol, kode autentikasi, teks
 * layanan), memeriksa lapis platform + fitur company (BR-WA-02) dan kuota
 * bulanan (BR-WA-03), mengirim lewat transport, lalu mencatat setiap pesan di
 * `wa_message_logs` pusat. Dipanggil dalam konteks tenant; `forCompany()`
 * dipakai webhook di domain pusat.
 */
class WhatsAppChannel
{
    public const EVENT_QUOTA = 'whatsapp.quota_exhausted';

    private ?Company $company = null;

    public function __construct(private readonly WhatsAppTransport $transport) {}

    /** Kanal untuk company tertentu (webhook pusat, sebelum/tanpa tenancy). */
    public function forCompany(Company $company): self
    {
        $kanal = clone $this;
        $kanal->company = $company;

        return $kanal;
    }

    /** Platform punya transport yang bisa mengirim. */
    public function available(): bool
    {
        return $this->transport->available();
    }

    /** Lapis 1: platform siap **dan** Super Admin menyalakan fitur `whatsapp` untuk company ini. */
    public function enabled(): bool
    {
        $company = $this->company();

        return $company !== null && $this->available() && $company->isFeatureEnabled('whatsapp');
    }

    /** @return array{used: int, limit: ?int} pesan template bulan ini (A-278) */
    public function quota(): array
    {
        $company = $this->company();

        if ($company === null) {
            return ['used' => 0, 'limit' => null];
        }

        $batas = $company->plan?->wa_quota;

        return [
            'used' => WaMessageLog::query()->where('company_id', $company->getTenantKey())
                ->where('direction', 'out')->whereIn('category', WaMessageLog::KUOTA)->thisMonth()->count(),
            'limit' => $batas === null ? null : (int) $batas,
        ];
    }

    /**
     * Template utility (notifikasi, approval, ringkasan).
     *
     * @param  list<string>  $body  parameter `{{1}}`… isi pesan
     * @param  list<array{type: 'url'|'quick_reply', value: string}>  $buttons  urut indeks tombol template
     * @param  array<string, mixed>  $meta  dicatat di log (kejadian, dokumen, user)
     */
    public function template(string $to, string $templateKey, array $body, array $buttons = [], array $meta = []): string
    {
        $komponen = [['type' => 'body', 'parameters' => array_map(fn ($t) => ['type' => 'text', 'text' => $this->teks($t)], $body)]];

        foreach ($buttons as $i => $b) {
            $komponen[] = $b['type'] === 'url'
                ? ['type' => 'button', 'sub_type' => 'url', 'index' => (string) $i, 'parameters' => [['type' => 'text', 'text' => $b['value']]]]
                : ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => (string) $i, 'parameters' => [['type' => 'payload', 'payload' => $b['value']]]];
        }

        return $this->kirim($to, WaMessageLog::CATEGORY_UTILITY, $this->namaTemplate($templateKey), [
            'type' => 'template',
            'template' => ['name' => $this->namaTemplate($templateKey), 'language' => ['code' => $this->bahasa()], 'components' => $komponen],
        ], $meta);
    }

    /** Template autentikasi `wms_kode`: kode + tombol salin (verifikasi nomor, OTP bukti terima). */
    public function authCode(string $to, string $code, array $meta = []): string
    {
        $nama = $this->namaTemplate('code');

        return $this->kirim($to, WaMessageLog::CATEGORY_AUTH, $nama, [
            'type' => 'template',
            'template' => ['name' => $nama, 'language' => ['code' => $this->bahasa()], 'components' => [
                ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
            ]],
        ], $meta);
    }

    /** Pesan layanan singkat di jendela 24 jam (balasan tombol, riset §2.6 no. 2). Tidak mengurangi kuota. */
    public function text(string $to, string $text, array $meta = []): string
    {
        return $this->kirim($to, WaMessageLog::CATEGORY_SERVICE, null, [
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => mb_substr($text, 0, 1000)],
        ], $meta);
    }

    /** Catat pesan masuk (tombol) sekali per `wa_message_id` (BR-WA-04). */
    public function logInbound(string $waMessageId, string $from, array $payload): bool
    {
        $company = $this->company();

        if ($company === null || WaMessageLog::query()->where('wa_message_id', $waMessageId)->exists()) {
            return false;
        }

        WaMessageLog::create([
            'company_id' => $company->getTenantKey(), 'direction' => 'in', 'category' => WaMessageLog::CATEGORY_INBOUND,
            'wa_message_id' => $waMessageId, 'to_number' => $from, 'payload' => $payload, 'status' => 'received',
        ]);

        return true;
    }

    /** URL tombol lewat pengalih pusat `/buka/{kode}/{path}` (A-276): satu basis URL untuk semua company. */
    public function linkSuffix(?string $path): string
    {
        $kode = mb_strtolower((string) ($this->company()?->code ?? ''));
        $path = ltrim((string) $path, '/');

        return $path === '' ? $kode : $kode.'/'.$path;
    }

    public function companyName(): string
    {
        return (string) ($this->company()?->name ?? config('app.name'));
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $meta
     */
    private function kirim(string $to, string $category, ?string $template, array $message, array $meta): string
    {
        $company = $this->company();

        if (! $this->enabled() || $company === null) {
            throw new WhatsAppNotSent('WhatsApp tidak aktif untuk company ini.');
        }

        if (in_array($category, WaMessageLog::KUOTA, true)) {
            $kuota = $this->quota();

            if ($kuota['limit'] !== null && $kuota['used'] >= $kuota['limit']) {
                $this->beriTahuKuotaHabis();

                throw WhatsAppNotSent::quota();
            }
        }

        $log = ['company_id' => $company->getTenantKey(), 'direction' => 'out', 'category' => $category, 'to_number' => $to, 'template' => $template];

        try {
            $id = $this->transport->send(['to' => $to] + $message);
        } catch (WhatsAppNotSent $e) {
            WaMessageLog::create($log + ['wa_message_id' => 'failed.'.Str::uuid()->toString(), 'status' => 'failed', 'payload' => $meta + ['galat' => $e->getMessage()]]);

            throw $e;
        }

        WaMessageLog::create($log + ['wa_message_id' => $id, 'status' => 'sent', 'payload' => $meta ?: null]);

        return $id;
    }

    /** BR-WA-03: Admin Company diberi tahu sekali per bulan (in-app/email, bukan WhatsApp). */
    private function beriTahuKuotaHabis(): void
    {
        if (tenant() === null) {
            return;
        }

        $sudah = Notification::query()->where('type', self::EVENT_QUOTA)
            ->where('created_at', '>=', now('Asia/Jakarta')->startOfMonth()->utc())->exists();

        if ($sudah) {
            return;
        }

        $notifier = app(Notifier::class);
        $notifier->send($notifier->recipients('company_setting.manage'), self::EVENT_QUOTA,
            'Kuota WhatsApp bulan ini habis',
            'Notifikasi & approval WhatsApp berhenti sampai bulan depan; lonceng dan email tetap berjalan. Hubungi pengelola platform untuk menambah kuota.',
            route('settings.company', absolute: false));
    }

    private function company(): ?Company
    {
        if ($this->company !== null) {
            return $this->company;
        }

        $t = tenant();

        return $t instanceof Company ? $t : null;
    }

    private function namaTemplate(string $key): string
    {
        return (string) config('wms.whatsapp.templates.'.$key, $key);
    }

    private function bahasa(): string
    {
        return (string) config('wms.whatsapp.language', 'id');
    }

    /** Parameter template tidak boleh memuat baris baru, tab, atau > 4 spasi berturut (aturan Meta). */
    private function teks(string $t): string
    {
        $t = preg_replace('/\s*[\r\n\t]+\s*/', ' · ', trim($t)) ?? '';
        $t = preg_replace('/ {4,}/', '   ', $t) ?? '';

        return $t === '' ? '-' : mb_substr($t, 0, 300);
    }
}
