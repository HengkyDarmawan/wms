<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\ApplyLayoutChanges;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Actions\MergeBins;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Enums\BinMergeDirection;
use App\Domain\Warehouse\Enums\BinMergeType;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Livewire\WarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\BinUsage;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-42 s.d. TC-WH-49 — Tata letak gudang Bagian 2, struktur rak nyata
 * (K-B, K-C, K-D, K-E; A-359–A-364): gabung/pisah bin, hapus bin yang belum
 * pernah dipakai, lebar bin, dan area lantai berkapasitas bebas — semuanya
 * lewat Simpan perubahan mode Atur (A-353).
 */
class StrukturRakTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Zone $zona;

    private Rack $rak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        $this->zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        $this->rak = app(SaveWarehouseLayout::class)->newRack($this->zona, ['code' => 'R01', 'levels' => '3', 'bins_per_level' => '3']);
    }

    private function b(string $level, string $petak, string $rak = 'R01'): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-'.$rak.'-'.$level.'-'.$petak)->firstOrFail();
    }

    private function kepala()
    {
        $u = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $u->forgetPermissionCache();

        return $u;
    }

    private function gagal(callable $aksi, string $aturan, ?string $pesan = null): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (WarehouseRuleException|LedgerException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());

            if ($pesan !== null) {
                $this->assertStringContainsString($pesan, $e->getMessage().' '.implode(' ', $e->fieldErrors ?? []));
            }
        }
    }

    private function masuk(Bin $bin, $item, float $qty): void
    {
        app(StockLedger::class)->post(new MovementRequest(item: $item, qtyBase: $qty, toBinId: $bin->id));
    }

    private function keluar(Bin $bin, $item, float $qty): void
    {
        app(StockLedger::class)->post(new MovementRequest(item: $item, qtyBase: $qty, fromBinId: $bin->id));
    }

    /** @return array<string, mixed> petak di data denah */
    private function petak(Bin $bin): array
    {
        $data = app(WarehouseLayoutData::class)->payload($this->gudang);

        return collect($data['zones'])->flatMap(fn ($z) => $z['racks'])->flatMap(fn ($r) => $r['levels'])
            ->flatMap(fn ($l) => $l['bins'])->firstWhere('id', $bin->id);
    }

    #[Test]
    public function tc_wh_42_gabung_samping_dan_atas_lewat_simpan_perubahan(): void
    {
        // Tetangga harus wajar.
        $aksi = app(MergeBins::class);
        $this->gagal(fn () => $aksi->merge($this->b('L2', 'B01'), [$this->b('L2', 'B03')->id], 'side', 'temporary', 'x'), 'BR-WH-08', 'bersebelahan');
        app(SaveWarehouseLayout::class)->newRack($this->zona, ['code' => 'R02', 'levels' => '1', 'bins_per_level' => '1']);
        $this->gagal(fn () => $aksi->merge($this->b('L2', 'B01'), [$this->b('L1', 'B01', 'R02')->id], 'side', 'temporary', 'x'), 'BR-WH-08', 'rak lain');
        $this->gagal(fn () => $aksi->merge($this->b('L2', 'B01'), [$this->b('L3', 'B02')->id], 'above', 'temporary', 'x'), 'BR-WH-08', 'bernomor sama');
        $this->gagal(fn () => $aksi->merge($this->b('L3', 'B01'), [$this->b('L2', 'B01')->id], 'above', 'temporary', 'x'), 'BR-WH-08', 'paling bawah');
        $this->gagal(fn () => $aksi->merge($this->b('L2', 'B01'), [$this->b('L2', 'B02')->id], 'miring', 'temporary', 'x'), 'BR-GEN-11');

        $this->actingAs($this->kepala());
        $riwayat = Activity::query()->count();

        $hasil = app(ApplyLayoutChanges::class)->handle($this->gudang, [
            ['op' => 'gabung', 'utama' => $this->b('L1', 'B01')->id, 'bins' => [$this->b('L1', 'B02')->id], 'arah' => 'side', 'sifat' => 'permanent', 'alasan' => 'Petak lebar untuk pallet'],
            ['op' => 'gabung', 'utama' => $this->b('L1', 'B03')->id, 'bins' => [$this->b('L2', 'B03')->id, $this->b('L3', 'B03')->id], 'arah' => 'above', 'sifat' => 'temporary', 'alasan' => 'Pipa tegak'],
        ], auth()->user());

        $this->assertTrue($hasil['ok'], json_encode($hasil));
        $b2 = $this->b('L1', 'B02');
        $this->assertSame([(int) $this->b('L1', 'B01')->id, BinMergeDirection::Side, BinMergeType::Permanent], [(int) $b2->occupied_by_bin_id, $b2->merge_direction, $b2->merge_type]);
        $this->assertSame(BinMergeDirection::Above, $this->b('L3', 'B03')->merge_direction);
        $this->assertSame('CKG-D-R01-L1-B02', $b2->code, 'Kode bin tidak berubah (BR-WH-01).');
        $this->assertSame(2, Activity::query()->where('description', 'like', 'Bin digabung%')->count(), 'Satu gabung = satu baris riwayat.');
        $this->assertSame(3, Activity::query()->count() - $riwayat, 'Dua gabung + satu *Perubahan denah disimpan*.');

        // Data denah: bin utama tahu bin tergabungnya; bin tergabung berstatus tergabung.
        $this->assertSame([(int) $b2->id], $this->petak($this->b('L1', 'B01'))['tergabung']);
        $this->assertSame(['tergabung', 'side', 'permanent'], [$this->petak($b2)['status'], $this->petak($b2)['arah'], $this->petak($b2)['sifat']]);

        $this->gagal(fn () => app(MergeBins::class)->merge($b2, [$this->b('L2', 'B02')->id], 'side', 'temporary', 'x'), 'BR-WH-08', 'sudah tergabung');
    }

    #[Test]
    public function tc_wh_43_bin_tergabung_wajib_kosong_dan_menolak_pergerakan(): void
    {
        [$utama, $b2, $b3] = [$this->b('L2', 'B01'), $this->b('L2', 'B02'), $this->b('L2', 'B03')];
        $aksi = app(MergeBins::class);

        $this->masuk($b3, $this->baut, 5);
        $this->gagal(fn () => $aksi->merge($utama, [$b2->id, $b3->id], 'side', 'temporary', 'genset'), 'BR-WH-08', 'harus kosong');
        $this->assertNull($b2->refresh()->occupied_by_bin_id, 'Satu bin berisi = tidak ada yang digabung.');

        $aksi->merge($utama, [$b2->id], 'side', 'temporary', 'genset besar');

        // Stok selalu di bin utama: bin tergabung menolak pergerakan sendiri dengan pesan jelas.
        $this->gagal(fn () => $this->masuk($b2->refresh(), $this->baut, 1), 'BR-WH-08', 'bin utama CKG-D-R01-L2-B01');
        $this->assertFalse($b2->acceptsMovement());
        $this->masuk($utama, $this->baut, 1);

        // Menonaktifkan bagian gabungan ditolak — pisah dulu.
        $this->gagal(fn () => app(ChangeBinStatus::class)->deactivate($b2, 'LAIN'), 'BR-WH-08', 'Pisah dulu');
    }

    #[Test]
    public function tc_wh_44_pisah_sementara_dan_permanen(): void
    {
        $aksi = app(MergeBins::class);
        [$utama, $b2] = [$this->b('L1', 'B01'), $this->b('L1', 'B02')];
        $aksi->merge($utama, [$b2->id], 'side', 'permanent', 'Rak lebar');

        // Permanen tidak dipisah otomatis saat bin utama kosong.
        $this->masuk($utama, $this->baut, 2);
        $this->keluar($utama, $this->baut, 2);
        $this->assertNotNull($b2->refresh()->occupied_by_bin_id);

        $this->gagal(fn () => $aksi->split($b2), 'BR-GEN-11', 'permanen');
        $this->assertSame(1, $aksi->split($b2, 'Rak dibongkar'));
        $this->assertNull($b2->refresh()->occupied_by_bin_id);
        $this->assertSame(1, Activity::query()->where('description', 'like', 'Gabungan dipisah:%')->count());

        // Sementara: dipisah lewat Simpan perubahan tanpa alasan.
        $aksi->merge($this->b('L2', 'B02'), [$this->b('L2', 'B03')->id], 'side', 'temporary', 'Pallet');
        $this->actingAs($this->kepala());
        $hasil = app(ApplyLayoutChanges::class)->handle($this->gudang, [['op' => 'pisah', 'bin' => $this->b('L2', 'B03')->id]], auth()->user());
        $this->assertTrue($hasil['ok'], json_encode($hasil));
        $this->assertNull($this->b('L2', 'B03')->occupied_by_bin_id);
        $this->gagal(fn () => $aksi->split($this->b('L2', 'B03')), 'BR-WH-08', 'tidak sedang digabung');
    }

    #[Test]
    public function tc_wh_45_ikut_terpakai_lama_menjadi_gabung_sementara_ke_samping(): void
    {
        [$utama, $lama, $permanen] = [$this->b('L3', 'B01'), $this->b('L3', 'B02'), $this->b('L3', 'B03')];
        // Data sebelum migrasi 000500: hanya penunjuk "ikut terpakai" (A-255).
        $lama->forceFill(['occupied_by_bin_id' => $utama->id, 'occupied_reason' => 'Genset', 'merge_direction' => null, 'merge_type' => null])->save();
        $permanen->forceFill(['occupied_by_bin_id' => $utama->id, 'merge_direction' => 'side', 'merge_type' => 'permanent'])->save();

        $migrasi = require base_path('database/migrations/tenant/2026_01_01_000500_add_merge_and_width_to_bins.php');
        $this->assertSame(1, $migrasi->isiBalik(), 'Hanya baris lama yang diisi balik.');

        $lama->refresh();
        $this->assertSame([BinMergeDirection::Side, BinMergeType::Temporary, 'Genset'], [$lama->merge_direction, $lama->merge_type, $lama->occupied_reason]);
        $this->assertSame(BinMergeType::Permanent, $permanen->refresh()->merge_type, 'Gabungan baru tidak ditimpa.');
        $this->assertSame(0, $migrasi->isiBalik(), 'Aman diulang.');
        $this->gagal(fn () => $this->masuk($lama, $this->baut, 1), 'BR-WH-08');
    }

    #[Test]
    public function tc_wh_46_hapus_bin_hanya_yang_belum_pernah_dipakai(): void
    {
        $this->actingAs($this->kepala());
        $aksi = app(ApplyLayoutChanges::class);
        [$bersih, $bekas, $bersih2] = [$this->b('L3', 'B01'), $this->b('L3', 'B02'), $this->b('L3', 'B03')];
        $this->masuk($bekas, $this->baut, 3);
        $this->keluar($bekas, $this->baut, 3);

        // Panel rak: tombol Hapus hanya untuk bin yang belum pernah dipakai.
        $rak = app(WarehouseLayoutData::class)->rakDetail($this->gudang, (int) $this->rak->id);
        $petak = collect($rak['levels'])->flatMap(fn ($l) => $l['bins'])->keyBy('code');
        $this->assertTrue($petak[$bersih->code]['boleh_hapus']);
        $this->assertFalse($petak[$bekas->code]['boleh_hapus'], 'Saldo nol pun, kartu stoknya ada.');

        // Satu gagal = semua batal: bin bersih ikut tidak terhapus.
        $gagal = $aksi->handle($this->gudang, [['op' => 'hapus_bin', 'bin' => $bersih2->id], ['op' => 'hapus_bin', 'bin' => $bekas->id]], auth()->user());
        $this->assertFalse($gagal['ok']);
        $this->assertSame('bin:'.$bekas->id, $gagal['galat'][0]['objek']);
        $this->assertStringContainsString('pernah dipakai (', $gagal['galat'][0]['pesan']);
        $this->assertNotNull(Bin::query()->find($bersih2->id));

        $ok = $aksi->handle($this->gudang, [
            ['op' => 'hapus_bin', 'bin' => $bersih->id],
            ['op' => 'nonaktif', 'jenis' => 'bin', 'id' => $bekas->id, 'alasan' => 'LAIN'],
        ], auth()->user());
        $this->assertTrue($ok['ok'], json_encode($ok));
        $this->assertNull(Bin::query()->withoutGlobalScopes()->find($bersih->id), 'Bin belum pernah dipakai terhapus.');
        $this->assertSame(BinStatus::Inactive, $bekas->refresh()->bin_status, 'Bin bekas hanya nonaktif (P-03).');
        $this->assertSame(1, Activity::query()->where('description', 'like', 'Bin CKG-D-R01-L3-B01 dihapus%')->count());

        // Bin sistem & bin utama gabungan tidak dihapus.
        $penerimaan = Bin::query()->where('warehouse_id', $this->gudang->id)->where('bin_type', BinType::Receiving->value)->firstOrFail();
        $sistem = $aksi->handle($this->gudang, [['op' => 'hapus_bin', 'bin' => $penerimaan->id]], auth()->user());
        $this->assertStringContainsString('bin sistem', $sistem['galat'][0]['pesan']);
        app(MergeBins::class)->merge($this->b('L2', 'B01'), [$this->b('L2', 'B02')->id], 'side', 'temporary', 'x');
        $utama = $aksi->handle($this->gudang, [['op' => 'hapus_bin', 'bin' => $this->b('L2', 'B01')->id], ['op' => 'hapus_bin', 'bin' => $this->b('L2', 'B02')->id]], auth()->user());
        $this->assertStringContainsString('bin tergabung', $utama['galat'][0]['pesan']);
        $this->assertStringContainsString('Pisah dulu', $utama['galat'][1]['pesan']);
    }

    #[Test]
    public function tc_wh_46b_semua_foreign_key_ke_bins_ikut_diperiksa(): void
    {
        $db = (new Bin)->getConnection()->getDatabaseName();
        $skema = collect(DB::select(
            'SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?',
            [$db, 'bins'],
        ))->map(fn ($r) => $r->t.'.'.$r->c)->sort()->values()->all();

        // A-368: pengaturan (tempat simpan) ikut terdaftar, terpisah dari pemakaian.
        $diperiksa = collect([...BinUsage::REFERENSI, ...BinUsage::KONFIGURASI])->map(fn ($r) => $r[0].'.'.$r[1])->sort()->values()->all();

        $this->assertNotEmpty($skema);
        $this->assertSame($skema, $diperiksa, 'Foreign key baru ke `bins` wajib didaftarkan di BinUsage::REFERENSI (A-362) supaya bin yang pernah dipakai tidak bisa dihapus.');
    }

    #[Test]
    public function tc_wh_47_lebar_bin_tersimpan_dan_ada_di_data_denah(): void
    {
        $this->actingAs($this->kepala());
        $aksi = app(ApplyLayoutChanges::class);
        $b1 = $this->b('L1', 'B01');

        $this->assertTrue($aksi->handle($this->gudang, [['op' => 'lebar_bin', 'bin' => $b1->id, 'lebar' => '1,2']], auth()->user())['ok']);
        $this->assertSame(1.2, (float) $b1->refresh()->width_m);
        $this->assertSame(1.2, $this->petak($b1)['lebar']);
        $this->assertNull($this->petak($this->b('L1', 'B02'))['lebar'], 'Kosong = rata bagi lebar rak.');

        $salah = $aksi->handle($this->gudang, [['op' => 'lebar_bin', 'bin' => $b1->id, 'lebar' => '-1']], auth()->user());
        $this->assertFalse($salah['ok']);
        $this->assertSame(1.2, (float) $b1->refresh()->width_m);

        $aksi->handle($this->gudang, [['op' => 'lebar_bin', 'bin' => $b1->id, 'lebar' => '']], auth()->user());
        $this->assertNull($b1->refresh()->width_m);
    }

    #[Test]
    public function tc_wh_48_area_lantai_kapasitas_bebas(): void
    {
        $this->actingAs($this->kepala());
        $area = app(SaveWarehouseLayout::class)->areaRack($this->zona, ['code' => 'AB1', 'name' => 'Parkir alat']);
        $this->assertNull($area->capacity_qty, 'Kosong = tanpa batas (K-E).');
        $this->masuk($area, $this->baut, 1000);

        $rak = $area->rackLevel->rack;
        $hasil = app(ApplyLayoutChanges::class)->handle($this->gudang, [
            ['op' => 'kapasitas_area', 'id' => $rak->id, 'data' => ['capacity_qty' => '1500', 'capacity_weight' => '2000', 'capacity_volume' => '']],
        ], auth()->user());
        $this->assertTrue($hasil['ok'], json_encode($hasil));
        $area->refresh();
        $this->assertSame([1500.0, 2000.0, null], [(float) $area->capacity_qty, (float) $area->capacity_weight, $area->capacity_volume]);

        $data = collect(app(WarehouseLayoutData::class)->payload($this->gudang)['zones'][0]['racks'])->firstWhere('code', 'AB1');
        $this->assertSame(['qty' => 1500.0, 'berat' => 2000.0, 'volume' => null], $data['kapasitas_area']);

        // Kapasitas dihormati (blokir, seluruh isi area).
        $this->masuk($area, $this->kabel, 500);
        $this->gagal(fn () => $this->masuk($area, $this->baut, 1), 'BR-WH-06');

        $salah = app(ApplyLayoutChanges::class)->handle($this->gudang, [
            ['op' => 'kapasitas_area', 'id' => $rak->id, 'data' => ['capacity_qty' => 'abc']],
            ['op' => 'kapasitas_area', 'id' => $this->rak->id, 'data' => ['capacity_qty' => '5']],
        ], auth()->user());
        $this->assertSame([0, 1], array_column($salah['galat'], 'i'));
        $this->assertSame(1500.0, (float) $area->refresh()->capacity_qty);
    }

    #[Test]
    public function tc_wh_49_kapasitas_seluruh_isi_bin_dan_gabungan(): void
    {
        [$utama, $b2] = [$this->b('L2', 'B01'), $this->b('L2', 'B02')];
        $utama->forceFill(['capacity_qty' => 10, 'capacity_mode' => 'block'])->save();
        $b2->forceFill(['capacity_qty' => 5])->save();

        // Keputusan #10: dua item berbeda dijumlah — dulu lolos karena dihitung per baris saldo.
        $this->masuk($utama, $this->baut, 6);
        $this->gagal(fn () => $this->masuk($utama, $this->kabel, 5), 'BR-WH-06');

        // Bin utama gabungan: kapasitas = 10 + 5.
        app(MergeBins::class)->merge($utama->refresh(), [$b2->id], 'side', 'temporary', 'Barang besar');
        $this->assertSame(15.0, $utama->refresh()->effectiveCapacity('capacity_qty'));
        $this->masuk($utama, $this->kabel, 9);
        $this->assertSame(15.0, $this->petak($utama)['capacity_qty']);
        $this->assertTrue($this->petak($utama)['penuh']);
        $this->gagal(fn () => $this->masuk($utama, $this->kabel, 1), 'BR-WH-06');

        // Salah satu tanpa batas → gabungan tanpa batas.
        $b2->refresh()->forceFill(['capacity_qty' => null])->save();
        $this->assertNull($utama->refresh()->effectiveCapacity('capacity_qty'));
        $this->masuk($utama, $this->kabel, 100);
    }

    #[Test]
    public function tc_wh_49b_layar_atur_dan_versi_daftar_menampilkan_gabungan(): void
    {
        app(MergeBins::class)->merge($this->b('L1', 'B01'), [$this->b('L1', 'B02')->id], 'side', 'permanent', 'Rak lebar');

        $this->actingAs($this->kepala())->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()
            ->assertSee('data-atur-petak', false)
            ->assertSee('data-gabung-label', false)
            ->assertSee('"utama":'.$this->b('L1', 'B01')->id, false);

        // Staf gudang tidak bisa menyimpan perubahan struktur.
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        Livewire::actingAs($staf)->test(WarehouseLayout::class, ['warehouse' => $this->gudang])
            ->call('simpanPerubahan', [['op' => 'hapus_bin', 'bin' => $this->b('L3', 'B01')->id]])->assertForbidden();
        $this->assertNotNull(Bin::query()->withoutGlobalScopes()->find($this->b('L3', 'B01')->id));
    }
}
