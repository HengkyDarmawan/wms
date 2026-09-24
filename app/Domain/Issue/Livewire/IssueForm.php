<?php

declare(strict_types=1);

namespace App\Domain\Issue\Livewire;

use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Livewire\Concerns\HandlesIssueRules;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Support\IssuableStock;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 23-pemakaian §6 — ISU baru / ubah draf: proyek `*`, Gudang Site `*`
 * milik proyek itu, lalu jumlah dan keperluan per barang stok Tersedia di bin
 * penyimpanan Gudang Site (BR-PRJ-08, A-117). Serial per unit, potongan utuh.
 */
class IssueForm extends Component
{
    use HandlesIssueRules;

    /** @var array<string, string> */
    public array $form = ['project_id' => '', 'warehouse_id' => '', 'notes' => ''];

    /** @var array<string, string> kunci calon (':' → '_') => jumlah */
    public array $qty = [];

    /** @var array<string, string> kunci calon => keperluan */
    public array $note = [];

    #[Locked]
    public ?int $issueId = null;

    public function mount(?MaterialIssue $materialIssue = null): void
    {
        if ($materialIssue !== null && $materialIssue->exists) {
            $this->authorize('update', $materialIssue);

            $this->issueId = (int) $materialIssue->id;
            $this->form = [
                'project_id' => (string) $materialIssue->project_id,
                'warehouse_id' => (string) $materialIssue->warehouse_id,
                'notes' => (string) $materialIssue->notes,
            ];

            foreach ($materialIssue->lines as $l) {
                $kunci = str_replace(':', '_', IssuableStock::key((int) $l->bin_id, (int) $l->item_id, $l->lot_id, $l->serial_id, $l->piece_id));
                $this->qty[$kunci] = rtrim(rtrim(number_format((float) $l->qty_base, 4, '.', ''), '0'), '.');
                $this->note[$kunci] = (string) $l->work_note;
            }

            return;
        }

        $this->authorize('create', MaterialIssue::class);

        $proyek = request()->query('project');
        $pilihan = $this->proyek();
        $awal = is_numeric($proyek) && $pilihan->contains('id', (int) $proyek) ? (int) $proyek : ($pilihan->count() === 1 ? (int) $pilihan->first()->id : 0);

        if ($awal > 0) {
            $this->form['project_id'] = (string) $awal;
            $this->pilihSiteTunggal();
        }
    }

    public function updatedFormProjectId(): void
    {
        $this->form['warehouse_id'] = '';
        $this->qty = [];
        $this->note = [];
        $this->pilihSiteTunggal();
    }

    public function updatedFormWarehouseId(): void
    {
        $this->qty = [];
        $this->note = [];
    }

    public function simpan(CreateMaterialIssue $action): void
    {
        $this->validate([
            'form.project_id' => ['required'],
            'form.warehouse_id' => ['required'],
        ], attributes: ['form.project_id' => __('Proyek'), 'form.warehouse_id' => __('Gudang Site')]);

        $baris = [];

        foreach ($this->qty as $kunci => $jumlah) {
            if (is_numeric($jumlah) && (float) $jumlah != 0.0) {
                $baris[] = [
                    'key' => str_replace('_', ':', (string) $kunci),
                    'qty_base' => $jumlah,
                    'work_note' => $this->note[$kunci] ?? null,
                ];
            }
        }

        $isu = null;

        $ok = $this->jalankan(function () use ($action, $baris, &$isu) {
            if ($this->issueId !== null) {
                $lama = MaterialIssue::query()->findOrFail($this->issueId);
                $this->authorize('update', $lama);
                $isu = $action->update($lama, $this->form, $baris, auth()->user());

                return;
            }

            $this->authorize('create', MaterialIssue::class);
            $isu = $action->handle($this->form, $baris, auth()->user());
        });

        if ($ok && $isu !== null) {
            $this->redirectRoute('issues.show', $isu, navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.issue.issue-form', [
            'projects' => $this->proyek(),
            'sites' => $this->sites(),
            'calon' => $this->calon(),
            'nomor' => $this->issueId !== null ? MaterialIssue::query()->whereKey($this->issueId)->value('number') : null,
        ]);
    }

    /** @return Collection<int, Project> proyek aktif dalam cakupan pengguna */
    private function proyek(): Collection
    {
        $ids = auth()->user()?->accessibleProjectIds();

        return Project::query()->active()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    /** @return Collection<int, Warehouse> Gudang Site aktif proyek terpilih, dalam cakupan gudang pengguna */
    private function sites(): Collection
    {
        $proyek = (int) $this->form['project_id'];

        if ($proyek === 0) {
            return collect();
        }

        return Warehouse::query()->active()->with('type')
            ->where('project_id', $proyek)
            ->orderBy('code')->get(['id', 'code', 'name', 'warehouse_type_id', 'project_id'])
            ->filter(fn (Warehouse $w) => $w->isSite())->values();
    }

    private function pilihSiteTunggal(): void
    {
        $sites = $this->sites();

        if ($sites->count() === 1) {
            $this->form['warehouse_id'] = (string) $sites->first()->id;
        }
    }

    /** @return Collection<string, array<string, mixed>> */
    private function calon(): Collection
    {
        $site = $this->sites()->firstWhere('id', (int) $this->form['warehouse_id']);

        if ($site === null) {
            return collect();
        }

        return app(IssuableStock::class)->selectable($site)
            ->mapWithKeys(fn (array $c) => [str_replace(':', '_', $c['key']) => $c]);
    }
}
