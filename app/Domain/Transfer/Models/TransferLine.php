<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Serial;
use App\Domain\Request\Models\MaterialRequestLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris TRF — item × jumlah dalam satuan dasar. Lot/serial/potongan dipilih
 * saat picking (alokasi keras, BR-STK-04); jumlah terkirim dan diterima diisi
 * dari SJ dan GRN transfer (A-107).
 */
class TransferLine extends Model
{
    use HasFactory;

    protected $table = 'transfer_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
            'qty_shipped' => 'decimal:4',
            'qty_received' => 'decimal:4',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class)->withoutGlobalScopes();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Aset yang dipindah antar proyek (TRF aset, A-249). */
    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }

    /** Baris REQ penunggu bila TRF lahir dari backorder (BR-REQ-08). */
    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestLine::class, 'material_request_line_id');
    }
}
