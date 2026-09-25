<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Actions\ChangeProjectStatus;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Models\Uom;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Notifications\EventMail;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Actions\CancelRequestLine;
use App\Domain\Request\Actions\ReviewRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Actions\LockStockPeriod;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\TenantTestCase;

/**
 * TC-NTF-07 s.d. TC-NTF-11 — notifikasi §8 modul Master, Stock, Request, dan
 * Aset: kejadian dari aksi dan pengingat harian `notifications:daily`
 * (Blueprint §10, A-189, A-233).
 */
class ModuleNotificationTest extends TenantTestCase
{
    use ApprovalFixtures;

    private Project $proyek;

    private Warehouse $gudang;

    private Item $item;

    private Item $pengganti;

    private User $klien;

    private int $pcs;

    protected function setUp(): void
    {
        parent::setUp();
        NotificationFacade::fake();

        $this->proyek = $this->makeProject();
        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $this->pcs = (int) Uom::query()->where('code', 'PCS')->value('id');

        $this->item = Item::create(['code' => 'BAUT-M12', 'name' => 'Baut M12', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::None, 'base_uom_id' => $this->pcs]);
        $this->pengganti = Item::create(['code' => 'BAUT-M14', 'name' => 'Baut M14', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::None, 'base_uom_id' => $this->pcs]);

        $bin = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);

        foreach ([$this->item, $this->pengganti] as $item) {
            app(StockLedger::class)->post(new MovementRequest(item: $item, qtyBase: 100, toBinId: $bin->id));
        }

        $this->klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->proyek->client_id]);
    }

    private function milik(User $user, string $event): int
    {
        return Notification::query()->where('user_id', $user->id)->where('type', $event)->where('channel', 'in_app')->count();
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function reqKlien(array $lines = []): MaterialRequest
    {
        $req = app(SaveRequest::class)->handle(null, [
            'project_id' => $this->proyek->id,
            'required_date' => now()->addDays(3)->toDateString(),
        ], $lines === [] ? [['item_id' => $this->item->id, 'qty_base' => 10]] : $lines, $this->klien);

        return app(SubmitRequest::class)->handle($req, $this->klien);
    }

    private function reqDisetujui(User $staf): MaterialRequest
    {
        $req = $this->reqKlien();
        app(ReviewRequest::class)->setSource($req->openLines()->first(), (int) $this->gudang->id, 'stock', now()->addDays(5)->toDateString(), $staf);

        return app(ReviewRequest::class)->submitToApproval($req->refresh(), $staf);
    }

    private function alasanId(): int
    {
        return (int) ReasonCode::query()->value('id');
    }

    #[Test]
    public function tc_ntf_07_tinjauan_memberi_tahu_item_sementara_penggantian_janji_dan_penolakan(): void
    {
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head');
        $staf = $this->makeUser('warehouse_staff');
        $tinjau = app(ReviewRequest::class);

        $req = $this->reqKlien([
            ['non_catalog_text' => 'Baut khusus galvanis', 'qty_base' => 5],
            ['item_id' => $this->item->id, 'qty_base' => 10],
        ]);
        [$teks, $katalog] = [$req->lines()->whereNull('item_id')->sole(), $req->lines()->whereNotNull('item_id')->sole()];

        // Item sementara → Admin Company (pemegang item.create), bukan Kepala Gudang.
        $tinjau->mapToProvisionalItem($teks, 'BAUT-GLV', 'Baut galvanis', $this->pcs, $staf);
        $this->assertSame(1, $this->milik($admin, 'item.provisional_created'));
        $this->assertSame(0, $this->milik($kepala, 'item.provisional_created'));

        // Penggantian item → klien, tautan ke portal.
        $tinjau->mapLine($katalog, (int) $this->pengganti->id, $staf);
        $n = Notification::query()->where('user_id', $this->klien->id)->where('type', 'request.line_substituted')->sole();
        $this->assertSame(route('portal.requests.show', $req->id, false), $n->url);

        // Tanggal janji ditetapkan/berubah → pemohon; tanggal sama tidak memicu apa pun.
        $tinjau->setSource($katalog->refresh(), (int) $this->gudang->id, 'stock', now()->addDays(5)->toDateString(), $staf);
        $tinjau->setSource($katalog->refresh(), (int) $this->gudang->id, 'stock', now()->addDays(5)->toDateString(), $staf);
        $this->assertSame(1, $this->milik($this->klien, 'request.promise_changed'));

        // Ditolak saat tinjau → pemohon.
        $tinjau->reject($req->refresh(), $this->alasanId(), null, $staf);
        $this->assertSame(1, $this->milik($this->klien, 'request.decided'));
    }

    #[Test]
    public function tc_ntf_08_keputusan_approval_dan_pembatalan_baris_sampai_ke_pihak_yang_tepat(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $staf = $this->makeUser('warehouse_staff');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)]);

        $req = $this->reqDisetujui($staf);
        app(ApproveRequest::class)->handle($req->refresh(), $kepala);

        // Klien (pemohon) diberi tahu; staf pengaju sudah menerima approval.decided.
        $this->assertSame(1, $this->milik($this->klien, 'request.decided'));
        $this->assertSame(0, $this->milik($staf, 'request.decided'));
        $this->assertSame(1, $this->milik($staf, 'approval.decided'));

        // Permintaan batal baris → pemegang request.confirm_cancel di proyek.
        $baris = $req->refresh()->openLines()->first();
        app(CancelRequestLine::class)->request($baris, $this->alasanId(), $this->klien);
        $this->assertSame(1, $this->milik($kepala, 'request.line_cancel_requested'));
        $this->assertSame(1, $this->milik($staf, 'request.line_cancel_requested'));

        // Hasilnya kembali ke klien.
        app(CancelRequestLine::class)->refuse($baris->refresh(), 'Sudah dipicking', $staf);
        $this->assertSame(1, $this->milik($this->klien, 'request.line_cancel_decided'));
    }

    #[Test]
    public function tc_ntf_09_proyek_ditutup_dan_periode_dikunci(): void
    {
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head');
        $staf = $this->makeUser('warehouse_staff');
        $pic = $this->makeUser('internal_requester');
        $proyek = $this->makeProject(['pic_user_id' => $pic->id]);

        app(ChangeProjectStatus::class)->handle($proyek, ProjectStatus::Closed, 'SELESAI', null, $admin);
        $this->assertSame(1, $this->milik($pic, 'project.closed'));
        $this->assertSame(1, $this->milik($kepala, 'project.closed'));
        $this->assertSame(0, $this->milik($staf, 'project.closed'));

        $admin2 = $this->makeUser('company_admin');
        app(LockStockPeriod::class)->handle(now()->subDay()->toDateString(), null, $admin);
        $this->assertSame(1, $this->milik($kepala, 'stock.period_locked'));
        $this->assertSame(1, $this->milik($admin2, 'stock.period_locked'));
        $this->assertSame(0, $this->milik($admin, 'stock.period_locked'), 'Pelaku tidak diberi tahu.');
        $this->assertSame(0, $this->milik($staf, 'stock.period_locked'));
    }

    #[Test]
    public function tc_ntf_10_pengingat_harian_sla_reservasi_dan_aset(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $staf = $this->makeUser('warehouse_staff');

        // SLA tinjau: REQ klien menunggu tinjau lebih lama dari ambang (1 hari).
        $telat = $this->reqKlien();
        $telat->forceFill(['created_at' => now()->subDays(2)])->save();

        // Reservasi menggantung: REQ disetujui, reservasinya lewat 7 hari.
        $req = $this->reqDisetujui($staf);
        StockReservation::query()->forDocument('material_request', (int) $req->id)->update(['created_at' => now()->subDays(8)]);

        // Aset: sisa umur di bawah 20 % dan lewat jatuh tempo di proyek ber-PIC.
        $pic = $this->makeUser('internal_requester');
        $proyek = $this->makeProject(['pic_user_id' => $pic->id]);
        Serial::create(['item_id' => $this->item->id, 'serial_no' => 'TUA-01', 'acquired_at' => now()->subDays(900)->toDateString(), 'expected_life_days' => 1000]);
        Serial::create(['item_id' => $this->item->id, 'serial_no' => 'PINJAM-01', 'asset_state' => AssetState::OnLoan,
            'current_project_id' => $proyek->id, 'due_return_date' => now()->subDays(2)->toDateString()]);

        $this->artisan('notifications:daily')->assertSuccessful();
        $this->artisan('notifications:daily')->assertSuccessful();

        $this->assertSame(1, $this->milik($staf, 'request.review_overdue'), 'Belum dibaca → tidak digandakan.');
        $this->assertSame(1, $this->milik($kepala, 'request.review_overdue'));
        $this->assertSame(1, $this->milik($kepala, 'stock.reservation_stale'));
        $this->assertSame(0, $this->milik($staf, 'stock.reservation_stale'));
        $this->assertSame(1, $this->milik($this->klien, 'stock.reservation_stale'), 'Pemohon ikut diberi tahu.');
        $this->assertSame(1, $this->milik($kepala, 'asset.life_alert'));
        $this->assertSame(1, $this->milik($pic, 'asset.overdue'), 'PIC proyek menerima aset lewat jatuh tempo.');
        NotificationFacade::assertSentTo($pic, EventMail::class);

        // Sudah dibaca → pengingat diulang keesokan harinya.
        Notification::query()->where('user_id', $staf->id)->update(['read_at' => now()]);
        $this->artisan('notifications:daily')->assertSuccessful();
        $this->assertSame(2, $this->milik($staf, 'request.review_overdue'));
    }

    #[Test]
    public function tc_ntf_11_company_ditangguhkan_tidak_menerima_pengingat(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $this->reqKlien()->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->setSubscriptionStatus(SubscriptionStatus::Suspended);
        $this->artisan('notifications:daily')->assertSuccessful();
        $this->assertSame(0, $this->milik($staf, 'request.review_overdue'));

        $this->setSubscriptionStatus(SubscriptionStatus::Active);
        $this->artisan('notifications:daily')->assertSuccessful();
        $this->assertSame(1, $this->milik($staf, 'request.review_overdue'));
    }
}
