<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Models\Item;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * **Tempat Simpan** (K-A, A-365): satu item di satu gudang pada satu bin
 * tertentu, seluruh rak, atau area lantai — berurutan (`sequence`) dan boleh
 * ditandai **Khusus Barang Ini** (`is_dedicated`, A-366).
 *
 * Jenis dibaca dari kolom yang terisi: `bin_id` = bin; `rack_id` pada rak
 * biasa = seluruh rak; `rack_id` pada rak area = area lantai. Tanpa enum baru.
 * Baris ini konfigurasi (bukan transaksi), jadi diganti/dihapus apa adanya
 * dengan jejak riwayat pada item (A-365).
 */
class ItemStorageLocation extends Model
{
    use ScopedToUser;

    public const JENIS_BIN = 'bin';

    public const JENIS_RAK = 'rak';

    public const JENIS_AREA = 'area';

    protected $table = 'item_storage_locations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_dedicated' => 'boolean',
        ];
    }

    /** BR-ACC-05: tempat simpan ikut cakupan gudangnya. */
    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class)->withoutGlobalScopes();
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /** `bin` | `rak` | `area`. */
    public function jenis(): string
    {
        if ($this->bin_id !== null) {
            return self::JENIS_BIN;
        }

        return $this->rack?->is_area ? self::JENIS_AREA : self::JENIS_RAK;
    }

    /** Kunci tempat untuk isian & browser: `bin:12` atau `rak:5`. */
    public function kunci(): string
    {
        return $this->bin_id !== null ? 'bin:'.$this->bin_id : 'rak:'.$this->rack_id;
    }

    /** Label tampilan: kode pendek bin, "Rak R01 (seluruh rak)", atau "Area AB1". */
    public function label(): string
    {
        if ($this->bin_id !== null) {
            return $this->bin !== null ? BinCode::pendek((string) $this->bin->code) : '#'.$this->bin_id;
        }

        $rak = $this->rack;

        if ($rak === null) {
            return '#'.$this->rack_id;
        }

        $zona = $rak->zone?->code;

        return $rak->is_area
            ? __('Area :kode', ['kode' => ($zona ? $zona.' · ' : '').$rak->code])
            : __('Rak :kode (seluruh rak)', ['kode' => ($zona ? $zona.' · ' : '').$rak->code]);
    }
}
