<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Harga beli vendor per item (glosarium `vendor_price`, purchasing/01 §4 no. 2,
 * A-211): harga per satuan dasar, berlaku mulai `valid_from`. Perubahan harga
 * = baris baru; baris lama dinonaktifkan, tidak dihapus (P-03).
 */
class VendorPrice extends Model
{
    use LogsActivity;

    protected $table = 'vendor_prices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'valid_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('purchasing')
            ->logOnly(['vendor_id', 'item_id', 'unit_price', 'valid_from', 'is_active'])
            ->logOnlyDirty();
    }
}
