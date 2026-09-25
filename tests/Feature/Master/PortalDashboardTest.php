<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Access\Enums\ScopeType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-MST-26 — beranda portal klien (BR-PRJ-07): kartu angka, daftar proyek
 * klien, dan Stok On-site per proyek (BR-PRJ-05) dari sumber yang sama dengan
 * form retur; klien lain tidak melihat proyek/stok klien ini.
 */
class PortalDashboardTest extends TenantTestCase
{
    use IssueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPemakaian();
    }

    #[Test]
    public function tc_mst_26_beranda_portal_menampilkan_proyek_angka_dan_stok_on_site(): void
    {
        $req = $this->kirimKeSite($this->baut, 40);
        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->proyek->client_id]);

        $this->actingAs($klien)->get($this->tenantUrl('portal'))->assertOk()
            ->assertSee($this->proyek->code)
            ->assertSee(__('Proyek aktif'))
            ->assertSee(__('Di Gudang Site'))
            ->assertSee($this->baut->code)
            ->assertSee(route('portal.requests.index'))
            ->assertSee(route('portal.returns.index'))
            ->assertDontSee(__('setelah modul Master dan Request dibangun'));

        // Klien lain: proyek & stok klien ini tidak tampil.
        $lain = $this->makeProject();
        $klienLain = $this->makeUser('client_user', ScopeType::Project, $lain->id, ['client_id' => $lain->client_id]);
        $this->actingAs($klienLain)->get($this->tenantUrl('portal'))->assertOk()
            ->assertSee($lain->code)
            ->assertDontSee($this->proyek->code)
            ->assertDontSee($this->baut->code);

        $this->assertNotNull($req->id);
    }
}
