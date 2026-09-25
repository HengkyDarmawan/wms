<?php

declare(strict_types=1);

namespace Tests\Feature\Transfer;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Shipment\Livewire\ShipmentForm;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Livewire\TransferDetail;
use App\Domain\Transfer\Livewire\TransferForm;
use App\Domain\Transfer\Livewire\TransferList;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Enums\BinStatus;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Transfer\Concerns\TransferFixtures;
use Tests\TenantTestCase;

/**
 * TC-TRF-14 s.d. TC-TRF-16 — izin layar (BR-GEN-09), tiga layar TRF dan
 * dialognya (BR-GEN-11), prefill SJ dari TRF, menu & palet.
 */
class TransferScreenTest extends TenantTestCase
{
    use ApprovalFixtures;
    use TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    #[Test]
    public function tc_trf_14_izin_layar_transfer(): void
    {
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 5]]);

        $staf = $this->makeUser('warehouse_staff');
        $this->assertTrue($staf->hasPermission('transfer.create'));
        $this->assertFalse($staf->hasPermission('transfer.approve'), 'Menyetujui hak Approver (Katalog §2.7).');

        foreach (['transfers', 'transfers/create', 'transfers/'.$trf->id] as $url) {
            $this->actingAs($staf)->get($this->tenantUrl($url))->assertOk();
        }

        $auditor = $this->makeUser('internal_auditor');
        $this->actingAs($auditor)->get($this->tenantUrl('transfers'))->assertOk()->assertSee($trf->number);
        $this->actingAs($auditor)->get($this->tenantUrl('transfers/create'))->assertForbidden();

        foreach (['internal_requester', 'driver', 'pr_follow_up'] as $role) {
            $this->actingAs($this->makeUser($role))->get($this->tenantUrl('transfers'))->assertForbidden();
        }

        $this->assertTrue($this->makeUser('management')->hasPermission('transfer.approve'));
        $this->assertTrue($this->makeUser('warehouse_head')->hasPermission('transfer.cancel'));
    }

    #[Test]
    public function tc_trf_15_layar_form_detail_setujui_tolak_batal(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::Transfer, [$this->lapisUser($kepala)]);
        $staf = $this->makeUser('warehouse_staff');

        Livewire::actingAs($staf)
            ->test(TransferForm::class)
            ->assertOk()
            ->set('form.from_warehouse_id', (string) $this->gudang->id)
            ->set('form.to_warehouse_id', (string) $this->gudang->id)
            ->set('rows.0.item_id', (string) $this->baut->id)
            ->set('rows.0.qty_base', '10')
            ->assertSee('100')
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-RET-02')
            ->set('form.to_warehouse_id', (string) $this->bks->id)
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $trf = Transfer::query()->latest('id')->firstOrFail();
        $this->assertSame(TransferStatus::PendingApproval, $trf->status);

        Livewire::actingAs($staf)->test(TransferList::class)->assertOk()->assertSee($trf->number);

        // Pengaju tidak melihat tombol setujui; approver menolak lewat dialog (Alasan wajib).
        Livewire::actingAs($staf)->test(TransferDetail::class, ['transfer' => $trf])
            ->assertOk()->assertDontSee(__('Setujui'))->assertSee(__('Riwayat approval'));

        Livewire::actingAs($kepala)
            ->test(TransferDetail::class, ['transfer' => $trf])
            ->call('mintaDialog', 'tolak')
            ->call('tolak')
            ->assertHasErrors('form.reason')
            ->set('form.reason', 'SPEC')
            ->call('tolak')
            ->assertSet('ruleError', '');

        $this->assertSame(TransferStatus::Rejected, $trf->refresh()->status);

        // Setujui lewat layar → PCK otomatis; batal lewat dialog saat masih menunggu.
        $kedua = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 4]], $staf);
        Livewire::actingAs($kepala)->test(TransferDetail::class, ['transfer' => $kedua])->call('setujui')->assertSet('ruleError', '');
        $this->assertSame(TransferStatus::InProgress, $kedua->refresh()->status);

        $ketiga = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 3]], $staf);
        Livewire::actingAs($staf)
            ->test(TransferDetail::class, ['transfer' => $ketiga])
            ->call('mintaDialog', 'batal')
            ->set('form.reason', 'WRONG_INPUT')
            ->call('batalkan')
            ->assertSet('ruleError', '');
        $this->assertSame(TransferStatus::Cancelled, $ketiga->refresh()->status);
    }

    #[Test]
    public function tc_trf_16_pck_manual_prefill_sj_dan_menu(): void
    {
        $this->binA->forceFill(['bin_status' => BinStatus::Frozen])->save();
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 6]]);
        $this->assertSame(TransferStatus::Approved, $trf->status);
        $this->binA->forceFill(['bin_status' => BinStatus::Active])->save();

        $kepala = $this->makeUser('warehouse_head');

        Livewire::actingAs($kepala)
            ->test(TransferDetail::class, ['transfer' => $trf])
            ->assertSee(__('Buat tugas picking'))
            ->call('buatPicking')
            ->assertSet('ruleError', '');

        $pck = $this->jalankanPck($this->pckTrf($trf->refresh()));

        Livewire::actingAs($kepala)->test(TransferDetail::class, ['transfer' => $trf])->assertSee(__('Susun surat jalan'));

        Livewire::actingAs($kepala)
            ->withQueryParams(['pick_task' => $pck->id])
            ->test(ShipmentForm::class)
            ->assertSet('pickTaskIds', [$pck->id])
            ->assertSet('form.destination_type', 'warehouse')
            ->assertSet('form.destination_warehouse_id', (string) $this->bks->id);

        $this->actingAs($kepala)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(__('Barang masuk'))->assertSee(route('transfers.index'));

        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('/'))->assertOk()
            ->assertDontSee(route('transfers.index'));
    }
}
