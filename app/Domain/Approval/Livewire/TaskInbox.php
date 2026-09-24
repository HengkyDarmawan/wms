<?php

declare(strict_types=1);

namespace App\Domain\Approval\Livewire;

use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Actions\EscalateApprovalTask;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Livewire\Concerns\HandlesApprovalRules;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Layar 20-approval §6.1 — "Tugas approval saya".
 *
 * Tab *Menunggu saya* berisi tugas terbuka milik pengguna (termasuk hasil
 * delegasi dan eskalasi); *Sudah saya putus* riwayat keputusannya; *Semua
 * tugas terbuka* hanya untuk pemegang `approval.escalate`, dengan tombol
 * eskalasi manual. Setujui/tolak langsung dari sini tanpa membuka dokumen.
 */
class TaskInbox extends Component
{
    use HandlesApprovalRules;

    #[Url(except: 'saya')]
    public string $tab = 'saya';

    #[Locked]
    public ?int $taskId = null;

    /** @var array<string, string> */
    public array $form = ['reason' => '', 'notes' => ''];

    public function mount(): void
    {
        $this->authorize('approval-inbox');

        if ($this->tab === 'semua' && ! auth()->user()->hasPermission('approval.escalate')) {
            $this->tab = 'saya';
        }
    }

    public function render(): View
    {
        $registry = app(ApprovalRegistry::class);

        return view('livewire.approval.task-inbox', [
            'tasks' => $this->daftar(),
            'registry' => $registry,
            'alasan' => ReasonCode::options(ReasonContext::Reject),
            'bolehEskalasi' => auth()->user()->hasPermission('approval.escalate'),
        ]);
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['saya', 'riwayat', 'semua'], true) ? $tab : 'saya';

        if ($this->tab === 'semua' && ! auth()->user()->hasPermission('approval.escalate')) {
            $this->tab = 'saya';
        }

        $this->tutupDialog();
    }

    public function setujui(int $id, DecideApproval $action): void
    {
        $task = $this->tugas($id);
        $this->authorize('decide', $task);

        if ($this->jalankan(fn () => $action->approve($task, auth()->user(), $this->form['notes'] ?: null))) {
            $this->form = ['reason' => '', 'notes' => ''];
            $this->dispatch('pesan', teks: __('Disetujui.'));
        }
    }

    public function mintaTolak(int $id): void
    {
        $this->authorize('decide', $this->tugas($id));

        $this->taskId = $id;
        $this->form = ['reason' => '', 'notes' => ''];
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->taskId = null;
        $this->resetValidation();
    }

    public function tolak(DecideApproval $action): void
    {
        $task = $this->tugas((int) $this->taskId);
        $this->authorize('decide', $task);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $alasan = ReasonCode::query()->where('context', ReasonContext::Reject->value)
            ->where('code', $this->form['reason'])->value('id');

        $ok = $this->jalankan(fn () => $action->reject(
            $task,
            auth()->user(),
            $alasan === null ? null : (int) $alasan,
            $this->form['notes'] ?: null,
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Ditolak.'));
        }
    }

    public function eskalasi(int $id, EscalateApprovalTask $action): void
    {
        $task = $this->tugas($id);
        $this->authorize('escalate', $task);

        if ($this->jalankan(fn () => $action->handle($task, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Tugas dieskalasi.'));
        }
    }

    private function tugas(int $id): ApprovalTask
    {
        return ApprovalTask::query()->with('snapshot')->findOrFail($id);
    }

    /** @return Collection<int, ApprovalTask> */
    private function daftar(): Collection
    {
        $q = ApprovalTask::query()
            ->with(['snapshot.submitter:id,name', 'approver:id,name', 'delegatedFrom:id,name', 'decisions.reason:id,label', 'escalatedFrom'])
            ->whereHas('snapshot');

        return match ($this->tab) {
            'riwayat' => $q->where('approver_user_id', auth()->id())
                ->whereIn('status', [ApprovalTaskStatus::Decided->value, ApprovalTaskStatus::Superseded->value, ApprovalTaskStatus::Expired->value])
                ->orderByDesc('updated_at')->limit(50)->get(),
            'semua' => $q->open()
                ->whereHas('snapshot', fn ($s) => $s->where('status', ApprovalSnapshotStatus::Pending->value))
                ->orderBy('due_at')->limit(200)->get(),
            default => $q->open()->where('approver_user_id', auth()->id())
                ->whereHas('snapshot', fn ($s) => $s->where('status', ApprovalSnapshotStatus::Pending->value))
                ->orderBy('due_at')->get(),
        };
    }
}
