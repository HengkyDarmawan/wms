<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Vendor;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Return\Livewire\ReturnForm;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Shipment\Livewire\ShipmentForm;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-ACC-46 — daftar pilihan proyek mengikuti cakupan pembaca (BR-ACC-05,
 * A-21, A-354): akun Klien hanya proyek kliennya, termasuk di form Retur
 * portal (dulu menampilkan semua proyek & stoknya); pengguna bercakupan
 * proyek hanya proyeknya; vendor nonaktif tidak ditawarkan (A-310).
 */
class ProjectScopeOptionsTest extends TenantTestCase
{
    use ReturnFixtures;

    private Project $lain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
        $this->stok($this->binKrw1, $this->baut, 20);

        // Proyek klien lain dengan Gudang Site berisi stok.
        $this->lain = $this->makeProject(['client_id' => $this->makeClient()->id]);
        $site = $this->buatGudangSite('KRW9', $this->lain);
        $this->stok(Bin::create(['warehouse_id' => $site->id, 'code' => 'KRW9-A-R01-L1-B01', 'bin_type' => BinType::Storage]), $this->baut, 99);
    }

    #[Test]
    public function tc_acc_46_klien_hanya_proyeknya_di_form_retur_portal(): void
    {
        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->proyek->client_id]);

        $form = Livewire::actingAs($klien)->test(ReturnForm::class);
        // A-391: pilihan proyek kini `<x-pilih server>` (isian awal + cari); daftarnya tetap hanya proyek klien.
        $this->assertSame([(int) $this->proyek->id], array_map('intval', array_column($form->viewData('opsiProyek'), 'value')));
        $form->call('cariPilihan', 'form.project_id', substr((string) $this->lain->code, 0, 3));
        $this->assertNotContains((int) $this->lain->id, array_map('intval', array_column($form->effects['returns'][0] ?? [], 'value')));

        // Mengganti id proyek dari browser tidak membuka gudang pengirim proyek klien lain.
        $form->set('form.project_id', (string) $this->lain->id);
        $this->assertCount(0, $form->viewData('warehouses'));

        // Pengaju internal bercakupan proyek: stok proyek lain tidak terbuka lewat id.
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $internal = Livewire::actingAs($pemohon)->test(ReturnForm::class);
        $this->assertNotEmpty($internal->viewData('calon'), 'Stok Gudang Site proyeknya sendiri tampil.');
        $internal->set('form.project_id', (string) $this->lain->id);
        $this->assertCount(0, $internal->viewData('calon'));

        $this->actingAs($klien)->get($this->tenantUrl('portal/returns/create'))->assertOk()
            ->assertSee($this->proyek->code)->assertDontSee($this->lain->code);
    }

    #[Test]
    public function tc_acc_46b_pilihan_proyek_di_layar_internal_mengikuti_cakupan(): void
    {
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);

        // A-392: pilihan proyek kini `<x-pilih server>` — isian awal dan hasil cari sama-sama mengikuti cakupan.
        $ids = fn (array $opsi) => array_map('intval', array_column($opsi, 'value'));
        $req = Livewire::actingAs($pemohon)->test(RequestForm::class);
        $this->assertSame([(int) $this->proyek->id], $ids($req->viewData('opsiProyek')));
        $req->call('cariPilihan', 'form.project_id', substr((string) $this->lain->code, 0, 3));
        $this->assertNotContains((int) $this->lain->id, $ids($req->effects['returns'][0] ?? []));

        $this->actingAs($pemohon);
        $opsi = app(ReportRegistry::class)->find('daftar-req')?->filters()['project_id']['options'] ?? null;
        if ($opsi !== null) {
            $this->assertSame([(int) $this->proyek->id], array_map('intval', array_keys($opsi)), 'Filter laporan tidak menawarkan proyek lain.');
        }

        // Cakupan semua (Admin) tetap melihat semua proyek.
        $admin = $this->makeUser('company_admin');
        $this->assertContains((int) $this->lain->id, $ids(Livewire::actingAs($admin)->test(RequestForm::class)->viewData('opsiProyek')));
    }

    #[Test]
    public function tc_acc_46c_vendor_nonaktif_tidak_ditawarkan_di_surat_jalan(): void
    {
        $aktif = Vendor::create(['code' => 'V-AKT', 'name' => 'Vendor Aktif', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Active, 'is_active' => true]);
        $mati = Vendor::create(['code' => 'V-MATI', 'name' => 'Vendor Mati', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Inactive, 'is_active' => false]);

        // A-392: vendor tujuan kini `<x-pilih server>` — isian awal dan hasil cari.
        $sj = Livewire::actingAs($this->makeUser('warehouse_head'))->test(ShipmentForm::class)->set('form.destination_type', 'vendor');
        $vendor = array_map('intval', array_column($sj->viewData('opsiVendor'), 'value'));
        $sj->call('cariPilihan', 'form.destination_vendor_id', 'vendor');
        $dicari = array_map('intval', array_column($sj->effects['returns'][0] ?? [], 'value'));

        $this->assertContains($aktif->id, $vendor);
        $this->assertNotContains($mati->id, $vendor);
        $this->assertContains($aktif->id, $dicari);
        $this->assertNotContains($mati->id, $dicari);
    }
}
