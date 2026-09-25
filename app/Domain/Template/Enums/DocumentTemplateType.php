<?php

declare(strict_types=1);

namespace App\Domain\Template\Enums;

/**
 * Katalog §3 `document_template_type` — jenis template cetak (18 §5.1, §5.3).
 * Nilai dokumen = nama di kode Glosarium; label memakai awalan `label_`.
 */
enum DocumentTemplateType: string
{
    case Shipment = 'shipment';
    case ProofOfDelivery = 'proof_of_delivery';
    case PickTask = 'pick_task';
    case DeliveryDiscrepancy = 'delivery_discrepancy';
    case VendorReturn = 'vendor_return';
    case StockAdjustment = 'stock_adjustment';
    case MaterialIssue = 'material_issue';
    case Conversion = 'conversion';
    case StockCount = 'stock_count';
    case AssetHandover = 'asset_handover';
    case WasteDisposal = 'waste_disposal';
    case LabelBin = 'label_bin';
    case LabelItem = 'label_item';
    case LabelLot = 'label_lot';
    case LabelPiece = 'label_piece';

    public function label(): string
    {
        return match ($this) {
            self::Shipment => __('Surat Jalan'),
            self::ProofOfDelivery => __('Bukti Terima'),
            self::PickTask => __('Picklist'),
            self::DeliveryDiscrepancy => __('BA Selisih Pengiriman'),
            self::VendorReturn => __('Surat Retur ke Vendor'),
            self::StockAdjustment => __('BA Penyesuaian Stok'),
            self::MaterialIssue => __('Bukti Pemakaian Material'),
            self::Conversion => __('Bukti Konversi Material'),
            self::StockCount => __('Laporan Stock Opname'),
            self::AssetHandover => __('BA Serah Terima Aset'),
            self::WasteDisposal => __('BA Waste'),
            self::LabelBin => __('Label bin'),
            self::LabelItem => __('Label item'),
            self::LabelLot => __('Label lot'),
            self::LabelPiece => __('Label potongan'),
        };
    }

    public function isLabel(): bool
    {
        return str_starts_with($this->value, 'label_');
    }

    /** BR-GEN-10: modul pemiliknya belum dibangun, jadi template ini masih stub. Sejak modul Aset tidak ada lagi. */
    public function isStub(): bool
    {
        return false;
    }

    public function defaultPaper(): PaperSize
    {
        return match (true) {
            $this === self::StockCount => PaperSize::A4Landscape,
            $this === self::LabelBin => PaperSize::LabelA4Grid,
            $this->isLabel() => PaperSize::Label50x30,
            default => PaperSize::A4,
        };
    }

    /**
     * Blok tanda tangan bawaan (18 §5.2, A-125).
     *
     * @return array<int, string>
     */
    public function defaultSignatureBlocks(): array
    {
        return match ($this) {
            self::Shipment => ['Dibuat oleh', 'Pengemudi', 'Penerima'],
            self::ProofOfDelivery => ['Pengemudi', 'Penerima'],
            self::PickTask => ['Picker', 'Diperiksa'],
            self::DeliveryDiscrepancy => ['Kepala Gudang', 'Pengemudi'],
            self::VendorReturn => ['Dibuat oleh', 'Disetujui', 'Vendor'],
            self::StockAdjustment => ['Diajukan', 'Disetujui'],
            self::MaterialIssue => ['Dicatat oleh', 'Dikonfirmasi', 'PIC proyek'],
            self::Conversion => ['Dikerjakan oleh', 'Disetujui', 'PIC proyek'],
            self::WasteDisposal => ['Dibuat oleh', 'Disetujui', 'Saksi'],
            self::StockCount => ['Rekonsiliasi', 'Disetujui'],
            self::AssetHandover => ['Diserahkan', 'Diterima', 'Dikembalikan'],
            default => [],
        };
    }

    /** Segmen URL `/print/{type}/{id}`: dokumen yang bisa dicetak lewat modul ini. */
    public static function fromRoute(string $segment): ?self
    {
        $type = self::tryFrom(str_replace('-', '_', $segment));

        return $type !== null && ! $type->isLabel() && $type !== self::StockCount ? $type : null;
    }

    public function routeSegment(): string
    {
        return str_replace('_', '-', $this->value);
    }

    /** @return array<int, self> */
    public static function documents(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => ! $t->isLabel()));
    }

    /** @return array<int, self> */
    public static function labels(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => $t->isLabel()));
    }
}
