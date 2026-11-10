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
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
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

    #[Test]
    public function tc_f2_13_meter_melewati_batas_memajukan_jadwal(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        // Meter GNS-01 = 100 jam; servis tiap 40 jam → jatuh tempo meter 140, tanggal cadangan +30 hari.
        $jadwal = $this->servis()->plan($this->gns, ['scheduled_at' => now()->addDays(30)->toDateString(), 'interval_meter' => '40'], $staf);
        $this->assertSame(140.0, (float) $jadwal->due_meter);

        $this->pinjamkan();
        $this->kembalikan();
        $this->periksa($this->ast(), ['meter_in' => 150]);

        $jadwal->refresh();
        $this->assertSame(now()->toDateString(), $jadwal->scheduled_at->toDateString(), 'Meter 150 ≥ 140: jadwal dimajukan ke hari ini.');
        $this->assertStringContainsString('melewati batas 140', (string) $jadwal->notes);

        // Aset dipilah dulu dari bin Retur ke rak (servis hanya dimulai dari bin penyimpanan).
        app(StockLedger::class)->post(new MovementRequest(item: $this->genset, qtyBase: 1, fromBinId: $this->binRetur()->id, toBinId: $this->binB->id,
            serialId: $this->gns->id, documentType: 'bin_move'));

        // Selesai: jadwal berikutnya ikut berinterval meter dari meter saat selesai.
        $this->servis()->finish($this->servis()->start($jadwal, $staf), $this->binB->id, $staf);
        $berikut = MaintenanceSchedule::query()->where('serial_id', $this->gns->id)->open()->sole();
        $this->assertSame(190.0, (float) $berikut->due_meter, 'Meter selesai 150 + 40.');
        $this->assertSame(150.0, (float) $jadwal->refresh()->meter_done);
    }

    #[Test]
    public function tc_f2_14_interval_meter_hanya_untuk_aset_bermeter(): void
    {
        $this->gns->forceFill(['meter_unit' => 'none'])->save();
        $this->gagalAset(fn () => $this->servis()->plan($this->gns->refresh(), ['scheduled_at' => now()->toDateString(), 'interval_meter' => '10'], $this->makeUser('warehouse_staff')), 'BR-AST-07');
    }
}
