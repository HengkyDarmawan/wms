<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Models;

use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Master\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resep konversi (BR-CNV-06, A-431): templat input → hasil yang mengisi form
 * konversi. Hanya mempercepat pengisian, tidak mengunci hasil nyata; semua
 * isian tetap bisa diubah sebelum disimpan.
 *
 * `definition`: {conversion_type, input_item_id?, potong: [{item_id?, length, count}],
 * hasil: [{kind: output|waste, item_id, qty}]}
 */
class ConversionRecipe extends Model
{
    protected $table = 'conversion_recipes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function type(): ?ConversionType
    {
        return ConversionType::tryFrom((string) ($this->definition['conversion_type'] ?? ''));
    }

    public function inputItem(): ?Item
    {
        $id = (int) ($this->definition['input_item_id'] ?? 0);

        return $id > 0 ? Item::query()->find($id) : null;
    }

    /** @return array<int, array{item_id: ?int, length: float, count: int}> */
    public function potong(): array
    {
        return array_values((array) ($this->definition['potong'] ?? []));
    }

    /** @return array<int, array{kind: string, item_id: int, qty: float}> */
    public function hasil(): array
    {
        return array_values((array) ($this->definition['hasil'] ?? []));
    }

    /** Ringkasan satu baris untuk daftar & pilihan, mis. "Potong: 4 × 1,5 + 1 × 0,4". */
    public function ringkasan(): string
    {
        $angka = fn (float $n) => rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');

        if ($this->type() === ConversionType::Cut) {
            return $this->type()->label().': '.implode(' + ', array_map(fn ($p) => (int) $p['count'].' × '.$angka((float) $p['length']), $this->potong()));
        }

        $kode = Item::query()->whereIn('id', array_column($this->hasil(), 'item_id'))->pluck('code', 'id');

        return ($this->type()?->label() ?? '-').': '.implode(', ', array_map(fn ($h) => $angka((float) $h['qty']).' '.($kode[$h['item_id']] ?? '?').($h['kind'] === 'waste' ? ' (waste)' : ''), $this->hasil()));
    }
}
