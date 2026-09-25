<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Livewire;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\PurchaseRequest\Actions\CreatePurchaseRequest;
use App\Domain\PurchaseRequest\Livewire\Concerns\HandlesPurchaseRequestRules;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 26-purchase-request §6.2 — PRQ manual (`pr.create`, langsung diajukan)
 * atau tinjau draf titik pesan ulang (`pr.submit`, BR-REQ-11).
 */
class PurchaseRequestForm extends Component
{
    use HandlesPurchaseRequestRules;

    #[Locked]
    public ?int $purchaseRequestId = null;

    /** @var array<string, string> */
    public array $form = ['warehouse_id' => '', 'project_id' => '', 'notes' => ''];

    /** @var array<int, array<string, string>> */
    public array $rows = [];

    public function mount(?PurchaseRequest $purchaseRequest = null): void
    {
        if ($purchaseRequest !== null && $purchaseRequest->exists) {
            $this->authorize('update', $purchaseRequest);
            $this->purchaseRequestId = (int) $purchaseRequest->id;
            $this->form = [
                'warehouse_id' => (string) $purchaseRequest->warehouse_id,
                'project_id' => (string) ($purchaseRequest->project_id ?? ''),
                'notes' => (string) ($purchaseRequest->notes ?? ''),
            ];

            foreach ($purchaseRequest->lines()->orderBy('id')->get() as $l) {
                $this->rows[] = [
                    'item_id' => (string) $l->item_id,
                    'qty_base' => (string) (float) $l->qty_base,
                    'required_date' => (string) ($l->required_date?->toDateString() ?? ''),
                    'notes' => (string) ($l->notes ?? ''),
                ];
            }

            return;
        }

        $this->authorize('create', PurchaseRequest::class);

        $gudang = Warehouse::query()->active()->orderBy('code')->first();
        $this->form['warehouse_id'] = $gudang === null ? '' : (string) $gudang->id;
        $this->tambahBaris();
    }

    public function tambahBaris(): void
    {
        $this->rows[] = ['item_id' => '', 'qty_base' => '', 'required_date' => '', 'notes' => ''];
    }

    public function hapusBaris(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function simpan(CreatePurchaseRequest $action): void
    {
        $prq = $this->purchaseRequestId !== null ? PurchaseRequest::query()->findOrFail($this->purchaseRequestId) : null;

        $prq !== null ? $this->authorize('update', $prq) : $this->authorize('create', PurchaseRequest::class);

        $this->resetValidation();
        $hasil = null;

        $ok = $this->jalankan(function () use ($action, $prq, &$hasil) {
            $hasil = $prq === null
                ? $action->handle($this->form, $this->rows, auth()->user())
                : $action->update($prq, $this->form, $this->rows, auth()->user());
        });

        if ($ok && $hasil !== null) {
            $this->redirectRoute('purchase-requests.show', $hasil, navigate: true);
        }
    }

    public function render(): View
    {
        $proyek = auth()->user()?->accessibleProjectIds();

        return view('livewire.purchase-request.purchase-request-form', [
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'projects' => Project::query()
                ->when($proyek !== null, fn (Builder $q) => $q->whereIn('id', $proyek))
                ->orderBy('code')->get(['id', 'code', 'name']),
            'items' => Item::query()->with('baseUom:id,code')
                ->whereIn('status', [ItemStatus::Active->value, ItemStatus::Provisional->value])
                ->orderBy('code')->get(['id', 'code', 'name', 'base_uom_id']),
            'draf' => $this->purchaseRequestId !== null,
        ]);
    }
}
