<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Support\AssetCustody;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Asset\Concerns\AssetFixtures;
use Tests\TenantTestCase;

/**
 * TC-AST-01 s.d. TC-AST-05 — siklus aset: dicadangkan → dalam perjalanan →
 * dipinjam (AST `checked_out`, `asset_checked_out` diperkaya) → kembali
 * (AST `returned`, hari pakai) → diperiksa (grade/skor/meter/foto) → dipilah
 * sesuai hasil; state mengikuti lokasi (BR-AST-01/03/05/08, KS 2.11, A-164).
 */
class AssetLifecycleTest extends TenantTestCase
{
    use AssetFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAset();
    }

    #[Test]
    public function tc_ast_01_dicadangkan_dalam_perjalanan_lalu_dipinjam_dengan_ast(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = app(SaveRequest::class)->handle(null, ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $this->genset->id, 'qty_base' => 1, 'line_ownership' => 'loan']], $pemohon);
        $req->openLines()->first()->forceFill(['source_warehouse_id' => $this->gudang->id, 'fulfillment_source' => 'stock'])->save();
        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];

        $this->assertSame(AssetState::Reserved, $this->gns->refresh()->asset_state, 'Alokasi keras serial = Dicadangkan (BR-AST-01).');

        $pck = $this->jalankanPck($pck);
        $this->assertSame(AssetState::Reserved, $this->gns->refresh()->asset_state, 'Di Loading Area tetap Dicadangkan.');

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id, 'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B1234AS'])->id, 'driver_id' => $this->makeUser('driver')->id,
        ], $this->makeUser('warehouse_staff'));
        $sj = app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
        $this->assertSame(AssetState::InTransit, $this->gns->refresh()->asset_state);

        $sj = $this->terimaSj($sj);
        $this->gns->refresh();
        $this->assertSame(AssetState::OnLoan, $this->gns->asset_state);
        $this->assertSame((int) $this->proyek->id, (int) $this->gns->current_project_id);
        $this->assertSame(now()->addDays(30)->toDateString(), $this->gns->due_return_date?->toDateString(), 'Bawaan jatuh tempo = target selesai proyek (A-163).');

        $ast = $this->ast();
        $this->assertSame(AssetHandoverStatus::CheckedOut, $ast->status);
        $this->assertMatchesRegularExpression('#^AST/CKG/#', $ast->number);
        $this->assertSame(100.0, (float) $ast->meter_out, 'Meter keluar = akumulasi terakhir.');
        $this->assertSame('A', $ast->condition_out);
        $this->assertSame((int) $sj->id, (int) $ast->shipment_id);

        $k = StockEvent::query()->where('event_type', StockEventType::AssetCheckedOut->value)->sole();
        $this->assertSame($ast->number, $k->payload['handover_number'], 'Matriks §14: serial, proyek, jatuh tempo, meter keluar.');
        $this->assertSame('GNS-01', $k->payload['serial_no']);
        $this->assertSame(100.0, (float) $k->payload['meter_out']);
        $this->assertSame($this->gns->due_return_date->toDateString(), $k->payload['due_return_date']);
    }

    #[Test]
    public function tc_ast_02_kembali_ke_bin_retur_hari_pakai_dan_wajib_diperiksa(): void
    {
        $this->pinjamkan();
        $this->ast()->forceFill(['checked_out_at' => now()->subDays(3)])->save();

        $ret = $this->kembalikan();
        $ast = $this->ast();

        $this->assertSame(AssetHandoverStatus::Returned, $ast->status);
        $this->assertSame(4, $ast->usage_days, 'BR-AST-05: hari kalender inklusif hari pertama.');
        $this->assertSame((int) $ret->id, (int) $ast->goods_return_id);
        $this->assertSame(AssetState::Returned, $this->gns->refresh()->asset_state);
        $this->assertSame(1.0, $this->saldo($this->binRetur(), $this->genset));

        $this->gagalAset(fn () => $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'good', 'qty' => 1, 'target_bin_id' => $this->binB->id]]]), 'BR-AST-03');
        $this->assertSame(4, AssetCustody::usageDays(now()->subDays(3), now()));
    }

    #[Test]
    public function tc_ast_03_guard_pemeriksaan_dan_hasil_grade_c_maintenance(): void
    {
        $this->pinjamkan();
        $ret = $this->kembalikan();
        $ast = $this->ast();

        $this->gagalAset(fn () => $this->periksa($ast, ['condition_grade' => 'E']), 'BR-AST-03');
        $this->gagalAset(fn () => $this->periksa($ast, ['condition_score' => 150]), 'BR-AST-08');
        $this->gagalAset(fn () => $this->periksa($ast, ['component_notes' => '']), 'BR-AST-08');
        $this->gagalAset(fn () => $this->periksa($ast, [], null, false), 'BR-AST-03');
        $this->gagalAset(fn () => $this->periksa($ast, ['meter_in' => '']), 'BR-AST-08');
        $this->gagalAset(fn () => $this->periksa($ast, ['meter_in' => 90]), 'BR-AST-08');

        // Meter diganti dengan alasan: pemakaian dihitung dari nol meter baru (A-166).
        $periksa = $this->periksa($ast, ['condition_grade' => 'C', 'condition_score' => 55, 'meter_in' => 12, 'meter_reset_reason' => 'Hour meter diganti di site']);
        $ast->refresh();

        $this->assertSame(AssetHandoverStatus::Inspected, $ast->status);
        $this->assertSame(12.0, (float) $ast->usage_hours);
        $this->assertSame(AssetState::Maintenance, $periksa->resulting_state);
        $this->gns->refresh();
        $this->assertSame(AssetState::Maintenance, $this->gns->asset_state);
        $this->assertSame('C', $this->gns->condition_grade);
        $this->assertSame(55, $this->gns->condition_score);
        $this->assertSame(112.0, (float) $this->gns->meter_total);
        $this->assertNull($this->gns->current_project_id);
        $this->assertSame([['component' => 'Mesin', 'note' => 'normal'], ['component' => 'Panel', 'note' => 'bersih']], $periksa->component_notes);
        Storage::disk('local')->assertExists($periksa->photo_path);

        $rusak = StockEvent::query()->where('event_type', StockEventType::AssetLostOrDamaged->value)->sole();
        $this->assertSame('damaged', $rusak->payload['kind'], 'BR-AST-03: C/D diteruskan ke Akuntansi.');
        $this->assertSame('C', $rusak->payload['condition_grade']);
        $this->gagalAset(fn () => $this->periksa($ast), 'BR-GEN-01');

        // Dipilah sesuai hasil: C → rusak, bukan layak.
        $id = $ret->lines()->sole()->id;
        $this->gagalAset(fn () => $this->pilah($ret, [$id => [['sorting' => 'good', 'qty' => 1, 'target_bin_id' => $this->binB->id]]]), 'BR-AST-03');
        $this->pilah($ret, [$id => [['sorting' => 'damaged', 'qty' => 1, 'target_bin_id' => $this->binSistem($this->gudang, BinType::Quarantine)->id, 'reason_code_id' => $this->alasan(ReasonContext::Damage)]]]);

        $this->assertSame(AssetState::Maintenance, $this->gns->refresh()->asset_state, 'Hasil pemeriksaan tetap berlaku setelah dipilah.');
        $kembali = StockEvent::query()->where('event_type', StockEventType::AssetReturned->value)->sole();
        $this->assertSame('C', $kembali->payload['inspection']['condition_grade']);
        $this->assertSame(12.0, (float) $kembali->payload['usage_hours']);
        $this->assertSame(1, $kembali->payload['usage_days']);
    }

    #[Test]
    public function tc_ast_04_grade_a_kembali_tersedia_dan_dipinjam_lagi_dengan_meter_terakhir(): void
    {
        $this->pinjamkan();
        $ret = $this->kembalikan();
        $this->periksa($this->ast(), ['meter_in' => 180]);

        $this->assertSame(AssetState::Available, $this->gns->refresh()->asset_state);
        $this->assertSame(180.0, (float) $this->gns->meter_total, '100 + 80 jam.');
        $this->assertSame(80.0, (float) $this->ast()->usage_hours);

        $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'good', 'qty' => 1, 'target_bin_id' => $this->binB->id]]]);
        $this->assertSame(AssetState::Available, $this->gns->refresh()->asset_state);
        $this->assertSame(1.0, $this->saldo($this->binB, $this->genset));

        // Sisa umur: max(100 hari / 1000, 180 jam / 1000) = 18 % terpakai.
        $this->assertSame(82.0, $this->gns->remainingLifePercent());
        $this->assertFalse($this->gns->isLifeAlert());

        // Dipinjam lagi: AST kedua, meter keluar = meter kembali terakhir.
        $this->pinjamkan();
        $kedua = $this->ast();
        $this->assertNotSame($ret->id, $kedua->goods_return_id);
        $this->assertSame(AssetHandoverStatus::CheckedOut, $kedua->status);
        $this->assertSame(180.0, (float) $kedua->meter_out);
        $this->assertSame(2, $this->gns->handovers()->count());
    }

    #[Test]
    public function tc_ast_05_state_mengikuti_lokasi_dari_modul_lain(): void
    {
        $ledger = app(StockLedger::class);
        $karantina = $this->binSistem($this->gudang, BinType::Quarantine);

        $ledger->post(new MovementRequest(item: $this->genset, qtyBase: 1, fromBinId: $this->binB->id, toBinId: $karantina->id, serialId: $this->gns->id, stockStatus: StockStatus::Quarantine, fromStockStatus: StockStatus::Available));
        $this->assertSame(AssetState::Maintenance, $this->gns->refresh()->asset_state, 'Karantina = maintenance.');

        $ledger->post(new MovementRequest(item: $this->genset, qtyBase: 1, fromBinId: $karantina->id, toBinId: $this->binA->id, serialId: $this->gns->id, stockStatus: StockStatus::Available, fromStockStatus: StockStatus::Quarantine));
        $this->assertSame(AssetState::Available, $this->gns->refresh()->asset_state);

        $ledger->post(new MovementRequest(item: $this->genset, qtyBase: 1, fromBinId: $this->binA->id, toBinId: $this->binA->id, serialId: $this->gns->id, stockStatus: StockStatus::Damaged, fromStockStatus: StockStatus::Available));
        $this->assertSame(AssetState::Damaged, $this->gns->refresh()->asset_state, 'Kondisi Rusak = damaged.');

        $ledger->post(new MovementRequest(item: $this->genset, qtyBase: 1, fromBinId: $this->binA->id, serialId: $this->gns->id, stockStatus: StockStatus::Damaged, documentType: 'stock_adjustment'));
        $this->assertSame(AssetState::WrittenOff, $this->gns->refresh()->asset_state, 'Keluar ledger lewat ADJ = dihapuskan.');

        // Item bukan aset tidak tersentuh.
        $ledger->post(new MovementRequest(item: $this->baut, qtyBase: 1, fromBinId: $this->binA->id));
        $this->assertSame(99.0, $this->saldo($this->binA, $this->baut));
    }
}
