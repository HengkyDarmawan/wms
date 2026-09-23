<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 11-master §9 — item sementara yang belum diresmikan.
 *
 * Item `provisional` lahir dari baris permintaan non-katalog (A-51) dan harus
 * dilengkapi Admin sebelum GRN pertama (BR-REQ-03). Umurnya ditampilkan supaya
 * yang menua terlihat.
 */
class ProvisionalItemReport extends Report
{
    public function key(): string
    {
        return 'item-sementara';
    }

    public function title(): string
    {
        return 'Item sementara';
    }

    public function permission(): string
    {
        return 'item.view';
    }

    public function description(): string
    {
        return 'Item berstatus Sementara yang menunggu dilengkapi Admin sebelum penerimaan pertama.';
    }

    public function columns(): array
    {
        return [
            'kode' => 'Kode',
            'nama' => 'Nama',
            'kategori' => 'Kategori',
            'satuan_dasar' => 'Satuan dasar',
            'dibuat' => 'Dibuat',
            'umur_hari' => 'Umur (hari)',
        ];
    }

    public function rows(array $filters): Collection
    {
        $zona = tenant()?->timezone ?? 'Asia/Jakarta';

        return Item::query()
            ->with('category:id,name', 'baseUom:id,code')
            ->where('status', ItemStatus::Provisional->value)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Item $item) => [
                'kode' => $item->code,
                'nama' => $item->name,
                'kategori' => $item->category?->name ?? '—',
                'satuan_dasar' => $item->baseUom?->code ?? '—',
                'dibuat' => $item->created_at?->timezone($zona)->format('d/m/Y'),
                'umur_hari' => $item->created_at === null
                    ? ''
                    : (int) $item->created_at->startOfDay()->diffInDays(now()->startOfDay()),
            ]);
    }
}
