<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Livewire;

use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
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
    use CariPilihan;
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

        // A-391: item dari daftar (item aktif); id lain dari browser ditolak di isiannya.
        $this->validate([
            'form.from_warehouse_id' => ['required'],
            'form.to_warehouse_id' => ['required'],
            'rows.*.item_id' => ['nullable', $this->pilihanItem()->aturan()],
        ], attributes: ['form.from_warehouse_id' => __('Gudang asal'), 'form.to_warehouse_id' => __('Gudang tujuan'), 'rows.*.item_id' => __('Item')]);

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
            'opsiGudang' => $gudang->map(fn (Warehouse $g) => ['value' => $g->id, 'text' => $g->code.' — '.$g->name, 'sub' => $g->project?->code])->all(),
            'opsiItem' => $this->pilihanItem()->awalPerBaris(collect($this->rows)->pluck('item_id')->all()),
            'tersedia' => $tersedia,
            'jenis' => $asal !== null && $tujuan !== null && $asal->id !== $tujuan->id
                ? TransferKind::forWarehouses($asal, $tujuan) : null,
        ]);
    }

    /** Item aktif (daftar lama `Item::active()`), dicari ke server (A-391). */
    private function pilihanItem(): Pilihan
    {
        return SumberPilihan::item();
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        if (! preg_match('/^rows\.\d+\.item_id$/', $model) || ! auth()->user()?->can('create', Transfer::class)) {
            return null;
        }

        return $this->pilihanItem();
    }

    /** @return array<string, string> */
    private function barisKosong(): array
    {
        return ['item_id' => '', 'qty_base' => '', 'notes' => ''];
    }
}
