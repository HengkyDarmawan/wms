<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\CancelGoodsReceipt;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\RecordQcResult;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Support\CrossDockCandidates;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 19-receipt-putaway §6.3 — detail GRN: terima, QC per baris, selesai,
 * batal, dan tautan ke PUT/RTV yang lahir darinya.
 */
class ReceiptDetail extends Component
{
    use HandlesReceiptRules;

    #[Locked]
    public int $receiptId;

    /** '', 'batal', 'qc' */
    public string $dialog = '';

    #[Locked]
    public ?int $qcLineId = null;

    /** @var array<string, string> */
    public array $form = [
        'reason' => '',
        'notes' => '',
        'qc_result' => 'passed',
        'qc_reason' => '',
        'qc_note' => '',
    ];

    public function mount(GoodsReceipt $goodsReceipt): void
    {
        $this->authorize('view', $goodsReceipt);

        $this->receiptId = (int) $goodsReceipt->id;
    }

    public function render(CrossDockCandidates $crossDock): View
    {
        $grn = $this->grn();
        $lines = $grn->lines()->with('item:id,code,name,tracking_mode', 'receivingBin:id,code,bin_type', 'lot', 'serial', 'piece', 'qcReason')->orderBy('id')->get();

        return view('livewire.receipt.receipt-detail', [
            'grn' => $grn,
            'lines' => $lines,
            'putaways' => $grn->putawayTasks()->withoutGlobalScopes()->orderBy('id')->get(),
            'returns' => $grn->vendorReturns()->withoutGlobalScopes()->orderBy('id')->get(),
            'crossDock' => $grn->status->value === 'draft' ? collect() : $lines->pluck('item_id')->unique()
                ->mapWithKeys(fn ($id) => [$id => $crossDock->waitingFor((int) $id, (int) $grn->warehouse_id)])
                ->filter(fn ($c) => $c->isNotEmpty()),
            'qcOptions' => QcResult::options(),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'bisaReplan' => $grn->status->value === 'completed'
                && $lines->contains(fn (GoodsReceiptLine $l) => $l->isPutawayEligible()
                    && ! $l->putawayLines()->whereHas('task', fn ($q) => $q->where('status', '!=', 'cancelled'))->exists()),
            'riwayat' => $this->riwayat($grn),
        ]);
    }

    public function terima(ReceiveGoodsReceipt $action): void
    {
        $grn = $this->grn();
        $this->authorize('receive', $grn);

        if ($this->jalankan(fn () => $action->handle($grn, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Barang diterima dan tercatat di kartu stok.'));
        }
    }

    public function selesaikan(CompleteGoodsReceipt $action): void
    {
        $grn = $this->grn();
        $this->authorize('complete', $grn);

        if ($this->jalankan(fn () => $action->handle($grn, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Penerimaan selesai; tugas put-away dibuat.'));
        }
    }

    public function buatUlangPutaway(CompleteGoodsReceipt $action): void
    {
        $grn = $this->grn();
        $this->authorize('complete', $grn);

        if ($this->jalankan(fn () => $action->replan($grn, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Tugas put-away dibuat ulang.'));
        }
    }

    public function mintaDialog(string $dialog, ?int $lineId = null): void
    {
        $grn = $this->grn();
        $this->authorize($dialog === 'qc' ? 'qc' : 'cancel', $grn);

        $this->dialog = $dialog;
        $this->qcLineId = $dialog === 'qc' ? $lineId : null;
        $this->form = ['reason' => '', 'notes' => '', 'qc_result' => 'passed', 'qc_reason' => '', 'qc_note' => ''];
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->qcLineId = null;
        $this->resetValidation();
    }

    public function batalkan(CancelGoodsReceipt $action): void
    {
        $grn = $this->grn();
        $this->authorize('cancel', $grn);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $grn,
            $this->alasanId($this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Penerimaan dibatalkan.'));
        }
    }

    public function simpanQc(RecordQcResult $action): void
    {
        $grn = $this->grn();
        $this->authorize('qc', $grn);

        $line = $grn->lines()->findOrFail((int) $this->qcLineId);
        $hasil = QcResult::tryFrom($this->form['qc_result']);

        if ($hasil === null) {
            $this->addError('form.qc_result', __('Hasil QC wajib dipilih.'));

            return;
        }

        $ok = $this->jalankan(fn () => $action->handle(
            $line,
            $hasil,
            $this->alasanId($this->form['qc_reason'], ReasonContext::Reject),
            $this->form['qc_note'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Hasil QC tersimpan.'));
        }
    }

    private function grn(): GoodsReceipt
    {
        return GoodsReceipt::query()
            ->with('warehouse:id,code,name', 'vendor:id,name', 'shipment:id,number', 'cancelReason:id,label', 'receiver:id,name')
            ->findOrFail($this->receiptId);
    }

    /** @return \Illuminate\Support\Collection<int, Activity> */
    private function riwayat(GoodsReceipt $grn): \Illuminate\Support\Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->where('log_name', 'receipt')
            ->where('subject_type', $grn->getMorphClass())
            ->where('subject_id', $grn->id)
            ->latest('id')
            ->limit(30)
            ->get();
    }
}
