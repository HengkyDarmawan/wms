<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Models\ProjectMaterialPlan;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use Illuminate\Support\Facades\DB;

/**
 * Rencana material proyek yang berlaku (BR-PRJ-09, A-428) dan cek "melebihi
 * rencana" untuk kondisi approval REQ (A-429): untuk item yang PUNYA rencana,
 * jumlah diminta (REQ lain yang masih berlaku + baris REQ ini) > rencana.
 * Item tanpa rencana tidak pernah dianggap melebihi.
 */
class ProjectPlan
{
    /** @return array<int, float> item_id => jumlah rencana (satuan dasar) versi berlaku */
    public function current(int $projectId): array
    {
        return ProjectMaterialPlan::query()->current()->where('project_id', $projectId)
            ->pluck('planned_qty_base', 'item_id')->map(fn ($q) => (float) $q)->all();
    }

    /** @return array<int, float> item_id => jumlah diminta di REQ proyek yang masih berlaku */
    public function requested(int $projectId, ?int $kecualiRequestId = null): array
    {
        return DB::table('material_request_lines as l')
            ->join('material_requests as r', 'r.id', '=', 'l.material_request_id')
            ->where('r.project_id', $projectId)
            ->whereNotIn('r.status', [MaterialRequestStatus::Draft->value, MaterialRequestStatus::Rejected->value, MaterialRequestStatus::Cancelled->value])
            ->where('l.status', '!=', RequestLineStatus::Cancelled->value)
            ->whereNotNull('l.item_id')
            ->when($kecualiRequestId !== null, fn ($q) => $q->where('r.id', '!=', $kecualiRequestId))
            ->groupBy('l.item_id')
            ->selectRaw('l.item_id, sum(l.qty_base) as jumlah')
            ->pluck('jumlah', 'item_id')->map(fn ($q) => (float) $q)->all();
    }

    /**
     * @param  array<int, float>  $barisIni  item_id => jumlah di REQ yang sedang dinilai
     */
    public function exceeds(int $projectId, array $barisIni, ?int $requestId = null): bool
    {
        $rencana = $this->current($projectId);

        if ($rencana === []) {
            return false;
        }

        $lain = $this->requested($projectId, $requestId);

        foreach ($barisIni as $itemId => $qty) {
            if (isset($rencana[$itemId]) && ($lain[$itemId] ?? 0) + $qty - $rencana[$itemId] > 0.00005) {
                return true;
            }
        }

        return false;
    }
}
