<?php

declare(strict_types=1);

namespace Tests\Feature\Return;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Serial;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Return\Actions\CancelGoodsReturn;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Return\Livewire\ReturnDetail;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickupShipment;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\ShipmentLineOrigins;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Support\DocumentPrinter;
use App\Domain\Warehouse\Actions\EnsureSystemBins;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-RET-18 s.d. TC-RET-21, TC-SJ-19 — SJ jemput tanpa PCK untuk barang di
 * tangan klien dan aset On-site (A-247, A-248; mengubah A-111).
 */
class ReturnPickupTest extends TenantTestCase
{
    use ReturnFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    private function pembawa(array $x = []): array
    {
        return $x + ['shipment_method' => 'own_fleet', 'vehicle_plate' => 'b 9123 zz', 'carried_by_name' => 'Pak Udin (sewa)'];
    }

    private function sjJemput(GoodsReturn $ret, array $data = []): Shipment
    {
        return app(CreatePickupShipment::class)->forGoodsReturn($ret->refresh(), $this->pembawa($data), $this->makeUser('warehouse_staff'));
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ReturnRuleException|ShipmentRuleException|ReceiptRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_ret_18_barang_di_tangan_klien_dijemput_tanpa_pck(): void
    {
        $kirim = $this->terimaSj($this->terkirimKeKlien($this->baut, 10));
        $retur = $this->binSistem($this->gudang, BinType::Return);
        $gerakSebelum = StockMovement::query()->count();

        $ret = $this->ret([['key' => 'sold:'.$kirim->lines()->first()->id, 'qty_base' => 3]], ['self_delivered' => false]);

        $this->assertTrue($ret->isPickup());
        $this->assertSame(GoodsReturnStatus::Approved, $ret->status, 'Tanpa aturan: disetujui, menunggu SJ jemput.');
        $this->assertNull($ret->livePickTask(), 'A-248: tanpa tugas picking.');

        // Sopir & plat wajib tertulis (A-247).
        $this->gagal(fn () => $this->sjJemput($ret, ['vehicle_plate' => '']), 'BR-SJ-07');
        $this->gagal(fn () => $this->sjJemput($ret, ['carried_by_name' => '']), 'BR-SJ-07');
        $this->gagal(fn () => $this->sjJemput($ret, ['shipment_method' => 'self_delivered']), 'BR-SJ-07');

        $sj = $this->sjJemput($ret);
        $this->assertTrue($sj->isReturnPickup());
        $this->assertSame((int) $this->proyek->id, (int) $sj->origin_project_id);
        $this->assertSame((int) $this->gudang->id, (int) $sj->destination_warehouse_id);
        $this->assertSame('B 9123 ZZ', $sj->vehicle_plate);
        $this->assertStringContainsString('Pak Udin', $sj->carrierLabel());
        $baris = $sj->lines()->sole();
        $this->assertNull($baris->pick_task_line_id);
        $this->assertSame((int) $ret->lines()->sole()->id, (int) $baris->source_line_id);
        $this->assertSame((int) $this->baut->id, (int) $baris->item_id);

        $ret->refresh();
        $this->assertSame(GoodsReturnStatus::InProgress, $ret->status, 'Katalog §2.8: SJ balik disusun → diproses.');
        $this->assertSame((int) $sj->id, (int) $ret->return_shipment_id);
        $this->gagal(fn () => $this->sjJemput($ret), 'BR-RET-03');

        // Berangkat tanpa pergerakan stok: barang klien di luar kartu stok.
        $sj = app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
        $this->assertSame(ShipmentStatus::Shipped, $sj->status);
        $this->assertSame($gerakSebelum, StockMovement::query()->count());
        $this->gagal(fn () => $this->grnRetur($ret), 'BR-SJ-04');

        // Tiba: 2 baik, 1 rusak — keduanya tiba, tanpa DSC dan tanpa pergerakan.
        $this->gagal(fn () => app(ConfirmDelivery::class)->handle($sj, ['received_by_name' => 'Staf CKG'], [
            ['shipment_line_id' => $baris->id, 'qty_good' => 2, 'qty_damaged' => 1, 'qty_missing' => 0],
        ], $this->makeUser('warehouse_staff')), 'BR-SJ-05'); // foto rusak wajib
        app(ConfirmDelivery::class)->handle($sj, ['received_by_name' => 'Staf CKG'], [
            ['shipment_line_id' => $baris->id, 'qty_good' => 2, 'qty_damaged' => 1, 'qty_missing' => 0, 'damage_photo_path' => 'uji/rusak.jpg'],
        ], $this->makeUser('warehouse_staff'));

        $sj->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $sj->status);
        $this->assertSame(0, $sj->discrepancies()->count(), 'A-248: SJ jemput tanpa DSC.');
        $this->assertSame(3.0, (float) $baris->refresh()->qty_delivered, 'Yang tiba = baik + rusak.');
        $this->assertNull($sj->proof->confirm_deadline_at, 'Tidak menunggu konfirmasi pemohon.');
        $this->assertSame($gerakSebelum, StockMovement::query()->count());

        // GRN retur dari luar kartu stok ke bin Retur (A-112), bukan dari Dalam Perjalanan.
        $grn = $this->grnRetur($ret->refresh());
        $this->assertSame((int) $sj->id, (int) $grn->shipment_id);
        $this->assertSame(3.0, $this->saldo($retur, $this->baut));
        $this->assertSame(0.0, $this->saldo($this->binSistem($this->gudang, BinType::InTransit), $this->baut));

        $ret = $this->pilah($ret, [$ret->lines()->sole()->id => [
            ['sorting' => 'good', 'qty' => 2, 'target_bin_id' => $this->binB->id],
            ['sorting' => 'damaged', 'qty' => 1, 'reason_code_id' => $this->alasan(ReasonContext::Damage)],
        ]]);
        $this->assertSame(GoodsReturnStatus::Sorted, $ret->status);
        $this->assertSame(1.0, $this->saldo($retur, $this->baut, StockStatus::Damaged));
    }

    #[Test]
    public function tc_ret_19_jemput_dan_stok_site_tidak_dicampur(): void
    {
        $this->stok($this->binKrw1, $this->baut, 5);
        $kirim = $this->terimaSj($this->terkirimKeKlien($this->baut, 10));
        $site = ['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 2];
        $jual = ['key' => 'sold:'.$kirim->lines()->first()->id, 'qty_base' => 2];

        $this->gagal(fn () => $this->ret([$site, $jual], ['self_delivered' => false]), 'BR-RET-03');

        // Stok Gudang Site tetap lewat PCK (A-111), bukan SJ jemput.
        $lewatPck = $this->ret([$site], ['self_delivered' => false]);
        $this->assertFalse($lewatPck->isPickup());
        $this->assertNotNull($lewatPck->livePickTask());
        $this->gagal(fn () => $this->sjJemput($lewatPck), 'BR-RET-03');

        // Diantar sendiri tetap tanpa SJ.
        $sendiri = $this->ret([$jual]);
        $this->assertFalse($sendiri->isPickup());
        $this->assertSame(GoodsReturnStatus::InProgress, $sendiri->status);
    }

    #[Test]
    public function tc_ret_20_batal_rret_jemput_sebelum_berangkat_dan_sj_dibatalkan(): void
    {
        $kirim = $this->terimaSj($this->terkirimKeKlien($this->baut, 10));
        $kunci = 'sold:'.$kirim->lines()->first()->id;
        $kepala = $this->makeUser('warehouse_head');

        // SJ jemput dibatalkan dari layar SJ → RET kembali menunggu SJ jemput.
        $ret = $this->ret([['key' => $kunci, 'qty_base' => 1]], ['self_delivered' => false]);
        $sj = $this->sjJemput($ret);
        app(ShipShipment::class)->cancel($sj, $this->alasan(ReasonContext::Cancel), $kepala);
        $ret->refresh();
        $this->assertSame(GoodsReturnStatus::Approved, $ret->status);
        $this->assertNull($ret->return_shipment_id);
        $sj2 = $this->sjJemput($ret);

        // RET batal selama SJ jemput belum berangkat; SJ-nya ikut batal.
        $this->assertTrue($ret->refresh()->canBeCancelled());
        app(CancelGoodsReturn::class)->handle($ret, $this->alasan(ReasonContext::Cancel), null, $kepala);
        $this->assertSame(GoodsReturnStatus::Cancelled, $ret->refresh()->status);
        $this->assertSame(ShipmentStatus::Cancelled, $sj2->refresh()->status);

        // Setelah berangkat tidak bisa dibatalkan.
        $lain = $this->ret([['key' => $kunci, 'qty_base' => 1]], ['self_delivered' => false]);
        app(ShipShipment::class)->handle($this->sjJemput($lain), null, $this->makeUser('driver'));
        $this->assertFalse($lain->refresh()->canBeCancelled());
        $this->gagal(fn () => app(CancelGoodsReturn::class)->handle($lain, $this->alasan(ReasonContext::Cancel), null, $kepala), 'BR-GEN-03');
    }

    #[Test]
    public function tc_ret_21_layar_detail_ret_membuat_sj_jemput(): void
    {
        $kirim = $this->terimaSj($this->terkirimKeKlien($this->baut, 10));
        $ret = $this->ret([['key' => 'sold:'.$kirim->lines()->first()->id, 'qty_base' => 2]], ['self_delivered' => false]);
        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($staf)->get($this->tenantUrl('returns/'.$ret->id))->assertOk()
            ->assertSee(__('Buat SJ jemput'))->assertSee(__('Dijemput driver'));

        // Pemohon tanpa `shipment.create` tidak melihat form jemput.
        $this->actingAs($this->makeUser('internal_requester'))->get($this->tenantUrl('returns/'.$ret->id))->assertOk()
            ->assertDontSee(__('Buat SJ jemput'));

        $this->actingAs($staf);
        Livewire::test(ReturnDetail::class, ['goodsReturn' => $ret])
            ->set('jemput.vehicle_plate', '')
            ->set('jemput.carried_by_name', 'Sopir Sewa')
            ->call('buatSjJemput')
            ->assertHasErrors('jemput.vehicle_plate')
            ->set('jemput.vehicle_plate', 'B 1 ABC')
            ->call('buatSjJemput')
            ->assertRedirect();

        $sj = Shipment::query()->where('source_type', 'goods_return')->where('source_id', $ret->id)->sole();
        $this->get($this->tenantUrl('shipments/'.$sj->id))->assertOk()
            ->assertSee(__('Dijemput dari'))->assertSee('B 1 ABC')->assertSee($kirim->number);
    }

    #[Test]
    public function tc_sj_19_aset_on_site_dijemput_dan_asal_baris_tercetak(): void
    {
        app(EnsureSystemBins::class)->onSiteBin($this->krw1, $this->proyek);
        $serial = Serial::create(['item_id' => $this->genset->id, 'serial_no' => 'GNS-JMP-1']);
        $this->stok($this->binB, $this->genset, 1, ['serial_id' => $serial->id]);
        $this->terimaSj($this->terkirimKeKlien($this->genset, 1, ['line_ownership' => 'loan']));
        $kirimBaut = $this->terimaSj($this->terkirimKeKlien($this->baut, 4));

        $onSite = Bin::query()->withoutGlobalScopes()->where('bin_type', BinType::OnSite->value)->where('project_id', $this->proyek->id)->sole();

        // Satu RET, dua asal (aset On-site + barang dari REQ lain) → satu SJ jemput.
        $ret = $this->ret([
            ['key' => implode(':', ['asset', $onSite->id, $this->genset->id, $serial->id]), 'qty_base' => 1],
            ['key' => 'sold:'.$kirimBaut->lines()->first()->id, 'qty_base' => 4],
        ], ['self_delivered' => false]);
        $sj = app(ShipShipment::class)->handle($this->sjJemput($ret), null, $this->makeUser('driver'));

        $this->assertSame(1.0, $this->saldo($onSite, $this->genset), 'Aset tetap di On-site sampai GRN retur.');

        $lines = $sj->lines()->with('pickTaskLine.pickTask')->orderBy('id')->get();
        $asal = app(ShipmentLineOrigins::class)->for($sj, $lines);
        $this->assertStringStartsWith('Aset, AST/', $asal[$lines[0]->id]);
        $this->assertStringContainsString($kirimBaut->number, $asal[$lines[1]->id]);
        $this->assertStringContainsString('REQ/', $asal[$lines[1]->id], 'Asal REQ ikut tercetak per baris.');

        $cetak = app(DocumentPrinter::class)->view(DocumentTemplateType::Shipment, $sj)->render();
        $this->assertStringContainsString('Dijemput dari', $cetak);
        $this->assertStringContainsString($ret->number, $cetak, 'Rujukan RET di kepala SJ jemput.');
        $this->assertStringContainsString($kirimBaut->number, $cetak, 'Kolom asal per baris.');
        $this->assertStringContainsString('B 9123 ZZ', $cetak, 'Plat penjemput tercetak.');

        // Unit serial dinilai utuh saat tiba (A-244).
        app(ConfirmDelivery::class)->handle($sj, ['received_by_name' => 'Staf CKG'], [
            ['shipment_line_id' => $lines[0]->id, 'qty_good' => 1],
            ['shipment_line_id' => $lines[1]->id, 'qty_good' => 3, 'qty_missing' => 1],
        ], $this->makeUser('warehouse_staff'));
        $this->assertSame(ShipmentStatus::PartiallyDelivered, $sj->refresh()->status, 'Ada yang tidak terbawa.');

        $grn = $this->grnRetur($ret->refresh());
        $this->assertSame(0.0, $this->saldo($onSite, $this->genset), 'GRN retur memindahkan aset dari On-site.');
        $this->assertSame([1.0, 3.0], $grn->lines()->orderBy('id')->pluck('qty_received')->map(fn ($q) => (float) $q)->all());
    }
}
