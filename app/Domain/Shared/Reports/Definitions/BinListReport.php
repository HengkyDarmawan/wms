<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\StorageCategory;
use App\Domain\Shared\Reports\Report;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/** Laporan 12-warehouse §9 — daftar bin beserta kapasitas dan penegakannya. */
class BinListReport extends Report
{
    public function key(): string
    {
        return 'daftar-bin';
    }

    public function title(): string
    {
        return 'Daftar bin';
    }

    public function permission(): string
    {
        return 'bin.view';
    }

    public function description(): string
    {
        return 'Bin beserta gudang, jenis, kategori penyimpanan, kapasitas, dan penanda hitung.';
    }

    public function columns(): array
    {
        return [
            'kode' => 'Kode bin',
            'gudang' => 'Gudang',
            'jenis' => 'Jenis',
            'kategori' => 'Kategori penyimpanan',
            'penegakan' => 'Penegakan kapasitas',
            'kapasitas_jumlah' => 'Kapasitas jumlah',
            'kapasitas_berat' => 'Kapasitas berat',
            'proyek' => 'Proyek',
            'penanda_hitung' => 'Perlu dihitung',
            'status' => 'Status',
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse_id' => [
                'label' => 'Gudang',
                'options' => Warehouse::query()->orderBy('code')->pluck('name', 'id')->all(),
            ],
            'bin_type' => ['label' => 'Jenis', 'options' => BinType::options()],
            'bin_status' => ['label' => 'Status', 'options' => BinStatus::options()],
            'storage_category_id' => [
                'label' => 'Kategori penyimpanan',
                'options' => StorageCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            ],
        ];
    }

    public function rows(array $filters): Collection
    {
        return Bin::query()
            ->with('warehouse:id,code', 'storageCategory:id,name,capacity_mode', 'project:id,code')
            ->when(($filters['warehouse_id'] ?? '') !== '', fn ($q) => $q->where('warehouse_id', (int) $filters['warehouse_id']))
            ->when(($filters['bin_type'] ?? '') !== '', fn ($q) => $q->where('bin_type', $filters['bin_type']))
            ->when(($filters['bin_status'] ?? '') !== '', fn ($q) => $q->where('bin_status', $filters['bin_status']))
            ->when(($filters['storage_category_id'] ?? '') !== '', fn ($q) => $q->where('storage_category_id', (int) $filters['storage_category_id']))
            ->orderBy('code')
            ->get()
            ->map(fn (Bin $bin) => [
                'kode' => $bin->code,
                'gudang' => $bin->warehouse?->code ?? '—',
                'jenis' => $bin->bin_type->label(),
                'kategori' => $bin->storageCategory?->name ?? '—',
                'penegakan' => $bin->capacityMode()->label(),
                'kapasitas_jumlah' => $bin->capacity_qty === null ? '' : (float) $bin->capacity_qty,
                'kapasitas_berat' => $bin->capacity_weight === null ? '' : (float) $bin->capacity_weight,
                'proyek' => $bin->project?->code ?? '—',
                'penanda_hitung' => $bin->count_flag ? 'Ya' : '',
                'status' => $bin->bin_status->label(),
            ]);
    }
}
