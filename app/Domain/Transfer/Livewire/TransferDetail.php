<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Transfer\Actions\ApproveTransfer;
use App\Domain\Transfer\Actions\CancelTransfer;
use App\Domain\Transfer\Livewire\Concerns\HandlesTransferRules;
use App\Domain\Transfer\Models\Transfer;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 22-retur-transfer §6 — detail TRF: baris (dikirim/diterima), dokumen
 * fisik (PCK → SJ → GRN), setujui/tolak (pemegang tugas), batal, buat PCK bila
 * pembuatan otomatis gagal (A-107), riwayat approval dan riwayat dokumen.
 */
class TransferDetail extends Component
{
    use HandlesTransferRules;

    #[Locked]
    public int $transferId;

    /** '', 'tolak', 'batal' */
    public string $dialog = '';

    /** @var array<string, string> */
    public array $form = ['reason' => '', 'notes' => ''];

    public function mount(Transfer $transfer): void
    {
        $this->authorize('view', $transfer);

        $this->transferId = (int) $transfer->id;
    }

    public function render(): View
    {
        $trf = $this->trf();
        $pck = $trf->pickTasks()->with('warehouse:id,code')->orderBy('id')->get();
        $sj = $this->suratJalan($pck);

        return view('livewire.transfer.transfer-detail', [
            'trf' => $trf,
            'lines' => $trf->lines()->with('item:id,code,name', 'requestLine.request:id,number')->orderBy('id')->get(),
            'pickTasks' => $pck,
            'shipments' => $sj,
            'receipts' => GoodsReceipt::query()->withoutGlobalScopes()->whereIn('shipment_id', $sj->pluck('id'))->orderBy('id')->get(['id', 'number', 'status', 'shipment_id']),
            'pckSiapKirim' => $pck->first(fn (PickTask $t) => $t->status === PickTaskStatus::Completed
                && $t->lines->sum(fn ($l) => $l->unshippedQty()) > 0),
            'sjMenungguGrn' => $sj->first(fn (Shipment $s) => in_array($s->status, [ShipmentStatus::Delivered, ShipmentStatus::PartiallyDelivered], true)
                && ! GoodsReceipt::query()->withoutGlobalScopes()->where('shipment_id', $s->id)->where('status', '!=', 'cancelled')->exists()),
            'req' => $trf->sourceRequest(),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'transfer')
                ->where('subject_type', $trf->getMorphClass())->where('subject_id', $trf->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::Transfer, (int) $trf->id),
        ]);
    }

    public function setujui(ApproveTransfer $action): void
    {
        $trf = $this->trf();
        $this->authorize('approve', $trf);

        if ($this->jalankan(fn () => $action->approve($trf, auth()->user()))) {
            $this->dispatch('pesan', teks: $trf->refresh()->status->value === 'pending_approval'
                ? __('Persetujuan Anda tercatat; TRF menunggu lapis berikutnya.')
                : __('TRF disetujui.'));
        }
    }

    public function buatPicking(CreatePickTask $action): void
    {
        $trf = $this->trf();
        $this->authorize('createPick', $trf);

        if ($this->jalankan(fn () => $action->forTransfer($trf, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Tugas picking dibuat di gudang asal.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize($dialog === 'tolak' ? 'approve' : 'cancel', $this->trf());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApproveTransfer $action): void
    {
        $trf = $this->trf();
        $this->authorize('approve', $trf);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject(
            $trf,
            $this->alasanId($this->form['reason'], ReasonContext::Reject),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('TRF ditolak.'));
        }
    }

    public function batalkan(CancelTransfer $action): void
    {
        $trf = $this->trf();
        $this->authorize('cancel', $trf);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $trf,
            $this->alasanId($this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('TRF dibatalkan.'));
        }
    }

    /**
     * SJ yang memuat baris PCK TRF ini.
     *
     * @param  Collection<int, PickTask>  $pck
     * @return Collection<int, Shipment>
     */
    private function suratJalan(Collection $pck): Collection
    {
        $ids = ShipmentLine::query()
            ->whereIn('pick_task_line_id', $pck->flatMap(fn (PickTask $t) => $t->lines->pluck('id'))->all())
            ->pluck('shipment_id')->unique();

        return Shipment::query()->withoutGlobalScopes()->whereIn('id', $ids)->orderBy('id')->get(['id', 'number', 'status', 'shipment_method']);
    }

    private function trf(): Transfer
    {
        return Transfer::query()
            ->with('fromWarehouse:id,code,name', 'toWarehouse:id,code,name', 'fromProject:id,code', 'toProject:id,code',
                'submitter:id,name', 'approver:id,name', 'rejectReason:id,label', 'cancelReason:id,label')
            ->findOrFail($this->transferId);
    }
}
