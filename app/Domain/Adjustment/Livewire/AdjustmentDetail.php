<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Livewire;

use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Adjustment\Actions\CancelStockAdjustment;
use App\Domain\Adjustment\Livewire\Concerns\HandlesAdjustmentRules;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ReasonContext;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 21-opname-penyesuaian §6.8 — detail ADJ: setujui/tolak (pemegang
 * tugas approval), batal (manual, sebelum disetujui), buat ADJ pembalik
 * (setelah diposting), riwayat approval dan riwayat dokumen.
 */
class AdjustmentDetail extends Component
{
    use HandlesAdjustmentRules;

    #[Locked]
    public int $adjustmentId;

    /** '', 'tolak', 'batal' */
    public string $dialog = '';

    /** @var array<string, string> */
    public array $form = ['reason' => '', 'notes' => ''];

    public function mount(StockAdjustment $stockAdjustment): void
    {
        $this->authorize('view', $stockAdjustment);

        $this->adjustmentId = (int) $stockAdjustment->id;
    }

    public function render(): View
    {
        $adj = $this->adj();

        return view('livewire.adjustment.adjustment-detail', [
            'adj' => $adj,
            'lines' => $adj->lines()->with('item:id,code,name', 'bin:id,code', 'lot', 'serial', 'piece', 'reason:id,label', 'movement:id')->orderBy('id')->get(),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'pembalik' => $adj->activeReversal(),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'adjustment')
                ->where('subject_type', $adj->getMorphClass())->where('subject_id', $adj->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::StockAdjustment, (int) $adj->id),
        ]);
    }

    public function setujui(ApproveStockAdjustment $action): void
    {
        $adj = $this->adj();
        $this->authorize('approve', $adj);

        if ($this->jalankan(fn () => $action->approve($adj, auth()->user()))) {
            $this->dispatch('pesan', teks: $adj->refresh()->status->value === 'posted'
                ? __('ADJ disetujui dan diposting ke kartu stok.')
                : __('Persetujuan Anda tercatat; ADJ menunggu lapis berikutnya.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize($dialog === 'tolak' ? 'approve' : 'cancel', $this->adj());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApproveStockAdjustment $action): void
    {
        $adj = $this->adj();
        $this->authorize('approve', $adj);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject(
            $adj,
            $this->alasanId($this->form['reason'], ReasonContext::Reject),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('ADJ ditolak.'));
        }
    }

    public function batalkan(CancelStockAdjustment $action): void
    {
        $adj = $this->adj();
        $this->authorize('cancel', $adj);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $adj,
            $this->alasanId($this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('ADJ dibatalkan.'));
        }
    }

    private function adj(): StockAdjustment
    {
        return StockAdjustment::query()
            ->with('warehouse:id,code,name', 'reason:id,label', 'submitter:id,name', 'approver:id,name',
                'rejectReason:id,label', 'cancelReason:id,label', 'stockCount:id,number', 'reversalOf:id,number')
            ->findOrFail($this->adjustmentId);
    }
}
