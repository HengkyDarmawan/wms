<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\DeactivateLocation;
use App\Domain\Warehouse\Actions\SaveBin;
use App\Domain\Warehouse\Actions\SaveFloorPlanObject;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\FloorPlanObjectType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Livewire\WarehouseLayout;
use App\Domain\Warehouse\Livewire\WarehouseList;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\FloorPlanObject;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-31–37 — denah gedung sesuai kenyataan (A-320–A-325): ukuran gedung,
 * posisi & ukuran zona, objek denah tanpa stok, tumpukan diperingatkan, kode
 * terkunci & nonaktif tanpa hapus, izin, panel rak per bin, dan mode Denah di
 * Daftar Gudang.
 */
class FloorPlanTest extends TenantTestCase
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

        foreach (['B01', 'B02'] as $kode) {
            $this->bin[] = app(SaveBin::class)->handle($this->gudang, null, ['rack_level_id' => $level->id, 'code' => $kode]);
        }
    }

    private function gagal(callable $aksi, string $aturan): WarehouseRuleException|LedgerException
    {
        try {
            $aksi();
        } catch (WarehouseRuleException|LedgerException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());

            return $e;
        }

        $this->fail('Seharusnya ditolak '.$aturan.'.');
    }

    /**
     * A-353: semua perubahan mode Atur dikirim sekali lewat "Simpan perubahan".
     *
     * @param  array<int, array<string, mixed>>  $ops
     * @return array<string, mixed>
     */
    private function simpan($layar, array $ops): array
    {
        return data_get($layar->call('simpanPerubahan', $ops)->effects, 'returns.0');
    }

    private function kepala()
    {
        $u = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $u->forgetPermissionCache();

        return $u;
    }

    #[Test]
    public function tc_wh_31_ukuran_gedung_posisi_dan_ukuran_zona_tersimpan(): void
    {
        $layar = Livewire::actingAs($this->kepala())->test(WarehouseLayout::class, ['warehouse' => $this->gudang]);
        $gagal = $this->simpan($layar, [['op' => 'gedung', 'data' => ['length_m' => '30', 'width_m' => '']]]);
        $this->assertFalse($gagal['ok']);
        $this->assertSame('gedung', $gagal['galat'][0]['objek']);

        $this->assertTrue($this->simpan($layar, [['op' => 'gedung', 'data' => ['length_m' => '30', 'width_m' => '18,5']]])['ok']);
        $this->assertSame([30.0, 18.5], [(float) $this->gudang->refresh()->length_m, (float) $this->gudang->width_m]);

        // Seret zona: snap 0,5 m dan dijepit di dalam gedung.
        $this->simpan($layar, [
            ['op' => 'ukuran', 'jenis' => 'zona', 'id' => $this->zona->id, 'p' => 10.2, 'l' => 6.1],
            ['op' => 'geser', 'jenis' => 'zona', 'id' => $this->zona->id, 'x' => 4.26, 'y' => 2.74],
        ]);
        $this->zona->refresh();
        $this->assertSame([10.0, 6.0, 4.5, 2.5], [(float) $this->zona->length_m, (float) $this->zona->width_m, (float) $this->zona->pos_x, (float) $this->zona->pos_y]);

        $this->simpan($layar, [['op' => 'geser', 'jenis' => 'zona', 'id' => $this->zona->id, 'x' => 99, 'y' => 99]]);
        $this->assertSame([20.0, 12.5], [(float) $this->zona->refresh()->pos_x, (float) $this->zona->pos_y], 'Tetap di dalam gedung 30 × 18,5 m.');

        // Isian zona juga menyimpan posisi; denah memakai koordinat gedung.
        $this->assertTrue($this->simpan($layar, [['op' => 'zona', 'id' => $this->zona->id, 'data' => ['name' => 'Denah', 'length_m' => 10, 'width_m' => 6, 'pos_x' => '2', 'pos_y' => '1']]])['ok']);

        $denah = app(WarehouseLayoutData::class)->build($this->gudang->refresh());
        $z = collect($denah['zones'])->firstWhere('code', 'D');
        $this->assertSame(['p' => 30.0, 'l' => 18.5], $denah['gedung']);
        $this->assertSame([2.0, 1.0, false], [$z['x'], $z['y'], $z['otomatis']]);
        $this->assertGreaterThanOrEqual(30.0, $denah['kanvas']['w']);
    }

    #[Test]
    public function tc_wh_32_objek_denah_tambah_geser_putar_nonaktif_tanpa_stok(): void
    {
        app(SaveWarehouseLayout::class)->building($this->gudang, ['length_m' => 20, 'width_m' => 10]);
        $gerakAwal = StockMovement::query()->count();

        $layar = Livewire::actingAs($this->kepala())->test(WarehouseLayout::class, ['warehouse' => $this->gudang]);

        // Tambah → geser → ubah ukuran dalam satu simpan; objek baru dirujuk id sementara.
        $this->assertTrue($this->simpan($layar, [
            ['op' => 'objek_baru', 'tmp' => 'b1', 'jenis' => 'dock', 'data' => ['pos_x' => 0.5, 'pos_y' => 0.5]],
            ['op' => 'geser', 'jenis' => 'obj', 'id' => 'b1', 'x' => 17.3, 'y' => 3.2],
            ['op' => 'ukuran', 'jenis' => 'obj', 'id' => 'b1', 'p' => 6, 'l' => 2],
        ])['ok']);

        $dock = FloorPlanObject::query()->sole();
        $this->assertSame(FloorPlanObjectType::Dock, $dock->object_type);
        $this->assertSame([16.0, 3.0, 6.0, 2.0], [(float) $dock->pos_x, (float) $dock->pos_y, (float) $dock->length_m, (float) $dock->width_m], 'Snap & dijepit di gedung 20 m.');
        $this->assertSame(1, Activity::query()->where('subject_id', $dock->id)->where('description', 'Objek denah digeser')->count(), 'Satu geser = satu baris riwayat.');

        // Putar 90°: tampak atas menukar panjang & lebar, posisi dijepit ulang.
        $this->simpan($layar, [['op' => 'putar', 'jenis' => 'obj', 'id' => $dock->id]]);
        $dock->refresh();
        $this->assertSame(90, $dock->rotation);
        $this->assertSame([2.0, 6.0], $dock->footprint());

        $this->assertFalse($this->simpan($layar, [['op' => 'objek', 'id' => $dock->id, 'data' => ['name' => '']]])['ok']);
        $this->assertTrue($this->simpan($layar, [['op' => 'objek', 'id' => $dock->id, 'data' => ['name' => 'Dock utara', 'object_type' => 'door']]])['ok']);
        $this->assertSame(['Dock utara', FloorPlanObjectType::Door], [$dock->refresh()->name, $dock->object_type]);

        $this->simpan($layar, [['op' => 'nonaktif', 'jenis' => 'obj', 'id' => $dock->id]]);
        $this->assertFalse($dock->refresh()->is_active, 'Tidak dihapus (P-03).');
        $this->assertSame(1, FloorPlanObject::query()->count());
        $this->assertSame([], app(WarehouseLayoutData::class)->build($this->gudang)['objects']);
        $this->assertSame($gerakAwal, StockMovement::query()->count(), 'Objek denah tidak menyentuh kartu stok (P-01).');

        $this->gagal(fn () => app(SaveFloorPlanObject::class)->create($this->gudang, 'tangga'), 'BR-GEN-11');
    }

    #[Test]
    public function tc_wh_33_tumpukan_diperingatkan_dan_geser_halus(): void
    {
        $layout = app(SaveWarehouseLayout::class);
        $layout->building($this->gudang, ['length_m' => 20, 'width_m' => 10]);
        $layout->zone($this->zona, ['length_m' => 8, 'width_m' => 4, 'pos_x' => 1, 'pos_y' => 1]);
        $layout->moveRack($this->rak->refresh(), 1, 1);  // rak 2 × 1 m di (2, 2) gedung

        $pilar = app(SaveFloorPlanObject::class)->create($this->gudang, 'pillar', ['name' => 'Pilar P2', 'pos_x' => 3, 'pos_y' => 2.5]);
        app(SaveFloorPlanObject::class)->create($this->gudang, 'forklift_lane', ['pos_x' => 0, 'pos_y' => 2]);
        $luar = app(SaveFloorPlanObject::class)->create($this->gudang, 'office', ['name' => 'Kantor']);
        $luar->forceFill(['pos_x' => 18])->save();  // data lama di luar garis gedung

        $zonaLain = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'E', 'name' => 'Lain']);
        $layout->zone($zonaLain, ['length_m' => 4, 'width_m' => 4, 'pos_x' => 7, 'pos_y' => 3]);

        $denah = app(WarehouseLayoutData::class)->build($this->gudang->refresh());
        $pesan = implode(' | ', $denah['tumpukan']);
        $this->assertStringContainsString('Rak D-R01 ↔ Pilar P2', $pesan);
        $this->assertStringContainsString('Zona D ↔ Zona E', $pesan);
        $this->assertStringContainsString('Kantor keluar dari garis gedung', $pesan);
        $this->assertStringNotContainsString('Jalur forklift', $pesan, 'Jalur forklift boleh ditumpuki (A-321).');
        $this->assertContains('rak:'.$this->rak->id, $denah['tumpukanId']);
        $this->assertContains('obj:'.$pilar->id, $denah['tumpukanId']);

        // Hanya peringatan: layar tetap bisa menyimpan (geser halus 0,1 m + putar).
        $layar = Livewire::actingAs($this->kepala())->test(WarehouseLayout::class, ['warehouse' => $this->gudang])
            ->assertSee(__('Geser salah satunya; peringatan ini tidak menolak simpan.'));
        $this->assertTrue($this->simpan($layar, [
            ['op' => 'geser', 'jenis' => 'rak', 'id' => $this->rak->id, 'x' => 1.1, 'y' => 1, 'halus' => true],
            ['op' => 'geser', 'jenis' => 'rak', 'id' => $this->rak->id, 'x' => 1.1, 'y' => 1.5, 'halus' => true],
            ['op' => 'putar', 'jenis' => 'rak', 'id' => $this->rak->id],
        ])['ok']);

        $this->rak->refresh();
        $this->assertSame([1.1, 1.5, 'v'], [(float) $this->rak->pos_x, (float) $this->rak->pos_y, $this->rak->orientation], 'Panah 0,1/0,5 m; R memutar rak.');
        // Rak "memanjang ke bawah" 1 × 2 m dijepit dengan ukuran tampak atasnya.
        app(SaveWarehouseLayout::class)->moveRack($this->rak, 50, 50);
        $this->assertSame([7.0, 2.0], [(float) $this->rak->refresh()->pos_x, (float) $this->rak->pos_y]);
    }

    #[Test]
    public function tc_wh_34_kode_terkunci_tidak_pindah_zona_nonaktif_tanpa_hapus(): void
    {
        $lokasi = app(SaveLocation::class);
        $this->gagal(fn () => $lokasi->saveRack($this->zona, $this->rak, ['code' => 'R99']), 'BR-WH-01');
        $zonaLain = $lokasi->saveZone($this->gudang, null, ['code' => 'E', 'name' => 'Lain']);

        // Form rak di denah tidak bisa memindah rak ke zona lain.
        app(SaveWarehouseLayout::class)->rack($this->rak, ['name' => 'Rak baru', 'zone_id' => $zonaLain->id, 'code' => 'R77']);
        $this->assertSame([(int) $this->zona->id, 'R01'], [(int) $this->rak->refresh()->zone_id, $this->rak->code]);

        $alasan = (string) ReasonCode::query()->where('context', ReasonContext::Cancel->value)->value('code');
        $nonaktif = app(DeactivateLocation::class);

        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 5, toBinId: $this->bin[0]->id));
        $this->gagal(fn () => $nonaktif->rack($this->rak, $alasan), 'BR-GEN-04');
        $this->gagal(fn () => $nonaktif->zone($this->zona, $alasan), 'BR-WH-07');
        $this->gagal(fn () => $nonaktif->rack($this->rak, ''), 'BR-GEN-11');
        $this->assertTrue($this->rak->refresh()->is_active);

        // Kosongkan lalu nonaktifkan dari layar.
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 5, fromBinId: $this->bin[0]->id));
        $layar = Livewire::actingAs($this->kepala())->test(WarehouseLayout::class, ['warehouse' => $this->gudang]);
        $tanpaAlasan = $this->simpan($layar, [['op' => 'nonaktif', 'jenis' => 'rak', 'id' => $this->rak->id, 'alasan' => '']]);
        $this->assertFalse($tanpaAlasan['ok']);
        $this->assertSame('rak:'.$this->rak->id, $tanpaAlasan['galat'][0]['objek']);
        $this->assertTrue($this->simpan($layar, [['op' => 'nonaktif', 'jenis' => 'rak', 'id' => $this->rak->id, 'alasan' => $alasan]])['ok']);

        $this->assertFalse($this->rak->refresh()->is_active);
        $this->assertSame(BinStatus::Inactive, $this->bin[0]->refresh()->bin_status);
        $this->assertSame(2, Bin::query()->withoutGlobalScopes()->whereIn('id', [$this->bin[0]->id, $this->bin[1]->id])->count(), 'Bin tidak dihapus (P-03).');
        $this->assertSame([], collect(app(WarehouseLayoutData::class)->build($this->gudang)['zones'])->firstWhere('code', 'D')['racks']);

        $nonaktif->zone($this->zona->refresh(), $alasan);
        $this->assertFalse($this->zona->refresh()->is_active);
    }

    #[Test]
    public function tc_wh_35_tanpa_izin_atau_sematan_ringkas_ditolak(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        $staf->forgetPermissionCache();
        $this->assertTrue($staf->hasPermission('warehouse.view'));

        $this->actingAs($staf)->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()->assertDontSee(__('Atur denah'));
        foreach ([['op' => 'geser', 'jenis' => 'zona', 'id' => $this->zona->id, 'x' => 1, 'y' => 1], ['op' => 'objek_baru', 'tmp' => 'b1', 'jenis' => 'door'], ['op' => 'gedung', 'data' => []]] as $op) {
            Livewire::actingAs($staf)->test(WarehouseLayout::class, ['warehouse' => $this->gudang])->call('simpanPerubahan', [$op])->assertForbidden();
        }

        // Staf tetap boleh melihat isi rak (tanpa render ulang).
        $this->assertSame('R01', data_get(Livewire::actingAs($staf)->test(WarehouseLayout::class, ['warehouse' => $this->gudang])->call('isiRak', $this->rak->id)->effects, 'returns.0.code'));

        // Sematan hanya-lihat di Daftar Gudang: Kepala Gudang pun tidak mengatur dari sana (A-323).
        Livewire::actingAs($this->kepala())->test(WarehouseLayout::class, ['warehouse' => $this->gudang, 'ringkas' => true])
            ->assertDontSee(__('Atur denah'))->call('simpanPerubahan', [])->assertForbidden();

        // Di luar cakupan gudang: halaman denah pun tertutup.
        $lain = $this->buatGudang('BKS', 'Gudang Bekasi');
        $kepalaLain = $this->makeUser('warehouse_head', ScopeType::Warehouse, $lain->id);
        $this->actingAs($kepalaLain)->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertNotFound();
        $this->assertSame(0, FloorPlanObject::query()->count());
    }

    #[Test]
    public function tc_wh_36_panel_rak_menampilkan_isi_bin_yang_diklik(): void
    {
        [$b1, $b2] = $this->bin;
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 7, toBinId: $b2->id));

        $layar = Livewire::actingAs($this->kepala())->test(WarehouseLayout::class, ['warehouse' => $this->gudang])
            ->assertSee(__('Tampak depan (tingkat paling bawah di bawah) — ketuk petak'))
            ->assertSee(__('Nonaktifkan rak'));   // tab Atur ada di halaman bagi pemegang bin.manage

        // A-353: klik rak → isi rak saja, per bin dengan kode pendek; tanpa HTML baru.
        $klik = $layar->call('isiRak', $this->rak->id);
        $this->assertArrayNotHasKey('html', $klik->effects, 'Klik rak tidak menggambar ulang halaman.');
        $isi = data_get($klik->effects, 'returns.0');
        $this->assertSame(['R01', 'D', 'Denah'], [$isi['code'], $isi['zona'], $isi['zona_nama']]);
        $petak = collect($isi['levels'][0]['bins'])->keyBy('code');
        $this->assertSame('R01 · L1 · 02', $petak[$b2->code]['pendek']);
        $this->assertSame('BAUT-M12', $petak[$b2->code]['isi'][0]['item_code']);
        $this->assertSame([], $petak[$b1->code]['isi'], 'Bin kosong.');
    }

    #[Test]
    public function tc_wh_37_tombol_denah_dan_mode_denah_di_daftar_gudang(): void
    {
        $kepala = $this->kepala();

        $this->actingAs($kepala)->get($this->tenantUrl('warehouses'))->assertOk()
            ->assertSee(route('warehouses.layout', $this->gudang))->assertSee(__('Tabel'))->assertSee(__('Denah'));

        Livewire::actingAs($kepala)->test(WarehouseList::class)
            ->set('tampilan', 'denah')
            ->assertSee(__('Buka denah penuh / Atur denah'))
            ->assertSeeLivewire(WarehouseLayout::class)
            ->assertViewHas('denahGudang', fn ($g) => (int) $g->id === (int) $this->gudang->id);

        $this->actingAs($kepala)->get($this->tenantUrl('warehouses?tampilan=denah'))->assertOk()->assertSee('data-denah-gedung', false);
    }
}
