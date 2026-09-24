<?php

declare(strict_types=1);

namespace App\Domain\Approval\Models;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Snapshot aturan yang melekat pada satu pengajuan dokumen (BR-APR-01).
 *
 * `steps` adalah salinan lapis setelah SoD dan resolusi approver; perubahan
 * aturan sesudahnya tidak menyentuhnya. `context` adalah data dokumen yang
 * dicocokkan saat diajukan, disimpan supaya "mengapa aturan ini berlaku" bisa
 * dijawab belakangan.
 *
 * Kolom waktunya bermikrodetik (lihat migrasi).
 *
 * @property ApprovalDocumentType $document_type
 * @property ApprovalSnapshotStatus $status
 * @property array<int, array<string, mixed>> $steps
 * @property array<string, mixed>|null $context
 */
class ApprovalSnapshot extends Model
{
    protected $table = 'approval_snapshots';

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'document_type' => ApprovalDocumentType::class,
            'document_id' => 'integer',
            'status' => ApprovalSnapshotStatus::class,
            'steps' => 'array',
            'context' => 'array',
            'current_step' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'rule_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ApprovalTask::class)->orderBy('id');
    }

    public function scopeForDocument(Builder $query, ApprovalDocumentType|string $type, int $id): Builder
    {
        $nilai = $type instanceof ApprovalDocumentType ? $type->value : $type;

        return $query->where('document_type', $nilai)->where('document_id', $id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalSnapshotStatus::Pending->value);
    }

    /** @return array<string, mixed>|null lapis dengan nomor tertentu */
    public function step(?int $stepNo): ?array
    {
        foreach ($this->steps ?? [] as $s) {
            if ((int) $s['step_no'] === $stepNo) {
                return $s;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null lapis sesudah nomor tertentu */
    public function stepAfter(int $stepNo): ?array
    {
        foreach ($this->steps ?? [] as $s) {
            if ((int) $s['step_no'] > $stepNo) {
                return $s;
            }
        }

        return null;
    }

    /** @return array<int, int> id pemohon/pengaju yang tidak boleh memutus (BR-APR-03) */
    public function requesterIds(): array
    {
        return array_map('intval', $this->context['requester_ids'] ?? []);
    }

    /** Disetujui tanpa lapis (A-08). */
    public function wasAutoApproved(): bool
    {
        return $this->status === ApprovalSnapshotStatus::Approved && ($this->steps ?? []) === [];
    }
}
