<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\StorageDedicationOverride;

/**
 * **Khusus Barang Ini** (BR-WH-10, A-366/A-367) — satu layanan pusat untuk
 * jalur yang **menaruh** barang ke bin penyimpanan: put-away, pilah retur,
 * penyesuaian (+), dan saldo awal. Opname **tidak** memakainya (opname
 * mencatat kenyataan, keputusan #2).
 *
 * Bin dianggap khusus bila ada tempat simpan ber-`is_dedicated` yang
 * menunjuk bin itu, atau seluruh rak/area lantainya. Barang yang tidak ada di
 * daftar pemiliknya ditolak, kecuali Kepala Gudang (izin `adjustment.approve`,
 * keputusan #5) membukanya dengan alasan wajib — tercatat di
 * `storage_dedication_overrides`. Stok tetap hanya lewat StockLedger (P-01);
 * layanan ini hanya memeriksa sebelum pergerakan dibuat.
 */
class StoragePolicy
{
    public const IZIN_BUKA = 'adjustment.approve';

    /**
     * Item yang memiliki bin ini secara khusus (kosong = bin bebas).
     *
     * @return array<int, int>
     */
    public function pemilikKhusus(Bin $bin): array
    {
        $id = (int) $bin->id;
        $rakId = $bin->rack_level_id !== null ? (int) $bin->rackLevel()->value('rack_id') : null;

        $pemilik = ItemStorageLocation::query()->withoutGlobalScopes()
            ->where('is_dedicated', true)
            ->where(fn ($q) => $q->where('bin_id', $id)->when($rakId !== null, fn ($q) => $q->orWhere('rack_id', $rakId)))
            ->distinct()->pluck('item_id')->map(fn ($v) => (int) $v)->values()->all();

        return $pemilik;
    }

    /** Pesan penolakan, atau null bila item boleh masuk bin ini. */
    public function tolakan(Bin $bin, Item $item): ?string
    {
        $pemilik = $this->pemilikKhusus($bin);

        if ($pemilik === [] || in_array((int) $item->id, $pemilik, true)) {
            return null;
        }

        $kode = Item::query()->whereIn('id', $pemilik)->orderBy('code')->pluck('code')->implode(', ');

        return 'Bin '.BinCode::pendek((string) $bin->code).' khusus untuk barang '.$kode.'; '.$item->code
            .' ditaruh di tempat lain, atau Kepala Gudang membukanya dengan alasan.';
    }

    /**
     * Tolak barang lain di bin khusus, kecuali dibuka Kepala Gudang dengan
     * alasan (dicatat). Mengembalikan catatan pembukaan bila dibuka.
     *
     * @param  array{type?: ?string, id?: ?int, number?: ?string}  $dokumen
     */
    public function assertBolehMasuk(Bin $bin, Item $item, ?string $alasanBuka = null, ?User $actor = null, array $dokumen = []): ?StorageDedicationOverride
    {
        $pesan = $this->tolakan($bin, $item);

        if ($pesan === null) {
            return null;
        }

        $alasan = trim((string) $alasanBuka);

        if ($alasan === '') {
            throw WarehouseRuleException::rule('BR-WH-10', $pesan);
        }

        if ($actor === null || ! $actor->hasPermission(self::IZIN_BUKA)) {
            throw WarehouseRuleException::rule('BR-WH-10', 'Hanya Kepala Gudang (izin setujui penyesuaian) yang boleh membuka tempat khusus. '.$pesan);
        }

        return $this->catatBuka($bin, $item, $alasan, $actor, $dokumen);
    }

    /**
     * Periksa saja (tanpa mencatat) — dipakai sebelum dokumennya lahir; catatan
     * dibuat kemudian lewat {@see catatBuka()}.
     */
    public function periksa(Bin $bin, Item $item, ?string $alasanBuka, ?User $actor): bool
    {
        $pesan = $this->tolakan($bin, $item);

        if ($pesan === null) {
            return false;
        }

        if (trim((string) $alasanBuka) === '') {
            throw WarehouseRuleException::rule('BR-WH-10', $pesan);
        }

        if ($actor === null || ! $actor->hasPermission(self::IZIN_BUKA)) {
            throw WarehouseRuleException::rule('BR-WH-10', 'Hanya Kepala Gudang (izin setujui penyesuaian) yang boleh membuka tempat khusus. '.$pesan);
        }

        return true;
    }

    /** @param  array{type?: ?string, id?: ?int, number?: ?string}  $dokumen */
    public function catatBuka(Bin $bin, Item $item, string $alasan, ?User $actor, array $dokumen = []): StorageDedicationOverride
    {
        $catatan = StorageDedicationOverride::create([
            'bin_id' => $bin->id,
            'item_id' => $item->id,
            'reason' => mb_substr(trim($alasan), 0, 255),
            'document_type' => $dokumen['type'] ?? null,
            'document_id' => $dokumen['id'] ?? null,
            'document_number' => $dokumen['number'] ?? null,
            'opened_by' => $actor?->id,
            'opened_at' => now(),
        ]);

        activity('warehouse')->performedOn($bin)->causedBy($actor)
            ->withProperties(['item' => $item->code, 'alasan' => $catatan->reason, 'dokumen' => $dokumen['number'] ?? null])
            ->log('Tempat khusus dibuka untuk '.$item->code);

        return $catatan;
    }
}
