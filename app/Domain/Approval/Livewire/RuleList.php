<?php

declare(strict_types=1);

namespace App\Domain\Approval\Livewire;

use App\Domain\Approval\Actions\SaveApprovalRule;
use App\Domain\Approval\Livewire\Concerns\HandlesApprovalRules;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Approval\Support\ApproverResolver;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Layar 20-approval §6.2 — daftar aturan approval per jenis dokumen, urut
 * prioritas pemeriksaan. Aturan dinonaktifkan, tidak dihapus (P-03).
 */
class RuleList extends Component
{
    use HandlesApprovalRules;

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ApprovalRule::class);
    }

    public function render(): View
    {
        $registry = app(ApprovalRegistry::class);

        $rules = ApprovalRule::query()->with('steps')->withCount('snapshots')
            ->when($this->typeFilter !== '', fn ($q) => $q->where('document_type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('document_type')->evaluationOrder()->get();

        return view('livewire.approval.rule-list', [
            'rules' => $rules,
            'types' => $registry->typeOptions(),
            'resolver' => app(ApproverResolver::class),
        ]);
    }

    public function setAktif(int $id, bool $aktif, SaveApprovalRule $action): void
    {
        $rule = ApprovalRule::query()->findOrFail($id);
        $this->authorize('update', $rule);

        if ($this->jalankan(fn () => $action->setActive($rule, $aktif, auth()->user()))) {
            $this->dispatch('pesan', teks: $aktif ? __('Aturan diaktifkan.') : __('Aturan dinonaktifkan.'));
        }
    }
}
