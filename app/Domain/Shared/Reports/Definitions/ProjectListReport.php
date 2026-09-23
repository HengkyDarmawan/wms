<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Project;
use App\Domain\Shared\Reports\Report;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/** Laporan 11-master §9 — daftar proyek beserta jumlah Gudang Site-nya (A-40). */
class ProjectListReport extends Report
{
    public function key(): string
    {
        return 'daftar-proyek';
    }

    public function title(): string
    {
        return 'Daftar proyek';
    }

    public function permission(): string
    {
        return 'project.view';
    }

    public function description(): string
    {
        return 'Proyek beserta klien, PIC, periode, dan jumlah Gudang Site yang melekat padanya.';
    }

    public function columns(): array
    {
        return [
            'kode' => 'Kode',
            'nama' => 'Nama',
            'klien' => 'Klien',
            'pic' => 'PIC',
            'mulai' => 'Mulai',
            'target_selesai' => 'Target selesai',
            'gudang_site' => 'Jumlah Gudang Site',
            'status' => 'Status',
        ];
    }

    public function filters(): array
    {
        return [
            'status' => ['label' => 'Status', 'options' => ProjectStatus::options()],
            'client_id' => [
                'label' => 'Klien',
                'options' => Client::query()->orderBy('name')->pluck('name', 'id')->all(),
            ],
        ];
    }

    public function rows(array $filters): Collection
    {
        // Dihitung sekali, bukan per baris, supaya laporan tidak memicu query beruntun.
        $siteCount = Warehouse::withoutGlobalScopes()
            ->whereNotNull('project_id')
            ->selectRaw('project_id, count(*) as jumlah')
            ->groupBy('project_id')
            ->pluck('jumlah', 'project_id');

        return Project::query()
            ->with('client:id,name', 'pic:id,name')
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['client_id'] ?? '') !== '', fn ($q) => $q->where('client_id', (int) $filters['client_id']))
            ->orderBy('code')
            ->get()
            ->map(fn (Project $proyek) => [
                'kode' => $proyek->code,
                'nama' => $proyek->name,
                'klien' => $proyek->is_internal ? 'Proyek Internal' : ($proyek->client?->name ?? '—'),
                'pic' => $proyek->pic?->name ?? '—',
                'mulai' => $proyek->start_date?->format('d/m/Y') ?? '—',
                'target_selesai' => $proyek->target_end_date?->format('d/m/Y') ?? '—',
                'gudang_site' => (int) ($siteCount[$proyek->id] ?? 0),
                'status' => $proyek->status->label(),
            ]);
    }
}
