<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Livewire;

use App\Domain\Master\Models\Item;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Actions\CreateTransfer;
use App\Domain\Transfer\Enums\TransferKind;
use App\Domain\Transfer\Livewire\Concerns\HandlesTransferRules;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 22-retur-transfer §6 — TRF baru: gudang asal `*`, gudang tujuan `*`
 * (Gudang Site menurunkan proyeknya; dua titik satu proyek = transfer dalam
 * proyek, A-50), baris item × jumlah. Stok tersedia gudang asal tampil per baris.
 */
class TransferForm extends Component
{
    use HandlesTransferRules;

    /** @var array<string, string> */
    public array $form = ['from_warehouse_id' => '', 'to_warehouse_id' => '', 'notes' => ''];

    /** @var array<int, array<string, string>> */
    public array $rows = [];

    public function mount(): void
    {
        $this->authorize('create', Transfer::class);

        // Dari hub proyek (A-228): gudang asal = Gudang Site proyek itu.
        $asal = request()->query('from_warehouse');

        if (is_numeric($asal) && Warehouse::query()->where('is_active', true)->whereKey((int) $asal)->exists()) {
            $this->form['from_warehouse_id'] = (string) (int) $asal;
        }

        $this->rows = [$this->barisKosong()];
    }

    public function tambahBaris(): void
    {
        $this->rows[] = $this->barisKosong();
    }

    public function hapusBaris(int $i): void
    {
        unset($this->rows[$i]);
        $this->rows = array_values($this->rows) ?: [$this->barisKosong()];
    }

    public function simpan(CreateTransfer $action): void
    {
        $this->authorize('create', Transfer::class);

        $this->validate([
            'form.from_warehouse_id' => ['required'],
            'form.to_warehouse_id' => ['required'],
        ], attributes: ['form.from_warehouse_id' => __('Gudang asal'), 'form.to_warehouse_id' => __('Gudang tujuan')]);

        $trf = null;

        $ok = $this->jalankan(function () use ($action, &$trf) {
            $trf = $action->handle($this->form, $this->rows, auth()->user());
        });

        if ($ok && $trf !== null) {
            $this->redirectRoute('transfers.show', $trf, navigate: true);
        }
    }

    public function render(): View
    {
        $gudang = Warehouse::query()->withoutGlobalScopes()->with('type', 'project:id,code')
            ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'warehouse_type_id', 'project_id']);

        $asal = $gudang->firstWhere('id', (int) $this->form['from_warehouse_id']);
        $tujuan = $gudang->firstWhere('id', (int) $this->form['to_warehouse_id']);
        $ledger = app(StockLedger::class);

        $tersedia = [];

        if ($asal !== null) {
            foreach ($this->rows as $i => $r) {
                if ((int) ($r['item_id'] ?? 0) > 0) {
                    $tersedia[$i] = $ledger->availableQty((int) $r['item_id'], (int) $asal->id);
                }
            }
        }

        return view('livewire.transfer.transfer-form', [
            'warehouses' => $gudang,
            'items' => Item::query()->active()->orderBy('code')->get(['id', 'code', 'name', 'tracking_mode']),
            'tersedia' => $tersedia,
            'jenis' => $asal !== null && $tujuan !== null && $asal->id !== $tujuan->id
                ? TransferKind::forWarehouses($asal, $tujuan) : null,
        ]);
    }

    /** @return array<string, string> */
    private function barisKosong(): array
    {
        return ['item_id' => '', 'qty_base' => '', 'notes' => ''];
    }
}
