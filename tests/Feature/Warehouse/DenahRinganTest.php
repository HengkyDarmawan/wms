<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\ApplyLayoutChanges;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Livewire\WarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\BinCode;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-38 s.d. TC-WH-41 — Denah ringan (K-H, K-I, K-J; A-352, A-353):
 * simpan semua perubahan sekali dalam satu transaksi dengan galat per objek,
 * klik rak hanya mengambil isi rak, data denah untuk browser & versi daftar,
 * dan kode pendek bin.
 */
class DenahRinganTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Zone $zona;

    private Rack $rak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        $this->zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        $this->rak = app(SaveWarehouseLayout::class)->newRack($this->zona, ['code' => 'R01', 'levels' => '2', 'bins_per_level' => '2']);
    }

    private function kepala()
    {
        $u = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $u->forgetPermissionCache();

        return $u;
    }

    #[Test]
    public function tc_wh_38_simpan_perubahan_satu_transaksi_dengan_galat_per_objek(): void
    {
        $this->actingAs($this->kepala());
        $aksi = app(ApplyLayoutChanges::class);
        $riwayatAwal = Activity::query()->count();

        // Berhasil: zona & rak baru (id sementara) lalu digeser, ditambah objek — sekali kirim.
        $hasil = $aksi->handle($this->gudang, [
            ['op' => 'zona_baru', 'tmp' => 'b1', 'data' => ['code' => 'B', 'name' => 'Zona B']],
            ['op' => 'rak_baru', 'tmp' => 'b2', 'zona' => 'b1', 'data' => ['code' => 'R01', 'levels' => '1', 'bins_per_level' => '3']],
            ['op' => 'geser', 'jenis' => 'rak', 'id' => 'b2', 'x' => 2, 'y' => 1],
            ['op' => 'putar', 'jenis' => 'rak', 'id' => $this->rak->id],
            ['op' => 'objek_baru', 'tmp' => 'b3', 'jenis' => 'pillar', 'data' => ['pos_x' => 3, 'pos_y' => 3]],
        ], auth()->user());

        $this->assertSame(['ok' => true, 'galat' => [], 'jumlah' => 5], $hasil);
        $rakB = Rack::query()->whereHas('zone', fn ($q) => $q->where('code', 'B'))->sole();
        $this->assertSame([2.0, 1.0], [(float) $rakB->pos_x, (float) $rakB->pos_y]);
        $this->assertSame('v', $this->rak->refresh()->orientation);
        $this->assertSame(1, Activity::query()->where('description', 'Perubahan denah disimpan')->count());
        $this->assertSame(1, Activity::query()->where('subject_id', $this->rak->id)->where('description', 'Rak diputar di denah')->count(), 'Satu putar = satu baris riwayat.');
        $this->assertGreaterThan($riwayatAwal, Activity::query()->count());

        // Gagal: galat per objek dikumpulkan; perubahan yang sah ikut dibatalkan.
        $gagal = $aksi->handle($this->gudang, [
            ['op' => 'zona_baru', 'tmp' => 'b1', 'data' => ['code' => 'C', 'name' => 'Zona C']],
            ['op' => 'geser', 'jenis' => 'rak', 'id' => $this->rak->id, 'x' => 5, 'y' => 5],
            ['op' => 'rak_baru', 'tmp' => 'b2', 'zona' => 'b1', 'data' => ['code' => 'R01', 'levels' => '0']],
            ['op' => 'geser', 'jenis' => 'rak', 'id' => 'b2', 'x' => 1, 'y' => 1],
            ['op' => 'terbang', 'jenis' => 'rak', 'id' => $this->rak->id],
            ['op' => 'geser', 'jenis' => 'rak', 'id' => 999999, 'x' => 1, 'y' => 1],
        ], auth()->user());

        $this->assertFalse($gagal['ok']);
        $this->assertSame([2, 3, 4, 5], array_column($gagal['galat'], 'i'));
        $this->assertSame(['rak:b2', 'rak:b2', 'rak:'.$this->rak->id, 'rak:999999'], array_column($gagal['galat'], 'objek'));
        $this->assertStringContainsString('bergantung', $gagal['galat'][1]['pesan'], 'Rak baru yang gagal membuat operasi sesudahnya ikut gagal.');
        $this->assertFalse(Zone::query()->where('code', 'C')->exists());
        $this->assertNull($this->rak->refresh()->pos_x, 'Geser yang sah pun dibatalkan.');

        // Batas jumlah operasi per simpan.
        $this->expectException(WarehouseRuleException::class);
        $aksi->handle($this->gudang, array_fill(0, ApplyLayoutChanges::MAKS_OPERASI + 1, ['op' => 'putar', 'jenis' => 'rak', 'id' => $this->rak->id]));
    }

    #[Test]
    public function tc_wh_39_klik_rak_hanya_mengambil_isi_rak_tanpa_menggambar_ulang(): void
    {
        [$b1] = $this->binRak();
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 9, toBinId: $b1->id));

        $layar = Livewire::actingAs($this->kepala())->test(WarehouseLayout::class, ['warehouse' => $this->gudang]);
        $klik = $layar->call('isiRak', $this->rak->id);

        $this->assertArrayNotHasKey('html', $klik->effects);
        $isi = data_get($klik->effects, 'returns.0');
        $this->assertSame(['L2', 'L1'], array_column($isi['levels'], 'code'), 'Tingkat teratas dulu.');
        $petak = collect($isi['levels'][1]['bins'])->firstWhere('code', $b1->code);
        $this->assertEquals([9, 'BAUT-M12', true], [$petak['total'], $petak['isi'][0]['item_code'], $petak['isi'][0]['tertua']]);
        $this->assertLessThan(10 * 1024, strlen(json_encode($isi)), 'Isi satu rak tetap ringan.');

        // Rak gudang lain tidak bisa diintip.
        $lain = $this->buatGudang('BKS', 'Gudang Bekasi');
        $zonaLain = app(SaveLocation::class)->saveZone($lain, null, ['code' => 'A', 'name' => 'A']);
        $rakLain = app(SaveWarehouseLayout::class)->newRack($zonaLain, ['code' => 'R09']);
        $this->expectException(ModelNotFoundException::class);
        app(WarehouseLayoutData::class)->rakDetail($this->gudang, (int) $rakLain->id);
    }

    #[Test]
    public function tc_wh_40_data_denah_sekali_muat_dan_versi_daftar_untuk_hp(): void
    {
        [$b1] = $this->binRak();
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 4, toBinId: $b1->id));

        $data = app(WarehouseLayoutData::class)->payload($this->gudang);
        $bin = collect($data['zones'][0]['racks'][0]['levels'])->flatMap(fn ($l) => $l['bins'])->firstWhere('code', $b1->code);

        $this->assertArrayNotHasKey('isi', $bin, 'Isi bin diambil saat rak diklik, bukan saat halaman dibuka.');
        $this->assertStringContainsString('baut-m12', $bin['cari'], 'Indeks cari di browser memuat kode item.');
        $this->assertSame(4, $data['jumlah_bin']);
        $this->assertArrayNotHasKey('hasil', $data);

        $this->actingAs($this->kepala())->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()
            ->assertSee('data-denah-daftar', false)->assertSee('data-ganti-tampilan', false)
            ->assertSee('"maksBinGambar":'.WarehouseLayout::MAKS_BIN_GAMBAR, false)
            ->assertDontSee('wire:poll', false);
    }

    #[Test]
    public function tc_wh_41_kode_pendek_bin(): void
    {
        $this->assertSame('B001 · L5 · 01', BinCode::pendek('CKG-A-B001-L5-B01'));
        $this->assertSame('A · R01 · L1 · 02', BinCode::pendek('CKG-A-R01-L1-B02', true));
        $this->assertSame('AB1 · Area', BinCode::pendek('CKG-A-AB1-L1-AREA'));
        $this->assertSame('CKG-RCV', BinCode::pendek('CKG-RCV'), 'Bin sistem tetap kode lengkap.');

        // Kode rak kembar antar-zona → awalan zona supaya tidak terbaca sama.
        $data = app(WarehouseLayoutData::class)->payload($this->gudang);
        $this->assertSame('R01 · L1 · 01', $data['zones'][0]['racks'][0]['levels'][1]['bins'][0]['pendek']);

        $zonaB = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'B', 'name' => 'Zona B']);
        app(SaveWarehouseLayout::class)->newRack($zonaB, ['code' => 'R01', 'levels' => '1', 'bins_per_level' => '1']);
        $zona = collect(app(WarehouseLayoutData::class)->payload($this->gudang)['zones'])->keyBy('code');
        $this->assertSame('D · R01 · L1 · 01', $zona['D']['racks'][0]['levels'][1]['bins'][0]['pendek']);
        $this->assertSame('B · R01 · L1 · 01', $zona['B']['racks'][0]['levels'][0]['bins'][0]['pendek']);
    }

    /** @return array<int, Bin> */
    private function binRak(): array
    {
        return Bin::query()->whereIn('rack_level_id', $this->rak->levels()->pluck('id'))->orderBy('code')->get()->all();
    }
}
