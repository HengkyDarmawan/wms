<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\AcceptInvitation;
use App\Domain\Access\Actions\InviteUser;
use App\Domain\Access\Livewire\UserDetail;
use App\Domain\Access\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * TC-ACC-42 dan TC-ACC-43 — kartu Undangan yang bisa disalin ([A-333]) dan
 * jalur "buatkan saja passwordnya" ([A-334]).
 */
class InvitationLinkTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_42_tautan_undangan_bisa_ditampilkan_disalin_dan_dikirim_ulang(): void
    {
        $admin = $this->makeUser('company_admin');
        $baru = $this->makeUser('warehouse_staff', attributes: ['email_verified_at' => null, 'password' => null]);

        $undangan = app(InviteUser::class)->handle($baru, $admin);
        $tokenLama = $undangan->plainToken;

        // A-333: token mentah tersimpan terenkripsi, jadi tautannya bisa dibuka lagi.
        $tersimpan = UserInvitation::query()->findOrFail($undangan->id);
        $this->assertSame($tokenLama, $tersimpan->token_plain);
        $this->assertNotSame($tokenLama, $tersimpan->token, 'Kolom token tetap hash, bukan teks polos.');
        $this->assertStringContainsString('/invitation/'.$tokenLama, (string) $tersimpan->url());

        $komponen = Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $baru->id])
            ->assertSet('tautan', '')
            ->call('tampilkanTautan');

        $komponen->assertSet('tautan', (string) $tersimpan->url());

        // Membuka tautan tercatat di riwayat.
        $this->assertSame(1, Activity::query()->where('subject_id', $baru->id)
            ->where('description', 'Tautan undangan dilihat')->count());

        // Kirim ulang: tautan baru, yang lama mati.
        $komponen->call('kirimUlangUndangan');

        $sekarang = UserInvitation::query()->where('user_id', $baru->id)->pending()->latest('id')->firstOrFail();

        $this->assertNotSame($tokenLama, $sekarang->token_plain);
        $this->assertSame(2, (int) $sekarang->sent_count);
        $this->assertNull(AcceptInvitation::findUsable((string) $tokenLama), 'Tautan lama langsung mati.');
        $this->assertNotNull(AcceptInvitation::findUsable((string) $sekarang->token_plain));

        // Kartu hilang setelah undangan dipakai.
        app(AcceptInvitation::class)->handle((string) $sekarang->token_plain, 'Rahasia#2026!Baru', null, null);

        Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $baru->id])
            ->assertDontSee('Undangan — belum dipakai');
    }

    #[Test]
    public function tc_acc_42b_hanya_pemegang_tambah_pengguna_yang_boleh_melihat_tautan(): void
    {
        $admin = $this->makeUser('company_admin');
        $baru = $this->makeUser('warehouse_staff', attributes: ['email_verified_at' => null, 'password' => null]);
        app(InviteUser::class)->handle($baru, $admin);

        // Kepala Gudang boleh melihat pengguna, tetapi tidak boleh menambah.
        $kepala = $this->makeUser('warehouse_head');

        Livewire::actingAs($kepala)->test(UserDetail::class, ['userId' => $baru->id])
            ->call('tampilkanTautan')
            ->assertForbidden();

        Livewire::actingAs($kepala)->test(UserDetail::class, ['userId' => $baru->id])
            ->assertDontSee('Tampilkan tautan undangan');
    }

    #[Test]
    public function tc_acc_43_admin_bisa_membuatkan_password_tanpa_undangan(): void
    {
        Mail::fake();

        $admin = $this->makeUser('company_admin');
        $baru = $this->makeUser('warehouse_staff', attributes: ['email_verified_at' => null, 'password' => null]);
        app(InviteUser::class)->handle($baru, $admin);

        $komponen = Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $baru->id])
            ->call('mintaPassword')
            ->assertSet('formPassword', true);

        // Password pendek ditolak.
        $komponen->set('passwordBaru', 'pendek')->call('simpanPassword')->assertHasErrors('passwordBaru');

        $komponen->set('passwordBaru', 'Palu-Beton-2026')->call('simpanPassword')
            ->assertHasNoErrors()
            ->assertSet('passwordDibuat', 'Palu-Beton-2026')
            ->assertSet('formPassword', false);

        $baru->refresh();

        $this->assertTrue($baru->is_active);
        $this->assertNotNull($baru->email_verified_at);
        $this->assertTrue($baru->canSignIn());
        $this->assertSame(0, UserInvitation::query()->where('user_id', $baru->id)->pending()->count(), 'Undangan tertunda dibatalkan.');
        $this->assertTrue(password_verify('Palu-Beton-2026', (string) $baru->password));
    }

    #[Test]
    public function tc_acc_43b_undangan_dan_buatkan_password_ditolak_untuk_akun_yang_sudah_bisa_masuk(): void
    {
        Mail::fake();

        $admin = $this->makeUser('company_admin');
        $aktif = $this->makeUser('warehouse_staff');
        $passwordLama = (string) $aktif->password;

        // Kirim ulang undangan ke akun aktif ditolak — tautannya tampil ke Admin.
        Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $aktif->id])
            ->call('kirimUlangUndangan')
            ->assertSet('tautan', '')
            ->assertNotSet('ruleError', '');

        $this->assertSame(0, UserInvitation::query()->where('user_id', $aktif->id)->count());

        // "Buatkan password" untuk akun aktif ditolak; password tidak berubah.
        Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $aktif->id])
            ->set('passwordBaru', 'Ambil-Alih-2026')
            ->call('simpanPassword')
            ->assertNotSet('ruleError', '')
            ->assertSet('passwordDibuat', '');

        $this->assertSame($passwordLama, (string) $aktif->refresh()->password);

        // Akun nonaktif tidak ikut dihidupkan.
        $nonaktif = $this->makeUser('warehouse_staff', attributes: ['email_verified_at' => null, 'password' => null, 'is_active' => false]);

        Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $nonaktif->id])
            ->set('passwordBaru', 'Hidupkan-Lagi-2026')
            ->call('simpanPassword')
            ->assertNotSet('ruleError', '');

        $this->assertFalse($nonaktif->refresh()->is_active);
    }

    #[Test]
    public function tc_acc_43c_token_undangan_dibersihkan_setelah_dipakai_dan_tidak_terserialisasi(): void
    {
        $admin = $this->makeUser('company_admin');
        $baru = $this->makeUser('warehouse_staff', attributes: ['email_verified_at' => null, 'password' => null]);
        $undangan = app(InviteUser::class)->handle($baru, $admin);

        $this->assertArrayNotHasKey('token_plain', UserInvitation::query()->findOrFail($undangan->id)->toArray());
        $this->assertArrayNotHasKey('token', UserInvitation::query()->findOrFail($undangan->id)->toArray());

        app(AcceptInvitation::class)->handle((string) $undangan->plainToken, 'Rahasia#2026!Baru', null, null);

        $this->assertNull(UserInvitation::query()->findOrFail($undangan->id)->token_plain);
    }
}
