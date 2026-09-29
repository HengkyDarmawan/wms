<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\CancelPutaway;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Support\PutawaySuggester;
use App\Domain\Receipt\Support\PutawayTargets;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 19-receipt-putaway §6.5 — mengerjakan satu tugas put-away: bin saran
 * sudah terisi, staf boleh menggantinya dengan alasan.
 */
class PutawayDetail extends Component
{
    use HandlesReceiptRules;

    #[Locked]
    public int $taskId;

    /** @var array<int|string, array<string, string>> line_id => [bin_id, override_reason] */
    public array $isian = [];

    public string $dialog = '';

    /** @var array<string, string> */
    public array $form = ['reason' => '', 'notes' => ''];

    /** @var array<int, string> */
    public array $peringatan = [];

    /** BR-WH-10 (A-367): alasan Kepala Gudang membuka bin Khusus Barang Ini. */
    public string $bukaKhusus = '';

    public function mount(PutawayTask $putawayTask): void
    {
        $this->authorize('view', $putawayTask);

        $this->taskId = (int) $putawayTask->id;

        foreach ($putawayTask->lines as $l) {
            $this->isian[$l->id] = [
                'bin_id' => (string) ($l->bin_id ?? $l->suggested_bin_id ?? ''),
                'override_reason' => (string) ($l->override_reason ?? ''),
            ];
        }
    }

    public function render(PutawaySuggester $saran): View
    {
        $task = $this->task();

        $lines = $task->lines()->with('task:id,warehouse_id', 'item:id,code,name', 'fromBin:id,code', 'suggestedBin:id,code,warehouse_id', 'bin:id,code,warehouse_id', 'lot', 'serial', 'piece')->orderBy('id')->get();
        $bins = $saran->storageBins($task->warehouse);

        return view('livewire.receipt.putaway-detail', [
            'task' => $task,
            'lines' => $lines,
            'bins' => $bins,
            // K-I: kode pendek untuk tampilan; A-377: tanda tempat simpan penuh.
            'pendek' => BinCode::pendekBanyak($bins->concat($lines->pluck('suggestedBin'))->concat($lines->pluck('bin'))->filter()->unique('id')),
            'penuh' => $task->status === PutawayTaskStatus::Pending ? app(PutawayTargets::class)->penuh($lines->whereNull('scanned_at')) : [],
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
        ]);
    }

    /**
     * Bin tujuan dipindai (Katalog §put-away "Bin tujuan dipindai", A-201):
     * kode bin penyimpanan gudang ini → isian baris; kode lain ditolak.
     */
    public function pindaiBin(int $lineId, string $kode): void
    {
        if (trim($kode) === '' || ! array_key_exists($lineId, $this->isian)) {
            return;
        }

        // A-373: QR bin berisi tautan Isi Bin; kode pendek yang diketik juga dikenali.
        $bin = BinCode::cocokkan($kode, app(PutawaySuggester::class)->storageBins($this->task()->warehouse));

        if ($bin === null) {
            $this->addError('pindai.'.$lineId, __('Bin ":kode" bukan bin penyimpanan gudang ini.', ['kode' => BinCode::dariPindai($kode)]));

            return;
        }

        $this->resetErrorBag('pindai.'.$lineId);
        $this->isian[$lineId]['bin_id'] = (string) $bin->id;
    }

    /** Keputusan #6 (A-375): taruh satu baris sekarang; tugas selesai setelah baris terakhir. */
    public function taruhBaris(int $lineId, CompletePutaway $action): void
    {
        $task = $this->task();
        $this->authorize('complete', $task);

        $isi = ($this->isian[$lineId] ?? []) + ['buka_khusus' => $this->bukaKhusus];

        if ($this->jalankan(fn () => $action->placeLine($task, $lineId, $isi, auth()->user()))) {
            $this->peringatan = $action->warnings();
            $this->dispatch('pesan', teks: __('Baris ditaruh di bin.'));
        }
    }

    public function selesaikan(CompletePutaway $action): void
    {
        $task = $this->task();
        $this->authorize('complete', $task);

        $isian = array_map(fn (array $i) => $i + ['buka_khusus' => $this->bukaKhusus], $this->isian);

        if ($this->jalankan(fn () => $action->handle($task, $isian, auth()->user()))) {
            $this->peringatan = $action->warnings();
            $this->dispatch('pesan', teks: __('Put-away selesai.'));
        }
    }

    public function mintaBatal(): void
    {
        $this->authorize('cancel', $this->task());
        $this->dialog = 'batal';
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->resetValidation();
    }

    public function batalkan(CancelPutaway $action): void
    {
        $task = $this->task();
        $this->authorize('cancel', $task);

        $this->validate(['form.reason' => ['required', 'string']], attributes: ['form.reason' => __('Alasan')]);

        $ok = $this->jalankan(fn () => $action->handle(
            $task,
            $this->alasanId($this->form['reason'], ReasonContext::Cancel),
            $this->form['notes'] ?: null,
            auth()->user(),
        ));

        if ($ok) {
            $this->tutupDialog();
            $this->dispatch('pesan', teks: __('Tugas put-away dibatalkan; barang tetap di bin Penerimaan.'));
        }
    }

    private function task(): PutawayTask
    {
        return PutawayTask::query()->with('warehouse', 'receipt:id,number')->findOrFail($this->taskId);
    }
}
