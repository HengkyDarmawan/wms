<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Livewire\ImpersonationPicker;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\DemoFlows;
use App\Domain\Access\Support\Impersonation;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * TC-ACC-31–38 — "Masuk sebagai" oleh Admin Company (10-access §6.8, A-260).
 */
class ImpersonationTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_31_admin_masuk_sebagai_staf_lalu_kembali(): void
    {
        $admin = $this->makeUser('company_admin', attributes: ['name' => 'Rina Admin']);
        $staf = $this->makeUser('warehouse_staff', attributes: ['name' => 'Dedi Staf']);

        $this->actingAs($admin)->post($this->tenantUrl('/impersonate/'.$staf->id))
            ->assertRedirect($this->tenantUrl('/'));

        $this->assertAuthenticatedAs($staf);
        $this->assertSame($admin->id, session(Impersonation::SESSION_IMPERSONATOR_ID));

        $this->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(__('Mode presentasi — Anda sedang masuk sebagai'))
            ->assertSee('Dedi Staf')
            ->assertSee(__('Kembali ke :nama', ['nama' => 'Rina Admin']));

        $this->post($this->tenantUrl('/impersonate/leave'))->assertRedirect($this->tenantUrl('/impersonate'));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has(Impersonation::SESSION_IMPERSONATOR_ID));
        $this->get($this->tenantUrl('/'))->assertOk()->assertDontSee(__('Mode presentasi — Anda sedang masuk sebagai'));
    }

    #[Test]
    public function tc_acc_32_masuk_sebagai_klien_membuka_portal(): void
    {
        $admin = $this->makeUser('company_admin');
        $proyek = $this->makeProject();
        $klien = $this->makeUser('client_user', ScopeType::Project, $proyek->id, ['client_id' => $proyek->client_id]);

        $this->actingAs($admin)->post($this->tenantUrl('/impersonate/'.$klien->id))
            ->assertRedirect($this->tenantUrl('/portal'));
        $this->assertAuthenticatedAs($klien);

        $this->get($this->tenantUrl('/portal'))->assertOk()->assertSee(__('Mode presentasi — Anda sedang masuk sebagai'));

        $this->post($this->tenantUrl('/impersonate/leave'))->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function tc_acc_33_selain_admin_company_tidak_bisa_masuk_sebagai(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($kepala)->get($this->tenantUrl('/impersonate'))->assertForbidden();
        $this->actingAs($kepala)->post($this->tenantUrl('/impersonate/'.$staf->id))->assertForbidden();

        $this->assertAuthenticatedAs($kepala);
        $this->assertFalse(session()->has(Impersonation::SESSION_IMPERSONATOR_ID));
    }

    #[Test]
    public function tc_acc_34_target_yang_tidak_memenuhi_syarat_ditolak(): void
    {
        $admin = $this->makeUser('company_admin');
        $adminLain = $this->makeUser('company_admin');
        $nonaktif = $this->makeUser('warehouse_staff', attributes: ['is_active' => false]);
        $tanpaRole = $this->makeUser('');

        foreach ([$admin, $adminLain, $nonaktif, $tanpaRole] as $target) {
            $this->actingAs($admin)->post($this->tenantUrl('/impersonate/'.$target->id))
                ->assertSessionHas('impersonate_error');

            $this->assertAuthenticatedAs($admin);
            $this->assertFalse(session()->has(Impersonation::SESSION_IMPERSONATOR_ID), 'Target '.$target->email.' seharusnya ditolak.');
        }
    }

    #[Test]
    public function tc_acc_35_ganti_langsung_ke_peran_lain_diperiksa_terhadap_admin_asli(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff');
        $driver = $this->makeUser('driver');

        $this->actingAs($admin)->post($this->tenantUrl('/impersonate/'.$staf->id));
        $this->assertAuthenticatedAs($staf);

        // Staf tidak punya user.impersonate, tetapi Admin asli punya.
        $this->get($this->tenantUrl('/impersonate'))->assertOk();
        $this->post($this->tenantUrl('/impersonate/'.$driver->id))->assertRedirect($this->tenantUrl('/'));

        $this->assertAuthenticatedAs($driver);
        $this->assertSame($admin->id, session(Impersonation::SESSION_IMPERSONATOR_ID));

        $this->post($this->tenantUrl('/impersonate/leave'));
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function tc_acc_36_audit_log_mencatat_admin_asli(): void
    {
        $admin = $this->makeUser('company_admin', attributes: ['name' => 'Rina Admin']);
        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($admin)->post($this->tenantUrl('/impersonate/'.$staf->id));
        $this->put($this->tenantUrl('/profile'), ['name' => 'Staf Diubah Saat Demo'])->assertRedirect();

        $log = Activity::query()->where('subject_type', $staf->getMorphClass())->where('subject_id', $staf->id)
            ->where('event', 'updated')->latest('id')->firstOrFail();

        $this->assertSame($admin->id, $log->properties['impersonated_by']['id']);
        $this->assertSame('Rina Admin', $log->properties['impersonated_by']['name']);

        $mulai = Activity::query()->where('description', 'Admin Company masuk sebagai pengguna ini')
            ->where('subject_id', $staf->id)->firstOrFail();
        $this->assertSame($admin->id, (int) $mulai->causer_id);
    }

    #[Test]
    public function tc_acc_37_sakelar_mati_menutup_fitur(): void
    {
        config(['access.impersonation.enabled' => false]);

        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($admin)->get($this->tenantUrl('/impersonate'))->assertNotFound();
        $this->actingAs($admin)->post($this->tenantUrl('/impersonate/'.$staf->id))->assertNotFound();
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function tc_acc_38_admin_asli_dinonaktifkan_kembali_berarti_keluar(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($admin)->post($this->tenantUrl('/impersonate/'.$staf->id));
        $admin->forceFill(['is_active' => false])->save();

        $this->post($this->tenantUrl('/impersonate/leave'))->assertRedirect($this->tenantUrl('/login'));
        $this->assertGuest('web');
    }

    #[Test]
    public function tc_acc_38b_transisi_tidak_lewat_get(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($admin)->get($this->tenantUrl('/impersonate/'.$staf->id))->assertMethodNotAllowed();
        $this->actingAs($admin)->get($this->tenantUrl('/impersonate/leave'))->assertMethodNotAllowed();
    }

    #[Test]
    public function tc_acc_38c_layar_pemilih_menampilkan_alur_dan_pengguna(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->makeUser('warehouse_head', attributes: ['name' => 'Andi Kepala']);
        $staf = $this->makeUser('warehouse_staff', attributes: ['name' => 'Dedi Staf']);
        $driver = $this->makeUser('driver', attributes: ['name' => 'Gani Driver']);

        // Email hanya tampil di kartu pengguna, jadi dipakai untuk menguji saringan.
        Livewire::actingAs($admin)->test(ImpersonationPicker::class)
            ->assertSee(__('Permintaan Material ke proyek'))
            ->assertSee('Andi Kepala')
            ->assertSee($staf->email)
            ->set('roleFilter', 'driver')
            ->assertSee($driver->email)
            ->assertDontSee($staf->email);

        $this->actingAs($admin)->get($this->tenantUrl('/impersonate'))->assertOk()->assertSee(__('Panduan alur demo'));
    }

    #[Test]
    public function tc_acc_38d_wakil_role_mengikuti_urutan_akun_dan_tidak_rangkap(): void
    {
        $admin = $this->makeUser('company_admin');
        // Seperti akun demo: Kepala Gudang CKG juga staf BKS, dibuat sebelum Kepala Gudang BKS.
        $ganda = $this->makeUser('warehouse_head', attributes: ['name' => 'Andi Kepala']);
        $this->assignRole($ganda, 'warehouse_staff');
        $this->makeUser('warehouse_head', attributes: ['name' => 'Sari Kepala']);
        $staf = $this->makeUser('warehouse_staff', attributes: ['name' => 'Dedi Staf']);

        $perRole = DemoFlows::perRole(DemoFlows::candidates($admin)->whereNull('reason')->values());

        $this->assertTrue($perRole['warehouse_head']->is($ganda), 'Kepala Gudang = akun pertama.');
        $this->assertTrue($perRole['warehouse_staff']->is($staf), 'Staf = staf murni, bukan Kepala Gudang yang sudah mewakili.');
        $this->assertArrayNotHasKey('company_admin', $perRole);
        $this->assertInstanceOf(User::class, $perRole['warehouse_head']);

        $this->assertSame('LP', DemoFlows::initials('Lina (PT Klien Satu)'));
        $this->assertSame('RA', DemoFlows::initials('Rina Admin'));
    }
}
