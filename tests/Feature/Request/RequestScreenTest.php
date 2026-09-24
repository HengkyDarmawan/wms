<?php

declare(strict_types=1);

namespace Tests\Feature\Request;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Livewire\PortalRequestDetail;
use App\Domain\Request\Livewire\PortalRequestList;
use App\Domain\Request\Livewire\RequestDetail;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Request\Livewire\RequestList;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\TenantTestCase;

/**
 * TC-REQ-25 dan TC-REQ-26 — cakupan (BR-ACC-05) dan izin layar (BR-GEN-09),
 * ditambah pemeriksaan kelima layar §6.
 */
class RequestScreenTest extends TenantTestCase
{
    use ApprovalFixtures;

    private Project $proyek;

    private Warehouse $gudang;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        // Saldo awal: approval membuat reservasi lunak, dan reservasi menuntut
        // stok tersedia (BR-STK-03).
        $bin = Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => 'CKG-A-R01-L1-B01',
            'bin_type' => BinType::Storage,
        ]);

        app(StockLedger::class)->post(new MovementRequest(
            item: $this->item,
            qtyBase: 100,
            toBinId: $bin->id,
        ));
    }

    private function buatReq(?Project $proyek = null, ?User $pemohon = null): MaterialRequest
    {
        $pemohon ??= $this->makeUser('internal_requester');

        return app(SaveRequest::class)->handle(
            null,
            [
                'project_id' => ($proyek ?? $this->proyek)->id,
                'required_date' => now()->addDays(3)->toDateString(),
            ],
            [['item_id' => $this->item->id, 'qty_base' => 10]],
            $pemohon,
        );
    }

    private function klienUntuk(Project $proyek): User
    {
        return $this->makeUser('client_user', ScopeType::Project, $proyek->id, [
            'client_id' => $proyek->client_id,
        ]);
    }

    #[Test]
    public function tc_req_25_klien_tidak_bisa_membuka_req_proyek_lain(): void
    {
        $lain = $this->makeProject();
        $reqLain = $this->buatReq($lain);

        $klien = $this->klienUntuk($this->proyek);
        $klien->forgetPermissionCache();

        $this->assertFalse($klien->can('view', $reqLain), 'Klien tidak boleh membuka REQ proyek lain.');

        // Route model binding memakai global scope yang sama: keberadaan REQ
        // milik proyek lain pun tidak terkonfirmasi.
        $this->actingAs($klien)
            ->get($this->tenantUrl('portal/requests/'.$reqLain->id))
            ->assertNotFound();
    }

    #[Test]
    public function tc_req_25b_daftar_portal_hanya_memuat_req_klien_itu(): void
    {
        $milikku = $this->buatReq();
        $lain = $this->buatReq($this->makeProject());

        $klien = $this->klienUntuk($this->proyek);
        $klien->forgetPermissionCache();

        Livewire::actingAs($klien)
            ->test(PortalRequestList::class)
            ->assertOk()
            ->assertSee($milikku->number)
            ->assertDontSee($lain->number);
    }

    #[Test]
    public function tc_req_26_izin_layar_permintaan(): void
    {
        $req = $this->buatReq();

        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        $this->assertTrue($staf->hasPermission('request.view'));
        $this->assertTrue($staf->hasPermission('request.review'));
        $this->assertFalse($staf->hasPermission('request.create'));
        $this->assertFalse($staf->hasPermission('request.close_short'));

        $this->actingAs($staf)->get($this->tenantUrl('requests'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('requests/'.$req->id))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('requests/create'))->assertForbidden();

        $pemohon = $this->makeUser('internal_requester');
        $pemohon->forgetPermissionCache();

        $this->assertTrue($pemohon->hasPermission('request.create'));
        $this->assertFalse($pemohon->hasPermission('request.review'));
        $this->assertFalse($pemohon->hasPermission('request.approve'));

        $this->actingAs($pemohon)->get($this->tenantUrl('requests/create'))->assertOk();
    }

    #[Test]
    public function tc_req_26b_driver_tidak_punya_izin_permintaan(): void
    {
        $sopir = $this->makeUser('driver');
        $sopir->forgetPermissionCache();

        $this->assertFalse($sopir->hasPermission('request.view'));

        $this->actingAs($sopir)->get($this->tenantUrl('requests'))->assertForbidden();
    }

    #[Test]
    public function tc_req_26c_layar_buat_req_menyimpan_dan_mengajukan(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $pemohon->forgetPermissionCache();

        Livewire::actingAs($pemohon)
            ->test(RequestForm::class)
            ->assertOk()
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.required_date', now()->addDays(4)->toDateString())
            ->set('lines.0.item_id', (string) $this->item->id)
            ->set('lines.0.qty_base', '12')
            ->call('simpanDanAjukan')
            ->assertHasNoErrors()
            ->assertRedirect();

        $req = MaterialRequest::query()->latest('id')->first();

        $this->assertNotNull($req);
        $this->assertSame('under_review', $req->status->value, 'Tanpa gudang sumber, REQ internal ikut ditinjau.');
        $this->assertSame(12.0, (float) $req->openLines()->first()->qty_base);
    }

    #[Test]
    public function tc_req_26d_layar_detail_menjalankan_tinjau_dan_approval(): void
    {
        // Sejak modul approval, approver ditentukan aturan (20-approval §13):
        // satu lapis Kepala Gudang terkait.
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapis(ApproverType::WarehouseHead)]);

        $pemohon = $this->makeUser('internal_requester');
        $req = app(SubmitRequest::class)->handle($this->buatReq(null, $pemohon), $pemohon);

        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        $baris = $req->openLines()->first();

        // Tanpa sumber, pengiriman ke approval ditolak dengan kode aturannya.
        Livewire::actingAs($staf)
            ->test(RequestDetail::class, ['request' => $req])
            ->assertOk()
            ->call('kirimKeApproval')
            ->assertSet('ruleCode', 'BR-REQ-04')
            ->call('simpanSumber', $baris->id, (string) $this->gudang->id, 'stock', '')
            ->call('kirimKeApproval')
            ->assertSet('ruleError', '');

        $this->assertSame('pending_approval', $req->refresh()->status->value);

        // Admin Company memegang `request.approve` tetapi tidak ditugaskan
        // aturan: tombolnya tidak muncul dan aksinya ditolak (A-86).
        $admin = $this->makeUser('company_admin');
        $admin->forgetPermissionCache();

        Livewire::actingAs($admin)
            ->test(RequestDetail::class, ['request' => $req])
            ->call('setujui')
            ->assertForbidden();

        Livewire::actingAs($kepala)
            ->test(RequestDetail::class, ['request' => $req])
            ->assertSee(__('Riwayat approval'))
            ->call('setujui')
            ->assertSet('ruleError', '');

        $this->assertSame('approved', $req->refresh()->status->value);
    }

    #[Test]
    public function tc_req_26e_staf_tanpa_izin_approve_ditolak_layar(): void
    {
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($this->makeUser('warehouse_head'))]);

        $pemohon = $this->makeUser('internal_requester');
        $req = $this->buatReq(null, $pemohon);

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);

        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        $this->assertFalse($staf->hasPermission('request.approve'));

        Livewire::actingAs($staf)
            ->test(RequestDetail::class, ['request' => $req])
            ->call('setujui')
            ->assertForbidden();
    }

    #[Test]
    public function tc_req_26f_portal_klien_menambah_baris_dari_layar(): void
    {
        $klien = $this->klienUntuk($this->proyek);
        $klien->forgetPermissionCache();

        $req = app(SubmitRequest::class)->handle($this->buatReq(null, $klien), $klien);

        Livewire::actingAs($klien)
            ->test(PortalRequestDetail::class, ['request' => $req])
            ->assertOk()
            ->call('mintaTambah')
            ->set('barisBaru.0.item_id', (string) $this->item->id)
            ->set('barisBaru.0.qty_base', '7')
            ->call('simpanTambahan')
            ->assertHasNoErrors()
            ->assertSet('ruleError', '');

        $this->assertSame(2, $req->refresh()->openLines()->count());
    }

    #[Test]
    public function tc_req_26g_daftar_req_menandai_sla_terlampaui(): void
    {
        $klien = $this->klienUntuk($this->proyek);
        $req = app(SubmitRequest::class)->handle($this->buatReq(null, $klien), $klien);

        $req->forceFill(['created_at' => now()->subDays(4)])->save();

        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(RequestList::class)
            ->assertOk()
            ->assertSee($req->number)
            ->assertSee('Ditinjau 4 hari')
            ->set('hanyaTerlambat', true)
            ->assertSee($req->number);
    }
}
