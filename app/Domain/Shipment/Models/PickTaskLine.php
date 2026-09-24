<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris PCK — satu alokasi: ambil sekian dari bin ini.
 *
 * `suggested_bin_id` menyimpan saran sistem, `bin_id` apa yang benar-benar
 * dipakai. Keduanya disimpan supaya penggantian bin bisa ditelusuri
 * ([BR-SJ-01](docs/wms/05-aturan-bisnis.md)), bukan hilang begitu staf
 * menimpanya.
 */
class PickTaskLine extends Model
{
    use HasFactory;

    protected $table = 'pick_task_lines';

    protected $guarded = [];

    protected $attributes = [
        'qty_picked' => 0,
    ];

    protected function casts(): array
    {
        return [
            'qty_allocated' => 'decimal:4',
            'qty_picked' => 'decimal:4',
            'scanned_at' => 'datetime',
        ];
    }

    /** Lintas cakupan: baris hanya terjangkau dari dokumen yang sudah boleh dibaca. */
    public function pickTask(): BelongsTo
    {
        return $this->belongsTo(PickTask::class)->withoutGlobalScopes();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function suggestedBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'suggested_bin_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function shortReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'short_reason_id');
    }

    public function shipmentLines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class);
    }

    /** BR-SJ-02: fisik kurang dari alokasi. */
    public function isShort(): bool
    {
        return (float) $this->qty_picked < (float) $this->qty_allocated;
    }

    public function shortQty(): float
    {
        return max(0, (float) $this->qty_allocated - (float) $this->qty_picked);
    }

    /** Staf memakai bin lain dari yang disarankan sistem. */
    public function binWasOverridden(): bool
    {
        return $this->suggested_bin_id !== null
            && (int) $this->suggested_bin_id !== (int) $this->bin_id;
    }

    /**
     * Sisa yang belum dimuat ke SJ mana pun. SJ yang dibatalkan tidak dihitung:
     * barangnya tetap di Loading Area dan boleh dimuat SJ lain (Katalog §2.3).
     */
    public function unshippedQty(): float
    {
        $dimuat = (float) $this->shipmentLines()
            ->whereHas('shipment', fn (Builder $q) => $q->withoutGlobalScopes()
                ->where('status', '!=', \App\Domain\Shipment\Enums\ShipmentStatus::Cancelled->value))
            ->sum('qty_shipped');

        return max(0, (float) $this->qty_picked - $dimuat);
    }

    public function scopeShortPicked(Builder $query): Builder
    {
        return $query->whereColumn('qty_picked', '<', 'qty_allocated');
    }
}
