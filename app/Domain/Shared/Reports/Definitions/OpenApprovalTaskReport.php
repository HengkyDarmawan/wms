<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Shared\Reports\Concerns\ApprovalScope;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 20-approval §9 *Tugas terbuka per approver dan umur*: tugas
 * berstatus Terbuka pada snapshot yang masih Menunggu, diurutkan per approver.
 * Umur dalam jam sejak tugas dibuat (ditugaskan); tenggat = `due_at`
 * (batas eskalasi otomatis, BR-APR-08). Cakupan: lihat `ApprovalScope`.
 */
class OpenApprovalTaskReport extends Report
{
    use ApprovalScope;

    public function key(): string
    {
        return 'tugas-approval-terbuka';
    }

    public function title(): string
    {
        return 'Tugas approval terbuka';
    }

    public function permission(): string
    {
        return 'approval_rule.view';
    }

    public function description(): string
    {
        return 'Tugas approval yang belum diputus, per approver: jenis dan nomor dokumen, lapis, kapan ditugaskan, umur dalam jam, tenggat, dan apakah sudah lewat tenggat.';
    }

    public function columns(): array
    {
        return [
            'approver' => 'Approver', 'jenis' => 'Jenis dokumen', 'nomor' => 'Nomor', 'lapis' => 'Lapis', 'wakil_dari' => 'Delegasi dari',
            'ditugaskan' => 'Ditugaskan', 'umur_jam' => 'Umur (jam)', 'tenggat' => 'Tenggat', 'lewat' => 'Lewat tenggat',
        ];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang(), 'document_type' => $this->penyaringJenisDokumen()];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);
        $jenis = $this->jenisDipilih($filters);
        $sekarang = now();

        return ApprovalTask::query()
            ->with('snapshot:id,document_type,document_number,context,status', 'approver:id,name', 'delegatedFrom:id,name')
            ->where('status', ApprovalTaskStatus::Open->value)
            ->whereHas('snapshot', fn ($q) => $q->where('status', ApprovalSnapshotStatus::Pending->value)
                ->when($jenis !== null, fn ($s) => $s->where('document_type', $jenis->value)))
            ->orderBy('created_at')
            ->get()
            ->filter(fn (ApprovalTask $t) => $this->snapshotTerlihat($t->snapshot, $gudang))
            ->map(fn (ApprovalTask $t) => [
                'approver' => $t->approver?->name ?? '—',
                'jenis' => $this->labelJenis($t->snapshot),
                'nomor' => $t->snapshot?->document_number ?? '—',
                'lapis' => (int) $t->step_no,
                'wakil_dari' => $t->delegatedFrom?->name ?? '—',
                'ditugaskan' => $t->created_at?->lokal()->format('d/m/Y H:i'),
                'umur_jam' => round($t->created_at->diffInMinutes($sekarang) / 60, 1),
                'tenggat' => $t->due_at?->lokal()->format('d/m/Y H:i') ?? '—',
                'lewat' => $t->due_at !== null && $t->due_at->lt($sekarang) ? 'Ya' : 'Tidak',
            ])
            ->sortBy([['approver', 'asc'], ['umur_jam', 'desc']])
            ->values();
    }
}
