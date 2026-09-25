<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\ItemVendor;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Actions\ApprovePurchaseRequest;
use App\Domain\PurchaseRequest\Actions\CancelPurchaseRequest;
use App\Domain\PurchaseRequest\Actions\OrderPurchaseRequest;
use App\Domain\PurchaseRequest\Actions\SubmitPurchaseRequest;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Livewire\Concerns\HandlesPurchaseRequestRules;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Receipt\Models\GoodsReceipt;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 26-purchase-request §6.3 — detail PRQ: ajukan draf, setujui/tolak,
 * catat pemesanan per vendor (vendor baru sementara, A-53), batal, daftar
 * catatan beserta GRN yang merujuknya, riwayat approval dan riwayat.
 */
class PurchaseRequestDetail extends Component
{
    use HandlesPurchaseRequestRules;

    #[Locked]
    public int $purchaseRequestId;

    /** '', 'tolak', 'batal', 'pesan' */
    public string $dialog = '';

    /** @var array<string, mixed> */
    public array $form = ['reason' => '', 'notes' => ''];

    /** @var array<string, string> catatan pemesanan (A-51) */
    public array $order = [];

    /** @var array<int|string, string> purchase_request_line_id => jumlah dipesan */
    public array $orderQty = [];

    public function mount(PurchaseRequest $purchaseRequest): void
    {
        $this->authorize('view', $purchaseRequest);

        $this->purchaseRequestId = (int) $purchaseRequest->id;
    }

    public function render(): View
    {
        $prq = $this->prq();
        $orders = $prq->orders()->with('vendor:id,code,name,vendor_type,status', 'orderer:id,name', 'lines.line.item:id,code,name')->orderBy('id')->get();
        $orderLineIds = $orders->flatMap(fn ($o) => $o->lines->pluck('id'))->all();

        return view('livewire.purchase-request.purchase-request-detail', [
            'prq' => $prq,
            'lines' => $prq->lines()->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'requestLine:id,material_request_id')->orderBy('id')->get(),
            'orders' => $orders,
            'receipts' => $orderLineIds === [] ? collect() : GoodsReceipt::query()->withoutGlobalScopes()
                ->whereHas('lines', fn ($q) => $q->whereIn('purchase_request_order_line_id', $orderLineIds))
                ->orderBy('id')->get(['id', 'number', 'status', 'received_at']),
            'vendors' => $this->dialog === 'pesan'
                ? Vendor::query()->where('is_active', true)->where('status', '!=', VendorStatus::Inactive->value)->orderBy('name')->get(['id', 'code', 'name', 'vendor_type'])
                : collect(),
            'vendorTypes' => VendorType::options(),
            'suggested' => $this->dialog === 'pesan'
                ? ItemVendor::query()->whereIn('item_id', $prq->lines()->pluck('item_id'))->pluck('vendor_id')->map(fn ($v) => (int) $v)->unique()->all()
                : [],
            'menunggu' => $prq->isAwaitingApproval(),
            'alasanTolak' => $this->pilihanAlasan(ReasonContext::Reject),
            'alasanBatal' => $this->pilihanAlasan(ReasonContext::Cancel),
            'riwayat' => Activity::query()->with('causer:id,name')->where('log_name', 'purchase_request')
                ->where('subject_type', $prq->getMorphClass())->where('subject_id', $prq->id)
                ->latest('id')->limit(30)->get(),
            'riwayatApproval' => app(ApprovalHistory::class)->for(ApprovalDocumentType::PurchaseRequest, (int) $prq->id),
        ]);
    }

    public function ajukan(SubmitPurchaseRequest $action): void
    {
        $prq = $this->prq();
        $this->authorize('submit', $prq);

        if ($this->jalankan(fn () => $action->handle($prq, auth()->user()))) {
            $this->dispatch('pesan', teks: $prq->refresh()->status === PurchaseRequestStatus::Approved
                ? __('PRQ diajukan dan disetujui otomatis.')
                : __('PRQ diajukan ke approval.'));
        }
    }

    public function setujui(ApprovePurchaseRequest $action): void
    {
        $prq = $this->prq();
        $this->authorize('approve', $prq);

        if ($this->jalankan(fn () => $action->approve($prq, auth()->user()))) {
            $this->dispatch('pesan', teks: $prq->refresh()->status === PurchaseRequestStatus::Approved
                ? __('PRQ disetujui; siap diteruskan ke Purchasing.')
                : __('Persetujuan Anda tercatat; menunggu lapis berikutnya.'));
        }
    }

    public function mintaDialog(string $dialog): void
    {
        $this->authorize(match ($dialog) {
            'tolak' => 'approve',
            'pesan' => 'order',
            default => 'cancel',
        }, $this->prq());

        $this->dialog = $dialog;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->order = ['vendor_id' => '', 'new_vendor_name' => '', 'new_vendor_type' => '', 'new_vendor_phone' => '',
            'external_po_no' => '', 'marketplace_order_no' => '', 'tracking_no' => '', 'eta_date' => '', 'vendor_note' => '', 'notes' => ''];
        $this->orderQty = [];

        if ($dialog === 'pesan') {
            foreach ($this->prq()->lines()->get() as $l) {
                $sisa = $l->unorderedQty();

                if ($sisa > 0) {
                    $this->orderQty[$l->id] = (string) $sisa;
                }
            }

            // A-52: vendor tetap item disarankan; memilih vendor lain boleh dengan keterangan.
            $saran = ItemVendor::query()->whereIn('item_id', $this->prq()->lines()->pluck('item_id'))
                ->whereHas('vendor', fn ($q) => $q->where('is_active', true)->where('status', '!=', VendorStatus::Inactive->value))
                ->orderByDesc('is_preferred')->orderBy('priority')->value('vendor_id');
            $this->order['vendor_id'] = $saran === null ? '' : (string) $saran;
        }

        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function tolak(ApprovePurchaseRequest $action): void
    {
        $prq = $this->prq();
        $this->authorize('approve', $prq);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->reject(
            $prq,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Reject),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('PRQ ditolak.'));
        }
    }

    public function batalkan(CancelPurchaseRequest $action): void
    {
        $prq = $this->prq();
        $this->authorize('cancel', $prq);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $prq,
            $this->alasanId((string) $this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('PRQ dibatalkan.'));
        }
    }

    public function catatPesanan(OrderPurchaseRequest $action): void
    {
        $prq = $this->prq();
        $this->authorize('order', $prq);

        $ok = $this->jalankan(fn () => $action->handle($prq, $this->order, $this->orderQty, auth()->user()), 'order');

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Catatan pemesanan tersimpan.'));
        }
    }

    private function prq(): PurchaseRequest
    {
        return PurchaseRequest::query()
            ->with('warehouse:id,code,name', 'project:id,code,name', 'materialRequest:id,number', 'creator:id,name', 'submitter:id,name',
                'approver:id,name', 'forwarder:id,name', 'rejectReason:id,label', 'cancelReason:id,label')
            ->findOrFail($this->purchaseRequestId);
    }
}
