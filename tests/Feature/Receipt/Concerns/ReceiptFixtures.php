<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt\Concerns;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\RecordQcResult;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;

/**
 * Bahan uji modul Receipt/Putaway: satu gudang dengan bin sistem dan dua bin
 * penyimpanan, satu vendor, dan item untuk tiap mode pelacakan.
 */
trait ReceiptFixtures
{
    protected Warehouse $gudang;

    protected Bin $binA;

    protected Bin $binB;

    protected Vendor $vendor;

    protected Item $baut;

    protected Item $semen;

    protected Item $genset;

    protected Item $pipa;

    protected Item $kabel;

    protected function siapkanPenerimaan(): void
    {
        $this->gudang = $this->buatGudang('CKG', 'Gudang Utama Cakung');

        $this->binA = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $this->binB = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B02', 'bin_type' => BinType::Storage]);

        $this->vendor = Vendor::create([
            'code' => 'V-BAJA', 'name' => 'PT Baja Jaya',
            'vendor_type' => VendorType::Company, 'status' => VendorStatus::Active, 'is_active' => true,
        ]);

        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        $m = Uom::query()->where('code', 'M')->value('id');

        $this->baut = $this->buatItem('BAUT-M12', TrackingMode::None, $pcs);
        $this->kabel = $this->buatItem('KABEL-NYM', TrackingMode::None, $pcs, ['requires_qc' => true]);
        $this->semen = $this->buatItem('SEMEN-PCC', TrackingMode::Lot, $pcs, ['has_expiry' => true, 'requires_qc' => true]);
        $this->genset = $this->buatItem('GENSET-5K', TrackingMode::Serial, $pcs, ['ownership_model' => OwnershipModel::Asset]);
        $this->pipa = $this->buatItem('PIPA-PVC', TrackingMode::Piece, $m);
    }

    protected function buatGudang(string $kode, string $nama): Warehouse
    {
        return app(SaveWarehouse::class)->handle(null, [
            'code' => $kode,
            'name' => $nama,
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    protected function buatItem(string $kode, TrackingMode $mode, int|string|null $uom, array $extra = []): Item
    {
        return Item::create(array_merge([
            'code' => $kode,
            'name' => $kode,
            'status' => ItemStatus::Active,
            'tracking_mode' => $mode,
            'ownership_model' => OwnershipModel::Consumable,
            'base_uom_id' => $uom,
        ], $extra));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $header
     */
    protected function grnDraf(array $lines, array $header = [], ?User $actor = null): GoodsReceipt
    {
        return app(SaveGoodsReceipt::class)->handle(null, array_merge([
            'receipt_type' => 'vendor',
            'warehouse_id' => $this->gudang->id,
            'vendor_id' => $this->vendor->id,
            'vendor_doc_no' => 'SJV-001',
        ], $header), $lines, $actor ?? $this->makeUser('warehouse_staff'));
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    protected function grnDiterima(array $lines): GoodsReceipt
    {
        return app(ReceiveGoodsReceipt::class)->handle($this->grnDraf($lines), $this->makeUser('warehouse_staff'));
    }

    protected function qc(GoodsReceipt $grn, int $index, QcResult $hasil, bool $denganAlasan = true): void
    {
        $line = $grn->lines()->orderBy('id')->get()[$index];

        app(RecordQcResult::class)->handle(
            $line,
            $hasil,
            $denganAlasan ? $this->alasan(ReasonContext::Reject) : null,
            null,
            $this->makeUser('warehouse_staff'),
        );
    }

    protected function alasan(ReasonContext $context): int
    {
        return (int) ReasonCode::query()->where('context', $context->value)->value('id');
    }

    protected function saldo(Bin|int $bin, Item $item, StockStatus $status = StockStatus::Available): float
    {
        return round((float) StockBalance::query()->withoutGlobalScopes()
            ->where('bin_id', $bin instanceof Bin ? $bin->id : $bin)
            ->where('item_id', $item->id)
            ->where('stock_status', $status->value)
            ->sum('qty_base'), 4);
    }

    protected function binSistem(Warehouse $gudang, BinType $type): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudang->id)->where('bin_type', $type->value)->firstOrFail();
    }
}
