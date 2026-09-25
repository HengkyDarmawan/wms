<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\CancelPutaway;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Support\PutawaySuggester;
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

        return view('livewire.receipt.putaway-detail', [
            'task' => $task,
            'lines' => $task->lines()->with('item:id,code,name', 'fromBin:id,code', 'suggestedBin:id,code', 'bin:id,code', 'lot', 'serial', 'piece')->orderBy('id')->get(),
            'bins' => $saran->storageBins($task->warehouse),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
        ]);
    }

    /**
     * Bin tujuan dipindai (Katalog §put-away "Bin tujuan dipindai", A-201):
     * kode bin penyimpanan gudang ini → isian baris; kode lain ditolak.
     */
    public function pindaiBin(int $lineId, string $kode): void
    {
        $kode = mb_strtoupper(trim($kode));

        if ($kode === '' || ! array_key_exists($lineId, $this->isian)) {
            return;
        }

        $bin = app(PutawaySuggester::class)->storageBins($this->task()->warehouse)->first(fn ($b) => mb_strtoupper((string) $b->code) === $kode);

        if ($bin === null) {
            $this->addError('pindai.'.$lineId, __('Bin ":kode" bukan bin penyimpanan gudang ini.', ['kode' => $kode]));

            return;
        }

        $this->resetErrorBag('pindai.'.$lineId);
        $this->isian[$lineId]['bin_id'] = (string) $bin->id;
    }

    public function selesaikan(CompletePutaway $action): void
    {
        $task = $this->task();
        $this->authorize('complete', $task);

        if ($this->jalankan(fn () => $action->handle($task, $this->isian, auth()->user()))) {
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
