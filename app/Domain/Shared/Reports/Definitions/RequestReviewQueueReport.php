<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\CompanySetting;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Livewire\RequestList;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/** Laporan 14-request §9 *REQ menunggu tinjau* (BR-REQ-14): umur di antrean tinjau dan SLA yang terlampaui. */
class RequestReviewQueueReport extends Report
{
    public function key(): string
    {
        return 'req-menunggu-tinjau';
    }

    public function title(): string
    {
        return 'Permintaan menunggu tinjauan';
    }

    public function permission(): string
    {
        return 'request.view';
    }

    public function description(): string
    {
        return 'REQ berstatus Diajukan / Menunggu Tinjauan beserta umurnya; SLA terlampaui mengikuti pengaturan company.';
    }

    public function columns(): array
    {
        return ['nomor' => 'Nomor', 'proyek' => 'Proyek', 'pemohon' => 'Pemohon', 'status' => 'Status', 'umur' => 'Umur (hari)', 'sla' => 'SLA terlampaui'];
    }

    public function filters(): array
    {
        return ['min_age_days' => ['label' => 'Umur minimal (hari)']];
    }

    public function rows(array $filters): Collection
    {
        $sla = max(1, (int) CompanySetting::get(RequestList::SLA, 1));
        $minimal = (int) ($filters['min_age_days'] ?? 0);
        $sekarang = now();

        return MaterialRequest::query()
            ->with('project:id,code', 'requester:id,name')
            ->whereIn('status', [MaterialRequestStatus::Submitted->value, MaterialRequestStatus::UnderReview->value])
            ->orderBy('updated_at')
            ->get()
            ->map(function (MaterialRequest $r) use ($sla, $sekarang) {
                $sejak = $r->updated_at ?? $r->created_at;
                $umur = $sejak === null ? 0 : (int) $sejak->diffInDays($sekarang);

                return [
                    'nomor' => $r->number,
                    'proyek' => $r->project?->code,
                    'pemohon' => $r->requester?->name ?? '—',
                    'status' => $r->status->label(),
                    'umur' => $umur,
                    'sla' => $umur > $sla ? 'Ya ('.($umur - $sla).' hari)' : '—',
                ];
            })
            ->filter(fn ($r) => $r['umur'] >= $minimal)
            ->sortByDesc('umur')
            ->values();
    }
}
