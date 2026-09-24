<?php

declare(strict_types=1);

namespace App\Domain\Approval\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\SaveDelegation;
use App\Domain\Approval\Livewire\Concerns\HandlesApprovalRules;
use App\Domain\Approval\Models\ApprovalDelegation;
use App\Domain\Approval\Support\ApprovalRegistry;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 20-approval §6.4 — delegasi hak approve berperiode (BR-APR-05).
 *
 * Pengguna melihat delegasi yang ia berikan dan yang ia terima; Admin Company
 * (`approval_rule.manage`) melihat semuanya dan boleh membuat delegasi atas
 * nama approver lain. Delegasi diakhiri, tidak dihapus.
 */
class DelegationManager extends Component
{
    use HandlesApprovalRules;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [
        'from_user_id' => '',
        'to_user_id' => '',
        'starts_at' => '',
        'ends_at' => '',
        'document_types' => [],
        'notes' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', ApprovalDelegation::class);
    }

    public function render(): View
    {
        $admin = auth()->user()->hasPermission('approval_rule.manage');

        $daftar = ApprovalDelegation::query()->with('fromUser:id,name', 'toUser:id,name')
            ->when(! $admin, fn ($q) => $q->where(fn ($w) => $w->where('from_user_id', auth()->id())->orWhere('to_user_id', auth()->id())))
            ->orderByDesc('is_active')->orderByDesc('starts_at')->limit(100)->get();

        return view('livewire.approval.delegations', [
            'delegations' => $daftar,
            'admin' => $admin,
            'users' => User::query()->internal()->active()->orderBy('name')->get(['id', 'name']),
            'types' => app(ApprovalRegistry::class)->typeOptions(),
            'zona' => $this->zona(),
        ]);
    }

    private function zona(): string
    {
        return tenant()?->timezone ?? 'Asia/Jakarta';
    }

    public function buat(): void
    {
        $this->authorize('create', ApprovalDelegation::class);

        $this->resetValidation();
        $this->form = [
            'from_user_id' => (string) auth()->id(),
            'to_user_id' => '',
            'starts_at' => now($this->zona())->format('Y-m-d\TH:i'),
            'ends_at' => now($this->zona())->addDays(7)->format('Y-m-d\TH:i'),
            'document_types' => [],
            'notes' => '',
        ];
        $this->showForm = true;
    }

    public function batalForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function simpan(SaveDelegation $action): void
    {
        $this->authorize('create', ApprovalDelegation::class);

        $this->validate([
            'form.to_user_id' => ['required'],
            'form.starts_at' => ['required', 'date'],
            'form.ends_at' => ['required', 'date'],
            'form.notes' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'form.to_user_id' => __('Delegat'),
            'form.starts_at' => __('Mulai'),
            'form.ends_at' => __('Sampai'),
        ]);

        // Isian datetime-local dalam zona waktu company; disimpan UTC (BR-GEN-07).
        $data = $this->form;
        foreach (['starts_at', 'ends_at'] as $k) {
            $data[$k] = Carbon::parse((string) $data[$k], $this->zona())->utc()->toDateTimeString();
        }

        if ($this->jalankan(fn () => $action->create($data, auth()->user()))) {
            $this->showForm = false;
            $this->dispatch('pesan', teks: __('Delegasi dibuat.'));
        }
    }

    public function akhiri(int $id, SaveDelegation $action): void
    {
        $d = ApprovalDelegation::query()->findOrFail($id);
        $this->authorize('end', $d);

        if ($this->jalankan(fn () => $action->end($d, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Delegasi diakhiri.'));
        }
    }
}
