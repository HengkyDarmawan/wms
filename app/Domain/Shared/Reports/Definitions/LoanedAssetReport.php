<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Asset\Support\AssetCustody;
use App\Domain\Master\Models\Project;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan *Aset dipinjamkan* (25-aset §9, BR-AST-05, BR-AST-06, A-169): aset
 * yang masih di proyek, jatuh tempo kembali, lewat berapa hari, dan hari pakai
 * berjalan — pengganti laporan harian aset lewat jatuh tempo selama modul
 * notifikasi belum ada. Tanpa nilai uang (D-07). Cakupan lewat AST.
 */
class LoanedAssetReport extends Report
{
    public function key(): string
    {
        return 'aset-dipinjamkan';
    }

    public function title(): string
    {
        return 'Aset dipinjamkan';
    }

    public function permission(): string
    {
        return 'asset.view';
    }

    public function description(): string
    {
        return 'Aset yang masih dipinjam proyek: tanggal keluar, jatuh tempo kembali, lewat jatuh tempo (hari), hari pakai berjalan, meter keluar.';
    }

    public function columns(): array
    {
        return [
            'ast' => 'Nomor AST',
            'proyek' => 'Proyek',
            'kode_item' => 'Kode item',
            'serial' => 'Serial',
            'keluar' => 'Keluar',
            'jatuh_tempo' => 'Jatuh tempo',
            'lewat_hari' => 'Lewat (hari)',
            'hari_pakai' => 'Hari pakai',
            'meter_keluar' => 'Meter keluar',
            'status' => 'Status',
        ];
    }

    public function filters(): array
    {
        $ids = auth()->user()?->accessibleProjectIds();

        return [
            'project_id' => ['label' => 'Proyek', 'options' => Project::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
                ->orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->name])->all()],
            'overdue' => ['label' => 'Hanya lewat jatuh tempo', 'options' => ['1' => 'Ya']],
        ];
    }

    public function rows(array $filters): Collection
    {
        $hariIni = now()->startOfDay();

        return AssetHandover::query()
            ->with('project:id,code', 'item:id,code', 'serial:id,serial_no')
            ->where('status', AssetHandoverStatus::CheckedOut->value)
            ->whereNull('lost_at')
            ->when((int) ($filters['project_id'] ?? 0) > 0, fn ($q) => $q->where('project_id', (int) $filters['project_id']))
            ->when(($filters['overdue'] ?? '') === '1', fn ($q) => $q->whereNotNull('due_return_date')->whereDate('due_return_date', '<', $hariIni->toDateString()))
            ->orderBy('due_return_date')->orderBy('id')
            ->get()
            ->map(fn (AssetHandover $a) => [
                'ast' => $a->number,
                'proyek' => $a->project?->code,
                'kode_item' => $a->item?->code,
                'serial' => $a->serial?->serial_no,
                'keluar' => $a->checked_out_at?->format('d/m/Y'),
                'jatuh_tempo' => $a->due_return_date?->format('d/m/Y'),
                'lewat_hari' => $a->due_return_date !== null && $a->due_return_date->lt($hariIni) ? (int) $a->due_return_date->diffInDays($hariIni) : 0,
                'hari_pakai' => AssetCustody::usageDays($a->checked_out_at, now()),
                'meter_keluar' => $a->meter_out !== null ? (float) $a->meter_out : null,
                'status' => $a->isOverdue() ? 'Lewat jatuh tempo' : 'Dipinjam',
            ]);
    }
}
