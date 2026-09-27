<?php

declare(strict_types=1);

namespace App\Domain\Label\Livewire;

use App\Domain\Label\Actions\CancelLabel;
use App\Domain\Label\Actions\CreateContentLabels;
use App\Domain\Label\Actions\CreatePackageLabels;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Layar 19-receipt-putaway §6.3 (A-296) — label kemasan GRN vendor: daftar
 * label induk & isi, buat label untuk barang Baik yang belum berlabel, cetak
 * label isi, cetak (≤200 per PDF), dan batal (Kepala Gudang).
 */
class ReceiptLabels extends Component
{
    public const PER_CETAK = 200;

    #[Locked]
    public int $receiptId;

    /** '', 'buat', 'isi', 'batal' */
    public string $dialog = '';

    /** @var array<int|string, array<string, string>> baris GRN => jumlah dus & isi per dus */
    public array $buat = [];

    #[Locked]
    public ?int $labelId = null;

    public string $nIsi = '';

    public string $alasan = '';

    public string $catatan = '';

    /** @var list<string> id label induk terpilih untuk dicetak */
    public array $pilih = [];

    public string $ruleError = '';

    public string $ruleCode = '';

    public function mount(int $receiptId): void
    {
        $grn = GoodsReceipt::query()->findOrFail($receiptId);
        $this->authorize('view', $grn);

        $this->receiptId = (int) $grn->id;
    }

    #[On('label-dibuat')]
    public function segarkan(): void {}

    public function render(): View
    {
        $grn = $this->grn();
        $induk = PackageLabel::query()->where('goods_receipt_id', $grn->id)->whereNull('parent_id')
            ->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'lot:id,lot_no', 'bin:id,code', 'warehouse:id,code', 'packageUom:id,code',
                'children:id,parent_id,code,qty,qty_remaining,status')
            ->orderBy('item_id')->orderBy('sequence')->get();
        $cetak = $induk->filter(fn (PackageLabel $l) => $l->status->value !== 'cancelled')->pluck('id');

        return view('livewire.label.receipt-labels', [
            'grn' => $grn,
            'induk' => $induk,
            'belum' => $this->barisBelum($grn),
            'cetakSemua' => $cetak->chunk(self::PER_CETAK)->map(fn ($c) => $this->urlCetak($c->all()))->values(),
            'cetakPilih' => $this->pilih === [] ? null : $this->urlCetak(array_slice($this->pilih, 0, self::PER_CETAK)),
            'alasanBatal' => ReasonCode::options(ReasonContext::Cancel),
            'labelDialog' => $this->labelId === null ? null : PackageLabel::query()->with('item.baseUom:id,code')->find($this->labelId),
        ]);
    }

    public function mintaBuat(CreatePackageLabels $labels): void
    {
        $this->authorize('receipt.complete');

        $this->buat = $this->barisBelum($this->grn())
            ->mapWithKeys(function (GoodsReceiptLine $l) use ($labels) {
                $d = $labels->defaults($l);

                return [$l->id => ['packages' => (string) $d['packages'], 'per_package' => PackageLabelLedger::angka($d['per_package']), 'uom_id' => (string) ($d['uom_id'] ?? '')]];
            })->all();
        $this->buka('buat');
    }

    public function buatLabel(CreatePackageLabels $action): void
    {
        $this->authorize('receipt.complete');

        if ($this->jalankan(fn () => $action->handle($this->grn(), $this->buat, auth()->user()))) {
            $this->tutup();
            $this->dispatch('pesan', teks: __('Label kemasan dibuat.'));
        }
    }

    public function mintaIsi(int $id, CreateContentLabels $action): void
    {
        $this->authorize('label.print');

        $label = $this->label($id);
        $this->labelId = (int) $label->id;
        $this->nIsi = (string) $action->suggest($label->loadMissing('item.activeConversions'));
        $this->buka('isi');
    }

    public function buatIsi(CreateContentLabels $action): void
    {
        $this->authorize('label.print');

        $this->validate(['nIsi' => ['required', 'integer', 'min:1', 'max:'.CreateContentLabels::MAKS]], attributes: ['nIsi' => __('Jumlah label isi')]);

        if ($this->jalankan(fn () => $action->handle($this->label((int) $this->labelId), (int) $this->nIsi, auth()->user()))) {
            $this->tutup();
            $this->dispatch('pesan', teks: __('Label isi dibuat; cetak lewat tautan di barisnya.'));
        }
    }

    public function mintaBatal(int $id): void
    {
        $this->authorize('adjustment.approve');

        $this->labelId = (int) $this->label($id)->id;
        $this->buka('batal');
    }

    public function batalkan(CancelLabel $action): void
    {
        $this->authorize('adjustment.approve');

        $alasan = ReasonCode::query()->where('code', $this->alasan)->value('id');

        if ($this->jalankan(fn () => $action->handle($this->label((int) $this->labelId), $alasan === null ? null : (int) $alasan, $this->catatan, auth()->user()))) {
            $this->tutup();
            $this->dispatch('pesan', teks: __('Label dibatalkan.'));
        }
    }

    public function tutup(): void
    {
        $this->dialog = '';
        $this->labelId = null;
        $this->buat = [];
        $this->nIsi = '';
        $this->alasan = '';
        $this->catatan = '';
        $this->resetValidation();
    }

    /** @param  list<int|string>  $ids */
    public function urlCetak(array $ids): string
    {
        return route('labels.print', ['type' => 'label_package', 'ids' => implode(',', $ids)]);
    }

    private function buka(string $dialog): void
    {
        $this->dialog = $dialog;
        $this->ruleError = '';
        $this->ruleCode = '';
        $this->resetValidation();
    }

    private function jalankan(callable $aksi): bool
    {
        $this->ruleError = '';
        $this->ruleCode = '';

        try {
            $aksi();

            return true;
        } catch (LabelRuleException $e) {
            $this->ruleCode = $e->rule;
            $this->ruleError = $e->getMessage();

            return false;
        }
    }

    /** @return Collection<int, GoodsReceiptLine> */
    private function barisBelum(GoodsReceipt $grn)
    {
        $labels = app(CreatePackageLabels::class);

        return $grn->lines()->with('item:id,code,name,tracking_mode,base_uom_id', 'item.baseUom:id,code', 'item.activeConversions', 'receipt')
            ->orderBy('id')->get()->filter(fn (GoodsReceiptLine $l) => $labels->labelable($l))->values();
    }

    private function label(int $id): PackageLabel
    {
        return PackageLabel::query()->where('goods_receipt_id', $this->receiptId)->findOrFail($id);
    }

    private function grn(): GoodsReceipt
    {
        return GoodsReceipt::query()->findOrFail($this->receiptId);
    }
}
