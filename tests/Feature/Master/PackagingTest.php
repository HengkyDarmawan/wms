<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\RememberItemPackaging;
use App\Domain\Master\Actions\SaveItem;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Livewire\ItemDetail;
use App\Domain\Master\Livewire\ItemForm;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\QtyFormat;
use App\Domain\Master\Support\UnitInput;
use DomainException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-36 dan TC-MST-37 — kemasan item: tab *Kemasan*, kemasan nonaktif
 * tidak hidup lagi, kemasan diingat dari dokumen, dan uraian "9 DUS 8 BOX"
 * (A-291 s.d. A-294).
 */
class PackagingTest extends TenantTestCase
{
    private function uom(string $kode): Uom
    {
        return Uom::query()->where('code', $kode)->firstOrFail();
    }

    private function masker(): Item
    {
        return Item::create([
            'code' => 'MASKER', 'name' => 'Masker', 'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None, 'base_uom_id' => $this->uom('BOX')->id,
        ])->refresh();
    }

    #[Test]
    public function tc_mst_36_tab_kemasan_kemasan_nonaktif_dan_ingat_dari_dokumen(): void
    {
        $this->actingAs($this->makeUser('company_admin'));
        $item = $this->masker();

        ItemUomConversion::create(['item_id' => $item->id, 'uom_id' => $this->uom('DUS')->id, 'qty_base' => 12, 'is_active' => true]);
        ItemUomConversion::create(['item_id' => $item->id, 'uom_id' => $this->uom('PACK')->id, 'qty_base' => 4, 'is_active' => false]);

        // Kemasan nonaktif tidak dimuat, jadi simpan ulang tidak menghidupkannya.
        $form = Livewire::test(ItemForm::class, ['item' => $item])
            ->assertCount('conversions', 1)
            ->set('tabTambahan', 'konversi')->assertSee(__('Kemasan'))->assertSee('DUS');

        // Kemasan yang diingat dari GRN saat form terbuka tidak ikut dinonaktifkan.
        $this->assertTrue(app(RememberItemPackaging::class)->handle($item, $this->uom('SET'), 6, null, 'GRN/UJI'));
        $form->call('simpan')->assertHasNoErrors();

        $aktif = $item->refresh()->activeConversions->pluck('uom.code')->all();
        $this->assertEqualsCanonicalizing(['DUS', 'SET'], $aktif);

        // Hanya menambah: tidak menimpa kemasan aktif dan tidak menghidupkan yang nonaktif.
        $this->assertFalse(app(RememberItemPackaging::class)->handle($item, $this->uom('DUS'), 20));
        $this->assertFalse(app(RememberItemPackaging::class)->handle($item, $this->uom('PACK'), 5));
        $this->assertSame(12.0, (float) $item->activeConversions()->where('uom_id', $this->uom('DUS')->id)->value('qty_base'));
        $this->assertFalse((bool) ItemUomConversion::query()->where('item_id', $item->id)->where('uom_id', $this->uom('PACK')->id)->value('is_active'));

        Livewire::test(ItemDetail::class, ['item' => $item])->assertSee('1 DUS = 12 BOX')->assertDontSee('PACK');

        // Satuan input: kemasan aktif, isian "1 X = …", dan item per potong ditolak.
        $this->assertSame(36.0, UnitInput::resolve($item, 3, $this->uom('DUS')->id)['qty_base']);
        $this->assertSame(10.0, UnitInput::resolve($item, 2, $this->uom('ROLL')->id, 5)['qty_base']);
        $this->expectException(DomainException::class);
        UnitInput::resolve($item, 2, $this->uom('ROLL')->id);
    }

    #[Test]
    public function tc_mst_37_uraian_kemasan(): void
    {
        $item = $this->masker();
        app(SaveItem::class)->handle($item, ['name' => 'Masker', 'base_uom_id' => $item->base_uom_id, 'item_kind' => 'standard'], [
            ['uom_id' => $this->uom('DUS')->id, 'qty_base' => 12],
            ['uom_id' => $this->uom('PACK')->id, 'qty_base' => 4],
        ]);
        $item->refresh();

        $this->assertSame('9 DUS 2 PACK', QtyFormat::packaging($item, 116));
        $this->assertSame('9 DUS 2 PACK 1 BOX', QtyFormat::packaging($item, 117));
        $this->assertSame('−1 DUS', QtyFormat::packaging($item, -12));
        $this->assertNull(QtyFormat::packaging($item, 3), 'Kurang dari satu kemasan: cukup satuan dasar.');
        $this->assertSame('1.234,5', QtyFormat::number(1234.5));
        $this->assertSame('120 BOX', QtyFormat::withUnit(120, 'BOX'));
    }
}
