<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Carrier;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Master\Support\EnumInput;
use App\Domain\Master\Support\MasterCode;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission: `reference.manage`. Satu aksi untuk empat master kecil yang
 * dikelola di layar Referensi: alasan baku (BR-GEN-02), kategori penyimpanan
 * (A-37), kendaraan, dan ekspedisi (A-57).
 */
class SaveReference
{
    /** @param  array<string, mixed>  $attributes */
    public function saveReasonCode(?ReasonCode $reason, array $attributes, ?User $actor = null): ReasonCode
    {
        $baru = $reason === null || ! $reason->exists;

        $label = trim((string) ($attributes['label'] ?? ''));

        if ($label === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Label alasan wajib diisi.');
        }

        $konteks = EnumInput::required(ReasonContext::class, $attributes['context'] ?? null, $reason?->context, 'context');

        $kode = MasterCode::resolve($reason, (string) ($attributes['code'] ?? ''), 'alasan');

        $bentrok = ReasonCode::query()
            ->where('context', $konteks->value)
            ->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($reason->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Kode alasan "'.$kode.'" sudah ada di konteks ini.');
        }

        $data = ['context' => $konteks, 'code' => $kode, 'label' => $label];

        $reason = $baru
            ? ReasonCode::create($data + ['is_active' => true])
            : tap($reason, fn ($m) => $m->fill($data)->save());

        $this->catat($reason, $baru, 'Alasan', $actor);

        return $reason->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    public function saveStorageCategory(?StorageCategory $category, array $attributes, ?User $actor = null): StorageCategory
    {
        $baru = $category === null || ! $category->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nama kategori penyimpanan wajib diisi.');
        }

        $kode = MasterCode::resolve($category, (string) ($attributes['code'] ?? ''), 'kategori penyimpanan');

        $bentrok = StorageCategory::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($category->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Kode kategori penyimpanan "'.$kode.'" sudah dipakai.');
        }

        $mode = EnumInput::required(CapacityMode::class, $attributes['capacity_mode'] ?? null, $category?->capacity_mode ?? CapacityMode::Warn, 'capacity_mode');

        $data = ['code' => $kode, 'name' => $nama, 'capacity_mode' => $mode];

        $category = $baru
            ? StorageCategory::create($data + ['is_active' => true])
            : tap($category, fn ($m) => $m->fill($data)->save());

        $this->catat($category, $baru, 'Kategori penyimpanan', $actor);

        return $category->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    public function saveVehicle(?Vehicle $vehicle, array $attributes, ?User $actor = null): Vehicle
    {
        $baru = $vehicle === null || ! $vehicle->exists;

        $plat = MasterCode::normalize((string) ($attributes['plate_no'] ?? ''));

        if ($plat === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nomor polisi wajib diisi.');
        }

        $bentrok = Vehicle::query()->where('plate_no', $plat)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($vehicle->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Nomor polisi "'.$plat.'" sudah terdaftar.');
        }

        $data = [
            'plate_no' => $plat,
            'type' => $this->kosongJadiNull($attributes['type'] ?? null),
            'default_driver_id' => $this->idAtauNull($attributes['default_driver_id'] ?? null),
        ];

        $vehicle = $baru
            ? Vehicle::create($data + ['is_active' => true])
            : tap($vehicle, fn ($m) => $m->fill($data)->save());

        $this->catat($vehicle, $baru, 'Kendaraan', $actor);

        return $vehicle->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    public function saveCarrier(?Carrier $carrier, array $attributes, ?User $actor = null): Carrier
    {
        $baru = $carrier === null || ! $carrier->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nama ekspedisi wajib diisi.');
        }

        $data = ['name' => $nama, 'phone' => $this->kosongJadiNull($attributes['phone'] ?? null)];

        $carrier = $baru
            ? Carrier::create($data + ['is_active' => true])
            : tap($carrier, fn ($m) => $m->fill($data)->save());

        $this->catat($carrier, $baru, 'Ekspedisi', $actor);

        return $carrier->refresh();
    }

    /** P-03: data referensi dinonaktifkan, tidak dihapus. */
    public function toggle(Model $model, bool $active, ?User $actor = null): Model
    {
        $model->forceFill(['is_active' => $active])->save();

        activity('master')
            ->performedOn($model)
            ->causedBy($actor)
            ->log($active ? 'Data referensi diaktifkan' : 'Data referensi dinonaktifkan');

        return $model->refresh();
    }

    private function catat(Model $model, bool $baru, string $label, ?User $actor): void
    {
        activity('master')
            ->performedOn($model)
            ->causedBy($actor)
            ->log($label.($baru ? ' dibuat' : ' diubah'));
    }

    private function kosongJadiNull(mixed $value): ?string
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : $teks;
    }

    private function idAtauNull(mixed $value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}
