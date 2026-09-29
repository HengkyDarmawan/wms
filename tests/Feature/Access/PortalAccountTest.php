<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Master\Actions\CreatePortalAccountForContact;
use App\Domain\Master\Actions\SaveClientContact;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Livewire\ClientDetail;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\ClientContact;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-40 — akun portal dibuat dari PIC klien ([A-328]): role Klien,
 * `client_id`, keanggotaan Tim site per proyek yang diurus PIC, dan penjagaan
 * BR-ACC-03 / BR-ACC-04.
 */
class PortalAccountTest extends TenantTestCase
{
    private Client $klien;

    private ClientContact $pic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->klien = $this->makeClient(['name' => 'PT Klien Portal']);
        $proyek = $this->makeProject([
            'client_id' => $this->klien->id,
            'target_end_date' => now()->addMonths(4)->toDateString(),
        ]);

        $this->pic = app(SaveClientContact::class)->handle($this->klien, null, [
            'name' => 'Sari Wulan',
            'position' => 'Admin Site',
            'phone' => '0812-3456-7890',
            'email' => 'sari@klien-portal.test',
            'projects' => [$proyek->id],
        ]);
    }

    #[Test]
    public function tc_acc_40_akun_portal_dari_pic_masuk_tim_site(): void
    {
        $admin = $this->makeUser('company_admin');

        $user = app(CreatePortalAccountForContact::class)->handle($this->pic, false, $admin);

        $this->assertSame('sari@klien-portal.test', $user->email);
        $this->assertSame((int) $this->klien->id, (int) $user->client_id);
        $this->assertSame('6281234567890', $user->phone);
        $this->assertTrue($user->isClient());
        $this->assertSame(['client_user'], $user->roleCodes());
        $this->assertSame((int) $user->id, (int) $this->pic->refresh()->user_id);

        $proyekId = (int) $this->pic->projects()->value('projects.id');

        $penugasan = $user->roleAssignments()->firstOrFail();
        $this->assertSame(ScopeType::Project, $penugasan->scope_type);
        $this->assertSame($proyekId, (int) $penugasan->scope_id);
        $this->assertSame(now()->addMonths(4)->toDateString(), $penugasan->valid_until->toDateString(), 'Bawaan tanggal selesai = target selesai proyek.');

        // A-339: penugasannya dicatat sebagai keanggotaan Tim site, bukan penugasan lepas.
        $member = ProjectTeamMember::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($proyekId, (int) $member->project_id);
        $this->assertSame($member->id, (int) $penugasan->refresh()->project_team_member_id);

        $this->assertTrue($user->canAccessProject($proyekId));

        // BR-ACC-03: role internal tidak bisa ditambahkan ke user klien.
        try {
            app(AssignRole::class)->handle($user, Role::findByCode('warehouse_staff'), ScopeType::All);
            $this->fail('Role internal pada user klien seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-03', $e->rule);
        }

        // BR-ACC-04: cakupan `all` tidak untuk role klien.
        try {
            app(AssignRole::class)->handle($user, Role::findByCode('client_user'), ScopeType::All);
            $this->fail('Cakupan semua pada role Klien seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-04', $e->rule);
        }

        // Sekali saja.
        try {
            app(CreatePortalAccountForContact::class)->handle($this->pic->refresh(), false, $admin);
            $this->fail('PIC yang sudah punya akun seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertStringContainsString('sudah punya akun portal', $e->getMessage());
        }
    }

    #[Test]
    public function tc_acc_40b_pic_tanpa_email_atau_proyek_ditolak(): void
    {
        $tanpaEmail = app(SaveClientContact::class)->handle($this->klien, null, [
            'name' => 'Tanpa Email',
            'projects' => [$this->pic->projects()->value('projects.id')],
        ]);

        try {
            app(CreatePortalAccountForContact::class)->handle($tanpaEmail, false);
            $this->fail('PIC tanpa email seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('email', $e->fieldErrors);
        }

        $tanpaProyek = app(SaveClientContact::class)->handle($this->klien, null, [
            'name' => 'Tanpa Proyek',
            'email' => 'tanpa-proyek@klien-portal.test',
        ]);

        try {
            app(CreatePortalAccountForContact::class)->handle($tanpaProyek, false);
            $this->fail('PIC tanpa proyek seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('projects', $e->fieldErrors);
        }
    }

    #[Test]
    public function tc_acc_40c_proyek_pic_bertambah_ditawarkan_diselaraskan(): void
    {
        $admin = $this->makeUser('company_admin');
        $user = app(CreatePortalAccountForContact::class)->handle($this->pic, false, $admin);

        $proyekBaru = $this->makeProject(['client_id' => $this->klien->id]);

        app(SaveClientContact::class)->handle($this->klien, $this->pic->refresh(), [
            'name' => $this->pic->name,
            'email' => $this->pic->email,
            'projects' => [$this->pic->projects()->value('projects.id'), $proyekBaru->id],
        ]);

        $action = app(CreatePortalAccountForContact::class);

        $this->assertSame([$proyekBaru->code], $action->belumJadiAnggota($this->pic->refresh())->pluck('code')->all());

        $jumlah = $action->selaraskan($this->pic, $admin);

        $this->assertSame(1, $jumlah);
        $this->assertSame(2, ProjectTeamMember::query()->where('user_id', $user->id)->count());
        $this->assertCount(0, $action->belumJadiAnggota($this->pic->refresh()));

        $user->forgetPermissionCache();
        $this->assertTrue($user->refresh()->canAccessProject((int) $proyekBaru->id));
    }

    #[Test]
    public function tc_acc_40d_tanpa_izin_tambah_pengguna_tombolnya_403(): void
    {
        // Kepala Gudang boleh melihat klien, tetapi tidak boleh membuat pengguna.
        $kepala = $this->makeUser('warehouse_head');

        Livewire::actingAs($kepala)
            ->test(ClientDetail::class, ['client' => $this->klien])
            ->call('buatAkunPortal', $this->pic->id)
            ->assertForbidden();

        $this->assertNull($this->pic->refresh()->user_id);
    }

    #[Test]
    public function tc_acc_40e_admin_membuat_akun_portal_dari_layar_klien(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(ClientDetail::class, ['client' => $this->klien])
            ->call('buatAkunPortal', $this->pic->id)
            ->assertRedirect();

        $this->assertNotNull($this->pic->refresh()->user_id);
        $this->assertSame(1, User::query()->where('client_id', $this->klien->id)->count());
    }
}
