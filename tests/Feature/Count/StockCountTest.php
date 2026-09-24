<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Count\Actions\AssignCounter;
use App\Domain\Count\Actions\CancelStockCount;
use App\Domain\Count\Actions\RecordCount;
use App\Domain\Count\Actions\StartStockCount;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Enums\VarianceClass;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Support\CountVisibility;
use App\Domain\Count\Support\VarianceClassifier;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-OPN-01 s.d. TC-OPN-09 dan TC-OPN-15 — sesi opname dari rencana sampai
 * siap rekonsiliasi: cakupan, pembekuan, snapshot, hitung buta, klasifikasi
 * selisih, hitung ulang oleh orang berbeda, pembatalan (Katalog §2.13).
 */
class StockCountTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    #[Test]
    public function tc_opn_01_sesi_direncanakan_dengan_cakupan_dan_tim(): void
    {
        $sesi = $this->sesi();

        $this->assertSame(StockCountStatus::Planned, $sesi->status);
        $this->assertMatchesRegularExpression('#^OPN/CKG/\d{4}/0001$#', $sesi->number);
        $this->assertFalse($sesi->is_audit);
        $this->assertSame([$this->gudang->id], $sesi->warehouses()->pluck('warehouses.id')->all());
        $this->assertSame([$this->staf1->id, $this->staf2->id], $sesi->teamIds());

        // Pemeriksaan mendadak: tanpa pembekuan, wajib daftar bin/item (BR-OPN-10).
        $spot = $this->sesi(['count_type' => 'spot_check', 'freeze_bins' => true]);
        $this->assertFalse($spot->freeze_bins);

        $this->tolak(fn () => $this->sesi(['count_type' => 'spot_check', 'bin_ids' => []]), 'BR-OPN-10');
        $this->tolak(fn () => $this->sesi(['count_type' => 'cycle_abc']), 'BR-GEN-10');
        $this->tolak(fn () => $this->sesi(['team_user_ids' => []]), 'BR-OPN-05');
        $this->tolak(fn () => $this->sesi(['team_user_ids' => [$this->makeUser('driver')->id]]), 'BR-GEN-09');

        $bks = app(SaveWarehouse::class)->handle(null, ['code' => 'BKS', 'name' => 'Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id')]);
        $binBks = Bin::create(['warehouse_id' => $bks->id, 'code' => 'BKS-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $this->tolak(fn () => $this->sesi(['bin_ids' => [$binBks->id]]), 'BR-OPN-01');

        // Penghitung harus boleh mengakses gudang cakupan (BR-GEN-09).
        $stafBks = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $bks->id);
        $this->tolak(fn () => $this->sesi(['team_user_ids' => [$stafBks->id]]), 'BR-GEN-09');

        $multi = $this->sesi(['warehouse_ids' => [$this->gudang->id, $bks->id], 'bin_ids' => [], 'team_user_ids' => [$this->auditor->id]], $this->auditor);
        $this->assertStringStartsWith('OPN/ALL/', $multi->number, 'Lintas gudang memakai segmen ALL (BR-GEN-06).');
        $this->assertTrue($multi->is_audit, 'Sesi yang dibuat Auditor adalah sesi audit (BR-OPN-09).');
    }

    #[Test]
    public function tc_opn_02_mulai_membekukan_bin_dan_snapshot_saldo_fisik(): void
    {
        $this->binB->forceFill(['count_flag' => true])->save();

        // Barang di Loading Area ikut dihitung (BR-OPN-01).
        $stg = Bin::query()->where('warehouse_id', $this->gudang->id)->where('bin_type', BinType::Staging->value)->firstOrFail();
        $this->masuk($this->baut, 7, $stg);

        $sesi = $this->sesiBerjalan(['bin_ids' => []]);

        $this->assertSame(StockCountStatus::InProgress, $sesi->status);
        $this->assertNotNull($sesi->started_at);

        foreach ([$this->binA, $this->binB, $stg] as $bin) {
            $bin->refresh();
            $this->assertSame(BinStatus::Frozen, $bin->bin_status, 'Bin '.$bin->code.' dibeku (BR-OPN-02).');
            $this->assertSame($sesi->id, (int) $bin->frozen_by_count_id);
        }

        $this->assertFalse(Bin::query()->where('warehouse_id', $this->gudang->id)->where('bin_type', 'in_transit')->firstOrFail()->bin_status === BinStatus::Frozen,
            'Bin virtual tidak ikut dihitung.');

        $this->assertSame(100.0, (float) $this->baris($sesi, $this->baut, $this->binA)->system_qty);
        $this->assertSame(7.0, (float) $this->baris($sesi, $this->baut, $stg)->system_qty);
        $this->assertSame(50.0, (float) $this->baris($sesi, $this->semen)->system_qty);
        $this->assertSame($this->lot->id, (int) $this->baris($sesi, $this->semen)->lot_id);
        $this->assertSame(1.0, (float) $this->baris($sesi, $this->genset)->system_qty);
        $this->assertSame(6.0, (float) $this->baris($sesi, $this->pipa)->system_qty);

        $tugas = CountAssignment::query()->where('stock_count_id', $sesi->id)->orderBy('id')->get();
        $this->assertSame($this->binB->id, (int) $tugas->first()->bin_id, 'Bin berpenanda hitung didahulukan (A-67).');
        $this->assertEqualsCanonicalizing([$this->staf1->id, $this->staf2->id], $tugas->take(2)->pluck('counter_user_id')->map(fn ($v) => (int) $v)->all(),
            'Penugasan dibagi bergiliran ke tim.');
    }

    #[Test]
    public function tc_opn_03_pck_berjalan_menahan_mulai_kecuali_tanpa_pembekuan(): void
    {
        $pck = PickTask::create(['number' => 'PCK/CKG/2609/0099', 'warehouse_id' => $this->gudang->id,
            'source_type' => 'material_request', 'source_id' => 1, 'status' => PickTaskStatus::InProgress]);
        PickTaskLine::create(['pick_task_id' => $pck->id, 'source_line_id' => 1, 'item_id' => $this->baut->id, 'bin_id' => $this->binA->id, 'qty_allocated' => 5]);

        $sesi = $this->sesi();
        $this->tolak(fn () => app(StartStockCount::class)->handle($sesi, $this->kepala), 'BR-OPN-02');
        $this->assertSame(StockCountStatus::Planned, $sesi->refresh()->status);

        // Pemeriksaan mendadak tidak membekukan, jadi PCK berjalan tidak menahan (BR-OPN-10).
        $spot = app(StartStockCount::class)->handle($this->sesi(['count_type' => 'spot_check']), $this->auditor);
        $this->assertSame(StockCountStatus::InProgress, $spot->status);
        $this->assertSame(BinStatus::Active, $this->binA->refresh()->bin_status);
    }

    #[Test]
    public function tc_opn_04_bin_beku_menolak_pergerakan_dan_picking(): void
    {
        $this->sesiBerjalan(['bin_ids' => [$this->binA->id]]);

        foreach ([
            new MovementRequest(item: $this->baut, qtyBase: 1, fromBinId: $this->binA->id),
            new MovementRequest(item: $this->baut, qtyBase: 1, toBinId: $this->binA->id),
            new MovementRequest(item: $this->baut, qtyBase: 1, fromBinId: $this->binA->id, toBinId: $this->binB->id),
        ] as $gerak) {
            try {
                app(StockLedger::class)->post($gerak);
                $this->fail('Bin beku harus menolak pergerakan (BR-OPN-02).');
            } catch (LedgerException $e) {
                $this->assertSame('BR-OPN-02', $e->rule);
            }
        }

        // Bin di luar cakupan tetap bebas.
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 1, toBinId: $this->binB->id));
        $this->assertSame(1.0, $this->saldoBin($this->binB, $this->baut));

        // PCK dari bin beku ditolak saat mulai (ProcessPickTask, BR-OPN-02).
        $pck = PickTask::create(['number' => 'PCK/CKG/2609/0098', 'warehouse_id' => $this->gudang->id,
            'source_type' => 'material_request', 'source_id' => 1, 'status' => PickTaskStatus::Pending]);
        PickTaskLine::create(['pick_task_id' => $pck->id, 'source_line_id' => 1, 'item_id' => $this->baut->id, 'bin_id' => $this->binA->id, 'qty_allocated' => 5]);

        try {
            app(\App\Domain\Shipment\Actions\ProcessPickTask::class)->start($pck, $this->staf1);
            $this->fail('PCK dari bin beku harus ditolak.');
        } catch (\App\Domain\Shipment\Exceptions\ShipmentRuleException $e) {
            $this->assertSame('BR-OPN-02', $e->rule);
        }
    }

    #[Test]
    public function tc_opn_05_hitung_buta_hanya_penghitung_yang_ditugaskan(): void
    {
        $sesi = $this->sesiBerjalan();
        $tugas = CountAssignment::query()->where('stock_count_id', $sesi->id)->where('counter_user_id', $this->staf1->id)->firstOrFail();
        $lain = $tugas->counter_user_id === $this->staf1->id ? $this->staf2 : $this->staf1;

        $visibility = app(CountVisibility::class);
        $this->assertFalse($visibility->canSeeNumbers($sesi, $this->staf1), 'Penghitung tidak melihat angka sistem.');
        $this->assertFalse($visibility->canSeeNumbers($sesi, $this->makeUser('warehouse_staff')), 'Staf tanpa izin rekonsiliasi juga tidak.');
        $this->assertTrue($visibility->canSeeNumbers($sesi, $this->kepala), 'Perekonsiliasi yang tidak menghitung boleh melihat.');

        $baris = $tugas->linesQuery()->first();
        $this->tolak(fn () => app(RecordCount::class)->save($tugas, [$baris->id => 1], $lain), 'BR-OPN-05');
        $this->tolak(fn () => app(RecordCount::class)->save($tugas, [$baris->id => -1], $this->staf1), 'BR-LED-02');
    }

    #[Test]
    public function tc_opn_06_selesai_hitung_menuntut_semua_baris_serial_ada_tidak_dan_temuan(): void
    {
        $sesi = $this->sesiBerjalan(['bin_ids' => [$this->binB->id], 'team_user_ids' => [$this->staf1->id]]);
        $tugas = CountAssignment::query()->where('stock_count_id', $sesi->id)->sole();
        $aksi = app(RecordCount::class);

        $semen = $this->baris($sesi, $this->semen);
        $genset = $this->baris($sesi, $this->genset);

        $aksi->save($tugas, [$semen->id => 48], $this->staf1);
        $this->tolak(fn () => $aksi->finish($tugas->refresh(), $this->staf1), 'BR-OPN-05');

        // Serial dihitung ada/tidak ada: isian 5 berarti "ada" = 1 unit.
        $aksi->save($tugas, [$genset->id => 5], $this->staf1);
        $this->assertSame(1.0, (float) $genset->refresh()->counted_qty_r1);

        // Temuan: item tanpa pelacakan boleh, serial tidak (BR-LED-03).
        $temuan = $aksi->addLine($tugas, $this->baut->id, null, 3, $this->staf1);
        $this->assertTrue($temuan->is_unexpected);
        $this->assertSame(0.0, (float) $temuan->system_qty);
        $this->tolak(fn () => $aksi->addLine($tugas, $this->genset->id, null, 1, $this->staf1), 'BR-LED-03');
        $this->tolak(fn () => $aksi->addLine($tugas, $this->semen->id, 'LOT-TAK-ADA', 1, $this->staf1), 'BR-LED-03');
        $this->tolak(fn () => $aksi->addLine($tugas, $this->semen->id, 'LOT-OPN-1', 1, $this->staf1), 'BR-OPN-01');

        $aksi->finish($tugas->refresh(), $this->staf1);
        $this->assertTrue($tugas->refresh()->isDone());
        $this->tolak(fn () => $aksi->save($tugas, [$semen->id => 50], $this->staf1), 'BR-OPN-05');
    }

    #[Test]
    public function tc_opn_07_klasifikasi_ambang_relatif_dan_absolut(): void
    {
        $k = app(VarianceClassifier::class);
        $baut = $this->baut->refresh();

        $this->assertNull($k->classify(100, 100, $baut)['class'], 'Tanpa selisih tanpa kelas.');
        $this->assertSame(VarianceClass::Minor, $k->classify(100, 99, $baut)['class'], '1 % dan 1 unit = kecil.');
        $this->assertSame(VarianceClass::Moderate, $k->classify(1000, 995, $baut)['class'], '0,5 % tetapi 5 unit > ambang absolut = sedang.');
        $this->assertSame(VarianceClass::Moderate, $k->classify(100, 96, $baut)['class'], '4 % = sedang.');
        $this->assertSame(VarianceClass::Major, $k->classify(100, 90, $baut)['class'], '10 % = besar.');
        $this->assertSame(VarianceClass::Major, $k->classify(0, 1, $baut)['class'], 'Angka sistem nol = selisih relatif tak terhingga.');
        $this->assertSame(-10.0, $k->classify(100, 90, $baut)['pct']);

        // Ambang kategori (diwarisi dari induk) menang atas ambang company (BR-OPN-04).
        $induk = ItemCategory::create(['code' => 'OPN-IND', 'name' => 'Induk', 'is_active' => true, 'tolerance_pct' => 10, 'tolerance_abs' => 20]);
        $anak = ItemCategory::create(['code' => 'OPN-ANK', 'name' => 'Anak', 'parent_id' => $induk->id, 'is_active' => true]);
        $baut->forceFill(['item_category_id' => $anak->id])->save();
        $this->assertSame(VarianceClass::Minor, app(VarianceClassifier::class)->classify(100, 92, $baut->refresh())['class']);

        CompanySetting::put(VarianceClassifier::BATAS_SEDANG, 20);
        $this->assertSame(VarianceClass::Moderate, app(VarianceClassifier::class)->classify(100, 85, $this->semen->refresh())['class']);
    }

    #[Test]
    public function tc_opn_08_selisih_sedang_hitung_ulang_oleh_penghitung_berbeda(): void
    {
        $sesi = $this->sesiBerjalan(['bin_ids' => [$this->binA->id], 'team_user_ids' => [$this->staf1->id, $this->staf2->id]]);
        $pertama = CountAssignment::query()->where('stock_count_id', $sesi->id)->sole();
        $baut = $this->baris($sesi, $this->baut);

        $sesi = $this->hitungPutaran($sesi, 1, [$baut->id => 96]);

        $this->assertSame(StockCountStatus::Recount, $sesi->status, 'Selisih sedang → hitung ulang otomatis (Katalog §2.13).');
        $this->assertTrue($baut->refresh()->is_recount);
        $this->assertSame(VarianceClass::Moderate, $baut->variance_class);
        $this->assertFalse($this->baris($sesi, $this->pipa)->is_recount, 'Baris tanpa selisih tidak dihitung ulang.');

        $ulang = CountAssignment::query()->where('stock_count_id', $sesi->id)->where('round', 2)->sole();
        $this->assertNotSame((int) $pertama->counter_user_id, (int) $ulang->counter_user_id, 'Penghitung ulang berbeda (BR-OPN-05).');
        $this->assertSame(1, $ulang->linesQuery()->count(), 'Putaran 2 hanya baris hitung ulang.');

        $this->tolak(fn () => app(AssignCounter::class)->handle($ulang, (int) $pertama->counter_user_id, $this->kepala), 'BR-OPN-05');
        $this->tolak(fn () => app(AssignCounter::class)->handle($ulang, $this->makeUser('driver')->id, $this->kepala), 'BR-GEN-09');

        $stafLain = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        app(AssignCounter::class)->handle($ulang, $stafLain->id, $this->kepala);
        $this->assertContains($stafLain->id, $sesi->refresh()->teamIds(), 'Penghitung pengganti masuk tim.');

        $sesi = $this->hitungPutaran($sesi, 2, [$baut->id => 99]);

        $baut->refresh();
        $this->assertSame(99.0, (float) $baut->counted_qty_r2);
        $this->assertSame(99.0, (float) $baut->final_qty, 'Angka akhir = hitung ulang.');
        $this->assertSame(VarianceClass::Minor, $baut->variance_class);
        $this->assertSame(StockCountStatus::Recount, $sesi->status, 'Tetap Hitung Ulang sampai direkonsiliasi.');
    }

    #[Test]
    public function tc_opn_09_klasifikasi_sesi_setelah_putaran_pertama(): void
    {
        $sesi = $this->sesiBerjalan();

        $this->hitungPutaran($sesi, 1, [
            $this->baris($sesi, $this->baut)->id => 80,       // besar
            $this->baris($sesi, $this->genset)->id => 0,      // serial hilang: besar
        ]);

        $sesi->refresh();
        $this->assertSame(StockCountStatus::InProgress, $sesi->status, 'Tanpa selisih sedang tidak ada hitung ulang.');
        $this->assertSame(VarianceClass::Major, $this->baris($sesi, $this->baut)->variance_class);
        $this->assertSame(-20.0, (float) $this->baris($sesi, $this->baut)->variance_qty);
        $this->assertSame(-1.0, (float) $this->baris($sesi, $this->genset)->variance_qty);
        $this->assertNull($this->baris($sesi, $this->semen)->variance_class);
        $this->assertSame(0, CountLine::query()->where('stock_count_id', $sesi->id)->whereNull('final_qty')->count());
    }

    #[Test]
    public function tc_opn_15_batal_hanya_saat_direncanakan_dengan_alasan(): void
    {
        $sesi = $this->sesi();
        $aksi = app(CancelStockCount::class);

        $this->tolak(fn () => $aksi->handle($sesi, null, null, $this->kepala), 'BR-GEN-11');

        $aksi->handle($sesi, $this->alasanId(ReasonContext::Cancel), 'Jadwal mundur', $this->kepala);
        $this->assertSame(StockCountStatus::Cancelled, $sesi->refresh()->status);
        $this->assertNotNull($sesi->cancel_reason_id);

        $jalan = $this->sesiBerjalan();
        $this->tolak(fn () => $aksi->handle($jalan, $this->alasanId(ReasonContext::Cancel), null, $this->kepala), 'BR-GEN-01');
    }

    private function tolak(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (CountRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }
}
