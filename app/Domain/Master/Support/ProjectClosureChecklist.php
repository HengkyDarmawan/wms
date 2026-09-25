<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Serial;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * BR-PRJ-02 — checklist penutupan proyek: tidak boleh ada REQ yang masih
 * diproses (menunggu keputusan s.d. dipenuhi), SJ disiapkan/dalam perjalanan, DSC terbuka, aset dipinjam, atau saldo di
 * **salah satu** Gudang Site proyek. Barang jual-putus yang sudah terkirim ke
 * klien tidak dihitung (BR-PRJ-03). Setiap butir menyebut jalan keluarnya
 * (retur, transfer, ISU, waste — A-187).
 */
class ProjectClosureChecklist
{
    /** @return array<int, string> butir yang menghalangi; kosong = boleh ditutup */
    public function blockers(Project $project): array
    {
        $butir = [];
        $site = $this->siteWarehouses($project);
        $siteIds = $site->pluck('id')->all();

        // REQ yang masih menunggu keputusan juga menghalangi: bila lolos, ia
        // bisa disetujui setelah proyek ditutup.
        $req = MaterialRequest::query()->withoutGlobalScopes()->where('project_id', $project->id)
            ->whereIn('status', [
                MaterialRequestStatus::Submitted->value, MaterialRequestStatus::UnderReview->value, MaterialRequestStatus::PendingApproval->value,
                MaterialRequestStatus::Approved->value, MaterialRequestStatus::InProgress->value, MaterialRequestStatus::PartiallyFulfilled->value,
            ])
            ->pluck('number');

        if ($req->isNotEmpty()) {
            $butir[] = 'REQ masih diproses: '.$this->daftar($req).' — putuskan, selesaikan, tutup sisa, atau batalkan.';
        }

        // SJ yang disiapkan (belum berangkat) juga menghalangi: tujuannya Gudang Site
        // yang akan dinonaktifkan atau proyek yang akan ditutup.
        $sj = Shipment::query()->withoutGlobalScopes()
            ->whereIn('status', [ShipmentStatus::Prepared->value, ShipmentStatus::Shipped->value])
            ->where(fn (Builder $q) => $q->where('destination_project_id', $project->id)
                ->when($siteIds !== [], fn (Builder $w) => $w->orWhereIn('destination_warehouse_id', $siteIds)))
            ->pluck('number');

        if ($sj->isNotEmpty()) {
            $butir[] = 'SJ belum diterima: '.$this->daftar($sj).' — berangkatkan dan tunggu bukti terima, atau batalkan SJ yang masih disiapkan.';
        }

        $dsc = DeliveryDiscrepancy::query()->withoutGlobalScopes()
            ->where('status', DiscrepancyStatus::Open->value)
            ->whereHas('shipment', fn (Builder $q) => $q->withoutGlobalScopes()
                ->where(fn (Builder $w) => $w->where('destination_project_id', $project->id)
                    ->when($siteIds !== [], fn (Builder $x) => $x->orWhereIn('destination_warehouse_id', $siteIds))))
            ->pluck('number');

        if ($dsc->isNotEmpty()) {
            $butir[] = 'Selisih pengiriman terbuka: '.$this->daftar($dsc).' — selesaikan DSC.';
        }

        $aset = Serial::query()->where('current_project_id', $project->id)
            ->where('asset_state', AssetState::OnLoan->value)->pluck('serial_no');

        if ($aset->isNotEmpty()) {
            $butir[] = 'Aset masih dipinjam proyek: '.$this->daftar($aset).' — ajukan retur aset.';
        }

        foreach ($site as $gudang) {
            $saldo = (float) StockBalance::query()->withoutGlobalScopes()
                ->join('bins as cb', 'cb.id', '=', 'stock_balances.bin_id')
                ->where('cb.warehouse_id', $gudang->id)
                ->where('stock_balances.qty_base', '>', 0)
                ->sum('stock_balances.qty_base');

            if ($saldo > 0.00005) {
                $butir[] = 'Gudang Site '.$gudang->code.' masih berisi stok ('.rtrim(rtrim(number_format($saldo, 4, ',', '.'), '0'), ',')
                    .' satuan dasar) — retur ke gudang, transfer ke proyek lain, catat pemakaian (ISU), atau jadikan waste.';
            }
        }

        return $butir;
    }

    /** @return Collection<int, Warehouse> */
    public function siteWarehouses(Project $project): Collection
    {
        return Warehouse::query()->withoutGlobalScopes()->with('type')
            ->where('project_id', $project->id)->get()
            ->filter(fn (Warehouse $w) => $w->isSite())->values();
    }

    /** @param  Collection<int, mixed>  $nilai */
    private function daftar(Collection $nilai): string
    {
        $teks = $nilai->take(5)->implode(', ');

        return $nilai->count() > 5 ? $teks.' (+'.($nilai->count() - 5).' lagi)' : $teks;
    }
}
