<?php

declare(strict_types=1);

namespace App\Domain\Asset\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Enums\ConditionGrade;
use App\Domain\Asset\Exceptions\AssetRuleException;
use App\Domain\Asset\Models\AssetHandover;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `asset.manage` — melengkapi serah terima keluar AST yang masih
 * `checked_out`: tanggal kembali, meter keluar, grade kondisi keluar
 * (Blueprint §6.8, BR-AST-08, A-163). Tidak mengubah status maupun stok.
 */
class UpdateAssetHandover
{
    /** @param  array{due_return_date?: mixed, meter_out?: mixed, condition_out?: mixed, notes?: mixed}  $data */
    public function handle(AssetHandover $ast, array $data, ?User $actor = null): AssetHandover
    {
        if ($ast->status !== AssetHandoverStatus::CheckedOut || $ast->lost_at !== null) {
            throw AssetRuleException::rule('BR-GEN-01', 'Serah terima hanya bisa dilengkapi selama aset masih dipinjam.');
        }

        $tanggal = null;
        $isiTanggal = trim((string) ($data['due_return_date'] ?? ''));

        if ($isiTanggal !== '') {
            try {
                $tanggal = Carbon::parse($isiTanggal)->startOfDay();
            } catch (\Throwable) {
                throw AssetRuleException::field('BR-AST-06', 'due_return_date', 'Tanggal kembali tidak valid.');
            }

            if ($tanggal->lt($ast->checked_out_at->copy()->startOfDay())) {
                throw AssetRuleException::field('BR-AST-06', 'due_return_date', 'Tanggal kembali tidak boleh sebelum tanggal keluar.');
            }
        }

        $meter = $data['meter_out'] ?? null;

        if ($meter !== null && $meter !== '' && (! is_numeric($meter) || (float) $meter < 0)) {
            throw AssetRuleException::field('BR-AST-08', 'meter_out', 'Meter keluar harus angka ≥ 0.');
        }

        $grade = trim((string) ($data['condition_out'] ?? ''));
        $grade = $grade === '' ? null : (ConditionGrade::tryFrom($grade) ?? throw AssetRuleException::field('BR-AST-03', 'condition_out', 'Grade kondisi A–D.'));

        return DB::transaction(function () use ($ast, $tanggal, $meter, $grade, $data, $actor) {
            $ast->forceFill([
                'due_return_date' => $tanggal?->toDateString(),
                'meter_out' => $meter === null || $meter === '' ? $ast->meter_out : round((float) $meter, 1),
                'condition_out' => $grade?->value ?? $ast->condition_out,
                'notes' => $this->teks($data['notes'] ?? null) ?? $ast->notes,
                'updated_by' => $actor?->id,
            ])->save();

            $ast->serial?->forceFill(['due_return_date' => $tanggal?->toDateString()])->save();

            activity('asset')->performedOn($ast)->causedBy($actor)->log('Serah terima dilengkapi');

            return $ast->refresh();
        });
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}
