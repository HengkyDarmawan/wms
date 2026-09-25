<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Livewire\Concerns\HandlesShipmentRules;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 15-picking-shipment §6 — detail dan pencatatan tugas picking.
 *
 * Jumlah diambil dicatat per baris sebelum PCK diselesaikan, karena itulah
 * urutan kerjanya di lapangan: petugas berjalan dari bin ke bin, bukan
 * menyelesaikan semuanya sekaligus di akhir.
 */
class PickDetail extends Component
{
    use HandlesShipmentRules;

    #[Locked]
    public int $taskId;

    /**
     * Isian per baris: jumlah diambil, bin pengganti, dan alasannya.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $isian = [];

    /** Kolom pindai item/bin (A-203). */
    public string $kodePindai = '';

    public ?int $binPindai = null;

    public ?int $sorot = null;

    /** '' atau 'batal' */
    public string $dialog = '';

    public string $reasonCode = '';

    public function mount(PickTask $pickTask): void
    {
        $this->authorize('view', $pickTask);

        $this->taskId = (int) $pickTask->id;
        $this->muatIsian($pickTask);
    }

    public function render(): View
    {
        $task = $this->task();

        return view('livewire.shipment.pick-detail', [
            'task' => $task,
            'lines' => $task->lines()
                ->with('item:id,code,name', 'bin:id,code', 'suggestedBin:id,code', 'shortReason:id,code,name')
                ->orderBy('id')
                ->get(),
            'bins' => Bin::query()
                ->where('warehouse_id', $task->warehouse_id)
                ->active()
                ->orderBy('code')
                ->get(['id', 'code']),
            'alasan' => $this->pilihanAlasan(
                $this->dialog === 'batal' ? ReasonContext::Cancel : ReasonContext::ShortPick,
            ),
        ]);
    }

    public function mulai(ProcessPickTask $action): void
    {
        $task = $this->task();

        $this->authorize('start', $task);

        if ($this->jalankan(fn () => $action->start($task, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Picking dimulai.'));
        }
    }

    /** Mencatat satu baris: jumlah diambil, bin yang dipakai, dan alasannya. */
    public function catat(int $lineId, ProcessPickTask $action): void
    {
        $task = $this->task();

        $this->authorize('complete', $task);

        $baris = $this->line($lineId);
        $isi = $this->isian[$lineId] ?? [];

        $berhasil = $this->jalankan(fn () => $action->recordLine(
            $baris,
            (float) ($isi['qty_picked'] ?? 0),
            ($isi['bin_id'] ?? '') === '' ? null : (int) $isi['bin_id'],
            ($isi['override_reason'] ?? '') ?: null,
            $this->alasanId((string) ($isi['short_reason'] ?? '')),
            auth()->user(),
        ));

        if ($berhasil) {
            $this->dispatch('pesan', teks: __('Baris dicatat.'));
        }
    }

    /**
     * Alur pindai (A-203): pindai kode bin → bin aktif; pindai barcode/kode
     * item (atau nomor lot/serial/potongan untuk item berlacak) → baris itu
     * yang belum tercatat (utamakan yang di bin aktif) dicatat dengan jumlah
     * di isiannya. Kurang ambil tetap lewat isian + Catat.
     */
    public function pindai(ProcessPickTask $action): void
    {
        $task = $this->task();
        $this->authorize('complete', $task);

        $kode = mb_strtoupper(trim($this->kodePindai));
        $this->kodePindai = '';
        $this->resetErrorBag('kodePindai');

        if ($kode === '') {
            return;
        }

        $bin = Bin::query()->where('warehouse_id', $task->warehouse_id)->active()->get(['id', 'code'])
            ->first(fn (Bin $b) => mb_strtoupper((string) $b->code) === $kode);

        if ($bin !== null) {
            $this->binPindai = (int) $bin->id;
            $this->dispatch('pesan', teks: __('Bin :kode dipilih; pindai item.', ['kode' => $bin->code]));

            return;
        }

        $belum = $task->lines()->with('item:id,code,barcode', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no')
            ->whereNull('scanned_at')->orderBy('id')->get();
        $cocokItem = fn (PickTaskLine $l) => mb_strtoupper((string) $l->item?->code) === $kode || mb_strtoupper((string) $l->item?->barcode) === $kode;
        $berlacak = fn (PickTaskLine $l) => $l->lot_id !== null || $l->serial_id !== null || $l->piece_id !== null;

        // Baris berlacak harus dipindai dengan nomor lot/serial/potongan yang
        // dialokasikan, supaya yang tercatat memang yang diambil (A-203).
        $baris = $belum->filter(fn (PickTaskLine $l) => $berlacak($l)
                ? in_array($kode, array_map(fn ($v) => mb_strtoupper((string) $v), array_filter([$l->lot?->lot_no, $l->serial?->serial_no, $l->piece?->piece_no])), true)
                : $cocokItem($l))
            ->sortByDesc(fn (PickTaskLine $l) => (int) ($this->isian[$l->id]['bin_id'] ?? 0) === $this->binPindai)
            ->first();

        if ($baris === null && $belum->contains(fn (PickTaskLine $l) => $berlacak($l) && $cocokItem($l))) {
            $this->addError('kodePindai', __('Item ini berlacak; pindai nomor lot/serial/potongan yang tertera di baris.'));

            return;
        }

        if ($baris === null) {
            $this->addError('kodePindai', __('":kode" bukan bin gudang ini dan tidak cocok dengan baris yang belum dicatat.', ['kode' => $kode]));

            return;
        }

        if ($this->binPindai !== null) {
            $this->isian[$baris->id]['bin_id'] = (string) $this->binPindai;
        }

        $this->sorot = (int) $baris->id;
        $this->catat((int) $baris->id, $action);
    }

    public function selesaikan(ProcessPickTask $action): void
    {
        $task = $this->task();

        $this->authorize('complete', $task);

        if ($this->jalankan(fn () => $action->complete($task, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Picking selesai; barang berada di Loading Area.'));
        }
    }

    public function mintaBatal(): void
    {
        $this->authorize('cancel', $this->task());

        $this->dialog = 'batal';
        $this->reasonCode = '';
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function tutupDialog(): void
    {
        $this->dialog = '';
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function batalkan(ProcessPickTask $action): void
    {
        $task = $this->task();

        $this->authorize('cancel', $task);

        $this->validate(
            ['reasonCode' => ['required', 'string']],
            attributes: ['reasonCode' => __('Alasan')],
        );

        if (! $this->jalankan(fn () => $action->cancel($task, $this->alasanId($this->reasonCode), auth()->user()))) {
            return;
        }

        $this->tutupDialog();
        $this->dispatch('pesan', teks: __('Tugas picking dibatalkan.'));
    }

    private function muatIsian(PickTask $task): void
    {
        $this->isian = $task->lines()->orderBy('id')->get()
            ->mapWithKeys(fn (PickTaskLine $l) => [$l->id => [
                // Bawaannya jumlah alokasi: yang paling sering terjadi adalah
                // fisik cocok, dan mengetik ulang angka yang sama hanya
                // memperlambat petugas.
                'qty_picked' => (string) (float) ($l->qty_picked > 0 ? $l->qty_picked : $l->qty_allocated),
                'bin_id' => (string) $l->bin_id,
                'override_reason' => (string) ($l->override_reason ?? ''),
                'short_reason' => '',
            ]])->all();
    }

    private function alasanId(string $code): ?int
    {
        if ($code === '') {
            return null;
        }

        $id = ReasonCode::query()->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function task(): PickTask
    {
        return PickTask::query()
            ->with('warehouse:id,code,name', 'assignee:id,name')
            ->findOrFail($this->taskId);
    }

    private function line(int $id): PickTaskLine
    {
        return PickTaskLine::query()
            ->with('pickTask', 'item')
            ->where('pick_task_id', $this->taskId)
            ->findOrFail($id);
    }
}
