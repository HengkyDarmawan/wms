<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Models\Item;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 11-master §6 — detail item: ringkasan, konversi satuan, vendor tetap,
 * serta lot, serial, dan potongan sebagai daftar **baca-saja**. Baris-baris itu
 * lahir dari transaksi (GRN, konversi, pemilahan retur), tidak diketik manual.
 */
class ItemDetail extends Component
{
    #[Locked]
    public Item $item;

    public string $tab = 'ringkasan';

    public function mount(Item $item): void
    {
        $this->authorize('view', $item);

        $this->item = $item;
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['ringkasan', 'lot', 'serial', 'potongan', 'riwayat'], true)
            ? $tab
            : 'ringkasan';
    }

    public function render(): View
    {
        $this->item->load([
            'category',
            'baseUom.category',
            'weightUom',
            'uomConversions.uom',
            'itemVendors.vendor',
        ]);

        return view('livewire.master.item-detail', [
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
