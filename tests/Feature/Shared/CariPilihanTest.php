<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PIL-02–TC-PIL-08 — pencarian pilihan ke server (A-384): method Livewire `cariPilihan`
 * memakai query yang sama dengan daftar, jadi pengguna berakses terbatas tidak
 * pernah mendapat baris di luar cakupannya — per jenis data (pengguna, proyek,
 * item, vendor, bin); Klien hanya datanya; nilai di luar cakupan tidak berlabel
 * dan ditolak simpan; model yang tidak dinyatakan tidak bisa dicari; laju dibatasi.
 */
class CariPilihanTest extends TenantTestCase
{
    private Warehouse $gudangA;

    private Warehouse $gudangB;

    private Project $proyekA;

    private Project $proyekB;

    protected function setUp(): void
    {
        parent::setUp();

        $tipe = WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id');
        $this->gudangA = app(SaveWarehouse::class)->handle(null, ['code' => 'CKA', 'name' => 'Gudang Cari A', 'warehouse_type_id' => $tipe]);
        $this->gudangB = app(SaveWarehouse::class)->handle(null, ['code' => 'CKB', 'name' => 'Gudang Cari B', 'warehouse_type_id' => $tipe]);
        Bin::create(['warehouse_id' => $this->gudangA->id, 'code' => 'CKA-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        Bin::create(['warehouse_id' => $this->gudangB->id, 'code' => 'CKB-A-R01-L1-B01', 'bin_type' => BinType::Storage]);

        $this->proyekA = $this->makeProject(['code' => 'CARI-A', 'name' => 'Proyek Cari Alfa']);
        $this->proyekB = $this->makeProject(['code' => 'CARI-B', 'name' => 'Proyek Cari Beta']);

        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        foreach (['CARI-AKTIF' => ItemStatus::Active, 'CARI-MATI' => ItemStatus::Inactive] as $kode => $status) {
            Item::create(['code' => $kode, 'name' => 'Barang '.$kode, 'status' => $status, 'tracking_mode' => TrackingMode::None,
                'ownership_model' => OwnershipModel::Consumable, 'base_uom_id' => $pcs]);
        }

        Vendor::create(['code' => 'V-CARI1', 'name' => 'Vendor Cari Aktif', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Active, 'is_active' => true]);
        Vendor::create(['code' => 'V-CARI2', 'name' => 'Vendor Cari Mati', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Inactive, 'is_active' => false]);

        $this->makeUser('warehouse_staff', attributes: ['name' => 'Cari Staf Aktif']);
        $this->makeUser('warehouse_staff', attributes: ['name' => 'Cari Staf Mati', 'is_active' => false]);
        $this->makeUser('', attributes: ['name' => 'Cari Klien Lain', 'client_id' => $this->proyekB->client_id]);

        Livewire::component('uji-cari-pilihan', UjiCariPilihan::class);
    }

    /** @return array<int, string> teks hasil cari */
    private function cari(User $siapa, string $model, string $kata, array $isi = []): array
    {
        $komponen = Livewire::actingAs($siapa)->test(UjiCariPilihan::class, $isi)->call('cariPilihan', $model, $kata);

        return array_column($komponen->effects['returns'][0] ?? [], 'text');
    }

    #[Test]
    public function tc_pil_02_pengguna_hanya_internal_aktif_dan_tertutup_bagi_klien(): void
    {
        $admin = $this->makeUser('company_admin');
        $hasil = $this->cari($admin, 'pengguna', 'cari');
        $this->assertContains('Cari Staf Aktif', $hasil);
        $this->assertNotContains('Cari Staf Mati', $hasil, 'Nonaktif tidak ditawarkan.');
        $this->assertNotContains('Cari Klien Lain', $hasil, 'Akun Klien tidak ditawarkan.');

        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyekA->id, ['client_id' => $this->proyekA->client_id]);
        $this->assertSame([], $this->cari($klien, 'pengguna', 'cari'), 'Klien tidak pernah mendapat daftar pengguna internal.');
    }

    #[Test]
    public function tc_pil_03_proyek_mengikuti_cakupan_dan_klien_hanya_proyeknya(): void
    {
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyekA->id);
        $this->assertSame(['CARI-A — Proyek Cari Alfa'], $this->cari($pemohon, 'proyek', 'proyek cari'));
        $this->assertSame([], $this->cari($pemohon, 'proyek', 'beta'), 'Proyek di luar cakupan tidak muncul walau cocok.');

        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyekA->id, ['client_id' => $this->proyekA->client_id]);
        $this->assertSame(['CARI-A — Proyek Cari Alfa'], $this->cari($klien, 'proyek', 'CARI'));

        $admin = $this->makeUser('company_admin');
        $this->assertCount(2, $this->cari($admin, 'proyek', 'CARI'));
    }

    #[Test]
    public function tc_pil_04_item_hanya_status_daftar_dan_minimal_dua_huruf(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudangA->id);
        $this->assertSame(['CARI-AKTIF — Barang CARI-AKTIF'], $this->cari($staf, 'item', 'barang cari'));
        $this->assertSame([], $this->cari($staf, 'item', 'c'), 'Kurang dari 2 huruf tidak mencari.');
        $this->assertSame([], $this->cari($staf, 'item', '%'), 'Wildcard SQL tidak membuka semua baris.');
    }

    #[Test]
    public function tc_pil_05_vendor_aktif_saja_dan_tertutup_bagi_klien(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudangA->id);
        $this->assertSame(['Vendor Cari Aktif'], $this->cari($staf, 'vendor', 'vendor cari'));

        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyekA->id, ['client_id' => $this->proyekA->client_id]);
        $this->assertSame([], $this->cari($klien, 'vendor', 'vendor'));
    }

    #[Test]
    public function tc_pil_06_bin_hanya_gudang_dalam_cakupan(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudangA->id);
        $this->assertSame(['CKA-A-R01-L1-B01'], $this->cari($staf, 'bin', 'R01', ['gudangId' => $this->gudangA->id]));
        $this->assertSame([], $this->cari($staf, 'bin', 'R01', ['gudangId' => $this->gudangB->id]), 'Gudang lain lewat id dari browser tetap tertutup.');

        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyekA->id, ['client_id' => $this->proyekA->client_id]);
        $this->assertSame([], $this->cari($klien, 'bin', 'R01', ['gudangId' => $this->gudangA->id]));
    }

    #[Test]
    public function tc_pil_07_nilai_di_luar_cakupan_tidak_berlabel_dan_ditolak_simpan(): void
    {
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyekA->id);
        $this->actingAs($pemohon);

        $proyek = SumberPilihan::proyek();
        $this->assertNull($proyek->label($this->proyekB->id), 'Nilai di luar cakupan tidak diberi label.');
        $this->assertSame('CARI-A — Proyek Cari Alfa', $proyek->label($this->proyekA->id)['text']);
        $this->assertNotContains((int) $this->proyekB->id, array_column($proyek->awalDengan($this->proyekB->id), 'value'));
        $this->assertFalse($proyek->berisi($this->proyekA->id.'abc'), '"5abc" tidak dianggap id 5.');

        // Simpan: nilai dari browser di luar cakupan ditolak.
        Livewire::actingAs($pemohon)->test(UjiCariPilihan::class)
            ->set('proyekId', $this->proyekB->id)->call('simpan')->assertHasErrors('proyekId')
            ->set('proyekId', $this->proyekA->id)->call('simpan')->assertHasNoErrors();
    }

    #[Test]
    public function tc_pil_08_model_tak_dinyatakan_tidak_bisa_dicari_dan_laju_dibatasi(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->assertSame([], $this->cari($admin, 'rahasia', 'cari'), 'Model yang tidak dinyatakan tidak bisa dicari.');

        RateLimiter::clear('cari-pilihan:'.tenant()->getTenantKey().':'.$admin->id);
        $komponen = Livewire::actingAs($admin)->test(UjiCariPilihan::class);
        for ($i = 0; $i < 60; $i++) {
            $komponen->call('cariPilihan', 'vendor', 'vendor');
        }
        $this->assertNotSame([], $komponen->effects['returns'][0]);
        $komponen->call('cariPilihan', 'vendor', 'vendor');
        $this->assertSame([], $komponen->effects['returns'][0], 'Pencarian ke-61 dalam semenit ditolak.');
    }
}

/** Komponen uji: memakai sumber baku kelima jenis data lewat trait CariPilihan. */
class UjiCariPilihan extends Component
{
    use CariPilihan;

    public ?int $gudangId = null;

    public ?int $proyekId = null;

    protected function pilihanServer(string $model): ?Pilihan
    {
        return match ($model) {
            'pengguna' => SumberPilihan::pengguna(),
            'proyek' => SumberPilihan::proyek(),
            'item' => SumberPilihan::item(),
            'vendor' => SumberPilihan::vendor(),
            'bin' => SumberPilihan::bin((int) $this->gudangId),
            default => null,
        };
    }

    public function simpan(): void
    {
        $this->validate(['proyekId' => ['nullable', 'integer', SumberPilihan::proyek()->aturan()]]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
