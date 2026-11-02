<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Livewire;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\PurchaseRequest\Actions\CreatePurchaseRequest;
use App\Domain\PurchaseRequest\Livewire\Concerns\HandlesPurchaseRequestRules;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
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
    use CariPilihan;
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
        // A-393: proyek & item dari daftar; id lain dari browser ditolak di isiannya.
        $this->validate($this->aturanPilihan($prq), attributes: $this->namaPilihan());
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
        return view('livewire.purchase-request.purchase-request-form', [
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'opsiProyek' => $this->pilihanProyek()->awalDengan($this->form['project_id']),
            'opsiItem' => $this->pilihanItem()->awalPerBaris(array_column($this->rows, 'item_id')),
            'draf' => $this->purchaseRequestId !== null,
        ]);
    }

    /** Proyek dalam cakupan pengguna, semua status (daftar lama), dicari ke server (A-393). */
    private function pilihanProyek(): Pilihan
    {
        $proyek = auth()->user()?->accessibleProjectIds();

        return Pilihan::dari(
            Project::query()->when($proyek !== null, fn (Builder $q) => $q->whereIn('id', $proyek))->orderBy('code'),
            ['code', 'name'],
            fn (Project $p) => ['value' => (int) $p->id, 'text' => $p->code.' — '.$p->name],
        );
    }

    /** Item aktif & sementara (daftar lama) + satuan dasar, dicari ke server (A-393). */
    private function pilihanItem(): Pilihan
    {
        return Pilihan::dari(
            Item::query()->with('baseUom:id,code')
                ->whereIn('status', [ItemStatus::Active->value, ItemStatus::Provisional->value])->orderBy('code'),
            ['code', 'name', 'barcode'],
            fn (Item $i) => ['value' => (int) $i->id, 'text' => $i->code.' — '.$i->name.' ('.$i->baseUom?->code.')'],
        );
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        $prq = $this->purchaseRequestId === null ? null : PurchaseRequest::query()->find($this->purchaseRequestId);
        $boleh = $prq === null
            ? $this->purchaseRequestId === null && (auth()->user()?->can('create', PurchaseRequest::class) ?? false)
            : (auth()->user()?->can('update', $prq) ?? false);

        return match (true) {
            ! $boleh => null,
            $model === 'form.project_id' => $prq === null ? $this->pilihanProyek() : null,
            (bool) preg_match('/^rows\.\d+\.item_id$/', $model) => $this->pilihanItem(),
            default => null,
        };
    }

    /**
     * Proyek draf (tidak bisa diubah) dan item baris tersimpan dikecualikan
     * supaya draf titik pesan ulang tetap bisa disimpan seperti dulu.
     *
     * @return array<string, array<int, mixed>>
     */
    private function aturanPilihan(?PurchaseRequest $prq): array
    {
        $aturan = $prq === null ? ['form.project_id' => [$this->pilihanProyek()->aturan()]] : [];
        $tersimpan = $prq === null ? [] : $prq->lines()->pluck('item_id')->map(fn ($id) => (string) $id)->all();

        foreach ($this->rows as $i => $r) {
            if (! in_array((string) ($r['item_id'] ?? ''), $tersimpan, true)) {
                $aturan["rows.$i.item_id"] = [$this->pilihanItem()->aturan()];
            }
        }

        return $aturan;
    }

    /** @return array<string, string> */
    private function namaPilihan(): array
    {
        return ['form.project_id' => __('Proyek')]
            + collect($this->rows)->keys()->mapWithKeys(fn ($i) => ["rows.$i.item_id" => __('Item')])->all();
    }
}
