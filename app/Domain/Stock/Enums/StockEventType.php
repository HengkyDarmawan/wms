<?php

declare(strict_types=1);

namespace App\Domain\Stock\Enums;

/**
 * Matriks kejadian stok — Aturan Bisnis §14.
 *
 * Satu kejadian per satu pergerakan ledger. Daftar ini adalah kontrak ke modul
 * Akuntansi dan Purchasing (AD-05); menambah nilai berarti mengubah katalog,
 * bukan menambah enum diam-diam.
 */
enum StockEventType: string
{
    case GoodsReceived = 'goods_received';
    case GoodsRejected = 'goods_rejected';
    case GoodsShipped = 'goods_shipped';
    case GoodsDelivered = 'goods_delivered';
    case GoodsReturned = 'goods_returned';
    case StockTransferred = 'stock_transferred';
    case DeliveryDiscrepancy = 'delivery_discrepancy';
    case MaterialConsumed = 'material_consumed';
    case MaterialConverted = 'material_converted';
    case WasteDisposed = 'waste_disposed';
    case StockAdjusted = 'stock_adjusted';
    case AssetCheckedOut = 'asset_checked_out';
    case AssetReturned = 'asset_returned';
    case AssetLostOrDamaged = 'asset_lost_or_damaged';
    case PurchaseRequested = 'purchase_requested';
    case PurchaseRequestCancelled = 'purchase_request_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::GoodsReceived => 'Barang diterima',
            self::GoodsRejected => 'Barang ditolak ke vendor',
            self::GoodsShipped => 'Barang dikirim',
            self::GoodsDelivered => 'Barang diterima klien',
            self::GoodsReturned => 'Barang diretur',
            self::StockTransferred => 'Stok dipindah antar gudang',
            self::DeliveryDiscrepancy => 'Selisih pengiriman',
            self::MaterialConsumed => 'Material dipakai proyek',
            self::MaterialConverted => 'Material dikonversi',
            self::WasteDisposed => 'Waste dibuang',
            self::StockAdjusted => 'Stok disesuaikan',
            self::AssetCheckedOut => 'Aset dipinjamkan',
            self::AssetReturned => 'Aset kembali',
            self::AssetLostOrDamaged => 'Aset hilang atau rusak',
            self::PurchaseRequested => 'Permintaan pembelian',
            self::PurchaseRequestCancelled => 'Permintaan pembelian dibatalkan',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }

    /**
     * Kejadian yang tidak menggerakkan ledger sama sekali; diterbitkan aksi
     * dokumen, bukan oleh posting stok (BR §14).
     */
    public function isLedgerless(): bool
    {
        return in_array($this, [
            self::AssetLostOrDamaged,
            self::PurchaseRequested,
            self::PurchaseRequestCancelled,
        ], true);
    }
}
