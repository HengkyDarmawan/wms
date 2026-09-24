<?php

declare(strict_types=1);

namespace App\Domain\Template\Livewire;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Template\Actions\PrintLabels;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 18 §6 — pilih bin/item/lot/potongan lalu cetak labelnya (A-120).
 * Pilihan bertahan antarhalaman; PDF dibuka di tab baru lewat GET.
 */
class LabelPrint extends Component
{
    use WithPagination;

    #[Url(as: 'type')]
    public string $type = 'label_bin';

    #[Url(as: 'warehouse')]
    public string $warehouseFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    public string $paper = '';

    public int $copies = 1;

    /** @var array<int, string> */
    public array $selected = [];

    public function mount(): void
    {
        $this->authorize('label.print');

        if (DocumentTemplateType::tryFrom($this->type)?->isLabel() !== true) {
            $this->type = DocumentTemplateType::LabelBin->value;
        }

        $this->paper = DocumentTemplate::forType(DocumentTemplateType::from($this->type))->paper->value;
    }

    public function updated(string $property): void
    {
        if ($property === 'type') {
            $jenis = DocumentTemplateType::tryFrom($this->type);
            $this->type = $jenis?->isLabel() ? $jenis->value : DocumentTemplateType::LabelBin->value;
            $this->paper = DocumentTemplate::forType(DocumentTemplateType::from($this->type))->paper->value;
            $this->selected = [];
        }

        if (in_array($property, ['type', 'warehouseFilter', 'search'], true)) {
            $this->resetPage();
        }
    }

    /** @param  array<int, int|string>  $ids */
    public function selectPage(array $ids): void
    {
        $this->selected = array_values(array_unique(array_merge($this->selected, array_map('strval', $ids))));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function render(): View
    {
        $jenis = DocumentTemplateType::from($this->type);
        $boleh = auth()->user()->hasPermission($jenis === DocumentTemplateType::LabelBin ? 'bin.view' : 'item.view');
        $copies = max(1, min(PrintLabels::MAKS_SALINAN, $this->copies));

        return view('livewire.template.label-print', [
            'jenisLabel' => DocumentTemplateType::labels(),
            'kertas' => PaperSize::forLabels(),
            'gudang' => $jenis === DocumentTemplateType::LabelBin
                ? Warehouse::query()->orderBy('code')->get(['id', 'code', 'name'])
                : collect(),
            'boleh' => $boleh,
            'rows' => $boleh ? $this->rows($jenis) : null,
            'jumlahLabel' => count($this->selected) * $copies,
            'maks' => PrintLabels::MAKS_LABEL,
            'printUrl' => route('labels.print', [
                'type' => $this->type,
                'ids' => implode(',', $this->selected),
                'paper' => $this->paper,
                'copies' => $copies,
            ]),
        ]);
    }

    private function rows(DocumentTemplateType $jenis): LengthAwarePaginator
    {
        $cari = trim($this->search);

        $query = match ($jenis) {
            DocumentTemplateType::LabelBin => Bin::query()->with('warehouse:id,code')
                ->when($this->warehouseFilter !== '', fn (Builder $q) => $q->where('warehouse_id', (int) $this->warehouseFilter))
                ->when($cari !== '', fn (Builder $q) => $q->where('code', 'like', '%'.$cari.'%'))
                ->orderBy('code'),
            DocumentTemplateType::LabelItem => Item::query()->with('baseUom:id,code')
                ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('code', 'like', '%'.$cari.'%')
                    ->orWhere('name', 'like', '%'.$cari.'%')
                    ->orWhere('barcode', 'like', '%'.$cari.'%')))
                ->orderBy('code'),
            DocumentTemplateType::LabelLot => Lot::query()->with('item:id,code,name')
                ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('lot_no', 'like', '%'.$cari.'%')
                    ->orWhereHas('item', fn (Builder $i) => $i->where('code', 'like', '%'.$cari.'%'))))
                ->orderByDesc('id'),
            default => Piece::query()->with('item:id,code,name')->where('is_consumed', false)
                ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('piece_no', 'like', '%'.$cari.'%')
                    ->orWhereHas('item', fn (Builder $i) => $i->where('code', 'like', '%'.$cari.'%'))))
                ->orderBy('piece_no'),
        };

        return $query->paginate(50);
    }
}
