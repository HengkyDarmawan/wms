<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\InviteUser;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Access\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-05, TC-ACC-06 — undangan user (10-access §4, §10).
 */
class InvitationTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_05_undangan_diterima_mengaktifkan_akun(): void
    {
        Notification::fake();

        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, [
            'password' => null,
            'email_verified_at' => null,
        ]);

        $invitation = app(InviteUser::class)->handle($user);
        $token = $invitation->plainToken;

        Notification::assertSentTo($user, UserInvitationNotification::class);
        $this->assertSame(\App\Domain\Access\Enums\UserStatus::Invited, $user->refresh()->status());

        $this->get($this->tenantUrl('/invitation/'.$token))
            ->assertOk()
            ->assertSee('Atur password Anda');

        $this->post($this->tenantUrl('/invitation/'.$token), [
            'name' => 'Nama Lengkap',
            'phone' => '+628123456789',
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])->assertRedirect($this->tenantUrl('/login'));

        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Nama Lengkap', $user->name);
        $this->assertSame(\App\Domain\Access\Enums\UserStatus::Active, $user->status());

        $this->assertNotNull(
            UserInvitation::query()->where('user_id', $user->id)->first()?->accepted_at,
            'Undangan harus ditandai diterima.',
        );

        // Setelah password diatur, user bisa masuk.
        $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => 'PasswordBaru#2026',
        ])->assertRedirect($this->tenantUrl('/'));
    }

    #[Test]
    public function tc_acc_06_undangan_kedaluwarsa_ditolak_dan_bisa_dikirim_ulang(): void
    {
        Notification::fake();

        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, ['password' => null]);

        $first = app(InviteUser::class)->handle($user);
        $firstToken = $first->plainToken;

        // Lewat masa berlaku (default 72 jam).
        $first->forceFill(['expires_at' => now()->subHour()])->save();

        $this->get($this->tenantUrl('/invitation/'.$firstToken))
            ->assertOk()
            ->assertSee('Undangan sudah kedaluwarsa');

        $this->post($this->tenantUrl('/invitation/'.$firstToken), [
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])->assertSessionHasErrors('password');

        $this->assertNull($user->refresh()->password, 'Password tidak boleh terpasang dari undangan kedaluwarsa.');

        // Admin mengirim ulang: token baru berlaku, token lama tidak.
        $second = app(InviteUser::class)->handle($user);
        $secondToken = $second->plainToken;

        $this->assertSame(2, $second->sent_count);
        $this->assertNotSame($firstToken, $secondToken);

        $this->assertNull(
            UserInvitation::query()->where('token', UserInvitation::hashToken($firstToken))->first(),
            'Undangan lama harus dibatalkan.',
        );

        $this->post($this->tenantUrl('/invitation/'.$secondToken), [
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])->assertRedirect($this->tenantUrl('/login'));

        $this->assertNotNull($user->refresh()->password);
    }

    #[Test]
    public function tc_acc_bonus_undangan_menyimpan_hash_bukan_token_mentah(): void
    {
        Notification::fake();

        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, ['password' => null]);
        $invitation = app(InviteUser::class)->handle($user);
        $token = $invitation->plainToken;

        $this->assertNotSame($token, $invitation->token);
        $this->assertSame(UserInvitation::hashToken($token), $invitation->token);
    }

    protected function makeUser(
        string $roleCode = 'warehouse_staff',
        ScopeType $scopeType = ScopeType::All,
        ?int $scopeId = null,
        array $attributes = [],
    ): User {
        return parent::makeUser($roleCode, $scopeType, $scopeId, $attributes);
    }
}
