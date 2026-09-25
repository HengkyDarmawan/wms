<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\Project;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/** Laporan 14-request §9 *Baris tanpa sumber*: baris REQ terbuka yang belum punya gudang/sumber pemenuhan. */
class UnsourcedLineReport extends Report
{
    public function key(): string
    {
        return 'baris-tanpa-sumber';
    }

    public function title(): string
    {
        return 'Baris permintaan tanpa sumber';
    }

    public function permission(): string
    {
        return 'request.view';
    }

    public function description(): string
    {
        return 'Baris REQ terbuka yang belum ditetapkan sumber pemenuhannya (stok, transfer, atau pembelian) dan gudang asalnya.';
    }

    public function columns(): array
    {
        return ['req' => 'REQ', 'proyek' => 'Proyek', 'status' => 'Status REQ', 'item' => 'Item', 'jumlah' => 'Jumlah', 'satuan' => 'Satuan', 'dibutuhkan' => 'Dibutuhkan'];
    }

    public function filters(): array
    {
        return ['project_id' => ['label' => 'Proyek', 'options' => Project::query()->orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->name])->all()]];
    }

    public function rows(array $filters): Collection
    {
        return MaterialRequestLine::query()
            ->with('request:id,number,project_id,status,required_date', 'request.project:id,code', 'item:id,code,base_uom_id', 'item.baseUom:id,code')
            ->where('status', RequestLineStatus::Open->value)
            ->where(fn ($q) => $q->whereNull('fulfillment_source')->orWhereNull('source_warehouse_id'))
            ->whereHas('request', fn ($q) => $q
                ->whereIn('status', [MaterialRequestStatus::Submitted->value, MaterialRequestStatus::UnderReview->value, MaterialRequestStatus::PendingApproval->value, MaterialRequestStatus::Approved->value, MaterialRequestStatus::InProgress->value, MaterialRequestStatus::PartiallyFulfilled->value])
                ->when((int) ($filters['project_id'] ?? 0) > 0, fn ($p) => $p->where('project_id', (int) $filters['project_id'])))
            ->get()
            ->map(fn (MaterialRequestLine $l) => [
                'req' => $l->request?->number,
                'proyek' => $l->request?->project?->code,
                'status' => $l->request?->status->label(),
                'item' => $l->item?->code ?? $l->displayName(),
                'jumlah' => round((float) $l->qty_base, 4),
                'satuan' => $l->item?->baseUom?->code ?? '—',
                'dibutuhkan' => ($l->required_date ?? $l->request?->required_date)?->format('d/m/Y') ?? '—',
            ])
            ->sortBy('dibutuhkan')
            ->values();
    }
}
