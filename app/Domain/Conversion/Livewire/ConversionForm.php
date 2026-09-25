<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Livewire;

use App\Domain\Conversion\Actions\CompleteConversion;
use App\Domain\Conversion\Actions\CreateConversion;
use App\Domain\Conversion\Actions\SubmitConversion;
use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Livewire\Concerns\HandlesConversionRules;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Support\ConversionApprovalRoute;
use App\Domain\Conversion\Support\ConversionLines;
use App\Domain\Conversion\Support\ConversionPlanner;
use App\Domain\Conversion\Support\ConvertibleStock;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Support\ScanCode;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 24-konversi-waste §6 — CNV baru / ubah draf, dirancang per jenis
 * (A-229): **Potong** = satu batang → daftar ukuran × jumlah, kerf & sisa
 * dihitung {@see ConversionPlanner}; **Ganti kemasan** = input → item tujuan,
 * susut otomatis waste; **Rakit/Bongkar** = input & hasil bebas tanpa neraca.
 * Ringkasan di layar dan baris yang disimpan berasal dari perencana yang
 * sama, jadi tidak ada selisih antara pratinjau dan pemeriksaan server.
 */
class ConversionForm extends Component
{
    use HandlesConversionRules;

    /** @var array<string, string> */
    public array $form = ['project_id' => '', 'warehouse_id' => '', 'conversion_type' => 'cut', 'notes' => '', 'bin_id' => ''];

    /** Potong: kunci calon batang (':' → '_'). */
    public string $batang = '';

    /** @var array<int, array<string, string>> Potong: id, item_id, length, count */
    public array $potong = [];

    /** @var array<string, string> Ganti kemasan / rakit / bongkar: kunci calon => jumlah */
    public array $qty = [];

    /** @var array<int, array<string, string>> hasil bebas: id, kind, item_id, qty, bin_id, lot_no */
    public array $hasil = [];

    public string $cari = '';

    public string $kodePindai = '';

    #[Locked]
    public ?int $conversionId = null;

    public function mount(?Conversion $conversion = null): void
    {
        if ($conversion !== null && $conversion->exists) {
            $this->authorize('update', $conversion);
            $this->isiDariDraf($conversion);

            return;
        }

        $this->authorize('create', Conversion::class);

        $proyek = request()->query('project');
        $pilihan = $this->proyek();
        $awal = is_numeric($proyek) && $pilihan->contains('id', (int) $proyek) ? (int) $proyek : ($pilihan->count() === 1 ? (int) $pilihan->first()->id : 0);

        if ($awal > 0) {
            $this->form['project_id'] = (string) $awal;
        }

        $this->potong[] = $this->barisPotong();
        $this->hasil[] = $this->barisHasil();
    }

    public function updatedFormProjectId(): void
    {
        if ($this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']) === null) {
            $this->form['warehouse_id'] = '';
            $this->kosongkanInput();
        }
    }

    public function updatedFormWarehouseId(): void
    {
        $this->kosongkanInput();
        $this->form['bin_id'] = '';
    }

    public function updatedFormConversionType(): void
    {
        $this->kosongkanInput();
        $this->resetErrorBag();
    }

    public function updatedBatang(): void
    {
        $this->resetErrorBag('rencana.batang');
    }

    public function tambahPotong(): void
    {
        $this->potong[] = $this->barisPotong();
    }

    public function hapusPotong(string $id): void
    {
        $this->potong = array_values(array_filter($this->potong, fn ($p) => $p['id'] !== $id)) ?: [$this->barisPotong()];
    }

    public function tambahHasil(string $kind = 'output'): void
    {
        $this->hasil[] = $this->barisHasil($kind === 'waste' ? 'waste' : 'output');
    }

    public function hapusHasil(string $id): void
    {
        $this->hasil = array_values(array_filter($this->hasil, fn ($h) => $h['id'] !== $id)) ?: [$this->barisHasil()];
    }

    /**
     * Pindai label (A-206): Potong → batang yang cocok dengan nomor potongan;
     * jenis lain → baris stok yang cocok ditambah 1 (potongan dipakai utuh).
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
        $arti = ScanCode::resolve($kode);

        $cocok = $calon->filter(function (array $c) use ($arti) {
            foreach ($arti as $a) {
                if ($a['item_id'] !== $c['item_id']) {
                    continue;
                }

                if ($c['piece_id'] !== null) {
                    return $a['piece_id'] === $c['piece_id'];
                }

                if ($c['lot_id'] !== null) {
                    return $a['lot_id'] === $c['lot_id'];
                }

                return $a['lot_id'] === null && $a['serial_id'] === null && $a['piece_id'] === null;
            }

            return false;
        });

        if ($cocok->isEmpty()) {
            $this->addError('kodePindai', __('":kode" tidak cocok dengan stok yang bisa dikonversi di gudang ini; item berlacak dipindai nomor lot/potongannya.', ['kode' => $kode]));

            return;
        }

        if ($this->jenis() === ConversionType::Cut) {
            $batang = $cocok->first(fn (array $c) => $c['piece_id'] !== null);

            if ($batang === null) {
                $this->addError('kodePindai', __('Mode Potong hanya menerima potongan (batang); pindai nomor potongannya.'));

                return;
            }

            $this->batang = str_replace(':', '_', $batang['key']);
            $this->updatedBatang();

            return;
        }

        foreach ($cocok as $kunci => $c) {
            $sekarang = is_numeric($this->qty[$kunci] ?? null) ? (float) $this->qty[$kunci] : 0.0;
            $isi = $c['piece_id'] !== null ? (float) $c['max'] : $sekarang + 1;

            if ($isi - (float) $c['max'] > 0.00005 || abs($isi - $sekarang) < 0.00005) {
                continue;
            }

            $this->qty[$kunci] = $this->angka($isi);

            return;
        }

        $this->addError('kodePindai', __('Jumlah :item sudah mencapai stok tersedia.', ['item' => $cocok->first()['item_code']]));
    }

    public function simpan(CreateConversion $action): void
    {
        $cnv = $this->simpanDraf($action);

        if ($cnv !== null) {
            $this->redirectRoute('conversions.show', $cnv, navigate: true);
        }
    }

    /** Simpan lalu selesaikan — atau ajukan bila ada aturan approval (A-153). */
    public function simpanDanSelesaikan(CreateConversion $action, CompleteConversion $selesai, SubmitConversion $ajukan, ConversionApprovalRoute $route): void
    {
        $cnv = $this->simpanDraf($action);

        if ($cnv === null) {
            return;
        }

        $ok = $this->jalankan(fn () => $route->needsApproval($cnv)
            ? $ajukan->handle($cnv, auth()->user())
            : $selesai->handle($cnv, auth()->user()));

        if ($ok) {
            $this->redirectRoute('conversions.show', $cnv, navigate: true);
        }
    }

    public function render(): View
    {
        $calon = $this->calon();
        $gudang = $this->gudang()->firstWhere('id', (int) $this->form['warehouse_id']);
        $jenis = $this->jenis();
        $items = $this->itemHasil();
        $batang = $jenis === ConversionType::Cut ? $calon->get($this->batang) : null;
        $uomInput = $batang['base_uom_id'] ?? $this->inputTerpilih($calon)->first()['base_uom_id'] ?? null;
        $rencana = $this->rencana($calon, $items);

        return view('livewire.conversion.conversion-form', [
            'projects' => $this->proyek(),
            'warehouses' => $this->gudang(),
            'types' => ConversionType::cases(),
            'jenis' => $jenis,
            'calon' => $this->saring($calon),
            'batangCalon' => $calon->filter(fn (array $c) => $c['piece_id'] !== null),
            'batangTerpilih' => $batang,
            'items' => $jenis->requiresSizeBalance() && $uomInput !== null ? $items->where('base_uom_id', $uomInput)->values() : $items->values(),
            'bins' => $gudang !== null ? app(ConversionLines::class)->storageBins($gudang) : collect(),
            'rencana' => $rencana,
            'butuhApproval' => $this->butuhApproval(),
            'nomor' => $this->conversionId !== null ? Conversion::query()->whereKey($this->conversionId)->value('number') : null,
        ]);
    }

    private function jenis(): ConversionType
    {
        return ConversionType::tryFrom($this->form['conversion_type']) ?? ConversionType::Cut;
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $calon
     * @param  Collection<int, Item>  $items
     * @return array{outputs: array<int, array<string, mixed>>, summary: array<string, mixed>, errors: array<string, string>}
     */
    private function rencana(Collection $calon, Collection $items): array
    {
        $jenis = $this->jenis();
        $inputs = $jenis === ConversionType::Cut
            ? array_values(array_filter([$this->batangTerpilih($calon)]))
            : $this->inputTerpilih($calon)->values()->all();

        $rows = $jenis === ConversionType::Cut ? $this->potong : $this->hasil;

        return app(ConversionPlanner::class)->plan($jenis, $inputs, $rows, $items->keyBy('id'), [
            'bin_id' => $this->form['bin_id'],
            'offcut_reason_id' => $this->alasanId('OFFCUT', ReasonContext::Waste),
            'spoil_reason_id' => $this->alasanId('SPOIL', ReasonContext::Waste),
        ]);
    }

    private function simpanDraf(CreateConversion $action): ?Conversion
    {
        $this->validate([
            'form.project_id' => ['required'],
            'form.warehouse_id' => ['required'],
            'form.conversion_type' => ['required'],
        ], attributes: ['form.project_id' => __('Proyek'), 'form.warehouse_id' => __('Gudang'), 'form.conversion_type' => __('Jenis konversi')]);

        $calon = $this->calon();
        $rencana = $this->rencana($calon, $this->itemHasil());

        if ($rencana['errors'] !== []) {
            foreach ($rencana['errors'] as $kunci => $pesan) {
                $this->addError('rencana.'.$kunci, $pesan);
            }

            $this->ruleCode = 'BR-CNV-02';
            $this->ruleError = __('Periksa isian konversi: :pesan', ['pesan' => reset($rencana['errors'])]);

            return null;
        }

        $inputs = $this->jenis() === ConversionType::Cut
            ? array_values(array_map(fn ($c) => ['key' => $c['key'], 'qty_base' => $c['qty']], array_filter([$this->batangTerpilih($calon)])))
            : $this->inputTerpilih($calon)->map(fn ($c) => ['key' => $c['key'], 'qty_base' => $c['qty']])->values()->all();

        $cnv = null;
        $header = ['project_id' => $this->form['project_id'], 'warehouse_id' => $this->form['warehouse_id'], 'conversion_type' => $this->form['conversion_type'], 'notes' => $this->form['notes']];

        $this->jalankan(function () use ($action, $header, $inputs, $rencana, &$cnv) {
            if ($this->conversionId !== null) {
                $lama = Conversion::query()->findOrFail($this->conversionId);
                $this->authorize('update', $lama);
                $cnv = $action->update($lama, $header, $inputs, $rencana['outputs'], auth()->user());

                return;
            }

            $this->authorize('create', Conversion::class);
            $cnv = $action->handle($header, $inputs, $rencana['outputs'], auth()->user());
        });

        return $cnv;
    }

    /** Draf yang diubah dibaca kembali ke isian per jenis; sisa & kerf otomatis tidak menjadi baris. */
    private function isiDariDraf(Conversion $conversion): void
    {
        $this->conversionId = (int) $conversion->id;
        $this->form = [
            'project_id' => (string) $conversion->project_id,
            'warehouse_id' => (string) $conversion->warehouse_id,
            'conversion_type' => $conversion->conversion_type->value,
            'notes' => (string) $conversion->notes,
            'bin_id' => '',
        ];

        $inputs = $conversion->inputs()->orderBy('id')->get();
        $outputs = $conversion->outputs()->orderBy('id')->get();

        if ($conversion->conversion_type === ConversionType::Cut) {
            $pertama = $inputs->first();
            $this->batang = $pertama === null ? '' : str_replace(':', '_', ConvertibleStock::key((int) $pertama->bin_id, (int) $pertama->item_id, $pertama->lot_id, $pertama->piece_id));

            $this->potong = $outputs->where('output_kind', ConversionOutputKind::Output)
                ->groupBy(fn ($o) => $o->item_id.'|'.(float) $o->qty_base)
                ->map(fn ($g) => ['id' => (string) Str::uuid(), 'item_id' => (int) $g->first()->item_id === (int) $pertama?->item_id ? '' : (string) $g->first()->item_id, 'length' => $this->angka((float) $g->first()->qty_base), 'count' => (string) $g->count()])
                ->values()->all() ?: [$this->barisPotong()];
            $this->form['bin_id'] = (string) ($outputs->firstWhere('output_kind', ConversionOutputKind::Output)?->bin_id ?? '');
            $this->hasil = [$this->barisHasil()];

            return;
        }

        foreach ($inputs as $i) {
            $kunci = str_replace(':', '_', ConvertibleStock::key((int) $i->bin_id, (int) $i->item_id, $i->lot_id, $i->piece_id));
            $this->qty[$kunci] = $this->angka((float) $i->qty_base);
        }

        $this->hasil = $outputs->filter(fn ($o) => $o->output_kind === ConversionOutputKind::Output || ($o->output_kind === ConversionOutputKind::Waste && ! $o->auto_waste && $conversion->conversion_type !== ConversionType::Repack))
            ->map(fn ($o) => ['id' => (string) Str::uuid(), 'kind' => $o->output_kind->value, 'item_id' => (string) $o->item_id, 'qty' => $this->angka((float) $o->qty_base), 'bin_id' => (string) ($o->bin_id ?? ''), 'lot_no' => (string) $o->lot_no])
            ->values()->all() ?: [$this->barisHasil()];
        $this->potong = [$this->barisPotong()];
    }

    private function kosongkanInput(): void
    {
        $this->batang = '';
        $this->qty = [];
        $this->potong = [$this->barisPotong()];
        $this->hasil = [$this->barisHasil()];
    }

    private function butuhApproval(): bool
    {
        if ($this->form['project_id'] === '' || $this->form['warehouse_id'] === '') {
            return false;
        }

        // Aturan CNV hanya memakai gudang/proyek/kategori; contoh dokumen tanpa baris cukup untuk menebak label tombol.
        $contoh = new Conversion([
            'project_id' => (int) $this->form['project_id'],
            'warehouse_id' => (int) $this->form['warehouse_id'],
            'conversion_type' => $this->form['conversion_type'],
        ]);

        try {
            return app(ConversionApprovalRoute::class)->needsApproval($contoh);
        } catch (\Throwable) {
            return false;
        }
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

    /** @return Collection<string, array<string, mixed>> kunci ('_') => calon */
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
     * Tabel calon untuk jenis selain Potong: tanpa batang (potongan dipotong lewat mode Potong), disaring teks.
     *
     * @param  Collection<string, array<string, mixed>>  $calon
     * @return Collection<string, array<string, mixed>>
     */
    private function saring(Collection $calon): Collection
    {
        $q = mb_strtolower(trim($this->cari));

        return $calon
            ->filter(fn (array $c) => $this->jenis() !== ConversionType::Repack || $c['piece_id'] === null)
            ->filter(fn (array $c, string $kunci) => $q === '' || isset($this->qty[$kunci])
                || str_contains(mb_strtolower($c['item_code'].' '.$c['item_name'].' '.$c['bin_code'].' '.$c['tracking']), $q));
    }

    /** @param  Collection<string, array<string, mixed>>  $calon */
    private function batangTerpilih(Collection $calon): ?array
    {
        $c = $calon->get($this->batang);

        return $c === null || $c['piece_id'] === null ? null : $c + ['qty' => (float) $c['balance']];
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $calon
     * @return Collection<string, array<string, mixed>>
     */
    private function inputTerpilih(Collection $calon): Collection
    {
        return $calon->map(function (array $c, string $kunci) {
            $isi = $this->qty[$kunci] ?? '';
            $jumlah = $c['piece_id'] !== null
                ? (filter_var($isi, FILTER_VALIDATE_BOOLEAN) || (is_numeric($isi) && (float) $isi > 0) ? (float) $c['balance'] : 0.0)
                : (is_numeric($isi) ? (float) $isi : 0.0);

            return $c + ['qty' => $jumlah];
        })->filter(fn (array $c) => $c['qty'] > 0);
    }

    /** @return Collection<int, Item> item yang boleh menjadi hasil: aktif, habis pakai, bukan serial */
    private function itemHasil(): Collection
    {
        return Item::query()
            ->with('baseUom:id,code')
            ->where('status', ItemStatus::Active->value)
            ->where('ownership_model', OwnershipModel::Consumable->value)
            ->where('tracking_mode', '!=', TrackingMode::Serial->value)
            ->orderBy('code')->get(['id', 'code', 'name', 'tracking_mode', 'base_uom_id']);
    }

    /** @return array<string, string> */
    private function barisPotong(): array
    {
        return ['id' => (string) Str::uuid(), 'item_id' => '', 'length' => '', 'count' => '1'];
    }

    /** @return array<string, string> */
    private function barisHasil(string $kind = 'output'): array
    {
        return ['id' => (string) Str::uuid(), 'kind' => $kind, 'item_id' => '', 'qty' => '', 'bin_id' => '', 'lot_no' => ''];
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}
