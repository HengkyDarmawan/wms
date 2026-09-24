<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\ApproveVendorReturn;
use App\Domain\Receipt\Actions\CancelVendorReturn;
use App\Domain\Receipt\Actions\CompleteVendorReturn;
use App\Domain\Receipt\Actions\ShipVendorReturn;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\VendorReturn;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 19-receipt-putaway §6.8 — detail RTV: setujui/tolak, kirim, selesai,
 * batal. Dokumen ini yang dicetak sebagai surat jalan retur (A-80).
 */
class VendorReturnDetail extends Component
{
    use HandlesReceiptRules;

    #[Locked]
    public int $returnId;

    /** '', 'tolak', 'batal' */
    public string $dialog = '';

    /** @var array<string, string> */
    public array $form = ['reason' => '', 'notes' => ''];

    public function mount(VendorReturn $vendorReturn): void
    {
        $this->authorize('view', $vendorReturn);

        $this->returnId = (int) $vendorReturn->id;
    }

    public function render(): View
    {
        $rtv = $this->rtv();

        return view('livewire.receipt.vendor-return-detail', [
            'rtv' => $rtv,
            'lines' => $rtv->lines()->with('item:id,code,name', 'bin:id,code', 'reason:id,label', 'lot', 'serial', 'piece')->orderBy('id')->get(),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'receipt')
                ->where('subject_type', $rtv->getMorphClass())->where('subject_id', $rtv->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::VendorReturn, (int) $rtv->id),
        ]);
    }

    public function setujui(ApproveVendorReturn $action): void
    {
        $rtv = $this->rtv();
        $this->authorize('approve', $rtv);

        if ($this->jalankan(fn () => $action->approve($rtv, auth()->user()))) {
            $this->dispatch('pesan', teks: $rtv->refresh()->status->value === 'approved'
                ? __('RTV disetujui.')
                : __('Persetujuan Anda tercatat; RTV menunggu lapis berikutnya.'));
        }
    }

    public function kirim(ShipVendorReturn $action): void
    {
        $rtv = $this->rtv();
        $this->authorize('ship', $rtv);

        if ($this->jalankan(fn () => $action->handle($rtv, null, auth()->user()))) {
            $this->dispatch('pesan', teks: __('RTV dikirim; barang keluar dari stok.'));
        }
    }

    public function selesaikan(CompleteVendorReturn $action): void
    {
        $rtv = $this->rtv();
        $this->authorize('complete', $rtv);

        if ($this->jalankan(fn () => $action->handle($rtv, $this->form['notes'] ?: null, auth()->user()))) {
            $this->dispatch('pesan', teks: __('RTV selesai.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize($dialog === 'tolak' ? 'approve' : 'cancel', $this->rtv());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApproveVendorReturn $action): void
    {
        $rtv = $this->rtv();
        $this->authorize('approve', $rtv);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject(
            $rtv,
            $this->alasanId($this->form['reason'], ReasonContext::Reject),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('RTV ditolak.'));
        }
    }

    public function batalkan(CancelVendorReturn $action): void
    {
        $rtv = $this->rtv();
        $this->authorize('cancel', $rtv);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $rtv,
            $this->alasanId($this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('RTV dibatalkan.'));
        }
    }

    private function rtv(): VendorReturn
    {
        return VendorReturn::query()
            ->with('warehouse:id,code,name', 'vendor:id,name', 'receipt:id,number', 'replacementReceipt:id,number',
                'submitter:id,name', 'approver:id,name', 'rejectReason:id,label', 'cancelReason:id,label')
            ->findOrFail($this->returnId);
    }
}
