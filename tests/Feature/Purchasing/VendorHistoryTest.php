<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\Access\Models\Role;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Livewire\VendorList;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestDetail;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Livewire\PriceHistory;
use App\Domain\Purchasing\Livewire\PurchaseOrderForm;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Reports\PurchasePriceHistoryReport;
use App\Domain\Purchasing\Support\VendorSuggestions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Purchasing\Concerns\PurchasingFixtures;
use Tests\TenantTestCase;

/**
 * TC-PO-13 s.d. TC-PO-16, TC-PRQ-13, TC-MST-39 — saran vendor dari riwayat
 * (A-304), riwayat harga beli (A-309), peringatan kenaikan harga (A-306),
 * kondisi jenis vendor di PO (A-308), dan dampak nonaktif vendor (A-310).
 */
class VendorHistoryTest extends TenantTestCase
{
    use PurchasingFixtures;

    private Vendor $murah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPurchasing();

        $this->murah = Vendor::create(['code' => 'V-MURAH', 'name' => 'Toko Murah', 'vendor_type' => VendorType::Shop,
            'status' => VendorStatus::Active, 'is_active' => true]);
    }

    /** PO baut disetujui ke $vendor dengan harga $harga, bertanggal $hariLalu hari lalu. */
    private function poBaut(Vendor $vendor, float $harga, int $hariLalu, float $qty = 10): PurchaseOrder
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => $qty]]);
        $po = $this->ajukan($this->poDraf($prq, [['purchase_request_line_id' => $prq->lines()->sole()->id, 'qty_base' => $qty, 'unit_price' => $harga]],
            ['vendor_id' => $vendor->id]));
        $po->forceFill(['order_date' => now()->subDays($hariLalu)->toDateString()])->save();

        return $po->refresh();
    }

    /** Peran buatan company: tindak lanjut PRQ tanpa izin PO (tanpa harga). */
    private function rolePencatatTanpaHarga(): string
    {
        $bawaan = Role::query()->where('code', 'pr_follow_up')->firstOrFail();
        $role = Role::create(['code' => 'pencatat_pesanan', 'name' => 'Pencatat pesanan', 'guard_name' => 'web',
            'is_builtin' => false, 'is_client_role' => false, 'is_active' => true]);
        $role->permissions()->sync($bawaan->permissions()->where('name', 'not like', 'po.%')->pluck('permissions.id')->all());

        return 'pencatat_pesanan';
    }

    #[Test]
    public function tc_po_13_saran_vendor_terakhir_dan_termurah_enam_bulan_hanya_vendor_aktif(): void
    {
        $saran = app(VendorSuggestions::class);
        $this->assertNull($saran->lastVendor((int) $this->baut->id), 'Tanpa riwayat tidak ada saran.');

        $this->poBaut($this->murah, 1200, 30);
        $this->poBaut($this->vendor, 1600, 5);
        $this->poBaut($this->murah, 900, 250); // lebih murah tetapi di luar 6 bulan

        $hasil = $saran->forItems([(int) $this->baut->id])[(int) $this->baut->id];
        $this->assertSame((int) $this->vendor->id, (int) $hasil['terakhir']->id, 'Terakhir = PO paling baru.');
        $this->assertSame((int) $this->murah->id, (int) $hasil['termurah']->id, 'Termurah 6 bulan = 1.200, bukan 900 (8 bulan lalu).');
        $this->assertSame(1200.0, $saran->cheapest((int) $this->baut->id)['price']);

        // PO yang belum disetujui tidak dihitung.
        $draf = $this->poDraf($this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 3]]),
            null, ['vendor_id' => $this->murah->id]);
        $this->assertSame(PurchaseOrderStatus::Draft, $draf->status);
        $this->assertSame((int) $this->vendor->id, (int) $saran->lastVendor((int) $this->baut->id)->id);

        // Vendor nonaktif tidak disarankan (A-310).
        $this->vendor->forceFill(['status' => VendorStatus::Inactive, 'is_active' => false])->save();
        $this->assertSame((int) $this->murah->id, (int) $saran->lastVendor((int) $this->baut->id)->id);
    }

    #[Test]
    public function tc_prq_13_dialog_pesan_menyarankan_dari_riwayat_termurah_tanpa_angka_hanya_po_view(): void
    {
        $this->poBaut($this->murah, 1000, 60);
        $this->poBaut($this->vendor, 1500, 3);
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 20]]);

        // Pemegang po.view: terakhir & termurah (nama saja, tanpa harga).
        Livewire::actingAs($this->makeUser('pr_follow_up'))->test(PurchaseRequestDetail::class, ['purchaseRequest' => $prq])
            ->call('mintaDialog', 'pesan')
            ->assertSet('order.vendor_id', (string) $this->vendor->id)
            ->assertSee('Termurah 6 bln')
            ->assertSee('Toko Murah')
            ->assertDontSee('1.000')
            ->assertDontSee('Rp');

        // Tanpa po.view: hanya vendor terakhir.
        $pr = $this->makeUser($this->rolePencatatTanpaHarga());
        $this->assertFalse($pr->can('po.view'));
        Livewire::actingAs($pr)->test(PurchaseRequestDetail::class, ['purchaseRequest' => $prq])
            ->call('mintaDialog', 'pesan')
            ->assertSee('Saran dari riwayat')
            ->assertDontSee('Termurah 6 bln')
            // Purchasing bebas mengganti vendor; keterangan opsional tersimpan.
            ->set('order.vendor_id', (string) $this->murah->id)
            ->set('order.vendor_note', 'Purnajual lebih baik')
            ->set('order.external_po_no', 'PO-LUAR-9')
            ->call('catatPesanan')
            ->assertHasNoErrors();

        $this->assertSame('Purnajual lebih baik', $prq->orders()->sole()->vendor_note);
        $this->assertSame((int) $this->murah->id, (int) $prq->orders()->sole()->vendor_id);
    }

    #[Test]
    public function tc_po_14_form_po_harga_terakhir_tanda_kenaikan_dan_alasan_pilihan(): void
    {
        $this->poBaut($this->vendor, 1000, 10);
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 50]]);
        $baris = $prq->lines()->sole();

        $layar = Livewire::actingAs($this->pembeli)->withQueryParams(['prq' => $prq->id])->test(PurchaseOrderForm::class)
            ->assertSet('form.vendor_id', (string) $this->vendor->id)
            ->assertSee('Terakhir')
            ->set('price.'.$baris->id, '1090')
            ->assertDontSee('dari harga terakhir')
            ->set('price.'.$baris->id, '1150')
            ->assertSee('Naik 15,0 % dari harga terakhir');

        // Batas company dinaikkan ke 20 %: 15 % tidak ditandai.
        CompanySetting::put('po_price_increase_pct', 20);
        $layar->set('price.'.$baris->id, '1151')->assertDontSee('dari harga terakhir');

        $layar->set('form.vendor_id', (string) $this->murah->id)
            ->set('price.'.$baris->id, '1100')
            ->set('form.vendor_choice_note', 'Stok siap, kirim besok')
            ->call('simpan')
            ->assertHasNoErrors();

        $po = PurchaseOrder::query()->where('vendor_id', $this->murah->id)->latest('id')->firstOrFail();
        $this->assertSame('Stok siap, kirim besok', $po->vendor_choice_note);
    }

    #[Test]
    public function tc_po_15_riwayat_harga_beli_per_item_ringkasan_dan_ekspor(): void
    {
        $this->poBaut($this->vendor, 1000, 20, 10);
        $this->poBaut($this->vendor, 1300, 100, 30);
        $this->poBaut($this->murah, 900, 200, 5);

        $pembeli = $this->makeUser('pr_follow_up');

        $komponen = Livewire::actingAs($pembeli)->test(PriceHistory::class, ['item' => $this->baut])
            ->assertSee('Toko Murah')
            ->assertSee('data-grafik-harga', false);
        $ringkas = collect($komponen->viewData('ringkas'))->keyBy('vendor_id');
        $v = $ringkas[(int) $this->vendor->id];
        $this->assertSame(1000.0, $v['terakhir']['harga']);
        $this->assertSame(1000.0, $v['jendela'][3]['termurah']);
        $this->assertSame(1225.0, $v['jendela'][6]['rata'], 'Rata-rata tertimbang: (10×1.000 + 30×1.300) / 40.');
        $this->assertNull($ringkas[(int) $this->murah->id]['jendela'][6]['termurah'], '200 hari lalu di luar 6 bulan.');

        // Laporan & ekspor Excel (satu-satunya laporan bernilai uang, izin po.view).
        $this->actingAs($pembeli);
        $rows = app(PurchasePriceHistoryReport::class)->rows(['item' => 'BAUT-M12']);
        $this->assertCount(3, $rows);
        $this->get($this->tenantUrl('reports/riwayat-harga-beli/export?filters[item]=BAUT-M12'))->assertOk();
        $this->get($this->tenantUrl('items/'.$this->baut->id.'/price-history'))->assertOk()->assertSee('Riwayat harga beli');
        $this->get($this->tenantUrl('items/'.$this->baut->id))->assertSee(route('items.price-history', $this->baut), false);

        // Tanpa po.view: 403 dan tombol tidak tampil.
        $staf = $this->makeUser('warehouse_staff');
        $this->actingAs($staf)->get($this->tenantUrl('items/'.$this->baut->id.'/price-history'))->assertForbidden();
        $this->actingAs($staf)->get($this->tenantUrl('reports/riwayat-harga-beli/export'))->assertForbidden();
        $this->actingAs($staf)->get($this->tenantUrl('items/'.$this->baut->id))->assertDontSee(route('items.price-history', $this->baut), false);
    }

    #[Test]
    public function tc_po_16_aturan_jenis_vendor_dinilai_di_po(): void
    {
        $toko = Vendor::create(['code' => 'V-ONLINE', 'name' => 'Toko Online', 'vendor_type' => VendorType::OnlineMarketplace,
            'status' => VendorStatus::Active, 'is_active' => true]);
        $this->aturan(ApprovalDocumentType::PurchaseOrder, [$this->lapisRole('management')],
            ['match' => 'all', 'vendor_types' => ['online_marketplace']], 10, 'PO toko online');

        $this->assertSame(PurchaseOrderStatus::Approved, $this->poBaut($this->vendor, 1000, 0)->status, 'Vendor perusahaan tanpa approval.');
        $this->assertSame(PurchaseOrderStatus::PendingApproval, $this->poBaut($toko, 1000, 0)->status);
    }

    #[Test]
    public function tc_mst_39_nonaktif_vendor_menampilkan_dampak_dan_alasan_wajib(): void
    {
        $po = $this->poBaut($this->vendor, 1000, 1);
        $prq = $this->prqManual([['item_id' => $this->semen->id, 'qty_base' => 4]]);
        $this->pesan($prq);

        Livewire::actingAs($this->makeUser('company_admin'))->test(VendorList::class)
            ->call('mintaNonaktif', $this->vendor->id)
            ->assertSee($po->number)
            ->assertSee($prq->number)
            ->call('nonaktifkan')
            ->assertHasErrors('reasonCode')
            ->set('reasonCode', (string) array_key_first(ReasonCode::options(ReasonContext::Cancel)))
            ->call('nonaktifkan');

        $this->assertFalse($this->vendor->refresh()->is_active);
        $this->assertSame(VendorStatus::Inactive, $this->vendor->status);
        $this->assertSame(PurchaseOrderStatus::Approved, $po->refresh()->status, 'Dokumen terbuka tetap berjalan (P-03).');
    }
}
