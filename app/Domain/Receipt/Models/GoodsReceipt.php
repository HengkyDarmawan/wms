<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Vendor;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * GRN — penerimaan barang (19-receipt-putaway §3.1).
 *
 * Satu-satunya dokumen masuk (A-33): dari vendor, dari SJ transfer, dan dari
 * RET (GRN retur ke bin Retur, A-112). Ledger diposting saat `received` (BR-GRN-01).
 *
 * @property GoodsReceiptStatus $status
 * @property ReceiptType $receipt_type
 */
class GoodsReceipt extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'goods_receipts';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'receipt_type' => ReceiptType::class,
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** BR-ACC-05: GRN mengikuti cakupan gudang penerimanya. */
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

    /** SJ transfer yang diterima; lintas cakupan karena gudang asalnya gudang lain. */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class)->withoutGlobalScopes();
    }

    /** RET yang diterima GRN retur (22-retur-transfer, A-112). */
    public function goodsReturn(): BelongsTo
    {
        return $this->belongsTo(GoodsReturn::class, 'goods_return_id')->withoutGlobalScopes();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function putawayTasks(): HasMany
    {
        return $this->hasMany(PutawayTask::class);
    }

    public function vendorReturns(): HasMany
    {
        return $this->hasMany(VendorReturn::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', GoodsReceiptStatus::Cancelled->value);
    }

    /** RTV yang barang penggantinya diterima GRN ini (BR-GRN-04). */
    public function replacedReturn(): ?VendorReturn
    {
        if ($this->source_type !== 'vendor_return' || $this->source_id === null) {
            return null;
        }

        return VendorReturn::query()->withoutGlobalScopes()->find($this->source_id);
    }

    public function sourceLabel(): string
    {
        return match ($this->receipt_type) {
            ReceiptType::Vendor => $this->vendor?->name ?? '—',
            ReceiptType::Transfer => $this->shipment?->number ?? '—',
            ReceiptType::Return => $this->goodsReturn?->number ?? '—',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('receipt')
            ->logOnly(['number', 'status', 'receipt_type', 'vendor_id', 'shipment_id', 'goods_return_id', 'received_at', 'completed_at'])
            ->logOnlyDirty();
    }
}
