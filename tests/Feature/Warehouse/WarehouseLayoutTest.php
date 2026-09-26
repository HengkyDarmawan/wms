<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Master\Models\Lot;
use App\Domain\Receipt\Support\PutawaySuggester;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Template\Support\LabelPayload;
use App\Domain\Warehouse\Actions\MarkBinsOccupied;
use App\Domain\Warehouse\Actions\SaveBin;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Livewire\BinList;
use App\Domain\Warehouse\Livewire\WarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-21 s.d. TC-WH-25 — denah gudang 2D (A-254), rak area & bin ikut
 * terpakai untuk barang besar (A-255), tanggal masuk & FIFO (A-256).
 */
class WarehouseLayoutTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Zone $zona;

    private Rack $rak;

    /** @var array<int, Bin> */
    private array $bin = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();

        $lokasi = app(SaveLocation::class);
        $this->zona = $lokasi->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Denah']);
        $this->rak = $lokasi->saveRack($this->zona, null, ['code' => 'R01']);
        $level = $lokasi->saveLevel($this->rak, null, ['code' => 'L1']);

        foreach (['B01', 'B02', 'B03'] as $kode) {
            $this->bin[] = app(SaveBin::class)->handle($this->gudang, null, ['rack_level_id' => $level->id, 'code' => $kode]);
        }
    }

    private function masuk(Bin $bin, $item, float $qty, array $x = []): void
    {
        app(StockLedger::class)->post(new MovementRequest(item: $item, qtyBase: $qty, toBinId: $bin->id, lotId: $x['lot_id'] ?? null, occurredAt: $x['at'] ?? null));
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (WarehouseRuleException|LedgerException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_wh_21_rak_area_satu_bin_untuk_alat_berat(): void
    {
        $this->zona->forceFill(['length_m' => 12, 'width_m' => 6])->save();

        $area = app(SaveWarehouseLayout::class)->areaRack($this->zona, ['code' => 'AB1', 'name' => 'Parkir excavator', 'seluruh_zona' => '1']);
        $rak = $area->rackLevel->rack;

        $this->assertSame('CKG-D-AB1-L1-AREA', $area->code);
        $this->assertTrue($rak->is_area);
        $this->assertSame(12.0, (float) $rak->length_m, 'Seluruh zona: ukuran = zona.');
        $this->assertSame('block', $area->capacity_mode);
        $this->assertSame(1.0, (float) $area->capacity_qty);

        $this->masuk($area, $this->baut, 1);
        // Isi kedua (item lain) ditolak: kapasitas dihitung dari total isi bin.
        $this->gagal(fn () => $this->masuk($area, $this->kabel, 1), 'BR-WH-06');
        $this->assertNotSame((int) $area->id, (int) app(PutawaySuggester::class)->suggest($this->kabel, $this->gudang, 1)?->id);
    }

    #[Test]
    public function tc_wh_22_bin_ikut_terpakai_dan_lepas_otomatis(): void
    {
        [$utama, $b2, $b3] = $this->bin;
        $aksi = app(MarkBinsOccupied::class);

        $this->gagal(fn () => $aksi->handle($utama, [$b2->id], 'genset besar'), 'BR-WH-06'); // bin utama masih kosong
        $this->masuk($utama, $this->baut, 5);
        $this->masuk($b3, $this->kabel, 1);

        $this->gagal(fn () => $aksi->handle($utama, [$b2->id], ''), 'BR-GEN-11');
        $this->gagal(fn () => $aksi->handle($utama, [$b3->id], 'besar'), 'BR-WH-06'); // tetangga berisi

        $this->assertSame(1, $aksi->handle($utama, [$b2->id], 'Barang besar memakan 2 bin'));
        $this->assertSame((int) $utama->id, (int) $b2->refresh()->occupied_by_bin_id);

        $saran = app(PutawaySuggester::class)->storageBins($this->gudang)->pluck('id');
        $this->assertNotContains((int) $b2->id, $saran->all(), 'Bin ikut terpakai tidak disarankan.');

        // Barang keluar sampai bin utama kosong → penanda lepas otomatis.
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 5, fromBinId: $utama->id));
        $this->assertNull($b2->refresh()->occupied_by_bin_id);
    }

    #[Test]
    public function tc_wh_23_ukuran_posisi_opsional_dan_geser_grid(): void
    {
        $data = app(WarehouseLayoutData::class)->build($this->gudang);
        $rak = collect($data['zones'])->firstWhere('code', 'D')['racks'][0];
        $this->assertTrue($rak['otomatis'], 'Tanpa posisi: ditata otomatis.');
        $this->assertSame(0.5, $rak['x']);

        $layout = app(SaveWarehouseLayout::class);
        $this->gagal(fn () => $layout->zone($this->zona, ['length_m' => '-3']), 'BR-GEN-11');
        $layout->zone($this->zona, ['length_m' => '10', 'width_m' => '5']);
        $layout->rack($this->rak, ['length_m' => '3', 'width_m' => '1', 'orientation' => 'h']);

        $rak = $layout->moveRack($this->rak->refresh(), 2.26, 1.74);
        $this->assertSame([2.5, 1.5], [(float) $rak->pos_x, (float) $rak->pos_y], 'Snap 0,5 m.');
        $rak = $layout->moveRack($rak, 50, 50);
        $this->assertSame([7.0, 4.0], [(float) $rak->pos_x, (float) $rak->pos_y], 'Tetap di dalam zona 10 × 5 m.');

        $this->assertFalse(collect(app(WarehouseLayoutData::class)->build($this->gudang)['zones'])->firstWhere('code', 'D')['racks'][0]['otomatis']);
    }

    #[Test]
    public function tc_wh_24_isi_bin_tanggal_masuk_tertua_dan_cari(): void
    {
        [$b1, $b2] = $this->bin;
        $this->masuk($b1, $this->baut, 10, ['at' => now()->subDays(40)]);
        $this->masuk($b2, $this->baut, 3, ['at' => now()->subDays(2)]);

        $data = app(WarehouseLayoutData::class)->build($this->gudang, 'baut-m12');
        $rak = collect($data['zones'])->firstWhere('code', 'D')['racks'][0];
        $bins = collect($rak['levels'][0]['bins'])->keyBy('code');

        $this->assertTrue($bins[$b1->code]['isi'][0]['tertua'], 'Masuk paling lama → ambil dulu (FIFO).');
        $this->assertFalse($bins[$b2->code]['isi'][0]['tertua']);
        $this->assertSame(40, $bins[$b1->code]['isi'][0]['umur']);
        $this->assertTrue($rak['cocok']);
        $this->assertCount(2, $data['hasil']);
        $this->assertSame('terisi', $rak['status']);

        // Label lot mencantumkan tanggal masuk (A-256).
        $lot = Lot::create(['item_id' => $this->baut->id, 'lot_no' => 'LOT-FIFO', 'received_at' => '2026-09-01']);
        $this->assertStringContainsString('Masuk 01/09/2026', LabelPayload::lot($lot)['detail']);
    }

    #[Test]
    public function tc_wh_25_layar_denah_dan_daftar_bin(): void
    {
        [$b1] = $this->bin;
        $this->masuk($b1, $this->baut, 4);

        $kepala = $this->makeUser('warehouse_head');
        $this->assertTrue($kepala->hasPermission('bin.manage'));

        $this->actingAs($kepala)->get($this->tenantUrl('warehouses/'.$this->gudang->id))->assertOk()->assertSee(route('warehouses.layout', $this->gudang));
        $this->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()
            ->assertSee(__('Denah gudang'))->assertSee('R01')->assertSee(__('Atur denah'));

        Livewire::test(WarehouseLayout::class, ['warehouse' => $this->gudang])
            ->call('pilihRak', $this->rak->id)
            ->assertSee($b1->code)->assertSee('BAUT-M12')->assertSee(__('tertua — ambil dulu'))
            ->call('aturEdit', true)
            ->call('pindahRak', $this->rak->id, 1.2, 0.9)
            ->set('formArea.zone_id', (string) $this->zona->id)->set('formArea.code', 'AX')
            ->call('buatArea')->assertHasNoErrors();
        $this->assertSame([1.0, 1.0], [(float) $this->rak->refresh()->pos_x, (float) $this->rak->pos_y]);
        $this->assertTrue(Rack::query()->where('code', 'AX')->sole()->is_area);

        // Tanpa bin.manage: boleh melihat, tidak boleh mengatur.
        $staf = $this->makeUser('warehouse_staff');
        $this->assertFalse($staf->hasPermission('bin.manage'));
        $this->actingAs($staf)->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()->assertDontSee(__('Atur denah'));
        Livewire::test(WarehouseLayout::class, ['warehouse' => $this->gudang])->call('pindahRak', $this->rak->id, 3, 3)->assertForbidden();

        // Daftar bin: kolom zona · rak · level, saring rak, ubah kapasitas.
        $this->actingAs($kepala);
        Livewire::test(BinList::class)
            ->set('rackFilter', 'r01')->assertSee($b1->code)->assertSee('D · R01 · L1')
            ->call('mintaUbah', $b1->id)
            ->set('formBin.capacity_qty', '20')->set('formBin.capacity_mode', 'block')
            ->call('simpanBin')->assertHasNoErrors();
        $this->assertSame(20.0, (float) $b1->refresh()->capacity_qty);
        $this->assertSame('block', $b1->capacity_mode);
    }
}
