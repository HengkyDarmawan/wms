<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\AddProjectTeamMember;
use App\Domain\Access\Actions\AdoptAssignmentIntoSiteTeam;
use App\Domain\Access\Actions\ExtendProjectTeamMember;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Support\SiteTeam;
use App\Domain\Master\Models\Project;
use App\Domain\Notification\Models\Notification;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-44 — pengingat H-7 Tim site ([A-340]) dan pemindahan penugasan
 * bertanggal lama ke Tim site ([A-343]).
 */
class SiteTeamReminderTest extends TenantTestCase
{
    private Project $proyek;

    private int $siteId;

    private int $gudangTetapId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject(['target_end_date' => now()->addMonths(6)->toDateString()]);

        $this->gudangTetapId = (int) app(SaveWarehouse::class)->handle(null, [
            'code' => 'PST',
            'name' => 'Gudang Pusat',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ])->id;

        $this->siteId = (int) app(SaveWarehouse::class)->handle(null, [
            'code' => 'SITE-H7',
            'name' => 'Gudang Site H7',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::SITE)->value('id'),
            'project_id' => $this->proyek->id,
        ])->id;
    }

    #[Test]
    public function tc_acc_44_pengingat_h7_dikirim_sekali_ke_orang_kepala_gudang_dan_admin(): void
    {
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->siteId);
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudangTetapId);

        $member = app(AddProjectTeamMember::class)->handle(
            $this->proyek, $staf, Role::findByCode('warehouse_staff'),
            now()->subDays(10)->toDateString(),
            now()->addDays(3)->toDateString(),
        );

        $this->artisan('notifications:daily')->assertSuccessful();

        foreach ([$staf, $kepala, $admin] as $penerima) {
            $this->assertSame(1, $this->lonceng($penerima->id), 'Penerima '.$penerima->name.' mendapat satu pengingat.');
        }

        $this->assertNotNull($member->refresh()->reminded_at, 'Penjaga reminded_at terisi supaya tidak berulang.');

        // Dijalankan lagi besok tidak menggandakan.
        Notification::query()->update(['read_at' => now()]);
        $this->artisan('notifications:daily')->assertSuccessful();

        $this->assertSame(1, $this->lonceng($staf->id), 'Pengingat H-7 hanya sekali per keanggotaan.');

        // Perpanjang mereset pengingat supaya periode baru diingatkan lagi.
        app(ExtendProjectTeamMember::class)->handle($member, now()->addDays(6)->toDateString());

        $this->assertNull($member->refresh()->reminded_at);

        $this->artisan('notifications:daily')->assertSuccessful();

        $this->assertSame(2, $this->lonceng($staf->id), 'Setelah diperpanjang, pengingat berikutnya dikirim lagi.');
    }

    #[Test]
    public function tc_acc_44b_penugasan_bertanggal_lama_dijadikan_tim_site(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudangTetapId);

        // Penugasan gaya lama: bertanggal, diketik langsung di form pengguna.
        $lama = RoleAssignment::create([
            'user_id' => $staf->id,
            'role_id' => Role::findByCode('warehouse_staff')->id,
            'scope_type' => ScopeType::Warehouse->value,
            'scope_id' => $this->siteId,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        $member = app(AdoptAssignmentIntoSiteTeam::class)->handle($lama->fresh()->load('role'));

        $this->assertSame((int) $this->proyek->id, (int) $member->project_id);
        $this->assertSame(now()->addMonth()->toDateString(), $member->ends_on->toDateString());
        $this->assertSame($member->id, (int) $lama->refresh()->project_team_member_id);

        // Sekali saja.
        try {
            app(AdoptAssignmentIntoSiteTeam::class)->handle($lama->fresh()->load('role'));
            $this->fail('Penugasan yang sudah jadi Tim site seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertStringContainsString('sudah bagian', $e->getMessage());
        }

        // Cakupan yang bukan proyek/Gudang Site ditolak.
        $bukanSite = RoleAssignment::create([
            'user_id' => $staf->id,
            'role_id' => Role::findByCode('warehouse_head')->id,
            'scope_type' => ScopeType::Warehouse->value,
            'scope_id' => $this->gudangTetapId,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        try {
            app(AdoptAssignmentIntoSiteTeam::class)->handle($bukanSite->fresh()->load('role'));
            $this->fail('Gudang tetap bukan Gudang Site, seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertStringContainsString('bukan proyek atau Gudang Site', $e->getMessage());
        }

        $this->assertSame(1, ProjectTeamMember::query()->where('user_id', $staf->id)->count());
    }

    #[Test]
    public function tc_acc_44c_kepala_gudang_site_boleh_memperpanjang(): void
    {
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->siteId);
        $kepalaLain = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudangTetapId);
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudangTetapId);

        $siteTeam = app(SiteTeam::class);

        $this->assertTrue($siteTeam->canExtend($kepala, $this->proyek), 'A-342: Kepala Gudang site boleh memperpanjang.');
        $this->assertFalse($siteTeam->canExtend($kepalaLain, $this->proyek), 'Kepala gudang lain tidak.');
        $this->assertFalse($siteTeam->canExtend($staf, $this->proyek));
        $this->assertTrue($siteTeam->canExtend($this->makeUser('company_admin'), $this->proyek));
    }

    private function lonceng(int $userId): int
    {
        return Notification::query()->where('user_id', $userId)
            ->where('type', 'project_team.ending_soon')->where('channel', 'in_app')->count();
    }
}
