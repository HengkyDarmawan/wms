<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\GrantSupportAccess;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-21 — akses dukungan Super Admin berperiode (A-27, BR-SUB-04).
 */
class SupportAccessTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_21_akses_dukungan_hanya_berlaku_dalam_periodenya(): void
    {
        $admin = $this->makeUser('company_admin');
        $superAdmin = $this->platformUser();

        $this->assertFalse(
            $superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()),
            'Tanpa izin, Super Admin tidak boleh membuka data company.',
        );

        $akses = app(GrantSupportAccess::class)->handle(
            $this->company,
            $superAdmin->id,
            now()->subMinute(),
            now()->addDays(2),
            'Menelusuri laporan stok yang tidak cocok',
            $admin,
        );

        $this->assertTrue($superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()));
        $this->assertTrue($akses->isActive());

        // Setelah masa berlaku habis.
        $akses->forceFill(['starts_at' => now()->subDays(5), 'ends_at' => now()->subDay()])->save();
        $this->assertFalse($superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()));

        // Dicabut sebelum waktunya.
        $akses->forceFill(['starts_at' => now()->subMinute(), 'ends_at' => now()->addDay()])->save();
        $this->assertTrue($superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()));

        app(GrantSupportAccess::class)->revoke($akses, $admin);
        $this->assertFalse($superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()));
    }

    #[Test]
    public function tc_acc_21b_akses_dukungan_wajib_alasan_dan_dibatasi_lamanya(): void
    {
        $admin = $this->makeUser('company_admin');
        $superAdmin = $this->platformUser();
        $action = app(GrantSupportAccess::class);

        try {
            $action->handle($this->company, $superAdmin->id, now(), now()->addDay(), '  ', $admin);
            $this->fail('Alasan kosong seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        try {
            $action->handle(
                $this->company,
                $superAdmin->id,
                now(),
                now()->addDays((int) config('access.support_access.max_days') + 3),
                'Terlalu lama',
                $admin,
            );
            $this->fail('Periode melebihi batas seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-SUB-04', $e->rule);
        }
    }

    private function platformUser(): PlatformUser
    {
        return PlatformUser::create([
            'name' => 'Super Admin Uji',
            'email' => 'superadmin.uji@wms.test',
            'password' => Hash::make('Rahasia#2026!'),
        ]);
    }
}
