<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\BinCodeBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `bin.manage`.
 *
 * BR-WH-01: kode bin diturunkan dari hierarki dan terkunci setelah dibuat.
 * BR-WH-03: bin `on_site` wajib punya proyek. Bin bawaan dan bin virtual dibuat
 * {@see EnsureSystemBins}, bukan lewat aksi ini.
 */
class SaveBin
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(Warehouse $warehouse, ?Bin $bin, array $attributes, ?User $actor = null): Bin
    {
        $baru = $bin === null || ! $bin->exists;

        $jenis = $this->resolveType($attributes, $bin);

        if ($baru && $jenis->isSystemDefault()) {
            throw WarehouseRuleException::fields(
                ['bin_type' => 'Bin '.$jenis->label().' dibuat otomatis untuk setiap gudang (BR-WH-02).'],
                'BR-WH-02',
            );
        }

        $level = $this->resolveLevel($warehouse, $attributes, $bin, $jenis);
        $projectId = $this->resolveProject($attributes, $jenis);

        $kode = $baru
            ? $this->buildCode($warehouse, $level, $jenis, $projectId, $attributes)
            : $this->lockedCode($bin, $attributes);

        $bentrok = Bin::query()->withoutGlobalScopes()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($bin->getKey()))
            ->exists();

        if ($bentrok) {
            throw WarehouseRuleException::fields(['code' => 'Kode bin "'.$kode.'" sudah dipakai.'], 'BR-WH-01');
        }

        $data = [
            'warehouse_id' => $warehouse->id,
            'rack_level_id' => $level?->id,
            'code' => $kode,
            'bin_type' => $jenis,
            'storage_category_id' => $this->resolveStorageCategory($attributes),
            'capacity_qty' => $this->angkaAtauNull($attributes['capacity_qty'] ?? null),
            'capacity_weight' => $this->angkaAtauNull($attributes['capacity_weight'] ?? null),
            'capacity_volume' => $this->angkaAtauNull($attributes['capacity_volume'] ?? null),
            'capacity_length' => $this->angkaAtauNull($attributes['capacity_length'] ?? null),
            'project_id' => $projectId,
            'is_virtual' => $jenis->isVirtual(),
        ];

        $bin = DB::transaction(function () use ($bin, $baru, $data): Bin {
            if ($baru) {
                return Bin::create($data + ['bin_status' => BinStatus::Active]);
            }

            $bin->fill($data)->save();

            return $bin;
        });

        activity('warehouse')
            ->performedOn($bin)
            ->causedBy($actor)
            ->withProperties(['bin_type' => $jenis->value])
            ->log($baru ? 'Bin dibuat' : 'Bin diubah');

        return $bin->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    private function resolveType(array $attributes, ?Bin $bin): BinType
    {
        $nilai = $attributes['bin_type'] ?? $bin?->bin_type ?? BinType::Storage;

        if ($nilai instanceof BinType) {
            return $nilai;
        }

        $jenis = BinType::tryFrom((string) $nilai);

        if ($jenis === null) {
            throw WarehouseRuleException::fields(['bin_type' => 'Jenis bin tidak dikenal.'], 'AD-14');
        }

        return $jenis;
    }

    /**
     * Bin virtual tidak menempel pada rak; bin penyimpanan wajib.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveLevel(Warehouse $warehouse, array $attributes, ?Bin $bin, BinType $jenis): ?RackLevel
    {
        if ($jenis->isVirtual()) {
            return null;
        }

        $id = $this->idAtauNull($attributes['rack_level_id'] ?? null) ?? $bin?->rack_level_id;

        if ($id === null) {
            throw WarehouseRuleException::fields(
                ['rack_level_id' => 'Level rak wajib dipilih untuk bin penyimpanan.'],
                'BR-GEN-11',
            );
        }

        $level = RackLevel::query()->with('rack.zone')->find($id);

        if ($level === null || (int) $level->rack?->zone?->warehouse_id !== (int) $warehouse->id) {
            throw WarehouseRuleException::fields(
                ['rack_level_id' => 'Level rak tidak ditemukan di gudang ini.'],
                'BR-WH-01',
            );
        }

        return $level;
    }

    /** BR-WH-03: hanya bin on_site yang terikat proyek, dan wajib punya. */
    private function resolveProject(array $attributes, BinType $jenis): ?int
    {
        $projectId = $this->idAtauNull($attributes['project_id'] ?? null);

        if ($jenis->requiresProject()) {
            if ($projectId === null) {
                throw WarehouseRuleException::fields(
                    ['project_id' => 'Bin On-site Proyek wajib terikat satu proyek (BR-WH-03).'],
                    'BR-WH-03',
                );
            }

            if (! Project::query()->whereKey($projectId)->exists()) {
                throw WarehouseRuleException::fields(['project_id' => 'Proyek tidak ditemukan.'], 'BR-WH-03');
            }

            return $projectId;
        }

        if ($projectId !== null) {
            throw WarehouseRuleException::fields(
                ['project_id' => 'Hanya bin On-site Proyek yang boleh terikat proyek (BR-WH-03).'],
                'BR-WH-03',
            );
        }

        return null;
    }

    /** @param  array<string, mixed>  $attributes */
    private function resolveStorageCategory(array $attributes): ?int
    {
        $id = $this->idAtauNull($attributes['storage_category_id'] ?? null);

        if ($id === null) {
            return null;
        }

        if (! StorageCategory::query()->whereKey($id)->exists()) {
            throw WarehouseRuleException::fields(
                ['storage_category_id' => 'Kategori penyimpanan tidak ditemukan.'],
                'BR-WH-06',
            );
        }

        return $id;
    }

    /** @param  array<string, mixed>  $attributes */
    private function buildCode(Warehouse $warehouse, ?RackLevel $level, BinType $jenis, ?int $projectId, array $attributes): string
    {
        if ($jenis === BinType::OnSite) {
            $kodeProyek = (string) Project::query()->whereKey($projectId)->value('code');

            return BinCodeBuilder::forOnSiteBin($warehouse, $kodeProyek);
        }

        if ($level === null) {
            return BinCodeBuilder::forSystemBin($warehouse, $jenis);
        }

        $segmen = BinCodeBuilder::segment((string) ($attributes['code'] ?? ''));

        if ($segmen === '') {
            $segmen = BinCodeBuilder::nextSequence($level);
        }

        return BinCodeBuilder::forRackLevel($level, $segmen);
    }

    /** @param  array<string, mixed>  $attributes */
    private function lockedCode(Bin $bin, array $attributes): string
    {
        $diminta = BinCodeBuilder::segment((string) ($attributes['code'] ?? ''));
        $lama = (string) $bin->code;

        // Kode penuh maupun segmen terakhirnya sama-sama diterima sebagai "tidak berubah".
        $segmenTerakhir = BinCodeBuilder::segment(substr((string) strrchr($lama, '-'), 1));

        if ($diminta !== '' && $diminta !== BinCodeBuilder::segment($lama) && $diminta !== $segmenTerakhir) {
            throw WarehouseRuleException::fields(
                ['code' => 'Kode bin tidak bisa diubah setelah dibuat; kode itu sudah tercetak di label (BR-WH-01).'],
                'BR-WH-01',
            );
        }

        return $lama;
    }

    private function angkaAtauNull(mixed $value): ?float
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : (float) str_replace(',', '.', $teks);
    }

    private function idAtauNull(mixed $value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}
