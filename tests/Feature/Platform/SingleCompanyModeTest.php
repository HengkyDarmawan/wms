<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PLT-30 — mode satu company (A-417): `WMS_SINGLE_COMPANY` diset SEBELUM
 * aplikasi dibuat karena rute pusat didaftarkan saat boot. Host apa pun membuka
 * company uji; landing pusat tidak ada; Super Admin tetap di /admin dengan
 * cookie sesi sendiri; URL company mengikuti APP_URL.
 */
class SingleCompanyModeTest extends TenantTestCase
{
    private const ENV = ['WMS_SINGLE_COMPANY' => self::SUBDOMAIN, 'APP_URL' => 'http://localhost:8000'];

    /** @var array<string, string|false> */
    private array $lama = [];

    public function createApplication(): Application
    {
        foreach (self::ENV as $kunci => $nilai) {
            $this->lama[$kunci] = getenv($kunci);
            putenv($kunci.'='.$nilai);
            $_ENV[$kunci] = $_SERVER[$kunci] = $nilai;
        }

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->lama as $kunci => $nilai) {
            $nilai === false ? putenv($kunci) : putenv($kunci.'='.$nilai);
            unset($_ENV[$kunci], $_SERVER[$kunci]);
        }
    }

    #[Test]
    public function tc_plt_30_satu_alamat_tanpa_subdomain(): void
    {
        // Super Admin tetap di /admin dengan cookie sesi berbeda (tidak saling menimpa).
        // Diuji lebih dulu: dalam satu proses uji, driver sesi disimpan antar-request.
        $admin = $this->get('http://localhost:8000/admin/login')->assertOk();
        $this->assertTrue(collect($admin->headers->getCookies())->contains(fn ($c) => str_ends_with($c->getName(), '-platform')),
            collect($admin->headers->getCookies())->map(fn ($c) => $c->getName())->implode(','));

        // Host apa pun (tanpa subdomain) membuka company uji.
        $this->get('http://localhost:8000/login')->assertOk()->assertSee($this->company->name);
        $this->get('http://wms-demo.example.com/login')->assertOk();

        // Tamu di "/" → halaman depan dengan tombol Masuk; yang sudah login → Beranda.
        $this->get('http://localhost:8000/')->assertRedirect(route('welcome'));
        $this->get('http://localhost:8000/selamat-datang')->assertOk()->assertSeeHtml('data-tombol-masuk');
        $this->actingAs($this->makeUser('company_admin'))->get('http://localhost:8000/')->assertOk()->assertDontSee('Masuk ke company Anda');

        // URL company (undangan, QR bin, email) mengikuti APP_URL.
        $this->assertSame('http://localhost:8000/x', $this->company->url('/x'));
        $this->assertStringStartsWith('http://localhost:8000/bins/', BinCode::tautan('CKG-A-R01-L1-B01'));
    }
}
