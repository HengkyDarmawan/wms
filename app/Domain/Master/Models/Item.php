<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\ItemKind;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\LineOwnership;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Support\TrackingCombination;
use App\Domain\Stock\Models\StockMovement;
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
 * {@see TrackingCombination}.
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

    /** A-294: kemasan yang berlaku, dari isi terbesar (mis. DUS = 12 sebelum PACK = 4). */
    public function activeConversions(): HasMany
    {
        return $this->hasMany(ItemUomConversion::class)->where('is_active', true)->orderByDesc('qty_base');
    }

    /**
     * A-291: satuan yang bisa diketik di baris dokumen — satuan dasar (faktor 1)
     * lalu kemasan aktif. Item per potong hanya satuan dasar (BR-STK-09).
     *
     * @return array<int, array{uom_id: ?int, code: string, factor: float}>
     */
    public function unitOptions(): array
    {
        $dasar = [['uom_id' => null, 'code' => (string) $this->baseUom?->code, 'factor' => 1.0]];

        if ($this->tracksPiece()) {
            return $dasar;
        }

        return array_merge($dasar, $this->activeConversions
            ->filter(fn (ItemUomConversion $k) => ! $k->is_nominal_piece && (int) $k->uom_id !== (int) $this->base_uom_id)
            ->map(fn (ItemUomConversion $k) => ['uom_id' => (int) $k->uom_id, 'code' => (string) $k->uom?->code, 'factor' => (float) $k->qty_base])
            ->values()->all());
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

    /**
     * A-283: saring menurut Jenis barang; `null` = Jenis khusus (di luar tiga jenis).
     */
    public function scopeOfKind(Builder $query, ?ItemKind $jenis): Builder
    {
        $cocok = fn (Builder $q, ItemKind $j) => $q
            ->where('tracking_mode', $j->trackingMode()->value)
            ->where('ownership_model', $j->ownershipModel()->value)
            ->where('has_expiry', $j->hasExpiry());

        if ($jenis !== null) {
            return $cocok($query, $jenis);
        }

        foreach (ItemKind::cases() as $j) {
            $query->whereNot(fn (Builder $q) => $cocok($q, $j));
        }

        return $query;
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

    /**
     * A-38: sifat baris (Beli/Pinjam) yang diisikan saat item ini dipilih di
     * baris permintaan — aset = Pinjam, habis pakai = Beli, Keduanya = sifat
     * baris bawaan item (kosong = Beli). Pemohon tetap bisa mengubahnya.
     */
    public function defaultLineOwnershipValue(): string
    {
        return match ($this->ownership_model) {
            OwnershipModel::Asset => LineOwnership::Loan->value,
            OwnershipModel::Both => $this->default_line_ownership?->value ?? LineOwnership::Buy->value,
            default => LineOwnership::Buy->value,
        };
    }

    /**
     * BR-MST-02 (A-356): satuan dasar terkunci begitu ada lot/serial/potongan
     * atau pergerakan stok — saldo lama tercatat dalam satuan itu.
     */
    public function baseUomIsLocked(): bool
    {
        return $this->baseUomLockReason() !== null;
    }

    /** Alasan kunci satuan dasar untuk ditampilkan di dekat isiannya; null = bebas diubah. */
    public function baseUomLockReason(): ?string
    {
        return match (true) {
            $this->hasTrackingRecords() => 'Terkunci karena item ini sudah punya lot, serial, atau potongan.',
            $this->hasStockMovements() => 'Terkunci karena item ini sudah punya pergerakan stok.',
            default => null,
        };
    }

    /** BR-MST-06: jenis barang terkunci begitu item punya pergerakan stok. */
    public function hasStockMovements(): bool
    {
        return $this->exists && StockMovement::query()->where('item_id', $this->id)->exists();
    }

    /** BR-MST-05: item yang sudah punya turunan tidak boleh dihapus, hanya dinonaktifkan. */
    public function hasTrackingRecords(): bool
    {
        return $this->exists && (
            $this->lots()->exists()
            || $this->serials()->exists()
            || $this->pieces()->exists()
        );
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
