<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Approval\Livewire\TaskInbox;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Asset\Actions\MarkAssetLost;
use App\Domain\Asset\Policies\AssetPolicy;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Asset\Concerns\AssetFixtures;
use Tests\TenantTestCase;

/**
 * TC-AST-06 dan TC-AST-07 — aset hilang (BR-AST-04, A-167): Alasan `*`,
 * `asset_lost_or_damaged` tanpa pergerakan, ADJ `asset_lost` dari bin On-site
 * lewat approval, `written_off` saat diposting; ditemukan kembali setelah ADJ
 * ditolak.
 */
class AssetLossTest extends TenantTestCase
{
    use AssetFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAset();
        $this->pinjamkan();
    }

    #[Test]
    public function tc_ast_06_hilang_di_proyek_adj_disetujui_lalu_dihapuskan(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $aksi = app(MarkAssetLost::class);

        $this->assertTrue(app(AssetPolicy::class)->markLost($kepala, $this->gns));
        $this->assertFalse(app(AssetPolicy::class)->markLost($this->makeUser('warehouse_staff'), $this->gns), 'Staf Gudang tidak memegang asset.mark_lost (A-168).');

        $this->gagalAset(fn () => $aksi->handle($this->gns, null, null, $kepala), 'BR-GEN-11');
        $this->gagalAset(fn () => $aksi->handle($this->gns, $this->alasan(ReasonContext::Cancel), null, $kepala), 'BR-GEN-11');
        $stafBks = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->bks->id);
        $this->gagalAset(fn () => $aksi->handle($this->gns, $this->alasan(ReasonContext::Lost), null, $stafBks), 'BR-ACC-05');

        $adj = $aksi->handle($this->gns, $this->alasan(ReasonContext::Lost), 'Hilang di gudang site', $kepala);

        $this->assertSame(AssetState::Lost, $this->gns->refresh()->asset_state);
        $this->assertSame(AdjustmentOrigin::AssetLost, $adj->origin);
        $this->assertSame(StockAdjustmentStatus::PendingApproval, $adj->status, 'ADJ asset_lost lewat approval (A-09).');
        $baris = $adj->lines()->sole();
        $this->assertSame((int) $this->onSite->id, (int) $baris->bin_id, 'Keluar dari bin On-site proyek.');
        $this->assertSame(-1.0, (float) $baris->qty_delta);
        $this->assertSame(1.0, $this->saldo($this->onSite, $this->genset), 'Belum bergerak sebelum ADJ diposting.');

        $ast = $this->ast();
        $this->assertNotNull($ast->lost_at);
        $this->assertSame((int) $adj->id, (int) $ast->stock_adjustment_id);

        $hilang = StockEvent::query()->where('event_type', StockEventType::AssetLostOrDamaged->value)->sole();
        $this->assertSame('lost', $hilang->payload['kind']);
        $this->assertSame((int) $this->proyek->id, (int) $hilang->project_id);

        $this->gagalAset(fn () => $aksi->handle($this->gns->refresh(), $this->alasan(ReasonContext::Lost), null, $kepala), 'BR-AST-04');
        $this->gagalAset(fn () => $aksi->found($this->gns, $kepala), 'BR-AST-04');

        // ADJ diputus dari kotak tugas approval: posting → dihapuskan.
        $tugas = ApprovalTask::query()->open()->latest('id')->firstOrFail();
        $approver = User::query()->findOrFail($tugas->approver_user_id);
        $this->assertNotSame((int) $kepala->id, (int) $approver->id, 'Pengaju tidak memutus (BR-APR-03).');
        Livewire::actingAs($approver)->test(TaskInbox::class)->call('setujui', $tugas->id)->assertSet('ruleError', '');

        $this->assertSame(StockAdjustmentStatus::Posted, $adj->refresh()->status);
        $this->assertSame(0.0, $this->saldo($this->onSite, $this->genset));
        $this->assertSame(AssetState::WrittenOff, $this->gns->refresh()->asset_state);
        $this->assertFalse(app(AssetPolicy::class)->markLost($kepala, $this->gns));
    }

    #[Test]
    public function tc_ast_07_ditemukan_kembali_setelah_adj_ditolak(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->makeUser('management');
        $aksi = app(MarkAssetLost::class);

        $adj = $aksi->handle($this->gns, $this->alasan(ReasonContext::Lost), null, $kepala);
        $approver = User::query()->findOrFail(ApprovalTask::query()->open()->latest('id')->value('approver_user_id'));
        app(ApproveStockAdjustment::class)->reject($adj, $this->alasan(ReasonContext::Reject), 'Masih dicari', $approver);
        $this->assertSame(StockAdjustmentStatus::Rejected, $adj->refresh()->status);
        $this->assertSame(AssetState::Lost, $this->gns->refresh()->asset_state);
        $this->assertTrue(app(AssetPolicy::class)->found($kepala, $this->gns));

        $this->gns = $aksi->found($this->gns, $kepala);
        $this->assertSame(AssetState::OnLoan, $this->gns->asset_state, 'State dihitung ulang dari lokasi: bin On-site.');
        $this->assertSame((int) $this->proyek->id, (int) $this->gns->current_project_id);
        $this->assertNull($this->ast()->lost_at);
        $this->gagalAset(fn () => $aksi->found($this->gns, $kepala), 'BR-GEN-01');
    }
}
