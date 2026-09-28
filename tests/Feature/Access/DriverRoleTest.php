<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Livewire\UserForm;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\DemoFlows;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-39 — driver tanpa akun (A-311, A-314): role Driver menjadi role
 * lama yang tidak ditawarkan untuk penugasan baru; user lama tetap bisa masuk.
 */
class DriverRoleTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_39_role_driver_tidak_ditawarkan_user_lama_tetap_masuk(): void
    {
        $admin = $this->makeUser('company_admin');
        $driver = Role::findByCode('driver');

        $this->assertNotNull($driver, 'Role lama tetap ada (P-03).');
        $this->assertSame('Driver (lama)', $driver->name);
        $this->assertContains('driver', Role::NOT_OFFERED);

        // User baru: Driver tidak ada di pilihan role.
        $kode = Livewire::actingAs($admin)->test(UserForm::class)->viewData('roles')->pluck('code')->all();
        $this->assertNotContains('driver', $kode);
        $this->assertContains('warehouse_staff', $kode);

        // User lama yang sudah memegang role Driver: pilihan tetap tampil agar penugasannya terbaca.
        $lama = $this->makeUser('driver');
        $kodeLama = Livewire::actingAs($admin)->test(UserForm::class, ['userId' => $lama->id])->viewData('roles')->pluck('code')->all();
        $this->assertContains('driver', $kodeLama);

        $lama->forgetPermissionCache();
        $this->assertTrue($lama->canSignIn());
        $this->assertTrue($lama->hasPermission('shipment.view'));
        $this->assertFalse($lama->hasPermission('shipment.ship'));
        $this->assertFalse($lama->hasPermission('shipment.confirm_delivery'));

        // Alur contoh "Masuk sebagai" tidak lagi memuat langkah driver.
        foreach ((new \ReflectionClassConstant(DemoFlows::class, 'FLOWS'))->getValue() as $alur) {
            $this->assertNotContains('driver', array_column($alur['steps'], 'role'), $alur['key']);
        }
    }
}
