<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Count\Enums\CountType;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Count\Support\AbcCyclePlanner;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-F2-06 s.d. TC-F2-09 — cycle count ABC (BR-OPN-11, A-424): kelas dari
 * frekuensi mutasi 90 hari, jatuh tempo per kelas, sesi satu gudang.
 */
class CycleCountAbcTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();

        // Kartu stok append-only (P-01): stok awal dibuat lalu waktu dimajukan 100 hari,
        // sehingga mutasi awal di luar jendela 90 hari. Mutasi baru: baut 15, semen 2,
        // genset & pipa 0.
        $this->travel(100)->days();
        for ($i = 0; $i < 15; $i++) {
            $this->masuk($this->baut, 1, $this->binA);
        }
        $this->masuk($this->semen, 1, $this->binB, lotId: $this->lot->id);
        $this->masuk($this->semen, 1, $this->binB, lotId: $this->lot->id);
    }

    /** @return array<int, string> item_id => kelas */
    private function kelas(): array
    {
        return array_column(app(AbcCyclePlanner::class)->klasifikasi((int) $this->gudang->id), 'kelas', 'item_id');
    }

    #[Test]
    public function tc_f2_06_kelas_dari_frekuensi_mutasi(): void
    {
        $k = $this->kelas();

        $this->assertSame('A', $k[$this->baut->id], 'Penyumbang mutasi terbesar = A.');
        $this->assertSame('B', $k[$this->semen->id]);
        $this->assertSame('C', $k[$this->genset->id], 'Tanpa mutasi dalam jendela = C.');
        $this->assertSame('C', $k[$this->pipa->id], 'Tanpa mutasi dalam jendela = C.');
    }

    #[Test]
    public function tc_f2_07_jatuh_tempo_menurut_jadwal_kelas(): void
    {
        $this->sudahDihitung($this->baut->id, $this->binA->id, 10);
        $this->sudahDihitung($this->pipa->id, $this->binA->id, 100);

        $tempo = array_column(app(AbcCyclePlanner::class)->klasifikasi((int) $this->gudang->id), 'jatuh_tempo', 'item_id');

        $this->assertFalse($tempo[$this->baut->id], 'A dihitung 10 hari lalu: belum jatuh tempo (30 hari).');
        $this->assertTrue($tempo[$this->semen->id], 'Belum pernah dihitung = jatuh tempo.');
        $this->assertFalse($tempo[$this->pipa->id], 'C dihitung 100 hari lalu: belum jatuh tempo (180 hari).');

        $urut = array_column(app(AbcCyclePlanner::class)->untukSesi((int) $this->gudang->id), 'item_id');
        $this->assertSame([$this->semen->id, $this->genset->id], $urut, 'Kelas B dulu, lalu C; yang belum jatuh tempo tidak ikut.');
    }

    #[Test]
    public function tc_f2_08_dua_gudang_atau_tanpa_jatuh_tempo_ditolak(): void
    {
        $bks = app(SaveWarehouse::class)->handle(null, ['code' => 'BKS', 'name' => 'Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id')]);

        $this->tolak(fn () => $this->sesi(['count_type' => 'cycle_abc', 'warehouse_ids' => [$this->gudang->id, $bks->id]], $this->auditor), 'BR-OPN-11');

        foreach ([$this->baut, $this->semen, $this->genset, $this->pipa] as $item) {
            $this->sudahDihitung($item->id, $this->binA->id, 1);
        }
        $this->tolak(fn () => $this->sesi(['count_type' => 'cycle_abc']), 'BR-OPN-11');
    }

    #[Test]
    public function tc_f2_09_sesi_berisi_item_dari_perencana(): void
    {
        $sesi = $this->sesi(['count_type' => 'cycle_abc', 'bin_ids' => [$this->binA->id], 'item_ids' => [$this->pipa->id]]);

        $this->assertSame(CountType::CycleAbc, $sesi->count_type);
        $this->assertSame(StockCountStatus::Planned, $sesi->status);
        $this->assertSame([], $sesi->scope['bin_ids'], 'Isian bin diabaikan.');
        $this->assertSame(
            array_column(app(AbcCyclePlanner::class)->untukSesi((int) $this->gudang->id), 'item_id'),
            $sesi->scope['item_ids'],
        );
        $this->assertContains($this->baut->id, $sesi->scope['item_ids'], 'Kelas A belum pernah dihitung ikut, paling depan.');
        $this->assertSame($this->baut->id, $sesi->scope['item_ids'][0]);
    }

    /** Sesi yang sudah disetujui `hariLalu` hari lalu memuat item ini di bin itu. */
    private function sudahDihitung(int $itemId, int $binId, int $hariLalu): void
    {
        $sesi = StockCount::create([
            'number' => 'OPN/UJI/'.uniqid(),
            'count_type' => CountType::Adhoc,
            'status' => StockCountStatus::Closed,
            'scope' => ['warehouse_ids' => [$this->gudang->id]],
            'approved_at' => now()->subDays($hariLalu),
        ]);
        CountLine::create(['stock_count_id' => $sesi->id, 'bin_id' => $binId, 'item_id' => $itemId, 'system_qty' => 1]);
    }

    private function tolak(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (CountRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }
}
