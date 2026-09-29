<?php

declare(strict_types=1);

namespace Tests\Feature\Return;

use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Livewire\ReturnDetail;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Support\BinCode;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-RET-24, TC-RET-25 — pilah retur dari proyek memakai urutan pindai
 * put-away (keputusan #11, A-378): saran dari tempat simpan, pindai QR bin,
 * alasan bila menyimpang (tercatat), **tanpa** tugas put-away baru; label
 * kemasan ikut pindah bin.
 */
class PilahPindaiTest extends TenantTestCase
{
    use IssueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPemakaian();
        FeatureSetting::seed(['qc' => false], overwrite: true);
        $zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        app(SaveWarehouseLayout::class)->newRack($zona, ['code' => 'R01', 'levels' => '1', 'bins_per_level' => '2']);
    }

    /** PCK uji memindai label kemasan Di gudang item baris (sama dengan PackageLabelIssueReturnTest). */
    protected function jalankanPck(PickTask $pck): PickTask
    {
        $staf = $this->makeUser('warehouse_staff');
        $aksi = app(ProcessPickTask::class);
        $pck = $aksi->start($pck, $staf);

        foreach ($pck->lines()->with('pickTask', 'item')->get() as $baris) {
            $butuh = (float) $baris->qty_allocated;

            foreach (PackageLabel::query()->inStock()->where('warehouse_id', $pck->warehouse_id)->where('item_id', $baris->item_id)
                ->where('qty_remaining', '>', 0)->orderBy('id')->get() as $label) {
                if ($butuh <= 0) {
                    break;
                }

                $ambil = min($butuh, (float) $label->qty_remaining);
                $baris = $aksi->claimLabel($baris->load('pickTask', 'item'), $label, $ambil, $staf);
                $butuh -= $ambil;
            }

            if ($baris->scanned_at === null) {
                $aksi->recordLine($baris->load('pickTask', 'item'), (float) $baris->qty_allocated, null, null, null, $staf);
            }
        }

        return $aksi->complete($pck->refresh(), $staf);
    }

    private function b(string $petak): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R01-L1-'.$petak)->firstOrFail();
    }

    #[Test]
    public function tc_ret_24_pilah_layak_pindai_bin_saran_tempat_simpan_dan_alasan_bila_menyimpang(): void
    {
        app(SaveItemStorageLocations::class)->replace($this->baut, $this->gudang, [['tempat' => 'bin:'.$this->b('B01')->id]], $this->makeUser('warehouse_head'));
        $this->stok($this->binKrw1, $this->baut, 10);
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 5]]);
        $this->grnRetur($ret);
        $baris = $ret->lines()->sole();
        $staf = $this->makeUser('warehouse_staff');

        Livewire::actingAs($staf)->test(ReturnDetail::class, ['goodsReturn' => $ret->refresh()])
            ->assertSet('pilah.'.$baris->id.'.0.target_bin_id', (string) $this->b('B01')->id)
            ->assertSee('tempat simpan')
            ->call('pindaiBinPilah', $baris->id, 0, 'TIDAK-ADA')
            ->assertHasErrors('pindai.'.$baris->id.'.0')
            ->call('pindaiBinPilah', $baris->id, 0, BinCode::tautan($this->b('B02')->code))
            ->assertSet('pilah.'.$baris->id.'.0.target_bin_id', (string) $this->b('B02')->id)
            ->assertSee('Alasan bin lain')
            ->call('simpanPilah')
            ->assertSet('ruleCode', 'BR-RET-04')
            ->assertHasErrors('pilah.override_reason')
            ->set('pilah.'.$baris->id.'.0.override_reason', 'B01 terhalang material lain')
            ->call('simpanPilah')
            ->assertSet('ruleError', '');

        $this->assertSame(GoodsReturnStatus::Sorted, $ret->refresh()->status);
        $this->assertSame('B01 terhalang material lain', $baris->refresh()->override_reason);
        $this->assertSame(5.0, $this->saldo($this->b('B02'), $this->baut));
        $this->assertSame('B01 terhalang material lain', StockMovement::query()->where('document_type', 'goods_return')
            ->where('document_id', $ret->id)->where('to_bin_id', $this->b('B02')->id)->sole()->notes);
        $this->assertSame(0, PutawayTask::query()->whereHas('receipt', fn ($q) => $q->where('goods_return_id', $ret->id))->count(),
            'Tanpa tugas put-away baru.');

        // Bin saran dipakai → tanpa alasan.
        $this->stok($this->binKrw1, $this->baut, 3);
        $ret2 = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 3]]);
        $this->grnRetur($ret2);
        $this->pilah($ret2, [$ret2->lines()->sole()->id => [['sorting' => 'good', 'qty' => 3, 'target_bin_id' => $this->b('B01')->id]]]);
        $this->assertSame(3.0, $this->saldo($this->b('B01'), $this->baut));
    }

    #[Test]
    public function tc_ret_25_pilah_layak_memindah_label_kemasan_ke_bin_tujuan(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 24]]);
        $grn = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser('warehouse_head'), [
            $grn->lines()->sole()->id => ['packages' => 2, 'per_package' => 12],
        ]);
        app(CompletePutaway::class)->handle($grn->putawayTasks()->sole(), [], $this->makeUser());

        $sj = $this->terimaSj($this->terkirimKeKlien($this->kabel, 12));
        $label = PackageLabel::query()->whereNull('parent_id')->orderBy('sequence')->first();
        $ret = $this->ret([['key' => 'sold:'.$sj->lines()->first()->id, 'qty_base' => 12]]);
        $this->grnRetur($ret, [['goods_return_line_id' => $ret->lines()->sole()->id, 'qty_received' => 12]]);
        $this->assertSame(PackageLabelStatus::InStock, $label->refresh()->status);

        $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'good', 'qty' => 12, 'target_bin_id' => $this->b('B02')->id]]]);

        $label->refresh();
        $this->assertSame((int) $this->b('B02')->id, (int) $label->bin_id, 'A-378: label ikut pindah ke bin hasil pilah.');
        $this->assertSame(12.0, (float) $label->qty_remaining);
        $this->assertSame(PackageLabelStatus::InStock, $label->status);
    }
}
