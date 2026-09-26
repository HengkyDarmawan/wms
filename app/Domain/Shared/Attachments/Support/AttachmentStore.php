<?php

declare(strict_types=1);

namespace App\Domain\Shared\Attachments\Support;

use App\Domain\Access\Models\User;
use App\Domain\Shared\Attachments\Enums\AttachmentKind;
use App\Domain\Shared\Attachments\Models\Attachment;
use App\Domain\Shared\Files\StoreUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Menyimpan berkas lampiran (A-68, A-238): berkas di disk `local` per company
 * di `attachments/<jenis-pemilik>/<id>/`, baris `attachments` menunjuknya.
 * Bila baris gagal disimpan, berkasnya dibuang lagi supaya tidak yatim.
 */
class AttachmentStore
{
    public function __construct(private readonly StoreUpload $files) {}

    /**
     * Foto unggahan user: jpeg/png/webp mentah ≤ 20 MB, dikompres otomatis
     * menjadi ≤ 5 MB (NFR-14, A-23, A-257). MIME & ukuran yang dicatat adalah
     * milik berkas tersimpan, bukan berkas asli.
     */
    public function photo(Model $owner, UploadedFile $file, ?User $actor = null): Attachment
    {
        $path = $this->files->handle($file, $this->folder($owner), $this->nama());

        return $this->catat($owner, AttachmentKind::Photo, $path, $this->files->mimeType($path), $this->files->size($path),
            mb_substr($file->getClientOriginalName(), 0, 150), $actor);
    }

    /** Berkas yang dibuat sistem, mis. PDF laporan. */
    public function content(Model $owner, AttachmentKind $kind, string $content, string $extension, string $mime, string $originalName, ?User $actor = null): Attachment
    {
        $path = $this->files->putContent($content, $this->folder($owner), $this->nama(), $extension);

        return $this->catat($owner, $kind, $path, $mime, strlen($content), $originalName, $actor);
    }

    private function catat(Model $owner, AttachmentKind $kind, string $path, string $mime, int $size, string $originalName, ?User $actor): Attachment
    {
        try {
            return Attachment::create([
                'attachable_type' => Attachment::typeOf($owner),
                'attachable_id' => $owner->getKey(),
                'kind' => $kind,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $originalName,
                'mime' => mb_substr($mime, 0, 60),
                'size_bytes' => $size,
                'uploaded_by' => $actor?->id,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->files->delete($path);

            throw $e;
        }
    }

    private function folder(Model $owner): string
    {
        return 'attachments/'.Attachment::typeOf($owner).'/'.$owner->getKey();
    }

    private function nama(): string
    {
        return now()->format('YmdHis').'-'.Str::lower(Str::random(6));
    }
}
