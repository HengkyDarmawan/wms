<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Support\QtyFormat;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data halaman **Isi Bin** (K-G, A-374) — tujuan QR label bin: kode pendek,
 * tanda Khusus, isi bin (item, jumlah + uraian kemasan, lot/serial/potongan,
 * kondisi, masuk terakhir), label kemasan di bin itu, dan bin gabungan.
 * Bin tergabung menampilkan isi bin utamanya (stok dicatat di bin utama,
 * A-359). Hanya membaca; tanpa harga (D-07). Query dimuat sekaligus.
 */
class BinContents
{
    /**
     * @return array<string, mixed>
     */
    public function untuk(Bin $bin): array
    {
        $bin->loadMissing('warehouse:id,code,name', 'rackLevel.rack:id,code,is_area', 'mainBin', 'mergedBins');
        $utama = $bin->mainBin ?? $bin;
        $utama->loadMissing('warehouse:id,code,name', 'rackLevel.rack:id,code,is_area', 'mergedBins');

        $saldo = StockBalance::query()->withoutGlobalScopes()
            ->with('item:id,code,name,base_uom_id,tracking_mode', 'item.baseUom:id,code', 'item.activeConversions.uom', 'lot:id,lot_no,expiry_date', 'serial:id,serial_no', 'piece:id,piece_no,length')
            ->where('bin_id', $utama->id)
            ->where('qty_base', '>', 0)
            ->get();

        // Masuk terakhir per item + lot/serial/potongan ke bin ini (satu query).
        $masuk = DB::table('stock_movements')->where('to_bin_id', $utama->id)
            ->groupBy('item_id', 'lot_id', 'serial_id', 'piece_id')
            ->selectRaw('item_id, lot_id, serial_id, piece_id, MAX(occurred_at) as terakhir')
            ->get()
            ->keyBy(fn ($r) => $r->item_id.':'.(int) $r->lot_id.':'.(int) $r->serial_id.':'.(int) $r->piece_id);

        $baris = $saldo->sortBy(fn (StockBalance $s) => $s->item?->code.'|'.$s->lot?->lot_no.'|'.$s->serial?->serial_no)
            ->map(function (StockBalance $s) use ($masuk) {
                $kunci = $s->item_id.':'.(int) $s->lot_id.':'.(int) $s->serial_id.':'.(int) $s->piece_id;
                $tgl = $masuk->get($kunci)?->terakhir;

                return [
                    'item_id' => (int) $s->item_id,
                    'kode' => $s->item?->code,
                    'nama' => $s->item?->name,
                    'qty' => QtyFormat::withUnit($s->qty_base, $s->item?->baseUom?->code),
                    'kemasan' => QtyFormat::packaging($s->item, $s->qty_base),
                    'lacak' => $s->lot !== null ? __('Lot').' '.$s->lot->lot_no.($s->lot->expiry_date ? ' · '.__('kedaluwarsa').' '.$s->lot->expiry_date->format('d/m/Y') : '')
                        : ($s->serial !== null ? __('Serial').' '.$s->serial->serial_no
                        : ($s->piece !== null ? __('Potongan').' '.$s->piece->piece_no : null)),
                    'kondisi' => $s->stock_status->label(),
                    'tersedia' => $s->stock_status->value === 'available',
                    'masuk' => $tgl !== null ? Carbon::parse($tgl, 'UTC')->lokal()->format('d/m/Y') : null,
                ];
            })->values();

        $rakId = $utama->rackLevel?->rack_id;
        $tempat = ItemStorageLocation::query()->withoutGlobalScopes()
            ->with('item:id,code,name')
            ->where(fn ($q) => $q->where('bin_id', $utama->id)->when($rakId !== null, fn ($q) => $q->orWhere('rack_id', $rakId)))
            ->orderByDesc('is_dedicated')->orderBy('sequence')
            ->get()
            ->unique('item_id')
            ->map(fn (ItemStorageLocation $t) => [
                'kode' => $t->item?->code,
                'nama' => $t->item?->name,
                'khusus' => (bool) $t->is_dedicated,
                'seluruh_rak' => $t->bin_id === null,
            ])->values();

        $khusus = app(StoragePolicy::class)->pemilikKhusus($utama);

        $label = PackageLabel::query()->inStock()->where('bin_id', $utama->id)->where('qty_remaining', '>', 0)
            ->with('item:id,code')
            ->orderBy('code')->limit(200)->get(['id', 'code', 'item_id', 'qty_remaining', 'parent_id']);

        $semuaBin = collect([$bin, $utama])->concat($utama->mergedBins)->unique('id');

        return [
            'bin' => $bin,
            'utama' => $utama,
            'pendek' => BinCode::pendekBanyak($semuaBin),
            'tergabung' => $utama->mergedBins->sortBy('code')->values(),
            'khusus' => $khusus === [] ? collect() : Item::query()->whereIn('id', $khusus)->orderBy('code')->get(['id', 'code', 'name']),
            'tempat' => $tempat,
            'baris' => $baris,
            'total' => (float) $saldo->sum('qty_base'),
            'kapasitas' => $utama->effectiveCapacity('capacity_qty'),
            'label' => $label,
        ];
    }
}
