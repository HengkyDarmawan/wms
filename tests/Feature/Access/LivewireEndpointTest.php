<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Http\Middleware\EnsureClientPortal;
use App\Http\Middleware\EnsureInternalArea;
use App\Http\Middleware\EnsureSubscriptionState;
use App\Http\Middleware\InitializeTenancyBySubdomain;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-28 — endpoint update Livewire harus melewati middleware tenant.
 *
 * Livewire mendaftarkan `POST <prefix>/update` sendiri dengan grup `web` saja.
 * Bila dibiarkan, setiap aksi Livewire berjalan tanpa inisialisasi tenant
 * (query jatuh ke database pusat), tanpa gerbang langganan (BR-SUB-02,
 * BR-SUB-03), dan tanpa pemisahan area internal & portal (BR-PRJ-07).
 *
 * Seluruh uji Livewire lain memakai `Livewire::test()` yang melewati lapisan
 * HTTP, jadi lubang itu tidak akan pernah tertangkap di sana. Berkas ini
 * sengaja menembak route HTTP sungguhan.
 */
class LivewireEndpointTest extends TenantTestCase
{
    private function updateUrl(): string
    {
        return $this->tenantUrl(ltrim(app(HandleRequests::class)->getUpdateUri(), '/'));
    }

    #[Test]
    public function tc_acc_28_route_update_livewire_memakai_middleware_tenant(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_ends_with((string) $route->getName(), 'livewire.update'));

        $this->assertCount(1, $routes, 'Hanya boleh ada satu route update Livewire.');

        // gatherRouteMiddleware menguraikan grup `web` menjadi daftar kelasnya.
        $middleware = app('router')->gatherRouteMiddleware($routes->first());

        $this->assertContains(InitializeTenancyBySubdomain::class, $middleware);
        $this->assertContains(EnsureSubscriptionState::class, $middleware);
        $this->assertContains(StartSession::class, $middleware);

        // Tenancy harus lebih dulu dari sesi supaya sesi dibaca dari database
        // company, bukan pusat (BR-ACC-06).
        $this->assertLessThan(
            array_search(StartSession::class, $middleware, true),
            array_search(InitializeTenancyBySubdomain::class, $middleware, true),
            'Tenancy harus berjalan sebelum sesi.',
        );
    }

    #[Test]
    public function tc_acc_28b_route_bawaan_livewire_tanpa_middleware_tidak_ada(): void
    {
        $bawaan = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'default-livewire.update');

        $this->assertNull(
            $bawaan,
            'Route update bawaan Livewire masih terdaftar dan menjadi jalur pintas tanpa middleware tenant.',
        );
    }

    #[Test]
    public function tc_acc_28c_pemisah_area_didaftarkan_sebagai_middleware_persisten(): void
    {
        // Pemisah area internal & portal melekat pada halaman asal, bukan pada
        // endpoint update, jadi keduanya harus dijalankan ulang oleh Livewire.
        $persisten = Livewire::getPersistentMiddleware();

        $this->assertContains(EnsureInternalArea::class, $persisten);
        $this->assertContains(EnsureClientPortal::class, $persisten);
    }

    #[Test]
    public function tc_acc_28d_langganan_ditangguhkan_menolak_aksi_livewire(): void
    {
        $this->setSubscriptionStatus(SubscriptionStatus::Suspended);

        $response = $this->postJson($this->updateUrl(), ['components' => []], ['X-Livewire' => 'true']);

        // 403 membuktikan tenancy dan gerbang langganan keduanya berjalan:
        // gerbang langganan hanya bisa membaca status setelah tenant terpasang.
        $response->assertForbidden();
    }

    #[Test]
    public function tc_acc_28g_mode_hanya_baca_meloloskan_cari_dan_halaman_livewire(): void
    {
        // A-196: cari/filter/pindah halaman boleh, aksi komponen tetap ditolak.
        $this->setSubscriptionStatus(SubscriptionStatus::Suspended);
        $komponen = fn (string $metode) => ['components' => [[
            'snapshot' => '{}', 'updates' => ['search' => 'baut'], 'calls' => [['method' => $metode, 'params' => []]],
        ]]];

        $this->postJson($this->updateUrl(), $komponen('simpan'), ['X-Livewire' => 'true'])->assertForbidden();

        $baca = $this->postJson($this->updateUrl(), $komponen('gotoPage'), ['X-Livewire' => 'true']);
        $this->assertStringNotContainsString(__('Langganan ditangguhkan: perubahan data tidak diizinkan.'), (string) $baca->getContent());
        $this->assertNotSame(403, $baca->status(), 'Pindah halaman tidak boleh ditolak gerbang langganan.');
    }

    #[Test]
    public function tc_acc_28e_subdomain_tidak_dikenal_ditolak_di_endpoint_livewire(): void
    {
        $path = app(HandleRequests::class)->getUpdateUri();

        $response = $this->postJson(
            'http://tidak-ada.'.config('tenancy.central_domains.0').$path,
            ['components' => []],
            ['X-Livewire' => 'true'],
        );

        $response->assertNotFound();
        $this->assertStringContainsString('Company tidak dikenal', $response->getContent());
    }

    #[Test]
    public function tc_acc_28f_tenant_aktif_lolos_seluruh_middleware(): void
    {
        $this->setSubscriptionStatus(SubscriptionStatus::Active);

        $response = $this->postJson($this->updateUrl(), ['components' => []], ['X-Livewire' => 'true']);

        // Payload kosong ditolak Livewire sendiri, bukan oleh middleware:
        // penolakannya tidak berasal dari gerbang langganan maupun dari
        // identifikasi tenant.
        $this->assertNotSame(403, $response->status(), 'Tenant aktif tidak boleh ditolak gerbang langganan.');
        $this->assertStringNotContainsString('Company tidak dikenal', $response->getContent());
    }
}
