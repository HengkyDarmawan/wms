<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Serial;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickupShipment;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Transfer\Actions\CancelTransfer;
use App\Domain\Transfer\Actions\CreateAssetTransfer;
use App\Domain\Transfer\Actions\MoveProjectRemainder;
use App\Domain\Transfer\Enums\TransferKind;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Livewire\ProjectMove;
use App\Domain\Transfer\Livewire\TransferDetail;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Actions\EnsureSystemBins;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Asset\Concerns\AssetFixtures;
use Tests\TenantTestCase;

/**
 * TC-AST-14–16, TC-TRF-18–20 — transfer aset On-site antar proyek lewat TRF
 * aset + SJ antar site (A-249; mengubah A-116, BR-RET-02), pindahan sisa
 * proyek dari hub (A-250), jejak lokasi aset (A-251).
 */
class AssetTransferTest extends TenantTestCase
{
    use AssetFixtures;

    private Project $p2;

    private Bin $onSite2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAset();

        $this->p2 = $this->makeProject();
        $this->p2->forceFill(['target_end_date' => now()->addDays(60)->toDateString()])->save();
        $this->site2 = $this->buatGudangSite('SBY1', $this->p2);
        $this->onSite2 = app(EnsureSystemBins::class)->onSiteBin($this->site2, $this->p2);
    }

    private Warehouse $site2;

    private function trfAset(array $serials, ?Project $ke = null): Transfer
    {
        return app(CreateAssetTransfer::class)->handle(
            ['from_project_id' => $this->proyek->id, 'to_project_id' => ($ke ?? $this->p2)->id, 'notes' => 'Proyek selesai'],
            array_map(fn (Serial $s) => $s->id, $serials),
            $this->makeUser('warehouse_head'),
        );
    }

    private function sjAntarSite(Transfer $trf): Shipment
    {
        return app(CreatePickupShipment::class)->forAssetTransfer($trf->refresh(), [
            'shipment_method' => 'own_fleet', 'vehicle_plate' => 'L 777 AB', 'carried_by_name' => 'Pak Joko',
        ], $this->makeUser('warehouse_staff'));
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (TransferRuleException|ShipmentRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_ast_14_aset_pindah_proyek_satu_pergerakan_ast_berantai(): void
    {
        $this->pinjamkan();
        $lama = $this->ast();
        $lama->forceFill(['checked_out_at' => now()->subDays(4)])->save();

        $trf = $this->trfAset([$this->gns]);
        $this->assertTrue($trf->asset_onsite);
        $this->assertSame(TransferKind::AssetOnSite, $trf->kind());
        $this->assertSame(TransferStatus::Approved, $trf->status, 'Tanpa aturan: disetujui (A-08), tanpa PCK.');
        $this->assertFalse($trf->hasLivePickTask());
        $this->assertSame((int) $this->onSite->warehouse_id, (int) $trf->from_warehouse_id);
        $this->assertSame((int) $this->onSite2->warehouse_id, (int) $trf->to_warehouse_id);

        $sj = $this->sjAntarSite($trf);
        $this->assertTrue($sj->isSiteTransfer());
        $this->assertSame(TransferStatus::InProgress, $trf->refresh()->status);
        $this->assertSame((int) $this->p2->id, (int) $sj->destination_project_id);
        $this->assertSame((int) $this->proyek->id, (int) $sj->origin_project_id);

        $gerak = StockMovement::query()->count();
        $sj = app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
        $this->assertSame($gerak, StockMovement::query()->count(), 'Berangkat tanpa pergerakan (A-247).');
        $this->assertSame(1.0, (float) $trf->lines()->sole()->qty_shipped);
        $this->assertTrue($this->makeUser('driver')->can('issueToken', $sj), 'Penerima proyek tujuan boleh lewat tautan bertoken.');

        app(ConfirmDelivery::class)->handle($sj, ['received_by_name' => 'PIC Surabaya', 'channel' => 'token_link'], [
            ['shipment_line_id' => $sj->lines()->sole()->id, 'qty_good' => 1],
        ], null);

        $this->assertSame(0.0, $this->saldo($this->onSite, $this->genset));
        $this->assertSame(1.0, $this->saldo($this->onSite2, $this->genset));
        $this->assertSame($gerak + 1, StockMovement::query()->count(), 'Satu pergerakan On-site → On-site.');

        $this->gns->refresh();
        $this->assertSame(AssetState::OnLoan, $this->gns->asset_state);
        $this->assertSame((int) $this->p2->id, (int) $this->gns->current_project_id);
        $this->assertSame(now()->addDays(60)->toDateString(), $this->gns->due_return_date?->toDateString());

        $lama->refresh();
        $baru = AssetHandover::query()->withoutGlobalScopes()->where('project_id', $this->p2->id)->sole();
        $this->assertSame(AssetHandoverStatus::Transferred, $lama->status);
        $this->assertSame(5, $lama->usage_days, 'Hari pakai proyek asal (inklusif, BR-AST-05).');
        $this->assertSame((int) $baru->id, (int) $lama->next_handover_id);
        $this->assertSame(AssetHandoverStatus::CheckedOut, $baru->status);
        $this->assertSame((int) $lama->id, (int) $baru->previous_handover_id);
        $this->assertSame((int) $trf->id, (int) $baru->transfer_id);
        $this->assertSame(100.0, (float) $baru->meter_out);

        $k = StockEvent::query()->where('event_type', StockEventType::AssetTransferred->value)->sole();
        $this->assertSame($this->proyek->code, $k->payload['from_project_code']);
        $this->assertSame($this->p2->code, $k->payload['to_project_code']);
        $this->assertSame($baru->number, $k->payload['handover_number']);
        $this->assertSame($lama->number, $k->payload['previous_handover_number']);
        $this->assertSame(5, $k->payload['usage_days_from_project']);

        $sj->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $sj->status);
        $this->assertSame(0, $sj->discrepancies()->count());
        $trf->refresh();
        $this->assertSame(TransferStatus::Completed, $trf->status);
        $this->assertSame(1.0, (float) $trf->lines()->sole()->qty_received);

        // Aset di proyek baru bisa diretur ke gudang seperti biasa, dari AST baru.
        $ret = $this->ret([['key' => implode(':', ['asset', $this->onSite2->id, $this->genset->id, $this->gns->id]), 'qty_base' => 1]], ['project_id' => $this->p2->id]);
        $this->grnRetur($ret);
        $this->assertSame(AssetHandoverStatus::Returned, $baru->refresh()->status);
    }

    #[Test]
    public function tc_ast_15_aset_tidak_tiba_tetap_di_proyek_asal(): void
    {
        $this->pinjamkan();
        $trf = $this->trfAset([$this->gns]);
        $sj = app(ShipShipment::class)->handle($this->sjAntarSite($trf), null, $this->makeUser('driver'));

        app(ConfirmDelivery::class)->handle($sj, ['received_by_name' => 'PIC'], [
            ['shipment_line_id' => $sj->lines()->sole()->id, 'qty_missing' => 1],
        ], $this->makeUser('driver'));

        $this->assertSame(ShipmentStatus::PartiallyDelivered, $sj->refresh()->status);
        $this->assertSame(1.0, $this->saldo($this->onSite, $this->genset), 'Tidak tiba: tetap tercatat di proyek asal.');
        $this->assertSame(AssetHandoverStatus::CheckedOut, $this->ast()->status);
        $this->assertSame(TransferStatus::Completed, $trf->refresh()->status);
        $this->assertSame(0.0, (float) $trf->lines()->sole()->qty_received);
    }

    #[Test]
    public function tc_trf_18_guard_trf_aset(): void
    {
        // Aset belum dipinjam proyek asal.
        $this->gagal(fn () => $this->trfAset([$this->gns]), 'BR-RET-02');

        $this->pinjamkan();
        $this->gagal(fn () => $this->trfAset([$this->gns], $this->proyek), 'BR-RET-02');
        $this->gagal(fn () => app(CreateAssetTransfer::class)->handle(['from_project_id' => $this->proyek->id, 'to_project_id' => $this->p2->id], [], $this->makeUser('warehouse_head')), 'BR-RET-01');

        // Proyek tujuan tanpa bin On-site.
        $this->gagal(fn () => $this->trfAset([$this->gns], $this->makeProject()), 'BR-WH-03');

        $trf = $this->trfAset([$this->gns]);
        $this->gagal(fn () => $this->trfAset([$this->gns]), 'BR-RET-02');

        // SJ antar site: sopir & plat wajib; satu per TRF; batal SJ → TRF kembali approved.
        $this->gagal(fn () => app(CreatePickupShipment::class)->forAssetTransfer($trf, ['shipment_method' => 'own_fleet', 'carried_by_name' => 'X'], $this->makeUser('warehouse_staff')), 'BR-SJ-07');
        $sj = $this->sjAntarSite($trf);
        $this->gagal(fn () => $this->sjAntarSite($trf), 'BR-RET-02');
        app(ShipShipment::class)->cancel($sj, $this->alasan(ReasonContext::Cancel), $this->makeUser('warehouse_head'));
        $this->assertSame(TransferStatus::Approved, $trf->refresh()->status);

        // TRF aset yang belum punya SJ bisa dibatalkan; tidak ada PCK/reservasi.
        app(CancelTransfer::class)->handle($trf, $this->alasan(ReasonContext::Cancel), null, $this->makeUser('warehouse_head'));
        $this->assertSame(TransferStatus::Cancelled, $trf->refresh()->status);
        $this->assertSame(AssetState::OnLoan, $this->gns->refresh()->asset_state);
    }

    #[Test]
    public function tc_trf_20_pindahkan_sisa_proyek_aset_dan_stok_sekaligus(): void
    {
        $this->pinjamkan();
        $this->stok($this->binKrw1, $this->baut, 12);
        $kepala = $this->makeUser('warehouse_head');

        $hasil = app(MoveProjectRemainder::class)->handle($this->proyek, [
            'to_project_id' => $this->p2->id,
            'to_warehouse_id' => $this->site2->id,
            'serial_ids' => [$this->gns->id],
            'stock' => [['warehouse_id' => $this->krw1->id, 'item_id' => $this->baut->id, 'qty_base' => 12]],
        ], $kepala);

        $this->assertTrue($hasil['asset']->asset_onsite);
        $this->assertCount(1, $hasil['stock']);
        $stok = $hasil['stock'][0];
        $this->assertFalse($stok->asset_onsite);
        $this->assertSame((int) $this->krw1->id, (int) $stok->from_warehouse_id);
        $this->assertSame((int) $this->site2->id, (int) $stok->to_warehouse_id);
        $this->assertSame(TransferKind::BetweenProjects, $stok->kind());
        $this->assertTrue($stok->hasLivePickTask(), 'Stok site lewat PCK → SJ → GRN biasa.');

        // Gudang Site tujuan harus milik proyek tujuan; gagal satu, gagal semua.
        $jumlahTrf = Transfer::query()->count();
        $this->gagal(fn () => app(MoveProjectRemainder::class)->handle($this->proyek, [
            'to_project_id' => $this->p2->id, 'to_warehouse_id' => $this->krw2->id,
            'stock' => [['warehouse_id' => $this->krw1->id, 'item_id' => $this->baut->id, 'qty_base' => 1]],
        ], $kepala), 'BR-RET-02');
        $this->assertSame($jumlahTrf, Transfer::query()->count());

        // Hub kedua proyek menampilkan riwayat pindahan bertaut ke proyek lawan.
        $this->actingAs($kepala)->get($this->tenantUrl('projects/'.$this->proyek->id))->assertOk()
            ->assertSee(__('Riwayat pindahan antar proyek'))->assertSee($hasil['asset']->number)
            ->assertSee(route('projects.show', $this->p2->id))->assertSee(route('projects.move', $this->proyek));
        $this->get($this->tenantUrl('projects/'.$this->p2->id))->assertOk()
            ->assertSee($stok->number)->assertSee(__('diterima dari'));
    }

    #[Test]
    public function tc_ast_16_layar_pindahan_trf_aset_dan_jejak_lokasi(): void
    {
        $this->pinjamkan();
        $this->stok($this->binKrw1, $this->baut, 5);
        $kepala = $this->makeUser('warehouse_head');
        $this->actingAs($kepala);

        $this->get($this->tenantUrl('projects/'.$this->proyek->id.'/move'))->assertOk()
            ->assertSee('GNS-01')->assertSee($this->baut->code);
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('projects/'.$this->proyek->id.'/move'))->assertForbidden();

        $this->actingAs($kepala);
        Livewire::test(ProjectMove::class, ['project' => $this->proyek])
            ->set('form.to_project_id', (string) $this->p2->id)
            ->assertSet('form.to_warehouse_id', (string) $this->site2->id)
            ->set('stok', [])
            ->call('simpan')
            ->assertRedirect(route('projects.show', $this->proyek->id));

        $trf = Transfer::query()->where('asset_onsite', true)->sole();
        $this->get($this->tenantUrl('transfers/'.$trf->id))->assertOk()
            ->assertSee(__('Aset antar proyek (On-site)'))->assertSee('GNS-01')->assertSee(__('Buat SJ antar site'));

        Livewire::test(TransferDetail::class, ['transfer' => $trf])
            ->set('jemput.vehicle_plate', 'L 1 SBY')->set('jemput.carried_by_name', 'Sopir')
            ->call('buatSjAntarSite')->assertRedirect();

        $sj = Shipment::query()->where('source_type', 'transfer')->sole();
        $sj = app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
        app(ConfirmDelivery::class)->handle($sj, ['received_by_name' => 'PIC'], [['shipment_line_id' => $sj->lines()->sole()->id, 'qty_good' => 1]], $kepala);

        $baru = AssetHandover::query()->withoutGlobalScopes()->where('project_id', $this->p2->id)->sole();
        $this->get($this->tenantUrl('assets/'.$this->gns->id))->assertOk()
            ->assertSee(__('Jejak lokasi'))->assertSee(__('Proyek').' '.$this->proyek->code)->assertSee(__('Proyek').' '.$this->p2->code);
        $this->get($this->tenantUrl('asset-handovers/'.$baru->id))->assertOk()
            ->assertSee(__('Dipindah dari'))->assertSee($baru->previousHandover->number);
        $this->get($this->tenantUrl('shipments/'.$sj->id))->assertOk()->assertSee(__('Dijemput dari'))->assertSee('Aset, AST/');
    }

    #[Test]
    public function tc_trf_19_aset_diretur_tidak_bisa_dipindah(): void
    {
        $this->pinjamkan();
        $this->ret([['key' => implode(':', ['asset', $this->onSite->id, $this->genset->id, $this->gns->id]), 'qty_base' => 1]], ['self_delivered' => false]);

        $this->gagal(fn () => $this->trfAset([$this->gns]), 'BR-RET-02');
    }
}
