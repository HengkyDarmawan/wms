<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\Template\Livewire\LabelDesigner;
use App\Domain\Template\Livewire\LabelPrint;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-TPL-34 — `<x-pilih>` di menu Template & label (A-398): ukuran label
 * (desainer & cetak) dan saringan gudang cetak label bin dimuat sekaligus;
 * nilai & aturan tetap (formatId string, bawaan per jenis).
 */
class PilihanTemplateTest extends TenantTestCase
{
    #[Test]
    public function tc_tpl_34_ukuran_label_dan_gudang_memakai_pilihan_yang_bisa_dicari(): void
    {
        $gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $admin = $this->makeUser('company_admin');
        $lot = LabelFormat::query()->where('code', 'THERMAL-40X30')->firstOrFail();

        Livewire::actingAs($admin)->test(LabelDesigner::class, ['type' => 'label_lot', 'formatId' => (string) $lot->id])
            ->assertSeeHtml('id="ld-format"')->assertSeeHtml('class="nx-pilih"')
            ->assertSeeHtml('>'.e($lot->name.' — '.$lot->summary()).'</option>')
            ->assertSet('formatId', (string) $lot->id);

        Livewire::actingAs($admin)->test(LabelPrint::class, ['type' => 'label_bin'])
            ->assertSeeHtml('id="lbl-gudang"')->assertSeeHtml('id="lbl-kertas"')
            ->assertSeeHtml('>'.e($gudang->code.' — '.$gudang->name).'</option>')
            ->set('warehouseFilter', (string) $gudang->id)->assertSet('warehouseFilter', (string) $gudang->id);
    }
}
