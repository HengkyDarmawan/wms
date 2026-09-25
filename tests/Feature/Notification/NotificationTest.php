<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Notifications\EventMail;
use App\Domain\Notification\Support\Notifier;
use App\Domain\PurchaseRequest\Actions\ApprovePurchaseRequest;
use App\Domain\PurchaseRequest\Actions\CreatePurchaseRequest;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-NTF-01 s.d. TC-NTF-06 — notifikasi in-app & email (Blueprint §10, A-189).
 */
class NotificationTest extends TenantTestCase
{
    use ApprovalFixtures;
    use OutboundChain;
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        NotificationFacade::fake();
    }

    private function milik(User $user, string $event): int
    {
        return Notification::query()->where('user_id', $user->id)->where('type', $event)->where('channel', 'in_app')->count();
    }

    #[Test]
    public function tc_ntf_01_tugas_approval_dan_keputusan_sampai_ke_orangnya(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::PurchaseRequest, [$this->lapisUser($kepala)]);

        $staf = $this->makeUser('warehouse_staff');
        $prq = app(CreatePurchaseRequest::class)->handle(['warehouse_id' => $this->gudang->id], [['item_id' => $this->baut->id, 'qty_base' => 5]], $staf);

        $this->assertSame(1, $this->milik($kepala, 'approval.task_assigned'));
        // Email tugas approval menyala secara bawaan.
        NotificationFacade::assertSentTo($kepala, EventMail::class);

        app(ApprovePurchaseRequest::class)->approve($prq, $kepala);
        $this->assertSame(1, $this->milik($staf, 'approval.decided'));
        NotificationFacade::assertNotSentTo($staf, EventMail::class);

        // Penindak Lanjut PR diberi tahu PRQ siap dipesan.
        $pr = $this->makeUser('pr_follow_up');
        $prq2 = app(CreatePurchaseRequest::class)->handle(['warehouse_id' => $this->gudang->id], [['item_id' => $this->kabel->id, 'qty_base' => 1]], $staf);
        app(ApprovePurchaseRequest::class)->approve($prq2, $kepala);
        $this->assertSame(1, $this->milik($pr, 'purchase_request.approved'), 'Dibuat setelah PRQ pertama disetujui.');
    }

    #[Test]
    public function tc_ntf_02_pemohon_diberi_tahu_barang_diterima_dan_lonceng_bekerja(): void
    {
        $grn = app(CompleteGoodsReceipt::class)->handle($this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 10]]), $this->makeUser());
        app(CompletePutaway::class)->handle($grn->putawayTasks()->sole(), [], $this->makeUser());
        $proyek = $this->makeProject();
        $req = $this->reqDisetujui($proyek, $this->gudang, $this->baut, 10);
        $pemohon = User::query()->findOrFail($req->requester_id);
        $this->buktiTerima($this->sjBerangkat($this->pckSelesai($req), ['destination_type' => 'project_client', 'destination_project_id' => $proyek->id]), 8, 2);

        $n = Notification::query()->where('user_id', $pemohon->id)->where('type', 'delivery.received')->sole();
        $this->assertSame('/requests/'.$req->id, $n->url);

        // DSC (kurang 2) ke penyelesai selisih di gudang itu.
        $kepala = $this->makeUser('warehouse_head');
        $this->assertSame(0, $this->milik($kepala, 'discrepancy.opened'), 'Kepala baru dibuat setelah DSC terbuka.');

        $this->actingAs($pemohon)->get($this->tenantUrl('/'))->assertOk()->assertSee(__('Notifikasi'))->assertSee($n->title);
        $this->actingAs($pemohon)->post($this->tenantUrl('notifications/'.$n->id.'/open'))->assertRedirect('/requests/'.$req->id);
        $this->assertNotNull($n->refresh()->read_at);

        // Notifikasi orang lain tidak bisa dibuka.
        $this->actingAs($this->makeUser('warehouse_staff'))->post($this->tenantUrl('notifications/'.$n->id.'/open'))->assertNotFound();
    }

    #[Test]
    public function tc_ntf_03_preferensi_mematikan_lonceng_menyalakan_email(): void
    {
        $user = $this->makeUser('warehouse_head');

        $this->actingAs($user)->get($this->tenantUrl('notifications/preferences'))->assertOk()->assertSee(__('Tugas approval baru untuk saya'))
            ->assertDontSee(__('Tagihan & status langganan company'));
        // Kejadian tagihan hanya bagi pemegang `billing.view` (A-202).
        $this->actingAs($this->makeUser('company_admin'))->get($this->tenantUrl('notifications/preferences'))->assertSee(__('Tagihan & status langganan company'));
        $this->actingAs($user)->post($this->tenantUrl('notifications/preferences'), [
            'prefs' => ['request.under_review' => ['email' => 1]],
        ])->assertSessionHasNoErrors();

        $pref = NotificationPreference::query()->where('user_id', $user->id)->where('event_key', 'request.under_review')->sole();
        $this->assertFalse($pref->in_app);
        $this->assertTrue($pref->email);

        // REQ tanpa gudang sumber → tinjauan: email terkirim, lonceng tidak.
        $proyek = $this->makeProject();
        $pemohon = $this->makeUser('internal_requester');
        $req = app(SaveRequest::class)->handle(null, ['project_id' => $proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $this->baut->id, 'qty_base' => 3]], $pemohon);
        app(SubmitRequest::class)->handle($req, $pemohon);

        $this->assertSame(0, $this->milik($user, 'request.under_review'));
        NotificationFacade::assertSentTo($user, EventMail::class);
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->where('channel', 'email')->count());
    }

    #[Test]
    public function tc_ntf_04_pengingat_yang_sama_tidak_digandakan(): void
    {
        $user = $this->makeUser('warehouse_head');
        $notif = app(Notifier::class);

        $notif->send($user, 'asset.overdue', 'Aset X lewat jatuh tempo', null, '/assets/1', 'serial', 1);
        $notif->send($user, 'asset.overdue', 'Aset X lewat jatuh tempo', null, '/assets/1', 'serial', 1);
        $this->assertSame(1, $this->milik($user, 'asset.overdue'));

        // Pelaku sendiri dan user nonaktif tidak menerima.
        $nonaktif = $this->makeUser('warehouse_head');
        $nonaktif->forceFill(['is_active' => false])->save();
        $this->assertSame(0, $notif->send([$user, $nonaktif], 'approval.decided', 'X', null, null, null, null, [], $user));

        $this->actingAs($user)->post($this->tenantUrl('notifications/read-all'))->assertRedirect();
        $this->assertSame(0, Notification::query()->where('user_id', $user->id)->whereNull('read_at')->count());
        $this->artisan('notifications:daily')->assertSuccessful();
    }

    #[Test]
    public function tc_ntf_06_tugas_eskalasi_memberi_tahu_approver_baru(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $admin = $this->makeUser('company_admin');
        $this->aturan(ApprovalDocumentType::PurchaseRequest, [$this->lapisUser($kepala)]);
        $prq = app(CreatePurchaseRequest::class)->handle(['warehouse_id' => $this->gudang->id], [['item_id' => $this->baut->id, 'qty_base' => 5]], $this->makeUser('warehouse_staff'));

        $tugas = ApprovalTask::query()->where('approver_user_id', $kepala->id)->open()->sole();
        $baru = app(ApprovalEngine::class)->escalate($tugas, 'manual', null);

        $notif = Notification::query()->where('user_id', $baru->approver_user_id)->where('type', 'approval.task_assigned')->inApp()->sole();
        $this->assertStringContainsString('Dieskalasi', (string) $notif->body);
        $this->assertStringContainsString((string) $prq->number, $notif->title);
    }

    #[Test]
    public function tc_ntf_05_email_gagal_tidak_membatalkan_approval(): void
    {
        // SMTP mati: pengiriman email melempar galat, tetapi aksi bisnis tetap jadi.
        $this->app->instance(ChannelManager::class, new class implements Dispatcher
        {
            public function send($notifiables, $notification): void
            {
                throw new \RuntimeException('SMTP mati');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null): void
            {
                throw new \RuntimeException('SMTP mati');
            }
        });

        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::PurchaseRequest, [$this->lapisUser($kepala)]);
        $staf = $this->makeUser('warehouse_staff');

        $prq = app(CreatePurchaseRequest::class)->handle(['warehouse_id' => $this->gudang->id], [['item_id' => $this->baut->id, 'qty_base' => 5]], $staf);

        $this->assertNotNull($prq->fresh(), 'PRQ tetap tersimpan.');
        $this->assertSame(1, $this->milik($kepala, 'approval.task_assigned'));
        $this->assertSame(0, Notification::query()->where('user_id', $kepala->id)->where('channel', 'email')->count(), 'Email gagal tidak dicatat terkirim.');
    }
}
