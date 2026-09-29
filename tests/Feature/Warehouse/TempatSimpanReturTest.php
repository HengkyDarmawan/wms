<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\StorageDedicationOverride;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-54b — Khusus Barang Ini di **pilah retur** (BR-WH-10, A-366/A-367):
 * hasil pilah ke bin khusus barang lain ditolak; Kepala Gudang membuka dengan
 * alasan, tercatat dengan nomor RET.
 */
class TempatSimpanReturTest extends TenantTestCase
{
    use ReturnFixtures;

    #[Test]
    public function tc_wh_54b_khusus_di_pilah_retur(): void
    {
        $this->siapkanTransfer();
        $zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        app(SaveWarehouseLayout::class)->newRack($zona, ['code' => 'R01', 'levels' => '1', 'bins_per_level' => '2']);
        $khusus = Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R01-L1-B01')->firstOrFail();
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $kepala->forgetPermissionCache();
        app(SaveItemStorageLocations::class)->replace($this->semen, $this->gudang, [['tempat' => 'bin:'.$khusus->id, 'khusus' => true]], $kepala);

        $this->stok($this->binKrw1, $this->baut, 10);
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 5]]);
        $this->grnRetur($ret);
        $baris = $ret->lines()->sole()->id;

        try {
            $this->pilah($ret, [$baris => [['sorting' => 'good', 'qty' => 5, 'target_bin_id' => $khusus->id]]]);
            $this->fail('Bin khusus barang lain seharusnya menolak.');
        } catch (ReturnRuleException $e) {
            $this->assertSame('BR-WH-10', $e->rule);
            $this->assertArrayHasKey('target_bin_id', $e->fieldErrors);
        }

        $ret = $this->pilah($ret, [$baris => [['sorting' => 'good', 'qty' => 5, 'target_bin_id' => $khusus->id, 'buka_khusus' => 'Barang retur sementara']]], $kepala);

        $this->assertSame(GoodsReturnStatus::Sorted, $ret->status);
        $this->assertSame(5.0, $this->saldo($khusus, $this->baut));
        $catatan = StorageDedicationOverride::query()->sole();
        $this->assertSame(['goods_return', $ret->number, 'Barang retur sementara'], [$catatan->document_type, $catatan->document_number, $catatan->reason]);
    }
}
