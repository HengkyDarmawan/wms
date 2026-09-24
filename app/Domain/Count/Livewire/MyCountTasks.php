<?php

declare(strict_types=1);

namespace App\Domain\Count\Livewire;

use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\CountAssignment;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 21-opname-penyesuaian §6.4 — "Hitungan saya" (ramah HP): penugasan
 * hitung milik pengguna di sesi yang sedang berjalan, bin berpenanda hitung
 * lebih dulu. Tidak menampilkan angka apa pun (hitung buta).
 */
class MyCountTasks extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', CountAssignment::class);
    }

    public function render(): View
    {
        $tugas = CountAssignment::query()
            ->with('bin:id,code,count_flag,bin_type', 'stockCount:id,number,count_type,status')
            ->where('counter_user_id', auth()->id())
            ->whereHas('stockCount', fn ($q) => $q->withoutGlobalScopes()
                ->whereIn('status', [StockCountStatus::InProgress->value, StockCountStatus::Recount->value]))
            ->orderBy('status')
            ->orderByDesc('round')
            ->orderBy('id')
            ->get();

        return view('livewire.count.my-tasks', [
            'terbuka' => $tugas->filter(fn (CountAssignment $a) => ! $a->isDone())->values(),
            'selesai' => $tugas->filter(fn (CountAssignment $a) => $a->isDone())->values(),
        ]);
    }
}
