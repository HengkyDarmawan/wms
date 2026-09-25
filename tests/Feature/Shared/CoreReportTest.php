<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-RPT-02 s.d. TC-RPT-05 — laporan inti Blueprint §6.9a yang ditambahkan di
 * Pendukung F1: saldo stok, mutasi periode, permintaan terbuka, konversi &
 * waste, akurasi stok, dan ekspor PDF (A-190).
 */
class CoreReportTest extends TenantTestCase
{
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    private function laporan(string $kunci)
    {
        return app(ReportRegistry::class)->find($kunci);
    }

    #[Test]
    public function tc_rpt_02_saldo_stok_dan_mutasi_periode(): void
    {
        $this->actingAs($this->makeUser('company_admin'));

        $baut = $this->laporan('saldo-stok')->rows(['item' => $this->baut->code]);
        $this->assertSame([100.0], $baut->pluck('jumlah')->all(), 'Saldo awal baut fixture 100 di binA.');
        $this->assertSame(1, $this->laporan('saldo-stok')->rows(['item' => $this->pipa->code])->first()['potong']);

        // Hari ini: masuk 100 (fixture), keluar 30, pindah antar bin 5 (bukan masuk/keluar).
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 30, fromBinId: $this->binA->id));
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 5, fromBinId: $this->binA->id, toBinId: $this->binB->id));

        $angka = fn (array $f) => collect($this->laporan('mutasi-periode')->rows($f)->firstWhere('kode_item', $this->baut->code))
            ->only(['awal', 'masuk', 'keluar', 'akhir'])->all();
        $tz = 'Asia/Jakarta';

        $this->assertSame(['awal' => 0.0, 'masuk' => 100.0, 'keluar' => 30.0, 'akhir' => 70.0],
            $angka(['date_from' => now($tz)->toDateString(), 'date_to' => now($tz)->toDateString()]),
            'Pindah antar bin di gudang yang sama bukan masuk/keluar.');

        // Periode sesudahnya: semuanya sudah menjadi saldo awal.
        $this->assertSame(['awal' => 70.0, 'masuk' => 0.0, 'keluar' => 0.0, 'akhir' => 70.0],
            $angka(['date_from' => now($tz)->addDay()->toDateString(), 'date_to' => now($tz)->addDays(2)->toDateString()]));

        // Cakupan: staf gudang lain tidak melihat saldo CKG.
        $this->actingAs($this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->bks->id));
        $this->assertSame([], $this->laporan('saldo-stok')->rows(['item' => $this->baut->code])->all());
    }

    #[Test]
    public function tc_rpt_03_konversi_waste_dan_permintaan_terbuka(): void
    {
        $this->actingAs($this->makeUser('company_admin'));
        $this->selesai($this->cnvPotong());

        $baris = $this->laporan('konversi-waste')->rows([])->sole();
        $this->assertSame(6.0, $baris['input']);
        $this->assertSame(5.0, $baris['output']);
        $this->assertSame(0.17, $baris['persen_waste'], '(waste 0 + kerf 0,01) / 6.');

        $this->assertIsIterable($this->laporan('permintaan-terbuka')->rows(['only_late' => '1']));
    }

    #[Test]
    public function tc_rpt_04_akurasi_stok_kosong_tanpa_opname(): void
    {
        $this->actingAs($this->makeUser('company_admin'));
        $this->assertSame([], $this->laporan('akurasi-stok')->rows([])->all());
        $this->assertSame(StockStatus::Available->label(), $this->laporan('saldo-stok')->rows(['item' => $this->baut->code])->first()['kondisi']);
    }

    #[Test]
    public function tc_rpt_05_ekspor_pdf_semua_laporan_mengikuti_izin(): void
    {
        $admin = $this->makeUser('company_admin');

        foreach (app(ReportRegistry::class)->all() as $laporan) {
            $this->actingAs($admin)->get($this->tenantUrl('reports/'.$laporan->key().'/pdf'))
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }

        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('reports/konversi-waste/pdf'))->assertForbidden();
        $this->actingAs($admin)->get($this->tenantUrl('reports/saldo-stok'))->assertOk()->assertSee(__('Ekspor PDF'));
    }
}
