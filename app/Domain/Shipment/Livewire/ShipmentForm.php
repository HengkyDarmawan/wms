<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Carrier;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Master\Models\Vendor;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Enums\DestinationType;
use App\Domain\Shipment\Enums\ShipmentMethod;
use App\Domain\Shipment\Livewire\Concerns\HandlesShipmentRules;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 15-picking-shipment §6 — menyusun surat jalan.
 *
 * Yang ditawarkan hanya PCK selesai di gudang yang dipilih (BR-SJ-09). Begitu
 * gudangnya berganti, pilihan sebelumnya dibuang: menggabungkan PCK dari dua
 * gudang bukan salah ketik yang perlu diperbaiki, melainkan hal yang tidak
 * boleh terjadi sejak awal.
 */
class ShipmentForm extends Component
{
    use HandlesShipmentRules;

    /** @var array<string, mixed> */
    public array $form = [
        'warehouse_id' => '',
        'destination_type' => '',
        'destination_project_id' => '',
        'destination_warehouse_id' => '',
        'destination_vendor_id' => '',
        'shipment_method' => '',
        'vehicle_id' => '',
        'driver_id' => '',
        'carrier_id' => '',
        'tracking_no' => '',
        'carried_by_name' => '',
        'notes' => '',
    ];

    /** @var array<int, int> */
    public array $pickTaskIds = [];

    public function mount(): void
    {
        $this->authorize('create', Shipment::class);

        $gudang = Warehouse::query()->active()->orderBy('code')->first();

        if ($gudang !== null) {
            $this->form['warehouse_id'] = (string) $gudang->id;
        }
    }

    public function updatedFormWarehouseId(): void
    {
        // Pilihan PCK tidak lagi berlaku untuk gudang lain.
        $this->pickTaskIds = [];
    }

    public function simpan(CreateShipment $action): void
    {
        $this->authorize('create', Shipment::class);

        $this->validate(
            ['pickTaskIds' => ['array', 'min:1']],
            ['pickTaskIds.min' => __('Pilih minimal satu tugas picking.')],
        );

        $sj = null;

        $berhasil = $this->jalankan(function () use ($action, &$sj) {
            $sj = $action->handle($this->pickTaskIds, $this->form, auth()->user());
        });

        if (! $berhasil || $sj === null) {
            return;
        }

        $this->redirectRoute('shipments.show', $sj, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.shipment.shipment-form', [
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'tasks' => $this->pckTersedia(),
            'destinations' => DestinationType::options(),
            'methods' => ShipmentMethod::options(),
            'projects' => Project::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'gudangTujuan' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::query()->where('is_active', true)->orderBy('plate_no')->get(['id', 'plate_no']),
            'carriers' => Carrier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'drivers' => $this->pilihanDriver(),
        ]);
    }

    /** @return Collection<int, PickTask> */
    private function pckTersedia(): Collection
    {
        if ($this->form['warehouse_id'] === '') {
            return new Collection;
        }

        return PickTask::query()
            ->with('lines.item:id,code,name')
            ->readyToShip()
            ->where('warehouse_id', (int) $this->form['warehouse_id'])
            ->orderBy('id')
            ->get()
            // PCK yang seluruh barisnya sudah termuat SJ lain tidak ditawarkan.
            ->filter(fn (PickTask $t) => $t->lines->sum(fn ($l) => $l->unshippedQty()) > 0)
            ->values();
    }

    /** @return Collection<int, User> */
    private function pilihanDriver(): Collection
    {
        return User::query()
            ->whereNull('client_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
