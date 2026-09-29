<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\Receipt\Livewire\PutawayList;
use App\Domain\Receipt\Livewire\ReceiptForm;
use App\Domain\Receipt\Livewire\ReceiptList;
use App\Domain\Receipt\Livewire\VendorReturnForm;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-34–TC-GRN-34b — `<x-pilih>` di modul Barang masuk (A-391): vendor &
 * item form GRN dicari ke server dengan daftar yang sama seperti dulu (vendor
 * aktif, item aktif), izin layar diulang, id di luar daftar ditolak simpan
 * kecuali nilai tersimpan di draf; daftar gudang/dokumen memakai kotak yang
 * bisa dicari.
 */
class PilihanBarangMasukTest extends TenantTestCase
{
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    /** @return array<int, array<string, mixed>> */
    private function hasil($komponen): array
    {
        return $komponen->effects['returns'][0] ?? [];
    }

    #[Test]
    public function tc_grn_34_vendor_dan_item_dicari_ke_server_dengan_cakupan_yang_sama(): void
    {
        $mati = Vendor::create([
            'code' => 'V-BAJU', 'name' => 'PT Baja Usang',
            'vendor_type' => VendorType::Company, 'status' => VendorStatus::Active, 'is_active' => false,
        ]);
        $usang = $this->buatItem('BAUT-M10', TrackingMode::None, Uom::query()->where('code', 'PCS')->value('id'), ['status' => ItemStatus::Inactive]);
        $staf = $this->makeUser('warehouse_staff');

        $form = Livewire::actingAs($staf)->test(ReceiptForm::class)
            ->assertSeeHtml('data-server="1"')
            ->call('cariPilihan', 'form.vendor_id', 'baja');
        $this->assertSame(['V-BAJA — PT Baja Jaya'], array_column($this->hasil($form), 'text'));

        $form->call('cariPilihan', 'rows.0.item_id', 'baut');
        $this->assertSame(['BAUT-M12 — BAUT-M12'], array_column($this->hasil($form), 'text'));

        // Model lain (gudang, dimuat sekaligus) tidak bisa dicari.
        $form->call('cariPilihan', 'form.warehouse_id', 'ckg');
        $this->assertSame([], $this->hasil($form));

        // Id di luar daftar dari browser → ditolak di isiannya sendiri.
        $form->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.vendor_id', (string) $mati->id)
            ->set('rows.0.item_id', (string) $usang->id)->set('rows.0.qty', '5')
            ->call('simpan')->assertHasErrors(['form.vendor_id', 'rows.0.item_id'])
            ->set('form.vendor_id', (string) $this->vendor->id)
            ->set('rows.0.item_id', (string) $this->baut->id)
            ->call('simpan')->assertHasNoErrors()->assertSet('ruleError', '')->assertRedirect();

        // Tanpa `receipt.create` (auditor): pencarian kosong.
        $this->actingAs($this->makeUser('internal_auditor'));
        $form->call('cariPilihan', 'form.vendor_id', 'baja');
        $this->assertSame([], $this->hasil($form));
    }

    #[Test]
    public function tc_grn_34b_draf_lama_tetap_bisa_disimpan_dan_layar_memakai_kotak_pilihan(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $draf = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 2]]);
        $this->baut->update(['status' => ItemStatus::Inactive]);

        // Item draf kini nonaktif: tidak berlabel di kotak; nilai tersimpan tidak ditolak oleh daftar
        // pilihan, keputusan tetap di aksi simpan dengan pesan aturan yang sama seperti dulu.
        Livewire::actingAs($staf)->test(ReceiptForm::class, ['goodsReceipt' => $draf])
            ->assertDontSeeHtml('>BAUT-M12 — BAUT-M12</option>')
            ->set('form.notes', 'ubah catatan')->call('simpan')
            ->assertHasNoErrors('rows.0.item_id')
            ->assertSet('ruleError', 'Baris 1 (BAUT-M12): item berstatus Nonaktif tidak bisa diterima. Lengkapi dan aktifkan item lebih dulu.');

        // Draf dengan item aktif: menyimpan ulang tanpa mengubah item tetap berhasil.
        $this->baut->update(['status' => ItemStatus::Active]);
        Livewire::actingAs($staf)->test(ReceiptForm::class, ['goodsReceipt' => $draf])
            ->set('form.notes', 'ubah catatan')->call('simpan')->assertHasNoErrors()->assertSet('ruleError', '');
        $this->assertSame('ubah catatan', $draf->refresh()->notes);

        Livewire::actingAs($staf)->test(ReceiptList::class)->assertSeeHtml('id="filter-gudang-grn"')->assertSeeHtml('class="nx-pilih"');
        Livewire::actingAs($staf)->test(PutawayList::class)->assertSeeHtml('id="filter-gudang-put"')->assertSeeHtml('class="nx-pilih"');
        Livewire::actingAs($staf)->test(VendorReturnForm::class)->assertSeeHtml('id="rtv-grn"')->assertSeeHtml('class="nx-pilih"');
    }
}
