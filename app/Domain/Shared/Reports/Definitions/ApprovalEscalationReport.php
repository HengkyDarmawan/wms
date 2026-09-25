<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Approval\Enums\ApprovalDecisionType;
use App\Domain\Approval\Models\ApprovalDecision;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Shared\Reports\Concerns\ApprovalScope;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 20-approval §9 *Eskalasi per periode* (BR-APR-06, BR-APR-08):
 * keputusan berjenis Dieskalasi dalam periode — dokumen, lapis, approver
 * lama, approver baru (tugas yang `escalated_from_task_id`-nya menunjuk tugas
 * lama), pelaku (kosong = penjadwal), dan catatan sebabnya.
 */
class ApprovalEscalationReport extends Report
{
    use ApprovalScope;

    public function key(): string
    {
        return 'eskalasi-approval';
    }

    public function title(): string
    {
        return 'Eskalasi approval';
    }

    public function permission(): string
    {
        return 'approval_rule.view';
    }

    public function description(): string
    {
        return 'Tugas approval yang dieskalasi dalam periode: dokumen, lapis, dari approver, ke approver, waktu, pelaku (manual atau penjadwal), dan sebabnya.';
    }

    public function columns(): array
    {
        return ['waktu' => 'Waktu eskalasi', 'jenis' => 'Jenis dokumen', 'nomor' => 'Nomor', 'lapis' => 'Lapis', 'dari' => 'Dari approver', 'ke' => 'Ke approver', 'oleh' => 'Oleh', 'sebab' => 'Sebab'];
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

        $eskalasi = ApprovalDecision::query()
            ->with('task:id,approval_snapshot_id,step_no,approver_user_id', 'task.approver:id,name', 'task.snapshot:id,document_type,document_number,context', 'decider:id,name')
            ->where('decision', ApprovalDecisionType::Escalated->value)
            ->whereBetween('decided_at', [$dari, $sampai])
            ->when($jenis !== null, fn ($q) => $q->whereHas('task.snapshot', fn ($s) => $s->where('document_type', $jenis->value)))
            ->orderBy('decided_at')
            ->get()
            ->filter(fn (ApprovalDecision $d) => $d->task !== null && $this->snapshotTerlihat($d->task->snapshot, $gudang))
            ->values();

        // Tugas pengganti dicari sekali untuk semua baris, bukan per baris.
        $pengganti = $eskalasi->isEmpty() ? collect() : ApprovalTask::query()
            ->with('approver:id,name')
            ->whereIn('escalated_from_task_id', $eskalasi->pluck('approval_task_id'))
            ->get(['id', 'escalated_from_task_id', 'approver_user_id'])
            ->keyBy('escalated_from_task_id');

        return $eskalasi->map(fn (ApprovalDecision $d) => [
            'waktu' => $d->decided_at?->lokal()->format('d/m/Y H:i'),
            'jenis' => $this->labelJenis($d->task->snapshot),
            'nomor' => $d->task->snapshot?->document_number ?? '—',
            'lapis' => (int) $d->task->step_no,
            'dari' => $d->task->approver?->name ?? '—',
            'ke' => $pengganti->get($d->approval_task_id)?->approver?->name ?? '—',
            'oleh' => $d->decider?->name ?? 'Penjadwal',
            'sebab' => $d->comment ?? '—',
        ]);
    }
}
