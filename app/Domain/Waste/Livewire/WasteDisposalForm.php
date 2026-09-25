<?php

declare(strict_types=1);

namespace App\Domain\Waste\Livewire;

use App\Domain\Conversion\Support\ConversionLines;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Waste\Actions\CreateWasteDisposal;
use App\Domain\Waste\Enums\WasteDisposition;
use App\Domain\Waste\Livewire\Concerns\HandlesWasteRules;
use App\Domain\Waste\Models\WasteDisposal;
use App\Domain\Waste\Support\DisposableWaste;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 24-konversi-waste §6 — BA waste baru: gudang `*`, proyek `*`,
 * disposisi `*` (dipakai ulang: bin tujuan `*`), lalu jumlah per isi bin Waste
 * (potongan dan serial utuh) dengan alasan opsional (A-159).
 */
class WasteDisposalForm extends Component
{
    use HandlesWasteRules;

    /** @var array<string, string> */
    public array $form = ['warehouse_id' => '', 'project_id' => '', 'disposition' => '', 'target_bin_id' => '', 'notes' => ''];

    /** @var array<string, string> kunci calon (':' → '_') => jumlah */
    public array $qty = [];

    /** @var array<string, string> kunci calon => kode alasan waste */
    public array $reason = [];

    public function mount(): void
    {
        $this->authorize('create', WasteDisposal::class);

        $gudang = $this->gudang();

        // Dari detail CNV / hub proyek (A-229): gudang & proyek langsung terisi bila ada di pilihan.
        $mintaGudang = request()->query('warehouse');
        $mintaProyek = request()->query('project');

        if (is_numeric($mintaGudang) && $gudang->contains('id', (int) $mintaGudang)) {
            $this->form['warehouse_id'] = (string) (int) $mintaGudang;
        } elseif ($gudang->count() === 1) {
            $this->form['warehouse_id'] = (string) $gudang->first()->id;
        }

        if (is_numeric($mintaProyek) && $this->form['warehouse_id'] !== '' && auth()->user()->canAccessProject((int) $mintaProyek)) {
            $this->form['project_id'] = (string) (int) $mintaProyek;
        }
    }

    public function updatedFormWarehouseId(): void
    {
        $this->qty = [];
        $this->reason = [];
        $this->form['target_bin_id'] = '';
    }

    public function simpan(CreateWasteDisposal $action): void
    {
        $this->authorize('create', WasteDisposal::class);

        $this->validate([
            'form.warehouse_id' => ['required'],
            'form.project_id' => ['required'],
            'form.disposition' => ['required'],
        ], attributes: ['form.warehouse_id' => __('Gudang'), 'form.project_id' => __('Proyek'), 'form.disposition' => __('Disposisi')]);

        $calon = $this->calon();
        $baris = [];

        foreach ($this->qty as $kunci => $jumlah) {
            $c = $calon->get($kunci);

            if ($c !== null && $c['whole']) {
                if (filter_var($jumlah, FILTER_VALIDATE_BOOLEAN)) {
                    $baris[] = ['key' => $c['key'], 'qty_base' => $c['balance'], 'reason_code_id' => $this->alasanId((string) ($this->reason[$kunci] ?? ''), ReasonContext::Waste)];
                }

                continue;
            }

            if (is_numeric($jumlah) && (float) $jumlah != 0.0) {
                $baris[] = [
                    'key' => str_replace('_', ':', (string) $kunci),
                    'qty_base' => $jumlah,
                    'reason_code_id' => $this->alasanId((string) ($this->reason[$kunci] ?? ''), ReasonContext::Waste),
                ];
            }
        }

        $wst = null;

        $ok = $this->jalankan(function () use ($action, $baris, &$wst) {
            $wst = $action->handle($this->form, $baris, auth()->user());
        });

        if ($ok && $wst !== null) {
            $this->redirectRoute('waste-disposals.show', $wst, navigate: true);
        }
    }

    public function render(): View
    {
        $gudang = $this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']);

        return view('livewire.waste.waste-disposal-form', [
            'warehouses' => $this->gudang(),
            'projects' => $this->proyek($gudang),
            'dispositions' => WasteDisposition::options(),
            'bins' => $gudang !== null ? app(ConversionLines::class)->storageBins($gudang) : collect(),
            'calon' => $this->calon(),
            'alasanWaste' => $this->pilihanAlasan(ReasonContext::Waste),
        ]);
    }

    /** @return Collection<int, Warehouse> */
    private function gudang(): Collection
    {
        return Warehouse::query()->active()->with('type')->orderBy('code')
            ->get(['id', 'code', 'name', 'warehouse_type_id', 'project_id']);
    }

    /** @return Collection<int, Project> proyek aktif dalam cakupan; Gudang Site hanya proyek pemiliknya */
    private function proyek(?Warehouse $gudang): Collection
    {
        $ids = auth()->user()?->accessibleProjectIds();

        return Project::query()->active()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->when($gudang?->isSite(), fn ($q) => $q->whereKey($gudang->project_id))
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    /** @return Collection<string, array<string, mixed>> */
    private function calon(): Collection
    {
        $gudang = $this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']);

        if ($gudang === null) {
            return collect();
        }

        return app(DisposableWaste::class)->selectable($gudang)
            ->mapWithKeys(fn (array $c) => [str_replace(':', '_', $c['key']) => $c]);
    }
}
