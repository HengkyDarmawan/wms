<?php

declare(strict_types=1);

namespace App\Domain\Count\Livewire;

use App\Domain\Count\Actions\RecordCount;
use App\Domain\Count\Livewire\Concerns\HandlesCountRules;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 21-opname-penyesuaian §6.5 — hitung satu bin (ramah HP, hitung
 * buta). Penghitung melihat daftar barang yang tercatat di bin tanpa angka
 * sistem, mengisi jumlah fisik (serial/potongan: ada/tidak ada), boleh
 * mencatat temuan barang di luar catatan, lalu menandai bin selesai.
 *
 * Tidak ada angka sistem di properti publik komponen ini, jadi tidak ikut
 * terkirim ke peramban (A-103).
 */
class CountEntry extends Component
{
    use HandlesCountRules;

    #[Locked]
    public int $assignmentId;

    /** @var array<int|string, string> count_line_id => jumlah */
    public array $qty = [];

    /** @var array<string, string> */
    public array $temuan = ['item_id' => '', 'lot_no' => '', 'qty' => ''];

    public bool $tambahTemuan = false;

    public function mount(CountAssignment $countAssignment): void
    {
        $this->authorize('view', $countAssignment);

        $this->assignmentId = (int) $countAssignment->id;
        $this->muatIsian();
    }

    public function render(): View
    {
        $tugas = $this->tugas();

        return view('livewire.count.count-entry', [
            'tugas' => $tugas,
            'lines' => $this->baris($tugas),
            'bolehIsi' => auth()->user()->can('record', $tugas),
            'items' => $this->tambahTemuan
                ? Item::query()->active()->whereIn('tracking_mode', [TrackingMode::None->value, TrackingMode::Lot->value])->orderBy('code')->get(['id', 'code', 'name', 'tracking_mode'])
                : collect(),
        ]);
    }

    public function simpan(RecordCount $action): void
    {
        $tugas = $this->tugas();
        $this->authorize('record', $tugas);

        if ($this->jalankan(fn () => $action->save($tugas, $this->qty, auth()->user()), 'qty')) {
            $this->dispatch('pesan', teks: __('Hitungan disimpan.'));
        }
    }

    public function selesai(RecordCount $action): void
    {
        $tugas = $this->tugas();
        $this->authorize('record', $tugas);

        $ok = $this->jalankan(function () use ($action, $tugas) {
            $action->save($tugas, $this->qty, auth()->user());
            $action->finish($tugas->refresh(), auth()->user());
        }, 'qty');

        if ($ok) {
            $this->redirectRoute('count-tasks.index', navigate: true);
        }
    }

    public function catatTemuan(RecordCount $action): void
    {
        $tugas = $this->tugas();
        $this->authorize('record', $tugas);

        $this->validate([
            'temuan.item_id' => ['required'],
            'temuan.qty' => ['required', 'numeric', 'gt:0'],
        ], attributes: ['temuan.item_id' => __('Item'), 'temuan.qty' => __('Jumlah')]);

        $ok = $this->jalankan(fn () => $action->addLine(
            $tugas,
            (int) $this->temuan['item_id'],
            $this->temuan['lot_no'] ?: null,
            $this->temuan['qty'],
            auth()->user(),
        ), 'temuan');

        if ($ok) {
            $this->temuan = ['item_id' => '', 'lot_no' => '', 'qty' => ''];
            $this->tambahTemuan = false;
            $this->muatIsian();
            $this->dispatch('pesan', teks: __('Temuan dicatat.'));
        }
    }

    private function tugas(): CountAssignment
    {
        return CountAssignment::query()->with('bin:id,code,bin_type', 'stockCount:id,number,status,count_type')
            ->findOrFail($this->assignmentId);
    }

    /** @return Collection<int, CountLine> */
    private function baris(CountAssignment $tugas): Collection
    {
        return $tugas->linesQuery()->with('item:id,code,name,tracking_mode', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no,length')
            ->orderBy('item_id')->orderBy('id')->get();
    }

    /** Isian milik penghitung sendiri (putaran ini); angka sistem tidak pernah dimuat. */
    private function muatIsian(): void
    {
        $tugas = $this->tugas();
        $kolom = $tugas->round === 2 ? 'counted_qty_r2' : 'counted_qty_r1';

        foreach ($tugas->linesQuery()->get(['id', $kolom, 'serial_id', 'piece_id']) as $l) {
            $nilai = $l->{$kolom};

            if ($l->serial_id !== null || $l->piece_id !== null) {
                $this->qty[$l->id] = $nilai === null ? '' : ((float) $nilai > 0 ? '1' : '0');
            } else {
                $this->qty[$l->id] = $nilai === null ? '' : rtrim(rtrim(number_format((float) $nilai, 4, '.', ''), '0'), '.');
            }
        }
    }
}
