<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Livewire;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalHistory;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Actions\ApprovePurchaseRequest;
use App\Domain\PurchaseRequest\Actions\CancelPurchaseRequest;
use App\Domain\PurchaseRequest\Actions\OrderPurchaseRequest;
use App\Domain\PurchaseRequest\Actions\SubmitPurchaseRequest;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Livewire\Concerns\HandlesPurchaseRequestRules;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Purchasing\Support\PilihanVendor;
use App\Domain\Purchasing\Support\VendorSuggestions;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
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
    use CariPilihan;
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
        // A-304: saran dari riwayat; termurah hanya nama & hanya bagi po.view (D-07).
        $saran = $this->dialog === 'pesan' ? $this->saranVendor($prq) : [];

        return view('livewire.purchase-request.purchase-request-detail', [
            'prq' => $prq,
            'lines' => $prq->lines()->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'requestLine:id,material_request_id')->orderBy('id')->get(),
            'orders' => $orders,
            'receipts' => $orderLineIds === [] ? collect() : GoodsReceipt::query()->withoutGlobalScopes()
                ->whereHas('lines', fn ($q) => $q->whereIn('purchase_request_order_line_id', $orderLineIds))
                ->orderBy('id')->get(['id', 'number', 'status', 'received_at']),
            'opsiVendor' => $this->dialog === 'pesan' ? $this->opsiVendor($saran['ids'] ?? []) : [],
            'vendorTypes' => VendorType::options(),
            'saran' => $saran,
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

            // A-304: bawaan = vendor terakhir item-item PRQ; Purchasing bebas mengganti.
            $saran = app(VendorSuggestions::class)->defaultVendor($this->prq()->lines()->pluck('item_id')->map(fn ($v) => (int) $v)->all());
            $this->order['vendor_id'] = $saran === null ? '' : (string) $saran->id;
        }

        $this->resetValidation();
    }

    /**
     * Per baris PRQ: vendor terakhir & termurah 6 bulan (nama saja).
     *
     * @return array{baris: list<array{item: string, terakhir: ?string, termurah: ?string}>, ids: list<int>}
     */
    private function saranVendor(PurchaseRequest $prq): array
    {
        $bolehHarga = auth()->user()?->can('po.view') ?? false;
        $lines = $prq->lines()->with('item:id,code,name')->orderBy('id')->get();
        $saran = app(VendorSuggestions::class)->forItems($lines->pluck('item_id')->map(fn ($v) => (int) $v)->all());
        $baris = [];
        $ids = [];

        foreach ($lines->unique('item_id') as $l) {
            $s = $saran[(int) $l->item_id] ?? ['terakhir' => null, 'termurah' => null];
            $termurah = $bolehHarga ? $s['termurah'] : null;
            $baris[] = ['item' => (string) $l->item?->code, 'terakhir' => $s['terakhir']?->name, 'termurah' => $termurah?->name];
            $ids = array_merge($ids, array_filter([$s['terakhir']?->id, $termurah?->id]));
        }

        return ['baris' => $baris, 'ids' => array_values(array_unique(array_map('intval', $ids)))];
    }

    /** Vendor aktif & sementara (daftar lama), dicari ke server (A-393). */
    private function pilihanVendor(): Pilihan
    {
        return PilihanVendor::dari(Vendor::query()->where('is_active', true)
            ->where('status', '!=', VendorStatus::Inactive->value)->orderBy('name'));
    }

    /**
     * Isian awal: vendor saran riwayat paling atas bertanda *disarankan*, lalu
     * potongan awal daftar + nilai terpilih.
     *
     * @param  list<int>  $saranIds
     * @return list<array<string, mixed>>
     */
    private function opsiVendor(array $saranIds): array
    {
        $pilihan = $this->pilihanVendor();
        $atas = [];

        foreach ($saranIds as $id) {
            if (($o = $pilihan->label($id)) !== null) {
                $atas[] = ['badge' => __('disarankan'), 'sub' => trim(($o['badge'] ?? '').' · '.$o['sub'], ' ·')] + $o;
            }
        }

        $ada = array_map(fn (array $o) => (string) $o['value'], $atas);

        return [...$atas, ...array_values(array_filter(
            $pilihan->awalDengan($this->order['vendor_id'] ?? ''),
            fn (array $o) => ! in_array((string) $o['value'], $ada, true),
        ))];
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        return $model === 'order.vendor_id' && $this->dialog === 'pesan' && (auth()->user()?->can('order', $this->prq()) ?? false)
            ? $this->pilihanVendor() : null;
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

        // A-393: vendor dari daftar (kecuali mengisi vendor baru sementara); id lain dari browser ditolak.
        if (trim((string) ($this->order['new_vendor_name'] ?? '')) === '') {
            $this->validate(['order.vendor_id' => [$this->pilihanVendor()->aturan()]], attributes: ['order.vendor_id' => __('Vendor')]);
        }

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
