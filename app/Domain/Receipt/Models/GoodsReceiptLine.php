<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris GRN — satu lot, satu serial, atau satu potongan (BR-LED-03).
 *
 * Saat draf, nomor lot/serial dan panjang potongan disimpan sebagai isian
 * teks; turunannya (`lot_id`, `serial_id`, `piece_id`) baru dibuat saat GRN
 * `received`, supaya draf yang dibatalkan tidak meninggalkan serial yatim yang
 * menghalangi penerimaan berikutnya.
 *
 * @property QcResult|null $qc_result
 */
class GoodsReceiptLine extends Model
{
    use HasFactory;

    protected $table = 'goods_receipt_lines';

    protected $guarded = [];

    protected $attributes = [
        'is_cross_dock' => false,
    ];

    protected function casts(): array
    {
        return [
            'qc_result' => QcResult::class,
            'qty_received' => 'decimal:4',
            'qty_excess' => 'decimal:4',
            'piece_length' => 'decimal:4',
            'expiry_date' => 'date',
            'qc_at' => 'datetime',
            'is_cross_dock' => 'boolean',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id')->withoutGlobalScopes();
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

    public function receivingBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'receiving_bin_id')->withoutGlobalScopes();
    }

    public function shipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class);
    }

    public function returnLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReturnLine::class, 'goods_return_line_id');
    }

    public function qcUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_by');
    }

    public function qcReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'qc_reason_id');
    }

    public function putawayLines(): HasMany
    {
        return $this->hasMany(PutawayTaskLine::class);
    }

    public function vendorReturnLines(): HasMany
    {
        return $this->hasMany(VendorReturnLine::class);
    }

    public function receivingBinIsQuarantine(): bool
    {
        return $this->receivingBin?->bin_type === BinType::Quarantine;
    }

    /**
     * Kondisi stok baris ini di bin Karantina (A-78): menunggu QC atau
     * `quarantined` = Karantina, `rejected` = Rusak.
     */
    public function quarantineStockStatus(): StockStatus
    {
        return $this->qc_result === QcResult::Rejected ? StockStatus::Damaged : StockStatus::Quarantine;
    }

    /** Menunggu keputusan QC: masuk Karantina dan belum lolos atau ditolak. */
    public function awaitsQc(): bool
    {
        return $this->receivingBinIsQuarantine()
            && ($this->qc_result === null || $this->qc_result === QcResult::Quarantined);
    }

    /** Boleh di-put-away: tanpa QC atau lolos QC, dan bukan cross-dock. */
    public function isPutawayEligible(): bool
    {
        if ($this->is_cross_dock) {
            return false;
        }

        return $this->qc_result === QcResult::Passed
            || ($this->qc_result === null && ! $this->receivingBinIsQuarantine());
    }

    /** Label turunan pelacakan untuk layar: lot, serial, atau potongan. */
    public function trackingLabel(): string
    {
        return match (true) {
            $this->serial_no !== null => 'SN '.$this->serial_no,
            $this->lot_no !== null => 'Lot '.$this->lot_no,
            $this->piece_length !== null => 'Potongan '.number_format((float) $this->piece_length, 2, ',', '.'),
            $this->serial_id !== null => 'SN '.$this->serial?->serial_no,
            $this->lot_id !== null => 'Lot '.$this->lot?->lot_no,
            $this->piece_id !== null => 'Potongan '.$this->piece?->piece_no,
            default => '—',
        };
    }
}
