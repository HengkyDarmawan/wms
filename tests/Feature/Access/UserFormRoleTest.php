<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\UpdateUser;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Livewire\UserForm;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-41 — form pengguna "pilih peran dulu" ([A-331]) dan hilangnya isian
 * tanggal dari form ([A-337]).
 */
class UserFormRoleTest extends TenantTestCase
{
    private int $gudangA;

    private int $gudangB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['UF-A', 'UF-B'] as $kode) {
            $id = (int) app(SaveWarehouse::class)->handle(null, [
                'code' => $kode,
                'name' => 'Gudang '.$kode,
                'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
            ])->id;

            $kode === 'UF-A' ? $this->gudangA = $id : $this->gudangB = $id;
        }
    }

    #[Test]
    public function tc_acc_41_pertanyaan_cakupan_mengikuti_peran_yang_dipilih(): void
    {
        $admin = $this->makeUser('company_admin');

        // Kepala Gudang tanpa gudang ditolak, dengan pesan awam.
        $komponen = Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Kepala Baru')
            ->set('email', 'kepala.baru@demo.wms.test')
            ->call('pilihPeran', Role::findByCode('warehouse_head')->id)
            ->call('save')
            ->assertHasErrors('gudangDipilih');

        $pesan = $komponen->errors()->first('gudangDipilih');

        $this->assertSame('Pilih minimal satu gudang.', $pesan);
        $this->assertStringNotContainsString('validation.', $pesan);

        // Dua gudang dicentang → dua penugasan, tanpa tanggal.
        $komponen->set('gudangDipilih', [(string) $this->gudangA, (string) $this->gudangB])
            ->call('save')
            ->assertHasNoErrors();

        $kepala = User::query()->where('email', 'kepala.baru@demo.wms.test')->firstOrFail();
        $penugasan = $kepala->roleAssignments()->get();

        $this->assertCount(2, $penugasan);
        $this->assertTrue($penugasan->every(fn (RoleAssignment $a) => $a->scope_type === ScopeType::Warehouse));
        $this->assertTrue($penugasan->every(fn (RoleAssignment $a) => $a->valid_until === null), 'A-337: peran biasa tanpa tanggal.');
    }

    #[Test]
    public function tc_acc_41b_peran_bercakupan_semua_tidak_bertanya_apa_apa(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Manajemen Baru')
            ->set('email', 'manajemen.baru@demo.wms.test')
            ->call('pilihPeran', Role::findByCode('management')->id)
            ->assertSee('Cakupan otomatis: semua gudang & proyek.')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'manajemen.baru@demo.wms.test')->firstOrFail();
        $penugasan = $user->roleAssignments()->sole();

        $this->assertSame(ScopeType::All, $penugasan->scope_type);
        $this->assertNull($penugasan->scope_id);
        $this->assertNull($user->accessibleWarehouseIds(), 'Cakupan semua = tidak dibatasi.');
    }

    #[Test]
    public function tc_acc_41c_peran_klien_menuntut_klien_dan_masuk_tim_site(): void
    {
        $admin = $this->makeUser('company_admin');
        $klien = $this->makeClient(['name' => 'PT Klien Form']);
        $proyek = $this->makeProject(['client_id' => $klien->id, 'target_end_date' => now()->addMonths(5)->toDateString()]);

        $komponen = Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Portal Baru')
            ->set('email', 'portal.baru@klien-form.test')
            ->call('pilihPeran', Role::findByCode('client_user')->id)
            ->call('save')
            ->assertHasErrors(['clientId', 'proyekDipilih']);

        $this->assertSame('Pilih klien dulu.', $komponen->errors()->first('clientId'));

        $komponen->set('clientId', $klien->id)
            ->set('proyekDipilih', [(string) $proyek->id])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'portal.baru@klien-form.test')->firstOrFail();

        $this->assertSame((int) $klien->id, (int) $user->client_id);
        $this->assertSame(['client_user'], $user->roleCodes());

        // A-339: penempatannya tercatat di Tim site, lengkap dengan periodenya.
        $member = ProjectTeamMember::query()->where('user_id', $user->id)->sole();

        $this->assertSame((int) $proyek->id, (int) $member->project_id);
        $this->assertSame(now()->addMonths(5)->toDateString(), $member->ends_on->toDateString());
        $this->assertSame($member->id, (int) $user->roleAssignments()->sole()->project_team_member_id);
    }

    #[Test]
    public function tc_acc_41f_proyek_klien_lain_tidak_ikut_terbawa_dan_pindah_klien_dijaga(): void
    {
        $admin = $this->makeUser('company_admin');
        $klienA = $this->makeClient(['name' => 'PT Klien A']);
        $klienB = $this->makeClient(['name' => 'PT Klien B']);
        $proyekA = $this->makeProject(['client_id' => $klienA->id, 'target_end_date' => now()->addMonths(3)->toDateString()]);
        $proyekB = $this->makeProject(['client_id' => $klienB->id, 'target_end_date' => now()->addMonths(3)->toDateString()]);

        // Ganti klien setelah mencentang proyek: centang lama dikosongkan.
        Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Portal Pindah')
            ->set('email', 'portal.pindah@klien-b.test')
            ->call('pilihPeran', Role::findByCode('client_user')->id)
            ->set('clientId', $klienA->id)
            ->set('proyekDipilih', [(string) $proyekA->id])
            ->set('clientId', $klienB->id)
            ->assertSet('proyekDipilih', [])
            // Payload yang diubah tetap ditolak di aksi (A-21).
            ->set('proyekDipilih', [(string) $proyekA->id, (string) $proyekB->id])
            ->call('save')
            ->assertHasErrors('peranUtama');

        $this->assertFalse(RoleAssignment::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'portal.pindah@klien-b.test'))
            ->where('scope_type', ScopeType::Project->value)->where('scope_id', $proyekA->id)
            ->exists(), 'Klien B tidak boleh mendapat proyek klien A.');

        // User klien yang masih punya penempatan Tim site tidak bisa dipindah ke klien lain (BR-ACC-03).
        Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Portal Tetap')
            ->set('email', 'portal.tetap@klien-a.test')
            ->call('pilihPeran', Role::findByCode('client_user')->id)
            ->set('clientId', $klienA->id)
            ->set('proyekDipilih', [(string) $proyekA->id])
            ->call('save')
            ->assertHasNoErrors();

        $tetap = User::query()->where('email', 'portal.tetap@klien-a.test')->firstOrFail();

        $this->expectException(AccessRuleException::class);

        app(UpdateUser::class)->handle($tetap, ['name' => $tetap->name, 'client_id' => $klienB->id], null, $admin);
    }

    #[Test]
    public function tc_acc_41d_form_tidak_punya_isian_tanggal_dan_tidak_mempermanenkan_penugasan_lama(): void
    {
        $admin = $this->makeUser('company_admin');
        $target = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudangA);

        // Penugasan bertanggal gaya lama pada peran kedua.
        RoleAssignment::create([
            'user_id' => $target->id,
            'role_id' => Role::findByCode('internal_requester')->id,
            'scope_type' => ScopeType::Project->value,
            'scope_id' => $this->makeProject()->id,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        $komponen = Livewire::actingAs($admin)->test(UserForm::class, ['userId' => $target->id]);

        // Mode Ubah membuka Pengaturan lanjutan sendiri karena ada peran kedua.
        $komponen->assertSet('lanjutanTerbuka', true)
            ->assertDontSeeHtml('wire:model="assignments.0.valid_from"')
            ->assertDontSeeHtml('wire:model="assignments.0.valid_until"')
            ->assertSee('Penugasan bertanggal lama');

        $komponen->set('name', 'Tetap Bertanggal')->call('save')->assertHasNoErrors();

        $lama = RoleAssignment::query()->where('user_id', $target->id)
            ->where('role_id', Role::findByCode('internal_requester')->id)->sole();

        $this->assertNotNull($lama->valid_until, 'A-337: tanggal lama tidak boleh diam-diam hilang.');
        $this->assertSame(now()->addMonth()->toDateString(), $lama->valid_until->toDateString());
    }

    #[Test]
    public function tc_acc_41e_admin_bisa_membuatkan_password_saat_menambah_pengguna(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Tanpa Undangan')
            ->set('email', 'tanpa.undangan@demo.wms.test')
            ->call('pilihPeran', Role::findByCode('warehouse_staff')->id)
            ->set('gudangDipilih', [(string) $this->gudangA])
            ->set('caraMasuk', 'password')
            ->set('passwordAwal', 'pendek')
            ->call('save')
            ->assertHasErrors('passwordAwal')
            ->set('passwordAwal', 'Palu-Beton-2026')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $user = User::query()->where('email', 'tanpa.undangan@demo.wms.test')->firstOrFail();

        $this->assertTrue($user->canSignIn());
        $this->assertSame(0, $user->invitations()->count(), 'Tanpa undangan sama sekali.');
        $this->assertTrue(password_verify('Palu-Beton-2026', (string) $user->password));
    }
}
