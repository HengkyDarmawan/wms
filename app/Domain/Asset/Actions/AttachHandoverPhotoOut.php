<?php

declare(strict_types=1);

namespace App\Domain\Asset\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Exceptions\AssetRuleException;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Shared\Attachments\Models\Attachment;
use App\Domain\Shared\Attachments\Support\AttachmentStore;
use Illuminate\Http\UploadedFile;

/**
 * Permission: `asset.manage` — foto serah terima keluar (`photo_out_id`,
 * 25-aset §13.3, A-238), bagian dari "Lengkapi serah terima" selama AST masih
 * `checked_out`. Foto baru menggantikan rujukan; foto lama tetap tersimpan
 * sebagai lampiran (P-03).
 */
class AttachHandoverPhotoOut
{
    public function __construct(private readonly AttachmentStore $store) {}

    public function handle(AssetHandover $ast, UploadedFile $photo, ?User $actor = null): Attachment
    {
        if ($ast->status !== AssetHandoverStatus::CheckedOut || $ast->lost_at !== null) {
            throw AssetRuleException::rule('BR-GEN-01', 'Foto serah terima keluar hanya bisa diunggah selama aset masih dipinjam.');
        }

        $lampiran = $this->store->photo($ast, $photo, $actor);
        $lama = $ast->photo_out_id;
        $ast->forceFill(['photo_out_id' => $lampiran->id])->save();

        activity('asset')->performedOn($ast)->causedBy($actor)
            ->withProperties(['attachment' => $lampiran->id, 'sebelumnya' => $lama])
            ->log('Foto serah terima keluar diunggah');

        return $lampiran;
    }
}
