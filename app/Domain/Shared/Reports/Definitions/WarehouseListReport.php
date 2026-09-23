<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Report;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Support\Collection;

/** Laporan 12-warehouse §9 — daftar gudang. */
class WarehouseListReport extends Report
{
    public function key(): string
    {
        return 'daftar-gudang';
    }

    public function title(): string
    {
        return 'Daftar gudang';
    }

    public function permission(): string
    {
        return 'warehouse.view';
    }

    public function description(): string
    {
        return 'Gudang beserta tipe, induk, proyek, kepala gudang, dan jumlah zona serta bin.';
    }

    public function columns(): array
    {
        return [
            'kode' => 'Kode',
            'nama' => 'Nama',
            'tipe' => 'Tipe',
            'induk' => 'Gudang induk',
            'proyek' => 'Proyek',
            'kepala' => 'Kepala gudang',
            'zona' => 'Jumlah zona',
            'bin' => 'Jumlah bin',
            'status' => 'Status',
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse_type_id' => [
                'label' => 'Tipe',
                'options' => WarehouseType::query()->orderBy('name')->pluck('name', 'id')->all(),
            ],
            'status' => ['label' => 'Status', 'options' => ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']],
        ];
    }

    public function rows(array $filters): Collection
    {
        // Memakai global scope: laporan ikut cakupan penugasan role (BR-ACC-05).
        return Warehouse::query()
            ->with('type:id,name', 'parent:id,name', 'project:id,code,name', 'head:id,name')
            ->withCount(['zones', 'bins'])
            ->when(($filters['warehouse_type_id'] ?? '') !== '', fn ($q) => $q->where('warehouse_type_id', (int) $filters['warehouse_type_id']))
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('is_active', $filters['status'] === 'aktif'))
            ->orderBy('code')
            ->get()
            ->map(fn (Warehouse $gudang) => [
                'kode' => $gudang->code,
                'nama' => $gudang->name,
                'tipe' => $gudang->type?->name ?? '—',
                'induk' => $gudang->parent?->name ?? '—',
                'proyek' => $gudang->project?->code ?? '—',
                'kepala' => $gudang->head?->name ?? '—',
                'zona' => $gudang->zones_count,
                'bin' => $gudang->bins_count,
                'status' => $gudang->is_active ? 'Aktif' : 'Nonaktif',
            ]);
    }
}
