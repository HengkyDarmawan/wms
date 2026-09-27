<?php

declare(strict_types=1);

namespace App\Domain\Template\Livewire;

use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Support\StockFeatures;
use App\Domain\Template\Actions\PrintLabels;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 18 §6 — pilih bin/item/lot/potongan lalu cetak labelnya dengan
 * ukuran dari master ukuran label (A-261) dan desain jenis × ukuran (A-262).
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

    public string $formatId = '';

    public int $copies = 1;

    /** @var array<int, string> */
    public array $selected = [];

    public function mount(): void
    {
        $this->authorize('label.print');

        if (DocumentTemplateType::tryFrom($this->type)?->isLabelAvailable(StockFeatures::piece()) !== true) {
            $this->type = DocumentTemplateType::LabelBin->value;
        }

        $this->formatId = (string) (LabelFormat::defaultFor(DocumentTemplateType::from($this->type))?->id ?? '');
    }

    public function updated(string $property): void
    {
        if ($property === 'type') {
            $jenis = DocumentTemplateType::tryFrom($this->type);
            $this->type = $jenis?->isLabelAvailable(StockFeatures::piece()) ? $jenis->value : DocumentTemplateType::LabelBin->value;
            $this->formatId = (string) (LabelFormat::defaultFor(DocumentTemplateType::from($this->type))?->id ?? '');
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
            'jenisLabel' => DocumentTemplateType::labels(StockFeatures::piece()),
            'formats' => LabelFormat::query()->active()->orderBy('media')->orderBy('code')->get(),
            'bisaDesain' => auth()->user()->can('document_layout.manage'),
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
                'format' => $this->formatId,
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
            // A-296: label kemasan Di gudang (terbaru dulu); cari kode label, item, atau nomor GRN.
            DocumentTemplateType::LabelPackage => PackageLabel::query()->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'receipt:id,number', 'warehouse:id,code')
                ->where('status', PackageLabelStatus::InStock->value)
                ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('code', 'like', '%'.$cari.'%')
                    ->orWhereHas('item', fn (Builder $i) => $i->where('code', 'like', '%'.$cari.'%'))
                    ->orWhereHas('receipt', fn (Builder $r) => $r->withoutGlobalScopes()->where('number', 'like', '%'.$cari.'%'))))
                ->orderByDesc('id'),
            // A-298: label serial alat.
            DocumentTemplateType::LabelSerial => Serial::query()->with('item:id,code,name')
                ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('serial_no', 'like', '%'.$cari.'%')
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
