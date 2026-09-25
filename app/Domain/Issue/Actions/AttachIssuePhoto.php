<?php

declare(strict_types=1);

namespace App\Domain\Issue\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Shared\Attachments\Models\Attachment;
use App\Domain\Shared\Attachments\Support\AttachmentStore;
use Illuminate\Http\UploadedFile;

/**
 * Permission: `issue.create` — foto pemakaian material di site (23-pemakaian
 * §13.3, A-238). Boleh ditambahkan pada ISU draf maupun terkonfirmasi (foto
 * sering diambil setelah pemasangan), tidak pada ISU batal; paling banyak
 * sepuluh foto per ISU. Tidak mengubah status maupun stok.
 */
class AttachIssuePhoto
{
    public const MAKSIMUM = 10;

    public function __construct(private readonly AttachmentStore $store) {}

    public function handle(MaterialIssue $issue, UploadedFile $photo, ?User $actor = null): Attachment
    {
        if ($issue->status === MaterialIssueStatus::Cancelled) {
            throw IssueRuleException::rule('BR-GEN-01', 'ISU yang dibatalkan tidak bisa diberi foto.');
        }

        if (Attachment::query()->for($issue)->count() >= self::MAKSIMUM) {
            throw IssueRuleException::field('BR-GEN-01', 'photo', 'Paling banyak '.self::MAKSIMUM.' foto per ISU.');
        }

        $lampiran = $this->store->photo($issue, $photo, $actor);

        activity('issue')->performedOn($issue)->causedBy($actor)
            ->withProperties(['attachment' => $lampiran->id])
            ->log('Foto pemakaian ditambahkan');

        return $lampiran;
    }
}
