<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Support\MasterCode;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `warehouse.create` / `warehouse.update`.
 *
 * Menjaga BR-WH-04 (Gudang Site wajib proyek, tipe lain wajib tanpa proyek),
 * BR-WH-05 (hierarki tidak melingkar), dan BR-MST-01 (kode huruf besar dan
 * terkunci). Gudang baru langsung mendapat bin bawaan lewat {@see EnsureSystemBins}
 * (BR-WH-02).
 */
class SaveWarehouse
{
    public function __construct(private readonly EnsureSystemBins $systemBins) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(?Warehouse $warehouse, array $attributes, ?User $actor = null): Warehouse
    {
        $baru = $warehouse === null || ! $warehouse->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw WarehouseRuleException::fields(['name' => 'Nama gudang wajib diisi.'], 'BR-GEN-11');
        }

        $kode = $this->resolveCode($warehouse, $attributes, $baru);
        $tipe = $this->resolveType($attributes, $warehouse);
        $projectId = $this->resolveProject($attributes, $tipe);
        $parentId = $this->resolveParent($warehouse, $attributes, $baru);

        $data = [
            'code' => $kode,
            'name' => $nama,
            'warehouse_type_id' => $tipe->id,
            'parent_id' => $parentId,
            'project_id' => $projectId,
            'head_user_id' => $this->idAtauNull($attributes['head_user_id'] ?? null),
            'address' => $this->kosongJadiNull($attributes['address'] ?? null),
        ];

        $warehouse = DB::transaction(function () use ($warehouse, $baru, $data): Warehouse {
            if ($baru) {
                $warehouse = Warehouse::create($data + ['is_active' => true]);
            } else {
                $warehouse->fill($data)->save();
            }

            return $warehouse;
        });

        // BR-WH-02: bin bawaan dan bin virtual dibuat setelah gudang ada.
        $this->systemBins->handle($warehouse, $actor);

        activity('warehouse')
            ->performedOn($warehouse)
            ->causedBy($actor)
            ->withProperties(['type' => $tipe->code])
            ->log($baru ? 'Gudang dibuat' : 'Gudang diubah');

        return $warehouse->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    private function resolveCode(?Warehouse $warehouse, array $attributes, bool $baru): string
    {
        $kode = MasterCode::resolve($warehouse, (string) ($attributes['code'] ?? ''), 'gudang');

        $bentrok = Warehouse::query()->withoutGlobalScopes()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($warehouse->getKey()))
            ->exists();

        if ($bentrok) {
            throw WarehouseRuleException::fields(
                ['code' => 'Kode gudang "'.$kode.'" sudah dipakai.'],
                'BR-MST-01',
            );
        }

        return $kode;
    }

    /** @param  array<string, mixed>  $attributes */
    private function resolveType(array $attributes, ?Warehouse $warehouse): WarehouseType
    {
        $id = $this->idAtauNull($attributes['warehouse_type_id'] ?? null) ?? $warehouse?->warehouse_type_id;

        if ($id === null) {
            throw WarehouseRuleException::fields(
                ['warehouse_type_id' => 'Tipe gudang wajib dipilih.'],
                'BR-GEN-11',
            );
        }

        $tipe = WarehouseType::query()->find($id);

        if ($tipe === null) {
            throw WarehouseRuleException::fields(
                ['warehouse_type_id' => 'Tipe gudang tidak ditemukan.'],
                'BR-GEN-11',
            );
        }

        return $tipe;
    }

    /**
     * BR-WH-04: Gudang Site wajib punya proyek; tipe lain wajib tidak punya,
     * supaya "gudang milik proyek" tidak pernah ambigu.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveProject(array $attributes, WarehouseType $tipe): ?int
    {
        $projectId = $this->idAtauNull($attributes['project_id'] ?? null);

        if ($tipe->isSite()) {
            if ($projectId === null) {
                throw WarehouseRuleException::fields(
                    ['project_id' => 'Gudang Site wajib terikat satu proyek (BR-WH-04).'],
                    'BR-WH-04',
                );
            }

            if (! Project::query()->whereKey($projectId)->exists()) {
                throw WarehouseRuleException::fields(
                    ['project_id' => 'Proyek tidak ditemukan.'],
                    'BR-WH-04',
                );
            }

            return $projectId;
        }

        if ($projectId !== null) {
            throw WarehouseRuleException::fields(
                ['project_id' => 'Hanya Gudang Site yang boleh terikat proyek (BR-WH-04).'],
                'BR-WH-04',
            );
        }

        return null;
    }

    /**
     * BR-WH-05: hierarki gudang tidak boleh melingkar.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveParent(?Warehouse $warehouse, array $attributes, bool $baru): ?int
    {
        $parentId = $this->idAtauNull($attributes['parent_id'] ?? null);

        if ($parentId === null) {
            return null;
        }

        if (! $baru && $parentId === (int) $warehouse->getKey()) {
            throw WarehouseRuleException::fields(
                ['parent_id' => 'Gudang tidak boleh menjadi induk dirinya sendiri.'],
                'BR-WH-05',
            );
        }

        if (! Warehouse::query()->withoutGlobalScopes()->whereKey($parentId)->exists()) {
            throw WarehouseRuleException::fields(['parent_id' => 'Gudang induk tidak ditemukan.'], 'BR-WH-05');
        }

        if (! $baru) {
            $this->tolakLingkaran((int) $warehouse->getKey(), $parentId);
        }

        return $parentId;
    }

    /** Menelusuri rantai induk; bila kembali ke gudang ini, hierarkinya melingkar. */
    private function tolakLingkaran(int $warehouseId, int $parentId): void
    {
        $id = $parentId;
        $batas = 0;

        while ($id !== null && $batas < 20) {
            if ($id === $warehouseId) {
                throw WarehouseRuleException::fields(
                    ['parent_id' => 'Hierarki gudang tidak boleh melingkar (BR-WH-05).'],
                    'BR-WH-05',
                );
            }

            $induk = Warehouse::query()->withoutGlobalScopes()->whereKey($id)->value('parent_id');
            $id = $induk === null ? null : (int) $induk;
            $batas++;
        }
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
