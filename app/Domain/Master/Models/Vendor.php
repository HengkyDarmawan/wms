<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Vendor — pemasok barang. Jenisnya membedakan perusahaan, toko, lapak
 * marketplace, dan perorangan (A-52). Vendor yang ditambahkan mendadak saat
 * pemesanan berstatus `provisional` sampai dilengkapi (A-53). Tanpa harga (D-07).
 *
 * @property VendorType $vendor_type
 * @property VendorStatus $status
 */
class Vendor extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'vendors';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'vendor_type' => VendorType::class,
            'status' => VendorStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_vendors')
            ->withPivot(['priority', 'is_preferred', 'notes'])
            ->withTimestamps();
    }

    public function itemVendors(): HasMany
    {
        return $this->hasMany(ItemVendor::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VendorStatus::Active->value)->where('is_active', true);
    }

    /** A-53: vendor sementara boleh dipakai pemesanan, tapi ditandai di layar. */
    public function isProvisional(): bool
    {
        return $this->status === VendorStatus::Provisional;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            VendorStatus::Active => 'success',
            VendorStatus::Provisional => 'warning',
            VendorStatus::Inactive => 'secondary',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['code', 'name', 'tax_id', 'contact_name', 'phone', 'email', 'payment_terms', 'vendor_type', 'status', 'is_active'])
            ->logOnlyDirty();
    }
}
