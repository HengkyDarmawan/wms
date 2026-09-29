<?php

declare(strict_types=1);

namespace Tests\Feature\Request;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Livewire\PortalRequestDetail;
use App\Domain\Request\Livewire\RequestDetail;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Request\Livewire\RequestList;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Livewire\PickList;
use App\Domain\Shipment\Livewire\ShipmentForm;
use App\Domain\Shipment\Livewire\ShipmentList;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-REQ-41–41c, TC-SJ-30, TC-PIL-09 — `<x-pilih>` di menu Barang keluar
 * (A-392): proyek & item REQ, saringan proyek daftar REQ, item pemetaan
 * non-katalog, item tambahan portal klien, serta proyek & vendor tujuan SJ
 * dicari ke server dengan daftar & cakupan yang sama seperti dulu; id di luar
 * daftar ditolak di isiannya; label nilai yang diisi server (pindai) hanya
 * untuk nilai di dalam daftar.
 */
class PilihanBarangKeluarTest extends TenantTestCase
{
    private Project $proyek;

    private Project $lain;

    private Item $baut;

    private Item $sementara;

    private Item $mati;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();
        $this->lain = $this->makeProject();

        app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        $buat = fn (string $kode, ItemStatus $status) => Item::create([
            'code' => $kode, 'name' => $kode, 'status' => $status,
            'tracking_mode' => TrackingMode::None, 'base_uom_id' => $pcs,
        ]);
        $this->baut = $buat('BAUT-M12', ItemStatus::Active);
        $this->sementara = $buat('BAUT-SEM', ItemStatus::Provisional);
        $this->mati = $buat('BAUT-MATI', ItemStatus::Inactive);
    }

    /** @return list<int> */
    private function nilai($komponen): array
    {
        return array_map('intval', array_column($komponen->effects['returns'][0] ?? [], 'value'));
    }

    private function req(User $pemohon, array $baris): MaterialRequest
    {
        $req = app(SaveRequest::class)->handle(null, [
            'project_id' => $this->proyek->id,
            'required_date' => now()->addDays(3)->toDateString(),
        ], $baris, $pemohon);

        return app(SubmitRequest::class)->handle($req, $pemohon);
    }

    #[Test]
    public function tc_req_41_proyek_dan_item_req_dicari_ke_server_sesuai_cakupan(): void
    {
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);

        $form = Livewire::actingAs($pemohon)->test(RequestForm::class)->assertSeeHtml('id="req-proyek"')
            ->call('cariPilihan', 'form.project_id', 'PR');
        $this->assertSame([(int) $this->proyek->id], $this->nilai($form));

        // Item: aktif & sementara (daftar lama), tanpa nonaktif.
        $form->call('cariPilihan', 'lines.0.item_id', 'baut');
        $this->assertEqualsCanonicalizing([(int) $this->baut->id, (int) $this->sementara->id], $this->nilai($form));

        $form->set('form.project_id', (string) $this->lain->id)
            ->set('lines.0.item_id', (string) $this->mati->id)->set('lines.0.qty_base', '3')
            ->call('simpan')->assertHasErrors(['form.project_id', 'lines.0.item_id'])
            ->set('form.project_id', (string) $this->proyek->id)->set('lines.0.item_id', (string) $this->baut->id)
            ->call('simpan')->assertHasNoErrors()->assertRedirect();

        // Saringan proyek daftar REQ: proyek dalam cakupan saja (semua status).
        $daftar = Livewire::actingAs($pemohon)->test(RequestList::class)->assertSeeHtml('id="filter-proyek-req"')
            ->call('cariPilihan', 'projectFilter', 'PR');
        $this->assertSame([(int) $this->proyek->id], $this->nilai($daftar));

        // Tanpa `request.create`: pencarian form kosong.
        $this->actingAs($this->makeUser('internal_auditor'));
        $form->call('cariPilihan', 'lines.0.item_id', 'baut');
        $this->assertSame([], $this->nilai($form));
    }

    #[Test]
    public function tc_req_41b_petakan_non_katalog_dan_tambahan_portal(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = $this->req($pemohon, [['non_catalog_text' => 'Baut besar', 'qty_base' => 5]]);
        $baris = $req->openLines()->firstOrFail();

        $staf = $this->makeUser('warehouse_staff');
        $detail = Livewire::actingAs($staf)->test(RequestDetail::class, ['request' => $req]);
        $detail->call('cariPilihan', 'form.item_id', 'baut');
        $this->assertSame([], $this->nilai($detail), 'Tanpa dialog Petakan terbuka, item tidak bisa dicari.');

        $detail->call('mintaPetakan', $baris->id)->call('cariPilihan', 'form.item_id', 'baut');
        $this->assertEqualsCanonicalizing([(int) $this->baut->id, (int) $this->sementara->id], $this->nilai($detail));
        $detail->set('form.item_id', (string) $this->mati->id)->call('petakan')->assertHasErrors('form.item_id');
        $this->assertNull($baris->refresh()->item_id);

        // Portal klien: tambahan baris memakai item aktif saja.
        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->proyek->client_id]);
        $reqKlien = $this->req($klien, [['item_id' => $this->baut->id, 'qty_base' => 2]]);
        $portal = Livewire::actingAs($klien)->test(PortalRequestDetail::class, ['request' => $reqKlien])
            ->call('mintaTambah')->call('cariPilihan', 'barisBaru.0.item_id', 'baut');
        $this->assertSame([(int) $this->baut->id], $this->nilai($portal));
        $portal->set('barisBaru.0.item_id', (string) $this->sementara->id)->set('barisBaru.0.qty_base', '1')
            ->call('simpanTambahan')->assertHasErrors('barisBaru.0.item_id');
    }

    #[Test]
    public function tc_pil_09_label_nilai_dari_server_hanya_di_dalam_daftar(): void
    {
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $form = Livewire::actingAs($pemohon)->test(RequestForm::class);

        $form->call('labelPilihan', 'lines.0.item_id', (string) $this->baut->id);
        $this->assertSame('BAUT-M12 — BAUT-M12', $form->effects['returns'][0]['text'] ?? null);

        foreach ([['lines.0.item_id', (string) $this->mati->id], ['form.project_id', (string) $this->lain->id], ['form.notes', '1'], ['lines.0.item_id', $this->baut->id.'abc']] as [$model, $nilai]) {
            $form->call('labelPilihan', $model, $nilai);
            $this->assertNull($form->effects['returns'][0] ?? null, $model.' = '.$nilai.' tidak boleh diberi label.');
        }
    }

    #[Test]
    public function tc_sj_30_proyek_dan_vendor_tujuan_sj_dicari_ke_server(): void
    {
        $aktif = Vendor::create(['code' => 'V-AKT', 'name' => 'Vendor Aktif', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Active, 'is_active' => true]);
        $mati = Vendor::create(['code' => 'V-MATI', 'name' => 'Vendor Mati', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Inactive, 'is_active' => false]);
        $tutup = $this->makeProject();
        $tutup->forceFill(['status' => 'closed'])->save();
        $kepala = $this->makeUser('warehouse_head');

        $sj = Livewire::actingAs($kepala)->test(ShipmentForm::class)->assertSeeHtml('id="sj-gudang"')
            ->call('cariPilihan', 'form.destination_project_id', 'PR');
        $this->assertContains((int) $this->proyek->id, $this->nilai($sj));
        $this->assertNotContains((int) $tutup->id, $this->nilai($sj), 'Proyek ditutup tidak ditawarkan.');

        $sj->call('cariPilihan', 'form.destination_vendor_id', 'vendor');
        $this->assertSame([(int) $aktif->id], $this->nilai($sj));

        $sj->set('form.destination_type', 'vendor')->set('form.destination_vendor_id', (string) $mati->id)
            ->set('pickTaskIds', [1])->call('simpan')->assertHasErrors('form.destination_vendor_id');
        $sj->set('form.destination_type', 'project_client')->set('form.destination_project_id', (string) $tutup->id)
            ->call('simpan')->assertHasErrors('form.destination_project_id');

        Livewire::actingAs($kepala)->test(PickList::class)->assertSeeHtml('id="filter-gudang-pck"')->assertSeeHtml('class="nx-pilih"');
        Livewire::actingAs($kepala)->test(ShipmentList::class)->assertSeeHtml('id="filter-gudang-sj"')->assertSeeHtml('class="nx-pilih"');
    }
}
