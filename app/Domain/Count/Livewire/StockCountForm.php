<?php

declare(strict_types=1);

namespace App\Domain\Count\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Count\Actions\CreateStockCount;
use App\Domain\Count\Enums\CountType;
use App\Domain\Count\Livewire\Concerns\HandlesCountRules;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Models\Item;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 21-opname-penyesuaian §6.2 — merencanakan sesi: jenis, cakupan
 * (gudang, zona, bin, item), pembekuan, tanggal, tim penghitung. Bin
 * berpenanda hitung (A-67) ditampilkan paling atas dan ditandai.
 */
class StockCountForm extends Component
{
    use HandlesCountRules;

    /** @var array<string, mixed> */
    public array $form = [
        'count_type' => 'monthly',
        'warehouse_ids' => [],
        'zone_ids' => [],
        'bin_ids' => [],
        'item_ids' => [],
        'freeze_bins' => true,
        'planned_start' => '',
        'team_user_ids' => [],
        'notes' => '',
    ];

    public function mount(): void
    {
        $this->authorize('create', StockCount::class);

        $this->form['planned_start'] = now()->toDateString();

        if (auth()->user()->hasRoleCode('internal_auditor')) {
            $this->form['count_type'] = CountType::SpotCheck->value;
            $this->form['freeze_bins'] = false;
        }
    }

    public function updatedFormCountType(string $nilai): void
    {
        if ($nilai === CountType::SpotCheck->value) {
            $this->form['freeze_bins'] = false;
        }
    }

    public function simpan(CreateStockCount $action): void
    {
        $this->authorize('create', StockCount::class);

        $this->validate([
            'form.count_type' => ['required', 'string'],
            'form.warehouse_ids' => ['required', 'array', 'min:1'],
            'form.team_user_ids' => ['required', 'array', 'min:1'],
        ], attributes: [
            'form.count_type' => __('Jenis'),
            'form.warehouse_ids' => __('Gudang'),
            'form.team_user_ids' => __('Tim penghitung'),
        ]);

        $sesi = null;

        $ok = $this->jalankan(function () use ($action, &$sesi) {
            $sesi = $action->handle($this->form, auth()->user());
        });

        if ($ok && $sesi !== null) {
            $this->redirectRoute('counts.show', $sesi, navigate: true);
        }
    }

    public function render(): View
    {
        $gudangIds = array_map('intval', (array) $this->form['warehouse_ids']);

        return view('livewire.count.count-form', [
            'types' => CountType::options(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'zones' => $gudangIds === [] ? collect() : Zone::query()->whereIn('warehouse_id', $gudangIds)->orderBy('code')->get(['id', 'warehouse_id', 'code', 'name']),
            'bins' => $gudangIds === [] ? collect() : Bin::query()->whereIn('warehouse_id', $gudangIds)
                ->where('is_virtual', false)->where('bin_status', '!=', 'inactive')
                ->orderByDesc('count_flag')->orderBy('code')->get(['id', 'code', 'bin_type', 'count_flag']),
            'items' => Item::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'counters' => $this->penghitung($gudangIds),
        ]);
    }

    /**
     * @param  array<int, int>  $gudangIds
     * @return Collection<int, User>
     */
    private function penghitung(array $gudangIds): Collection
    {
        return User::query()->active()->internal()->orderBy('name')->get()
            ->filter(fn (User $u) => $u->hasPermission('count.record')
                && ($gudangIds === [] || collect($gudangIds)->contains(fn (int $g) => $u->canAccessWarehouse($g))))
            ->values();
    }
}
