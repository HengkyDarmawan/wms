<?php

declare(strict_types=1);

namespace App\Domain\Approval\Livewire;

use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\SaveApprovalRule;
use App\Domain\Approval\Actions\SimulateApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\ConditionMatch;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Livewire\Concerns\HandlesApprovalRules;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 20-approval §6.3 — form aturan approval: kondisi tanpa nilai uang
 * (BR-APR-07), lapis berurutan, dan simulasi sebelum disimpan (BR-APR-11).
 */
class RuleForm extends Component
{
    use HandlesApprovalRules;

    #[Locked]
    public ?int $ruleId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'document_type' => '',
        'name' => '',
        'priority' => '100',
        'is_active' => true,
    ];

    /** @var array<string, mixed> */
    public array $conditions = [
        'match' => 'all',
        'warehouse_ids' => [],
        'project_ids' => [],
        'category_ids' => [],
        'ownership_models' => [],
        'line_count_min' => '',
        'line_qty_min' => '',
        'from_client' => '',
        'vendor_types' => [],
        'order_value_min' => '',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $steps = [];

    public string $sampleNumber = '';

    /** @var array<string, mixed>|null */
    public ?array $simulasi = null;

    public function mount(?ApprovalRule $rule = null): void
    {
        if ($rule !== null && $rule->exists) {
            $this->authorize('update', $rule);
            $this->ruleId = (int) $rule->id;
            $this->form = [
                'document_type' => $rule->document_type->value,
                'name' => $rule->name,
                'priority' => (string) $rule->priority,
                'is_active' => $rule->is_active,
            ];
            $this->conditions = array_merge($this->conditions, $rule->conditions ?? []);
            $this->conditions['from_client'] = array_key_exists('from_client', $rule->conditions ?? [])
                ? ($rule->conditions['from_client'] ? '1' : '0') : '';
            $this->steps = $rule->steps->map(fn ($s) => [
                'approver_type' => $s->approver_type->value,
                'approver_ref_id' => (string) ($s->approver_ref_id ?? ''),
                'decision_mode' => $s->decision_mode->value,
                'timeout_hours' => (string) $s->timeout_hours,
                'backup_approver_type' => $s->backup_approver_type?->value ?? '',
                'backup_ref_id' => (string) ($s->backup_ref_id ?? ''),
            ])->all();

            return;
        }

        $this->authorize('create', ApprovalRule::class);
        $this->form['document_type'] = array_key_first(app(ApprovalRegistry::class)->typeOptions()) ?? '';
        $this->tambahLapis();
    }

    public function render(): View
    {
        $jenis = ApprovalDocumentType::tryFrom((string) $this->form['document_type']);

        return view('livewire.approval.rule-form', [
            'types' => app(ApprovalRegistry::class)->typeOptions(),
            'allowed' => $jenis?->conditions() ?? [],
            'approverTypes' => ApproverType::options(),
            'modes' => DecisionMode::options(),
            'matches' => ConditionMatch::options(),
            'warehouses' => Warehouse::withoutGlobalScopes()->orderBy('code')->get(['id', 'code', 'name']),
            'projects' => Project::query()->orderBy('code')->get(['id', 'code', 'name']),
            'categories' => ItemCategory::query()->orderBy('code')->get(['id', 'code', 'name']),
            'ownerships' => OwnershipModel::options(),
            'vendorTypes' => VendorType::options(),
            'users' => User::query()->internal()->active()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->where('is_client_role', false)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function tambahLapis(): void
    {
        $this->steps[] = [
            'approver_type' => ApproverType::WarehouseHead->value,
            'approver_ref_id' => '',
            'decision_mode' => DecisionMode::Any->value,
            'timeout_hours' => '24',
            'backup_approver_type' => '',
            'backup_ref_id' => '',
        ];
    }

    public function hapusLapis(int $i): void
    {
        unset($this->steps[$i]);
        $this->steps = array_values($this->steps);
    }

    public function naikkanLapis(int $i): void
    {
        if ($i > 0 && isset($this->steps[$i])) {
            [$this->steps[$i - 1], $this->steps[$i]] = [$this->steps[$i], $this->steps[$i - 1]];
        }
    }

    /** BR-APR-11: "siapa yang akan approve dokumen contoh ini?" sebelum disimpan. */
    public function simulasikan(SimulateApproval $action): void
    {
        $this->authorize('approval.simulate');
        $this->simulasi = null;

        $jenis = ApprovalDocumentType::tryFrom((string) $this->form['document_type']);

        if ($jenis === null) {
            return;
        }

        $this->jalankan(function () use ($action, $jenis) {
            $ctx = $action->contextForDocument($jenis, $this->sampleNumber);
            $this->simulasi = $action->run($ctx, ['conditions' => $this->conditions, 'steps' => $this->steps]);
            $this->simulasi['document'] = $ctx->documentNumber;
        }, 'sim');
    }

    public function simpan(SaveApprovalRule $action): void
    {
        $rule = $this->ruleId === null ? null : ApprovalRule::query()->findOrFail($this->ruleId);
        $this->authorize($rule === null ? 'create' : 'update', $rule ?? ApprovalRule::class);

        $this->validate([
            'form.document_type' => ['required', 'string'],
            'form.name' => ['required', 'string', 'max:100'],
            'form.priority' => ['required', 'integer', 'min:1', 'max:9999'],
        ], attributes: [
            'form.document_type' => __('Jenis dokumen'),
            'form.name' => __('Nama aturan'),
            'form.priority' => __('Prioritas'),
        ]);

        $ok = $this->jalankan(function () use ($action, $rule) {
            $action->handle($rule, $this->form + ['conditions' => $this->conditions], $this->steps, auth()->user());
        });

        if ($ok) {
            session()->flash('pesan', __('Aturan approval disimpan.'));
            $this->redirectRoute('approval.rules.index');
        }
    }
}
