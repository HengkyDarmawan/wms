<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Master\Actions\ChangeProjectStatus;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Support\ProjectClosureChecklist;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Support\ReturnableStock;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Waste\Models\WasteDisposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 11-master §6.3 — hub proyek (Blueprint §6.9, A-228): ringkasan, tombol
 * aksi yang langsung terisi proyek ini, lalu tab dokumen per jenis. Tab hanya
 * daftar bertautan ke halaman dokumen masing-masing; aksi dokumen tetap di
 * sana. Ubah status/tutup proyek (BR-PRJ-02) dijalankan dari sini.
 */
class ProjectDetail extends Component
{
    use HandlesMasterRules;
    use WithPagination;

    public const TABS = ['permintaan', 'pengiriman', 'stok', 'pemakaian', 'konversi', 'retur', 'aset', 'approval', 'riwayat'];

    #[Locked]
    public int $projectId;

    #[Url(except: 'permintaan')]
    public string $tab = 'permintaan';

    public bool $dialogStatus = false;

    public string $targetStatus = '';

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->projectId = (int) $project->id;

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'permintaan';
        }
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'permintaan';
    }

    public function mintaUbahStatus(): void
    {
        $this->authorize('close', $this->project());

        $this->ruleError = '';
        $this->dialogStatus = true;
        $this->targetStatus = '';
        $this->reasonCode = '';
        $this->reasonNotes = '';
        $this->resetValidation();
    }

    public function batalUbahStatus(): void
    {
        $this->dialogStatus = false;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function ubahStatus(ChangeProjectStatus $action): void
    {
        $project = $this->project();
        $this->authorize('close', $project);

        $this->validate([
            'targetStatus' => ['required', Rule::enum(ProjectStatus::class)],
            'reasonCode' => ['required', 'string'],
        ], attributes: ['targetStatus' => __('Status tujuan'), 'reasonCode' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $project,
            ProjectStatus::tryFrom($this->targetStatus) ?? ProjectStatus::Closed,
            $this->reasonCode,
            $this->reasonNotes ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->dialogStatus = false;
            $this->dispatch('pesan', teks: __('Status proyek diperbarui.'));
        }
    }

    public function render(ChangeProjectStatus $status, ProjectClosureChecklist $checklist, ReturnableStock $stok): View
    {
        $project = $this->project()->load('client:id,code,name', 'pic:id,name');
        $sites = $checklist->siteWarehouses($project);

        return view('livewire.master.project-detail', [
            'project' => $project,
            'sites' => $sites,
            'ringkas' => $this->ringkas($project, $sites),
            'targetOptions' => $status->availableTargets($project),
            'blockers' => $this->dialogStatus && $project->status === ProjectStatus::Active ? $checklist->blockers($project) : [],
            'alasan' => ReasonCode::options(ReasonContext::Cancel),
            'tabs' => $this->labelTab(),
            'data' => $this->dataTab($project, $stok),
        ]);
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    /**
     * @param  Collection<int, Warehouse>  $sites
     * @return array<string, int|float>
     */
    private function ringkas(Project $project, Collection $sites): array
    {
        return [
            'stok' => (float) StockBalance::query()->withoutGlobalScopes()
                ->join('bins as b', 'b.id', '=', 'stock_balances.bin_id')
                ->whereIn('b.warehouse_id', $sites->pluck('id'))
                ->where('b.bin_type', BinType::Storage->value)
                ->where('stock_balances.stock_status', StockStatus::Available->value)
                ->where('stock_balances.qty_base', '>', 0)
                ->sum('stock_balances.qty_base'),
            'req' => MaterialRequest::query()->withoutGlobalScopes()->where('project_id', $project->id)
                ->whereNotIn('status', $this->statusFinalReq())->count(),
            'aset' => Serial::query()->where('current_project_id', $project->id)->where('asset_state', AssetState::OnLoan->value)->count(),
            'approval' => $this->snapshotPending($project)->count(),
        ];
    }

    /** @return array<int, string> */
    private function statusFinalReq(): array
    {
        return array_map(fn ($s) => $s->value, array_filter(MaterialRequestStatus::cases(), fn ($s) => $s->isFinal()));
    }

    private function snapshotPending(Project $project): Builder
    {
        return ApprovalSnapshot::query()->pending()->where('context->project_id', $project->id);
    }

    /** @return array<string, string> */
    private function labelTab(): array
    {
        return [
            'permintaan' => __('Permintaan'),
            'pengiriman' => __('Pengiriman'),
            'stok' => __('Stok on-site'),
            'pemakaian' => __('Pemakaian'),
            'konversi' => __('Konversi & waste'),
            'retur' => __('Retur & transfer'),
            'aset' => __('Aset'),
            'approval' => __('Approval'),
            'riwayat' => __('Riwayat'),
        ];
    }

    /** Data hanya untuk tab yang sedang dibuka. */
    private function dataTab(Project $project, ReturnableStock $stok): mixed
    {
        $id = (int) $project->id;

        return match ($this->tab) {
            'permintaan' => MaterialRequest::query()->where('project_id', $id)->with('requester:id,name')->withCount('lines')
                ->orderByDesc('id')->paginate(20, pageName: 'req'),
            'pengiriman' => Shipment::query()->where('destination_project_id', $id)->with('warehouse:id,code', 'destinationWarehouse:id,code', 'driver:id,name')
                ->orderByDesc('id')->paginate(20, pageName: 'sj'),
            'stok' => $stok->forProject($project)->groupBy(fn (array $c) => $c['source']->value),
            'pemakaian' => MaterialIssue::query()->where('project_id', $id)->with('warehouse:id,code', 'issuer:id,name')->withCount('lines')
                ->orderByDesc('id')->paginate(20, pageName: 'isu'),
            'konversi' => [
                'cnv' => Conversion::query()->where('project_id', $id)->with('warehouse:id,code', 'inputs.item:id,code', 'outputs.item:id,code')
                    ->orderByDesc('id')->paginate(20, pageName: 'cnv'),
                'wst' => WasteDisposal::query()->where('project_id', $id)->with('warehouse:id,code')->withCount('lines')
                    ->orderByDesc('id')->paginate(10, pageName: 'wst'),
            ],
            'retur' => [
                'ret' => GoodsReturn::query()->where('project_id', $id)->with('toWarehouse:id,code', 'requester:id,name')->withCount('lines')
                    ->orderByDesc('id')->paginate(20, pageName: 'ret'),
                'trf' => Transfer::query()->where(fn (Builder $q) => $q->where('from_project_id', $id)->orWhere('to_project_id', $id))
                    ->with('fromWarehouse:id,code', 'toWarehouse:id,code', 'fromProject:id,code', 'toProject:id,code')->withCount('lines')
                    ->orderByDesc('id')->paginate(20, pageName: 'trf'),
            ],
            'aset' => AssetHandover::query()->where('project_id', $id)->with('serial:id,serial_no,asset_state,due_return_date', 'item:id,code,name')
                ->orderByDesc('id')->paginate(20, pageName: 'ast'),
            'approval' => $this->approvalMenunggu($project),
            'riwayat' => Activity::query()->where('subject_type', Project::class)->where('subject_id', $id)
                ->with('causer:id,name')->latest('id')->paginate(20, pageName: 'riwayat'),
            default => null,
        };
    }

    /** @return Collection<int, array<string, mixed>> snapshot menunggu untuk dokumen proyek ini */
    private function approvalMenunggu(Project $project): Collection
    {
        $registry = app(ApprovalRegistry::class);

        return $this->snapshotPending($project)->with('tasks.approver:id,name', 'submitter:id,name')->latest('id')->get()
            ->map(function (ApprovalSnapshot $s) use ($registry) {
                $url = null;

                if ($registry->has($s->document_type)) {
                    $handler = $registry->handler($s->document_type);
                    $dokumen = $handler->find((int) $s->document_id);
                    $url = $dokumen === null ? null : $handler->url($dokumen);
                }

                return [
                    'nomor' => (string) ($s->context['document_number'] ?? '#'.$s->document_id),
                    'jenis' => $s->document_type->longLabel(),
                    'aturan' => $s->rule_name,
                    'lapis' => $s->current_step,
                    'approver' => $s->tasks->where('status', ApprovalTaskStatus::Open)->map(fn ($t) => $t->approver?->name)->filter()->unique()->implode(', '),
                    'diajukan' => $s->submitter?->name,
                    'sejak' => $s->created_at,
                    'url' => $url,
                ];
            });
    }
}
