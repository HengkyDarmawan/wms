<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Master\Actions\SaveReference;
use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Carrier;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Master\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 11-master §6 — empat master kecil dalam satu layar bertab: alasan baku
 * (BR-GEN-02), kategori penyimpanan (A-37), kendaraan, dan ekspedisi (A-57).
 */
class ReferenceList extends Component
{
    use HandlesMasterRules;

    /** @var array<int, string> */
    private const TABS = ['alasan', 'penyimpanan', 'kendaraan', 'ekspedisi'];

    #[Url(except: 'alasan')]
    public string $tab = 'alasan';

    #[Url(except: '')]
    public string $contextFilter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'context' => 'reject',
        'code' => '',
        'label' => '',
        'name' => '',
        'capacity_mode' => 'warn',
        'plate_no' => '',
        'type' => '',
        'default_driver_id' => '',
        'phone' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', ReasonCode::class);
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'alasan';
        $this->showForm = false;
        $this->editingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function buat(): void
    {
        $this->authorize('create', $this->modelKelas());

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = null;
        $this->form = [
            'context' => $this->contextFilter !== '' ? $this->contextFilter : ReasonContext::Reject->value,
            'code' => '',
            'label' => '',
            'name' => '',
            'capacity_mode' => CapacityMode::Warn->value,
            'plate_no' => '',
            'type' => '',
            'default_driver_id' => '',
            'phone' => '',
        ];
        $this->showForm = true;
    }

    public function ubah(int $id): void
    {
        $model = $this->cari($id);

        $this->authorize('update', $model);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = $id;

        $this->form = match ($this->tab) {
            'alasan' => [...$this->form, 'context' => $model->context->value, 'code' => (string) $model->code, 'label' => (string) $model->label],
            'penyimpanan' => [...$this->form, 'code' => (string) $model->code, 'name' => (string) $model->name, 'capacity_mode' => $model->capacity_mode->value],
            'kendaraan' => [...$this->form, 'plate_no' => (string) $model->plate_no, 'type' => (string) $model->type, 'default_driver_id' => (string) $model->default_driver_id],
            default => [...$this->form, 'name' => (string) $model->name, 'phone' => (string) $model->phone],
        };

        $this->showForm = true;
    }

    public function simpan(SaveReference $action): void
    {
        $model = $this->editingId === null ? null : $this->cari($this->editingId);

        $this->authorize($model === null ? 'create' : 'update', $model ?? $this->modelKelas());

        $aturan = match ($this->tab) {
            'alasan' => [
                'form.context' => ['required', Rule::enum(ReasonContext::class)],
                'form.code' => ['required', 'string', 'max:30'],
                'form.label' => ['required', 'string', 'max:100'],
            ],
            'penyimpanan' => [
                'form.code' => ['required', 'string', 'max:30'],
                'form.name' => ['required', 'string', 'max:100'],
                'form.capacity_mode' => ['required', Rule::enum(CapacityMode::class)],
            ],
            'kendaraan' => [
                'form.plate_no' => ['required', 'string', 'max:15'],
                'form.type' => ['nullable', 'string', 'max:40'],
            ],
            default => [
                'form.name' => ['required', 'string', 'max:100'],
                'form.phone' => ['nullable', 'string', 'max:20'],
            ],
        };

        $this->validate($aturan, attributes: [
            'form.context' => __('Konteks'),
            'form.code' => __('Kode'),
            'form.label' => __('Label'),
            'form.name' => __('Nama'),
            'form.plate_no' => __('Nomor polisi'),
            'form.capacity_mode' => __('Mode kapasitas'),
        ]);

        $berhasil = $this->jalankan(fn () => match ($this->tab) {
            'alasan' => $action->saveReasonCode($model, $this->form, auth()->user()),
            'penyimpanan' => $action->saveStorageCategory($model, $this->form, auth()->user()),
            'kendaraan' => $action->saveVehicle($model, $this->form, auth()->user()),
            default => $action->saveCarrier($model, $this->form, auth()->user()),
        });

        if (! $berhasil) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('Data referensi disimpan.'));
    }

    public function batalForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function ubahAktif(int $id, bool $aktif, SaveReference $action): void
    {
        $model = $this->cari($id);

        $this->authorize($aktif ? 'update' : 'deactivate', $model);

        $action->toggle($model, $aktif, auth()->user());

        $this->dispatch('pesan', teks: $aktif ? __('Data diaktifkan.') : __('Data dinonaktifkan.'));
    }

    public function render(): View
    {
        return view('livewire.master.reference-list', [
            'baris' => $this->baris(),
            'konteks' => ReasonContext::options(),
            'capacityModes' => CapacityMode::options(),
            'drivers' => $this->tab === 'kendaraan'
                ? User::query()->internal()->active()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Model> */
    private function baris(): \Illuminate\Support\Collection
    {
        return match ($this->tab) {
            'alasan' => ReasonCode::query()
                ->when($this->contextFilter !== '', fn ($q) => $q->where('context', $this->contextFilter))
                ->orderBy('context')->orderBy('code')->get(),
            'penyimpanan' => StorageCategory::query()->orderBy('name')->get(),
            'kendaraan' => Vehicle::query()->with('defaultDriver:id,name')->orderBy('plate_no')->get(),
            default => Carrier::query()->orderBy('name')->get(),
        };
    }

    private function cari(int $id): Model
    {
        return match ($this->tab) {
            'alasan' => ReasonCode::findOrFail($id),
            'penyimpanan' => StorageCategory::findOrFail($id),
            'kendaraan' => Vehicle::findOrFail($id),
            default => Carrier::findOrFail($id),
        };
    }

    /** @return class-string<Model> */
    private function modelKelas(): string
    {
        return match ($this->tab) {
            'alasan' => ReasonCode::class,
            'penyimpanan' => StorageCategory::class,
            'kendaraan' => Vehicle::class,
            default => Carrier::class,
        };
    }
}
