<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Livewire;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\Purchasing\Support\PurchasePriceHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Riwayat harga beli satu item (A-309, purchasing/02 §6): baris PO, ringkasan
 * per vendor 3/6/12 bulan, grafik harga, dan ekspor Excel lewat laporan
 * `riwayat-harga-beli`. Izin `po.view` (D-07).
 */
class PriceHistory extends Component
{
    #[Locked]
    public int $itemId;

    #[Url(as: 'vendor', except: '')]
    public string $vendor = '';

    #[Url(as: 'dari', except: '')]
    public string $dari = '';

    #[Url(as: 'sampai', except: '')]
    public string $sampai = '';

    public function mount(Item $item): void
    {
        $this->authorize('po.view');

        $this->itemId = (int) $item->id;
    }

    public function render(PurchasePriceHistory $riwayat): View
    {
        $item = Item::query()->with('baseUom:id,code')->findOrFail($this->itemId);
        $dari = $this->tanggal($this->dari) ?? now()->subMonths(12)->startOfDay();
        $sampai = $this->tanggal($this->sampai)?->endOfDay();
        $vendor = is_numeric($this->vendor) ? (int) $this->vendor : null;

        $baris = $riwayat->lines($this->itemId, $vendor, $dari, $sampai);
        // Ringkasan 3/6/12 bulan tidak dibatasi penyaring periode.
        $ringkas = $riwayat->summary($riwayat->lines($this->itemId, $vendor, now()->subMonths(12)->startOfDay()));

        return view('livewire.purchasing.price-history', [
            'item' => $item,
            'baris' => $baris,
            'ringkas' => $ringkas,
            'grafik' => $this->grafik($baris->where('dihitung', true)),
            'vendors' => Vendor::query()->whereIn('id', $riwayat->lines($this->itemId)->pluck('vendor_id')->unique())->orderBy('name')->get(['id', 'name']),
            'ekspor' => route('reports.export', ['report' => 'riwayat-harga-beli', 'filters' => array_filter([
                'item' => $item->code, 'vendor_id' => $vendor, 'date_from' => $dari->toDateString(), 'date_to' => $sampai?->toDateString(),
            ])]),
        ]);
    }

    /**
     * Titik grafik garis per vendor (SVG 600×200, tanpa pustaka).
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return array{garis: list<array{vendor: string, warna: string, titik: string, bulatan: list<array{x: float, y: float, label: string}>}>, min: float, max: float, awal: ?string, akhir: ?string}
     */
    private function grafik(Collection $baris): array
    {
        if ($baris->isEmpty()) {
            return ['garis' => [], 'min' => 0, 'max' => 0, 'awal' => null, 'akhir' => null];
        }

        $t0 = $baris->min(fn ($l) => $l['tanggal']->timestamp);
        $t1 = max($baris->max(fn ($l) => $l['tanggal']->timestamp), $t0 + 86400);
        $h0 = (float) $baris->min('harga');
        $h1 = max((float) $baris->max('harga'), $h0 + 1);
        $warna = ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed', '#0891b2'];
        $garis = [];

        foreach ($baris->groupBy('vendor_id')->values() as $i => $g) {
            $pt = $g->sortBy(fn ($l) => $l['tanggal']->timestamp)->map(fn ($l) => [
                'x' => round(40 + ($l['tanggal']->timestamp - $t0) / ($t1 - $t0) * 550, 1),
                'y' => round(190 - ($l['harga'] - $h0) / ($h1 - $h0) * 170, 1),
                'label' => $l['tanggal']->format('d/m/Y').' · '.$l['po'],
            ])->values();

            $garis[] = [
                'vendor' => $g->first()['vendor'],
                'warna' => $warna[$i % count($warna)],
                'titik' => $pt->map(fn ($p) => $p['x'].','.$p['y'])->implode(' '),
                'bulatan' => $pt->all(),
            ];
        }

        return ['garis' => $garis, 'min' => $h0, 'max' => $h1, 'awal' => Carbon::createFromTimestamp($t0)->format('m/Y'), 'akhir' => Carbon::createFromTimestamp($t1)->format('m/Y')];
    }

    private function tanggal(string $nilai): ?Carbon
    {
        try {
            return trim($nilai) === '' ? null : Carbon::parse($nilai)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
