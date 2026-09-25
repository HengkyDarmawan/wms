<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/**
 * Katalog Status §3 `approval_document_type` — jenis dokumen yang boleh punya
 * aturan approval (alur 9: REQ, TRF, RET, CNV, ADJ, WST, PRQ, RTV, OPN, plus
 * ISU pembalik). Nilainya nama di kode dari Glosarium.
 *
 * Hanya jenis yang punya penangan terdaftar di `ApprovalRegistry` yang bisa
 * dipilih di layar aturan; sisanya titik sambung modul yang belum dibangun
 * (BR-GEN-10). `purchase_order` milik modul Purchasing (D-28, purchasing/02) —
 * satu-satunya jenis dengan kondisi nilai uang (A-212).
 */
enum ApprovalDocumentType: string
{
    use HasOptions;

    case MaterialRequest = 'material_request';
    case Transfer = 'transfer';
    case GoodsReturn = 'goods_return';
    case Conversion = 'conversion';
    case StockAdjustment = 'stock_adjustment';
    case WasteDisposal = 'waste_disposal';
    case PurchaseRequest = 'purchase_request';
    case VendorReturn = 'vendor_return';
    case StockCount = 'stock_count';
    case MaterialIssue = 'material_issue';
    case PurchaseOrder = 'purchase_order';

    public function code(): string
    {
        return match ($this) {
            self::MaterialRequest => 'REQ',
            self::Transfer => 'TRF',
            self::GoodsReturn => 'RET',
            self::Conversion => 'CNV',
            self::StockAdjustment => 'ADJ',
            self::WasteDisposal => 'WST',
            self::PurchaseRequest => 'PRQ',
            self::VendorReturn => 'RTV',
            self::StockCount => 'OPN',
            self::MaterialIssue => 'ISU',
            self::PurchaseOrder => 'PO',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MaterialRequest => 'Permintaan Material',
            self::Transfer => 'Transfer',
            self::GoodsReturn => 'Retur',
            self::Conversion => 'Konversi Material',
            self::StockAdjustment => 'Penyesuaian Stok',
            self::WasteDisposal => 'Berita Acara Waste',
            self::PurchaseRequest => 'Purchase Request',
            self::VendorReturn => 'Retur ke Vendor',
            self::StockCount => 'Stock Opname',
            self::MaterialIssue => 'Pemakaian Material',
            self::PurchaseOrder => 'Purchase Order',
        };
    }

    /** Label singkat untuk tabel: "REQ — Permintaan Material". */
    public function longLabel(): string
    {
        return $this->code().' — '.$this->label();
    }

    /**
     * Kondisi yang bermakna untuk jenis dokumen ini (BR-APR-07, Blueprint §8.1).
     * Layar aturan hanya menampilkan kondisi ini.
     *
     * @return array<int, string> kunci kondisi (lihat ConditionMatcher::KEYS)
     */
    public function conditions(): array
    {
        return match ($this) {
            self::MaterialRequest => ['warehouse_ids', 'project_ids', 'category_ids', 'ownership_models', 'line_count_min', 'line_qty_min', 'from_client'],
            self::VendorReturn => ['warehouse_ids', 'category_ids', 'ownership_models', 'line_count_min', 'line_qty_min', 'vendor_types'],
            self::PurchaseRequest => ['warehouse_ids', 'project_ids', 'category_ids', 'line_count_min', 'line_qty_min', 'vendor_types', 'purchase_request_origins'],
            self::StockCount => ['warehouse_ids', 'count_types'],
            self::StockAdjustment => ['warehouse_ids', 'category_ids', 'ownership_models', 'line_count_min', 'line_qty_min'],
            self::GoodsReturn => ['warehouse_ids', 'project_ids', 'category_ids', 'ownership_models', 'line_count_min', 'line_qty_min', 'from_client'],
            self::PurchaseOrder => ['warehouse_ids', 'category_ids', 'vendor_types', 'line_count_min', 'order_value_min'],
            default => ['warehouse_ids', 'project_ids', 'category_ids', 'ownership_models', 'line_count_min', 'line_qty_min'],
        };
    }
}
