<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\ChangeProjectStatus;
use App\Domain\Master\Actions\DeactivateClient;
use App\Domain\Master\Actions\SaveClient;
use App\Domain\Master\Actions\SaveProject;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Project;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-01 s.d. TC-MST-06 — klien dan proyek
 * (BR-MST-01, BR-MST-04, BR-MST-05, BR-PRJ-01, BR-PRJ-02).
 */
class ClientProjectTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_01_kode_klien_disimpan_huruf_besar_dan_unik(): void
    {
        $klien = app(SaveClient::class)->handle(null, [
            'code' => 'kl-uji 1',
            'name' => 'PT Klien Uji',
        ]);

        $this->assertSame('KL-UJI-1', $klien->code);
        $this->assertTrue($klien->is_active);

        // Kode yang sama ditolak.
        try {
            app(SaveClient::class)->handle(null, ['code' => 'KL-UJI-1', 'name' => 'Klien Kembar']);
            $this->fail('Kode klien kembar seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }

        // BR-MST-01: kode tidak bisa diubah setelah dibuat.
        try {
            app(SaveClient::class)->handle($klien, ['code' => 'KL-LAIN', 'name' => 'PT Klien Uji']);
            $this->fail('Kode klien seharusnya terkunci.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }
    }

    #[Test]
    public function tc_mst_02_klien_dengan_proyek_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $klien = $this->makeClient();

        Project::create([
            'code' => 'PRJ-AKTIF',
            'name' => 'Proyek Aktif',
            'client_id' => $klien->id,
            'status' => ProjectStatus::Active,
        ]);

        try {
            app(DeactivateClient::class)->handle($klien, 'PROJECT_HOLD');
            $this->fail('Klien dengan proyek aktif seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-05', $e->rule);
            $this->assertStringContainsString('proyek aktif', $e->getMessage());
        }

        $this->assertTrue($klien->refresh()->is_active);
    }

    #[Test]
    public function tc_mst_02b_alasan_wajib_keterangan_opsional(): void
    {
        $klien = $this->makeClient();

        // BR-GEN-11: alasan wajib.
        try {
            app(DeactivateClient::class)->handle($klien, '');
            $this->fail('Alasan kosong seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        // Keterangan boleh kosong.
        $klien = app(DeactivateClient::class)->handle($klien, 'NOT_NEEDED');

        $this->assertFalse($klien->is_active);
    }

    #[Test]
    public function tc_mst_03_proyek_baru_berstatus_aktif(): void
    {
        $klien = $this->makeClient();

        $proyek = app(SaveProject::class)->handle(null, [
            'code' => 'prj-uji',
            'name' => 'Proyek Uji Baru',
            'client_id' => $klien->id,
        ]);

        $this->assertSame('PRJ-UJI', $proyek->code);
        $this->assertSame(ProjectStatus::Active, $proyek->status);
        $this->assertFalse($proyek->is_internal);
        $this->assertTrue($proyek->acceptsDocuments());
    }

    #[Test]
    public function tc_mst_04_proyek_internal_tidak_boleh_punya_klien(): void
    {
        $klien = $this->makeClient();

        try {
            app(SaveProject::class)->handle(null, [
                'code' => 'PRJ-INT-UJI',
                'name' => 'Proyek Internal Uji',
                'client_id' => $klien->id,
                'is_internal' => true,
            ]);
            $this->fail('Proyek Internal berklien seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-04', $e->rule);
            $this->assertArrayHasKey('client_id', $e->fieldErrors);
        }

        // Sebaliknya: proyek biasa wajib punya klien.
        try {
            app(SaveProject::class)->handle(null, ['code' => 'PRJ-TANPA', 'name' => 'Tanpa Klien']);
            $this->fail('Proyek non-internal tanpa klien seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-04', $e->rule);
        }

        // Proyek Internal tanpa klien tersimpan (A-06).
        $internal = app(SaveProject::class)->handle(null, [
            'code' => 'PRJ-INT-UJI',
            'name' => 'Proyek Internal Uji',
            'is_internal' => true,
        ]);

        $this->assertNull($internal->client_id);
        $this->assertTrue($internal->is_internal);
    }

    #[Test]
    public function tc_mst_05_proyek_aktif_bisa_ditutup(): void
    {
        $proyek = $this->makeProject();

        $proyek = app(ChangeProjectStatus::class)
            ->handle($proyek, ProjectStatus::Closed, 'NOT_NEEDED', 'Pekerjaan selesai');

        $this->assertSame(ProjectStatus::Closed, $proyek->status);
        $this->assertNotNull($proyek->closed_at);
        $this->assertFalse($proyek->acceptsDocuments());
    }

    #[Test]
    public function tc_mst_05b_proyek_internal_tidak_bisa_ditutup(): void
    {
        $internal = $this->makeProject(['is_internal' => true]);

        try {
            app(ChangeProjectStatus::class)->handle($internal, ProjectStatus::Closed, 'NOT_NEEDED');
            $this->fail('Proyek Internal seharusnya tidak bisa ditutup.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-04', $e->rule);
        }

        $this->assertSame([], app(ChangeProjectStatus::class)->availableTargets($internal));
    }

    #[Test]
    public function tc_mst_06_proyek_tertutup_bisa_diarsipkan_tapi_tidak_dibatalkan(): void
    {
        $aksi = app(ChangeProjectStatus::class);
        $proyek = $this->makeProject();

        $proyek = $aksi->handle($proyek, ProjectStatus::Closed, 'NOT_NEEDED');

        // Tutup → batal bukan transisi yang sah.
        try {
            $aksi->handle($proyek, ProjectStatus::Cancelled, 'NOT_NEEDED');
            $this->fail('Transisi closed → cancelled seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-PRJ-01', $e->rule);
        }

        $proyek = $aksi->handle($proyek, ProjectStatus::Archived, 'NOT_NEEDED');

        $this->assertSame(ProjectStatus::Archived, $proyek->status);
        $this->assertSame([], $aksi->availableTargets($proyek));
    }

    #[Test]
    public function tc_mst_06b_klien_nonaktif_tidak_bisa_dipakai_proyek_baru(): void
    {
        $klien = $this->makeClient();
        app(DeactivateClient::class)->handle($klien, 'NOT_NEEDED');

        try {
            app(SaveProject::class)->handle(null, [
                'code' => 'PRJ-NONAKTIF',
                'name' => 'Proyek Klien Nonaktif',
                'client_id' => $klien->id,
            ]);
            $this->fail('Klien nonaktif seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-05', $e->rule);
        }

        $this->assertSame(0, Client::query()->active()->where('id', $klien->id)->count());
    }
}
