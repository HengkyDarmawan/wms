<?php

declare(strict_types=1);

namespace App\Domain\Stock\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kartu stok — satu baris per mutasi, **append-only** (P-01, BR-STK-01).
 *
 * Baris yang sudah tercatat tidak pernah diubah maupun dihapus: koreksi
 * dilakukan dengan baris pembalik (BR-LED-05). Larangannya ditegakkan di sini
 * dan juga oleh trigger database, karena jaminan sekuat ini tidak boleh
 * bergantung pada satu lapisan saja.
 *
 * @property StockStatus $stock_status
 */
class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'stock_movements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'qty_base' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw LedgerException::rule(
                'P-01',
                'Kartu stok tidak bisa diubah. Koreksi dilakukan lewat baris pembalik.',
            );
        });

        static::deleting(function (): void {
            throw LedgerException::rule('P-01', 'Kartu stok tidak bisa dihapus.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'from_bin_id');
    }

    public function toBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'to_bin_id');
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    /** Pergerakan yang menyentuh satu bin, baik sebagai asal maupun tujuan. */
    public function scopeTouchingBin(Builder $query, int $binId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('from_bin_id', $binId)
            ->orWhere('to_bin_id', $binId));
    }

    public function scopeForDocument(Builder $query, string $type, int $id): Builder
    {
        return $query->where('document_type', $type)->where('document_id', $id);
    }

    /** Masuk ke gudang bila tidak punya asal; keluar bila tidak punya tujuan. */
    public function isInbound(): bool
    {
        return $this->from_bin_id === null;
    }

    public function isOutbound(): bool
    {
        return $this->to_bin_id === null;
    }

    public function isTransfer(): bool
    {
        return $this->from_bin_id !== null && $this->to_bin_id !== null;
    }

    public function hasBeenReversed(): bool
    {
        return self::query()->where('reverses_movement_id', $this->id)->exists();
    }
}
