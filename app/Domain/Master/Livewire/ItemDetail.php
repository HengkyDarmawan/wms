<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Enums\ItemKind;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Support\StockFeatures;
use App\Domain\Purchasing\Support\VendorSuggestions;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 11-master §6 — detail item: ringkasan, konversi satuan, vendor tetap,
 * serta lot, serial, dan potongan sebagai daftar **baca-saja**. Baris-baris itu
 * lahir dari transaksi (GRN, konversi, pemilahan retur), tidak diketik manual.
 */
class ItemDetail extends Component
{
    use WithPagination;

    public const TABS = ['ringkasan', 'lot', 'serial', 'potongan', 'riwayat'];

    #[Locked]
    public Item $item;

    #[Url(except: 'ringkasan')]
    public string $tab = 'ringkasan';

    public function mount(Item $item): void
    {
        $this->authorize('view', $item);

        $this->item = $item;

        $this->pilihTab($this->tab);
    }

    public function pilihTab(string $tab): void
    {
        // A-284: tab Potongan hanya bila saklar per potong menyala.
        $sah = in_array($tab, self::TABS, true) && ($tab !== 'potongan' || StockFeatures::piece());

        $this->tab = $sah ? $tab : 'ringkasan';
    }

    public function render(): View
    {
        $this->item->load([
            'category',
            'baseUom.category',
            'weightUom',
            'activeConversions.uom',
            'activeConversions.contentUom',
        ]);

        return view('livewire.master.item-detail', [
            'saranVendor' => $this->tab === 'ringkasan'
                ? app(VendorSuggestions::class)->forItems([(int) $this->item->id])[(int) $this->item->id]
                : ['terakhir' => null, 'termurah' => null],
            'lots' => $this->tab === 'lot'
                ? $this->item->lots()->with('vendor:id,name')->orderByDesc('id')->paginate(15, pageName: 'lot')
                : null,
            'serials' => $this->tab === 'serial'
                ? $this->item->serials()->with('currentProject:id,name')->orderByDesc('id')->paginate(15, pageName: 'serial')
                : null,
            'pieces' => $this->tab === 'potongan'
                ? $this->item->pieces()->orderByDesc('id')->paginate(15, pageName: 'potongan')
                : null,
            'riwayat' => $this->tab === 'riwayat' ? $this->riwayat() : null,
            'jenis' => ItemKind::fromItem($this->item),
            'pieceAktif' => StockFeatures::piece(),
            'qcAktif' => StockFeatures::qc(),
        ]);
    }

    /** BR-GEN-05: jejak perubahan item. */
    private function riwayat(): mixed
    {
        return Activity::query()
            ->where('subject_type', Item::class)
            ->where('subject_id', $this->item->id)
            ->with('causer')
            ->latest('id')
            ->paginate(15, pageName: 'riwayat');
    }
}
