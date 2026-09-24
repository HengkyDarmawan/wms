<?php

declare(strict_types=1);

namespace Tests\Feature\Return\Concerns;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Actions\CreateGoodsReturn;
use App\Domain\Return\Actions\SortGoodsReturn;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Support\ReturnableStock;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Warehouse\Models\Bin;
use Tests\Feature\Transfer\Concerns\TransferFixtures;

/**
 * Bahan uji retur dari proyek: memakai gudang & proyek TransferFixtures
 * (CKG, BKS, proyek dengan Gudang Site KRW1/KRW2) dan rantai keluar sungguhan
 * untuk barang yang sudah terkirim ke klien.
 */
trait ReturnFixtures
{
    use TransferFixtures;

    protected function kunciSite(Bin $bin, Item $item, array $turunan = []): string
    {
        return implode(':', ['site', $bin->id, $item->id, $turunan['lot_id'] ?? 0, $turunan['serial_id'] ?? 0, $turunan['piece_id'] ?? 0]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  key, qty_base
     * @param  array<string, mixed>  $header
     */
    protected function ret(array $lines, array $header = [], ?User $actor = null): GoodsReturn
    {
        return app(CreateGoodsReturn::class)->handle($header + [
            'project_id' => $this->proyek->id,
            'to_warehouse_id' => $this->gudang->id,
            'self_delivered' => true,
            'notes' => 'Retur uji',
        ], $lines, $actor ?? $this->makeUser('internal_requester'));
    }

    /** @return \Illuminate\Support\Collection<string, array<string, mixed>> */
    protected function calon(): \Illuminate\Support\Collection
    {
        return app(ReturnableStock::class)->forProject($this->proyek);
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    protected function grnRetur(GoodsReturn $ret, array $lines = [], ?User $actor = null): GoodsReceipt
    {
        $staf = $actor ?? $this->makeUser('warehouse_staff');

        $grn = app(SaveGoodsReceipt::class)->handle(null, [
            'receipt_type' => 'return',
            'warehouse_id' => $ret->to_warehouse_id,
            'goods_return_id' => $ret->id,
        ], $lines, $staf);

        return app(ReceiveGoodsReceipt::class)->handle($grn, $staf);
    }

    /** @param  array<int|string, array<int, array<string, mixed>>>  $portions */
    protected function pilah(GoodsReturn $ret, array $portions, ?User $actor = null): GoodsReturn
    {
        return app(SortGoodsReturn::class)->handle($ret->refresh(), $portions, null, $actor ?? $this->makeUser('warehouse_staff'));
    }

    /**
     * REQ stok dari CKG yang dikirim dan diterima baik oleh proyek (jual putus
     * atau pinjam), sehingga barangnya bisa diretur.
     *
     * @param  array<string, mixed>  $extraLine
     */
    protected function terkirimKeKlien(Item $item, float $qty, array $extraLine = [], string $metode = 'own_fleet'): Shipment
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $item->id, 'qty_base' => $qty] + $extraLine],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        /** @var MaterialRequest $req */
        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
        $pck = $this->jalankanPck(app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0]);

        $data = [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => $metode,
        ] + ($metode === 'carrier'
            ? ['carrier_id' => \App\Domain\Master\Models\Carrier::create(['name' => 'JNE Uji', 'is_active' => true])->id, 'tracking_no' => 'RESI-1']
            : ['vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'RT'])->id, 'driver_id' => $this->makeUser('driver')->id]);

        $sj = app(CreateShipment::class)->handle([$pck->id], $data, $this->makeUser('warehouse_staff'));

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
    }
}
