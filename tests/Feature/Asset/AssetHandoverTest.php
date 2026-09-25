<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Domain\Asset\Actions\UpdateAssetHandover;
use App\Domain\Asset\Actions\UpdateAssetProfile;
use App\Domain\Asset\Policies\AssetHandoverPolicy;
use App\Domain\Asset\Policies\AssetPolicy;
use App\Domain\Master\Enums\MeterUnit;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Serial;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Enums\PodUnitCondition;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Livewire\ShipmentDetail;
use App\Domain\Shipment\Models\ProofOfDeliveryUnit;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Asset\Concerns\AssetFixtures;
use Tests\TenantTestCase;

/**
 * TC-AST-08 dan TC-AST-09 — melengkapi serah terima (tanggal kembali, meter
 * & grade keluar), lewat jatuh tempo dan laporan Aset dipinjamkan (BR-AST-06),
 * profil masa pakai dan peringatan sisa umur (BR-AST-08, A-66).
 */
class AssetHandoverTest extends TenantTestCase
{
    use AssetFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAset();
    }

    #[Test]
    public function tc_ast_08_lengkapi_serah_terima_lewat_jatuh_tempo_dan_laporan(): void
    {
        $this->pinjamkan();
        $ast = $this->ast();
        $kepala = $this->makeUser('warehouse_head');
        $aksi = app(UpdateAssetHandover::class);

        $this->assertTrue(app(AssetHandoverPolicy::class)->update($kepala, $ast));
        $this->assertFalse(app(AssetHandoverPolicy::class)->update($this->makeUser('warehouse_staff'), $ast), 'asset.manage hanya Kepala Gudang & Admin.');

        $this->gagalAset(fn () => $aksi->handle($ast, ['due_return_date' => now()->subDays(5)->toDateString()], $kepala), 'BR-AST-06');
        $this->gagalAset(fn () => $aksi->handle($ast, ['meter_out' => -1], $kepala), 'BR-AST-08');
        $this->gagalAset(fn () => $aksi->handle($ast, ['condition_out' => 'Z'], $kepala), 'BR-AST-03');

        $ast = $aksi->handle($ast, ['due_return_date' => now()->toDateString(), 'meter_out' => 105, 'condition_out' => 'B', 'notes' => 'Serah ke mandor'], $kepala);
        $this->assertSame(105.0, (float) $ast->meter_out);
        $this->assertSame('B', $ast->condition_out);
        $this->assertSame(now()->toDateString(), $this->gns->refresh()->due_return_date->toDateString());
        $this->assertFalse($ast->isOverdue(), 'Jatuh tempo hari ini belum lewat.');

        // Lewat jatuh tempo (BR-AST-06): laporan harian aset dipinjamkan.
        $ast->forceFill(['checked_out_at' => now()->subDays(10), 'due_return_date' => now()->subDays(2)->toDateString()])->save();
        $this->gns->forceFill(['due_return_date' => now()->subDays(2)->toDateString()])->save();
        $this->assertTrue($ast->refresh()->isOverdue());
        $this->assertTrue($this->gns->refresh()->needsAttention());
        $this->assertSame(1, Serial::query()->overdue()->count());

        $this->actingAs($kepala);
        $baris = app(ReportRegistry::class)->find('aset-dipinjamkan')->rows(['overdue' => '1'])->sole();
        $this->assertSame($ast->number, $baris['ast']);
        $this->assertSame(2, $baris['lewat_hari']);
        $this->assertSame(11, $baris['hari_pakai']);
        $this->assertSame('Lewat jatuh tempo', $baris['status']);

        // Setelah kembali, serah terima tidak bisa diubah lagi.
        $this->kembalikan();
        $this->gagalAset(fn () => $aksi->handle($this->ast(), ['meter_out' => 1], $kepala), 'BR-GEN-01');
        $this->assertSame([], app(ReportRegistry::class)->find('aset-dipinjamkan')->rows([])->all());
    }

    #[Test]
    public function tc_ast_09_profil_masa_pakai_dan_peringatan_sisa_umur(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $aksi = app(UpdateAssetProfile::class);

        $this->gagalAset(fn () => $aksi->handle($this->gns, ['meter_unit' => 'liter'], $kepala), 'BR-AST-08');
        $this->gagalAset(fn () => $aksi->handle($this->gns, ['meter_unit' => 'hour', 'expected_life_days' => -5], $kepala), 'BR-AST-08');

        $gns = $aksi->handle($this->gns, ['acquired_at' => now()->subDays(850)->toDateString(), 'meter_unit' => 'hour', 'meter_total' => 100, 'expected_life_days' => 1000, 'expected_life_hours' => 2000], $kepala);
        $this->assertSame(15.0, $gns->remainingLifePercent(), 'max(850/1000, 100/2000) = 85 % terpakai.');
        $this->assertTrue($gns->isLifeAlert(), 'Di bawah ambang bawaan 20 %.');

        CompanySetting::put('asset_life_alert_pct', 10);
        $this->assertFalse($gns->isLifeAlert());

        // Selama dipinjam, satuan & akumulasi meter dikunci.
        $this->pinjamkan();
        $this->gagalAset(fn () => $aksi->handle($this->gns->refresh(), ['meter_unit' => 'km', 'meter_total' => 100], $kepala), 'BR-AST-08');
        $gns = $aksi->handle($this->gns, ['meter_unit' => 'hour', 'meter_total' => 100, 'expected_life_days' => 1200, 'acquired_at' => now()->subDays(850)->toDateString()], $kepala);
        $this->assertSame(1200, $gns->expected_life_days);
        $this->assertSame(MeterUnit::Hour, $gns->meter_unit);

        $this->assertFalse(app(AssetPolicy::class)->update($this->makeUser('warehouse_staff'), $gns));
        $this->gagalAset(fn () => $aksi->handle(Serial::create(['item_id' => $this->kabel->id, 'serial_no' => 'KBL-1']), [], $kepala), 'BR-STK-08');
    }

    #[Test]
    public function tc_sj_18_bukti_terima_serial_dinilai_per_unit(): void
    {
        $sj = $this->terkirimKeKlien($this->genset, 1, ['line_ownership' => 'loan']);
        $baris = $sj->lines()->sole();
        $aksi = app(ConfirmDelivery::class);

        // Satu unit tidak boleh dibagi baik/rusak (BR-SJ-05, A-244).
        try {
            $aksi->handle($sj, ['received_by_name' => 'Mandor'], [['shipment_line_id' => $baris->id, 'qty_good' => 0.5, 'qty_damaged' => 0.5, 'damage_photo_path' => 'uji/rusak.jpg']], $this->makeUser('driver'));
            $this->fail('Unit serial tidak boleh dibagi.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-05', $e->rule);
        }

        // Layar driver menawarkan satu pilihan kondisi untuk baris serial.
        $driver = $this->makeUser('driver');
        Livewire::actingAs($driver)->test(ShipmentDetail::class, ['shipment' => $sj])
            ->call('mintaDialog', 'terima')
            ->assertSet('terima.'.$baris->id.'.kondisi', 'good')
            ->assertSee('GNS-01');

        $bukti = $aksi->handle($sj, ['received_by_name' => 'Mandor'], [['shipment_line_id' => $baris->id, 'qty_good' => 1]], $driver);
        $unit = ProofOfDeliveryUnit::query()->where('proof_of_delivery_line_id', $bukti->lines()->sole()->id)->sole();
        $this->assertSame((int) $this->gns->id, (int) $unit->serial_id);
        $this->assertSame(PodUnitCondition::Good, $unit->condition);
    }
}
