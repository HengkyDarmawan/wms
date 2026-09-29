<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustmentLine;
use App\Domain\Adjustment\Support\AdjustmentPoster;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\ApplyLayoutChanges;
use App\Domain\Warehouse\Actions\DeleteBin;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\StorageDedicationOverride;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\StorageLocationPlanner;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-50 s.d. TC-WH-55 — Tata letak gudang Bagian 3: **Tempat Simpan**
 * barang (K-A, A-365–A-368): simpan & urutan, cakupan gudang, saran bin,
 * **Khusus Barang Ini** di put-away / penyesuaian (+) / saldo awal (opname
 * tidak), buka oleh Kepala Gudang dengan alasan tercatat.
 */
class TempatSimpanTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Zone $zona;

    private Rack $rak;

    private Rack $area;

    private Item $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        $this->zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        $this->rak = app(SaveWarehouseLayout::class)->newRack($this->zona, ['code' => 'R01', 'levels' => '3', 'bins_per_level' => '3']);
        $this->cat = $this->buatItem('CAT-TEMBOK', TrackingMode::None, Uom::query()->where('code', 'PCS')->value('id'));
        $this->area = Rack::query()->findOrFail((int) app(SaveWarehouseLayout::class)->areaRack($this->zona, ['code' => 'AB1'])->rackLevel()->value('rack_id'));
    }

    private function b(string $level, string $petak): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R01-'.$level.'-'.$petak)->firstOrFail();
    }

    private function kepala()
    {
        $u = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $u->forgetPermissionCache();

        return $u;
    }

    private function simpan(Item $item, array $baris, $actor = null): void
    {
        app(SaveItemStorageLocations::class)->replace($item, $this->gudang, $baris, $actor ?? $this->kepala());
    }

    private function gagal(callable $aksi, string $aturan, string $kelas = WarehouseRuleException::class): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($kelas, $e, $e->getMessage());
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    private function stok(Bin $bin, Item $item, float $qty): void
    {
        app(StockLedger::class)->post(new MovementRequest(item: $item, qtyBase: $qty, toBinId: $bin->id));
    }

    private function putDari(Item $item, float $qty = 10): PutawayTask
    {
        $grn = $this->grnDiterima([['item_id' => $item->id, 'qty_received' => $qty]]);

        return app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser())->putawayTasks()->with('lines')->sole();
    }

    #[Test]
    public function tc_wh_50_simpan_dan_ubah_urutan_tempat_simpan(): void
    {
        $this->simpan($this->baut, [
            ['tempat' => 'rak:'.$this->rak->id],
            ['tempat' => 'bin:'.$this->b('L2', 'B01')->id, 'khusus' => true],
            ['tempat' => 'rak:'.$this->area->id],
        ]);

        $daftar = ItemStorageLocation::query()->where('item_id', $this->baut->id)->orderBy('sequence')->get();
        $this->assertSame(['rak', 'bin', 'area'], $daftar->map->jenis()->all());
        $this->assertSame([1, 2, 3], $daftar->pluck('sequence')->all());
        $this->assertSame([false, true, false], $daftar->pluck('is_dedicated')->all());
        $this->assertSame('R01 · L2 · 01', $daftar[1]->label());

        // Ubah urutan: area dulu, bin khusus dilepas.
        $this->simpan($this->baut, [['tempat' => 'rak:'.$this->area->id], ['tempat' => 'rak:'.$this->rak->id]]);
        $this->assertSame([(int) $this->area->id, (int) $this->rak->id],
            ItemStorageLocation::query()->where('item_id', $this->baut->id)->orderBy('sequence')->pluck('rack_id')->map(fn ($v) => (int) $v)->all());
        $this->assertTrue(Activity::query()->where('subject_type', Item::class)->where('subject_id', $this->baut->id)
            ->where('description', 'like', 'Tempat simpan di gudang CKG diubah%')->exists());

        // Bin area dipilih sebagai bin → dicatat sebagai area.
        $binArea = Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-AB1-L1-AREA')->firstOrFail();
        $this->simpan($this->semen, [['tempat' => 'bin:'.$binArea->id]]);
        $this->assertSame('area', ItemStorageLocation::query()->where('item_id', $this->semen->id)->sole()->jenis());

        // Ganda, tempat asing, bin tanpa rak ditolak.
        $this->gagal(fn () => $this->simpan($this->baut, [['tempat' => 'rak:'.$this->rak->id], ['tempat' => 'rak:'.$this->rak->id]]), 'BR-GEN-11');
        $this->gagal(fn () => $this->simpan($this->baut, [['tempat' => 'gudang:1']]), 'BR-GEN-11');
        $this->gagal(fn () => $this->simpan($this->baut, [['tempat' => 'bin:'.$this->binA->id]]), 'BR-GEN-11');
    }

    #[Test]
    public function tc_wh_51_cakupan_gudang_dihormati(): void
    {
        $this->simpan($this->baut, [['tempat' => 'rak:'.$this->rak->id]]);
        $lain = $this->buatGudang('SBY', 'Gudang Surabaya');
        $orangLain = $this->makeUser('warehouse_head', ScopeType::Warehouse, $lain->id);
        $orangLain->forgetPermissionCache();

        $this->gagal(fn () => $this->simpan($this->baut, [['tempat' => 'rak:'.$this->rak->id]], $orangLain), 'BR-GEN-09');

        $this->actingAs($orangLain);
        $this->assertSame(0, ItemStorageLocation::query()->count(), 'BR-ACC-05: tempat simpan gudang lain tidak terlihat.');
        $this->actingAs($this->kepala());
        $this->assertSame(1, ItemStorageLocation::query()->count());
    }

    #[Test]
    public function tc_wh_52_saran_bin_urut_tempat_simpan_lalu_aturan_lama_bertanda_penuh(): void
    {
        $planner = app(StorageLocationPlanner::class);
        $this->simpan($this->baut, [['tempat' => 'rak:'.$this->rak->id]]);

        // Keputusan #4: kosong dari L1, kiri ke kanan.
        $this->assertSame(['L1-B01', 'L1-B02', 'L1-B03', 'L2-B01'], $planner->kandidat($this->baut, $this->gudang)->take(4)
            ->map(fn (Bin $b) => substr($b->code, -6))->all());

        // Bin yang sudah berisi barang itu dulu; bin berisi barang lain dilewati.
        $this->stok($this->b('L3', 'B02'), $this->baut, 2);
        $this->stok($this->b('L1', 'B01'), $this->cat, 1);
        $urut = $planner->kandidat($this->baut, $this->gudang)->map(fn (Bin $b) => substr($b->code, -6))->all();
        $this->assertSame('L3-B02', $urut[0]);
        $this->assertNotContains('L1-B01', $urut);
        $this->assertSame('L1-B02', $urut[1]);

        $saran = $planner->saranBin($this->baut, $this->gudang, 5);
        $this->assertSame('CKG-D-R01-L3-B02', $saran['bin']->code);
        $this->assertTrue($saran['dari_tempat_simpan']);
        $this->assertFalse($saran['penuh']);

        // Keputusan #3: tempat penuh → tempat berikutnya; semuanya penuh → aturan lama bertanda penuh.
        $this->simpan($this->baut, [['tempat' => 'bin:'.$this->b('L2', 'B03')->id]]);
        $this->b('L2', 'B03')->forceFill(['capacity_qty' => 4])->save();
        $saran = $planner->saranBin($this->baut, $this->gudang, 5);
        $this->assertTrue($saran['penuh']);
        $this->assertFalse($saran['dari_tempat_simpan']);
        $this->assertNotNull($saran['bin'], 'Tidak ditolak: aturan lama A-84 tetap menyarankan.');

        // Put-away memakai saran yang sama.
        $this->simpan($this->baut, [['tempat' => 'rak:'.$this->area->id]]);
        $this->assertSame('CKG-D-AB1-L1-AREA', Bin::query()->withoutGlobalScopes()->find($this->putDari($this->baut)->lines->first()->suggested_bin_id)->code);
    }

    #[Test]
    public function tc_wh_53_khusus_menolak_barang_lain_di_penyesuaian_dan_saldo_awal_opname_tidak(): void
    {
        $bin = $this->b('L1', 'B01');
        $this->simpan($this->baut, [['tempat' => 'bin:'.$bin->id, 'khusus' => true]]);
        $alasan = $this->alasan(ReasonContext::Adjustment);
        $baris = fn (Item $item, array $extra = []) => [['item_id' => $item->id, 'bin_id' => $bin->id, 'direction' => 'in', 'qty' => 3] + $extra];

        // Barang lain ditolak; barang pemiliknya boleh.
        $this->gagal(fn () => app(CreateStockAdjustment::class)->handle(['warehouse_id' => $this->gudang->id, 'reason_code_id' => $alasan], $baris($this->kabel), $this->makeUser()),
            'BR-WH-10', AdjustmentRuleException::class);
        app(CreateStockAdjustment::class)->handle(['warehouse_id' => $this->gudang->id, 'reason_code_id' => $alasan], $baris($this->baut), $this->makeUser());

        // Saldo awal = ADJ yang sama (A-192): ditolak juga.
        $this->gagal(fn () => app(CreateStockAdjustment::class)->handle(['warehouse_id' => $this->gudang->id, 'reason_code_id' => $alasan, 'notes' => 'Saldo awal'], $baris($this->kabel), $this->kepala()),
            'BR-WH-10', AdjustmentRuleException::class);

        // Seluruh rak khusus juga menutup bin-binnya.
        $this->simpan($this->semen, [['tempat' => 'rak:'.$this->area->id, 'khusus' => true]]);
        $binArea = Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-AB1-L1-AREA')->firstOrFail();
        $this->gagal(fn () => app(CreateStockAdjustment::class)->handle(['warehouse_id' => $this->gudang->id, 'reason_code_id' => $alasan],
            [['item_id' => $this->kabel->id, 'bin_id' => $binArea->id, 'direction' => 'in', 'qty' => 1]], $this->makeUser()), 'BR-WH-10', AdjustmentRuleException::class);

        // Opname mencatat kenyataan: ADJ asal opname tetap diposting.
        $adj = StockAdjustment::create(['number' => 'ADJ/UJI/1', 'warehouse_id' => $this->gudang->id, 'origin' => AdjustmentOrigin::Count,
            'reason_code_id' => $alasan, 'status' => StockAdjustmentStatus::Approved]);
        StockAdjustmentLine::create(['stock_adjustment_id' => $adj->id, 'item_id' => $this->kabel->id, 'bin_id' => $bin->id, 'qty_delta' => 2, 'stock_status' => 'available']);
        app(AdjustmentPoster::class)->post($adj, $this->kepala());
        $this->assertSame(2.0, $this->saldo($bin, $this->kabel));
    }

    #[Test]
    public function tc_wh_54_khusus_di_put_away_dan_buka_oleh_kepala_gudang_tercatat(): void
    {
        $bin = $this->b('L1', 'B01');
        $this->simpan($this->baut, [['tempat' => 'bin:'.$bin->id, 'khusus' => true]]);
        $put = $this->putDari($this->cat, 4);
        $baris = $put->lines->first();
        $this->assertNotSame((int) $bin->id, (int) $baris->suggested_bin_id, 'Saran tidak menunjuk bin khusus barang lain.');

        $isian = fn (array $x = []) => [$baris->id => ['bin_id' => $bin->id, 'override_reason' => 'Dekat pintu'] + $x];

        $this->gagal(fn () => app(CompletePutaway::class)->handle($put, $isian(), $this->makeUser()), 'BR-WH-10', ReceiptRuleException::class);
        // Staf dengan alasan tetap ditolak — butuh izin Kepala Gudang.
        $this->gagal(fn () => app(CompletePutaway::class)->handle($put, $isian(['buka_khusus' => 'Darurat']), $this->makeUser()), 'BR-WH-10', ReceiptRuleException::class);
        $this->assertSame(0, StorageDedicationOverride::query()->count());

        $kepala = $this->kepala();
        app(CompletePutaway::class)->handle($put, $isian(['buka_khusus' => 'Rak lain penuh, sementara']), $kepala);
        $this->assertSame(4.0, $this->saldo($bin, $this->cat));

        $catatan = StorageDedicationOverride::query()->sole();
        $this->assertSame([(int) $bin->id, (int) $this->cat->id, 'Rak lain penuh, sementara', 'putaway_task', $put->number, (int) $kepala->id],
            [(int) $catatan->bin_id, (int) $catatan->item_id, $catatan->reason, $catatan->document_type, $catatan->document_number, (int) $catatan->opened_by]);
        $this->assertNotNull($catatan->opened_at);
    }

    #[Test]
    public function tc_wh_55_menandai_khusus_ditolak_bila_barang_lain_sudah_bertempat(): void
    {
        $bin = $this->b('L1', 'B01');
        $this->simpan($this->semen, [['tempat' => 'bin:'.$bin->id]]);

        $this->gagal(fn () => $this->simpan($this->baut, [['tempat' => 'bin:'.$bin->id, 'khusus' => true]]), 'BR-WH-10');
        $this->gagal(fn () => $this->simpan($this->baut, [['tempat' => 'rak:'.$this->rak->id, 'khusus' => true]]), 'BR-WH-10');
        $this->simpan($this->baut, [['tempat' => 'rak:'.$this->rak->id]]);

        // Tempat di dalam tempat khusus barang lain ditolak.
        $this->simpan($this->kabel, [['tempat' => 'bin:'.$this->b('L3', 'B03')->id, 'khusus' => true]]);
        $this->gagal(fn () => $this->simpan($this->semen, [['tempat' => 'bin:'.$this->b('L3', 'B03')->id]]), 'BR-WH-10');
    }

    #[Test]
    public function tc_wh_46c_bin_yang_hanya_jadi_tempat_simpan_tetap_boleh_dihapus(): void
    {
        $bin = $this->b('L3', 'B03');
        $this->simpan($this->baut, [['tempat' => 'bin:'.$bin->id], ['tempat' => 'rak:'.$this->area->id]]);

        app(DeleteBin::class)->handle($bin, $this->kepala());

        $this->assertNull(Bin::query()->withoutGlobalScopes()->find($bin->id));
        $this->assertSame(['area'], ItemStorageLocation::query()->where('item_id', $this->baut->id)->get()->map->jenis()->all());
    }

    #[Test]
    public function tc_wh_56_mode_tata_letak_taruh_dan_lepas_barang_lewat_simpan_perubahan(): void
    {
        $kepala = $this->kepala();
        $this->actingAs($kepala);
        $aksi = app(ApplyLayoutChanges::class);
        $bin = $this->b('L2', 'B02');

        $hasil = $aksi->handle($this->gudang, [
            ['op' => 'tempat_barang', 'item' => $this->baut->id, 'tempat' => 'bin:'.$bin->id, 'khusus' => true],
            ['op' => 'tempat_barang', 'item' => $this->semen->id, 'tempat' => 'rak:'.$this->rak->id],
            ['op' => 'tempat_barang', 'item' => $this->kabel->id, 'tempat' => 'rak:'.$this->area->id],
        ], $kepala);
        $this->assertTrue($hasil['ok'], json_encode($hasil['galat']));

        // Muatan denah membawa barang per bin & per rak; isi rak membawa nama ("Barang di sini").
        $data = app(WarehouseLayoutData::class);
        $denah = collect($data->payload($this->gudang)['zones'])->firstWhere('code', 'D');
        $rak = collect($denah['racks'])->firstWhere('code', 'R01');
        $this->assertSame([['id' => (int) $this->semen->id, 'code' => 'SEMEN-PCC', 'k' => false]], $rak['barang']);
        $petak = collect($rak['levels'])->flatMap(fn ($l) => $l['bins'])->firstWhere('id', $bin->id);
        $this->assertSame([['id' => (int) $this->baut->id, 'code' => 'BAUT-M12', 'k' => true]], $petak['barang']);
        $detail = $data->rakDetail($this->gudang, (int) $this->rak->id);
        $this->assertSame('SEMEN-PCC', $detail['barang'][0]['code']);
        $this->assertTrue(collect($detail['levels'])->flatMap(fn ($l) => $l['bins'])->firstWhere('id', $bin->id)['barang'][0]['khusus']);

        // Satu salah = tidak ada yang tersimpan.
        $salah = $aksi->handle($this->gudang, [
            ['op' => 'lepas_barang', 'item' => $this->semen->id, 'tempat' => 'rak:'.$this->rak->id],
            ['op' => 'tempat_barang', 'item' => $this->genset->id, 'tempat' => 'bin:'.$bin->id],
        ], $kepala);
        $this->assertFalse($salah['ok']);
        $this->assertSame('barang:'.$this->genset->id.'@bin:'.$bin->id, $salah['galat'][0]['objek']);
        $this->assertSame(1, ItemStorageLocation::query()->where('item_id', $this->semen->id)->count());

        $this->assertTrue($aksi->handle($this->gudang, [['op' => 'lepas_barang', 'item' => $this->semen->id, 'tempat' => 'rak:'.$this->rak->id]], $kepala)['ok']);
        $this->assertSame(0, ItemStorageLocation::query()->where('item_id', $this->semen->id)->count());
    }
}
