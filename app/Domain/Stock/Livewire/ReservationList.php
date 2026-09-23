<?php

declare(strict_types=1);

namespace App\Domain\Stock\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\ReservationStatus;
use App\Domain\Stock\Livewire\Concerns\HandlesStockRules;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 13-stock §6 — reservasi aktif.
 *
 * Reservasi menggantung (BR-STK-16) ditandai di daftar, bukan dilepas otomatis:
 * janji yang dibuat sebuah dokumen hanya boleh dibatalkan orang, dan selalu
 * dengan alasan (BR-STK-05, BR-GEN-11).
 */
class ReservationList extends Component
{
    use HandlesStockRules;
    use WithPagination;

    /** Ambang hari sebelum reservasi lunak dianggap menggantung. */
    public const AMBANG = 'reservation_alert_days';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $warehouseFilter = '';

    #[Url(except: '')]
    public string $levelFilter = '';

    #[Url(except: 'active')]
    public string $statusFilter = 'active';

    #[Url(except: false)]
    public bool $hanyaMenggantung = false;

    #[Locked]
    public ?int $actingId = null;

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockReservation::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page' && ! str_starts_with($property, 'reason')) {
            $this->resetPage();
        }
    }

    public function mintaLepas(int $id): void
    {
        $reservasi = StockReservation::findOrFail($id);

        $this->authorize('release', $reservasi);

        $this->ruleError = '';
        $this->actingId = $reservasi->id;
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function batalLepas(): void
    {
        $this->actingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    /** BR-STK-05: pelepasan manual, dengan alasan wajib dan keterangan opsional. */
    public function lepas(ManageReservation $action): void
    {
        $reservasi = StockReservation::findOrFail($this->actingId);

        $this->authorize('release', $reservasi);

        $this->validate(
            ['reasonCode' => ['required', 'string']],
            attributes: ['reasonCode' => __('Alasan')],
        );

        $alasan = $this->reasonNotes !== ''
            ? $this->reasonCode.' — '.$this->reasonNotes
            : $this->reasonCode;

        if (! $this->jalankan(fn () => $action->release($reservasi, $alasan, auth()->user()))) {
            return;
        }

        $this->actingId = null;
        $this->dispatch('pesan', teks: __('Reservasi dilepas.'));
    }

    public function render(): View
    {
        return view('livewire.stock.reservation-list', [
            'reservasi' => $this->daftar(),
            'ambang' => $this->ambangHari(),
            'warehouses' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name']),
            'levels' => ReservationLevel::options(),
            'statuses' => ReservationStatus::options(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
        ]);
    }

    /** A-59 dan BR-STK-16: ambang bawaan 7 hari, bisa disetel per company. */
    public function ambangHari(): int
    {
        $nilai = (int) CompanySetting::get(self::AMBANG, 7);

        return $nilai > 0 ? $nilai : 7;
    }

    private function daftar(): LengthAwarePaginator
    {
        $ambang = $this->ambangHari();

        return StockReservation::query()
            ->with('item:id,code,name', 'warehouse:id,code,name', 'bin:id,code')
            // BR-ACC-05: hanya reservasi di gudang yang boleh diakses pengguna.
            ->whereIn('warehouse_id', Warehouse::query()->pluck('id'))
            ->when($this->search !== '', fn (Builder $q) => $q
                ->whereHas('item', fn (Builder $i) => $i
                    ->where('code', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%')))
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q
                ->where('warehouse_id', (int) $this->warehouseFilter))
            ->when($this->levelFilter !== '', fn (Builder $q) => $q->where('level', $this->levelFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->hanyaMenggantung, fn (Builder $q) => $q->stale($ambang))
            ->orderBy('created_at')
            ->paginate(25);
    }
}
