<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Platform\Models\Plan;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-LND — landing page produk di domain pusat (30-landing-page §10).
 */
class LandingPageTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Uji layar Livewire sebelumnya dalam proses PHPUnit yang sama
        // meninggalkan penanda "komponen sudah dirender", sehingga Livewire
        // menyuntikkan skripnya ke halaman berikutnya. Di produksi setiap
        // permintaan dimulai bersih.
        SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest = false;
        SupportAutoInjectedAssets::$forceAssetInjection = false;
    }

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_domains.0').'/'.ltrim($path, '/');
    }

    #[Test]
    public function tc_lnd_01_landing_tampil_dengan_bagian_utama(): void
    {
        $this->get($this->centralUrl('/'))
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Stok yang bisa dipercaya,')
            ->assertSee('Dibuat untuk material yang dipotong, dipinjam, dan dipakai di site')
            ->assertSee('Setiap potongan punya asal-usul')
            ->assertSee('Dari permintaan sampai terpakai di site')
            ->assertSee('Satu sistem, setiap orang melihat bagiannya')
            ->assertSee('Langganan bulanan flat per company')
            ->assertSee('Masuk ke company Anda')
            ->assertSee('Pertanyaan yang sering muncul')
            ->assertSee('Apakah ada harga barang di WMS?')
            ->assertSee('mailto:'.config('wms.sales_email'), false);
    }

    #[Test]
    public function tc_lnd_02_paket_aktif_dari_database_pusat(): void
    {
        Plan::create([
            'code' => 'landing-uji',
            'name' => 'Paket Landing Uji',
            'monthly_price' => 1500000,
            'trial_days' => 30,
            'is_active' => true,
        ]);
        Plan::create([
            'code' => 'landing-mati',
            'name' => 'Paket Nonaktif Uji',
            'monthly_price' => 999000,
            'is_active' => false,
        ]);

        $this->get($this->centralUrl('/'))
            ->assertOk()
            // Paket harga 0 (paket uji bawaan) → "Hubungi kami" (O-08, A-222).
            ->assertSee('Paket Uji')
            ->assertSee('Hubungi kami')
            ->assertSee('Paket Landing Uji')
            ->assertSee('Rp 1.500.000')
            ->assertSee('Trial 30 hari')
            ->assertDontSee('Paket Nonaktif Uji')
            ->assertDontSee('999.000');
    }

    #[Test]
    public function tc_lnd_03_tanpa_pwa_livewire_dan_tautan_super_admin(): void
    {
        $this->get($this->centralUrl('/'))
            ->assertOk()
            ->assertDontSee('manifest.webmanifest', false)
            ->assertDontSee('serviceWorker', false)
            ->assertDontSee('sw.js', false)
            ->assertDontSee('livewire', false)
            ->assertDontSee('wire:', false)
            ->assertDontSee('/admin/login', false);
    }

    #[Test]
    public function tc_lnd_04_masuk_ke_company_mengalihkan_ke_subdomain(): void
    {
        $domain = config('tenancy.central_domains.0');

        $this->get($this->centralUrl('masuk?company=Demo&as=team'))
            ->assertRedirect('http://demo.'.$domain.'/login');

        $this->get($this->centralUrl('masuk?company=demo&as=portal'))
            ->assertRedirect('http://demo.'.$domain.'/portal/login');

        // Tidak membocorkan daftar company: subdomain yang belum ada tetap dialihkan (A-221).
        $this->get($this->centralUrl('masuk?company=belum-ada-123'))
            ->assertRedirect('http://belum-ada-123.'.$domain.'/login');
    }

    #[Test]
    public function tc_lnd_05_alamat_company_tidak_sah_ditolak(): void
    {
        foreach (['', 'www', 'admin', 'nama_salah', '-awal', 'akhir-', 'a.b', 'evil.com/x'] as $salah) {
            $respons = $this->get($this->centralUrl('masuk?company='.urlencode($salah)));

            $respons->assertRedirect()->assertSessionHasErrors('company');
            $this->assertStringEndsWith('#masuk', (string) $respons->headers->get('Location'), 'Masukan: '.$salah);
        }

        $this->get($this->centralUrl('masuk?company=demo&as=admin'))
            ->assertRedirect()
            ->assertSessionHasErrors('as');
    }

    #[Test]
    public function tc_lnd_06_masuk_hanya_get_tanpa_mengubah_data(): void
    {
        $route = app('router')->getRoutes()->getByName('central.enter');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }
}
