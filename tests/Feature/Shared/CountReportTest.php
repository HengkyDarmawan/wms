<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Count\Actions\ApproveStockCount;
use App\Domain\Count\Actions\ReconcileStockCount;
use App\Domain\Count\Actions\RecordCountRootCause;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-RPT-09 — laporan §9 modul Count/Adjustment (Sisa Fase 1 2d, A-241):
 * tren akurasi, top selisih, akar masalah, dan ADJ per alasan membaca sesi
 * opname yang direkonsiliasi dan ADJ yang diposting.
 */
class CountReportTest extends TenantTestCase
{
    use ApprovalFixtures;
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    private function rows(string $kunci, array $filters = [])
    {
        return app(ReportRegistry::class)->find($kunci)->rows($filters);
    }

    #[Test]
    public function tc_rpt_09_laporan_opname_dan_penyesuaian(): void
    {
        $registry = app(ReportRegistry::class);
        $admin = $this->makeUser('company_admin');
        $admin->forgetPermissionCache();
        $kunci = ['tren-akurasi', 'top-selisih', 'akar-masalah', 'adj-per-alasan'];

        foreach ($kunci as $k) {
            $laporan = $registry->find($k);
            $this->actingAs($admin)->get($this->tenantUrl('reports/'.$k))->assertOk()->assertSee($laporan->title());
            $this->actingAs($admin)->get($this->tenantUrl('reports/'.$k.'/export'))->assertOk();
            $this->actingAs($admin);
            $this->assertTrue($laporan->rows([])->isEmpty(), $k.' kosong tanpa data.');
        }

        // Sesi dua bin: baut −20 dan genset hilang −1 (besar), semen & pipa cocok.
        $sesi = $this->sesiBerjalan();
        $sesi = $this->hitungPutaran($sesi, 1, [
            $this->baris($sesi, $this->baut)->id => 80,
            $this->baris($sesi, $this->genset)->id => 0,
        ]);

        foreach ([$this->baut, $this->genset] as $item) {
            app(RecordCountRootCause::class)->handle($this->baris($sesi, $item), 'damaged_lost', 'Ditemukan rusak', $this->kepala);
        }

        $sesi = app(ReconcileStockCount::class)->handle($sesi, $this->auditor);
        app(ApproveStockCount::class)->approve($sesi, $this->kepala);

        // ADJ manual: 5 baut ditemukan.
        $adj = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 5]]);
        app(ApproveStockAdjustment::class)->approve($adj, $this->kepala);

        $this->actingAs($admin);

        $tren = $this->rows('tren-akurasi')->sole();
        $this->assertSame([now()->lokal()->format('m/Y'), 'CKG', 1, 4, 2, 50.0, 2],
            [$tren['bulan'], $tren['gudang'], $tren['sesi'], $tren['dihitung'], $tren['cocok'], $tren['akurasi'], $tren['besar']]);

        $top = $this->rows('top-selisih');
        $this->assertCount(2, $top, 'Hanya baris berselisih.');
        $this->assertSame(['BAUT-OPN', 100.0, 80.0, -20.0, 'Besar', 'Rusak/hilang'],
            [$top[0]['item'], $top[0]['sistem'], $top[0]['hitung'], $top[0]['selisih'], $top[0]['kelas'], $top[0]['akar']]);
        $this->assertSame('GENSET-OPN', $top[1]['item'], 'Urut selisih mutlak terbesar.');

        $akar = $this->rows('akar-masalah')->sole();
        $this->assertSame(['Rusak/hilang', 2, 1, -21.0, 0.0, 21.0], [$akar['akar'], $akar['baris'], $akar['sesi'], $akar['kurang'], $akar['lebih'], $akar['mutlak']]);

        $alasan = $this->rows('adj-per-alasan');
        $this->assertCount(2, $alasan, 'ADJ opname dan ADJ manual beralasan berbeda.');
        $ditemukan = $alasan->first(fn ($r) => str_starts_with($r['alasan'], 'FOUND'));
        $this->assertSame([1, 1, 5.0, 0.0], [$ditemukan['adj'], $ditemukan['baris'], $ditemukan['masuk'], $ditemukan['keluar']]);
        $opname = $alasan->first(fn ($r) => ! str_starts_with($r['alasan'], 'FOUND'));
        $this->assertSame([1, 2, 0.0, 21.0], [$opname['adj'], $opname['baris'], $opname['masuk'], $opname['keluar']]);

        // Periode lain dan gudang lain: kosong.
        $besok = ['date_from' => now()->addMonth()->toDateString(), 'date_to' => now()->addMonths(2)->toDateString()];
        foreach ($kunci as $k) {
            $this->assertTrue($this->rows($k, $besok)->isEmpty(), $k.' mengikuti periode.');
        }

        $lain = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS', 'name' => 'Gudang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $this->actingAs($this->makeUser('warehouse_head', ScopeType::Warehouse, $lain->id));
        foreach ($kunci as $k) {
            $this->assertTrue($this->rows($k)->isEmpty(), $k.' dibatasi cakupan gudang (BR-ACC-05).');
        }

        // Izin: driver tidak memegang count.view maupun adjustment.view.
        $driver = $this->makeUser('driver');
        $this->actingAs($driver)->get($this->tenantUrl('reports/tren-akurasi'))->assertForbidden();
        $this->actingAs($driver)->get($this->tenantUrl('reports/adj-per-alasan'))->assertForbidden();
    }
}
