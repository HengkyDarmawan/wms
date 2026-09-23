<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\SaveItem;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\LineOwnership;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\Master\Support\TrackingCombination;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 11-master §6 — form item lengkap.
 *
 * Matriks kombinasi pelacakan diperiksa dua kali: di layar sebagai petunjuk
 * langsung saat mengubah pilihan, dan di {@see SaveItem} sebagai penjaga
 * sebenarnya. Layar boleh salah, aksi tidak boleh.
 */
class ItemForm extends Component
{
    use HandlesMasterRules;

    #[Locked]
    public ?int $itemId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '',
        'name' => '',
        'item_category_id' => '',
        'status' => 'active',
        'ownership_model' => 'consumable',
        'default_line_ownership' => '',
        'tracking_mode' => 'none',
        'has_expiry' => false,
        'base_uom_id' => '',
        'is_cuttable' => false,
        'min_offcut_length' => '',
        'kerf' => '',
        'requires_qc' => false,
        'removal_strategy' => '',
        'reorder_point' => '',
        'min_stock' => '',
        'barcode' => '',
        'weight' => '',
        'weight_uom_id' => '',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $conversions = [];

    /** @var array<int, array<string, mixed>> */
    public array $vendorRows = [];

    public bool $baseUomLocked = false;

    public function mount(?Item $item = null): void
    {
        if ($item !== null && $item->exists) {
            $this->authorize('update', $item);
            $this->isiDari($item);

            return;
        }

        $this->authorize('create', Item::class);
    }

    private function isiDari(Item $item): void
    {
        $this->itemId = $item->id;
        $this->baseUomLocked = $item->baseUomIsLocked();

        $this->form = [
            'code' => (string) $item->code,
            'name' => (string) $item->name,
            'item_category_id' => (string) $item->item_category_id,
            'status' => $item->status->value,
            'ownership_model' => $item->ownership_model->value,
            'default_line_ownership' => $item->default_line_ownership?->value ?? '',
            'tracking_mode' => $item->tracking_mode->value,
            'has_expiry' => (bool) $item->has_expiry,
            'base_uom_id' => (string) $item->base_uom_id,
            'is_cuttable' => (bool) $item->is_cuttable,
            'min_offcut_length' => (string) $item->min_offcut_length,
            'kerf' => (string) $item->kerf,
            'requires_qc' => (bool) $item->requires_qc,
            'removal_strategy' => $item->removal_strategy?->value ?? '',
            'reorder_point' => (string) $item->reorder_point,
            'min_stock' => (string) $item->min_stock,
            'barcode' => (string) $item->barcode,
            'weight' => (string) $item->weight,
            'weight_uom_id' => (string) $item->weight_uom_id,
        ];

        $this->conversions = $item->uomConversions()
            ->get()
            ->map(fn ($k) => [
                'uom_id' => (string) $k->uom_id,
                'qty_base' => (string) $k->qty_base,
                'is_nominal_piece' => (bool) $k->is_nominal_piece,
            ])
            ->all();

        $this->vendorRows = $item->itemVendors()
            ->ordered()
            ->get()
            ->map(fn ($v) => [
                'vendor_id' => (string) $v->vendor_id,
                'priority' => (int) $v->priority,
                'is_preferred' => (bool) $v->is_preferred,
                'notes' => (string) $v->notes,
            ])
            ->all();
    }

    public function tambahKonversi(): void
    {
        $this->conversions[] = ['uom_id' => '', 'qty_base' => '', 'is_nominal_piece' => false];
    }

    public function hapusKonversi(int $index): void
    {
        unset($this->conversions[$index]);
        $this->conversions = array_values($this->conversions);
    }

    public function tambahVendor(): void
    {
        $this->vendorRows[] = [
            'vendor_id' => '',
            'priority' => count($this->vendorRows) + 1,
            'is_preferred' => false,
            'notes' => '',
        ];
    }

    public function hapusVendor(int $index): void
    {
        unset($this->vendorRows[$index]);
        $this->vendorRows = array_values($this->vendorRows);
    }

    public function simpan(SaveItem $action): void
    {
        $item = $this->itemId === null ? null : Item::findOrFail($this->itemId);

        $this->authorize($item === null ? 'create' : 'update', $item ?? Item::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:40'],
            'form.name' => ['required', 'string', 'max:150'],
            'form.base_uom_id' => ['required'],
            'form.tracking_mode' => ['required', Rule::enum(TrackingMode::class)],
            'form.ownership_model' => ['required', Rule::enum(OwnershipModel::class)],
            'form.status' => ['required', Rule::enum(ItemStatus::class)],
            'form.removal_strategy' => ['nullable', Rule::enum(RemovalStrategy::class)],
            'form.default_line_ownership' => ['nullable', Rule::enum(LineOwnership::class)],
            'form.min_offcut_length' => ['nullable', 'numeric', 'min:0'],
            'form.kerf' => ['nullable', 'numeric', 'min:0'],
            'form.reorder_point' => ['nullable', 'numeric', 'min:0'],
            'form.min_stock' => ['nullable', 'numeric', 'min:0'],
            'form.weight' => ['nullable', 'numeric', 'min:0'],
        ], attributes: [
            'form.code' => __('Kode item'),
            'form.name' => __('Nama item'),
            'form.base_uom_id' => __('Satuan dasar'),
            'form.tracking_mode' => __('Mode pelacakan'),
            'form.ownership_model' => __('Model kepemilikan'),
        ]);

        $tersimpan = null;

        $berhasil = $this->jalankan(function () use ($action, $item, &$tersimpan): void {
            $tersimpan = $action->handle(
                $item,
                $this->form,
                array_values($this->conversions),
                array_values($this->vendorRows),
                auth()->user(),
            );
        });

        if (! $berhasil) {
            return;
        }

        session()->flash('pesan', __('Item disimpan.'));

        $this->redirectRoute('items.show', $tersimpan, navigate: true);
    }

    /**
     * Petunjuk langsung di layar dari matriks kombinasi, supaya user tahu
     * kenapa sebuah pilihan tidak tersedia sebelum menekan Simpan.
     *
     * @return array<string, string>
     */
    public function petunjuk(TrackingCombination $combination): array
    {
        $pelacakan = TrackingMode::tryFrom((string) $this->form['tracking_mode']) ?? TrackingMode::None;
        $kepemilikan = OwnershipModel::tryFrom((string) $this->form['ownership_model']) ?? OwnershipModel::Consumable;
        $strategi = RemovalStrategy::tryFrom((string) $this->form['removal_strategy']);

        $satuan = $this->form['base_uom_id'] === ''
            ? null
            : Uom::query()->with('category')->find((int) $this->form['base_uom_id']);

        return $combination->violations(
            $pelacakan,
            $kepemilikan,
            $strategi,
            (bool) $this->form['has_expiry'],
            (bool) $this->form['is_cuttable'],
            $this->form['min_offcut_length'] === '' ? null : (float) $this->form['min_offcut_length'],
            $satuan?->category?->isLength(),
        );
    }

    public function render(TrackingCombination $combination): View
    {
        $pelacakan = TrackingMode::tryFrom((string) $this->form['tracking_mode']) ?? TrackingMode::None;

        $strategiTersedia = [];

        foreach ($pelacakan->allowedRemovalStrategies() as $strategi) {
            $strategiTersedia[$strategi->value] = $strategi->label();
        }

        $kepemilikanTersedia = [];

        foreach ($pelacakan->allowedOwnershipModels() as $model) {
            $kepemilikanTersedia[$model->value] = $model->label();
        }

        return view('livewire.master.item-form', [
            'categories' => ItemCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'uoms' => Uom::query()->active()->with('category:id,name')->orderBy('code')->get(),
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'trackingModes' => TrackingMode::options(),
            'ownerships' => $kepemilikanTersedia,
            'lineOwnerships' => LineOwnership::options(),
            'strategies' => $strategiTersedia,
            'statuses' => ItemStatus::options(),
            'bolehKedaluwarsa' => $pelacakan->allowsExpiry(),
            'petunjuk' => $this->petunjuk($combination),
        ]);
    }
}
