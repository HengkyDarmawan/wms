<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Livewire\ItemDetail;
use App\Domain\Master\Livewire\ItemForm;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-54 — tab *Stok & pembelian otomatis* di form item: kalimat contoh
 * pembelian dari titik pesan ulang & isi sampai, peringatan bila isi sampai
 * tidak lebih besar (A-412), dan **ukuran per satuan dasar** dengan satuan
 * kategori mana pun kecuali Hitung (A-413).
 */
class ItemStokPembelianTest extends TenantTestCase
{
    private function uom(string $kode): Uom
    {
        return Uom::query()->where('code', $kode)->firstOrFail();
    }

    #[Test]
    public function tc_mst_54_isi_sampai_dan_ukuran_per_satuan_dasar(): void
    {
        $this->actingAs($this->makeUser('company_admin'));

        $form = Livewire::test(ItemForm::class)
            ->assertSet('form.measure_uom_id', '')
            ->assertSee(__('Stok & pembelian otomatis'))->assertSee(__('Isi sampai (stok ideal)'))
            ->assertViewHas('uomUkuran', fn ($u) => $u->pluck('code')->intersect(['KG', 'M', 'L', 'M2'])->count() === 4
                && ! $u->pluck('code')->contains('PCS') && ! $u->pluck('code')->contains('DUS'));

        // Kalimat contoh: titik 20, isi sampai 100, satuan dasar BATANG.
        $form->set('form.base_uom_id', (string) $this->uom('BATANG')->id)
            ->assertSee(__('Ukuran per 1 :s', ['s' => 'BATANG']))
            ->set('form.reorder_point', '20')->set('form.min_stock', '100')
            ->assertSeeHtml('data-contoh-pembelian')->assertSee('tinggal 10 BATANG')->assertSee('pembelian 90 BATANG')
            ->assertDontSeeHtml('data-peringatan-isi-sampai')
            // Isi sampai ≤ titik → peringatan.
            ->set('form.min_stock', '10')->assertSeeHtml('data-peringatan-isi-sampai')->assertSee('mis. 100');

        // Ukuran: angka tanpa satuan ditolak; satuan Hitung (PCS) ditolak; panjang M diterima.
        $form->set('form.code', 'PIPA-PVC4')->set('form.name', 'Pipa PVC 4"')->set('form.item_kind', 'standard')
            ->set('form.measure_qty', '6')->call('simpan')->assertHasErrors('form.measure_uom_id')
            ->set('form.measure_uom_id', (string) $this->uom('PCS')->id)->call('simpan')->assertHasErrors('form.measure_uom_id')
            ->set('form.measure_uom_id', (string) $this->uom('M')->id)->call('simpan')->assertHasNoErrors();

        $item = Item::query()->where('code', 'PIPA-PVC4')->firstOrFail();
        $this->assertSame([20.0, 10.0, 6.0, (int) $this->uom('M')->id], [(float) $item->reorder_point, (float) $item->min_stock, (float) $item->measure_qty, (int) $item->measure_uom_id]);

        // Detail item menulis kalimat ukuran.
        Livewire::test(ItemDetail::class, ['item' => $item])->assertSee('1 BATANG = 6 M');

        // Ubah: isian terisi; ukuran boleh berat (KG).
        Livewire::test(ItemForm::class, ['item' => $item])->assertSet('form.measure_qty', '6')
            ->set('form.measure_qty', '12')->set('form.measure_uom_id', (string) $this->uom('KG')->id)->call('simpan')->assertHasNoErrors();
        $this->assertSame((int) $this->uom('KG')->id, (int) $item->refresh()->measure_uom_id);
    }
}
