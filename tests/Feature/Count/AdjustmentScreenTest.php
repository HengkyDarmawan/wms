<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Livewire\AdjustmentDetail;
use App\Domain\Adjustment\Livewire\AdjustmentForm;
use App\Domain\Adjustment\Livewire\AdjustmentList;
use App\Domain\Adjustment\Models\StockAdjustment;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-ADJ-10 — layar penyesuaian stok: izin halaman (BR-GEN-09), form ADJ
 * manual, detail (setujui/tolak/batal), ADJ pembalik, panel riwayat approval.
 */
class AdjustmentScreenTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    #[Test]
    public function tc_adj_10_izin_halaman_penyesuaian(): void
    {
        $adj = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 2]]);

        foreach (['adjustments', 'adjustments/create', 'adjustments/'.$adj->id] as $url) {
            $this->actingAs($this->staf1)->get($this->tenantUrl($url))->assertOk();
        }

        $this->actingAs($this->auditor)->get($this->tenantUrl('adjustments'))->assertOk();
        $this->actingAs($this->auditor)->get($this->tenantUrl('adjustments/create'))->assertForbidden();

        $driver = $this->makeUser('driver');
        foreach (['adjustments', 'adjustments/create', 'adjustments/'.$adj->id] as $url) {
            $this->actingAs($driver)->get($this->tenantUrl($url))->assertForbidden();
        }

        $this->actingAs($this->kepala)->get($this->tenantUrl('adjustments/'.$adj->id))->assertOk()
            ->assertSee(__('Riwayat approval'))->assertSee(__('Setujui'));

        Livewire::actingAs($this->staf1)->test(AdjustmentList::class)->assertOk()->assertSee($adj->number);
    }

    #[Test]
    public function tc_adj_10b_form_detail_dan_pembalik(): void
    {
        Livewire::actingAs($this->staf1)
            ->test(AdjustmentForm::class)
            ->assertOk()
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.reason', 'FOUND')
            ->set('rows.0.direction', 'in')
            ->set('rows.0.bin_id', (string) $this->binA->id)
            ->set('rows.0.item_id', (string) $this->baut->id)
            ->set('rows.0.qty', '4')
            ->call('tambahBaris')
            ->set('rows.1.direction', 'out')
            ->set('rows.1.bin_id', (string) $this->binB->id)
            ->set('rows.1.item_id', (string) $this->semen->id)
            ->set('rows.1.qty', '2')
            ->set('rows.1.lot_no', 'LOT-OPN-1')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $adj = StockAdjustment::query()->latest('id')->firstOrFail();
        $this->assertSame(StockAdjustmentStatus::PendingApproval, $adj->status);
        $this->assertSame(2, $adj->lines()->count());

        // Pengaju tidak melihat tombol setujui (BR-APR-03).
        Livewire::actingAs($this->staf1)
            ->test(AdjustmentDetail::class, ['stockAdjustment' => $adj])
            ->call('setujui')
            ->assertForbidden();

        Livewire::actingAs($this->kepala)
            ->test(AdjustmentDetail::class, ['stockAdjustment' => $adj])
            ->call('setujui')
            ->assertSet('ruleError', '');

        $this->assertSame(StockAdjustmentStatus::Posted, $adj->refresh()->status);
        $this->assertSame(104.0, $this->saldoBin($this->binA, $this->baut));

        // ADJ pembalik dari layar.
        Livewire::withQueryParams(['reversal_of' => $adj->id])
            ->actingAs($this->staf1)
            ->test(AdjustmentForm::class)
            ->assertSee($adj->number)
            ->set('form.reason', 'SYSTEM_FIX')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $balik = StockAdjustment::query()->where('reversal_of_id', $adj->id)->sole();
        app(ApproveStockAdjustment::class)->approve($balik, $this->kepala);
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut));

        // Pembalik kedua tidak ditawarkan (BR-LED-05).
        Livewire::withQueryParams(['reversal_of' => $adj->id])
            ->actingAs($this->staf1)
            ->test(AdjustmentForm::class)
            ->assertForbidden();
    }

    #[Test]
    public function tc_adj_10c_tolak_dan_batal_lewat_dialog(): void
    {
        $a = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 1]]);
        $b = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 1]]);

        Livewire::actingAs($this->kepala)
            ->test(AdjustmentDetail::class, ['stockAdjustment' => $a])
            ->call('mintaDialog', 'tolak')
            ->call('tolak')
            ->assertHasErrors(['form.reason'])
            ->set('form.reason', 'QTY')
            ->call('tolak')
            ->assertSet('ruleError', '');

        $this->assertSame(StockAdjustmentStatus::Rejected, $a->refresh()->status);

        Livewire::actingAs($this->staf1)
            ->test(AdjustmentDetail::class, ['stockAdjustment' => $b])
            ->call('mintaDialog', 'batal')
            ->set('form.reason', 'WRONG_INPUT')
            ->call('batalkan')
            ->assertSet('ruleError', '');

        $this->assertSame(StockAdjustmentStatus::Cancelled, $b->refresh()->status);
    }
}
