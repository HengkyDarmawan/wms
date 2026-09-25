<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Support\ProjectClosureChecklist;
use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\Warehouse\Enums\BinStatus;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `project.close` — menutup, membatalkan, atau mengarsipkan proyek
 * (11-master §4). Transisi lewat POST, tidak pernah lewat GET.
 *
 * Menutup atau membatalkan proyek menuntut checklist BR-PRJ-02 bersih
 * ({@see ProjectClosureChecklist}); saat ditutup, setiap Gudang Site proyek
 * dinonaktifkan (BR-PRJ-04).
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

        DB::transaction(function () use ($project, $target, $reasonCode, $notes, $actor): void {
            // Checklist di dalam transaksi dengan baris proyek terkunci (A-187).
            if (in_array($target, [ProjectStatus::Closed, ProjectStatus::Cancelled], true)) {
                $project->newQuery()->whereKey($project->id)->lockForUpdate()->first();
                $butir = app(ProjectClosureChecklist::class)->blockers($project);

                if ($butir !== []) {
                    throw MasterRuleException::rule('BR-PRJ-02', 'Proyek '.$project->code.' belum bisa '.($target === ProjectStatus::Closed ? 'ditutup' : 'dibatalkan').': '.implode(' ', $butir));
                }
            }

            $project->forceFill([
                'status' => $target,
                'closed_at' => $target === ProjectStatus::Archived
                    ? $project->closed_at
                    : now(),
                'close_reason' => trim($reasonCode.($notes ? ' — '.$notes : '')),
            ])->save();

            // BR-PRJ-04: Gudang Site proyek yang sudah kosong dinonaktifkan.
            if ($target === ProjectStatus::Closed) {
                // Sama dengan efek DeactivateWarehouse (bin ikut nonaktif + jejak),
                // tanpa syarat bin kosong-aktif: checklist sudah memastikan saldo nol.
                app(ProjectClosureChecklist::class)->siteWarehouses($project)
                    ->each(function ($gudang) use ($project, $actor): void {
                        $gudang->forceFill(['is_active' => false])->save();
                        $gudang->bins()->update(['bin_status' => BinStatus::Inactive->value]);
                        activity('warehouse')->performedOn($gudang)->causedBy($actor)
                            ->withProperties(['project' => $project->code])
                            ->log('Gudang Site dinonaktifkan karena proyek ditutup');
                    });
            }
        });

        activity('master')
            ->performedOn($project)
            ->causedBy($actor)
            ->withProperties(['status' => $target->value, 'reason_code' => $reasonCode, 'notes' => $notes])
            ->log('Status proyek diubah menjadi '.$target->label());

        if (in_array($target, [ProjectStatus::Closed, ProjectStatus::Cancelled], true)) {
            app(DomainNotifications::class)->projectClosed($project->refresh(), $actor);
        }

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
