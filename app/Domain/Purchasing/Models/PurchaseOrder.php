<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * PO — Purchase Order (Katalog Status §2.17, glosarium `purchase_order`,
 * purchasing/02). Satu vendor × satu gudang tujuan; baris dari baris PRQ
 * (A-210). Nilai uang hanya ada di domain Purchasing (A-208).
 *
 * Cakupan (BR-ACC-05): gudang tujuan.
 *
 * @property PurchaseOrderStatus $status
 */
class PurchaseOrder extends Model
{
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'purchase_orders';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'IDR',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'eta_date' => 'date',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
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

    public function rejectReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reject_reason_id');
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function closeReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'close_reason_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ApprovalSnapshot::class, 'approval_snapshot_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /** Catatan pemesanan PRQ yang lahir dari PO ini saat disetujui (`po_created`, A-213). */
    public function requestOrders(): HasMany
    {
        return $this->hasMany(PurchaseRequestOrder::class);
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === PurchaseOrderStatus::PendingApproval
            && app(ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::PurchaseOrder, (int) $this->id) !== null;
    }

    public function hasReceipts(): bool
    {
        return $this->lines()->where('qty_received', '>', 0)->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('purchase_order')
            ->logOnly(['number', 'status', 'eta_date', 'approved_by'])
            ->logOnlyDirty();
    }
}
