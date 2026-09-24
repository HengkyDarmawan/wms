<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Vendor;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * RTV — retur ke vendor (Katalog Status §2.16, A-34, BR-GRN-04).
 *
 * Barangnya selalu dari bin Karantina; setelah `shipped` barang keluar dari
 * buku besar dengan kejadian `goods_rejected`.
 *
 * @property VendorReturnStatus $status
 */
class VendorReturn extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'vendor_returns';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'submitted',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorReturnStatus::class,
            'approved_at' => 'datetime',
            'shipped_at' => 'datetime',
            'vendor_confirmed_at' => 'datetime',
        ];
    }

    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id')->withoutGlobalScopes();
    }

    public function replacementReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'replacement_receipt_id')->withoutGlobalScopes();
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

    public function lines(): HasMany
    {
        return $this->hasMany(VendorReturnLine::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('receipt')
            ->logOnly(['number', 'status', 'approved_by', 'shipped_at', 'vendor_confirmed_at', 'replacement_receipt_id'])
            ->logOnlyDirty();
    }
}
