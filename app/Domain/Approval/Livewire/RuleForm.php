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
use App\Domain\Approval\Support\ApprovalRuleSentence;
use App\Domain\Approval\Support\ConditionMatcher;
use App\Domain\Count\Enums\CountType;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Project;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\WhatsApp\Support\WhatsAppChannel;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 20-approval §6.3 — form aturan approval: kondisi tanpa nilai uang
 * (BR-APR-07), lapis berurutan, dan simulasi sebelum disimpan (BR-APR-11).
 *
 * **Mode sederhana** bawaan (A-348): tiga langkah — dokumen apa, siapa yang
 * menyetujui, berlaku kapan — dengan nama & prioritas otomatis dan kalimat
 * ringkasan. **Mode lanjutan** memuat semua isian lama. Keduanya mengisi
 * properti yang sama dan disimpan lewat `SaveApprovalRule` yang sama.
 */
class RuleForm extends Component
{
    use HandlesApprovalRules;

    /** Jenis approver yang ditawarkan mode sederhana. */
    public const APPROVER_SEDERHANA = ['direct_manager', 'warehouse_head', 'role', 'user'];

    #[Locked]
    public ?int $ruleId = null;

    /** 'sederhana' | 'lanjutan' */
    public string $mode = 'sederhana';

    /** 'selalu' | 'bila' — langkah 3 mode sederhana. */
    public string $berlaku = 'selalu';

    /** Nama diketik sendiri: berhenti diisi otomatis. */
    public bool $namaManual = false;

    /** Prioritas diketik sendiri di mode lanjutan: berhenti dihitung otomatis. */
    public bool $prioritasManual = false;

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
        'purchase_request_origins' => [],
        'count_types' => [],
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
                'manager_levels' => (string) ($s->manager_levels ?? 1),
                'decision_mode' => $s->decision_mode->value,
                'same_org_unit' => (bool) $s->same_org_unit,
                'timeout_hours' => (string) $s->timeout_hours,
                'channel' => $s->channel?->value === 'both' ? 'both' : 'web',
                'backup_approver_type' => $s->backup_approver_type?->value ?? '',
                'backup_ref_id' => (string) ($s->backup_ref_id ?? ''),
            ])->all();

            // Aturan tersimpan: nama & prioritasnya dipertahankan apa adanya.
            $this->namaManual = true;
            $this->prioritasManual = true;
            $this->berlaku = $this->adaKondisi() ? 'bila' : 'selalu';
            $this->mode = $this->butuhLanjutan() ? 'lanjutan' : 'sederhana';

            return;
        }

        $this->authorize('create', ApprovalRule::class);
        $this->form['document_type'] = array_key_first(app(ApprovalRegistry::class)->typeOptions()) ?? '';
        $this->tambahLapis();
        $this->perbaruiNama();
    }

    // ------------------------------------------------------------ mode sederhana

    public function pilihJenis(string $jenis): void
    {
        if ($this->ruleId !== null || ! app(ApprovalRegistry::class)->has($jenis)) {
            return;
        }

        $this->form['document_type'] = $jenis;
        $this->simulasi = null;
        $this->perbaruiNama();
    }

    public function pilihApprover(int $i, string $jenis): void
    {
        if (! isset($this->steps[$i]) || ! in_array($jenis, self::APPROVER_SEDERHANA, true)) {
            return;
        }

        $this->steps[$i]['approver_type'] = $jenis;
        $this->steps[$i]['approver_ref_id'] = $jenis === 'role'
            ? (string) (Role::findByCode('management')?->id ?? '')
            : '';
        $this->perbaruiNama();
    }

    public function pilihBerlaku(string $berlaku): void
    {
        $this->berlaku = $berlaku === 'bila' ? 'bila' : 'selalu';
        $this->perbaruiNama();
    }

    public function keMode(string $mode): void
    {
        $this->mode = $mode === 'lanjutan' ? 'lanjutan' : 'sederhana';

        // Di lanjutan prioritas tampil sebagai angka: tunjukkan nilai otomatisnya.
        if ($this->mode === 'lanjutan' && ! $this->prioritasManual) {
            $this->form['priority'] = (string) $this->prioritasOtomatis();
        }
    }

    public function updated(string $properti): void
    {
        if ($properti === 'form.name') {
            $this->namaManual = trim((string) $this->form['name']) !== '';

            if (! $this->namaManual) {
                $this->perbaruiNama();
            }

            return;
        }

        if ($properti === 'form.priority') {
            $this->prioritasManual = true;

            return;
        }

        if (str_starts_with($properti, 'conditions') || str_starts_with($properti, 'steps')) {
            // Mengisi syarat berarti "Hanya bila…" — jangan sampai terbuang saat disimpan.
            if (str_starts_with($properti, 'conditions') && $this->adaKondisi()) {
                $this->berlaku = 'bila';
            }

            $this->perbaruiNama();
        }
    }

    // ------------------------------------------------------------------ lapis

    public function tambahLapis(): void
    {
        $this->steps[] = [
            'approver_type' => $this->steps === [] ? ApproverType::WarehouseHead->value : ApproverType::Role->value,
            'approver_ref_id' => $this->steps === [] ? '' : (string) (Role::findByCode('management')?->id ?? ''),
            'manager_levels' => '1',
            'decision_mode' => DecisionMode::Any->value,
            'same_org_unit' => true,
            'timeout_hours' => '24',
            'channel' => 'web',
            'backup_approver_type' => '',
            'backup_ref_id' => '',
        ];
        $this->perbaruiNama();
    }

    public function hapusLapis(int $i): void
    {
        unset($this->steps[$i]);
        $this->steps = array_values($this->steps);
        $this->perbaruiNama();
    }

    public function naikkanLapis(int $i): void
    {
        if ($i > 0 && isset($this->steps[$i])) {
            [$this->steps[$i - 1], $this->steps[$i]] = [$this->steps[$i], $this->steps[$i - 1]];
        }
    }

    // ----------------------------------------------------------- simulasi/simpan

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
            $this->simulasi = $action->run($ctx, ['conditions' => $this->kondisiDisimpan(), 'steps' => $this->steps]);
            $this->simulasi['document'] = $ctx->documentNumber;
        }, 'sim');
    }

    public function simpan(SaveApprovalRule $action): void
    {
        $rule = $this->ruleId === null ? null : ApprovalRule::query()->findOrFail($this->ruleId);
        $this->authorize($rule === null ? 'create' : 'update', $rule ?? ApprovalRule::class);

        if (! $this->prioritasManual) {
            $this->form['priority'] = (string) $this->prioritasOtomatis();
        }

        if (trim((string) $this->form['name']) === '') {
            $this->form['name'] = $this->namaOtomatis();
        }

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
            $action->handle($rule, $this->form + ['conditions' => $this->kondisiDisimpan()], $this->steps, auth()->user());
        });

        if ($ok) {
            session()->flash('pesan', __('Aturan approval disimpan.'));
            $this->redirectRoute('approval.rules.index');
        }
    }

    // ------------------------------------------------------------------ render

    public function render(): View
    {
        $jenis = ApprovalDocumentType::tryFrom((string) $this->form['document_type']);
        $kalimat = app(ApprovalRuleSentence::class);

        return view('livewire.approval.rule-form', [
            // Fase 2a (A-277): kanal lapis Web & WhatsApp berlaku bila WhatsApp company aktif.
            'waAktif' => app(WhatsAppChannel::class)->enabled(),
            'types' => app(ApprovalRegistry::class)->typeOptions(),
            'allowed' => $jenis?->conditions() ?? [],
            // A-308: aturan PRQ lama berkondisi jenis vendor — kondisi itu tidak lagi dinilai.
            'kondisiUsang' => array_values(array_diff(array_keys(array_filter($this->conditions, fn ($v) => $v !== [] && $v !== '' && $v !== null)), array_merge(['match'], $jenis?->conditions() ?? [], ['order_value_min']))),
            'approverTypes' => ApproverType::options(),
            'modes' => DecisionMode::options(),
            'matches' => ConditionMatch::options(),
            'warehouses' => Warehouse::withoutGlobalScopes()->orderBy('code')->get(['id', 'code', 'name']),
            'projects' => Project::query()->orderBy('code')->get(['id', 'code', 'name']),
            'categories' => ItemCategory::query()->orderBy('code')->get(['id', 'code', 'name']),
            'ownerships' => OwnershipModel::options(),
            'vendorTypes' => VendorType::options(),
            'origins' => PurchaseRequestOrigin::options(),
            'countTypes' => CountType::options(),
            'users' => User::query()->internal()->active()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->where('is_client_role', false)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ringkasan' => $jenis === null ? '' : $kalimat->kalimat($jenis, $this->kondisiDisimpan(), $this->steps),
            'prioritasOtomatis' => $this->prioritasOtomatis(),
            'butuhLanjutan' => $this->butuhLanjutan(),
        ]);
    }

    // ----------------------------------------------------------------- bantuan

    /**
     * Kondisi yang benar-benar disimpan: kosong bila "Selalu" (mode sederhana),
     * dan hanya kunci yang bermakna untuk jenis dokumen ini.
     *
     * @return array<string, mixed>
     */
    private function kondisiDisimpan(): array
    {
        if ($this->mode === 'sederhana' && $this->berlaku === 'selalu') {
            return ['match' => ConditionMatch::All->value];
        }

        $jenis = ApprovalDocumentType::tryFrom((string) $this->form['document_type']);
        $boleh = array_merge(['match'], $jenis?->conditions() ?? []);

        return array_intersect_key($this->conditions, array_flip($boleh));
    }

    private function prioritasOtomatis(): int
    {
        return ConditionMatcher::prioritasOtomatis($this->kondisiDisimpan());
    }

    private function adaKondisi(): bool
    {
        return array_diff(array_keys(ConditionMatcher::normalize($this->conditions)), ['match']) !== [];
    }

    /** Aturan yang memakai isian di luar mode sederhana dibuka di mode lanjutan. */
    private function butuhLanjutan(): bool
    {
        if (count($this->steps) > 2 || ($this->conditions['match'] ?? 'all') === ConditionMatch::Any->value) {
            return true;
        }

        foreach ($this->steps as $s) {
            if (! in_array($s['approver_type'] ?? '', self::APPROVER_SEDERHANA, true)
                || ($s['decision_mode'] ?? 'any') !== DecisionMode::Any->value
                || (int) ($s['timeout_hours'] ?? 24) !== 24
                || ($s['channel'] ?? 'web') !== 'web'
                || ($s['backup_approver_type'] ?? '') !== ''
                || (($s['approver_type'] ?? '') === 'role' && ! ($s['same_org_unit'] ?? true))) {
                return true;
            }
        }

        return false;
    }

    private function perbaruiNama(): void
    {
        if (! $this->namaManual) {
            $this->form['name'] = $this->namaOtomatis();
        }
    }

    /** "Permintaan Material — gudang CKG, BKS" (maks. 100 karakter). */
    private function namaOtomatis(): string
    {
        $jenis = ApprovalDocumentType::tryFrom((string) $this->form['document_type']);

        if ($jenis === null) {
            return '';
        }

        $kondisi = $this->kondisiDisimpan();
        $syarat = count($kondisi) > 1 ? app(ApprovalRuleSentence::class)->kondisi($jenis, $kondisi) : '';

        return Str::limit($jenis->label().($syarat !== '' ? ' — '.$syarat : ''), 99, '…');
    }
}
