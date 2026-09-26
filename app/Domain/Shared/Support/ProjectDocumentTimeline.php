<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Master\Models\Project;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Waste\Models\WasteDisposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Tab *Riwayat* hub proyek (A-252): linimasa gabungan semua dokumen proyek —
 * REQ, SJ (dikirim ke / dijemput dari proyek), ISU, CNV, WST, RET, TRF (asal
 * atau tujuan), PRQ, AST — urut tanggal dibuat, terbaru di atas, bertaut ke
 * detailnya. Dibaca langsung dari tabel dokumen (tanpa `document_timelines`).
 */
class ProjectDocumentTimeline
{
    public function __construct(private readonly DocumentLineage $lineage) {}

    /** @return Collection<int, array<string, mixed>> */
    public function for(Project $project, int $limit = 60): Collection
    {
        $id = (int) $project->id;

        /** @var array<int, Builder<Model>> $sumber */
        $sumber = [
            MaterialRequest::query()->where('project_id', $id),
            Shipment::query()->where(fn (Builder $q) => $q->where('destination_project_id', $id)->orWhere('origin_project_id', $id)),
            MaterialIssue::query()->where('project_id', $id),
            Conversion::query()->where('project_id', $id),
            WasteDisposal::query()->where('project_id', $id),
            GoodsReturn::query()->where('project_id', $id),
            Transfer::query()->where(fn (Builder $q) => $q->where('from_project_id', $id)->orWhere('to_project_id', $id)),
            PurchaseRequest::query()->where('project_id', $id),
            AssetHandover::query()->where('project_id', $id),
        ];

        return collect($sumber)
            ->flatMap(fn (Builder $q) => $q->latest('id')->limit($limit)->get()->all())
            ->map(fn (Model $m) => $this->lineage->entry($m))
            ->filter()
            ->sortByDesc(fn (array $e) => $e['tanggal']?->getTimestamp() ?? 0)
            ->take($limit)
            ->values();
    }
}
