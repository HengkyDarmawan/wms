<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Livewire;

use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Support\ProjectClosureChecklist;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Actions\MoveProjectRemainder;
use App\Domain\Transfer\Livewire\Concerns\HandlesTransferRules;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar "Pindahkan ke proyek lain" dari hub proyek (A-250): pilih proyek
 * tujuan, centang aset yang sedang dipinjam dan stok Gudang Site yang ikut
 * pindah. Hasilnya TRF aset On-site (A-249) dan TRF stok per Gudang Site.
 */
class ProjectMove extends Component
{
    use HandlesTransferRules;

    #[Locked]
    public int $projectId;

    /** @var array<string, string> */
    public array $form = ['to_project_id' => '', 'to_warehouse_id' => '', 'notes' => ''];

    /** @var array<int|string, bool> serial_id => dipilih */
    public array $aset = [];

    /** @var array<string, string> "gudang:item" => jumlah */
    public array $stok = [];

    public function mount(Project $project): void
    {
        abort_unless(auth()->user()->canAccessProject((int) $project->id), 404);
        $this->authorize('create', Transfer::class);

        $this->projectId = (int) $project->id;

        foreach ($this->asetDipinjam() as $s) {
            $this->aset[$s->id] = true;
        }

        foreach ($this->stokSite() as $b) {
            $this->stok[$b['kunci']] = $this->angka($b['tersedia']);
        }
    }

    public function updatedFormToProjectId(): void
    {
        $this->form['to_warehouse_id'] = (string) (Warehouse::query()->withoutGlobalScopes()->active()
            ->where('project_id', (int) $this->form['to_project_id'])->orderBy('code')->value('id') ?? '');
    }

    public function simpan(MoveProjectRemainder $action): void
    {
        $this->authorize('create', Transfer::class);

        $stok = [];

        foreach ($this->stok as $kunci => $qty) {
            [$gudang, $item] = array_map('intval', explode(':', (string) $kunci));

            if (is_numeric($qty) && (float) $qty > 0) {
                $stok[] = ['warehouse_id' => $gudang, 'item_id' => $item, 'qty_base' => $qty];
            }
        }

        $hasil = null;

        $ok = $this->jalankan(function () use ($action, $stok, &$hasil) {
            $hasil = $action->handle($this->project(), [
                'to_project_id' => $this->form['to_project_id'],
                'to_warehouse_id' => $this->form['to_warehouse_id'],
                'serial_ids' => array_keys(array_filter($this->aset)),
                'stock' => $stok,
                'notes' => $this->form['notes'],
            ], auth()->user());
        });

        if ($ok && $hasil !== null) {
            $nomor = array_filter(array_merge([$hasil['asset']?->number], array_map(fn (Transfer $t) => $t->number, $hasil['stock'])));
            session()->flash('pesan', __('Pindahan dibuat: :n.', ['n' => implode(', ', $nomor)]));
            $this->redirectRoute('projects.show', $this->projectId, navigate: true);
        }
    }

    public function render(): View
    {
        $proyek = $this->project();

        return view('livewire.transfer.project-move', [
            'project' => $proyek,
            'tujuan' => Project::query()->where('status', ProjectStatus::Active->value)->whereKeyNot($proyek->id)->orderBy('code')->get(['id', 'code', 'name']),
            'gudangTujuan' => $this->form['to_project_id'] === '' ? collect() : Warehouse::query()->withoutGlobalScopes()->active()
                ->where('project_id', (int) $this->form['to_project_id'])->orderBy('code')->get(['id', 'code', 'name']),
            'asetDipinjam' => $this->asetDipinjam(),
            'stokSite' => $this->stokSite(),
        ]);
    }

    /** @return Collection<int, Serial> */
    private function asetDipinjam(): Collection
    {
        return Serial::query()->with('item:id,code,name')
            ->where('current_project_id', $this->projectId)
            ->where('asset_state', AssetState::OnLoan->value)
            ->orderBy('serial_no')->get();
    }

    /**
     * Stok Tersedia di bin penyimpanan Gudang Site proyek, per gudang × item,
     * dibatasi yang bisa dipindah (tersedia dikurangi reservasi, BR-STK-03).
     *
     * @return Collection<int, array{kunci: string, gudang: Warehouse, item_code: string, item_name: string, satuan: ?string, tersedia: float}>
     */
    private function stokSite(): Collection
    {
        $sites = app(ProjectClosureChecklist::class)->siteWarehouses($this->project());
        $ledger = app(StockLedger::class);

        return StockBalance::query()->withoutGlobalScopes()
            ->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code')
            ->join('bins as b', 'b.id', '=', 'stock_balances.bin_id')
            ->whereIn('b.warehouse_id', $sites->pluck('id'))
            ->where('b.bin_type', BinType::Storage->value)
            ->where('stock_balances.stock_status', StockStatus::Available->value)
            ->where('stock_balances.qty_base', '>', 0)
            ->get(['stock_balances.item_id', 'b.warehouse_id'])
            ->unique(fn ($s) => $s->warehouse_id.':'.$s->item_id)
            ->map(fn ($s) => [
                'kunci' => $s->warehouse_id.':'.$s->item_id,
                'gudang' => $sites->firstWhere('id', $s->warehouse_id),
                'item_code' => (string) $s->item?->code,
                'item_name' => (string) $s->item?->name,
                'satuan' => $s->item?->baseUom?->code,
                'tersedia' => round($ledger->availableQty((int) $s->item_id, (int) $s->warehouse_id), 4),
            ])
            ->filter(fn (array $b) => $b['tersedia'] > 0)
            ->sortBy(['kunci'])
            ->values();
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}
