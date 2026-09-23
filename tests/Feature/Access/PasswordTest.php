<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\ChangePassword;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\PasswordHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-07, TC-ACC-08 — kebijakan password (BR-ACC-06).
 */
class PasswordTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_07_password_tidak_boleh_sama_dengan_tiga_terakhir(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);
        $action = app(ChangePassword::class);

        $action->handle($user, 'PasswordSatu#01', requireCurrent: false);
        $action->handle($user, 'PasswordDua#02', requireCurrent: false);
        $action->handle($user, 'PasswordTiga#03', requireCurrent: false);

        $this->assertSame(3, PasswordHistory::where('user_id', $user->id)->count());

        // Memakai ulang password kedua harus ditolak.
        try {
            $action->handle($user, 'PasswordDua#02', requireCurrent: false);
            $this->fail('Password lama seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-06', $e->rule);
            $this->assertStringContainsString('3 password terakhir', $e->getMessage());
        }

        // Password yang benar-benar baru diterima.
        $action->handle($user, 'PasswordEmpat#04', requireCurrent: false);
        $this->assertTrue(Hash::check('PasswordEmpat#04', $user->refresh()->password));
    }

    #[Test]
    public function tc_acc_07b_password_minimal_sepuluh_karakter(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $this->expectException(AccessRuleException::class);
        $this->expectExceptionMessage('Password minimal 10 karakter.');

        app(ChangePassword::class)->handle($user, 'pendek', requireCurrent: false);
    }

    #[Test]
    public function tc_acc_08_ganti_password_menghapus_sesi_lain(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        // Dua sesi lain milik user yang sama.
        foreach (['sesi-lama-1', 'sesi-lama-2'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'uji',
                'payload' => base64_encode('kosong'),
                'last_activity' => now()->timestamp,
            ]);
        }

        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());

        app(ChangePassword::class)->handle($user, 'PasswordBaru#2026', requireCurrent: false);

        $this->assertSame(
            0,
            DB::table('sessions')->where('user_id', $user->id)->count(),
            'Sesi lain harus dihapus saat password diganti (BR-ACC-06).',
        );
    }

    #[Test]
    public function tc_acc_08b_ganti_password_lewat_profil_butuh_password_lama(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, [
            'password' => Hash::make('PasswordLama#01'),
        ]);

        $this->actingAs($user)
            ->put($this->tenantUrl('/profile/password'), [
                'current_password' => 'salah',
                'password' => 'PasswordBaru#2026',
                'password_confirmation' => 'PasswordBaru#2026',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('PasswordLama#01', $user->refresh()->password));

        $this->actingAs($user)
            ->put($this->tenantUrl('/profile/password'), [
                'current_password' => 'PasswordLama#01',
                'password' => 'PasswordBaru#2026',
                'password_confirmation' => 'PasswordBaru#2026',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('PasswordBaru#2026', $user->refresh()->password));
    }
}
