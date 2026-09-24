<?php

declare(strict_types=1);

namespace App\Domain\Issue\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ISU — pemakaian material di Gudang Site (Katalog Status §2.9, glosarium
 * `material_issue`, A-32).
 *
 * Barang habis pakai keluar dari bin Gudang Site proyek saat ISU dikonfirmasi,
 * lewat `StockLedger` (P-01) dengan kejadian `material_consumed`. Koreksi
 * setelah `confirmed` hanya lewat ISU pembalik (jumlah negatif, alasan,
 * approval — BR-GEN-04, A-150).
 *
 * Cakupan (BR-ACC-05, A-119): Gudang Site dan proyeknya harus sama-sama dalam
 * cakupan pengguna.
 *
 * @property MaterialIssueStatus $status
 */
class MaterialIssue extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'material_issues';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaterialIssueStatus::class,
            'confirmed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public static function scopeProjectColumn(): ?string
    {
        return 'project_id';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withoutGlobalScopes();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }

    public function rejectReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reject_reason_id');
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ApprovalSnapshot::class, 'approval_snapshot_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id')->withoutGlobalScopes();
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id')->withoutGlobalScopes();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialIssueLine::class);
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }

    /** ISU pembalik yang sedang menunggu keputusan approver (A-150). */
    public function isAwaitingApproval(): bool
    {
        return $this->isReversal()
            && $this->status === MaterialIssueStatus::Draft
            && app(ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::MaterialIssue, (int) $this->id) !== null;
    }

    /** ISU pembalik yang masih berlaku (draf atau dikonfirmasi) atas ISU ini. */
    public function activeReversals(): HasMany
    {
        return $this->reversals()->where('status', '!=', MaterialIssueStatus::Cancelled->value);
    }

    /**
     * Id baris ISU ini yang sudah dibalik atau sedang diajukan untuk dibalik
     * (BR-LED-05: sekali saja per baris).
     *
     * @return array<int, int>
     */
    public function reversedLineIds(): array
    {
        return MaterialIssueLine::query()
            ->whereIn('material_issue_id', $this->activeReversals()->pluck('id'))
            ->whereNotNull('reversal_of_line_id')
            ->pluck('reversal_of_line_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('issue')
            ->logOnly(['number', 'status', 'confirmed_by', 'approved_by'])
            ->logOnlyDirty();
    }
}
