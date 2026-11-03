<?php

declare(strict_types=1);

namespace App\Domain\Waste\Livewire;

use App\Domain\Conversion\Support\ConversionLines;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
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
    use CariPilihan;
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

        // Daftar proyek ikut gudang (Gudang Site = proyek pemiliknya): nilai di luar daftar baru dikosongkan.
        if (! $this->pilihanProyek()->berisi($this->form['project_id'])) {
            $this->form['project_id'] = '';
        }
    }

    public function simpan(CreateWasteDisposal $action): void
    {
        $this->authorize('create', WasteDisposal::class);

        // A-395: proyek & bin tujuan dari daftar; id lain dari browser ditolak di isiannya.
        $this->validate([
            'form.warehouse_id' => ['required'],
            'form.project_id' => ['required', $this->pilihanProyek()->aturan()],
            'form.disposition' => ['required'],
            'form.target_bin_id' => [$this->pilihanBin()?->aturan() ?? 'nullable'],
        ], attributes: ['form.warehouse_id' => __('Gudang'), 'form.project_id' => __('Proyek'), 'form.disposition' => __('Disposisi'), 'form.target_bin_id' => __('Bin tujuan')]);

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
            // A-395: proyek & bin tujuan dicari ke server (daftar lama).
            'opsiProyek' => $this->pilihanProyek()->awalDengan($this->form['project_id']),
            'dispositions' => WasteDisposition::options(),
            'opsiBin' => $gudang !== null ? app(ConversionLines::class)->pilihanStorageBin($gudang)->awalDengan($this->form['target_bin_id']) : [],
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

    /** Proyek aktif dalam id cakupan; Gudang Site hanya proyek pemiliknya (daftar lama), dicari ke server (A-395). */
    private function pilihanProyek(): Pilihan
    {
        $gudang = $this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']);

        return SumberPilihan::proyekIdCakupan(aktif: true)
            ->saring(fn ($q) => $q->when($gudang?->isSite(), fn ($q) => $q->whereKey($gudang->project_id)));
    }

    private function pilihanBin(): ?Pilihan
    {
        $gudang = $this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']);

        return $gudang === null ? null : app(ConversionLines::class)->pilihanStorageBin($gudang);
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        if (! auth()->user()?->can('create', WasteDisposal::class)) {
            return null;
        }

        return match ($model) {
            'form.project_id' => $this->pilihanProyek(),
            'form.target_bin_id' => $this->form['disposition'] === 'reused' ? $this->pilihanBin() : null,
            default => null,
        };
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
