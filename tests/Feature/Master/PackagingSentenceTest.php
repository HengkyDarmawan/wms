<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\SaveItem;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Livewire\ItemDetail;
use App\Domain\Master\Livewire\ItemForm;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\QtyFormat;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-MST-45 s.d. TC-MST-48 — kemasan sebagai kalimat "1 DUS berisi 40 PACK"
 * (A-355), validasi per baris, data lama, dan kunci satuan dasar karena
 * pergerakan stok (A-356, BR-MST-02).
 */
class PackagingSentenceTest extends TenantTestCase
{
    use ReceiptFixtures;

    private function uom(string $kode): Uom
    {
        return Uom::query()->where('code', $kode)->firstOrFail();
    }

    private function masker(): Item
    {
        return app(SaveItem::class)->handle(null, [
            'code' => 'MASKER', 'name' => 'Masker', 'base_uom_id' => $this->uom('BOX')->id, 'item_kind' => 'standard',
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $baris */
    private function galat(Item $item, array $baris): array
    {
        try {
            app(SaveItem::class)->handle($item, ['name' => $item->name, 'item_kind' => 'standard'], $baris);
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-STK-09', $e->rule);

            return $e->fieldErrors;
        }

        $this->fail('Kemasan seharusnya ditolak.');
    }

    #[Test]
    public function tc_mst_45_kemasan_bertingkat_dihitung_ke_satuan_dasar(): void
    {
        $this->actingAs($this->makeUser('company_admin'));
        $item = $this->masker();
        [$pack, $dus] = [$this->uom('PACK'), $this->uom('DUS')];

        // Hasil tampil langsung saat mengetik, beserta rinciannya dan contoh penerimaan.
        Livewire::test(ItemForm::class, ['item' => $item])
            ->set('tabTambahan', 'konversi')
            ->assertSee(__('Satuan terkecil yang dikeluarkan ke proyek. Stok dihitung dalam satuan ini.'))
            ->call('tambahKonversi')->call('tambahKonversi')
            ->set('conversions.0.uom_id', (string) $pack->id)->set('conversions.0.content_qty', '12')
            ->set('conversions.1.uom_id', (string) $dus->id)->set('conversions.1.content_qty', '40')
            ->set('conversions.1.content_uom_id', (string) $pack->id)
            ->assertSeeHtml('data-hasil-kemasan>12 BOX<')
            ->assertSeeHtml('data-hasil-kemasan>480 BOX<')
            ->assertSee('(40 × 12)')
            ->assertSee('Contoh: terima 5 DUS → stok bertambah 2.400 BOX.')
            ->call('simpan')->assertHasNoErrors();

        $kemasanDus = ItemUomConversion::query()->where('item_id', $item->id)->where('uom_id', $dus->id)->sole();
        $this->assertSame([480.0, 40.0, (int) $pack->id], [(float) $kemasanDus->qty_base, (float) $kemasanDus->content_qty, (int) $kemasanDus->content_uom_id]);

        // Dibuka lagi: kalimat yang sama; isi PACK diubah → DUS ikut dihitung ulang dan diberitahukan.
        Livewire::test(ItemForm::class, ['item' => $item->refresh()])
            ->set('tabTambahan', 'konversi')
            ->assertSet('conversions.1.content_qty', '40')
            ->assertSet('conversions.1.content_uom_id', (string) $pack->id)
            ->set('conversions.0.content_qty', '10')
            ->assertSee('DUS berubah dari 480 BOX menjadi 400 BOX.')
            ->call('simpan')->assertHasNoErrors();

        $item->refresh();
        $this->assertSame(400.0, (float) $kemasanDus->refresh()->qty_base);
        $this->assertSame('1 DUS 5 BOX', QtyFormat::packaging($item, 405));
        $this->assertSame('1 DUS 1 PACK', QtyFormat::packaging($item, 410));

        Livewire::test(ItemDetail::class, ['item' => $item])->assertSee('1 DUS berisi 40 PACK')->assertSee('1 PACK berisi 10 BOX');
    }

    #[Test]
    public function tc_mst_46_validasi_kemasan_per_baris_tanpa_lingkaran(): void
    {
        $this->actingAs($this->makeUser('company_admin'));
        $item = $this->masker();
        [$box, $pack, $dus] = [$this->uom('BOX')->id, $this->uom('PACK')->id, $this->uom('DUS')->id];
        $baris = fn (int $uom, mixed $isi, ?int $satuanIsi = null) => ['uom_id' => $uom, 'content_qty' => $isi, 'content_uom_id' => $satuanIsi ?? ''];

        $this->assertSame(['conversions.0' => 'BOX adalah satuan dasar; kemasan harus satuan lain.'], $this->galat($item, [$baris($box, 12)]));
        $this->assertSame(['conversions.0' => 'Isi 1 DUS harus lebih dari 1.'], $this->galat($item, [$baris($dus, 1)]));
        $this->assertSame(['conversions.0' => 'Isi 1 DUS wajib diisi.'], $this->galat($item, [$baris($dus, '')]));
        $this->assertSame(['conversions.1' => 'Kemasan DUS sudah ada di baris 1.'], $this->galat($item, [$baris($dus, 12), $baris($dus, 10)]));
        $this->assertSame(['conversions.0' => '1 DUS tidak bisa berisi DUS.'], $this->galat($item, [$baris($dus, 2, $dus)]));
        $this->assertStringContainsString('tidak ada di daftar kemasan', $this->galat($item, [$baris($dus, 2, $pack)])['conversions.0']);

        $lingkar = $this->galat($item, [$baris($dus, 2, $pack), $baris($pack, 3, $dus)]);
        $this->assertSame(['conversions.0', 'conversions.1'], array_keys($lingkar));
        $this->assertStringContainsString('Kemasan saling berisi (', $lingkar['conversions.0']);
        $this->assertStringContainsString('Pilih satuan isi yang lebih kecil.', $lingkar['conversions.1']);

        $this->assertSame(0, ItemUomConversion::query()->where('item_id', $item->id)->count(), 'Satu baris salah = tidak ada yang tersimpan.');

        // Layar menampilkan pesannya di baris yang salah.
        Livewire::test(ItemForm::class, ['item' => $item])
            ->set('tabTambahan', 'konversi')
            ->call('tambahKonversi')->call('tambahKonversi')
            ->set('conversions.0.uom_id', (string) $dus)->set('conversions.0.content_qty', '12')
            ->set('conversions.1.uom_id', (string) $dus)->set('conversions.1.content_qty', '10')
            ->call('simpan')
            ->assertHasErrors('form.conversions.1')
            ->assertSee('Kemasan DUS sudah ada di baris 1.');
    }

    #[Test]
    public function tc_mst_47_data_kemasan_lama_tetap_terbaca_dan_tersimpan(): void
    {
        $this->actingAs($this->makeUser('company_admin'));
        $item = $this->masker();
        [$pack, $dus] = [$this->uom('PACK'), $this->uom('DUS')];

        // Data sebelum A-355: hanya qty_base, termasuk isi ≤ 1 yang kini tidak boleh diketik baru.
        ItemUomConversion::create(['item_id' => $item->id, 'uom_id' => $dus->id, 'qty_base' => 12, 'is_active' => true]);
        ItemUomConversion::create(['item_id' => $item->id, 'uom_id' => $pack->id, 'qty_base' => 0.5, 'is_active' => true]);

        $form = Livewire::test(ItemForm::class, ['item' => $item->refresh()])
            ->set('tabTambahan', 'konversi')
            ->assertSet('conversions.0.content_qty', '0,5')->assertSet('conversions.0.content_uom_id', '')
            ->assertSet('conversions.1.content_qty', '12')->assertSet('conversions.1.content_uom_id', '')
            ->assertSeeHtml('data-hasil-kemasan>12 BOX<')
            ->call('simpan')->assertHasNoErrors();

        $this->assertSame(0.5, (float) ItemUomConversion::query()->where('item_id', $item->id)->where('uom_id', $pack->id)->value('qty_base'));

        // Yang diubah wajib mengikuti aturan baru.
        $form->set('conversions.0.content_qty', '0,4')->call('simpan')->assertHasErrors('form.conversions.0')->assertSee('Isi 1 PACK harus lebih dari 1.');
        $form->set('conversions.0.content_qty', '0,5')->set('conversions.1.content_qty', '2')->set('conversions.1.content_uom_id', (string) $pack->id)
            ->call('simpan')->assertHasErrors('form.conversions.1')->assertSee('1 DUS harus berisi lebih dari 1 BOX.');

        Livewire::test(ItemDetail::class, ['item' => $item->refresh()])->assertSee('1 DUS berisi 12 BOX');
    }

    #[Test]
    public function tc_mst_48_satuan_dasar_terkunci_karena_pergerakan_stok(): void
    {
        $this->siapkanPenerimaan();
        $this->assertFalse($this->baut->baseUomIsLocked(), 'Belum ada pergerakan stok.');

        app(ReceiveGoodsReceipt::class)->handle($this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 10]]), $this->makeUser('warehouse_staff'));
        $this->baut->refresh();

        $this->assertSame('Terkunci karena item ini sudah punya pergerakan stok.', $this->baut->baseUomLockReason());
        $this->assertFalse($this->baut->hasTrackingRecords(), 'BR-MST-05 (hapus item) tidak berubah.');

        try {
            app(SaveItem::class)->handle($this->baut, ['name' => $this->baut->name, 'base_uom_id' => $this->uom('BOX')->id]);
            $this->fail('Satuan dasar seharusnya terkunci.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-02', $e->rule);
            $this->assertStringContainsString('pergerakan stok', $e->fieldErrors['base_uom_id']);
        }

        // Alasan tampil di dekat isian; kemasan tetap boleh ditambah.
        Livewire::actingAs($this->makeUser('company_admin'))->test(ItemForm::class, ['item' => $this->baut])
            ->assertSet('baseUomLocked', true)
            ->assertSee('Terkunci karena item ini sudah punya pergerakan stok.')
            ->set('tabTambahan', 'konversi')
            ->call('tambahKonversi')
            ->set('conversions.0.uom_id', (string) $this->uom('DUS')->id)->set('conversions.0.content_qty', '100')
            ->call('simpan')->assertHasNoErrors();

        $this->assertSame(100.0, (float) $this->baut->activeConversions()->value('qty_base'));
    }
}
