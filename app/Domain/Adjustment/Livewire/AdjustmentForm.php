<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Livewire;

use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Livewire\Concerns\HandlesAdjustmentRules;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Item;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 21-opname-penyesuaian §6.7 — ADJ manual: gudang, Alasan `*`, baris
 * ± per bin/lot/serial/potongan. Dengan `?reversal_of=<id>` layar ini
 * mengajukan ADJ pembalik untuk ADJ yang sudah diposting (BR-GEN-03).
 */
class AdjustmentForm extends Component
{
    use HandlesAdjustmentRules;

    /** @var array<string, string> */
    public array $form = ['warehouse_id' => '', 'reason' => '', 'notes' => ''];

    /** @var array<int, array<string, string>> */
    public array $rows = [];

    #[Locked]
    public ?int $reversalOfId = null;

    public function mount(): void
    {
        $this->authorize('create', StockAdjustment::class);

        $asal = (int) request()->query('reversal_of', 0);

        if ($asal > 0) {
            $adj = StockAdjustment::query()->findOrFail($asal);
            $this->authorize('reverse', $adj);
            $this->reversalOfId = (int) $adj->id;
            $this->form['warehouse_id'] = (string) $adj->warehouse_id;
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

    public function simpan(CreateStockAdjustment $action): void
    {
        $this->authorize('create', StockAdjustment::class);

        $this->validate([
            'form.warehouse_id' => ['required'],
            'form.reason' => ['required', 'string'],
        ], attributes: ['form.warehouse_id' => __('Gudang'), 'form.reason' => __('Alasan')]);

        $alasan = $this->alasanId($this->form['reason'], ReasonContext::Adjustment);
        $adj = null;

        $ok = $this->jalankan(function () use ($action, $alasan, &$adj) {
            if ($this->reversalOfId !== null) {
                $asal = StockAdjustment::query()->findOrFail($this->reversalOfId);
                $this->authorize('reverse', $asal);
                $adj = $action->reverse($asal, $alasan, $this->form['notes'] ?: null, auth()->user());

                return;
            }

            $adj = $action->handle([
                'warehouse_id' => $this->form['warehouse_id'],
                'reason_code_id' => $alasan,
                'notes' => $this->form['notes'],
            ], array_map(fn (array $r) => $r + ['reason_code_id' => null], $this->rows), auth()->user());
        });

        if ($ok && $adj !== null) {
            $this->redirectRoute('adjustments.show', $adj, navigate: true);
        }
    }

    public function render(): View
    {
        $gudangId = (int) $this->form['warehouse_id'];

        return view('livewire.adjustment.adjustment-form', [
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'bins' => $gudangId > 0 ? Bin::query()->where('warehouse_id', $gudangId)->where('is_virtual', false)
                ->where('bin_status', 'active')->orderBy('code')->get(['id', 'code', 'bin_type']) : collect(),
            'items' => Item::query()->orderBy('code')->get(['id', 'code', 'name', 'tracking_mode']),
            'alasan' => $this->pilihanAlasan(ReasonContext::Adjustment),
            'statuses' => StockStatus::options(),
            'asal' => $this->reversalOfId !== null
                ? StockAdjustment::query()->with('lines.item:id,code', 'lines.bin:id,code', 'lines.lot', 'lines.serial', 'lines.piece')->find($this->reversalOfId)
                : null,
        ]);
    }

    /** @return array<string, string> */
    private function barisKosong(): array
    {
        return [
            'direction' => 'in', 'bin_id' => '', 'item_id' => '', 'stock_status' => 'available', 'qty' => '',
            'lot_no' => '', 'expiry_date' => '', 'serial_no' => '', 'piece_no' => '', 'piece_length' => '', 'notes' => '',
        ];
    }
}
