<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\AddProjectTeamMember;
use App\Domain\Access\Actions\EndProjectTeamMember;
use App\Domain\Access\Actions\ExtendProjectTeamMember;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SiteTeam;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tab **Tim site** di hub proyek (A-337): siapa ditempatkan di site ini, sejak
 * kapan sampai kapan. Satu-satunya tempat akses berbatas waktu — form pengguna
 * tidak punya isian tanggal lagi.
 *
 * Komponen terpisah (bukan menumpuk di `ProjectDetail`) supaya berkasnya tetap
 * di bawah batas ±450 baris.
 */
class ProjectTeam extends Component
{
    #[Locked]
    public int $projectId;

    public string $ruleError = '';

    public bool $showForm = false;

    public string $userId = '';

    public string $roleId = '';

    public string $startsOn = '';

    public string $endsOn = '';

    #[Locked]
    public ?int $extendingId = null;

    public string $newEndsOn = '';

    public string $extendNotes = '';

    #[Locked]
    public ?int $endingId = null;

    public string $endsOnAkhir = '';

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->projectId = (int) $project->id;
    }

    public function tambah(): void
    {
        $this->authorize('role.assign');

        $this->bersih();
        $this->userId = '';
        $this->roleId = '';
        $this->startsOn = now()->toDateString();
        $this->endsOn = $this->project()->target_end_date?->toDateString() ?? now()->addMonths(3)->toDateString();
        $this->showForm = true;
    }

    public function simpanTambah(AddProjectTeamMember $action): void
    {
        $this->authorize('role.assign');

        $this->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
        ], attributes: [
            'userId' => __('Orang'),
            'roleId' => __('Peran di site'),
            'startsOn' => __('Mulai'),
            'endsOn' => __('Selesai'),
        ]);

        $ok = $this->jalankan(fn () => $action->handle(
            $this->project(),
            User::query()->findOrFail((int) $this->userId),
            Role::query()->findOrFail((int) $this->roleId),
            $this->startsOn,
            $this->endsOn,
            auth()->user(),
        ));

        if (! $ok) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('Anggota Tim site ditambahkan.'));
    }

    public function batal(): void
    {
        $this->showForm = false;
        $this->extendingId = null;
        $this->endingId = null;
        $this->bersih();
    }

    public function mintaPerpanjang(int $id): void
    {
        $member = $this->member($id);

        abort_unless(app(SiteTeam::class)->canExtend(auth()->user(), $this->project()), 403);

        $this->bersih();
        $this->extendingId = $member->id;
        $this->newEndsOn = $member->ends_on->copy()->addMonths(3)->toDateString();
        $this->extendNotes = '';
    }

    public function perpanjang(ExtendProjectTeamMember $action): void
    {
        $member = $this->member((int) $this->extendingId);

        abort_unless(app(SiteTeam::class)->canExtend(auth()->user(), $this->project()), 403);

        $this->validate(['newEndsOn' => ['required', 'date']], attributes: ['newEndsOn' => __('Selesai baru')]);

        $ok = $this->jalankan(fn () => $action->handle($member, $this->newEndsOn, $this->extendNotes ?: null, auth()->user()));

        if (! $ok) {
            return;
        }

        $this->extendingId = null;
        $this->dispatch('pesan', teks: __('Penempatan diperpanjang.'));
    }

    public function mintaAkhiri(int $id): void
    {
        $this->authorize('role.assign');

        $this->bersih();
        $this->endingId = $this->member($id)->id;
        $this->endsOnAkhir = now()->toDateString();
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function akhiri(EndProjectTeamMember $action): void
    {
        $this->authorize('role.assign');

        $member = $this->member((int) $this->endingId);

        $this->validate(
            ['reasonCode' => ['required', 'string'], 'endsOnAkhir' => ['required', 'date']],
            attributes: ['reasonCode' => __('Alasan'), 'endsOnAkhir' => __('Berlaku sampai')],
        );

        $ok = $this->jalankan(fn () => $action->handle(
            $member,
            $this->reasonCode,
            $this->reasonNotes ?: null,
            $this->endsOnAkhir,
            auth()->user(),
        ));

        if (! $ok) {
            return;
        }

        $this->endingId = null;
        $this->dispatch('pesan', teks: __('Penempatan di site diakhiri.'));
    }

    public function render(): View
    {
        $project = $this->project();
        $siteTeam = app(SiteTeam::class);

        return view('livewire.access.project-team', [
            'project' => $project,
            'anggota' => ProjectTeamMember::query()->where('project_id', $project->id)
                ->with('user:id,name,client_id', 'role:id,name,code', 'endReason:id,label')
                ->orderByRaw('ended_at is null desc')->orderByDesc('ends_on')->get(),
            'sites' => $siteTeam->siteWarehouses($project),
            'peranPilihan' => $siteTeam->roleOptions(),
            'calon' => $siteTeam->candidates($project),
            'bolehAtur' => auth()->user()?->hasPermission('role.assign') ?? false,
            'bolehPerpanjang' => $siteTeam->canExtend(auth()->user(), $project),
            'alasan' => ReasonCode::options(ReasonContext::Cancel),
            'perpanjangkan' => $this->extendingId === null ? null : $this->member($this->extendingId),
            'akhirkan' => $this->endingId === null ? null : $this->member($this->endingId),
        ]);
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    private function member(int $id): ProjectTeamMember
    {
        return ProjectTeamMember::query()->where('project_id', $this->projectId)->findOrFail($id);
    }

    private function bersih(): void
    {
        $this->ruleError = '';
        $this->resetValidation();
    }

    /** Pelanggaran aturan Access menjadi pesan di layar, bukan galat 500. */
    private function jalankan(callable $aksi): bool
    {
        $this->ruleError = '';

        try {
            $aksi();

            return true;
        } catch (AccessRuleException $e) {
            $this->ruleError = $e->getMessage();

            return false;
        }
    }
}
