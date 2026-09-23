<?php

declare(strict_types=1);

namespace App\Domain\Stock\Livewire;

use App\Domain\Stock\Actions\LockStockPeriod;
use App\Domain\Stock\Livewire\Concerns\HandlesStockRules;
use App\Domain\Stock\Models\StockBalance;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 13-stock §6 — kunci periode stok (BR-STK-15).
 *
 * Riwayatnya dibaca dari log aktivitas, bukan dari tabel tersendiri: pemajuan
 * kunci adalah kejadian audit dan sudah tercatat di sana lengkap dengan
 * pelakunya, jadi menyimpannya dua kali hanya menambah sumber kebenaran kedua.
 */
class PeriodLock extends Component
{
    use HandlesStockRules;

    public string $tanggal = '';

    public string $catatan = '';

    public function mount(): void
    {
        $this->authorize('lockPeriod', StockBalance::class);
    }

    public function kunci(LockStockPeriod $action): void
    {
        $this->authorize('lockPeriod', StockBalance::class);

        $this->validate(
            ['tanggal' => ['required', 'date_format:Y-m-d']],
            attributes: ['tanggal' => __('Tanggal kunci')],
        );

        if (! $this->jalankan(fn () => $action->handle($this->tanggal, $this->catatan ?: null, auth()->user()))) {
            return;
        }

        $this->catatan = '';
        $this->dispatch('pesan', teks: __('Periode stok dikunci sampai :tgl.', ['tgl' => $this->tanggal]));
    }

    public function render(): View
    {
        $action = app(LockStockPeriod::class);

        return view('livewire.stock.period-lock', [
            'terkunciSampai' => $action->current(),
            'riwayat' => $this->riwayat(),
        ]);
    }

    /** @return Collection<int, Activity> */
    private function riwayat(): Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->where('log_name', 'stock')
            ->where('description', 'like', 'Periode stok dikunci%')
            ->latest('id')
            ->limit(20)
            ->get();
    }
}
