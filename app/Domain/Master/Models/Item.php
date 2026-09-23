<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\LineOwnership;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Item — master barang (Blueprint §6.6). Mode pelacakan, model kepemilikan,
 * strategi pengambilan, dan kedaluwarsa saling terikat matriks
 * [BR §15](../../../../docs/wms/05-aturan-bisnis.md); pemeriksaannya ada di
 * {@see \App\Domain\Master\Support\TrackingCombination}.
 *
 * @property ItemStatus $status
 * @property OwnershipModel $ownership_model
 * @property TrackingMode $tracking_mode
 * @property LineOwnership|null $default_line_ownership
 * @property RemovalStrategy|null $removal_strategy
 */
class Item extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'ownership_model' => OwnershipModel::class,
            'tracking_mode' => TrackingMode::class,
            'default_line_ownership' => LineOwnership::class,
            'removal_strategy' => RemovalStrategy::class,
            'has_expiry' => 'boolean',
            'is_cuttable' => 'boolean',
            'requires_qc' => 'boolean',
            'dimensions' => 'array',
            'min_offcut_length' => 'decimal:4',
            'kerf' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'weight' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function weightUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'weight_uom_id');
    }

    public function uomConversions(): HasMany
    {
        return $this->hasMany(ItemUomConversion::class);
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'item_vendors')
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

    public function serials(): HasMany
    {
        return $this->hasMany(Serial::class);
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ItemStatus::Active->value);
    }

    /** A-51: item sementara dari permintaan klien belum boleh dipakai dokumen resmi. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereIn('status', [ItemStatus::Active->value, ItemStatus::Provisional->value]);
    }

    /** Strategi efektif: milik item, kalau kosong warisi kategori, terakhir FIFO (A-10). */
    public function effectiveRemovalStrategy(): RemovalStrategy
    {
        return $this->removal_strategy
            ?? $this->category?->removal_strategy
            ?? ($this->tracking_mode === TrackingMode::Piece
                ? RemovalStrategy::OffcutFirst
                : RemovalStrategy::Fifo);
    }

    public function tracksLot(): bool
    {
        return $this->tracking_mode === TrackingMode::Lot;
    }

    public function tracksSerial(): bool
    {
        return $this->tracking_mode === TrackingMode::Serial;
    }

    public function tracksPiece(): bool
    {
        return $this->tracking_mode === TrackingMode::Piece;
    }

    /** BR-STK-08: item yang bisa dipinjamkan sebagai aset. */
    public function isAsset(): bool
    {
        return in_array($this->ownership_model, [OwnershipModel::Asset, OwnershipModel::Both], true);
    }

    /** BR-MST-02: satuan dasar terkunci begitu ada lot/serial/potong. */
    public function baseUomIsLocked(): bool
    {
        return $this->exists && (
            $this->lots()->exists()
            || $this->serials()->exists()
            || $this->pieces()->exists()
        );
    }

    /** BR-MST-05: item yang sudah punya turunan tidak boleh dihapus, hanya dinonaktifkan. */
    public function hasTrackingRecords(): bool
    {
        return $this->baseUomIsLocked();
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            ItemStatus::Active => 'success',
            ItemStatus::Provisional => 'warning',
            ItemStatus::Inactive => 'secondary',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly([
                'code', 'name', 'item_category_id', 'status', 'ownership_model',
                'default_line_ownership', 'tracking_mode', 'has_expiry', 'base_uom_id',
                'is_cuttable', 'min_offcut_length', 'kerf', 'requires_qc',
                'removal_strategy', 'reorder_point', 'min_stock', 'barcode',
            ])
            ->logOnlyDirty();
    }
}
