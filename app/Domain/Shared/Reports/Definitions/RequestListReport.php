<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\Project;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/** Laporan 14-request §9 *Daftar REQ*: permintaan material per periode pembuatan; cakupan lewat global scope dokumen (BR-ACC-05). */
class RequestListReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'daftar-req';
    }

    public function title(): string
    {
        return 'Daftar permintaan material';
    }

    public function permission(): string
    {
        return 'request.view';
    }

    public function description(): string
    {
        return 'REQ yang dibuat dalam rentang tanggal: proyek, pemohon, status, jumlah baris, dan tanggal dibutuhkan.';
    }

    public function columns(): array
    {
        return ['nomor' => 'Nomor', 'dibuat' => 'Dibuat', 'proyek' => 'Proyek', 'pemohon' => 'Pemohon', 'status' => 'Status', 'baris' => 'Jumlah baris', 'dibutuhkan' => 'Dibutuhkan'];
    }

    public function filters(): array
    {
        return [
            'status' => ['label' => 'Status', 'options' => MaterialRequestStatus::options()],
            'project_id' => ['label' => 'Proyek', 'options' => Project::query()->orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->name])->all()],
            'requester' => ['label' => 'Pemohon mengandung'],
        ] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $pemohon = trim((string) ($filters['requester'] ?? ''));

        return MaterialRequest::query()
            ->with('project:id,code', 'requester:id,name')
            ->withCount('lines')
            ->whereBetween('created_at', [$dari, $sampai])
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when((int) ($filters['project_id'] ?? 0) > 0, fn ($q) => $q->where('project_id', (int) $filters['project_id']))
            ->when($pemohon !== '', fn ($q) => $q->whereHas('requester', fn ($u) => $u->where('name', 'like', '%'.$pemohon.'%')))
            ->orderByDesc('id')
            ->limit(2000)
            ->get()
            ->map(fn (MaterialRequest $r) => [
                'nomor' => $r->number,
                'dibuat' => $r->created_at?->lokal()->format('d/m/Y'),
                'proyek' => $r->project?->code,
                'pemohon' => $r->requester?->name ?? '—',
                'status' => $r->status->label(),
                'baris' => (int) $r->lines_count,
                'dibutuhkan' => $r->required_date?->format('d/m/Y') ?? '—',
            ]);
    }
}
