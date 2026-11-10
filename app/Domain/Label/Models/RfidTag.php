<?php

declare(strict_types=1);

namespace App\Domain\Label\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Model;

/**
 * Tag RFID (Blueprint §6.10, A-437): EPC → item, serial, potongan, atau bin.
 * Reader keyboard wedge mengetik EPC seperti pemindai barcode; EPC
 * diterjemahkan oleh ScanCode (barang) dan BinCode (bin) sehingga semua layar
 * pindai yang ada langsung menerima tag.
 */
class RfidTag extends Model
{
    public const TARGET = ['item' => 'Barang', 'serial' => 'Serial / aset', 'piece' => 'Potongan', 'bin' => 'Bin'];

    protected $table = 'rfid_tags';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function normalize(string $epc): string
    {
        return mb_strtoupper(preg_replace('/[\s:-]+/', '', trim($epc)) ?? '');
    }

    /** Tag aktif untuk EPC ini, atau null. EPC pendek (< 8) diabaikan supaya kode biasa tidak tertukar. */
    public static function aktif(string $raw): ?self
    {
        $epc = self::normalize($raw);

        if (mb_strlen($epc) < 8 || ! ctype_xdigit($epc)) {
            return null;
        }

        return static::query()->where('epc', $epc)->where('is_active', true)->first();
    }

    /** Kode yang dikenali layar pindai untuk target ini (kode item/bin, nomor serial/potongan). */
    public function kodeTarget(): ?string
    {
        return match ($this->target_type) {
            'item' => Item::query()->whereKey($this->target_id)->value('code'),
            'serial' => Serial::query()->whereKey($this->target_id)->value('serial_no'),
            'piece' => Piece::query()->whereKey($this->target_id)->value('piece_no'),
            'bin' => Bin::query()->withoutGlobalScopes()->whereKey($this->target_id)->value('code'),
            default => null,
        };
    }
}
