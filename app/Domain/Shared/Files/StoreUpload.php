<?php

declare(strict_types=1);

namespace App\Domain\Shared\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
    /** NFR-14: batas ukuran berkas unggahan. */
    public const MAKSIMUM_BYTE = 5 * 1024 * 1024;

    /** @var array<int, string> */
    public const TIPE_GAMBAR = ['image/jpeg', 'image/png', 'image/webp'];

    private const DISK = 'local';

    /**
     * @param  array<int, string>  $allowedMimes
     * @return string path relatif di dalam disk tenant
     */
    public function handle(UploadedFile $file, string $folder, string $name, array $allowedMimes = self::TIPE_GAMBAR): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Berkas gagal diunggah.');
        }

        if ($file->getSize() > self::MAKSIMUM_BYTE) {
            throw new RuntimeException('Ukuran berkas melebihi 5 MB.');
        }

        // Tipe dibaca dari isi berkas, bukan dari nama atau header klien.
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Jenis berkas tidak didukung.');
        }

        $ekstensi = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
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

        Storage::disk(self::DISK)->putFileAs(trim($folder, '/'), $file, $name.'.'.$ekstensi);

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
    public function stream(string $path): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::disk(self::DISK)->response($path);
    }
}
