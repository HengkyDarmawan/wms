<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Models\StockReservation;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-RPT-06 — sebelas laporan §9 modul Stock, Request, dan Picking/Shipment
 * (A-232): terdaftar, terbuka & terekspor oleh admin, dan isinya membaca data
 * yang benar (kartu stok, titik pesan ulang, reservasi menggantung, kinerja).
 */
class ExtendedReportTest extends TenantTestCase
{
    use ReturnFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    #[Test]
    public function tc_rpt_06_laporan_stock_request_shipment_terdaftar_dan_berisi(): void
    {
        $registry = app(ReportRegistry::class);
        $admin = $this->makeUser('company_admin');
        $admin->forgetPermissionCache();

        $kunci = ['kartu-stok', 'reservasi-menggantung', 'titik-pesan-ulang', 'daftar-req', 'req-menunggu-tinjau', 'baris-tanpa-sumber',
            'penggantian-menunggu', 'daftar-pengiriman', 'short-pick', 'posisi-rusak-selisih', 'kinerja-pengiriman'];

        foreach ($kunci as $k) {
            $laporan = $registry->find($k);
            $this->actingAs($admin)->get($this->tenantUrl('reports/'.$k))->assertOk()->assertSee($laporan->title());
            $this->actingAs($admin)->get($this->tenantUrl('reports/'.$k.'/export'))->assertOk();
            $this->assertNotEmpty($laporan->columns(), $k);
        }

        $this->actingAs($admin);

        // Kartu stok: stok awal fixture (100 BAUT-M12 ke bin A) tampil bulan ini.
        $kartu = $registry->find('kartu-stok')->rows(['item' => 'BAUT']);
        $this->assertTrue($kartu->contains(fn ($r) => $r['item'] === 'BAUT-M12' && $r['jumlah'] == 100.0 && $r['tujuan'] === $this->binA->code), 'Mutasi awal tampil di kartu stok.');
        $this->assertTrue($registry->find('kartu-stok')->rows(['item' => 'TIDAK-ADA'])->isEmpty());

        // Titik pesan ulang: 100 tersedia < 250 → selisih 150; item tanpa titik tidak tampil.
        $this->baut->forceFill(['reorder_point' => 250])->save();
        $rop = $registry->find('titik-pesan-ulang')->rows([]);
        $baris = $rop->firstWhere('item', 'BAUT-M12');
        $this->assertNotNull($baris);
        $this->assertSame(150.0, $baris['selisih']);
        $this->assertNull($rop->firstWhere('item', 'SEMEN-50'));

        // Reservasi menggantung: reservasi lunak berumur 10 hari tampil; yang baru tidak.
        StockReservation::create([
            'item_id' => $this->baut->id, 'warehouse_id' => $this->gudang->id, 'bin_id' => $this->binA->id, 'qty_base' => 3,
            'level' => ReservationLevel::Soft, 'document_type' => 'material_request', 'document_id' => 999999,
            'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10),
        ]);
        StockReservation::create([
            'item_id' => $this->baut->id, 'warehouse_id' => $this->gudang->id, 'bin_id' => $this->binA->id, 'qty_base' => 2,
            'level' => ReservationLevel::Soft, 'document_type' => 'material_request', 'document_id' => 999998,
        ]);
        $gantung = $registry->find('reservasi-menggantung')->rows(['min_age_days' => 7]);
        $this->assertCount(1, $gantung);
        $this->assertSame(10, $gantung->first()['umur']);
        $this->assertSame('material_request#999999', $gantung->first()['dokumen']);

        // Tanpa data: laporan lain tetap kosong tanpa galat.
        foreach (['daftar-req', 'req-menunggu-tinjau', 'baris-tanpa-sumber', 'penggantian-menunggu', 'daftar-pengiriman', 'short-pick', 'posisi-rusak-selisih', 'kinerja-pengiriman'] as $k) {
            $this->assertTrue($registry->find($k)->rows([])->isEmpty(), $k.' kosong.');
        }

        // Kinerja & daftar pengiriman: satu SJ transfer berangkat dan diterima utuh.
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 5]]);
        $pck = $this->jalankanPck($this->pckTrf($trf));
        $sj = $this->terimaSj($this->sjDari($pck, $this->bks));

        $kinerja = $registry->find('kinerja-pengiriman')->rows([]);
        $this->assertSame(1, $kinerja->first()['dikirim']);
        $this->assertSame(1, $kinerja->first()['utuh']);
        $daftar = $registry->find('daftar-pengiriman')->rows(['status' => 'delivered']);
        $this->assertSame($sj->number, $daftar->first()['nomor']);
        $this->assertTrue($registry->find('daftar-pengiriman')->rows(['status' => 'cancelled'])->isEmpty());

        // Izin: driver (stock.view, pick.view) membuka kartu stok & short pick; pemohon internal tanpa pick.view ditolak.
        $driver = $this->makeUser('driver');
        $driver->forgetPermissionCache();
        $this->actingAs($driver)->get($this->tenantUrl('reports/kartu-stok'))->assertOk();
        $this->actingAs($driver)->get($this->tenantUrl('reports/short-pick'))->assertOk();
        $pemohon = $this->makeUser('internal_requester');
        $pemohon->forgetPermissionCache();
        $this->actingAs($pemohon)->get($this->tenantUrl('reports/short-pick'))->assertForbidden();
    }
}
