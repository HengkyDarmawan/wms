<?php

declare(strict_types=1);

namespace App\Domain\Issue\Livewire;

use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Livewire\Concerns\HandlesIssueRules;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Support\IssuableStock;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Livewire\Concerns\CapturesPackageLabels;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Support\ScanCode;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
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
    use CapturesPackageLabels;
    use CariPilihan;
    use HandlesIssueRules;

    /** @var array<string, string> */
    public array $form = ['project_id' => '', 'warehouse_id' => '', 'notes' => ''];

    /** @var array<string, string> kunci calon (':' → '_') => jumlah */
    public array $qty = [];

    /** @var array<string, string> kunci calon => keperluan */
    public array $note = [];

    /** @var array<string, list<array{id: int, qty: float}>> kunci calon => klaim label kemasan (A-299) */
    public array $labels = [];

    #[Locked]
    public ?int $issueId = null;

    public string $kodePindai = '';

    /** Kunci calon yang terakhir terisi lewat pindai, untuk disorot. */
    public ?string $sorot = null;

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

                if (($l->labels ?? []) !== []) {
                    $this->labels[$kunci] = $l->labels;
                }
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
        $this->labels = [];
        $this->sorot = null;
        $this->pilihSiteTunggal();
    }

    public function updatedFormWarehouseId(): void
    {
        $this->qty = [];
        $this->note = [];
        $this->labels = [];
        $this->sorot = null;
    }

    /**
     * Pindai label (A-206) ke stok Gudang Site yang tampil: item tanpa lacak
     * dicocokkan lewat kode/barcode dan jumlahnya ditambah satu (sampai batas
     * tersedia, lalu bin berikutnya); item berlacak harus dipindai nomor
     * lot/serial/potongannya — serial terisi 1, potongan terisi utuh (A-117).
     */
    public function pindai(): void
    {
        $kode = ScanCode::normalize($this->kodePindai);
        $this->kodePindai = '';
        $this->resetErrorBag('kodePindai');

        if ($kode === '') {
            return;
        }

        $calon = $this->calon()->reject(fn (array $c) => $c['frozen']);

        if ($calon->isEmpty()) {
            $this->addError('kodePindai', __('Pilih proyek dan Gudang Site yang punya stok dulu.'));

            return;
        }

        // A-299: label kemasan induk/isi — klaim label pada baris item+lot-nya.
        if (($label = ScanCode::label($kode)) !== null) {
            $this->pindaiLabel($calon, $label);

            return;
        }

        $arti = ScanCode::resolve($kode);
        $berlacak = fn (array $c) => $c['lot_id'] !== null || $c['serial_id'] !== null || $c['piece_id'] !== null;

        $cocok = $calon->filter(function (array $c) use ($arti, $berlacak) {
            foreach ($arti as $a) {
                if ($a['item_id'] !== $c['item_id']) {
                    continue;
                }

                if (! $berlacak($c)) {
                    return $a['lot_id'] === null && $a['serial_id'] === null && $a['piece_id'] === null;
                }

                if (($a['lot_id'] !== null && $a['lot_id'] === $c['lot_id'])
                    || ($a['serial_id'] !== null && $a['serial_id'] === $c['serial_id'])
                    || ($a['piece_id'] !== null && $a['piece_id'] === $c['piece_id'])) {
                    return true;
                }
            }

            return false;
        });

        if ($cocok->isEmpty()) {
            $hanyaItem = collect($arti)->pluck('item_id')->intersect($calon->filter($berlacak)->pluck('item_id'))->isNotEmpty();

            $this->addError('kodePindai', $hanyaItem
                ? __('Item ini berlacak; pindai nomor lot/serial/potongan pada labelnya.')
                : __('":kode" tidak cocok dengan stok yang bisa dipakai di Gudang Site ini.', ['kode' => $kode]));

            return;
        }

        foreach ($cocok as $kunci => $c) {
            $sekarang = is_numeric($this->qty[$kunci] ?? null) ? (float) $this->qty[$kunci] : 0.0;
            $isi = match (true) {
                $c['serial_id'] !== null => 1.0,
                $c['piece_id'] !== null => (float) $c['max'],
                default => $sekarang + 1,
            };

            if ($isi - (float) $c['max'] > 0.00005 || abs($isi - $sekarang) < 0.00005) {
                continue;
            }

            $this->qty[$kunci] = rtrim(rtrim(number_format($isi, 4, '.', ''), '0'), '.');
            $this->sorot = (string) $kunci;

            return;
        }

        $this->addError('kodePindai', __('Jumlah :item sudah mencapai stok tersedia.', ['item' => $cocok->first()['item_code']]));
    }

    public function simpan(CreateMaterialIssue $action): void
    {
        // A-395: proyek dari daftar (ISU baru; proyek draf terkunci); id lain dari browser ditolak di isiannya.
        $this->validate([
            'form.project_id' => ['required', ...($this->issueId === null ? [$this->pilihanProyek()->aturan()] : [])],
            'form.warehouse_id' => ['required'],
        ], attributes: ['form.project_id' => __('Proyek'), 'form.warehouse_id' => __('Gudang Site')]);

        $baris = [];

        foreach ($this->qty as $kunci => $jumlah) {
            if (is_numeric($jumlah) && (float) $jumlah != 0.0) {
                $baris[] = [
                    'key' => str_replace('_', ':', (string) $kunci),
                    'qty_base' => $jumlah,
                    'work_note' => $this->note[$kunci] ?? null,
                    'labels' => $this->labels[$kunci] ?? [],
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

    public function lepasLabel(string $kunci, int $labelId): void
    {
        $sisa = collect($this->labels[$kunci] ?? [])->reject(fn ($c) => (int) $c['id'] === $labelId)->values()->all();

        if ($sisa === []) {
            unset($this->labels[$kunci]);
        } else {
            $this->labels[$kunci] = $sisa;
        }
    }

    /** @param  Collection<string, array<string, mixed>>  $calon */
    private function pindaiLabel(Collection $calon, PackageLabel $label): void
    {
        $cocok = $calon->filter(fn (array $c) => $c['item_id'] === (int) $label->item_id && $c['serial_id'] === null && $c['piece_id'] === null
            && (int) $c['lot_id'] === (int) $label->lot_id);

        if ($cocok->isEmpty()) {
            $this->addError('kodePindai', __('Label :kode tidak cocok dengan stok yang bisa dipakai di Gudang Site ini.', ['kode' => $label->code]));

            return;
        }

        $kunci = (string) $cocok->sortByDesc(fn (array $c) => $c['bin_id'] === (int) $label->bin_id)->keys()->first();
        $this->sorot = $kunci;

        $this->pindaiLabelKemasan($label, $kunci, (float) $cocok[$kunci]['max'] - $this->diklaim($kunci), 'kodePindai');
    }

    protected function klaimLabel(string $baris, PackageLabel $label, float $qty): bool
    {
        $c = $this->calon()->get($baris);
        $medan = $this->labelInduk === null ? 'kodePindai' : 'labelIsi';

        if ($c === null) {
            $this->addError($medan, __('Baris stok tidak ditemukan lagi.'));

            return false;
        }

        $klaim = collect($this->labels[$baris] ?? [])->reject(fn ($k) => (int) $k['id'] === (int) $label->id)->values()->all();
        $klaim[] = ['id' => (int) $label->id, 'qty' => round($qty, 4)];

        try {
            app(PackageLabelLedger::class)->resolveClaims($klaim, (int) $this->form['warehouse_id'], (int) $c['item_id'], $c['lot_id'], 'Baris '.$c['item_code']);
        } catch (LabelRuleException $e) {
            $this->addError($medan, $e->getMessage());

            return false;
        }

        $total = round(array_sum(array_column($klaim, 'qty')), 4);

        if ($total - (float) $c['max'] > 0.00005) {
            $this->addError($medan, __('Isi label (:qty) melebihi stok tersedia :item.', ['qty' => PackageLabelLedger::angka($total), 'item' => $c['item_code']]));

            return false;
        }

        $this->labels[$baris] = $klaim;
        $sekarang = is_numeric($this->qty[$baris] ?? null) ? (float) $this->qty[$baris] : 0.0;
        $this->qty[$baris] = PackageLabelLedger::angka(max($sekarang, $total));
        $this->sorot = $baris;

        return true;
    }

    private function diklaim(string $kunci): float
    {
        return round(array_sum(array_map(fn ($c) => (float) $c['qty'], $this->labels[$kunci] ?? [])), 4);
    }

    public function render(): View
    {
        return view('livewire.issue.issue-form', [
            'opsiProyek' => $this->pilihanProyek()->awalDengan($this->form['project_id']),
            'sites' => $this->sites(),
            'calon' => $this->calon(),
            'kodeLabel' => $this->kodeLabel(collect($this->labels)->flatten(1)),
            'labelDialog' => $this->labelDialog(),
            'nomor' => $this->issueId !== null ? MaterialIssue::query()->whereKey($this->issueId)->value('number') : null,
        ]);
    }

    /** Proyek aktif dalam id cakupan (daftar lama `proyek()`), dicari ke server (A-395). */
    private function pilihanProyek(): Pilihan
    {
        return SumberPilihan::proyekIdCakupan(aktif: true);
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        if ($model !== 'form.project_id' || $this->issueId !== null) {
            return null;
        }

        return auth()->user()?->can('create', MaterialIssue::class) ? $this->pilihanProyek() : null;
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
