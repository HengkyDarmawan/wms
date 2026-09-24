<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris PUT — pindahkan sekian dari bin Penerimaan ke bin ini.
 *
 * `suggested_bin_id` menyimpan saran sistem, `bin_id` bin yang benar-benar
 * dipakai; penggantian menuntut alasan (19-receipt-putaway §5), sama seperti
 * picking (BR-SJ-01).
 */
class PutawayTaskLine extends Model
{
    use HasFactory;

    protected $table = 'putaway_task_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
            'scanned_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PutawayTask::class, 'putaway_task_id')->withoutGlobalScopes();
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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

    public function fromBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'from_bin_id')->withoutGlobalScopes();
    }

    public function suggestedBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'suggested_bin_id')->withoutGlobalScopes();
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id')->withoutGlobalScopes();
    }
}
