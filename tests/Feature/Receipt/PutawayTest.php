<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Receipt\Actions\CancelPutaway;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Support\PutawaySuggester;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-PUT-01 s.d. TC-PUT-07 — tugas put-away (Katalog §2.6, BR-GRN-03, BR-WH-06, A-84).
 */
class PutawayTest extends TenantTestCase
{
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    private function putDari(float $qty = 10): PutawayTask
    {
        $grn = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => $qty]]);

        return app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser())->putawayTasks()->with('lines')->sole();
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ReceiptRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_put_01_saran_bin_yang_sudah_berisi_item_sama_lalu_bin_kosong(): void
    {
        $this->assertSame((int) $this->binA->id, (int) $this->putDari()->lines->first()->suggested_bin_id, 'Tanpa isi: bin kosong pertama.');

        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 3, toBinId: $this->binB->id));

        $saran = app(PutawaySuggester::class)->suggest($this->baut, $this->gudang, 5);
        $this->assertSame((int) $this->binB->id, (int) $saran?->id, 'Barang sejenis dikumpulkan.');
    }

    #[Test]
    public function tc_put_02_selesai_di_bin_saran_memindah_stok_tanpa_kejadian(): void
    {
        $put = $this->putDari(10);
        $kejadian = StockEvent::query()->count();

        $put = app(CompletePutaway::class)->handle($put, [], $this->makeUser());

        $this->assertSame(PutawayTaskStatus::Completed, $put->status);
        $this->assertSame(10.0, $this->saldo($this->binA, $this->baut));
        $this->assertSame(0.0, $this->saldo($this->binSistem($this->gudang, BinType::Receiving), $this->baut));
        $this->assertSame($kejadian, StockEvent::query()->count());
    }

    #[Test]
    public function tc_put_03_ganti_bin_saran_wajib_alasan(): void
    {
        $put = $this->putDari();
        $baris = $put->lines->first();

        $this->gagal(fn () => app(CompletePutaway::class)->handle($put, [$baris->id => ['bin_id' => $this->binB->id]], $this->makeUser()), 'BR-GRN-03');

        app(CompletePutaway::class)->handle($put, [$baris->id => ['bin_id' => $this->binB->id, 'override_reason' => 'Rak A penuh']], $this->makeUser());

        $this->assertSame(10.0, $this->saldo($this->binB, $this->baut));
        $this->assertSame('Rak A penuh', $baris->refresh()->override_reason);
    }

    #[Test]
    public function tc_put_04_bin_tujuan_harus_bin_penyimpanan_gudang_ini(): void
    {
        $put = $this->putDari();
        $baris = $put->lines->first();
        $karantina = $this->binSistem($this->gudang, BinType::Quarantine);

        $this->gagal(fn () => app(CompletePutaway::class)->handle($put, [$baris->id => ['bin_id' => $karantina->id, 'override_reason' => 'x']], $this->makeUser()), 'BR-GRN-03');

        $lain = $this->buatGudang('BKS', 'Gudang Bekasi');
        $binLain = Bin::create(['warehouse_id' => $lain->id, 'code' => 'BKS-A-R01-L1-B01', 'bin_type' => BinType::Storage]);

        $this->gagal(fn () => app(CompletePutaway::class)->handle($put, [$baris->id => ['bin_id' => $binLain->id, 'override_reason' => 'x']], $this->makeUser()), 'BR-GRN-03');
    }

    #[Test]
    public function tc_put_05_kapasitas_blokir_menolak_dan_peringatan_dilaporkan(): void
    {
        $blokir = StorageCategory::query()->where('code', 'B3')->firstOrFail();
        $sempit = Bin::create([
            'warehouse_id' => $this->gudang->id, 'code' => 'CKG-B-R01-L1-B01', 'bin_type' => BinType::Storage,
            'storage_category_id' => $blokir->id, 'capacity_qty' => 5,
        ]);

        $put = $this->putDari(10);
        $baris = $put->lines->first();

        $this->gagal(fn () => app(CompletePutaway::class)->handle($put, [$baris->id => ['bin_id' => $sempit->id, 'override_reason' => 'coba']], $this->makeUser()), 'BR-WH-06');

        $this->binA->forceFill(['capacity_qty' => 5])->save();
        $aksi = app(CompletePutaway::class);
        $aksi->handle($put->refresh(), [$baris->id => ['bin_id' => $this->binA->id, 'override_reason' => 'tetap']], $this->makeUser());

        $this->assertNotEmpty($aksi->warnings(), 'Mode peringatan: tetap ditaruh, tetapi diperingatkan.');
        $this->assertSame(10.0, $this->saldo($this->binA, $this->baut));
    }

    #[Test]
    public function tc_put_06_batal_dengan_alasan_lalu_dibuat_ulang(): void
    {
        $put = $this->putDari();

        $this->gagal(fn () => app(CancelPutaway::class)->handle($put, null, null, $this->makeUser('warehouse_head')), 'BR-GEN-11');

        $put = app(CancelPutaway::class)->handle($put, $this->alasan(ReasonContext::Cancel), null, $this->makeUser('warehouse_head'));
        $this->assertSame(PutawayTaskStatus::Cancelled, $put->status);
        $this->assertSame(10.0, $this->saldo($this->binSistem($this->gudang, BinType::Receiving), $this->baut));

        $grn = GoodsReceipt::query()->findOrFail($put->goods_receipt_id);
        $baru = app(CompleteGoodsReceipt::class)->replan($grn, $this->makeUser());
        $this->assertSame(PutawayTaskStatus::Pending, $baru->status);

        $this->gagal(fn () => app(CompleteGoodsReceipt::class)->replan($grn, $this->makeUser()), 'BR-GRN-03');
    }

    #[Test]
    public function tc_put_07_saran_melewati_bin_yang_akan_melampaui_kapasitas(): void
    {
        $this->binA->forceFill(['capacity_qty' => 4])->save();

        $saran = app(PutawaySuggester::class)->suggest($this->baut, $this->gudang, 10);
        $this->assertSame((int) $this->binB->id, (int) $saran?->id);

        $this->binB->forceFill(['capacity_qty' => 4])->save();
        $this->assertNull(app(PutawaySuggester::class)->suggest($this->baut, $this->gudang, 10));
    }
}
