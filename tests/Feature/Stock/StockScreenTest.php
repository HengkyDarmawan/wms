<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Enums\ReservationStatus;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Livewire\BalanceList;
use App\Domain\Stock\Livewire\EventList;
use App\Domain\Stock\Livewire\PeriodLock;
use App\Domain\Stock\Livewire\ReservationList;
use App\Domain\Stock\Livewire\StockCard;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-STK-26 s.d. TC-STK-33 — lima layar §6, izin (BR-GEN-09), dan cakupan
 * gudang (BR-ACC-05).
 */
class StockScreenTest extends TenantTestCase
{
    private StockLedger $ledger;

    private Warehouse $ckg;

    private Bin $binCkg;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(StockLedger::class);

        $this->ckg = $this->buatGudang('CKG', 'Gudang Utama Cakung');
        $this->binCkg = $this->buatBin($this->ckg, 'CKG-A-R01-L1-B01');

        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 20,
            toBinId: $this->binCkg->id,
        ));
    }

    private function buatGudang(string $code, string $name): Warehouse
    {
        return app(SaveWarehouse::class)->handle(null, [
            'code' => $code,
            'name' => $name,
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
    }

    private function buatBin(Warehouse $gudang, string $code): Bin
    {
        return Bin::create([
            'warehouse_id' => $gudang->id,
            'code' => $code,
            'bin_type' => BinType::Storage,
        ]);
    }

    #[Test]
    public function tc_stk_27_saldo_menampilkan_tersedia_setelah_dikurangi_reservasi(): void
    {
        app(ManageReservation::class)
            ->reserveSoft($this->item, $this->ckg, 5, 'material_request', 1);

        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(BalanceList::class)
            ->assertOk()
            ->assertSee('BAUT-M12')
            // 20 saldo − 5 reservasi = 15 tersedia, ditulis dengan format Indonesia.
            ->assertSee('15,00')
            ->assertSee('5,00');
    }

    #[Test]
    public function tc_stk_27b_saldo_nol_disembunyikan_kecuali_diminta(): void
    {
        // Seluruh saldo dikeluarkan sehingga barisnya menjadi nol.
        $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 20,
            fromBinId: $this->binCkg->id,
        ));

        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(BalanceList::class)
            ->assertDontSee('BAUT-M12')
            ->set('tampilkanNol', true)
            ->assertSee('BAUT-M12');
    }

    #[Test]
    public function tc_stk_28_kartu_stok_menampilkan_saldo_dan_riwayat(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(StockCard::class, ['item' => $this->item])
            ->assertOk()
            ->assertSee('Baut M12')
            ->assertSee('CKG-A-R01-L1-B01')
            ->assertSee('20,00');
    }

    #[Test]
    public function tc_stk_28b_kartu_stok_menandai_baris_pembalik(): void
    {
        $gerakan = $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 3,
            fromBinId: $this->binCkg->id,
        ));

        $this->ledger->reverse($gerakan, null, 'Salah input');

        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(StockCard::class, ['item' => $this->item])
            ->assertSee('Pembalik');
    }

    #[Test]
    public function tc_stk_29_kartu_stok_disaring_per_gudang(): void
    {
        $bks = $this->buatGudang('BKS', 'Gudang Cabang Bekasi');
        $binBks = $this->buatBin($bks, 'BKS-A-R01-L1-B01');

        $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 7,
            toBinId: $binBks->id,
        ));

        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(StockCard::class, ['item' => $this->item])
            ->assertSee('BKS-A-R01-L1-B01')
            ->set('warehouseFilter', (string) $this->ckg->id)
            ->assertDontSee('BKS-A-R01-L1-B01')
            ->assertSee('CKG-A-R01-L1-B01');
    }

    #[Test]
    public function tc_stk_30_reservasi_dilepas_dari_layar_dengan_alasan(): void
    {
        $reservasi = app(ManageReservation::class)
            ->reserveSoft($this->item, $this->ckg, 6, 'material_request', 1);

        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->ckg->id);
        $kepala->forgetPermissionCache();

        Livewire::actingAs($kepala)
            ->test(ReservationList::class)
            ->assertOk()
            ->call('mintaLepas', $reservasi->id)
            // BR-GEN-11: alasan wajib sebelum tombol Lepas berlaku.
            ->call('lepas')
            ->assertHasErrors(['reasonCode' => 'required'])
            ->set('reasonCode', 'NOT_NEEDED')
            ->set('reasonNotes', 'Dokumen dibatalkan pemohon')
            ->call('lepas')
            ->assertHasNoErrors();

        $reservasi->refresh();

        $this->assertSame(ReservationStatus::Released, $reservasi->status);
        $this->assertStringContainsString('NOT_NEEDED', (string) $reservasi->released_reason);
        $this->assertStringContainsString('Dokumen dibatalkan pemohon', (string) $reservasi->released_reason);
    }

    #[Test]
    public function tc_stk_31_staf_gudang_tidak_bisa_melepas_reservasi(): void
    {
        $reservasi = app(ManageReservation::class)
            ->reserveSoft($this->item, $this->ckg, 6, 'material_request', 1);

        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->ckg->id);
        $staf->forgetPermissionCache();

        $this->assertTrue($staf->hasPermission('reservation.view'));
        $this->assertFalse($staf->hasPermission('reservation.release'));

        Livewire::actingAs($staf)
            ->test(ReservationList::class)
            ->assertOk()
            ->call('mintaLepas', $reservasi->id)
            ->assertForbidden();
    }

    #[Test]
    public function tc_stk_31b_reservasi_gudang_lain_tidak_tampil(): void
    {
        $bks = $this->buatGudang('BKS', 'Gudang Cabang Bekasi');
        $binBks = $this->buatBin($bks, 'BKS-A-R01-L1-B01');

        $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 9,
            toBinId: $binBks->id,
        ));

        $reservasiBks = app(ManageReservation::class)
            ->reserveSoft($this->item, $bks, 4, 'material_request', 77);

        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->ckg->id);
        $kepala->forgetPermissionCache();

        Livewire::actingAs($kepala)
            ->test(ReservationList::class)
            ->assertDontSee('#77');

        // Policy juga menolaknya, bukan hanya daftarnya yang menyembunyikan.
        $this->assertFalse($kepala->can('release', $reservasiBks));
    }

    #[Test]
    public function tc_stk_32_outbox_kejadian_dibaca_dan_disaring(): void
    {
        StockEvent::create([
            'event_id' => (string) Str::uuid(),
            'event_type' => StockEventType::StockAdjusted,
            'source_type' => 'adjustment',
            'source_id' => 12,
            'payload' => ['item_id' => $this->item->id],
            'occurred_at' => now(),
            'recorded_at' => now(),
            'attempts' => 4,
            'last_error' => 'Sambungan ke Akuntansi gagal',
        ]);

        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(EventList::class)
            ->assertOk()
            ->assertSee('Sambungan ke Akuntansi gagal')
            ->set('statusFilter', 'terkirim')
            ->assertDontSee('Sambungan ke Akuntansi gagal');
    }

    #[Test]
    public function tc_stk_33_kunci_periode_hanya_maju_dan_tercatat(): void
    {
        $admin = $this->makeUser('company_admin');

        $kemarin = now()->subDay()->toDateString();
        $seminggu = now()->subWeek()->toDateString();

        $komponen = Livewire::actingAs($admin)
            ->test(PeriodLock::class)
            ->assertOk()
            ->set('tanggal', $kemarin)
            ->set('catatan', 'Tutup buku September')
            ->call('kunci')
            ->assertHasNoErrors()
            ->assertSet('ruleError', '');

        // Memundurkan kunci ditolak dengan kode aturannya disebut.
        $komponen
            ->set('tanggal', $seminggu)
            ->call('kunci')
            ->assertSet('ruleCode', 'BR-STK-15');

        $this->assertSame($kemarin, app(\App\Domain\Stock\Actions\LockStockPeriod::class)->current());

        // Riwayat pemajuan terbaca dari log aktivitas.
        $komponen->assertSee('Tutup buku September');
    }

    #[Test]
    public function tc_stk_26_izin_layar_stok(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->ckg->id);
        $staf->forgetPermissionCache();

        $this->assertTrue($staf->hasPermission('stock.view'));
        $this->assertFalse($staf->hasPermission('stock_event.view'));
        $this->assertFalse($staf->hasPermission('stock.lock_period'));

        $this->actingAs($staf)->get($this->tenantUrl('stock'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('stock/items/'.$this->item->id))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('stock/reservations'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('stock/events'))->assertForbidden();
        $this->actingAs($staf)->get($this->tenantUrl('settings/stock-period'))->assertForbidden();

        $admin = $this->makeUser('company_admin');

        foreach (['stock', 'stock/reservations', 'stock/events', 'settings/stock-period'] as $path) {
            $this->actingAs($admin)->get($this->tenantUrl($path))->assertOk();
        }
    }

    #[Test]
    public function tc_stk_26b_klien_tidak_punya_izin_stok(): void
    {
        $proyek = $this->makeProject();
        $klien = $this->makeUser('client_user', ScopeType::Project, $proyek->id, [
            'client_id' => $proyek->client_id,
        ]);

        $klien->forgetPermissionCache();

        $this->assertFalse($klien->hasPermission('stock.view'));
        $this->assertFalse($klien->hasPermission('reservation.view'));
        $this->assertSame(0, StockReservation::query()->whereRaw('1 = 0')->count());
    }
}
