<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `project.close` — menutup, membatalkan, atau mengarsipkan proyek
 * (11-master §4). Transisi lewat POST, tidak pernah lewat GET.
 *
 * Guard penuh BR-PRJ-02 (saldo Gudang Site nol, aset sudah kembali, DSC selesai)
 * ditambahkan modul `stock`; di modul ini hanya urutan status yang dijaga.
 */
class ChangeProjectStatus
{
    /** @var array<string, array<int, ProjectStatus>> */
    private const TRANSISI = [
        'active' => [ProjectStatus::Closed, ProjectStatus::Cancelled],
        'closed' => [ProjectStatus::Archived],
        'cancelled' => [ProjectStatus::Archived],
        'archived' => [],
    ];

    public function handle(
        Project $project,
        ProjectStatus $target,
        string $reasonCode,
        ?string $notes = null,
        ?User $actor = null,
    ): Project {
        $sah = self::TRANSISI[$project->status->value] ?? [];

        if (! in_array($target, $sah, true)) {
            throw MasterRuleException::rule(
                'BR-PRJ-01',
                'Proyek berstatus '.$project->status->label().' tidak bisa diubah menjadi '.$target->label().'.',
            );
        }

        // BR-GEN-11: alasan wajib dipilih, keterangan bebas tetap opsional.
        if (trim($reasonCode) === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        // A-06: Proyek Internal adalah tulang punggung konversi, tidak boleh ditutup.
        if ($project->is_internal) {
            throw MasterRuleException::rule(
                'BR-MST-04',
                'Proyek Internal dipakai konversi dan peminjaman non-klien, jadi tidak bisa ditutup.',
            );
        }

        DB::transaction(function () use ($project, $target, $reasonCode, $notes): void {
            $project->forceFill([
                'status' => $target,
                'closed_at' => $target === ProjectStatus::Archived
                    ? $project->closed_at
                    : now(),
                'close_reason' => trim($reasonCode.($notes ? ' — '.$notes : '')),
            ])->save();
        });

        activity('master')
            ->performedOn($project)
            ->causedBy($actor)
            ->withProperties(['status' => $target->value, 'reason_code' => $reasonCode, 'notes' => $notes])
            ->log('Status proyek diubah menjadi '.$target->label());

        return $project->refresh();
    }

    /**
     * Status tujuan yang tersedia dari status sekarang, untuk mengisi dropdown.
     *
     * @return array<int, ProjectStatus>
     */
    public function availableTargets(Project $project): array
    {
        if ($project->is_internal) {
            return [];
        }

        return self::TRANSISI[$project->status->value] ?? [];
    }
}
