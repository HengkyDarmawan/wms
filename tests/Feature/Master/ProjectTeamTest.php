<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Access\Actions\AddProjectTeamMember;
use App\Domain\Access\Actions\EndProjectTeamMember;
use App\Domain\Access\Actions\ExtendProjectTeamMember;
use App\Domain\Access\Actions\UpdateUser;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SiteTeamStatus;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Livewire\ProjectTeam;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-43 dan TC-MST-44 — Tim site ([A-337]): penempatan berbatas waktu di
 * Gudang Site proyek, perpanjang, akhiri lebih awal, dan penjagaan supaya form
 * pengguna tidak ikut menghapus atau mempermanenkan penugasannya.
 */
class ProjectTeamTest extends TenantTestCase
{
    private Project $proyek;

    /** @var array<int, int> */
    private array $siteIds = [];

    private int $gudangTetapId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject(['target_end_date' => now()->addMonths(6)->toDateString()]);

        $this->gudangTetapId = (int) app(SaveWarehouse::class)->handle(null, [
            'code' => 'PUSAT1',
            'name' => 'Gudang Pusat Uji',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ])->id;

        foreach (['SITEA', 'SITEB'] as $kode) {
            $this->siteIds[] = (int) app(SaveWarehouse::class)->handle(null, [
                'code' => $kode,
                'name' => 'Gudang '.$kode,
                'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::SITE)->value('id'),
                'project_id' => $this->proyek->id,
            ])->id;
        }
    }

    #[Test]
    public function tc_mst_43_anggota_tim_site_ditambah_diperpanjang_dan_diakhiri(): void
    {
        // Staf ini punya gudang tetap sendiri; site hanya penempatan sementara.
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudangTetapId);
        $peran = Role::findByCode('warehouse_staff');

        $member = app(AddProjectTeamMember::class)->handle(
            $this->proyek, $staf, $peran,
            now()->toDateString(),
            now()->addMonth()->toDateString(),
        );

        $this->assertTrue($member->grants_access);

        $penugasan = RoleAssignment::query()->where('project_team_member_id', $member->id)->get();

        $this->assertCount(2, $penugasan, 'Satu penugasan per Gudang Site proyek (A-338).');
        $this->assertSame(
            $this->siteIds,
            $penugasan->pluck('scope_id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
        );
        $this->assertSame(ScopeType::Warehouse, $penugasan->first()->scope_type);
        $this->assertSame(now()->addMonth()->toDateString(), $penugasan->first()->valid_until->toDateString());

        $staf->forgetPermissionCache();
        $this->assertTrue($staf->refresh()->canAccessWarehouse($this->siteIds[0]));

        // Kembar ditolak.
        try {
            app(AddProjectTeamMember::class)->handle($this->proyek, $staf, $peran, now()->toDateString(), now()->addMonth()->toDateString());
            $this->fail('Anggota kembar seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertStringContainsString('sudah menjadi anggota', $e->getMessage());
        }

        // Perpanjang: tanggal mundur ditolak, maju diterima dan pengingat direset.
        $member->forceFill(['reminded_at' => now()])->save();

        try {
            app(ExtendProjectTeamMember::class)->handle($member, now()->subDay()->toDateString());
            $this->fail('Perpanjang ke tanggal mundur seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertStringContainsString('lebih jauh', $e->getMessage());
        }

        $baru = now()->addMonths(3)->toDateString();
        app(ExtendProjectTeamMember::class)->handle($member, $baru, 'Proyek mundur');

        $this->assertSame($baru, $member->refresh()->ends_on->toDateString());
        $this->assertNull($member->reminded_at);
        $this->assertSame($baru, RoleAssignment::query()->where('project_team_member_id', $member->id)->first()->valid_until->toDateString());

        // Akhiri lebih awal: alasan wajib, barisnya tidak dihapus (P-03).
        try {
            app(EndProjectTeamMember::class)->handle($member, '');
            $this->fail('Akhiri tanpa alasan seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        $alasan = ReasonCode::create(['context' => 'cancel', 'code' => 'SITE-PINDAH', 'label' => 'Pindah proyek', 'is_active' => true]);

        app(EndProjectTeamMember::class)->handle($member, $alasan->code, 'Pindah ke proyek lain', now()->subDay()->toDateString());

        $member->refresh();

        $this->assertNotNull($member->ended_at);
        $this->assertSame(SiteTeamStatus::Ended, $member->status());
        $this->assertDatabaseHas('project_team_members', ['id' => $member->id], 'tenant');

        $staf->forgetPermissionCache();
        $staf->refresh();

        // A-341: yang berhenti hanya akses ke site; akun dan gudang tetapnya jalan terus.
        $this->assertFalse($staf->canAccessWarehouse($this->siteIds[0]), 'Akses ke Gudang Site berhenti.');
        $this->assertFalse($staf->canAccessWarehouse($this->siteIds[1]));
        $this->assertTrue($staf->canAccessWarehouse($this->gudangTetapId), 'Gudang tetap tidak ikut terputus.');
        $this->assertTrue($staf->canSignIn(), 'Akunnya tetap bisa masuk.');
    }

    #[Test]
    public function tc_mst_43b_proyek_tanpa_gudang_site_menolak_peran_gudang(): void
    {
        $polos = $this->makeProject();
        $staf = $this->makeUser('warehouse_staff');

        try {
            app(AddProjectTeamMember::class)->handle($polos, $staf, Role::findByCode('warehouse_staff'), now()->toDateString(), now()->addMonth()->toDateString());
            $this->fail('Peran gudang tanpa Gudang Site seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertStringContainsString('Gudang Site', $e->getMessage());
        }

        // Peran berbasis proyek tetap bisa: cakupannya proyek itu sendiri.
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $polos->id);
        $pemohon->roleAssignments()->delete();

        $member = app(AddProjectTeamMember::class)->handle(
            $polos, $pemohon, Role::findByCode('internal_requester'),
            now()->toDateString(), now()->addMonth()->toDateString(),
        );

        $penugasan = RoleAssignment::query()->where('project_team_member_id', $member->id)->firstOrFail();

        $this->assertSame(ScopeType::Project, $penugasan->scope_type);
        $this->assertSame((int) $polos->id, (int) $penugasan->scope_id);
    }

    #[Test]
    public function tc_mst_44_penugasan_tetap_dan_form_pengguna_tidak_tersentuh(): void
    {
        $peran = Role::findByCode('warehouse_staff');

        // Orang ini sudah punya akses tetap ke SITEA lewat penugasan biasa.
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->siteIds[0]);

        $member = app(AddProjectTeamMember::class)->handle(
            $this->proyek, $staf, $peran, now()->toDateString(), now()->addMonth()->toDateString(),
        );

        $tetap = RoleAssignment::query()->where('user_id', $staf->id)
            ->where('scope_id', $this->siteIds[0])->whereNull('project_team_member_id')->firstOrFail();

        $this->assertNull($tetap->valid_until, 'Penugasan tetap tidak boleh ikut diberi tanggal.');
        $this->assertTrue($member->grants_access, 'SITEB tetap diberikan lewat Tim site.');

        // Menyimpan ulang dari form pengguna tidak menghapus baris Tim site.
        $sebelum = RoleAssignment::query()->where('project_team_member_id', $member->id)->count();

        app(UpdateUser::class)->handle($staf, ['name' => $staf->name], [[
            'role_id' => $peran->id,
            'scope_type' => ScopeType::Warehouse->value,
            'scope_id' => $this->siteIds[0],
            'valid_from' => null,
            'valid_until' => null,
        ]]);

        $this->assertSame($sebelum, RoleAssignment::query()->where('project_team_member_id', $member->id)->count());
        $this->assertNotNull(RoleAssignment::query()->where('project_team_member_id', $member->id)->first()->valid_until);
        $this->assertSame(1, ProjectTeamMember::query()->where('id', $member->id)->count());
    }

    #[Test]
    public function tc_mst_44b_layar_tim_site_menuntut_izin(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->siteIds[0]);

        Livewire::actingAs($admin)
            ->test(ProjectTeam::class, ['project' => $this->proyek])
            ->assertSet('showForm', false)
            ->call('tambah')
            ->assertSet('showForm', true)
            ->set('userId', (string) $staf->id)
            ->set('roleId', (string) Role::findByCode('internal_requester')->id)
            ->set('startsOn', now()->toDateString())
            ->set('endsOn', now()->addMonth()->toDateString())
            ->call('simpanTambah')
            ->assertSet('showForm', false);

        $this->assertSame(1, ProjectTeamMember::query()->where('project_id', $this->proyek->id)->count());

        // Tanpa `role.assign` tidak bisa menambah anggota.
        Livewire::actingAs($staf)
            ->test(ProjectTeam::class, ['project' => $this->proyek])
            ->call('tambah')
            ->assertForbidden();

        // Hub proyek memunculkan tabnya.
        $this->actingAs($admin)->get($this->tenantUrl('projects/'.$this->proyek->id.'?tab=tim-site'))
            ->assertOk()
            ->assertSee('Tim site');
    }
}
