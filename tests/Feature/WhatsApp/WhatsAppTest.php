<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Models\ApprovalDecision;
use App\Domain\Approval\Models\ApprovalToken;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Support\Notifier;
use App\Domain\Platform\Models\FeatureFlag;
use App\Domain\Platform\Models\Plan;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\IssueDeliveryToken;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\WhatsApp\Actions\VerifyWhatsAppNumber;
use App\Domain\WhatsApp\Livewire\WhatsAppCompanySettings;
use App\Domain\WhatsApp\Livewire\WhatsAppNumber;
use App\Domain\WhatsApp\Models\WaMessageLog;
use App\Domain\WhatsApp\Support\WhatsAppChannel;
use App\Domain\WhatsApp\Support\WhatsAppNotifier;
use App\Domain\WhatsApp\Transport\CloudTransport;
use App\Domain\WhatsApp\Transport\WhatsAppNotSent;
use App\Domain\WhatsApp\Transport\WhatsAppTransport;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-WA-01 s.d. TC-WA-11 — WhatsApp Fase 2a (31-whatsapp): transport Cloud API,
 * kuota, verifikasi nomor, notifikasi langsung & ringkasan, approval bertombol,
 * webhook bertanda tangan, status pesan, layar, OTP bukti terima lewat WA.
 */
class WhatsAppTest extends TenantTestCase
{
    use ApprovalScenario;

    private RecordingTransport $wa;

    private const RAHASIA = 'rahasia-app-uji';

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanSkenario();

        $this->wa = new RecordingTransport;
        $this->app->instance(WhatsAppTransport::class, $this->wa);
        config(['wms.whatsapp.app_secret' => self::RAHASIA, 'wms.whatsapp.driver' => 'cloud']);
        $this->fiturCompany(true);
    }

    private function fiturCompany(bool $nyala): void
    {
        FeatureFlag::query()->updateOrCreate(['company_id' => $this->company->getTenantKey(), 'key' => 'whatsapp'], ['enabled' => $nyala]);
    }

    private function terverifikasi(string $role, string $nomor): User
    {
        return $this->makeUser($role, attributes: ['phone' => $nomor, 'phone_verified_at' => now()]);
    }

    /** @param  array<string, mixed>  $value */
    private function webhook(array $value, ?string $rahasia = self::RAHASIA)
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => $value]]]]]);
        $tanda = 'sha256='.hash_hmac('sha256', $body, (string) $rahasia);

        return $this->call('POST', 'http://wms.test/api/webhooks/whatsapp', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $tanda, 'CONTENT_TYPE' => 'application/json'], $body);
    }

    /** @return array<string, mixed> */
    private function tombol(string $dari, string $payload, string $id): array
    {
        return ['messages' => [['id' => $id, 'from' => $dari, 'type' => 'button', 'button' => ['payload' => $payload, 'text' => 'Setujui']]]];
    }

    private function payloadTerakhir(string $aksi): string
    {
        $pesan = collect($this->wa->terkirim)->last(fn ($m) => ($m['template']['name'] ?? null) === 'wms_approval');
        $tombol = collect($pesan['template']['components'])->firstWhere(fn ($k) => ($k['sub_type'] ?? null) === 'quick_reply' && $k['index'] === ($aksi === 'A' ? '0' : '1'));

        return $tombol['parameters'][0]['payload'];
    }

    private function sjDikirim(): Shipment
    {
        $req = $this->ajukanReq($this->pemohon()); // tanpa aturan → langsung disetujui (TC-APR-01)
        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($req->refresh(), $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);
        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet', 'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'WA'])->id,
            'driver_name' => 'Gani', 'driver_phone' => '081200000008',
        ], $staf);

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
    }

    #[Test]
    public function tc_wa_01_cloud_api_log_dan_kuota(): void
    {
        Http::fake([
            'graph.facebook.com/v23.0/PNID/messages' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.OK1']]])
                ->push(['error' => ['message' => 'Template tidak ada']], 400),
        ]);
        $cloud = new CloudTransport(['token' => 'TOKEN', 'phone_number_id' => 'PNID', 'graph_version' => 'v23.0']);

        $this->assertSame('wamid.OK1', $cloud->send(['to' => '6281234567890', 'type' => 'text', 'text' => ['body' => 'hai']]));
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer TOKEN') && $r['messaging_product'] === 'whatsapp' && $r['to'] === '6281234567890');
        $this->expectExceptionObject(new WhatsAppNotSent('WhatsApp menolak pesan: Template tidak ada'));

        try {
            $cloud->send(['to' => '6281234567890', 'type' => 'text', 'text' => ['body' => 'hai']]);
        } finally {
            $this->assertFalse((new CloudTransport(['token' => '']))->available());

            // Kanal: template tercatat di log pusat; kuota 1 → kiriman kedua ditolak + Admin Company diberi tahu.
            $plan = Plan::create(['code' => 'WAUJI', 'name' => 'Paket uji WA', 'monthly_price' => 0, 'trial_days' => 14, 'wa_quota' => 1, 'is_active' => true]);
            $this->company->forceFill(['plan_id' => $plan->id])->save();
            $this->company->unsetRelation('plan');
            $admin = $this->makeUser('company_admin');

            $kanal = app(WhatsAppChannel::class);
            $id = $kanal->template('6281234567890', 'notification', ["A\nB", 'judul', 'isi'], [['type' => 'url', 'value' => 'test/requests']]);
            $log = WaMessageLog::query()->where('wa_message_id', $id)->sole();
            $this->assertSame(['utility_template', 'sent', 'wms_notifikasi'], [$log->category, $log->status, $log->template]);
            $this->assertSame('A · B', $this->wa->terkirim[0]['template']['components'][0]['parameters'][0]['text'], 'Tanpa baris baru (aturan Meta).');

            try {
                $kanal->template('6281234567890', 'notification', ['a', 'b', 'c']);
                $this->fail('Kuota habis seharusnya menolak.');
            } catch (WhatsAppNotSent) {
                $this->assertCount(1, $this->wa->terkirim);
            }
            $kanal->text('6281234567890', 'balasan layanan tidak dihitung kuota');
            $this->assertSame(1, Notification::query()->where('user_id', $admin->id)->where('type', WhatsAppChannel::EVENT_QUOTA)->where('channel', 'in_app')->count());
            try {
                $kanal->template('6281234567890', 'notification', ['a', 'b', 'c']);
            } catch (WhatsAppNotSent) {
            }
            $this->assertSame(1, Notification::query()->where('type', WhatsAppChannel::EVENT_QUOTA)->where('channel', 'in_app')->count(), 'Sekali per bulan.');
        }
    }

    #[Test]
    public function tc_wa_02_verifikasi_nomor(): void
    {
        $user = $this->makeUser('warehouse_staff', attributes: ['phone' => '0811111111']);
        $aksi = app(VerifyWhatsAppNumber::class);

        $aksi->sendCode($user, '0812-3456-7890');
        $this->assertSame('6281234567890', $user->refresh()->phone);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('wms_kode', $this->wa->terkirim[0]['template']['name']);
        $kode = $this->wa->terkirim[0]['template']['components'][0]['parameters'][0]['text'];

        try {
            $aksi->sendCode($user, '081234567890');
            $this->fail('Jeda 60 detik.');
        } catch (ValidationException) {
        }

        foreach (range(1, 5) as $_) {
            try {
                $aksi->confirm($user->refresh(), $kode === '000000' ? '111111' : '000000');
            } catch (ValidationException) {
            }
        }
        try {
            $aksi->confirm($user->refresh(), $kode);
            $this->fail('Terkunci setelah 5 salah.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Terlalu banyak', $e->getMessage());
        }

        $this->travel(2)->minutes();
        $aksi->sendCode($user->refresh(), '081234567890');
        $kode = $this->wa->terkirim[1]['template']['components'][0]['parameters'][0]['text'];
        $aksi->confirm($user->refresh(), $kode);
        $this->assertNotNull($user->refresh()->phone_verified_at);

        // Nomor diubah Admin Company → verifikasi hilang (BR-WA-01).
        $user->forceFill(['phone' => '0819999999'])->save();
        $this->assertNull($user->refresh()->phone_verified_at);

        // Layar profil: kartu WhatsApp, fitur mati → pesan belum aktif.
        $this->actingAs($user)->get($this->tenantUrl('profile'))->assertOk()->assertSee(__('Kirim kode'));
        $this->fiturCompany(false);
        Livewire::test(WhatsAppNumber::class)->assertSee(__('WhatsApp belum aktif untuk company ini.', []), false);
    }

    #[Test]
    public function tc_wa_03_notifikasi_langsung_tiga_lapis(): void
    {
        $user = $this->terverifikasi('warehouse_staff', '6281200000001');
        $kirim = fn () => app(Notifier::class)->send($user, 'discrepancy.opened', 'Selisih baru DSC/1', 'Kurang 2', '/discrepancies');

        $kirim();
        $this->assertSame([], $this->wa->terkirim, 'Kejadian belum diizinkan company.');

        CompanySetting::put('wa_events', ['discrepancy.opened' => 'instant']);
        $kirim();
        $this->assertCount(1, $this->wa->terkirim);
        $pesan = $this->wa->terkirim[0];
        $this->assertSame(['6281200000001', 'wms_notifikasi'], [$pesan['to'], $pesan['template']['name']]);
        $this->assertSame('test/discrepancies', $pesan['template']['components'][1]['parameters'][0]['text']);
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('channel', 'whatsapp')->count());

        NotificationPreference::query()->updateOrInsert(['user_id' => $user->id, 'event_key' => 'discrepancy.opened'], ['in_app' => true, 'email' => false, 'whatsapp' => false]);
        $kirim();
        $this->assertCount(1, $this->wa->terkirim, 'User mematikan WA.');

        NotificationPreference::query()->where('user_id', $user->id)->update(['whatsapp' => true]);
        $this->fiturCompany(false);
        $kirim();
        $this->assertCount(1, $this->wa->terkirim, 'Fitur company mati.');

        $this->fiturCompany(true);
        $user->forceFill(['phone_verified_at' => null])->save();
        $kirim();
        $this->assertCount(1, $this->wa->terkirim, 'Nomor belum terverifikasi.');
    }

    #[Test]
    public function tc_wa_04_ringkasan_harian(): void
    {
        $user = $this->terverifikasi('warehouse_staff', '6281200000002');
        CompanySetting::put('wa_events', ['discrepancy.opened' => 'digest']);

        foreach ([1, 2, 3] as $n) {
            app(Notifier::class)->send($user, 'discrepancy.opened', 'Selisih '.$n, null, '/discrepancies', 'delivery_discrepancy', $n);
        }
        $this->assertSame([], $this->wa->terkirim, 'Mode ringkasan: tidak ada pesan langsung.');

        $this->assertSame(1, app(WhatsAppNotifier::class)->digest());
        $pesan = $this->wa->terkirim[0];
        $this->assertSame('wms_ringkasan', $pesan['template']['name']);
        $this->assertSame('3', $pesan['template']['components'][0]['parameters'][1]['text']);
        $this->assertSame(0, app(WhatsAppNotifier::class)->digest(), 'Tidak ada yang baru sejak ringkasan terakhir.');

        $this->artisan('notifications:daily')->expectsOutputToContain('ringkasan WhatsApp')->assertSuccessful();
    }

    #[Test]
    public function tc_wa_05_sampai_08_approval_bertombol_dan_webhook(): void
    {
        $kepala = $this->terverifikasi('warehouse_head', '6281300000001');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala, extra: ['channel' => 'both'])]);
        CompanySetting::put('wa_events', ['approval.task_assigned' => 'instant']);

        $req = $this->ajukanReq($this->pemohon());

        // TC-WA-05: satu pesan bertombol, tanpa wms_notifikasi ganda.
        $this->assertCount(1, $this->wa->terkirim);
        $this->assertSame('wms_approval', $this->wa->terkirim[0]['template']['name']);
        $token = ApprovalToken::query()->sole();
        $this->assertNotNull($token->wa_message_id);
        $setuju = $this->payloadTerakhir('A');
        $this->assertSame('APR|'.$this->company->getTenantKey().'|'.$token->token.'|A', $setuju);

        // TC-WA-07: tanda tangan salah → 403; nomor lain → tidak diputus.
        $this->webhook($this->tombol('6281300000001', $setuju, 'wamid.IN0'), 'salah')->assertForbidden();
        $this->webhook($this->tombol('6289999999999', $setuju, 'wamid.IN1'))->assertOk()->assertJson(['decisions' => 0]);
        $this->assertSame(ApprovalSnapshotStatus::Pending, $this->snapshotReq($req)->status);
        $this->assertStringContainsString('tidak terdaftar', end($this->wa->terkirim)['text']['body']);

        // TC-WA-08: Tolak → tautan web, tidak diputus.
        $this->webhook($this->tombol('6281300000001', $this->payloadTerakhir('R'), 'wamid.IN2'))->assertOk();
        $this->assertSame(ApprovalSnapshotStatus::Pending, $this->snapshotReq($req)->status);
        $this->assertStringContainsString('alasan penolakan', end($this->wa->terkirim)['text']['body']);

        // TC-WA-06: Setujui dari nomor approver.
        $this->webhook($this->tombol('+62 813-0000-0001', $setuju, 'wamid.IN3'))->assertOk()->assertJson(['decisions' => 1]);
        $this->assertSame(ApprovalSnapshotStatus::Approved, $this->snapshotReq($req)->status);
        $keputusan = ApprovalDecision::query()->where('channel', 'whatsapp')->sole();
        $this->assertSame(['6281300000001', 'wamid.IN3', (int) $token->id], [$keputusan->wa_from_number, $keputusan->wa_message_id, (int) $keputusan->approval_token_id]);
        $this->assertNotNull($token->refresh()->used_at);
        $this->assertStringContainsString('disetujui', end($this->wa->terkirim)['text']['body']);

        // TC-WA-07: pesan ganda diabaikan; token terpakai → balasan tidak berlaku.
        $jumlah = count($this->wa->terkirim);
        $this->webhook($this->tombol('6281300000001', $setuju, 'wamid.IN3'))->assertOk()->assertJson(['decisions' => 0]);
        $this->assertCount($jumlah, $this->wa->terkirim, 'Pesan ganda tidak dibalas.');
        $this->webhook($this->tombol('6281300000001', $setuju, 'wamid.IN4'))->assertOk();
        $this->assertStringContainsString('tidak berlaku', end($this->wa->terkirim)['text']['body']);

        // Balasan konfirmasi bisa dimatikan.
        CompanySetting::put('wa_confirm_reply', false);
        $req2 = $this->ajukanReq($this->pemohon());
        $jumlah = count($this->wa->terkirim);
        $this->webhook($this->tombol('6281300000001', $this->payloadTerakhir('A'), 'wamid.IN5'))->assertOk()->assertJson(['decisions' => 1]);
        $this->assertSame(ApprovalSnapshotStatus::Approved, $this->snapshotReq($req2)->status);
        $this->assertCount($jumlah, $this->wa->terkirim);
    }

    #[Test]
    public function tc_wa_09_status_dan_verifikasi_webhook(): void
    {
        $id = app(WhatsAppChannel::class)->text('6281234567890', 'halo');

        $this->webhook(['statuses' => [['id' => $id, 'status' => 'delivered']]])->assertOk()->assertJson(['statuses' => 1]);
        $this->webhook(['statuses' => [['id' => $id, 'status' => 'read']]])->assertOk();
        $this->webhook(['statuses' => [['id' => $id, 'status' => 'delivered']]])->assertOk()->assertJson(['statuses' => 0]);
        $this->assertSame('read', WaMessageLog::query()->where('wa_message_id', $id)->value('status'));

        config(['wms.whatsapp.verify_token' => 'VERIF']);
        $this->get('http://wms.test/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=VERIF&hub_challenge=123')->assertOk()->assertSeeText('123');
        $this->get('http://wms.test/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=X&hub_challenge=123')->assertForbidden();
    }

    #[Test]
    public function tc_wa_10_layar(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin);

        Livewire::test(WhatsAppCompanySettings::class)
            ->assertSee(__('Pemakaian bulan ini'))
            ->set('events.discrepancy__opened', 'instant')->set('events.asset__overdue', 'digest')->set('confirmReply', false)
            ->call('simpan');
        $this->assertSame(['asset.overdue' => 'digest', 'discrepancy.opened' => 'instant'], CompanySetting::get('wa_events'));
        $this->assertFalse((bool) CompanySetting::get('wa_confirm_reply'));

        $this->get($this->tenantUrl('settings/company'))->assertOk()->assertSee('whatsapp-company', false);
        $this->get($this->tenantUrl('notifications/preferences'))->assertOk()->assertSee(__('Verifikasi nomor WhatsApp Anda di profil'));

        $this->fiturCompany(false);
        $this->get($this->tenantUrl('notifications/preferences'))->assertOk()->assertDontSee(__('Verifikasi nomor WhatsApp Anda di profil'));

        // Pengalih tombol URL ke subdomain company; path aneh ditolak.
        $this->get('http://wms.test/buka/test/requests/5')->assertRedirect('http://test.wms.test/requests/5');
        $this->get('http://wms.test/buka/tidakada/requests')->assertNotFound();
        $this->get('http://wms.test/buka/test/..%2F..%2Fetc')->assertNotFound();
    }

    #[Test]
    public function tc_wa_11_otp_bukti_terima_lewat_whatsapp(): void
    {
        FeatureSetting::toggle('otp_auto', true);
        $sj = $this->sjDikirim();
        $hasil = app(IssueDeliveryToken::class)->handle($sj, '0812-7777-8888');

        $this->assertTrue($hasil['sent']);
        $this->assertSame('whatsapp', $hasil['via']);
        $this->assertNull($hasil['otp']);
        $pesan = end($this->wa->terkirim);
        $this->assertSame(['6281277778888', 'wms_kode'], [$pesan['to'], $pesan['template']['name']]);
        $kode = $pesan['template']['components'][0]['parameters'][0]['text'];
        $this->assertTrue(Hash::check($kode, (string) $hasil['token']->refresh()->otp_hash));
    }
}

/** Transport palsu: mencatat pesan dan mengembalikan id. */
class RecordingTransport implements WhatsAppTransport
{
    /** @var list<array<string, mixed>> */
    public array $terkirim = [];

    public function available(): bool
    {
        return true;
    }

    public function send(array $message): string
    {
        $this->terkirim[] = $message;

        return 'wamid.uji.'.count($this->terkirim).'.'.uniqid();
    }
}
