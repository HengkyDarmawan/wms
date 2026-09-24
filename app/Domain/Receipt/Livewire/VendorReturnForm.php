<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\CreateVendorReturn;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Models\VendorReturn;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 19-receipt-putaway §6.7 — mengajukan RTV dari baris GRN yang ada di
 * Karantina dengan hasil QC Ditolak atau Karantina (BR-GRN-04).
 */
class VendorReturnForm extends Component
{
    use HandlesReceiptRules;

    public string $receiptId = '';

    /** @var array<int|string, array<string, string>> line_id => [qty, reason] */
    public array $isian = [];

    /** @var array<string, string> */
    public array $form = ['notes' => ''];

    public function mount(): void
    {
        $this->authorize('create', VendorReturn::class);

        $id = (int) request()->query('receipt', 0);

        if ($id > 0) {
            $this->receiptId = (string) $id;
            $this->updatedReceiptId();
        }
    }

    public function updatedReceiptId(): void
    {
        $this->isian = [];

        foreach ($this->barisLayak() as $l) {
            $this->isian[$l->id] = [
                'qty' => $l->qc_result === QcResult::Rejected ? (string) (float) $l->qty_received : '0',
                'reason' => (string) ($l->qcReason?->code ?? ''),
            ];
        }
    }

    public function simpan(CreateVendorReturn $action): void
    {
        $this->authorize('create', VendorReturn::class);

        $grn = GoodsReceipt::query()->find((int) $this->receiptId);

        if ($grn === null) {
            $this->addError('receiptId', __('Pilih GRN asal.'));

            return;
        }

        $baris = collect($this->isian)->map(fn ($v, $id) => [
            'goods_receipt_line_id' => (int) $id,
            'qty_base' => (float) ($v['qty'] ?? 0),
            'reason_code_id' => $this->alasanId((string) ($v['reason'] ?? ''), ReasonContext::Reject),
        ])->values()->all();

        $rtv = null;

        $ok = $this->jalankan(function () use ($action, $grn, $baris, &$rtv) {
            $rtv = $action->handle($grn, $baris, $this->form['notes'] ?: null, auth()->user());
        });

        if ($ok && $rtv !== null) {
            $this->redirectRoute('vendor-returns.show', $rtv, navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.receipt.vendor-return-form', [
            'receipts' => GoodsReceipt::query()
                ->with('vendor:id,name')
                ->where('receipt_type', ReceiptType::Vendor->value)
                ->whereIn('status', [GoodsReceiptStatus::Received->value, GoodsReceiptStatus::Completed->value])
                ->whereHas('lines', fn ($q) => $q->whereIn('qc_result', [QcResult::Rejected->value, QcResult::Quarantined->value]))
                ->orderByDesc('id')
                ->get(['id', 'number', 'vendor_id']),
            'lines' => $this->barisLayak(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Reject),
        ]);
    }

    /** @return Collection<int, GoodsReceiptLine> */
    private function barisLayak(): Collection
    {
        $grn = GoodsReceipt::query()->find((int) $this->receiptId);

        if ($grn === null) {
            return collect();
        }

        return $grn->lines()
            ->with('item:id,code,name', 'receivingBin:id,code,bin_type', 'qcReason:id,code', 'lot', 'serial', 'piece')
            ->whereIn('qc_result', [QcResult::Rejected->value, QcResult::Quarantined->value])
            ->orderBy('id')
            ->get()
            ->filter(fn (GoodsReceiptLine $l) => $l->receivingBinIsQuarantine())
            ->values();
    }
}
