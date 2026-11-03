<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Asset\Actions\MaintainAsset;
use App\Domain\Asset\Enums\MaintenanceSource;
use App\Domain\Asset\Enums\MaintenanceStatus;
use App\Domain\Asset\Models\MaintenanceSchedule;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Asset\Concerns\AssetFixtures;
use Tests\TenantTestCase;

/**
 * TC-F2-01 s.d. TC-F2-05 — jadwal servis aset (BR-AST-07, A-423,
 * 33-pendukung-f2): servis memindah aset ke Karantina dan kembali lewat kartu
 * stok, state mengikuti lokasi (BR-AST-01).
 */
class AssetMaintenanceTest extends TenantTestCase
{
    use AssetFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAset();
    }

    private function servis(): MaintainAsset
    {
        return app(MaintainAsset::class);
    }

    private function binAset(): int
    {
        return (int) StockBalance::query()->withoutGlobalScopes()->where('serial_id', $this->gns->id)->where('qty_base', '>', 0)->value('bin_id');
    }

    #[Test]
    public function tc_f2_01_jadwalkan_mulai_selesai_lewat_kartu_stok(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $asal = $this->binAset();

        $jadwal = $this->servis()->plan($this->gns, ['scheduled_at' => now()->toDateString(), 'notes' => 'Ganti oli'], $staf);
        $this->assertSame(MaintenanceStatus::Planned, $jadwal->status);
        $this->assertSame(MaintenanceSource::Manual, $jadwal->source);

        $jadwal = $this->servis()->start($jadwal, $staf);
        $this->assertSame(MaintenanceStatus::InProgress, $jadwal->status);
        $this->assertSame($this->binSistem($this->gudang, BinType::Quarantine)->id, $this->binAset(), 'Aset pindah ke bin Karantina.');
        $this->assertSame(AssetState::Maintenance, $this->gns->refresh()->asset_state, 'State mengikuti bin (BR-AST-01).');
        $this->assertSame($asal, (int) $jadwal->origin_bin_id);

        $jadwal = $this->servis()->finish($jadwal, $asal, $staf);
        $this->assertSame(MaintenanceStatus::Done, $jadwal->status);
        $this->assertSame(now()->toDateString(), $jadwal->performed_at?->toDateString());
        $this->assertSame($asal, $this->binAset());
        $this->assertSame(AssetState::Available, $this->gns->refresh()->asset_state);
        $this->assertSame(2, StockMovement::query()->where('document_type', 'maintenance')->where('document_id', $jadwal->id)->count(), 'Dua baris kartu stok (P-01).');
        $this->assertSame(0, MaintenanceSchedule::query()->where('serial_id', $this->gns->id)->open()->count(), 'Tanpa interval tidak ada jadwal berikutnya.');
    }

    #[Test]
    public function tc_f2_02_servis_berkala_membuat_jadwal_berikutnya(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $jadwal = $this->servis()->plan($this->gns, ['scheduled_at' => now()->toDateString(), 'interval_days' => '30'], $staf);
        $this->servis()->finish($this->servis()->start($jadwal, $staf), $this->binB->id, $staf);

        $berikut = MaintenanceSchedule::query()->where('serial_id', $this->gns->id)->open()->sole();
        $this->assertSame(MaintenanceSource::Interval, $berikut->source);
        $this->assertSame(now()->addDays(30)->toDateString(), $berikut->scheduled_at->toDateString());
        $this->assertSame(30, (int) $berikut->interval_days);
    }

    #[Test]
    public function tc_f2_03_guard_jadwal_terbuka_aset_dipinjam_dan_bin_tujuan(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $jadwal = $this->servis()->plan($this->gns, ['scheduled_at' => now()->toDateString()], $staf);

        $this->gagalAset(fn () => $this->servis()->plan($this->gns, ['scheduled_at' => now()->toDateString()], $staf), 'BR-AST-07');
        $this->gagalAset(fn () => $this->servis()->plan($this->gns, ['scheduled_at' => ''], $staf), 'BR-GEN-11');
        $this->gagalAset(fn () => $this->servis()->finish($jadwal, $this->binB->id, $staf), 'BR-GEN-01');

        $jadwal = $this->servis()->start($jadwal, $staf);
        $this->gagalAset(fn () => $this->servis()->finish($jadwal, $this->binRetur()->id, $staf), 'BR-STK-02');

        // Aset yang sedang dipinjam proyek boleh dijadwalkan, tetapi servis baru bisa dimulai di gudang.
        $this->servis()->finish($jadwal, $this->binB->id, $staf);
        $this->pinjamkan();
        $lain = $this->servis()->plan($this->gns->refresh(), ['scheduled_at' => now()->toDateString()], $staf);
        $this->gagalAset(fn () => $this->servis()->start($lain, $staf), 'BR-AST-07');
    }

    #[Test]
    public function tc_f2_04_skor_pemeriksaan_rendah_menjadwalkan_servis(): void
    {
        $this->pinjamkan();
        $this->kembalikan();
        $this->periksa($this->ast(), ['condition_grade' => 'B', 'condition_score' => 40]);

        $jadwal = MaintenanceSchedule::query()->where('serial_id', $this->gns->id)->open()->sole();
        $this->assertSame(MaintenanceSource::Inspection, $jadwal->source);
        $this->assertSame(now()->toDateString(), $jadwal->scheduled_at->toDateString());
        $this->assertStringContainsString('40 %', (string) $jadwal->notes);
    }

    #[Test]
    public function tc_f2_04b_skor_baik_tidak_menjadwalkan(): void
    {
        $this->pinjamkan();
        $this->kembalikan();
        $this->periksa($this->ast(), ['condition_score' => 80]);

        $this->assertSame(0, MaintenanceSchedule::query()->where('serial_id', $this->gns->id)->count());
    }

    #[Test]
    public function tc_f2_05_tanpa_izin_atau_di_luar_cakupan_ditolak(): void
    {
        $this->gagalAset(fn () => $this->servis()->plan($this->gns, ['scheduled_at' => now()->toDateString()], $this->makeUser('internal_requester')), 'BR-GEN-09');

        $jadwal = $this->servis()->plan($this->gns, ['scheduled_at' => now()->toDateString()], $this->makeUser('warehouse_staff'));
        $luar = $this->makeUser('warehouse_staff', ScopeType::Warehouse, (int) $this->krw1->id);
        $this->gagalAset(fn () => $this->servis()->start($jadwal, $luar), 'BR-GEN-09');
    }
}
