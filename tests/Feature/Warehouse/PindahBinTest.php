<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Warehouse\Actions\MoveBinStock;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Livewire\BinMove;
use App\Domain\Warehouse\Models\Bin;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-74–75 — Pindah bin (A-416): stok pindah antar bin penyimpanan satu
 * gudang tanpa approval, lewat kartu stok; aturan penolakan; layar pindai.
 */
class PindahBinTest extends TenantTestCase
{
    use CountFixtures;

    private Bin $binC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
        // Bin tujuan di rak sungguhan (Tempat simpan butuh bin di rak, A-365): CKG-D-R05-L1-B01..B02.
        $zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        app(SaveWarehouseLayout::class)->newRack($zona, ['code' => 'R05', 'levels' => '1', 'bins_per_level' => '2']);
        $this->binC = Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R05-L1-B01')->firstOrFail();
    }

    private function saldoId(Bin $bin, int $itemId): int
    {
        return (int) StockBalance::query()->where('bin_id', $bin->id)->where('item_id', $itemId)->where('qty_base', '>', 0)->value('id');
    }

    /** @param  array<string, mixed>  $data */
    private function tolak(array $data, string $pesan): void
    {
        try {
            app(MoveBinStock::class)->handle($this->gudang, $data, $this->staf1);
            $this->fail('Seharusnya ditolak: '.$pesan);
        } catch (WarehouseRuleException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        }
    }

    #[Test]
    public function tc_wh_74_pindah_bin_lewat_kartu_stok_dan_aturannya(): void
    {
        $aksi = app(MoveBinStock::class);

        // Staf gudang memindah 30 dari 100 baut: tanpa approval, satu pergerakan bin_move.
        $gerak = $aksi->handle($this->gudang, ['balance_id' => $this->saldoId($this->binA, $this->baut->id), 'to_bin_id' => $this->binC->id, 'qty' => '30', 'notes' => 'rapikan rak'], $this->staf1);
        $this->assertSame([70.0, 30.0], [$this->saldoBin($this->binA, $this->baut), $this->saldoBin($this->binC, $this->baut)]);
        $this->assertSame('bin_move', $gerak->document_type);
        $this->assertSame('Pindah bin: rapikan rak', $gerak->notes);
        $this->assertSame(1, StockMovement::query()->where('document_type', 'bin_move')->count());
        $this->assertTrue(Activity::query()->where('description', 'like', 'Pindah bin%')->exists());

        // Serial & potongan selalu pindah utuh walau jumlah diisi lain.
        $aksi->handle($this->gudang, ['balance_id' => $this->saldoId($this->binB, $this->genset->id), 'to_bin_id' => $this->binC->id, 'qty' => '5'], $this->staf1);
        $this->assertSame(1.0, $this->saldoBin($this->binC, $this->genset));

        // Penolakan.
        $baut = $this->saldoId($this->binA, $this->baut->id);
        $this->tolak(['balance_id' => $baut, 'to_bin_id' => $this->binA->id, 'qty' => '1'], 'harus berbeda');
        $this->tolak(['balance_id' => $baut, 'to_bin_id' => $this->binC->id, 'qty' => '71'], 'Melebihi isi bin');
        $this->tolak(['balance_id' => $baut, 'to_bin_id' => $this->binC->id, 'qty' => '0'], 'lebih dari 0');

        $penerimaan = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-RCV-X', 'bin_type' => BinType::Receiving]);
        $this->tolak(['balance_id' => $baut, 'to_bin_id' => $penerimaan->id, 'qty' => '1'], 'bukan bin penyimpanan');

        // Reservasi yang menunjuk bin asal tidak ikut dipindah.
        StockReservation::create(['item_id' => $this->baut->id, 'warehouse_id' => $this->gudang->id, 'bin_id' => $this->binA->id,
            'qty_base' => 60, 'level' => 'hard', 'status' => 'active', 'document_type' => 'pick_task', 'document_id' => 1]);
        $this->tolak(['balance_id' => $baut, 'to_bin_id' => $this->binC->id, 'qty' => '11'], 'Paling banyak 10');

        // Bin tujuan Khusus barang lain ditolak (BR-WH-10).
        app(SaveItemStorageLocations::class)->replace($this->semen, $this->gudang, [['tempat' => 'bin:'.$this->binC->id, 'khusus' => true]], $this->kepala);
        $this->tolak(['balance_id' => $baut, 'to_bin_id' => $this->binC->id, 'qty' => '1'], 'khusus');

        // Tanpa izin putaway.complete / di luar cakupan.
        $lain = $this->makeUser('warehouse_staff', ScopeType::Warehouse, app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS', 'name' => 'Bekasi', 'warehouse_type_id' => $this->gudang->warehouse_type_id,
        ])->id);
        $this->expectException(WarehouseRuleException::class);
        $aksi->handle($this->gudang, ['balance_id' => $baut, 'to_bin_id' => $this->binB->id, 'qty' => '1'], $lain);
    }

    #[Test]
    public function tc_wh_75_layar_pindah_bin_dengan_pindai(): void
    {
        $this->actingAs($this->staf1)->get($this->tenantUrl('bin-moves'))->assertOk()->assertSee(__('Pindah bin'))->assertSee(__('Tunjukkan caranya'));
        $this->actingAs($this->makeUser('internal_requester'))->get($this->tenantUrl('bin-moves'))->assertForbidden();

        $layar = Livewire::actingAs($this->staf1)->test(BinMove::class)
            ->assertSet('gudangId', (string) $this->gudang->id)
            // Kode pendek diketik (A-373) → bin asal; kode asing ditolak.
            ->set('pindaiAsal', 'tidak-ada')->call('pindai', 'asal')->assertHasErrors('pindaiAsal')
            ->set('pindaiAsal', 'r01 l1 01')->call('pindai', 'asal')->assertSet('asalId', (string) $this->binA->id)
            ->assertSee('BAUT-OPN')
            ->set('form.balance_id', (string) $this->saldoId($this->binA, $this->baut->id))->assertSet('form.qty', '100')
            ->set('form.qty', '25')
            ->set('pindaiTujuan', 'r05 l1 01')->call('pindai', 'tujuan')->assertSet('form.to_bin_id', (string) $this->binC->id)
            ->call('simpan')->assertHasNoErrors();

        $layar->assertSeeHtml('data-pindah-berhasil')->assertSeeHtml('data-riwayat-pindah')->assertSet('form.balance_id', '');
        $this->assertSame(25.0, $this->saldoBin($this->binC, $this->baut));

        // Kartu stok menulis "Pindah bin".
        $this->actingAs($this->staf1)->get($this->tenantUrl('stock/items/'.$this->baut->id))->assertOk()->assertSee(__('Pindah bin'));
    }
}
