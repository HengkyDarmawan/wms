<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * PRQ — Purchase Request (Katalog Status §2.15, glosarium `purchase_request`,
 * A-47, A-51). Fase 1 manual: WMS menerbitkan PRQ, Penindak Lanjut PR mencatat
 * pemesanan per vendor; GRN vendor merujuk baris catatan pemesanan. Tanpa
 * nilai uang (D-07); PO & harga ada di modul Purchasing (D-08, D-28).
 *
 * Cakupan (BR-ACC-05): gudang tujuan.
 *
 * @property PurchaseRequestStatus $status
 * @property PurchaseRequestOrigin $origin
 */
class PurchaseRequest extends Model
{
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'purchase_requests';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
        'origin' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'origin' => PurchaseRequestOrigin::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withoutGlobalScopes();
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class)->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function forwarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_by');
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
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseRequestOrder::class);
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === PurchaseRequestStatus::PendingApproval
            && app(ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::PurchaseRequest, (int) $this->id) !== null;
    }

    /** Sudah ada barang yang diterima lewat GRN (Katalog §2.15: batal hanya bila belum ada GRN). */
    public function hasReceipts(): bool
    {
        return $this->lines()->where('qty_received', '>', 0)->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('purchase_request')
            ->logOnly(['number', 'status', 'approved_by', 'forwarded_by'])
            ->logOnlyDirty();
    }
}
