<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Purchasing\Actions\ApprovePurchaseOrder;
use App\Domain\Purchasing\Actions\CancelPurchaseOrder;
use App\Domain\Purchasing\Actions\ClosePurchaseOrder;
use App\Domain\Purchasing\Actions\SubmitPurchaseOrder;
use App\Domain\Purchasing\Actions\UpdatePurchaseOrderEta;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Livewire\Concerns\HandlesPurchasingRules;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Receipt\Models\GoodsReceipt;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar purchasing/02 §6 — detail PO: ajukan, setujui/tolak, ubah ETA,
 * batalkan, tutup sisa, cetak; PRQ & GRN terkait, riwayat approval, riwayat.
 */
class PurchaseOrderDetail extends Component
{
    use HandlesPurchasingRules;

    #[Locked]
    public int $purchaseOrderId;

    /** '', 'tolak', 'batal', 'tutup', 'eta' */
    public string $dialog = '';

    /** @var array<string, mixed> */
    public array $form = ['reason' => '', 'notes' => '', 'eta_date' => ''];

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->authorize('view', $purchaseOrder);

        $this->purchaseOrderId = (int) $purchaseOrder->id;
    }

    public function render(): View
    {
        $po = $this->po();
        $lines = $po->lines()->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'requestLine.purchaseRequest:id,number', 'orderLine:id,purchase_order_line_id')->orderBy('id')->get();
        $orderLineIds = $lines->pluck('orderLine.id')->filter()->all();

        return view('livewire.purchasing.purchase-order-detail', [
            'po' => $po,
            'lines' => $lines,
            'prqs' => $lines->map(fn ($l) => $l->requestLine?->purchaseRequest)->filter()->unique('id')->values(),
            'receipts' => $orderLineIds === [] ? collect() : GoodsReceipt::query()->withoutGlobalScopes()
                ->whereHas('lines', fn ($q) => $q->whereIn('purchase_request_order_line_id', $orderLineIds))
                ->orderBy('id')->get(['id', 'number', 'status', 'received_at']),
            'menunggu' => $po->isAwaitingApproval(),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'purchase_order')
                ->where('subject_type', $po->getMorphClass())->where('subject_id', $po->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::PurchaseOrder, (int) $po->id),
        ]);
    }

    public function ajukan(SubmitPurchaseOrder $action): void
    {
        $po = $this->po();
        $this->authorize('submit', $po);

        if ($this->jalankan(fn () => $action->handle($po, auth()->user()))) {
            $this->dispatch('pesan', teks: $po->refresh()->status === PurchaseOrderStatus::Approved
                ? __('PO diajukan, disetujui otomatis, dan diterbitkan ke gudang.')
                : __('PO diajukan ke approval.'));
        }
    }

    public function setujui(ApprovePurchaseOrder $action): void
    {
        $po = $this->po();
        $this->authorize('approve', $po);

        if ($this->jalankan(fn () => $action->approve($po, auth()->user()))) {
            $this->dispatch('pesan', teks: $po->refresh()->status === PurchaseOrderStatus::Approved
                ? __('PO disetujui dan diterbitkan ke gudang.')
                : __('Persetujuan Anda tercatat; menunggu lapis berikutnya.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize(match ($dialog) {
            'tolak' => 'approve',
            'tutup' => 'close',
            'eta' => 'updateEta',
            default => 'cancel',
        }, $this->po());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => '', 'eta_date' => $this->po()->eta_date?->toDateString() ?? ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApprovePurchaseOrder $action): void
    {
        $po = $this->po();
        $this->authorize('approve', $po);
        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        if ($this->jalankan(fn () => $action->reject($po, $this->alasanId((string) $this->form['reason'], ReasonContext::Reject), $this->form['notes'] ?: null, auth()->user()))) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('PO ditolak.'));
        }
    }

    public function batalkan(CancelPurchaseOrder $action): void
    {
        $po = $this->po();
        $this->authorize('cancel', $po);
        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        if ($this->jalankan(fn () => $action->handle($po, $this->alasanId((string) $this->form['reason'], ReasonContext::Cancel), $this->form['notes'] ?: null, auth()->user()))) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('PO dibatalkan.'));
        }
    }

    public function tutupSisa(ClosePurchaseOrder $action): void
    {
        $po = $this->po();
        $this->authorize('close', $po);
        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        if ($this->jalankan(fn () => $action->handle($po, $this->alasanId((string) $this->form['reason'], ReasonContext::Cancel), $this->form['notes'] ?: null, auth()->user()))) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Sisa PO ditutup; bisa dipesan lagi.'));
        }
    }

    public function simpanEta(UpdatePurchaseOrderEta $action): void
    {
        $po = $this->po();
        $this->authorize('updateEta', $po);

        if ($this->jalankan(fn () => $action->handle($po, $this->form['eta_date'], auth()->user()))) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('ETA PO diperbarui, termasuk di catatan pemesanan gudang.'));
        }
    }

    private function po(): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with('vendor:id,code,name,vendor_type,phone,email', 'warehouse:id,code,name', 'creator:id,name', 'submitter:id,name',
                'approver:id,name', 'rejectReason:id,label', 'cancelReason:id,label', 'closeReason:id,label')
            ->findOrFail($this->purchaseOrderId);
    }
}
