<?php

declare(strict_types=1);

namespace App\Domain\Waste\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Waste\Actions\ApproveWasteDisposal;
use App\Domain\Waste\Actions\CancelWasteDisposal;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Livewire\Concerns\HandlesWasteRules;
use App\Domain\Waste\Models\WasteDisposal;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 24-konversi-waste §6 — detail BA waste: setujui/tolak (pemegang tugas
 * approval), batal, riwayat approval dan riwayat. Penutupan dengan bukti
 * memakai form unggah biasa (POST) karena unggahan Livewire berjalan di luar
 * middleware tenant (lihat ItemPhotoController).
 */
class WasteDisposalDetail extends Component
{
    use HandlesWasteRules;

    #[Locked]
    public int $wasteDisposalId;

    /** '', 'tolak', 'batal' */
    public string $dialog = '';

    /** @var array<string, mixed> */
    public array $form = ['reason' => '', 'notes' => ''];

    public function mount(WasteDisposal $wasteDisposal): void
    {
        $this->authorize('view', $wasteDisposal);

        $this->wasteDisposalId = (int) $wasteDisposal->id;
    }

    public function render(): View
    {
        $wst = $this->wst();

        return view('livewire.waste.waste-disposal-detail', [
            'wst' => $wst,
            'lines' => $wst->lines()->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'bin:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no,length', 'reason:id,label')->orderBy('id')->get(),
            'menunggu' => $wst->isAwaitingApproval(),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'waste')
                ->where('subject_type', $wst->getMorphClass())->where('subject_id', $wst->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::WasteDisposal, (int) $wst->id),
        ]);
    }

    public function setujui(ApproveWasteDisposal $action): void
    {
        $wst = $this->wst();
        $this->authorize('approve', $wst);

        if ($this->jalankan(fn () => $action->approve($wst, auth()->user()))) {
            $this->dispatch('pesan', teks: $wst->refresh()->status === WasteDisposalStatus::Approved
                ? __('BA waste disetujui; tutup dengan bukti.')
                : __('Persetujuan Anda tercatat; menunggu lapis berikutnya.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize($dialog === 'tolak' ? 'approve' : 'cancel', $this->wst());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApproveWasteDisposal $action): void
    {
        $wst = $this->wst();
        $this->authorize('approve', $wst);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject(
            $wst,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Reject),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('BA waste ditolak.'));
        }
    }

    public function batalkan(CancelWasteDisposal $action): void
    {
        $wst = $this->wst();
        $this->authorize('cancel', $wst);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $wst,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('BA waste dibatalkan.'));
        }
    }

    private function wst(): WasteDisposal
    {
        return WasteDisposal::query()
            ->with('project:id,code,name', 'warehouse:id,code,name', 'targetBin:id,code', 'submitter:id,name', 'approver:id,name', 'closer:id,name',
                'rejectReason:id,label', 'cancelReason:id,label')
            ->findOrFail($this->wasteDisposalId);
    }
}
