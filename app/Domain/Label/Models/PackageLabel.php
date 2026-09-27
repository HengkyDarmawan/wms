<?php

declare(strict_types=1);

namespace App\Domain\Label\Models;

use App\Domain\Access\Models\User;
use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Label kemasan (A-296): **induk** (`parent_id` kosong, satu per kemasan) atau
 * **isi** (satu per isi kemasan). Menyimpan asal GRN supaya barang bisa
 * ditelusuri ke vendor & pesanan. `qty_remaining` = isi yang masih dipegang
 * label ini; isi induk yang sudah dipecah ke label isi berpindah ke label isi.
 *
 * Label tidak pernah menggerakkan stok (P-01); statusnya ditulis hanya oleh
 * {@see PackageLabelLedger}.
 */
class PackageLabel extends Model
{
    protected $table = 'package_labels';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PackageLabelStatus::class,
            'qty' => 'decimal:4',
            'qty_remaining' => 'decimal:4',
            'cancelled_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sequence');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id')->withoutGlobalScopes();
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    public function packageUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'package_uom_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class)->withoutGlobalScopes();
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(PackageLabelMove::class)->orderBy('occurred_at')->orderBy('id');
    }

    /** Kejadian label terbaru (relasi `moves` berurut naik untuk riwayat). */
    public function lastMove(): ?PackageLabelMove
    {
        return $this->moves()->reorder()->latest('occurred_at')->latest('id')->first();
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', PackageLabelStatus::InStock->value);
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }
}
