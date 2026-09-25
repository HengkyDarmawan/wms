<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Livewire;

use App\Domain\Conversion\Actions\CreateConversion;
use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Livewire\Concerns\HandlesConversionRules;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Support\ConversionLines;
use App\Domain\Conversion\Support\ConvertibleStock;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 24-konversi-waste §6 — CNV baru / ubah draf: proyek `*`, gudang `*`,
 * jenis konversi, input dari stok Tersedia bin penyimpanan (potongan dipakai
 * utuh), lalu baris hasil output/offcut/waste/kerf dengan neraca ukuran
 * berjalan (BR-CNV-02/03, A-154, A-156).
 */
class ConversionForm extends Component
{
    use HandlesConversionRules;

    /** @var array<string, string> */
    public array $form = ['project_id' => '', 'warehouse_id' => '', 'conversion_type' => 'cut', 'notes' => ''];

    /** @var array<string, string> kunci calon (':' → '_') => jumlah (potongan: '1' = dipakai utuh) */
    public array $qty = [];

    /** @var array<int, array<string, string>> baris hasil */
    public array $outputs = [];

    #[Locked]
    public ?int $conversionId = null;

    public function mount(?Conversion $conversion = null): void
    {
        if ($conversion !== null && $conversion->exists) {
            $this->authorize('update', $conversion);

            $this->conversionId = (int) $conversion->id;
            $this->form = [
                'project_id' => (string) $conversion->project_id,
                'warehouse_id' => (string) $conversion->warehouse_id,
                'conversion_type' => $conversion->conversion_type->value,
                'notes' => (string) $conversion->notes,
            ];

            $kunciInput = [];

            foreach ($conversion->inputs()->orderBy('id')->get() as $i) {
                $kunci = ConvertibleStock::key((int) $i->bin_id, (int) $i->item_id, $i->lot_id, $i->piece_id);
                $kunciInput[(int) $i->id] = $kunci;
                $this->qty[str_replace(':', '_', $kunci)] = $i->piece_id !== null ? '1' : $this->angka((float) $i->qty_base);
            }

            foreach ($conversion->outputs()->with('reason:id,code')->orderBy('id')->get() as $o) {
                $this->outputs[] = [
                    'kind' => $o->auto_waste ? ConversionOutputKind::Offcut->value : $o->output_kind->value,
                    'item_id' => (string) $o->item_id,
                    'parent' => $kunciInput[(int) $o->parent_input_id] ?? '',
                    'qty_base' => $this->angka((float) $o->qty_base),
                    'count' => '1',
                    'lot_no' => (string) $o->lot_no,
                    'bin_id' => $o->output_kind->movesStock() && $o->output_kind !== ConversionOutputKind::Waste ? (string) $o->bin_id : '',
                    'reason' => (string) $o->reason?->code,
                ];
            }

            return;
        }

        $this->authorize('create', Conversion::class);

        $proyek = request()->query('project');
        $pilihan = $this->proyek();
        $awal = is_numeric($proyek) && $pilihan->contains('id', (int) $proyek) ? (int) $proyek : ($pilihan->count() === 1 ? (int) $pilihan->first()->id : 0);

        if ($awal > 0) {
            $this->form['project_id'] = (string) $awal;
        }

        $this->outputs[] = $this->barisKosong(ConversionOutputKind::Output);
    }

    public function updatedFormProjectId(): void
    {
        $gudang = $this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']);

        if ($gudang === null) {
            $this->form['warehouse_id'] = '';
            $this->qty = [];
        }
    }

    public function updatedFormWarehouseId(): void
    {
        $this->qty = [];

        foreach ($this->outputs as $i => $o) {
            $this->outputs[$i]['parent'] = '';
            $this->outputs[$i]['bin_id'] = '';
        }
    }

    public function tambahHasil(string $kind): void
    {
        $jenis = ConversionOutputKind::tryFrom($kind) ?? ConversionOutputKind::Output;
        $this->outputs[] = $this->barisKosong($jenis);
    }

    public function hapusHasil(int $index): void
    {
        unset($this->outputs[$index]);
        $this->outputs = array_values($this->outputs);
    }

    public function simpan(CreateConversion $action): void
    {
        $this->validate([
            'form.project_id' => ['required'],
            'form.warehouse_id' => ['required'],
            'form.conversion_type' => ['required'],
        ], attributes: ['form.project_id' => __('Proyek'), 'form.warehouse_id' => __('Gudang'), 'form.conversion_type' => __('Jenis konversi')]);

        $calon = $this->calon();
        $inputs = [];

        foreach ($this->qty as $kunci => $jumlah) {
            $c = $calon->get($kunci);

            if ($c !== null && $c['piece_id'] !== null) {
                // Potongan dipakai utuh: kotak centang = seluruh panjangnya.
                if (filter_var($jumlah, FILTER_VALIDATE_BOOLEAN)) {
                    $inputs[] = ['key' => $c['key'], 'qty_base' => $c['balance']];
                }

                continue;
            }

            if (is_numeric($jumlah) && (float) $jumlah != 0.0) {
                $inputs[] = ['key' => str_replace('_', ':', (string) $kunci), 'qty_base' => $jumlah];
            }
        }

        $outputs = array_map(fn (array $o) => [
            'kind' => $o['kind'] ?? '',
            'item_id' => $o['item_id'] ?? '',
            'parent' => $o['parent'] ?? '',
            'qty_base' => $o['qty_base'] ?? '',
            'count' => $o['count'] ?? '1',
            'lot_no' => $o['lot_no'] ?? '',
            'bin_id' => $o['bin_id'] ?? '',
            'reason_code_id' => $this->alasanId((string) ($o['reason'] ?? ''), ReasonContext::Waste),
        ], $this->outputs);

        $cnv = null;

        $ok = $this->jalankan(function () use ($action, $inputs, $outputs, &$cnv) {
            if ($this->conversionId !== null) {
                $lama = Conversion::query()->findOrFail($this->conversionId);
                $this->authorize('update', $lama);
                $cnv = $action->update($lama, $this->form, $inputs, $outputs, auth()->user());

                return;
            }

            $this->authorize('create', Conversion::class);
            $cnv = $action->handle($this->form, $inputs, $outputs, auth()->user());
        });

        if ($ok && $cnv !== null) {
            $this->redirectRoute('conversions.show', $cnv, navigate: true);
        }
    }

    public function render(): View
    {
        $calon = $this->calon();
        $gudang = $this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']);
        $dipilih = $this->inputTerpilih($calon);

        return view('livewire.conversion.conversion-form', [
            'projects' => $this->proyek(),
            'warehouses' => $this->gudang(),
            'types' => ConversionType::options(),
            'kinds' => ConversionOutputKind::options(),
            'calon' => $calon,
            'dipilih' => $dipilih,
            'items' => $this->itemHasil(),
            'bins' => $gudang !== null ? app(ConversionLines::class)->storageBins($gudang) : collect(),
            'alasanWaste' => $this->pilihanAlasan(ReasonContext::Waste),
            'neraca' => $this->neraca($dipilih),
            'nomor' => $this->conversionId !== null ? Conversion::query()->whereKey($this->conversionId)->value('number') : null,
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

    /** @return Collection<int, Warehouse> gudang aktif dalam cakupan; Gudang Site hanya milik proyek terpilih */
    private function gudang(): Collection
    {
        $proyek = (int) $this->form['project_id'];

        return Warehouse::query()->active()->with('type')
            ->orderBy('code')->get(['id', 'code', 'name', 'warehouse_type_id', 'project_id'])
            ->filter(fn (Warehouse $w) => ! $w->isSite() || (int) $w->project_id === $proyek)
            ->values();
    }

    /** @return Collection<string, array<string, mixed>> */
    private function calon(): Collection
    {
        $gudang = $this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']);

        if ($gudang === null) {
            return collect();
        }

        return app(ConvertibleStock::class)->selectable($gudang)
            ->mapWithKeys(fn (array $c) => [str_replace(':', '_', $c['key']) => $c]);
    }

    /**
     * Input yang sedang dipilih (untuk induk baris hasil & neraca).
     *
     * @param  Collection<string, array<string, mixed>>  $calon
     * @return Collection<string, array<string, mixed>> kunci asli => calon + qty
     */
    private function inputTerpilih(Collection $calon): Collection
    {
        return $calon->map(function (array $c, string $kunci) {
            $isi = $this->qty[$kunci] ?? '';
            $jumlah = $c['piece_id'] !== null
                ? (filter_var($isi, FILTER_VALIDATE_BOOLEAN) ? (float) $c['balance'] : 0.0)
                : (is_numeric($isi) ? (float) $isi : 0.0);

            return $c + ['qty' => $jumlah];
        })->filter(fn (array $c) => $c['qty'] > 0)->keyBy('key');
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $dipilih
     * @return array{input: float, hasil: float, selisih: float}
     */
    private function neraca(Collection $dipilih): array
    {
        $masuk = round((float) $dipilih->sum('qty'), 4);
        $keluar = 0.0;

        foreach ($this->outputs as $o) {
            $jumlah = is_numeric($o['qty_base'] ?? null) ? (float) $o['qty_base'] : 0.0;
            $ganda = is_numeric($o['count'] ?? null) ? max(1, (int) $o['count']) : 1;
            $keluar += $jumlah * $ganda;
        }

        $keluar = round($keluar, 4);

        return ['input' => $masuk, 'hasil' => $keluar, 'selisih' => round($masuk - $keluar, 4)];
    }

    /** @return Collection<int, Item> item yang boleh menjadi output: aktif, habis pakai, bukan serial */
    private function itemHasil(): Collection
    {
        return Item::query()
            ->where('status', ItemStatus::Active->value)
            ->where('ownership_model', OwnershipModel::Consumable->value)
            ->where('tracking_mode', '!=', TrackingMode::Serial->value)
            ->orderBy('code')->get(['id', 'code', 'name', 'tracking_mode']);
    }

    /** @return array<string, string> */
    private function barisKosong(ConversionOutputKind $jenis): array
    {
        return ['kind' => $jenis->value, 'item_id' => '', 'parent' => '', 'qty_base' => '', 'count' => '1', 'lot_no' => '', 'bin_id' => '', 'reason' => ''];
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}
