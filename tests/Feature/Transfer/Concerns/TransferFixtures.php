<?php

declare(strict_types=1);

namespace Tests\Feature\Transfer\Concerns;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Actions\CreateTransfer;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;

/**
 * Bahan uji modul Transfer & Retur: CKG (utama) dengan BKS sebagai cabangnya,
 * satu proyek dengan dua Gudang Site (KRW1, KRW2 — A-40, A-50), bin
 * penyimpanan di setiap gudang, dan stok awal Baut di CKG.
 */
trait TransferFixtures
{
    use ReceiptFixtures;

    protected Warehouse $bks;

    protected Warehouse $krw1;

    protected Warehouse $krw2;

    protected Project $proyek;

    protected Bin $binBks;

    protected Bin $binKrw1;

    protected Bin $binKrw2;

    protected function siapkanTransfer(): void
    {
        $this->siapkanPenerimaan();

        $this->bks = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS',
            'name' => 'Gudang Cabang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::BRANCH)->value('id'),
            'parent_id' => $this->gudang->id,
        ]);

        $this->proyek = $this->makeProject();
        $this->krw1 = $this->buatGudangSite('KRW1', $this->proyek);
        $this->krw2 = $this->buatGudangSite('KRW2', $this->proyek);

        $this->binBks = Bin::create(['warehouse_id' => $this->bks->id, 'code' => 'BKS-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $this->binKrw1 = Bin::create(['warehouse_id' => $this->krw1->id, 'code' => 'KRW1-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $this->binKrw2 = Bin::create(['warehouse_id' => $this->krw2->id, 'code' => 'KRW2-A-R01-L1-B01', 'bin_type' => BinType::Storage]);

        $this->stok($this->binA, $this->baut, 100);
    }

    protected function buatGudangSite(string $kode, Project $proyek): Warehouse
    {
        return app(SaveWarehouse::class)->handle(null, [
            'code' => $kode,
            'name' => 'Gudang Site '.$kode,
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::SITE)->value('id'),
            'project_id' => $proyek->id,
        ]);
    }

    protected function stok(Bin $bin, Item $item, float $qty, array $turunan = []): void
    {
        app(StockLedger::class)->post(new MovementRequest(
            item: $item,
            qtyBase: $qty,
            toBinId: $bin->id,
            lotId: $turunan['lot_id'] ?? null,
            serialId: $turunan['serial_id'] ?? null,
            pieceId: $turunan['piece_id'] ?? null,
        ));
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    protected function trf(Warehouse $asal, Warehouse $tujuan, array $lines, ?User $actor = null): Transfer
    {
        return app(CreateTransfer::class)->handle(
            ['from_warehouse_id' => $asal->id, 'to_warehouse_id' => $tujuan->id, 'notes' => 'Uji'],
            $lines,
            $actor ?? $this->makeUser('warehouse_staff'),
        );
    }

    /** Menjalankan PCK sampai selesai dengan jumlah penuh. */
    protected function jalankanPck(PickTask $pck): PickTask
    {
        $staf = $this->makeUser('warehouse_staff');
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        return app(ProcessPickTask::class)->complete($pck->refresh(), $staf);
    }

    /** @param  array<string, mixed>  $data */
    protected function sjDari(PickTask $pck, Warehouse $tujuan, array $data = []): Shipment
    {
        $sj = app(CreateShipment::class)->handle([$pck->id], $data + [
            'destination_type' => $tujuan->isSite() ? 'site_warehouse' : 'warehouse',
            'destination_warehouse_id' => $tujuan->id,
            'destination_project_id' => $tujuan->project_id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'TR'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $this->makeUser('warehouse_staff'));

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
    }

    /** Bukti terima: semua baris baik, kecuali yang disebut. */
    protected function terimaSj(Shipment $sj, array $rusak = [], array $kurang = []): Shipment
    {
        $baris = [];

        foreach ($sj->lines()->get() as $l) {
            $r = (float) ($rusak[$l->id] ?? 0);
            $k = (float) ($kurang[$l->id] ?? 0);
            $baris[] = [
                'shipment_line_id' => $l->id,
                'qty_good' => (float) $l->qty_shipped - $r - $k,
                'qty_damaged' => $r,
                'qty_missing' => $k,
                'damage_photo_path' => $r > 0 ? 'uji/rusak.jpg' : null,
            ];
        }

        app(ConfirmDelivery::class)->handle($sj, ['received_by_name' => 'Penerima Uji'], $baris, $this->makeUser('driver'));

        return $sj->refresh();
    }

    protected function grnTransferSelesai(Shipment $sj, Warehouse $tujuan): GoodsReceipt
    {
        $staf = $this->makeUser('warehouse_staff');
        $grn = app(SaveGoodsReceipt::class)->handle(null, [
            'receipt_type' => 'transfer',
            'warehouse_id' => $tujuan->id,
            'shipment_id' => $sj->id,
        ], [], $staf);

        $grn = app(ReceiveGoodsReceipt::class)->handle($grn, $staf);

        return app(CompleteGoodsReceipt::class)->handle($grn, $staf);
    }

    protected function putSelesai(GoodsReceipt $grn, Bin $bin): void
    {
        $put = $grn->putawayTasks()->sole();
        $isian = [];

        foreach ($put->lines as $l) {
            $isian[$l->id] = ['bin_id' => $bin->id, 'override_reason' => (int) $l->suggested_bin_id === (int) $bin->id ? null : 'Uji'];
        }

        app(CompletePutaway::class)->handle($put, $isian, $this->makeUser('warehouse_staff'));
    }

    protected function pckTrf(Transfer $trf): PickTask
    {
        return $trf->pickTasks()->where('status', '!=', 'cancelled')->latest('id')->firstOrFail();
    }
}
