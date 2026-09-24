<?php

declare(strict_types=1);

namespace App\Domain\Return\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Return\Enums\ReturnOwnership;
use App\Domain\Return\Enums\ReturnSorting;
use App\Domain\Return\Enums\ReturnSource;
use App\Domain\Shipment\Models\DeliveryDiscrepancyLine;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris RET — satu item × lot/serial/potongan yang dikembalikan.
 *
 * `qty_base` = jumlah yang diajukan dan tidak pernah diubah; `qty_received` =
 * yang diterima GRN retur. Pemilahan boleh membagi baris menjadi beberapa hasil:
 * bagian pertama ditulis di baris ini, bagian berikutnya menjadi baris hasil
 * pilah (`split_from_line_id`, jumlah diajukan 0) — A-113.
 *
 * @property ReturnOwnership $ownership
 * @property ReturnSorting|null $sorting
 * @property StockStatus $stock_status
 */
class GoodsReturnLine extends Model
{
    use HasFactory;

    protected $table = 'goods_return_lines';

    protected $guarded = [];

    protected $attributes = [
        'ownership' => 'company',
        'stock_status' => 'available',
    ];

    protected function casts(): array
    {
        return [
            'ownership' => ReturnOwnership::class,
            'sorting' => ReturnSorting::class,
            'stock_status' => StockStatus::class,
            'qty_base' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'sorted_qty' => 'decimal:4',
        ];
    }

    public function goodsReturn(): BelongsTo
    {
        return $this->belongsTo(GoodsReturn::class)->withoutGlobalScopes();
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

    public function newPiece(): BelongsTo
    {
        return $this->belongsTo(Piece::class, 'new_piece_id');
    }

    public function fromBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'from_bin_id')->withoutGlobalScopes();
    }

    public function targetBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'target_bin_id')->withoutGlobalScopes();
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }

    public function originShipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class, 'origin_shipment_line_id');
    }

    public function originDiscrepancyLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryDiscrepancyLine::class, 'origin_discrepancy_line_id');
    }

    public function splitParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_line_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(self::class, 'split_from_line_id');
    }

    /** A-110: asal barang diturunkan dari kolom baris. */
    public function source(): ReturnSource
    {
        $baris = $this->split_from_line_id !== null ? $this->splitParent : $this;

        if ($baris->from_bin_id !== null) {
            return $baris->fromBin?->bin_type === BinType::OnSite ? ReturnSource::OnSiteAsset : ReturnSource::SiteStock;
        }

        return $baris->origin_discrepancy_line_id !== null
            ? ReturnSource::CarrierLeftDamaged
            : ReturnSource::DeliveredToClient;
    }

    public function isSplit(): bool
    {
        return $this->split_from_line_id !== null;
    }

    /** Label turunan pelacakan untuk layar. */
    public function trackingLabel(): string
    {
        return match (true) {
            $this->serial_id !== null => 'SN '.$this->serial?->serial_no,
            $this->lot_id !== null => 'Lot '.$this->lot?->lot_no,
            $this->piece_id !== null => 'Potongan '.$this->piece?->piece_no.' ('.number_format((float) $this->piece?->length, 2, ',', '.').')',
            default => '',
        };
    }
}
