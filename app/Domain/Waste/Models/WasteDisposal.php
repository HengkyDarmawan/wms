<?php

declare(strict_types=1);

namespace App\Domain\Waste\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Enums\WasteDisposition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * WST — Berita Acara Waste (Katalog Status §2.14, glosarium `waste_disposal`,
 * Blueprint §6.7).
 *
 * Menutup isi bin Waste gudang dengan disposisi dibuang / dijual scrap /
 * dipakai ulang. Stok baru bergerak saat `closed` (dengan bukti), lewat
 * `StockLedger` (P-01) dengan kejadian `waste_disposed`.
 *
 * Cakupan (BR-ACC-05): gudang dan proyek harus sama-sama dalam cakupan.
 *
 * @property WasteDisposalStatus $status
 * @property WasteDisposition $disposition
 */
class WasteDisposal extends Model
{
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'waste_disposals';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'submitted',
    ];

    protected function casts(): array
    {
        return [
            'status' => WasteDisposalStatus::class,
            'disposition' => WasteDisposition::class,
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function targetBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'target_bin_id')->withoutGlobalScopes();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
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

    public function lines(): HasMany
    {
        return $this->hasMany(WasteDisposalLine::class);
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === WasteDisposalStatus::PendingApproval
            && app(ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::WasteDisposal, (int) $this->id) !== null;
    }

    public function hasEvidence(): bool
    {
        return ($this->evidence_path ?? '') !== '' || ($this->evidence_note ?? '') !== '';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('waste')
            ->logOnly(['number', 'status', 'disposition', 'approved_by', 'closed_by'])
            ->logOnlyDirty();
    }
}
