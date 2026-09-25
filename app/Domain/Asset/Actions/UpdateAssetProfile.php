<?php

declare(strict_types=1);

namespace App\Domain\Asset\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Exceptions\AssetRuleException;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\MeterUnit;
use App\Domain\Master\Models\Serial;
use Carbon\Carbon;

/**
 * Permission: `asset.manage` — profil masa pakai aset (A-66, BR-AST-08):
 * tanggal perolehan, satuan meter, akumulasi meter awal, umur harapan hari
 * dan/atau jam. Tidak ada nilai uang (D-07).
 *
 * Satuan meter dan akumulasinya hanya boleh diubah selama aset di gudang
 * (bukan dipinjam/dalam perjalanan), supaya meter keluar-kembali tetap sebanding.
 */
class UpdateAssetProfile
{
    /** @param  array<string, mixed>  $data */
    public function handle(Serial $serial, array $data, ?User $actor = null): Serial
    {
        if (! $serial->item?->isAsset()) {
            throw AssetRuleException::rule('BR-STK-08', 'Serial '.$serial->serial_no.' bukan aset.');
        }

        $satuan = MeterUnit::tryFrom((string) ($data['meter_unit'] ?? $serial->meter_unit?->value ?? 'none'))
            ?? throw AssetRuleException::field('BR-AST-08', 'meter_unit', 'Satuan meter tidak dikenal.');

        $total = $this->angka($data['meter_total'] ?? null, 'meter_total', 'Akumulasi meter');
        $hari = $this->angka($data['expected_life_days'] ?? null, 'expected_life_days', 'Umur harapan (hari)');
        $jam = $this->angka($data['expected_life_hours'] ?? null, 'expected_life_hours', 'Umur harapan (jam/km)');

        $berjalan = in_array($serial->asset_state, [AssetState::OnLoan, AssetState::InTransit, AssetState::Returned], true);

        if ($berjalan && ($satuan !== $serial->meter_unit || ($total !== null && abs($total - (float) $serial->meter_total) > 0.05))) {
            throw AssetRuleException::field('BR-AST-08', 'meter_unit', 'Satuan dan akumulasi meter tidak bisa diubah selama aset '.$serial->asset_state->label().'.');
        }

        $perolehan = trim((string) ($data['acquired_at'] ?? ''));

        try {
            $tanggal = $perolehan === '' ? null : Carbon::parse($perolehan)->toDateString();
        } catch (\Throwable) {
            throw AssetRuleException::field('BR-AST-08', 'acquired_at', 'Tanggal perolehan tidak valid.');
        }

        $serial->forceFill([
            'acquired_at' => $tanggal,
            'meter_unit' => $satuan,
            'meter_total' => $total ?? $serial->meter_total,
            'expected_life_days' => $hari === null ? null : (int) $hari,
            'expected_life_hours' => $jam,
        ])->save();

        activity('asset')->performedOn($serial)->causedBy($actor)->log('Profil masa pakai aset diubah');

        return $serial->refresh();
    }

    private function angka(mixed $nilai, string $field, string $label): ?float
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        if (! is_numeric($nilai) || (float) $nilai < 0) {
            throw AssetRuleException::field('BR-AST-08', $field, $label.' harus angka ≥ 0.');
        }

        return round((float) $nilai, 1);
    }
}
