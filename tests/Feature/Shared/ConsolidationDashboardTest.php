<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Count\Actions\ReconcileStockCount;
use App\Domain\Shared\Livewire\ConsolidationDashboard;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-DSH-01 s.d. TC-DSH-03 — dashboard Ringkasan opname & waste (A-434):
 * akurasi dari sesi yang direkonsiliasi, keadaan kosong waste, akses.
 */
class ConsolidationDashboardTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    #[Test]
    public function tc_dsh_01_akurasi_dari_sesi_direkonsiliasi(): void
    {
        $sesi = $this->sesiBerjalan();
        $this->hitungPutaran($sesi, 1);
        app(ReconcileStockCount::class)->handle($sesi->refresh(), $this->kepala);

        Livewire::actingAs($this->auditor)->test(ConsolidationDashboard::class)
            ->assertSee('Akurasi stok per gudang (%)')
            ->assertSee('CKG')
            ->assertSee('100,0')
            ->assertSee('Belum ada waste pada periode ini.');
    }

    #[Test]
    public function tc_dsh_02_tanpa_data_menampilkan_keadaan_kosong(): void
    {
        Livewire::actingAs($this->auditor)->test(ConsolidationDashboard::class)
            ->assertSee('Belum ada sesi opname yang direkonsiliasi pada periode ini.')
            ->set('bulan', '12')->assertSet('bulan', '12')->assertSee('Belum ada akar masalah tercatat pada periode ini.');
    }

    #[Test]
    public function tc_dsh_03_akses_dan_menu(): void
    {
        $this->actingAs($this->makeUser('internal_requester'))->get($this->tenantUrl('dashboards/opname-waste'))->assertForbidden();
        $this->actingAs($this->kepala)->get($this->tenantUrl('dashboards/opname-waste'))->assertOk()->assertSee('Ringkasan opname &amp; waste', false);
    }
}
