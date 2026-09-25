<?php

declare(strict_types=1);

namespace App\Domain\Issue\Support;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan **Material per Proyek** (Blueprint §9 laporan inti, BR-PRJ-08, A-151):
 * per proyek × item, dalam satuan dasar, tanpa nilai uang (D-07).
 *
 * - *Diminta*  — baris REQ proyek yang masih berlaku (bukan draf/ditolak/batal).
 * - *Terkirim* — dibaca dari kartu stok: barang yang masuk ke penguasaan proyek
 *   dari luar proyek (GRN/SJ ke bin Gudang Site proyek atau bin On-site proyek)
 *   ditambah barang jual-putus yang diterima klien (SJ keluar ledger ber-proyek).
 *   Pemindahan antar titik dalam proyek yang sama tidak dihitung (A-50).
 * - *Terpakai* — pergerakan ISU (keluar) dikurangi ISU pembalik.
 * - *Diretur*  — GRN retur ber-proyek yang masuk bin Retur (A-112).
 * - *Di Gudang Site* / *Aset di Proyek* — saldo sekarang (BR-PRJ-05).
 * - *Dikonversi* / *Hasil konversi* — input CNV keluar / output + offcut CNV
 *   masuk, dikurangi CNV pembalik (A-161).
 * - *Waste* — waste CNV dan pilah RET ke bin Waste ber-proyek, dikurangi pembalik.
 * - *Waste didisposisi* — isi bin Waste yang ditutup WST `closed` (A-161).
 *
 * Kolom *Rencana* `[F2]` (BR-PRJ-09) belum.
 */
class ProjectMaterialSummary
{
    /**
     * @param  array<int, int>|null  $projectIds  null = semua proyek
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(?array $projectIds, string $itemSearch = ''): Collection
    {
        $proyek = Project::query()
            ->when($projectIds !== null, fn ($q) => $q->whereIn('id', $projectIds === [] ? [0] : $projectIds))
            ->orderBy('code')->get(['id', 'code', 'name'])->keyBy('id');

        if ($proyek->isEmpty()) {
            return collect();
        }

        $pids = $proyek->keys()->map(fn ($id) => (int) $id)->all();
        $angka = [];
        $tambah = function (int $projectId, int $itemId, string $kolom, float $qty) use (&$angka): void {
            $angka[$projectId][$itemId][$kolom] = round(($angka[$projectId][$itemId][$kolom] ?? 0) + $qty, 4);
        };

        // Bin dalam penguasaan proyek: semua bin Gudang Site proyek + bin On-site proyek.
        $gudangSite = Warehouse::query()->withoutGlobalScopes()->whereIn('project_id', $pids)->pluck('project_id', 'id');
        $binSite = Bin::query()->withoutGlobalScopes()->whereIn('warehouse_id', $gudangSite->keys()->all() ?: [0])
            ->get(['id', 'warehouse_id'])
            ->mapWithKeys(fn (Bin $b) => [(int) $b->id => (int) $gudangSite[$b->warehouse_id]])
            ->all();
        $binOnSite = Bin::query()->withoutGlobalScopes()->where('bin_type', BinType::OnSite->value)->whereIn('project_id', $pids)
            ->pluck('project_id', 'id')->map(fn ($p) => (int) $p)->all();
        $penguasaan = $binSite + $binOnSite;

        // Diminta.
        DB::table('material_request_lines as l')
            ->join('material_requests as r', 'r.id', '=', 'l.material_request_id')
            ->whereIn('r.project_id', $pids)
            ->whereNotIn('r.status', [MaterialRequestStatus::Draft->value, MaterialRequestStatus::Rejected->value, MaterialRequestStatus::Cancelled->value])
            ->where('l.status', '!=', RequestLineStatus::Cancelled->value)
            ->whereNotNull('l.item_id')
            ->groupBy('r.project_id', 'l.item_id')
            ->selectRaw('r.project_id, l.item_id, sum(l.qty_base) as jumlah')
            ->get()
            ->each(fn ($r) => $tambah((int) $r->project_id, (int) $r->item_id, 'diminta', (float) $r->jumlah));

        // Terkirim (1): masuk ke penguasaan proyek dari luar proyek (GRN ke Gudang Site, aset ke On-site).
        if ($penguasaan !== []) {
            StockMovement::query()
                ->whereIn('document_type', ['goods_receipt', 'shipment'])
                ->whereIn('to_bin_id', array_keys($penguasaan))
                ->get(['item_id', 'from_bin_id', 'to_bin_id', 'qty_base'])
                ->each(function (StockMovement $m) use ($penguasaan, $tambah): void {
                    $ke = $penguasaan[(int) $m->to_bin_id];
                    $dari = $m->from_bin_id !== null ? ($penguasaan[(int) $m->from_bin_id] ?? null) : null;

                    if ($dari !== $ke) {
                        $tambah($ke, (int) $m->item_id, 'terkirim', (float) $m->qty_base);
                    }
                });
        }

        // Terkirim (2): jual-putus diterima klien — SJ keluar ledger dengan penanda proyek.
        StockMovement::query()
            ->where('document_type', 'shipment')
            ->whereNull('to_bin_id')
            ->whereIn('project_id', $pids)
            ->groupBy('project_id', 'item_id')
            ->selectRaw('project_id, item_id, sum(qty_base) as jumlah')
            ->get()
            ->each(fn ($r) => $tambah((int) $r->project_id, (int) $r->item_id, 'terkirim', (float) $r->jumlah));

        // Terpakai: ISU keluar dikurangi ISU pembalik (masuk kembali).
        StockMovement::query()
            ->where('document_type', 'material_issue')
            ->whereIn('project_id', $pids)
            ->get(['project_id', 'item_id', 'from_bin_id', 'to_bin_id', 'qty_base'])
            ->each(fn (StockMovement $m) => $tambah((int) $m->project_id, (int) $m->item_id, 'terpakai',
                $m->to_bin_id === null ? (float) $m->qty_base : -1 * (float) $m->qty_base));

        // Diretur: GRN retur ber-proyek ke bin Retur.
        $binRetur = Bin::query()->withoutGlobalScopes()->where('bin_type', BinType::Return->value)->pluck('id')->all();

        if ($binRetur !== []) {
            StockMovement::query()
                ->where('document_type', 'goods_receipt')
                ->whereIn('project_id', $pids)
                ->whereIn('to_bin_id', $binRetur)
                ->groupBy('project_id', 'item_id')
                ->selectRaw('project_id, item_id, sum(qty_base) as jumlah')
                ->get()
                ->each(fn ($r) => $tambah((int) $r->project_id, (int) $r->item_id, 'diretur', (float) $r->jumlah));
        }

        // Konversi & waste (A-161): dibaca dari kartu stok CNV, pilah RET, dan WST.
        $binWaste = array_flip(Bin::query()->withoutGlobalScopes()->where('bin_type', BinType::Waste->value)->pluck('id')->map(fn ($id) => (int) $id)->all());

        StockMovement::query()
            ->where('document_type', 'conversion')
            ->whereIn('project_id', $pids)
            ->get(['project_id', 'item_id', 'from_bin_id', 'to_bin_id', 'qty_base', 'reverses_movement_id'])
            ->each(function (StockMovement $m) use ($binWaste, $tambah): void {
                $tanda = $m->reverses_movement_id === null ? 1 : -1;
                // Arah asli: pembalik menukar asal dan tujuan.
                $keluar = $tanda === 1 ? $m->to_bin_id === null : $m->from_bin_id === null;
                $binHasil = $tanda === 1 ? $m->to_bin_id : $m->from_bin_id;
                $kolom = match (true) {
                    $keluar => 'dikonversi',
                    isset($binWaste[(int) $binHasil]) => 'waste',
                    default => 'hasil_konversi',
                };

                $tambah((int) $m->project_id, (int) $m->item_id, $kolom, $tanda * (float) $m->qty_base);
            });

        if ($binWaste !== []) {
            StockMovement::query()
                ->where('document_type', 'goods_return')
                ->whereIn('project_id', $pids)
                ->whereIn('to_bin_id', array_keys($binWaste))
                ->groupBy('project_id', 'item_id')
                ->selectRaw('project_id, item_id, sum(qty_base) as jumlah')
                ->get()
                ->each(fn ($r) => $tambah((int) $r->project_id, (int) $r->item_id, 'waste', (float) $r->jumlah));
        }

        StockMovement::query()
            ->where('document_type', 'waste_disposal')
            ->whereIn('project_id', $pids)
            ->groupBy('project_id', 'item_id')
            ->selectRaw('project_id, item_id, sum(qty_base) as jumlah')
            ->get()
            ->each(fn ($r) => $tambah((int) $r->project_id, (int) $r->item_id, 'waste_didisposisi', (float) $r->jumlah));

        // Posisi sekarang.
        foreach (['di_site' => $binSite, 'aset_proyek' => $binOnSite] as $kolom => $bins) {
            if ($bins === []) {
                continue;
            }

            StockBalance::query()->whereIn('bin_id', array_keys($bins))->nonZero()
                ->get(['bin_id', 'item_id', 'qty_base'])
                ->each(fn (StockBalance $s) => $tambah($bins[(int) $s->bin_id], (int) $s->item_id, $kolom, (float) $s->qty_base));
        }

        $itemIds = collect($angka)->flatMap(fn (array $perItem) => array_keys($perItem))->unique()->all();
        $item = Item::query()->with('baseUom:id,code')->whereIn('id', $itemIds ?: [0])->get(['id', 'code', 'name', 'base_uom_id'])->keyBy('id');
        $cari = mb_strtolower(trim($itemSearch));

        $hasil = collect();

        foreach ($angka as $projectId => $perItem) {
            foreach ($perItem as $itemId => $nilai) {
                $i = $item->get($itemId);

                if ($cari !== '' && ! str_contains(mb_strtolower($i?->code.' '.$i?->name), $cari)) {
                    continue;
                }

                $hasil->push([
                    'project_id' => (int) $projectId,
                    'project_code' => $proyek[$projectId]?->code,
                    'project_name' => $proyek[$projectId]?->name,
                    'item_id' => (int) $itemId,
                    'item_code' => $i?->code,
                    'item_name' => $i?->name,
                    'uom' => $i?->baseUom?->code,
                    'diminta' => $nilai['diminta'] ?? 0.0,
                    'terkirim' => $nilai['terkirim'] ?? 0.0,
                    'terpakai' => $nilai['terpakai'] ?? 0.0,
                    'diretur' => $nilai['diretur'] ?? 0.0,
                    'di_site' => $nilai['di_site'] ?? 0.0,
                    'aset_proyek' => $nilai['aset_proyek'] ?? 0.0,
                    'dikonversi' => $nilai['dikonversi'] ?? 0.0,
                    'hasil_konversi' => $nilai['hasil_konversi'] ?? 0.0,
                    'waste' => $nilai['waste'] ?? 0.0,
                    'waste_didisposisi' => $nilai['waste_didisposisi'] ?? 0.0,
                ]);
            }
        }

        return $hasil->sortBy(fn (array $r) => $r['project_code'].'|'.$r['item_code'])->values();
    }
}
