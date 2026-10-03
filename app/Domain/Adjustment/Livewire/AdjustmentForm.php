<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Livewire;

use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Livewire\Concerns\HandlesAdjustmentRules;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Item;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
use App\Domain\Stock\Enums\StockStatus;
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
    use CariPilihan;
    use HandlesAdjustmentRules;

    /** @var array<string, string> */
    public array $form = ['warehouse_id' => '', 'reason' => '', 'notes' => ''];

    /** @var array<int, array<string, string>> */
    public array $rows = [];

    /** BR-WH-10 (A-367): alasan Kepala Gudang membuka bin Khusus Barang Ini. */
    public string $bukaKhusus = '';

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

        // A-402: `?reason=<kode>` (tautan wizard setup "Masukkan saldo awal") mengisi alasan bila kodenya alasan penyesuaian aktif.
        $alasan = strtoupper(trim((string) request()->query('reason', '')));

        if ($alasan !== '' && array_key_exists($alasan, $this->pilihanAlasan(ReasonContext::Adjustment))) {
            $this->form['reason'] = $alasan;
        }

        $this->rows = [$this->barisKosong()];
    }

    /** Ganti gudang = bin baris dikosongkan (daftar bin ikut gudang). */
    public function updatedFormWarehouseId(): void
    {
        foreach (array_keys($this->rows) as $i) {
            $this->rows[$i]['bin_id'] = '';
        }
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

        // A-394: bin & item baris dari daftar; id lain dari browser ditolak di isiannya.
        $this->validate([
            'form.warehouse_id' => ['required'],
            'form.reason' => ['required', 'string'],
        ] + $this->aturanPilihan(), attributes: ['form.warehouse_id' => __('Gudang'), 'form.reason' => __('Alasan')] + $this->namaPilihan());

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
            ], array_map(fn (array $r) => $r + ['reason_code_id' => null, 'buka_khusus' => $this->bukaKhusus], $this->rows), auth()->user());
        });

        if ($ok && $adj !== null) {
            $this->redirectRoute('adjustments.show', $adj, navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.adjustment.adjustment-form', [
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'opsiBin' => $this->pilihanBin()?->awalPerBaris(array_column($this->rows, 'bin_id')) ?? [],
            'opsiItem' => $this->pilihanItem()->awalPerBaris(array_column($this->rows, 'item_id')),
            // Mode pelacakan item baris (lot/serial/potongan) — hanya item di daftar pilihan.
            'itemBarisan' => $this->pilihanItem()->query()
                ->whereIn('id', array_map('intval', array_filter(array_column($this->rows, 'item_id'), 'is_numeric')))
                ->get(['id', 'tracking_mode'])->keyBy('id'),
            'alasan' => $this->pilihanAlasan(ReasonContext::Adjustment),
            'statuses' => StockStatus::options(),
            'asal' => $this->reversalOfId !== null
                ? StockAdjustment::query()->with('lines.item:id,code', 'lines.bin:id,code', 'lines.lot', 'lines.serial', 'lines.piece')->find($this->reversalOfId)
                : null,
        ]);
    }

    /** Bin aktif non-virtual gudang terpilih (daftar lama), dicari ke server (A-394). */
    private function pilihanBin(): ?Pilihan
    {
        $gudangId = (int) $this->form['warehouse_id'];

        return $gudangId > 0 ? SumberPilihan::bin($gudangId) : null;
    }

    /** Semua item, semua status (daftar lama), dicari ke server (A-394). */
    private function pilihanItem(): Pilihan
    {
        return Pilihan::dari(Item::query()->orderBy('code'), ['code', 'name', 'barcode'], fn (Item $i) => [
            'value' => (int) $i->id,
            'text' => $i->code.' — '.$i->name,
        ]);
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        if ($this->reversalOfId !== null || ! auth()->user()?->can('create', StockAdjustment::class)) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/^rows\.\d+\.bin_id$/', $model) => $this->pilihanBin(),
            (bool) preg_match('/^rows\.\d+\.item_id$/', $model) => $this->pilihanItem(),
            default => null,
        };
    }

    /** @return array<string, array<int, mixed>> */
    private function aturanPilihan(): array
    {
        if ($this->reversalOfId !== null) {
            return [];
        }

        $aturan = [];
        $bin = $this->pilihanBin();

        foreach (array_keys($this->rows) as $i) {
            if ($bin !== null) {
                $aturan["rows.$i.bin_id"] = [$bin->aturan()];
            }

            $aturan["rows.$i.item_id"] = [$this->pilihanItem()->aturan()];
        }

        return $aturan;
    }

    /** @return array<string, string> */
    private function namaPilihan(): array
    {
        return collect($this->rows)->keys()->mapWithKeys(fn ($i) => ["rows.$i.bin_id" => __('Bin'), "rows.$i.item_id" => __('Item')])->all();
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
