<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/** Laporan 11-master §9 — daftar item. */
class ItemListReport extends Report
{
    public function key(): string
    {
        return 'daftar-item';
    }

    public function title(): string
    {
        return 'Daftar item';
    }

    public function permission(): string
    {
        return 'item.view';
    }

    public function description(): string
    {
        return 'Seluruh item beserta kategori, satuan dasar, mode pelacakan, dan ambang pesan ulang.';
    }

    public function columns(): array
    {
        return [
            'kode' => 'Kode',
            'nama' => 'Nama',
            'kategori' => 'Kategori',
            'satuan_dasar' => 'Satuan dasar',
            'pelacakan' => 'Mode pelacakan',
            'kepemilikan' => 'Kepemilikan',
            'titik_pesan_ulang' => 'Titik pesan ulang',
            'stok_minimum' => 'Stok minimum',
            'status' => 'Status',
        ];
    }

    public function filters(): array
    {
        return [
            'status' => ['label' => 'Status', 'options' => ItemStatus::options()],
            'tracking_mode' => ['label' => 'Mode pelacakan', 'options' => TrackingMode::options()],
            'ownership_model' => ['label' => 'Kepemilikan', 'options' => OwnershipModel::options()],
        ];
    }

    public function rows(array $filters): Collection
    {
        return Item::query()
            ->with('category:id,name', 'baseUom:id,code')
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['tracking_mode'] ?? '') !== '', fn ($q) => $q->where('tracking_mode', $filters['tracking_mode']))
            ->when(($filters['ownership_model'] ?? '') !== '', fn ($q) => $q->where('ownership_model', $filters['ownership_model']))
            ->orderBy('code')
            ->get()
            ->map(fn (Item $item) => [
                'kode' => $item->code,
                'nama' => $item->name,
                'kategori' => $item->category?->name ?? '—',
                'satuan_dasar' => $item->baseUom?->code ?? '—',
                'pelacakan' => $item->tracking_mode->label(),
                'kepemilikan' => $item->ownership_model->label(),
                'titik_pesan_ulang' => $item->reorder_point === null ? '' : (float) $item->reorder_point,
                'stok_minimum' => $item->min_stock === null ? '' : (float) $item->min_stock,
                'status' => $item->status->label(),
            ]);
    }
}
