<?php

declare(strict_types=1);

namespace App\Domain\Asset\Livewire;

use App\Domain\Asset\Actions\MaintainAsset;
use App\Domain\Asset\Enums\MaintenanceStatus;
use App\Domain\Asset\Livewire\Concerns\HandlesAssetRules;
use App\Domain\Asset\Models\MaintenanceSchedule;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Kartu **Servis** di detail aset (33-pendukung-f2 §6.1, A-423): jadwal,
 * jadwalkan/ubah, mulai servis (ke Karantina), selesai servis (ke bin pilihan).
 * Komponen tersendiri supaya AssetDetail tidak membengkak.
 */
class AssetMaintenance extends Component
{
    use HandlesAssetRules;

    #[Locked]
    public int $serialId;

    /** Id jadwal yang sedang diubah; 0 = form jadwal baru tertutup, -1 = jadwal baru. */
    public int $ubah = 0;

    /** @var array{scheduled_at: string, interval_days: string, interval_meter: string, notes: string} */
    public array $form = ['scheduled_at' => '', 'interval_days' => '', 'interval_meter' => '', 'notes' => ''];

    public string $binTujuan = '';

    public function mount(int $serialId): void
    {
        $this->serialId = $serialId;
    }

    public function render(): View
    {
        $jadwal = MaintenanceSchedule::query()->with('originBin:id,code', 'creator:id,name')
            ->where('serial_id', $this->serialId)->orderByRaw("FIELD(status, 'in_progress', 'planned', 'done')")->orderByDesc('scheduled_at')->orderByDesc('id')->get();
        $sedang = $jadwal->first(fn (MaintenanceSchedule $j) => $j->status === MaintenanceStatus::InProgress);

        return view('livewire.asset.asset-maintenance', [
            'jadwal' => $jadwal,
            'bisaAtur' => auth()->user()->hasPermission('asset.maintain'),
            'aset' => Serial::query()->find($this->serialId, ['id', 'meter_unit', 'meter_total']),
            'binPilihan' => $sedang ? $this->binPilihan($sedang) : [],
        ]);
    }

    public function bukaForm(int $id = -1): void
    {
        $this->ubah = $id;
        $j = $id > 0 ? $this->jadwal($id) : null;
        $this->form = [
            'scheduled_at' => $j?->scheduled_at?->toDateString() ?? now()->toDateString(),
            'interval_days' => $j?->interval_days !== null ? (string) $j->interval_days : '',
            'interval_meter' => $j?->interval_meter !== null ? rtrim(rtrim((string) $j->interval_meter, '0'), '.') : '',
            'notes' => (string) ($j?->notes ?? ''),
        ];
        $this->resetValidation();
    }

    public function tutupForm(): void
    {
        $this->ubah = 0;
        $this->resetValidation();
    }

    public function simpan(MaintainAsset $action): void
    {
        $serial = Serial::query()->findOrFail($this->serialId);
        $j = $this->ubah > 0 ? $this->jadwal($this->ubah) : null;

        if ($this->jalankan(fn () => $action->plan($serial, $this->form, auth()->user(), $j))) {
            $this->tutupForm();
            $this->dispatch('pesan', teks: __('Jadwal servis disimpan.'));
        }
    }

    public function mulai(int $id, MaintainAsset $action): void
    {
        if ($this->jalankan(fn () => $action->start($this->jadwal($id), auth()->user()))) {
            $this->dispatch('pesan', teks: __('Servis dimulai; aset dipindah ke bin Karantina.'));
            $this->dispatch('aset-berubah');
        }
    }

    public function selesai(int $id, MaintainAsset $action): void
    {
        $this->validate(['binTujuan' => ['required', 'integer']], attributes: ['binTujuan' => __('Bin tujuan')]);

        if ($this->jalankan(fn () => $action->finish($this->jadwal($id), (int) $this->binTujuan, auth()->user()), 'form')) {
            $this->binTujuan = '';
            $this->dispatch('pesan', teks: __('Servis selesai; aset kembali ke bin penyimpanan.'));
            $this->dispatch('aset-berubah');
        }
    }

    private function jadwal(int $id): MaintenanceSchedule
    {
        return MaintenanceSchedule::query()->where('serial_id', $this->serialId)->findOrFail($id);
    }

    /** @return array<int, array{value: int, text: string}> bin penyimpanan aktif di gudang tempat aset diservis */
    private function binPilihan(MaintenanceSchedule $sedang): array
    {
        $gudang = StockBalance::query()->withoutGlobalScopes()->with('bin:id,warehouse_id')
            ->where('serial_id', $this->serialId)->where('qty_base', '>', 0)->first()?->bin?->warehouse_id;

        if ($gudang === null) {
            return [];
        }

        if ($this->binTujuan === '' && $sedang->origin_bin_id !== null) {
            $this->binTujuan = (string) $sedang->origin_bin_id;
        }

        return Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudang)
            ->where('bin_type', BinType::Storage->value)->where('bin_status', BinStatus::Active->value)->whereNull('occupied_by_bin_id')
            ->orderBy('code')->get(['id', 'code'])
            ->map(fn (Bin $b) => ['value' => (int) $b->id, 'text' => $b->code.((int) $b->id === (int) $sedang->origin_bin_id ? ' ('.__('bin asal').')' : '')])
            ->all();
    }
}
