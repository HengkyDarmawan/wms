<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Platform\Models\FeatureFlag;
use App\Domain\WhatsApp\Livewire\WhatsAppSimulator;
use App\Domain\WhatsApp\Models\WaMessageLog;
use App\Domain\WhatsApp\Transport\LogTransport;
use App\Domain\WhatsApp\Transport\WhatsAppTransport;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-WA-12 s.d. TC-WA-14 — Kotak pesan WhatsApp simulasi (A-435): mode demo
 * tanpa Meta; pesan & tombol tersimpan hanya untuk driver `log`, tombol
 * Setujui memutus approval lewat jalur WhatsApp yang sama.
 */
class WhatsAppSimulatorTest extends TenantTestCase
{
    use ApprovalScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanSkenario();
        config(['wms.whatsapp.driver' => 'log']);
        $this->app->instance(WhatsAppTransport::class, new LogTransport);
        FeatureFlag::query()->updateOrCreate(['company_id' => $this->company->getTenantKey(), 'key' => 'whatsapp'], ['enabled' => true]);
    }

    #[Test]
    public function tc_wa_12_13_tekan_setujui_di_simulator_memutus_approval(): void
    {
        $kepala = $this->makeUser('warehouse_head', attributes: ['phone' => '6281300000009', 'phone_verified_at' => now()]);
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala, extra: ['channel' => 'both'])]);
        CompanySetting::put('wa_events', ['approval.task_assigned' => 'instant']);
        $req = $this->ajukanReq($this->pemohon());

        $log = WaMessageLog::query()->where('direction', 'out')->where('template', 'wms_approval')->sole();
        $this->assertNotEmpty($log->payload['pesan']['template']['components'] ?? [], 'Driver log menyimpan isi pesan untuk simulasi.');

        Livewire::actingAs($this->makeUser('company_admin'))->test(WhatsAppSimulator::class)
            ->assertSee($kepala->name)
            ->assertSee('Setujui')->assertSee('Tolak')
            ->call('tekan', $log->id, 0)
            ->assertSet('hasil', 'Keputusan tercatat lewat WhatsApp (simulasi).');

        $this->assertSame(ApprovalSnapshotStatus::Approved, $this->snapshotReq($req)->status);
        $this->assertTrue(WaMessageLog::query()->where('direction', 'in')->exists(), 'Balasan tombol tercatat sebagai pesan masuk.');
    }

    #[Test]
    public function tc_wa_14_simulator_hanya_untuk_driver_log_dan_admin(): void
    {
        Livewire::actingAs($this->makeUser('warehouse_staff'))->test(WhatsAppSimulator::class)->assertForbidden();

        config(['wms.whatsapp.driver' => 'cloud']);
        Livewire::actingAs($this->makeUser('company_admin'))->test(WhatsAppSimulator::class)->assertNotFound();
    }
}
