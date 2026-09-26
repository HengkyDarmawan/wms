<?php

declare(strict_types=1);

namespace App\Domain\Shared\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyimpan berkas unggahan ke disk tenant (AD-10, NFR-14).
 *
 * Disk `local` sudah dipisah per company oleh `FilesystemTenancyBootstrapper`
 * (config/tenancy.php §filesystem), jadi berkas satu company tidak pernah
 * berada di folder company lain.
 *
 * Penyedia S3 untuk produksi masih menunggu [O-14]; sampai itu diputuskan,
 * dev dan staging memakai disk lokal ([A-68], disetujui 24 Sep 2026).
 */
class StoreUpload
{
    /** NFR-14, A-23: batas ukuran berkas **tersimpan**. */
    public const MAKSIMUM_BYTE = 5 * 1024 * 1024;

    /** A-257: batas unggahan mentah sebelum dikompres (foto kamera HP). */
    public const MAKSIMUM_MENTAH = 20 * 1024 * 1024;

    /** @var array<int, string> */
    public const TIPE_GAMBAR = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var array<int, string> aturan validasi foto unggahan (A-257: mentah ≤ 20 MB) */
    public const ATURAN_FOTO = ['image', 'mimes:jpg,jpeg,png,webp', 'max:20480'];

    private const DISK = 'local';

    /**
     * Foto dikompres otomatis ({@see ImageCompressor}, A-23): unggahan mentah
     * ≤ 20 MB, hasil tersimpan ≤ 5 MB. `$keepFormat` menjaga PNG tetap PNG
     * (tanda tangan, logo). Berkas bukan gambar disimpan apa adanya.
     *
     * @param  array<int, string>  $allowedMimes
     * @return string path relatif di dalam disk tenant
     */
    public function handle(UploadedFile $file, string $folder, string $name, array $allowedMimes = self::TIPE_GAMBAR, bool $keepFormat = false): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Berkas gagal diunggah.');
        }

        if ($file->getSize() > self::MAKSIMUM_MENTAH) {
            throw new RuntimeException('Ukuran berkas melebihi 20 MB.');
        }

        // Tipe dibaca dari isi berkas, bukan dari nama atau header klien.
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Jenis berkas tidak didukung.');
        }

        $hasil = app(ImageCompressor::class)->compress((string) $file->getRealPath(), $mime, $keepFormat);
        $mimeAkhir = $hasil['mime'] ?? $mime;
        $ukuranAkhir = $hasil !== null ? strlen($hasil['bytes']) : (int) $file->getSize();

        if ($ukuranAkhir > self::MAKSIMUM_BYTE) {
            throw new RuntimeException(in_array($mime, self::TIPE_GAMBAR, true)
                ? 'Ukuran gambar masih melebihi 5 MB setelah dikompres.'
                : 'Ukuran berkas melebihi 5 MB.');
        }

        $ekstensi = match ($mimeAkhir) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'jpg',
        };

        $path = trim($folder, '/').'/'.$name.'.'.$ekstensi;

        // Berkas lama dengan ekstensi berbeda dibuang supaya tidak menumpuk.
        foreach (['jpg', 'png', 'webp'] as $lama) {
            $kandidat = trim($folder, '/').'/'.$name.'.'.$lama;

            if ($kandidat !== $path && Storage::disk(self::DISK)->exists($kandidat)) {
                Storage::disk(self::DISK)->delete($kandidat);
            }
        }

        if ($hasil !== null) {
            Storage::disk(self::DISK)->put($path, $hasil['bytes']);
        } else {
            Storage::disk(self::DISK)->putFileAs(trim($folder, '/'), $file, $name.'.'.$ekstensi);
        }

        return $path;
    }

    /** Ukuran berkas tersimpan (setelah kompresi). */
    public function size(string $path): int
    {
        return (int) Storage::disk(self::DISK)->size($path);
    }

    /** MIME berkas tersimpan (setelah kompresi). */
    public function mimeType(string $path): string
    {
        return (string) Storage::disk(self::DISK)->mimeType($path);
    }

    /**
     * Gambar dari data URL (`data:image/png;base64,…`) — kanvas tanda tangan
     * bukti terima (A-231). Batas ukuran dan jenis sama dengan unggahan biasa;
     * isi diperiksa sebagai gambar sungguhan, bukan sekadar awalan teks.
     *
     * @return string path relatif di dalam disk tenant
     */
    public function handleDataUrl(string $dataUrl, string $folder, string $name): string
    {
        if (preg_match('#^data:(image/(?:png|jpeg|webp));base64,([A-Za-z0-9+/=\r\n]+)$#', trim($dataUrl), $m) !== 1) {
            throw new RuntimeException('Tanda tangan tidak terbaca.');
        }

        $isi = base64_decode($m[2], true);

        if ($isi === false || $isi === '') {
            throw new RuntimeException('Tanda tangan tidak terbaca.');
        }

        if (strlen($isi) > self::MAKSIMUM_BYTE) {
            throw new RuntimeException('Ukuran berkas melebihi 5 MB.');
        }

        $info = @getimagesizefromstring($isi);

        if ($info === false) {
            throw new RuntimeException('Jenis berkas tidak didukung.');
        }

        $ekstensi = match ($info['mime'] ?? '') {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            default => throw new RuntimeException('Jenis berkas tidak didukung.'),
        };

        $path = trim($folder, '/').'/'.$name.'.'.$ekstensi;
        Storage::disk(self::DISK)->put($path, $isi);

        return $path;
    }

    /**
     * Isi yang dibuat sistem (mis. PDF laporan opname, A-238) — bukan unggahan
     * user, jadi tidak dibatasi 5 MB.
     *
     * @return string path relatif di dalam disk tenant
     */
    public function putContent(string $content, string $folder, string $name, string $extension): string
    {
        $path = trim($folder, '/').'/'.$name.'.'.$extension;
        Storage::disk(self::DISK)->put($path, $content);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    /** Isi berkas untuk dikirim lewat controller, bukan lewat URL publik. */
    public function stream(string $path): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($path);
    }

    /** Seperti stream(), tetapi sebagai unduhan bernama. */
    public function download(string $path, string $name): StreamedResponse
    {
        return Storage::disk(self::DISK)->download($path, $name);
    }
}
