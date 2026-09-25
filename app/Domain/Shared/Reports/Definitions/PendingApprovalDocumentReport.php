<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Shared\Reports\Concerns\ApprovalScope;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 20-approval §9 *Dokumen menunggu per jenis*: snapshot approval
 * berstatus Menunggu, dijumlahkan per jenis dokumen — berapa dokumen, berapa
 * tugas terbuka, berapa yang tugasnya lewat tenggat, dan umur dokumen tertua
 * (sejak diajukan). Cakupan: lihat `ApprovalScope`.
 */
class PendingApprovalDocumentReport extends Report
{
    use ApprovalScope;

    public function key(): string
    {
        return 'dokumen-menunggu-approval';
    }

    public function title(): string
    {
        return 'Dokumen menunggu approval';
    }

    public function permission(): string
    {
        return 'approval_rule.view';
    }

    public function description(): string
    {
        return 'Per jenis dokumen: jumlah dokumen yang menunggu approval, tugas terbuka, tugas lewat tenggat, dan umur dokumen tertua.';
    }

    public function columns(): array
    {
        return ['jenis' => 'Jenis dokumen', 'dokumen' => 'Dokumen menunggu', 'tugas' => 'Tugas terbuka', 'lewat' => 'Lewat tenggat', 'tertua' => 'Dokumen tertua', 'umur_hari' => 'Umur tertua (hari)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);
        $sekarang = now();

        $snapshot = ApprovalSnapshot::query()
            ->with(['tasks' => fn ($q) => $q->where('status', ApprovalTaskStatus::Open->value)->select('id', 'approval_snapshot_id', 'due_at', 'status')])
            ->where('status', ApprovalSnapshotStatus::Pending->value)
            ->get(['id', 'document_type', 'document_number', 'context', 'status', 'submitted_at'])
            ->filter(fn (ApprovalSnapshot $s) => $this->snapshotTerlihat($s, $gudang));

        return $snapshot->groupBy(fn (ApprovalSnapshot $s) => $s->document_type->value)
            ->map(function (Collection $grup) use ($sekarang) {
                /** @var ApprovalSnapshot $tertua */
                $tertua = $grup->sortBy('submitted_at')->first();
                $tugas = $grup->flatMap(fn (ApprovalSnapshot $s) => $s->tasks);

                return [
                    'jenis' => $this->labelJenis($tertua),
                    'dokumen' => $grup->count(),
                    'tugas' => $tugas->count(),
                    'lewat' => $tugas->filter(fn (ApprovalTask $t) => $t->due_at !== null && $t->due_at->lt($sekarang))->count(),
                    'tertua' => $tertua->document_number ?? '—',
                    'umur_hari' => round($tertua->submitted_at->diffInMinutes($sekarang) / 1440, 1),
                ];
            })
            ->sortByDesc('dokumen')
            ->values();
    }
}
