<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestDetail;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestForm;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestList;
use App\Domain\Purchasing\Livewire\PriceHistory;
use App\Domain\Purchasing\Livewire\PurchaseOrderForm;
use App\Domain\Purchasing\Livewire\PurchaseOrderList;
use App\Domain\Purchasing\Livewire\VendorPriceList;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Purchasing\Concerns\PurchasingFixtures;
use Tests\TenantTestCase;

/**
 * TC-PRQ-14, TC-PO-17 — `<x-pilih>` di menu Pembelian (A-393): proyek & item
 * PRQ, vendor catatan pemesanan, vendor PO, saringan vendor PO, serta vendor &
 * item harga beli dicari ke server dengan daftar & cakupan yang sama seperti
 * dulu; id di luar daftar ditolak di isiannya; pengguna tanpa izin layar
 * tidak mendapat hasil.
 */
class PilihanPembelianTest extends TenantTestCase
{
    use PurchasingFixtures;

    private Item $mati;

    private Vendor $sementara;

    private Vendor $nonaktif;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPurchasing();

        $this->mati = Item::create([
            'code' => 'BAUT-MATI', 'name' => 'BAUT-MATI', 'status' => ItemStatus::Inactive,
            'tracking_mode' => TrackingMode::None, 'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);
        $this->sementara = Vendor::create(['code' => 'V-SEM', 'name' => 'PT Baja Sementara', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Provisional, 'is_active' => true]);
        $this->nonaktif = Vendor::create(['code' => 'V-MATI', 'name' => 'PT Baja Mati', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Inactive, 'is_active' => false]);
    }

    /** @return list<int> */
    private function nilai($komponen): array
    {
        return array_map('intval', array_column($komponen->effects['returns'][0] ?? [], 'value'));
    }

    #[Test]
    public function tc_prq_14_proyek_item_dan_vendor_pemesanan_dicari_ke_server(): void
    {
        $lain = $this->makeProject();
        $staf = $this->makeUser('warehouse_staff', ScopeType::Project, $this->proyek->id);

        $form = Livewire::actingAs($staf)->test(PurchaseRequestForm::class)->assertSeeHtml('id="prq-proyek"')
            ->call('cariPilihan', 'form.project_id', 'PR');
        $this->assertSame([(int) $this->proyek->id], $this->nilai($form), 'Proyek hanya dalam cakupan.');

        $form->call('cariPilihan', 'rows.0.item_id', 'BAUT');
        $this->assertContains((int) $this->baut->id, $this->nilai($form));
        $this->assertNotContains((int) $this->mati->id, $this->nilai($form), 'Item nonaktif tidak ditawarkan.');

        $form->set('form.warehouse_id', (string) $this->gudang->id)->set('form.project_id', (string) $lain->id)
            ->set('rows.0.item_id', (string) $this->mati->id)->set('rows.0.qty_base', '5')
            ->call('simpan')->assertHasErrors(['form.project_id', 'rows.0.item_id'])
            ->set('form.project_id', (string) $this->proyek->id)->set('rows.0.item_id', (string) $this->baut->id)
            ->call('simpan')->assertHasNoErrors()->assertRedirect();

        // Tanpa `pr.create`: pencarian form kosong.
        $this->actingAs($this->makeUser('driver'));
        $form->call('cariPilihan', 'rows.0.item_id', 'BAUT');
        $this->assertSame([], $this->nilai($form));

        Livewire::actingAs($staf)->test(PurchaseRequestList::class)->assertSeeHtml('id="filter-gudang-prq"')->assertSeeHtml('class="nx-pilih"');

        // Catat pemesanan: vendor aktif & sementara, tanpa nonaktif; hanya saat dialog terbuka.
        $prq = $this->prqManual();
        $detail = Livewire::actingAs($this->pembeli)->test(PurchaseRequestDetail::class, ['purchaseRequest' => $prq])
            ->call('cariPilihan', 'order.vendor_id', 'baja');
        $this->assertSame([], $this->nilai($detail), 'Tanpa dialog pesan, vendor tidak bisa dicari.');

        $detail->call('mintaDialog', 'pesan')->call('cariPilihan', 'order.vendor_id', 'baja');
        $this->assertEqualsCanonicalizing([(int) $this->vendor->id, (int) $this->sementara->id], $this->nilai($detail));
        $detail->set('order.vendor_id', (string) $this->nonaktif->id)->call('catatPesanan')->assertHasErrors('order.vendor_id');
        $this->assertSame(0, $prq->orders()->count());
    }

    #[Test]
    public function tc_po_17_vendor_po_saringan_dan_harga_beli_dicari_ke_server(): void
    {
        $po = Livewire::actingAs($this->pembeli)->test(PurchaseOrderForm::class)->assertSeeHtml('id="po-vendor"')
            ->call('cariPilihan', 'form.vendor_id', 'baja');
        $this->assertSame([(int) $this->vendor->id], $this->nilai($po), 'PO hanya ke vendor aktif (bukan sementara/nonaktif).');
        $po->set('form.vendor_id', (string) $this->sementara->id)->set('form.warehouse_id', (string) $this->gudang->id)
            ->call('simpan')->assertHasErrors('form.vendor_id');

        // Saringan vendor daftar PO: hanya vendor yang punya PO.
        $this->poDraf($this->prqManual());
        $daftar = Livewire::actingAs($this->pembeli)->test(PurchaseOrderList::class)->call('cariPilihan', 'vendorFilter', 'baja');
        $this->assertSame([(int) $this->vendor->id], $this->nilai($daftar));

        // Harga beli: vendor selain nonaktif, item aktif; form hanya saat terbuka & bagi pemegang izin buat.
        $harga = Livewire::actingAs($this->pembeli)->test(VendorPriceList::class)->call('cariPilihan', 'form.item_id', 'BAUT');
        $this->assertSame([], $this->nilai($harga), 'Form tertutup: item tidak bisa dicari.');
        $harga->call('buat')->call('cariPilihan', 'form.vendor_id', 'baja');
        $this->assertEqualsCanonicalizing([(int) $this->vendor->id, (int) $this->sementara->id], $this->nilai($harga));
        $harga->call('cariPilihan', 'form.item_id', 'BAUT');
        $this->assertNotContains((int) $this->mati->id, $this->nilai($harga));
        $harga->set('form.vendor_id', (string) $this->nonaktif->id)->set('form.item_id', (string) $this->mati->id)
            ->set('form.unit_price', '1000')->call('simpan')->assertHasErrors(['form.vendor_id', 'form.item_id']);

        $manajemen = Livewire::actingAs($this->makeUser('management'))->test(VendorPriceList::class)
            ->call('cariPilihan', 'vendorFilter', 'baja');
        $this->assertContains((int) $this->vendor->id, $this->nilai($manajemen), 'Saringan untuk semua yang boleh melihat daftar.');
        $manajemen->call('cariPilihan', 'form.vendor_id', 'baja');
        $this->assertSame([], $this->nilai($manajemen), 'Tanpa izin buat harga: vendor form tidak bisa dicari.');

        Livewire::actingAs($this->pembeli)->test(PriceHistory::class, ['item' => $this->baut])->assertSeeHtml('id="rh-vendor"');
    }
}
