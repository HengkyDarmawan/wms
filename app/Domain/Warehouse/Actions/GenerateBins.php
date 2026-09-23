<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Support\BinCodeBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `bin.manage` — membuat banyak bin sekaligus pada satu level rak.
 *
 * Gudang baru bisa punya ratusan bin; mengetiknya satu per satu adalah cara
 * paling mudah menghasilkan kode yang tidak konsisten. Kodenya tetap dibangun
 * {@see BinCodeBuilder} sehingga BR-WH-01 berlaku sama.
 */
class GenerateBins
{
    private const MAKSIMUM = 200;

    /**
     * @param  array<string, mixed>  $attributes  kapasitas dan kategori penyimpanan untuk semua bin
     * @return array<int, Bin>
     */
    public function handle(RackLevel $level, int $jumlah, array $attributes = [], ?User $actor = null): array
    {
        if ($jumlah < 1) {
            throw WarehouseRuleException::fields(['jumlah' => 'Jumlah bin minimal satu.'], 'BR-GEN-11');
        }

        if ($jumlah > self::MAKSIMUM) {
            throw WarehouseRuleException::fields(
                ['jumlah' => 'Sekali buat maksimum '.self::MAKSIMUM.' bin.'],
                'BR-WH-01',
            );
        }

        $level->loadMissing('rack.zone.warehouse');
        $warehouse = $level->rack?->zone?->warehouse;

        if ($warehouse === null) {
            throw WarehouseRuleException::rule('BR-WH-01', 'Level rak ini tidak terhubung ke gudang mana pun.');
        }

        $prefix = BinCodeBuilder::segment((string) ($attributes['prefix'] ?? 'B')) ?: 'B';

        $dibuat = DB::transaction(function () use ($level, $warehouse, $jumlah, $attributes, $prefix): array {
            $hasil = [];

            for ($i = 0; $i < $jumlah; $i++) {
                // Nomor berikutnya dihitung ulang tiap putaran supaya kode yang
                // sudah ada di level itu tidak tertabrak.
                $segmen = BinCodeBuilder::nextSequence($level, $prefix);
                $kode = BinCodeBuilder::forRackLevel($level, $segmen);

                if (Bin::query()->withoutGlobalScopes()->where('code', $kode)->exists()) {
                    continue;
                }

                $hasil[] = Bin::create([
                    'warehouse_id' => $warehouse->id,
                    'rack_level_id' => $level->id,
                    'code' => $kode,
                    'bin_type' => BinType::Storage,
                    'bin_status' => BinStatus::Active,
                    'storage_category_id' => ($attributes['storage_category_id'] ?? null) ?: null,
                    'capacity_qty' => $this->angkaAtauNull($attributes['capacity_qty'] ?? null),
                    'capacity_weight' => $this->angkaAtauNull($attributes['capacity_weight'] ?? null),
                    'capacity_volume' => $this->angkaAtauNull($attributes['capacity_volume'] ?? null),
                    'capacity_length' => $this->angkaAtauNull($attributes['capacity_length'] ?? null),
                    'is_virtual' => false,
                ]);
            }

            return $hasil;
        });

        activity('warehouse')
            ->performedOn($level)
            ->causedBy($actor)
            ->withProperties(['jumlah' => count($dibuat)])
            ->log('Bin dibuat massal');

        return $dibuat;
    }

    private function angkaAtauNull(mixed $value): ?float
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : (float) str_replace(',', '.', $teks);
    }
}
