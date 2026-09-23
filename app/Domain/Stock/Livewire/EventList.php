<?php

declare(strict_types=1);

namespace App\Domain\Stock\Livewire;

use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 13-stock §6 — outbox kejadian stok (AD-05).
 *
 * Layar baca saja. Kejadian tidak pernah diubah dari sini: bila sebuah kejadian
 * gagal terkirim, yang diperbaiki adalah penerimanya, bukan catatannya.
 */
class EventList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $typeFilter = '';

    /** '' semua · 'belum' belum terkirim · 'gagal' berulang kali gagal · 'terkirim' */
    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $dariTanggal = '';

    #[Url(except: '')]
    public string $sampaiTanggal = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockEvent::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.stock.event-list', [
            'kejadian' => $this->daftar(),
            'types' => StockEventType::options(),
            'ringkasan' => $this->ringkasan(),
        ]);
    }

    /**
     * Tiga angka di kepala layar: belum terkirim, gagal berulang, dan total.
     *
     * @return array<string, int>
     */
    private function ringkasan(): array
    {
        return [
            'belum' => StockEvent::query()->unpublished()->count(),
            'gagal' => StockEvent::query()->failing()->count(),
            'total' => StockEvent::query()->count(),
        ];
    }

    private function daftar(): LengthAwarePaginator
    {
        return StockEvent::query()
            ->with('project:id,code,name')
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('event_type', $this->typeFilter))
            ->when($this->statusFilter === 'belum', fn (Builder $q) => $q->unpublished())
            ->when($this->statusFilter === 'gagal', fn (Builder $q) => $q->failing())
            ->when($this->statusFilter === 'terkirim', fn (Builder $q) => $q->whereNotNull('published_at'))
            ->when($this->dariTanggal !== '', fn (Builder $q) => $q->whereDate('occurred_at', '>=', $this->dariTanggal))
            ->when($this->sampaiTanggal !== '', fn (Builder $q) => $q->whereDate('occurred_at', '<=', $this->sampaiTanggal))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25);
    }
}
