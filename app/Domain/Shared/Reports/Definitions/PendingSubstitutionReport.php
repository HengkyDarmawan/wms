<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\Project;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/** Laporan 14-request §9 *Penggantian item menunggu tanggapan* (BR-REQ-13): baris yang diganti dan belum dijawab klien. */
class PendingSubstitutionReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'penggantian-menunggu';
    }

    public function title(): string
    {
        return 'Penggantian item menunggu tanggapan';
    }

    public function permission(): string
    {
        return 'request.view';
    }

    public function description(): string
    {
        return 'Baris REQ klien yang itemnya diganti staf dan belum ditanggapi; tenggat lewat berarti dianggap setuju saat pekerjaan harian berjalan.';
    }

    public function columns(): array
    {
        return ['req' => 'REQ', 'proyek' => 'Proyek', 'asal' => 'Item asal', 'pengganti' => 'Item pengganti', 'jumlah' => 'Jumlah', 'diganti' => 'Diganti', 'tenggat' => 'Tenggat', 'lewat' => 'Lewat tenggat'];
    }

    public function filters(): array
    {
        return [
            'project_id' => ['label' => 'Proyek', 'options' => Project::query()->orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->name])->all()],
            'deadline_before' => ['label' => 'Tenggat sebelum tanggal'],
        ];
    }

    public function rows(array $filters): Collection
    {
        $tz = tenant()?->timezone ?? 'Asia/Jakarta';
        $batas = $this->tanggal($filters['deadline_before'] ?? null, $tz)?->endOfDay()->utc();
        $sekarang = now();

        return MaterialRequestLine::query()
            ->with('request:id,number,project_id', 'request.project:id,code', 'item:id,code,name')
            ->whereNotNull('substituted_at')
            ->whereNull('substitution_response')
            ->when($batas !== null, fn ($q) => $q->where('substitution_deadline_at', '<=', $batas))
            ->whereHas('request', fn ($q) => $q->when((int) ($filters['project_id'] ?? 0) > 0, fn ($p) => $p->where('project_id', (int) $filters['project_id'])))
            ->orderBy('substitution_deadline_at')
            ->get()
            ->map(fn (MaterialRequestLine $l) => [
                'req' => $l->request?->number,
                'proyek' => $l->request?->project?->code,
                'asal' => $l->original_item_text ?: ($l->non_catalog_text ?: '—'),
                'pengganti' => trim(($l->item?->code ?? '').' '.($l->item?->name ?? '')) ?: '—',
                'jumlah' => round((float) $l->qty_base, 4),
                'diganti' => $l->substituted_at?->lokal()->format('d/m/Y H:i'),
                'tenggat' => $l->substitution_deadline_at?->lokal()->format('d/m/Y H:i') ?? '—',
                'lewat' => $l->substitution_deadline_at !== null && $l->substitution_deadline_at->lt($sekarang) ? 'Ya' : '—',
            ]);
    }
}
