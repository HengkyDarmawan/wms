<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Enums\ItemKind;
use App\Domain\Master\Enums\ItemStatus;
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
        return 'Seluruh item beserta kategori, satuan dasar, jenis barang, dan ambang pesan ulang.';
    }

    public function columns(): array
    {
        return [
            'kode' => 'Kode',
            'nama' => 'Nama',
            'kategori' => 'Kategori',
            'satuan_dasar' => 'Satuan dasar',
            'jenis' => 'Jenis barang',
            'titik_pesan_ulang' => 'Titik pesan ulang',
            'stok_minimum' => 'Stok minimum',
            'status' => 'Status',
        ];
    }

    public function filters(): array
    {
        return [
            'status' => ['label' => 'Status', 'options' => ItemStatus::options()],
            'jenis' => ['label' => 'Jenis barang', 'options' => $this->pilihanJenis()],
        ];
    }

    public function rows(array $filters): Collection
    {
        return Item::query()
            ->with('category:id,name', 'baseUom:id,code')
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['jenis'] ?? '') === 'khusus', fn ($q) => $q->ofKind(null))
            ->when(ItemKind::tryFrom((string) ($filters['jenis'] ?? '')) !== null, fn ($q) => $q->ofKind(ItemKind::from($filters['jenis'])))
            ->orderBy('code')
            ->get()
            ->map(fn (Item $item) => [
                'kode' => $item->code,
                'nama' => $item->name,
                'kategori' => $item->category?->name ?? '—',
                'satuan_dasar' => $item->baseUom?->code ?? '—',
                'jenis' => ItemKind::fromItem($item)?->label() ?? 'Jenis khusus',
                'titik_pesan_ulang' => $item->reorder_point === null ? '' : (float) $item->reorder_point,
                'stok_minimum' => $item->min_stock === null ? '' : (float) $item->min_stock,
                'status' => $item->status->label(),
            ]);
    }

    /** @return array<string, string> A-283: tiga jenis barang + Jenis khusus. */
    private function pilihanJenis(): array
    {
        $hasil = [];

        foreach (ItemKind::cases() as $jenis) {
            $hasil[$jenis->value] = $jenis->label();
        }

        return $hasil + ['khusus' => 'Jenis khusus'];
    }
}
