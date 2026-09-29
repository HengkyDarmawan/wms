<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\Uom;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Template\Actions\PrintLabels;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Warehouse\Actions\MergeBins;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Support\BinCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-61, TC-WH-62 — Tata letak gudang Bagian 4: halaman **Isi Bin**
 * (K-G, A-374) lewat tautan QR, izin & cakupan; QR label bin berisi tautan dan
 * kode pendek besar (keputusan #7, A-373, A-379); pemindai mengenali tautan.
 */
class IsiBinTest extends TenantTestCase
{
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        $zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        app(SaveWarehouseLayout::class)->newRack($zona, ['code' => 'R01', 'levels' => '1', 'bins_per_level' => '3']);
    }

    private function b(string $petak): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R01-L1-'.$petak)->firstOrFail();
    }

    private function kepala()
    {
        $u = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $u->forgetPermissionCache();

        return $u;
    }

    #[Test]
    public function tc_wh_61_halaman_isi_bin_izin_cakupan_dan_isi(): void
    {
        $bin = $this->b('B01');
        $dus = Uom::query()->firstOrCreate(['code' => 'DUS'], ['name' => 'Dus']);
        ItemUomConversion::create(['item_id' => $this->baut->id, 'uom_id' => $dus->id, 'qty_base' => 12, 'is_active' => true]);
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut->refresh(), qtyBase: 30, toBinId: $bin->id));
        app(SaveItemStorageLocations::class)->replace($this->baut, $this->gudang, [['tempat' => 'bin:'.$bin->id, 'khusus' => true]], $this->kepala());

        $halaman = $this->actingAs($this->makeUser())->get($this->tenantUrl('bins/'.$bin->code));
        $halaman->assertOk()
            ->assertSee('R01 · L1 · 01')
            ->assertSee('CKG-D-R01-L1-B01')
            ->assertSee('Khusus BAUT-M12')
            ->assertSee('30 PCS')
            ->assertSee('2 DUS 6 PCS')
            ->assertSee('Masuk terakhir')
            ->assertDontSee('Rp');

        // Tautan QR (huruf kecil / ber-URL-encode) tetap dikenali.
        $this->actingAs($this->makeUser())->get($this->tenantUrl('bins/ckg-d-r01-l1-b01'))->assertOk();

        // Izin & cakupan: tanpa bin.view 403; gudang lain = tidak ditemukan.
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('bins/'.$bin->code))->assertForbidden();
        $lain = $this->buatGudang('SBY', 'Gudang Surabaya');
        $stafLain = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $lain->id);
        $stafLain->forgetPermissionCache();
        $this->actingAs($stafLain)->get($this->tenantUrl('bins/'.$bin->code))->assertNotFound();
        $this->actingAs($this->makeUser())->get($this->tenantUrl('bins/TIDAK-ADA'))->assertNotFound();

        // Bin gabungan: bin tergabung menunjuk bin utama dan menampilkan isinya.
        app(MergeBins::class)->merge($bin, [$this->b('B02')->id], 'side', 'permanent', 'Palet panjang', $this->kepala());
        $this->actingAs($this->makeUser())->get($this->tenantUrl('bins/'.$this->b('B02')->code))->assertOk()
            ->assertSee('Bin ini digabung ke bin utama')
            ->assertSee('30 PCS');
        $this->actingAs($this->makeUser())->get($this->tenantUrl('bins/'.$bin->code))->assertOk()->assertSee('Bin gabungan');
    }

    #[Test]
    public function tc_wh_62_label_bin_berisi_tautan_dan_pemindai_mengenalinya(): void
    {
        $bin = $this->b('B03');
        $tautan = BinCode::tautan($bin->code);
        $this->assertStringEndsWith('/bins/CKG-D-R01-L1-B03', $tautan);

        $this->assertSame('CKG-D-R01-L1-B03', BinCode::dariPindai($tautan));
        $this->assertSame('CKG-D-R01-L1-B03', BinCode::dariPindai(' ckg-d-r01-l1-b03 '));
        $bins = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $this->gudang->id)->get();
        $this->assertSame((int) $bin->id, (int) BinCode::cocokkan($tautan, $bins)?->id);
        $this->assertSame((int) $bin->id, (int) BinCode::cocokkan('R01 · L1 · 03', $bins)?->id);
        $this->assertNull(BinCode::cocokkan('R01 L1 01', $bins), 'Kode pendek kembar (bin fixture zona A) = ambigu.');

        $html = app(PrintLabels::class)->view(DocumentTemplateType::LabelBin, [$bin->id],
            LabelFormat::defaultFor(DocumentTemplateType::LabelBin), 1, $this->kepala())->render();
        $this->assertStringContainsString('R01 · L1 · 03', $html, 'Kode pendek besar di label.');
        $this->assertStringContainsString('CKG-D-R01-L1-B03', $html);
    }
}
