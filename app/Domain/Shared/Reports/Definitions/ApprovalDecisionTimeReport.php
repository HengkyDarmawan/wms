<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Approval\Enums\ApprovalDecisionType;
use App\Domain\Approval\Models\ApprovalDecision;
use App\Domain\Shared\Reports\Concerns\ApprovalScope;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 20-approval §9 *Rata-rata waktu putus per lapis*: keputusan Setuju
 * atau Tolak yang diambil dalam periode, per jenis dokumen × lapis. Waktu
 * putus = dari tugas dibuat (ditugaskan ke approver yang memutus) sampai
 * keputusannya; tugas hasil eskalasi/delegasi dihitung dari tugas barunya,
 * bukan dari dokumen diajukan. Cakupan: lihat `ApprovalScope`.
 */
class ApprovalDecisionTimeReport extends Report
{
    use ApprovalScope;

    public function key(): string
    {
        return 'waktu-putus-approval';
    }

    public function title(): string
    {
        return 'Waktu putus approval';
    }

    public function permission(): string
    {
        return 'approval_rule.view';
    }

    public function description(): string
    {
        return 'Per jenis dokumen dan lapis: jumlah keputusan dalam periode (setuju/tolak), rata-rata dan maksimum jam dari tugas ditugaskan sampai diputus.';
    }

    public function columns(): array
    {
        return ['jenis' => 'Jenis dokumen', 'lapis' => 'Lapis', 'keputusan' => 'Keputusan', 'setuju' => 'Setuju', 'tolak' => 'Tolak', 'rata_jam' => 'Rata-rata (jam)', 'maks_jam' => 'Maksimum (jam)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang(), 'document_type' => $this->penyaringJenisDokumen()] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $gudang = $this->gudangDipilih($filters);
        $jenis = $this->jenisDipilih($filters);

        return ApprovalDecision::query()
            ->with('task:id,approval_snapshot_id,step_no,created_at', 'task.snapshot:id,document_type,context')
            ->whereIn('decision', [ApprovalDecisionType::Approved->value, ApprovalDecisionType::Rejected->value])
            ->whereBetween('decided_at', [$dari, $sampai])
            ->when($jenis !== null, fn ($q) => $q->whereHas('task.snapshot', fn ($s) => $s->where('document_type', $jenis->value)))
            ->get(['id', 'approval_task_id', 'decision', 'decided_at'])
            ->filter(fn (ApprovalDecision $d) => $d->task !== null && $this->snapshotTerlihat($d->task->snapshot, $gudang))
            ->groupBy(fn (ApprovalDecision $d) => $d->task->snapshot->document_type->value.'#'.$d->task->step_no)
            ->map(function (Collection $grup) {
                /** @var ApprovalDecision $contoh */
                $contoh = $grup->first();
                $jam = $grup->map(fn (ApprovalDecision $d) => $d->task->created_at->diffInMinutes($d->decided_at) / 60);

                return [
                    'jenis' => $this->labelJenis($contoh->task->snapshot),
                    'lapis' => (int) $contoh->task->step_no,
                    'keputusan' => $grup->count(),
                    'setuju' => $grup->where('decision', ApprovalDecisionType::Approved)->count(),
                    'tolak' => $grup->where('decision', ApprovalDecisionType::Rejected)->count(),
                    'rata_jam' => round((float) $jam->avg(), 1),
                    'maks_jam' => round((float) $jam->max(), 1),
                ];
            })
            ->sortBy([['jenis', 'asc'], ['lapis', 'asc']])
            ->values();
    }
}
