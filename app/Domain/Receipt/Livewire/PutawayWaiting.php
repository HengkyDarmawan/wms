<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Master\Support\QtyFormat;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Models\PutawayTaskLine;
use App\Domain\Receipt\Support\PutawayTargets;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Layar **Menunggu Dimasukkan** (K-F, A-376) — put-away dengan pindai di HP:
 * pindai label barang → layar menunjuk bin tujuan (kode pendek besar) →
 * pindai QR bin → baris itu langsung ditaruh (satu pergerakan stok lewat
 * `CompletePutaway::placeLine`, keputusan #6). Bin lain menuntut alasan;
 * bin Khusus barang lain ditolak kecuali dibuka Kepala Gudang.
 */
class PutawayWaiting extends Component
{
    use HandlesReceiptRules;

    #[Url(except: '')]
    public string $gudang = '';

    public string $kodeBarang = '';

    public string $kodeBin = '';

    /** Baris yang sedang dikerjakan. */
    public ?int $pilih = null;

    /** @var array<int, int> baris yang cocok dengan pindaian terakhir (lebih dari satu) */
    public array $saring = [];

    /** Bin selain saran yang dipindai — menunggu alasan. */
    public ?int $binLain = null;

    public string $alasan = '';

    /** BR-WH-10 (A-367): alasan Kepala Gudang membuka bin Khusus Barang Ini. */
    public string $bukaKhusus = '';

    /** @var array<int, string> */
    public array $peringatan = [];

    public string $terakhir = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PutawayTask::class);
    }

    public function render(PutawayTargets $target): View
    {
        $gudangId = $this->gudang !== '' ? (int) $this->gudang : null;
        $semua = $target->menunggu($gudangId);
        $daftar = $this->saring !== [] ? $semua->whereIn('id', $this->saring)->values() : $semua;
        $baris = $this->pilih !== null ? $this->baris($this->pilih) : null;
        $bins = $semua->pluck('suggestedBin')->concat([$baris?->suggestedBin])->filter()->unique('id')->values();

        if ($this->binLain !== null && ($b = Bin::query()->withoutGlobalScopes()->find($this->binLain)) !== null) {
            $bins->push($b);
        }

        return view('livewire.receipt.putaway-waiting', [
            'daftar' => $daftar,
            'jumlah' => $semua->count(),
            'baris' => $baris,
            'pendek' => BinCode::pendekBanyak($bins),
            'penuh' => $target->penuh($baris !== null ? $semua->concat([$baris])->unique('id') : $semua),
            'binLainModel' => $this->binLain !== null ? $bins->firstWhere('id', $this->binLain) : null,
            'gudangList' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'bolehTaruh' => auth()->user()?->hasPermission('putaway.complete') ?? false,
            'qty' => fn (PutawayTaskLine $l) => QtyFormat::withUnit($l->qty_base, $l->item?->baseUom?->code)
                .(($k = QtyFormat::packaging($l->item, $l->qty_base)) !== null ? ' ('.$k.')' : ''),
        ]);
    }

    public function updatedGudang(): void
    {
        $this->batalPilih();
    }

    /** Langkah 1: pindai label barang (label kemasan, lot, serial, potongan, kode/barcode item). */
    public function pindaiBarang(PutawayTargets $target): void
    {
        $kode = trim($this->kodeBarang);
        $this->kodeBarang = '';
        $this->resetErrorBag('kodeBarang');

        if ($kode === '') {
            return;
        }

        $semua = $target->menunggu($this->gudang !== '' ? (int) $this->gudang : null);
        $cocok = $target->cocokBarang($kode, $semua);

        if ($cocok->count() === 1) {
            $this->mulai((int) $cocok->first()->id);

            return;
        }

        if ($cocok->count() > 1) {
            $this->batalPilih();
            $this->saring = $cocok->pluck('id')->map(fn ($v) => (int) $v)->all();
            $this->addError('kodeBarang', __(':n baris cocok — ketuk salah satu.', ['n' => $cocok->count()]));

            return;
        }

        $gudangBaris = $semua->map(fn (PutawayTaskLine $l) => (int) $l->task->warehouse_id)->unique();

        $this->addError('kodeBarang', $gudangBaris->contains(fn (int $g) => $target->binTujuan($kode, $g) instanceof Bin)
            ? __('Itu kode bin. Pindai label barang dulu, lalu QR bin.')
            : __('":kode" tidak cocok dengan barang yang menunggu dimasukkan.', ['kode' => $kode]));
    }

    /** Ketuk baris di daftar. */
    public function mulai(int $lineId): void
    {
        $baris = $this->baris($lineId);

        if ($baris === null) {
            $this->addError('kodeBarang', __('Baris ini sudah ditaruh atau tidak ada di cakupan Anda.'));

            return;
        }

        $this->pilih = (int) $baris->id;
        $this->saring = [];
        $this->binLain = null;
        $this->alasan = '';
        $this->ruleError = '';
        $this->ruleCode = '';
        $this->resetErrorBag();
    }

    public function batalPilih(): void
    {
        $this->pilih = null;
        $this->saring = [];
        $this->binLain = null;
        $this->alasan = '';
        $this->kodeBin = '';
        $this->ruleError = '';
        $this->ruleCode = '';
        $this->resetErrorBag();
    }

    /** Langkah 2: pindai QR bin tujuan. Bin saran → langsung ditaruh. */
    public function pindaiBin(PutawayTargets $target, CompletePutaway $action): void
    {
        $kode = trim($this->kodeBin);
        $this->kodeBin = '';
        $this->resetErrorBag('kodeBin');
        $baris = $this->pilih !== null ? $this->baris($this->pilih) : null;

        if ($kode === '' || $baris === null) {
            return;
        }

        $bin = $target->binTujuan($kode, (int) $baris->task->warehouse_id);

        if (is_string($bin)) {
            $this->addError('kodeBin', $bin);

            return;
        }

        if ($baris->suggested_bin_id !== null && (int) $bin->id !== (int) $baris->suggested_bin_id) {
            // Bin lain = alasan wajib (BR-GRN-03), tercatat di baris & kartu stok.
            $this->binLain = (int) $bin->id;

            return;
        }

        $this->binLain = null;
        $this->taruh($baris, $bin, null, $action);
    }

    /** Konfirmasi bin lain dengan alasan. */
    public function taruhBinLain(CompletePutaway $action): void
    {
        $baris = $this->pilih !== null ? $this->baris($this->pilih) : null;
        $bin = $this->binLain !== null ? Bin::query()->withoutGlobalScopes()->find($this->binLain) : null;

        if ($baris === null || $bin === null) {
            return;
        }

        if (trim($this->alasan) === '') {
            $this->addError('alasan', __('Alasan wajib diisi bila menaruh di bin selain saran.'));

            return;
        }

        $this->taruh($baris, $bin, trim($this->alasan), $action);
    }

    private function taruh(PutawayTaskLine $baris, Bin $bin, ?string $alasan, CompletePutaway $action): void
    {
        $task = PutawayTask::query()->findOrFail($baris->putaway_task_id);
        $this->authorize('complete', $task);

        $hasil = null;
        $ok = $this->jalankan(function () use ($action, $task, $baris, $bin, $alasan, &$hasil) {
            $hasil = $action->placeLine($task, (int) $baris->id, [
                'bin_id' => $bin->id,
                'override_reason' => $alasan,
                'buka_khusus' => $this->bukaKhusus,
            ], auth()->user());
        });

        if (! $ok) {
            return;
        }

        $this->peringatan = $action->warnings();
        $this->terakhir = __(':item ditaruh di :bin.', ['item' => $baris->item?->code, 'bin' => BinCode::pendekUntuk($bin)])
            .($hasil?->status === PutawayTaskStatus::Completed ? ' '.__(':put selesai.', ['put' => $task->number]) : '');
        $this->batalPilih();
        $this->dispatch('pesan', teks: $this->terakhir);
    }

    /** Baris menunggu dalam cakupan (tugas bercakupan gudang, BR-ACC-05). */
    private function baris(int $lineId): ?PutawayTaskLine
    {
        return PutawayTaskLine::query()
            ->whereKey($lineId)
            ->whereNull('scanned_at')
            ->whereIn('putaway_task_id', PutawayTask::query()->where('status', PutawayTaskStatus::Pending->value)->select('id'))
            ->with('task:id,number,warehouse_id,goods_receipt_id', 'item:id,code,name,base_uom_id,tracking_mode', 'item.baseUom:id,code',
                'item.activeConversions.uom', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no', 'suggestedBin', 'fromBin:id,code')
            ->first();
    }
}
