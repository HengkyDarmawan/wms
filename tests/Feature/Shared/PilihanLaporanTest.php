<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\Shared\Livewire\ReportViewer;
use App\Domain\Stock\Livewire\BalanceList;
use App\Domain\Stock\Livewire\ReservationList;
use App\Domain\Stock\Livewire\StockCard;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-RPT-14, TC-STK-39 — `<x-pilih>` di menu Laporan (A-396): penyaring
 * proyek & vendor laporan dicari ke server dengan query lama (cakupan sama),
 * gudang/klien/kategori dimuat sekaligus; akun Klien tidak pernah mendapat
 * pilihan dari layar laporan. Kartu stok: bin dicari ke server (hanya bin
 * yang pernah menyimpan item, dalam cakupan gudang); saringan gudang saldo,
 * reservasi, kartu stok dimuat sekaligus.
 */
class PilihanLaporanTest extends TenantTestCase
{
    /** @return list<int> */
    private function hasil($komponen): array
    {
        return array_map('intval', array_column($komponen->effects['returns'][0] ?? [], 'value'));
    }

    /** @param  list<array<string, mixed>>  $opsi  @return list<int> */
    private function nilai(array $opsi): array
    {
        return array_map('intval', array_column($opsi, 'value'));
    }

    #[Test]
    public function tc_rpt_14_penyaring_proyek_dan_vendor_laporan_dicari_ke_server_sesuai_cakupan(): void
    {
        $milik = $this->makeProject();
        $lain = $this->makeProject();
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, (int) $milik->id);

        // Pengguna bercakupan proyek: isian awal & hasil cari hanya proyeknya.
        $req = Livewire::actingAs($pemohon)->test(ReportViewer::class, ['reportKey' => 'daftar-req'])
            ->assertSeeHtml('id="filter-project_id"')->assertSeeHtml('class="nx-pilih"');
        $this->assertSame([(int) $milik->id], $this->nilai($req->viewData('opsiServer')['project_id']));
        $req->call('cariPilihan', 'filters.project_id', 'PRU');
        $this->assertSame([(int) $milik->id], $this->hasil($req));

        // Proyek di luar cakupan lewat URL: tanpa label (tidak dibocorkan).
        $req->set('filters.project_id', (string) $lain->id);
        $this->assertNotContains((int) $lain->id, $this->nilai($req->viewData('opsiServer')['project_id']));

        // Penyaring lain (enum, teks, dimuat sekaligus) tidak bisa dicari ke server.
        $req->call('cariPilihan', 'filters.status', 'draft');
        $this->assertSame([], $this->hasil($req));
        $req->call('cariPilihan', 'form.project_id', 'PRU');
        $this->assertSame([], $this->hasil($req));

        // Admin (cakupan semua) melihat semua proyek — daftar lama.
        $admin = $this->makeUser('company_admin');
        $semua = Livewire::actingAs($admin)->test(ReportViewer::class, ['reportKey' => 'retur-per-proyek'])
            ->call('cariPilihan', 'filters.project_id', 'PRU');
        $this->assertContains((int) $lain->id, $this->hasil($semua));

        // Vendor: semua vendor (termasuk nonaktif — laporan riwayat), dicari ke server.
        $mati = Vendor::create(['code' => 'V-MATI', 'name' => 'Vendor Mati', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Inactive, 'is_active' => false]);
        $vendor = Livewire::actingAs($admin)->test(ReportViewer::class, ['reportKey' => 'barang-bermasalah-vendor'])
            ->assertSeeHtml('id="filter-vendor_id"')->assertSeeHtml('id="filter-warehouse_id"')
            ->call('cariPilihan', 'filters.vendor_id', 'MATI');
        $this->assertSame([(int) $mati->id], $this->hasil($vendor));

        // Pengguna tanpa izin laporan itu tidak mendapat hasil.
        $this->actingAs($this->makeUser('driver'));
        $vendor->call('cariPilihan', 'filters.vendor_id', 'MATI');
        $this->assertSame([], $this->hasil($vendor));
    }

    #[Test]
    public function tc_rpt_14b_akun_klien_tidak_mendapat_pilihan_dari_layar_laporan(): void
    {
        $milik = $this->makeProject();
        $lain = $this->makeProject();

        // Klien tanpa penugasan proyek (cakupan "semua") — keadaan terburuk: id cakupan null.
        $klien = $this->makeUser('client_user', ScopeType::All, null, ['client_id' => $milik->client_id]);

        foreach (['daftar-req', 'retur-per-proyek'] as $kunci) {
            $layar = Livewire::actingAs($klien)->test(ReportViewer::class, ['reportKey' => $kunci]);
            $this->assertSame([], $layar->viewData('opsiServer')['project_id'], $kunci);
            $layar->call('cariPilihan', 'filters.project_id', 'PRU');
            $this->assertSame([], $this->hasil($layar), $kunci);
            $layar->call('labelPilihan', 'filters.project_id', (string) $lain->id);
            $this->assertNull($layar->effects['returns'][0] ?? null, $kunci);
        }
    }

    #[Test]
    public function tc_stk_39_bin_kartu_stok_dicari_ke_server_dan_gudang_dimuat_sekaligus(): void
    {
        $ckg = $this->gudang('CKG');
        $bks = $this->gudang('BKS');
        $binCkg = $this->bin($ckg, 'CKG-A-R01-L1-B01');
        $binKosong = $this->bin($ckg, 'CKG-A-R01-L1-B02');
        $binBks = $this->bin($bks, 'BKS-A-R01-L1-B01');

        $item = Item::create([
            'code' => 'BAUT-M12', 'name' => 'Baut M12', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);
        $ledger = app(StockLedger::class);
        $ledger->post(new MovementRequest(item: $item, qtyBase: 5, toBinId: $binCkg->id));
        $ledger->post(new MovementRequest(item: $item, qtyBase: 3, toBinId: $binBks->id));

        // Admin: hanya bin yang pernah menyimpan item ini; saringan gudang mempersempit.
        $admin = $this->makeUser('company_admin');
        $kartu = Livewire::actingAs($admin)->test(StockCard::class, ['item' => $item])
            ->assertSeeHtml('id="kartu-gudang"')->assertSeeHtml('id="kartu-bin"')
            ->call('cariPilihan', 'binFilter', 'A-R01');
        $this->assertEqualsCanonicalizing([(int) $binCkg->id, (int) $binBks->id], $this->hasil($kartu));
        $this->assertNotContains((int) $binKosong->id, $this->hasil($kartu));

        $kartu->set('warehouseFilter', (string) $bks->id)->call('cariPilihan', 'binFilter', 'A-R01');
        $this->assertSame([(int) $binBks->id], $this->hasil($kartu));

        // Staf bercakupan gudang BKS: bin gudang lain tidak ditawarkan.
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, (int) $bks->id);
        $terbatas = Livewire::actingAs($staf)->test(StockCard::class, ['item' => $item])->call('cariPilihan', 'binFilter', 'A-R01');
        $this->assertSame([(int) $binBks->id], $this->hasil($terbatas));

        // Akun Klien & pengguna tanpa izin stok: tanpa hasil.
        $this->actingAs($this->makeUser('client_user', ScopeType::All, null, ['client_id' => $this->makeClient()->id]));
        $kartu->call('cariPilihan', 'binFilter', 'A-R01');
        $this->assertSame([], $this->hasil($kartu));
        $this->actingAs($this->makeUser('external_auditor'));
        $kartu->call('cariPilihan', 'binFilter', 'A-R01');
        $this->assertSame([], $this->hasil($kartu));

        // Saringan gudang saldo & reservasi: dimuat sekaligus.
        Livewire::actingAs($admin)->test(BalanceList::class)->assertSeeHtml('id="filter-gudang-stok"')->assertSeeHtml('class="nx-pilih"');
        Livewire::actingAs($admin)->test(ReservationList::class)->assertSeeHtml('id="filter-gudang-reservasi"')->assertSeeHtml('class="nx-pilih"');

        // Nilai dari URL tetap diberi label (tautan dari saldo stok).
        $url = Livewire::actingAs($admin)->withQueryParams(['warehouseFilter' => (string) $ckg->id, 'binFilter' => (string) $binCkg->id])
            ->test(StockCard::class, ['item' => $item]);
        $this->assertContains((int) $binCkg->id, $this->nilai($url->viewData('opsiBin')));
    }

    private function gudang(string $kode): Warehouse
    {
        return app(SaveWarehouse::class)->handle(null, [
            'code' => $kode,
            'name' => 'Gudang '.$kode,
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
    }

    private function bin(Warehouse $gudang, string $kode): Bin
    {
        return Bin::create(['warehouse_id' => $gudang->id, 'code' => $kode, 'bin_type' => BinType::Storage]);
    }
}
