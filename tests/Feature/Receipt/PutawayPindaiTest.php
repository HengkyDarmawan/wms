<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Receipt\Actions\CancelPutaway;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Livewire\PutawayDetail;
use App\Domain\Receipt\Livewire\PutawayWaiting;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\StorageDedicationOverride;
use App\Domain\Warehouse\Support\BinCode;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-PUT-10 s.d. TC-PUT-15 — Tata letak gudang Bagian 4: put-away **per
 * baris** tanpa status baru (keputusan #6, A-375), layar **Menunggu
 * Dimasukkan** dengan pindai label barang → bin tujuan → QR bin (A-376), bin
 * lain beralasan, Khusus barang lain ditolak, tanda penuh (A-377), label
 * kemasan ikut pindah, dan cakupan gudang.
 */
class PutawayPindaiTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Item $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        $zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        app(SaveWarehouseLayout::class)->newRack($zona, ['code' => 'R01', 'levels' => '2', 'bins_per_level' => '3']);
        $this->cat = $this->buatItem('CAT-TEMBOK', TrackingMode::None, Uom::query()->where('code', 'PCS')->value('id'));
        $this->tempat($this->baut, [['tempat' => 'bin:'.$this->b('L1', 'B01')->id]]);
    }

    private function b(string $level, string $petak): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R01-'.$level.'-'.$petak)->firstOrFail();
    }

    private function kepala()
    {
        $u = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $u->forgetPermissionCache();

        return $u;
    }

    private function tempat(Item $item, array $baris): void
    {
        app(SaveItemStorageLocations::class)->replace($item, $this->gudang, $baris, $this->kepala());
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function tugasPut(array $lines, array $label = []): PutawayTask
    {
        $grn = $this->grnDiterima($lines);
        $rencana = [];

        foreach ($label as $index => $plan) {
            $rencana[$grn->lines()->orderBy('id')->get()[$index]->id] = $plan;
        }

        return app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser('warehouse_head'), $rencana)
            ->putawayTasks()->with('lines')->latest('id')->firstOrFail();
    }

    #[Test]
    public function tc_put_10_put_away_per_baris_tugas_tetap_menunggu_sampai_baris_terakhir(): void
    {
        $put = $this->tugasPut([['item_id' => $this->baut->id, 'qty_received' => 10], ['item_id' => $this->cat->id, 'qty_received' => 5]]);
        [$bBaut, $bCat] = $put->lines()->orderBy('id')->get()->all();
        $this->assertSame((int) $this->b('L1', 'B01')->id, (int) $bBaut->suggested_bin_id, 'Saran dari tempat simpan.');
        $staf = $this->makeUser();

        $put = app(CompletePutaway::class)->placeLine($put, (int) $bBaut->id, ['bin_id' => $bBaut->suggested_bin_id], $staf);
        $this->assertSame(PutawayTaskStatus::Pending, $put->status, 'Keputusan #6: tanpa status baru, tetap Menunggu.');
        $this->assertSame(10.0, $this->saldo($this->b('L1', 'B01'), $this->baut));
        $this->assertNotNull($bBaut->refresh()->scanned_at);
        $this->assertSame(1, StockMovement::query()->where('document_type', 'putaway_task')->where('document_id', $put->id)->count());

        try {
            app(CompletePutaway::class)->placeLine($put, (int) $bBaut->id, ['bin_id' => $bBaut->suggested_bin_id], $staf);
            $this->fail('Baris yang sudah ditaruh tidak boleh ditaruh lagi.');
        } catch (ReceiptRuleException $e) {
            $this->assertSame('BR-GEN-01', $e->rule);
        }

        // Selesaikan sisanya: hanya baris yang belum; baris baut tidak bergerak dua kali.
        $put = app(CompletePutaway::class)->handle($put->refresh(), [], $staf);
        $this->assertSame(PutawayTaskStatus::Completed, $put->status);
        $this->assertSame(10.0, $this->saldo($this->b('L1', 'B01'), $this->baut));
        $this->assertSame(5.0, $this->saldo((int) $bCat->suggested_bin_id, $this->cat));
        $this->assertSame(2, StockMovement::query()->where('document_type', 'putaway_task')->where('document_id', $put->id)->count());
    }

    #[Test]
    public function tc_put_11_layar_menunggu_dimasukkan_pindai_label_lalu_qr_bin(): void
    {
        $put = $this->tugasPut([['item_id' => $this->baut->id, 'qty_received' => 10]]);
        $baris = $put->lines()->sole();
        $staf = $this->makeUser();

        $layar = Livewire::actingAs($staf)->test(PutawayWaiting::class)
            ->assertOk()
            ->assertSee('BAUT-M12')
            ->assertSee('R01 · L1 · 01')
            ->set('kodeBarang', 'TIDAK-ADA')->call('pindaiBarang')
            ->assertHasErrors('kodeBarang')
            // QR bin dipindai lebih dulu → diminta label barang.
            ->set('kodeBarang', BinCode::tautan('CKG-D-R01-L1-B01'))->call('pindaiBarang')
            ->assertHasErrors('kodeBarang')
            ->set('kodeBarang', 'baut-m12')->call('pindaiBarang')
            ->assertSet('pilih', (int) $baris->id)
            ->assertSee('Taruh di')
            ->set('kodeBin', 'TIDAK-ADA')->call('pindaiBin')
            ->assertHasErrors('kodeBin')
            // Bin lain → alasan wajib.
            ->set('kodeBin', BinCode::tautan('CKG-D-R01-L1-B02'))->call('pindaiBin')
            ->assertSet('binLain', (int) $this->b('L1', 'B02')->id)
            ->call('taruhBinLain')
            ->assertHasErrors('alasan');
        $this->assertNull($baris->refresh()->scanned_at);

        $layar->set('alasan', 'Bin saran terhalang palet')->call('taruhBinLain')
            ->assertSet('ruleError', '')
            ->assertSet('pilih', null);

        $baris->refresh();
        $this->assertSame((int) $this->b('L1', 'B02')->id, (int) $baris->bin_id);
        $this->assertSame('Bin saran terhalang palet', $baris->override_reason);
        $this->assertSame(PutawayTaskStatus::Completed, $put->refresh()->status);
        $this->assertSame(10.0, $this->saldo($this->b('L1', 'B02'), $this->baut));
        $this->assertSame('Bin saran terhalang palet', StockMovement::query()->where('document_type', 'putaway_task')->where('document_id', $put->id)->sole()->notes);

        // Bin saran diketik sebagai kode pendek (berawalan zona bila kembar) → langsung ditaruh.
        $put2 = $this->tugasPut([['item_id' => $this->baut->id, 'qty_received' => 4]]);
        Livewire::actingAs($staf)->test(PutawayWaiting::class)
            ->call('mulai', (int) $put2->lines()->sole()->id)
            ->set('kodeBin', 'r01 l1 01')->call('pindaiBin')
            ->assertHasErrors('kodeBin') // kode pendek kembar dengan bin zona A → ambigu, ditolak
            ->set('kodeBin', 'd r01 l1 01')->call('pindaiBin')
            ->assertSet('ruleError', '')
            ->assertSet('binLain', null)
            ->assertSee('BAUT-M12 ditaruh di R01 · L1 · 01.');
        $this->assertSame(4.0, $this->saldo($this->b('L1', 'B01'), $this->baut));
    }

    #[Test]
    public function tc_put_12_label_kemasan_dipindai_dan_ikut_pindah_bin(): void
    {
        $put = $this->tugasPut([['item_id' => $this->baut->id, 'qty_received' => 10], ['item_id' => $this->cat->id, 'qty_received' => 6]],
            [0 => ['packages' => 2, 'per_package' => 5]]);
        $label = PackageLabel::query()->where('item_id', $this->baut->id)->whereNull('parent_id')->orderBy('id')->get();
        $this->assertCount(2, $label);
        [$bBaut] = $put->lines()->orderBy('id')->get()->all();

        Livewire::actingAs($this->makeUser())->test(PutawayWaiting::class)
            ->set('kodeBarang', $label[0]->code)->call('pindaiBarang')
            ->assertSet('pilih', (int) $bBaut->id)
            ->set('kodeBin', 'CKG-D-R01-L1-B01')->call('pindaiBin')
            ->assertSet('ruleError', '');

        $this->assertSame([(int) $this->b('L1', 'B01')->id], $label->map(fn ($l) => (int) $l->refresh()->bin_id)->unique()->values()->all(),
            'A-296: label kemasan baris itu ikut pindah ke bin tujuan.');
        $this->assertSame(PutawayTaskStatus::Pending, $put->refresh()->status, 'Baris cat belum ditaruh.');
    }

    #[Test]
    public function tc_put_13_bin_khusus_barang_lain_ditolak_di_layar_pindai(): void
    {
        $khusus = $this->b('L2', 'B01');
        $this->tempat($this->cat, [['tempat' => 'bin:'.$khusus->id, 'khusus' => true]]);
        $put = $this->tugasPut([['item_id' => $this->baut->id, 'qty_received' => 3]]);
        $baris = $put->lines()->sole();

        Livewire::actingAs($this->makeUser())->test(PutawayWaiting::class)
            ->call('mulai', (int) $baris->id)
            ->set('kodeBin', BinCode::tautan($khusus->code))->call('pindaiBin')
            ->set('alasan', 'Rak lain penuh')->call('taruhBinLain')
            ->assertSet('ruleCode', 'BR-WH-10');
        $this->assertNull($baris->refresh()->scanned_at);

        Livewire::actingAs($this->kepala())->test(PutawayWaiting::class)
            ->call('mulai', (int) $baris->id)
            ->set('kodeBin', $khusus->code)->call('pindaiBin')
            ->set('alasan', 'Rak lain penuh')
            ->set('bukaKhusus', 'Darurat, sementara')
            ->call('taruhBinLain')
            ->assertSet('ruleError', '');

        $this->assertSame(3.0, $this->saldo($khusus, $this->baut));
        $this->assertSame('Darurat, sementara', StorageDedicationOverride::query()->sole()->reason);
    }

    #[Test]
    public function tc_put_14_cakupan_gudang_dan_tanda_tempat_simpan_penuh(): void
    {
        $this->b('L1', 'B01')->forceFill(['capacity_qty' => 5])->save();
        $put = $this->tugasPut([['item_id' => $this->baut->id, 'qty_received' => 10]]);
        $this->assertNotSame((int) $this->b('L1', 'B01')->id, (int) $put->lines()->sole()->suggested_bin_id, 'Tempat penuh → aturan lama.');

        Livewire::actingAs($this->makeUser())->test(PutawayWaiting::class)
            ->assertSee('Tempat simpan penuh');
        Livewire::actingAs($this->makeUser())->test(PutawayDetail::class, ['putawayTask' => $put])
            ->assertSee('Tempat simpan penuh')
            ->assertSee('Pindai di HP');

        $lain = $this->buatGudang('SBY', 'Gudang Surabaya');
        $stafLain = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $lain->id);
        $stafLain->forgetPermissionCache();
        Livewire::actingAs($stafLain)->test(PutawayWaiting::class)
            ->assertDontSee('BAUT-M12')
            ->assertSee('Tidak ada barang yang menunggu dimasukkan.')
            ->call('mulai', (int) $put->lines()->sole()->id)
            ->assertSet('pilih', null)
            ->assertHasErrors('kodeBarang');

        $this->actingAs($this->makeUser())->get($this->tenantUrl('putaways/waiting'))->assertOk()->assertSee('Menunggu dimasukkan');
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('putaways/waiting'))->assertForbidden();
    }

    #[Test]
    public function tc_put_15_batal_setelah_sebagian_ditaruh_lalu_buat_ulang_hanya_sisa(): void
    {
        $put = $this->tugasPut([['item_id' => $this->baut->id, 'qty_received' => 10], ['item_id' => $this->cat->id, 'qty_received' => 5]]);
        [$bBaut, $bCat] = $put->lines()->orderBy('id')->get()->all();
        $kepala = $this->kepala();

        Livewire::actingAs($kepala)->test(PutawayDetail::class, ['putawayTask' => $put])
            ->call('taruhBaris', (int) $bBaut->id)
            ->assertSet('ruleError', '')
            ->assertSee('Sudah ditaruh');

        app(CancelPutaway::class)->handle($put->refresh(), $this->alasan(ReasonContext::Cancel), null, $kepala);
        $this->assertSame(10.0, $this->saldo($this->b('L1', 'B01'), $this->baut), 'Baris yang sudah ditaruh tetap di binnya.');

        $baru = app(CompleteGoodsReceipt::class)->replan($put->receipt, $kepala);
        $this->assertSame([(int) $this->cat->id], $baru->lines()->pluck('item_id')->map(fn ($v) => (int) $v)->all(),
            'Hanya baris yang belum ditaruh direncanakan ulang.');
        $this->assertSame((int) $bCat->goods_receipt_line_id, (int) $baru->lines()->sole()->goods_receipt_line_id);
    }
}
