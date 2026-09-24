<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Template\Models\DocumentLayout;
use Illuminate\Support\Facades\Storage;

/**
 * Data kop layout induk untuk semua cetakan (18 §5.2). Gambar dari disk tenant
 * disematkan sebagai data URI karena dompdf tidak boleh membaca URL berotorisasi.
 */
class PrintAssets
{
    private const DISK = 'local';

    public function __construct(private readonly BarcodeImage $barcode) {}

    /**
     * @return array{company: string, logo: ?string, header: string, footer: string, accent: string}
     */
    public function kop(?DocumentLayout $layout = null): array
    {
        $layout ??= DocumentLayout::current();

        return [
            'company' => (string) (tenant()?->name ?? config('app.name')),
            'logo' => $this->image($layout->logo_path),
            'header' => (string) $layout->header_html,
            'footer' => (string) $layout->footer_html,
            'accent' => $layout->accent(),
        ];
    }

    /** QR dokumen berisi tautan halaman detail internal (A-121). */
    public function qr(string $url): string
    {
        return $this->barcode->qr($url, 3);
    }

    /** Berkas gambar di disk tenant sebagai data URI, atau null bila tidak ada. */
    public function image(?string $path): ?string
    {
        if ($path === null || $path === '' || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => null,
        };

        // dompdf hanya andal untuk PNG dan JPEG; format lain tidak dicetak.
        if ($mime === null) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk(self::DISK)->get($path));
    }
}
