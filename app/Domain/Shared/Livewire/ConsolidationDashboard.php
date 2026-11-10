<?php

declare(strict_types=1);

namespace App\Domain\Shared\Livewire;

use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard **Ringkasan opname & waste** lintas gudang (Blueprint §18 Fase 2,
 * A-434): akurasi stok per gudang per bulan dan akar masalah memakai laporan
 * yang sudah ada (angka sama dengan laporan), waste dari BA yang ditutup dan isi
 * bin Waste yang belum di-BA. Tanpa nilai uang (D-07); gudang dibatasi cakupan.
 */
class ConsolidationDashboard extends Component
{
    /** A-434: batas warna akurasi (persen). */
    public const AKURASI_BAIK = 98.0;

    public const AKURASI_WASPADA = 95.0;

    #[Url(as: 'bulan', except: '6')]
    public string $bulan = '6';

    #[Url(as: 'gudang', except: '')]
    public string $gudang = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('count.view') || auth()->user()->hasPermission('waste.view'), 403);
    }

    public function render(): View
    {
        $n = in_array($this->bulan, ['3', '6', '12'], true) ? (int) $this->bulan : 6;
        $dari = now()->lokal()->subMonthsNoOverflow($n - 1)->startOfMonth();
        $filter = ['date_from' => $dari->toDateString(), 'date_to' => now()->lokal()->toDateString(), 'warehouse_id' => $this->gudang];
        $bulanList = collect(range($n - 1, 0))->map(fn (int $i) => now()->lokal()->subMonthsNoOverflow($i)->format('m/Y'))->all();
        $user = auth()->user();
        $izinGudang = $user->accessibleWarehouseIds();

        return view('livewire.shared.consolidation-dashboard', [
            'bulanList' => $bulanList,
            'akurasi' => $user->hasPermission('count.view') ? $this->akurasi(app(ReportRegistry::class)->find('tren-akurasi')->rows($filter)) : null,
            'akar' => $user->hasPermission('count.view') ? app(ReportRegistry::class)->find('akar-masalah')->rows($filter)->sortByDesc('baris')->take(5)->values() : null,
            'waste' => $user->hasPermission('waste.view') ? $this->waste($dari->copy()->utc(), $izinGudang) : null,
            'gudangOpsi' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Pivot tren akurasi: gudang × bulan + total periode.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return Collection<string, array{bulan: array<string, float>, sesi: int, dihitung: int, cocok: int, besar: int, akurasi: ?float}>
     */
    private function akurasi(Collection $baris): Collection
    {
        return $baris->groupBy('gudang')->map(fn (Collection $g) => [
            'bulan' => $g->mapWithKeys(fn ($r) => [$r['bulan'] => (float) $r['akurasi']])->all(),
            'sesi' => (int) $g->sum('sesi'),
            'dihitung' => (int) $g->sum('dihitung'),
            'cocok' => (int) $g->sum('cocok'),
            'besar' => (int) $g->sum('besar'),
            'akurasi' => $g->sum('dihitung') > 0 ? round($g->sum('cocok') / $g->sum('dihitung') * 100, 2) : null,
        ])->sortKeys();
    }

    /**
     * Waste per gudang: BA ditutup dalam periode (per disposisi) + isi bin Waste
     * yang belum di-BA; 5 item teratas per jumlah satuan dasar.
     *
     * @param  array<int, int>|null  $izinGudang
     * @return array{perGudang: Collection<int, object>, item: Collection<int, object>}
     */
    private function waste(CarbonInterface $dari, ?array $izinGudang): array
    {
        $pilih = (int) $this->gudang;
        $gudangIds = $pilih > 0 ? [$pilih] : $izinGudang;
        $gudangIds = $gudangIds === null ? null : ($izinGudang === null ? $gudangIds : array_values(array_intersect($gudangIds, $izinGudang)));

        $ditutup = DB::table('waste_disposals as w')
            ->join('warehouses as g', 'g.id', '=', 'w.warehouse_id')
            ->where('w.status', WasteDisposalStatus::Closed->value)->where('w.closed_at', '>=', $dari)
            ->when($gudangIds !== null, fn ($q) => $q->whereIn('w.warehouse_id', $gudangIds ?: [0]))
            ->groupBy('g.code')
            ->selectRaw("g.code as gudang, count(*) as ba, sum(w.disposition = 'disposed') as dibuang, sum(w.disposition = 'sold_scrap') as dijual, sum(w.disposition = 'reused') as dipakai_ulang")
            ->get()->keyBy('gudang');

        $menunggu = DB::table('stock_balances as s')
            ->join('bins as b', 'b.id', '=', 's.bin_id')
            ->join('warehouses as g', 'g.id', '=', 'b.warehouse_id')
            ->where('b.bin_type', BinType::Waste->value)->where('s.qty_base', '>', 0)
            ->when($gudangIds !== null, fn ($q) => $q->whereIn('b.warehouse_id', $gudangIds ?: [0]))
            ->groupBy('g.code')->selectRaw('g.code as gudang, count(distinct s.item_id) as item')
            ->pluck('item', 'gudang');

        $perGudang = collect(array_unique(array_merge($ditutup->keys()->all(), $menunggu->keys()->all())))->sort()->values()
            ->map(fn (string $kode) => (object) [
                'gudang' => $kode,
                'ba' => (int) ($ditutup[$kode]->ba ?? 0),
                'dibuang' => (int) ($ditutup[$kode]->dibuang ?? 0),
                'dijual' => (int) ($ditutup[$kode]->dijual ?? 0),
                'dipakai_ulang' => (int) ($ditutup[$kode]->dipakai_ulang ?? 0),
                'menunggu' => (int) ($menunggu[$kode] ?? 0),
            ]);

        $item = DB::table('waste_disposal_lines as l')
            ->join('waste_disposals as w', 'w.id', '=', 'l.waste_disposal_id')
            ->join('items as i', 'i.id', '=', 'l.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'i.base_uom_id')
            ->where('w.status', WasteDisposalStatus::Closed->value)->where('w.closed_at', '>=', $dari)
            ->when($gudangIds !== null, fn ($q) => $q->whereIn('w.warehouse_id', $gudangIds ?: [0]))
            ->groupBy('i.code', 'i.name', 'u.code')
            ->selectRaw('i.code, i.name, u.code as uom, sum(l.qty_base) as qty')
            ->orderByDesc('qty')->limit(5)->get();

        return ['perGudang' => $perGudang, 'item' => $item];
    }
}
