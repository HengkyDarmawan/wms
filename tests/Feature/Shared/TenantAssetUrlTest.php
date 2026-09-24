<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-FIL-02 — aset aplikasi di halaman company dilayani dari public/, bukan storage company.
 *
 * Bila asset() ikut di-tenant-kan, build Vite dan logo menunjuk /tenancy/assets/…,
 * menghasilkan 404, dan seluruh halaman company tampil tanpa gaya.
 */
class TenantAssetUrlTest extends TenantTestCase
{
    #[Test]
    public function tc_fil_02_asset_tidak_menunjuk_storage_company(): void
    {
        $this->assertTrue(tenancy()->initialized);

        foreach (['build/assets/app.css', 'img/logo.svg'] as $path) {
            $this->assertStringNotContainsString('/tenancy/assets/', asset($path), $path);
        }
    }

    #[Test]
    public function tc_fil_02b_halaman_login_company_memuat_logo_global(): void
    {
        $response = $this->get($this->tenantUrl('/login'));

        $response->assertOk();
        $response->assertDontSee('/tenancy/assets/', false);
        $response->assertSee('/img/logo.svg', false);
    }
}
